<?php
session_name('CABTECH_SYSTEM');
session_start();
include_once('../config/db.php');

header('Content-Type: application/json');

$account_id = $_SESSION['Account_id'] ?? 0;

// Expect an array of notification IDs
$ids = $_POST['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No notifications to delete.']);
    exit;
}

// Sanitize IDs
$ids = array_map('intval', $ids);

// Build placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids)) . 'i'; // +1 for account_id
$params = array_merge($ids, [$account_id]);

$sql = "DELETE FROM user_notifreadtbl WHERE notification_id IN ($placeholders) AND account_id = ? AND isRead = 1";

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
