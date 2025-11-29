<?php
// handlers/login.php
header('Content-Type: application/json; charset=utf-8');
session_name('CABTECH_WEBSITE');
session_start();

// clear time for testing
unset($_SESSION['login_attempts']);



// include DB (adjust path if your handlers folder differs)
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);

$response = ['success' => false, 'message' => 'Invalid login credentials.'];

// Get POST values (matches your form)
$emailUser = trim($_POST['emailUser'] ?? '');
$password  = trim($_POST['password'] ?? '');

// === BEGIN: Simple session-based login attempt limiter (MINIMAL) ===
// Config — tweak if needed
$MAX_LOGIN_ATTEMPTS = 5;
$LOCKOUT_SECONDS    = 10 * 60; // 10 minutes

// client IP (simple)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// initialize container
if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// keys (keep compact)
$userKey = 'user_' . md5(strtolower($emailUser));
$ipKey   = 'ip_' . md5($clientIp);

// helpers
function isLocked($key) {
    if (!isset($_SESSION['login_attempts'][$key])) return false;
    $e = $_SESSION['login_attempts'][$key];
    if (!empty($e['locked_until']) && $e['locked_until'] > time()) return $e['locked_until'];
    return false;
}
function registerFailedAttempt($key, $maxAttempts, $lockSeconds) {
    if (!isset($_SESSION['login_attempts'][$key]) || !is_array($_SESSION['login_attempts'][$key])) {
        $_SESSION['login_attempts'][$key] = ['count' => 0, 'locked_until' => 0, 'last' => 0];
    }
    $_SESSION['login_attempts'][$key]['count']++;
    $_SESSION['login_attempts'][$key]['last'] = time();
    if ($_SESSION['login_attempts'][$key]['count'] >= $maxAttempts) {
        $_SESSION['login_attempts'][$key]['locked_until'] = time() + $lockSeconds;
    }

}
function resetAttempts($key) {
    if (isset($_SESSION['login_attempts'][$key])) unset($_SESSION['login_attempts'][$key]);
}

// Check locks BEFORE DB query/verification
if ($lu = isLocked($userKey)) {
    $secsLeft = max(0, $lu - time());
    $response['message'] = "Too many failed attempts for that username/email. Try again in {$secsLeft} second(s).";
    echo json_encode($response);
    exit;
}
if ($lu = isLocked($ipKey)) {
    $secsLeft = max(0, $lu - time());
    $response['message'] = "Too many failed attempts from your network. Try again in {$secsLeft} second(s).";
    echo json_encode($response);
    exit;
}
// === END: attempt limiter block ===



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
        registerFailedAttempt($userKey, $MAX_LOGIN_ATTEMPTS, $LOCKOUT_SECONDS);
        registerFailedAttempt($ipKey, $MAX_LOGIN_ATTEMPTS, $LOCKOUT_SECONDS);
    }
} else {
    $response['message'] = 'Account not found.';
    registerFailedAttempt($userKey, $MAX_LOGIN_ATTEMPTS, $LOCKOUT_SECONDS);
    registerFailedAttempt($ipKey, $MAX_LOGIN_ATTEMPTS, $LOCKOUT_SECONDS);
}

mysqli_stmt_close($stmt);
mysqli_close($db_connection);

echo json_encode($response);
exit;
?>
