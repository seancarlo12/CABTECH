<?php
// handlers/login.php
header('Content-Type: application/json; charset=utf-8');
session_name('CABTECH_WEBSITE');
session_start();

// include DB (adjust path if your handlers folder differs)
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);

$response = ['success' => false, 'message' => 'Invalid login credentials.'];

// Get POST values (matches your form)
$emailUser = trim($_POST['emailUser'] ?? '');
$password  = trim($_POST['password'] ?? '');

if ($emailUser === '' || $password === '') {
    $response['message'] = 'Please provide email/username and password.';
    echo json_encode($response);
    exit;
}

if (!isset($db_connection) || !$db_connection) {
    $response['message'] = 'Database connection not available.';
    echo json_encode($response);
    exit;
}

// We search by account username OR client email (clientstbl.email).
// accountstbl has username + password, clientstbl has email and links via account_id.
$sql = "
    SELECT a.account_id, a.username, a.password AS pw_hash, c.client_id, c.email
    FROM accountstbl AS a
    LEFT JOIN clientstbl AS c ON a.account_id = c.account_id
    WHERE a.username = ? OR c.email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($db_connection, $sql);
if (!$stmt) {
    $response['message'] = 'Database error.';
    // $response['error_detail'] = mysqli_error($db_connection); // optionally expose for debugging
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ss', $emailUser, $emailUser);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // verify password against hash in accountstbl
    if (isset($row['pw_hash']) && password_verify($password, $row['pw_hash'])) {
        // Login success — set session values
        $_SESSION['account_id'] = (int)$row['account_id'];
        $_SESSION['username']   = $row['username'];
        $_SESSION['email']   = $row['email'];
        $_SESSION['client_id']  = isset($row['client_id']) ? (int)$row['client_id'] : null;

        $response = [
            'success' => true,
            'message' => 'Login successful.',
            'account_id' => (int)$row['account_id'],
            'username' => $row['username'],
            'client_id' => isset($row['client_id']) ? (int)$row['client_id'] : null
        ];
    } else {
        $response['message'] = 'Incorrect password.';
    }
} else {
    $response['message'] = 'Account not found.';
}

mysqli_stmt_close($stmt);
mysqli_close($db_connection);

echo json_encode($response);
exit;
?>
