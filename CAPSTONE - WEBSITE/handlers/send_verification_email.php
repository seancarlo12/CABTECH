<?php
// handlers/send_verification_email.php
header('Content-Type: application/json');
session_name('CABTECH_WEBSITE');
session_start();
$response = [];

// Log helper
function log_email_debug($message)
{
    file_put_contents(__DIR__ . '/mail_debug.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// --- Include DB ---
$dbPath = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($dbPath)) {
    include_once($dbPath);
    log_email_debug("DB included successfully: {$dbPath}");
} else {
    log_email_debug("DB include failed: {$dbPath}");
}

// --- PHPMailer requires ---
$phpMailerPaths = [
    'Exception' => dirname(__DIR__, 2) . '/plugins/PHPMailer/src/Exception.php',
    'PHPMailer' => dirname(__DIR__, 2) . '/plugins/PHPMailer/src/PHPMailer.php',
    'SMTP' => dirname(__DIR__, 2) . '/plugins/PHPMailer/src/SMTP.php'
];

foreach ($phpMailerPaths as $name => $path) {
    if (file_exists($path)) {
        require_once($path);
        log_email_debug("PHPMailer {$name} included successfully: {$path}");
    } else {
        log_email_debug("PHPMailer {$name} include failed: {$path}");
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Skip verification if already logged in ---
if (isset($_SESSION['client_id']) && !empty($_SESSION['client_id'])) {
    $response = [
        'success' => true,
        'message' => 'User already verified (session active).',
        'skip_verification' => true
    ];
    log_email_debug("Skipped verification: session client_id={$_SESSION['client_id']}");
    echo json_encode($response);
    exit;
}


// --- Input check ---
$email = trim($_POST['email'] ?? '');
if ($email === '') {
    $response = ['success' => false, 'message' => 'No email provided.'];
    log_email_debug("No email provided.");
    echo json_encode($response);
    exit;
}
log_email_debug("Email to verify: {$email}");

// --- OPTIONAL: check if email exists ---
// --- Robust email-exists check (replace current block) ---
// --- Email existence check + allow_existing handling ---
$emailExists = false;
$mysqli = null;

$rawEmail = $_POST['email'] ?? '';
$email = trim($rawEmail);
if ($email === '') {
    log_email_debug("No email provided for existence check (early exit). Raw: " . json_encode($rawEmail));
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    log_email_debug("Invalid email format: " . $email);
} else {
    $emailNormalized = mb_strtolower($email, 'UTF-8');
    log_email_debug("Email to verify (normalized): {$emailNormalized}");

    // detect DB connection variable
    if (isset($db_connection) && $db_connection) {
        $mysqli = $db_connection;
        log_email_debug("Using DB connection: \$db_connection");
    } elseif (isset($conn) && $conn) {
        $mysqli = $conn;
        log_email_debug("Using DB connection: \$conn");
    }

    if ($mysqli) {
        $sql = "SELECT client_id FROM clientstbl WHERE LOWER(email) = ? LIMIT 1";
        $stmt = mysqli_prepare($mysqli, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $emailNormalized);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                $num = mysqli_stmt_num_rows($stmt);
                $emailExists = ($num > 0);
                log_email_debug("Email exists check for {$emailNormalized}: " . ($emailExists ? "found" : "not found") . " (rows={$num})");
            } else {
                log_email_debug("mysqli_stmt_execute failed: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            log_email_debug("mysqli_prepare failed: " . mysqli_error($mysqli) . " -- SQL: {$sql}");
        }
    } else {
        log_email_debug("Skipping email exists lookup because no DB connection ($emailNormalized).");
    }
}

// Decide behavior based on allow_existing (front-end control)
$allowExisting = isset($_POST['allow_existing']) && ($_POST['allow_existing'] === '1' || $_POST['allow_existing'] === 1);

// If the intent is "signup" and email exists -> return error
if ($emailExists && !$allowExisting) {
    $response = [
        'success' => false,
        'message' => 'Email already registered.',
        'exists' => true
    ];
    log_email_debug("Returning early: email already registered -> {$emailNormalized} (allow_existing not set)");
    echo json_encode($response);
    exit;
}


// --- Rate-limit ---
$maxSendsPerHour = 5;
$now = time();
if (!isset($_SESSION['mail_sends'])) $_SESSION['mail_sends'] = [];
foreach ($_SESSION['mail_sends'] as $k => $entry) {
    if ($entry['time'] < $now - 3600) unset($_SESSION['mail_sends'][$k]);
}
// $sendCount = 0;
// foreach ($_SESSION['mail_sends'] as $entry) if ($entry['email'] === $email) $sendCount++;
// if ($sendCount >= $maxSendsPerHour) {
//     $response = ['success' => false, 'message' => 'Too many verification attempts. Try again later.'];
//     log_email_debug("Rate limit exceeded for {$email}");
//     echo json_encode($response);
//     exit;
// }

// --- Generate code ---
$code = random_int(100000, 999999);
$expires = $now + (10 * 60);
$_SESSION['email_verification'][$email] = [
    'code' => strval($code),
    'expires' => $expires,
    'attempts' => 0,
    'sent_at' => $now
];
$_SESSION['mail_sends'][] = ['email' => $email, 'time' => $now];
log_email_debug("Generated code for {$email}: {$code}");

// --- PHPMailer config ---
$smtpHost = 'smtp.gmail.com';
$smtpUser = 'cabtech.system@gmail.com';
$smtpPass = 'xpze ongj ijau zyiu';
$smtpPort = 587;
$smtpSecure = 'tls';
$fromEmail = 'cabtech.system@gmail.com';
$fromName = 'CABTECH - Email Verification';

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your verification code';
    $mail->Body = "<p>Your verification code is: <strong>{$code}</strong></p><p>Expires in 10 minutes.</p>";

    $mail->send();
    $response = ['success' => true, 'message' => 'Verification code sent.'];
    log_email_debug("Email sent successfully to {$email}");
} catch (Exception $ex) {
    $response = ['success' => false, 'message' => 'Failed to send verification email.'];
    log_email_debug("Email sending failed: " . $ex->getMessage());
}

echo json_encode($response);
log_email_debug("Response: " . json_encode($response));
