<?php
ob_start();
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'error_detail' => ''
];

// --- Read JSON or form input ---
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
} else {
    $input = $_POST;
}

// --- Helper ---
function gv($arr, $key) { return isset($arr[$key]) ? trim((string)$arr[$key]) : ''; }

// --- Extract fields ---
$client_id        = gv($input, 'client_id'); // optional (for existing clients)
$username         = gv($input, 'username');
$email            = gv($input, 'email');
$f_name           = gv($input, 'f_name');
$m_name           = gv($input, 'm_name');
$l_name           = gv($input, 'l_name');
$contact_number   = gv($input, 'contact_number');
$address          = gv($input, 'address');
$password         = gv($input, 'password');
$confirm_password = gv($input, 'confirm_password');

if ($contact_number === '09') $contact_number = '';

// --- VALIDATION ---
if ($username === '') $response['errors'][] = 'Username is required.';
if ($password === '') $response['errors'][] = 'Password is required.';
if ($password && strlen($password) < 8)
    $response['errors'][] = 'Password must be at least 8 characters long.';
if ($password && $password !== $confirm_password)
    $response['errors'][] = 'Password and re-entered password do not match.';

if ($contact_number !== '' && !preg_match('/^09\d{9}$/', $contact_number))
    $response['errors'][] = 'Contact number must be 11 digits and start with 09, or leave it blank.';

if (!$client_id) { // new client must fill full info
    if ($email === '') $response['errors'][] = 'Email address is required.';
    if ($f_name === '') $response['errors'][] = 'First name is required.';
    if ($l_name === '') $response['errors'][] = 'Last name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $response['errors'][] = 'Email address is not valid.';
}

if (!empty($response['errors'])) {
    echo json_encode($response);
    exit;
}

if (!isset($db_connection) || !$db_connection) {
    $response['message'] = 'Database connection not available.';
    echo json_encode($response);
    exit;
}

mysqli_begin_transaction($db_connection);

try {
    // --- 1️⃣ Check username uniqueness ---
    $sql = "SELECT 1 FROM accountstbl WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($db_connection, $sql);
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0)
        $response['errors'][] = 'Username is already taken.';
    mysqli_stmt_close($stmt);

    if (!empty($response['errors'])) {
        mysqli_rollback($db_connection);
        echo json_encode($response);
        exit;
    }

    // --- 2️⃣ If existing client, verify it exists ---
    if ($client_id) {
        $sql = "SELECT account_id FROM clientstbl WHERE client_id = ? LIMIT 1";
        $stmt = mysqli_prepare($db_connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $client_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $existing_acc);
        if (!mysqli_stmt_fetch($stmt)) {
            throw new Exception("Client ID #$client_id does not exist.");
        }
        mysqli_stmt_close($stmt);

        if ($existing_acc) {
            $response['errors'][] = 'This client already has an account.';
            mysqli_rollback($db_connection);
            echo json_encode($response);
            exit;
        }
    }

    // --- 3️⃣ Create the account first ---
    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO accountstbl (username, password, date_created)
            VALUES (?, ?, NOW())";
    $stmt = mysqli_prepare($db_connection, $sql);
    if (!$stmt) throw new Exception('Prepare failed (account insert): ' . mysqli_error($db_connection));
    mysqli_stmt_bind_param($stmt, 'ss', $username, $pw_hash);
    if (!mysqli_stmt_execute($stmt))
        throw new Exception('Execute failed (account insert): ' . mysqli_stmt_error($stmt));
    $account_id = mysqli_insert_id($db_connection);
    mysqli_stmt_close($stmt);

    // --- 4️⃣ Create or update client record ---
    if (!$client_id) {
        // new client
        $sql = "INSERT INTO clientstbl (account_id, first_name, middle_name, last_name, email, contact_number, address)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($db_connection, $sql);
        if (!$stmt) throw new Exception('Prepare failed (client insert): ' . mysqli_error($db_connection));
        mysqli_stmt_bind_param($stmt, 'issssss', $account_id, $f_name, $m_name, $l_name, $email, $contact_number, $address);
        if (!mysqli_stmt_execute($stmt))
            throw new Exception('Execute failed (client insert): ' . mysqli_stmt_error($stmt));
        $client_id = mysqli_insert_id($db_connection);
        mysqli_stmt_close($stmt);
    } else {
        // existing client — just link the new account
        $sql = "UPDATE clientstbl SET account_id = ? WHERE client_id = ?";
        $stmt = mysqli_prepare($db_connection, $sql);
        if (!$stmt) throw new Exception('Prepare failed (client update): ' . mysqli_error($db_connection));
        mysqli_stmt_bind_param($stmt, 'ii', $account_id, $client_id);
        if (!mysqli_stmt_execute($stmt))
            throw new Exception('Execute failed (client update): ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($db_connection);

    $response['success'] = true;
    $response['message'] = 'Account successfully created and linked to client.';
    $response['client_id'] = $client_id;
    $response['account_id'] = $account_id;


     // Send Welcome Notification
    $notifType = 'system';
    $welcomeMessage = "Welcome to CabTech Auto Services! Your account has been successfully created and linked.";
    sendNotification($db_connection, $notifType, $welcomeMessage, [$account_id], 'clients');


} catch (Exception $e) {
    mysqli_rollback($db_connection);
    $response['message'] = 'An error occurred during signup.';
    $response['error_detail'] = $e->getMessage();
}

mysqli_close($db_connection);
echo json_encode($response);
exit;
?>
