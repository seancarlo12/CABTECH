<?php
// handlers/verify_email_code.php
header('Content-Type: application/json');
session_name('CABTECH_WEBSITE');
session_start();
$response = [];

// include DB
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);

// --- Skip verification if already logged in ---
if (isset($_SESSION['client_id']) && !empty($_SESSION['client_id'])) {
    $client = null;
    $vehicles = [];

    // Fetch client + vehicles using session client_id
    if (isset($db_connection)) {
        $stmt = mysqli_prepare($db_connection, "SELECT client_id, first_name, middle_name, last_name, contact_number, email, address, status, account_id FROM clientstbl WHERE client_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['client_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $client = $row;

                // Fetch vehicles
                $vidStmt = mysqli_prepare($db_connection, "
                    SELECT *
                    FROM vehiclestbl 
                    WHERE client_id = ? 
                    AND status='Active'
                    AND vehicle_id NOT IN (
                        SELECT vehicle_id 
                        FROM requeststbl 
                        WHERE status NOT IN ('Completed', 'Cancelled')
                    )
                ");
                if ($vidStmt) {
                    mysqli_stmt_bind_param($vidStmt, "i", $client['client_id']);
                    mysqli_stmt_execute($vidStmt);
                    $vres = mysqli_stmt_get_result($vidStmt);
                    while ($v = mysqli_fetch_assoc($vres)) {
                        $vehicles[] = $v;
                    }
                    mysqli_stmt_close($vidStmt);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Already verified (session active).',
        'client' => $client,
        'vehicles' => $vehicles
    ]);
    exit;
}
$email = trim($_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');

if ($email === '' || $code === '') {
    echo json_encode(['success' => false, 'message' => 'Missing email or code.']);
    exit;
}

if (!isset($_SESSION['email_verification'][$email])) {
    echo json_encode(['success' => false, 'message' => 'No code requested for that email.']);
    exit;
}

$entry = &$_SESSION['email_verification'][$email];
$now = time();

// check expiry
if ($now > $entry['expires']) {
    unset($_SESSION['email_verification'][$email]);
    echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new one.']);
    exit;
}

// check attempt limit
$maxAttempts = 5;
if (!isset($entry['attempts'])) $entry['attempts'] = 0;
if ($entry['attempts'] >= $maxAttempts) {
    unset($_SESSION['email_verification'][$email]);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Request a new code.']);
    exit;
}

// compare codes
if (!hash_equals($entry['code'], $code)) {
    $entry['attempts']++;
    echo json_encode(['success' => false, 'message' => 'Incorrect code.']);
    exit;
}

// success — consume the entry
unset($_SESSION['email_verification'][$email]);

// Now fetch client + vehicles from DB and return them (safe now, because verification is done)
$client = null;
$vehicles = [];

if (isset($db_connection)) {
    $stmt = mysqli_prepare($db_connection, "SELECT client_id, first_name, middle_name, last_name, contact_number, email, address, status, account_id FROM clientstbl WHERE email = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $client = $row;
            // fetch vehicles
            $vidStmt = mysqli_prepare($db_connection, "
                SELECT * 
                FROM vehiclestbl 
                WHERE client_id = ? 
                AND status='Active'
                AND vehicle_id NOT IN (
                    SELECT vehicle_id 
                    FROM requeststbl 
                    WHERE status NOT IN ('Completed', 'Cancelled')
                )
            ");
            if ($vidStmt) {
                mysqli_stmt_bind_param($vidStmt, "i", $client['client_id']);
                mysqli_stmt_execute($vidStmt);
                $vres = mysqli_stmt_get_result($vidStmt);
                while ($v = mysqli_fetch_assoc($vres)) {
                    $vehicles[] = $v;
                }
                mysqli_stmt_close($vidStmt);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// If client not found (maybe verification for new client), return success but no client
echo json_encode(['success' => true, 'message' => 'Verified', 'client' => $client, 'vehicles' => $vehicles]);
exit;
