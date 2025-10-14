<?php
session_name('CABTECH_WEBSITE');
session_start();
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
}
header('Content-Type: application/json');

$account_id = $_SESSION['account_id'] ?? 0;

// Handle either single ID or multiple IDs
$ids = [];
if (isset($_POST['id'])) {
    $ids[] = intval($_POST['id']);
} elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No notification IDs provided']);
    exit;
}

// Prepare placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids)) . 'i'; // last i for account_id
$params = array_merge($ids, [$account_id]);

$sql = "UPDATE user_notifreadtbl SET isRead = 1 WHERE notification_id IN ($placeholders) AND account_id = ?";

$stmt = mysqli_prepare($db_connection, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => mysqli_error($db_connection)]);
    exit;
}

// Bind parameters dynamically
$bind_names = [];
$bind_names[] = $types;
for ($i = 0; $i < count($params); $i++) {
    $bind_names[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

$success = mysqli_stmt_execute($stmt);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($db_connection)]);
}
exit;
