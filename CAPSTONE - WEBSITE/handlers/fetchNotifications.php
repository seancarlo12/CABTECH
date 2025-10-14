<?php
session_name('CABTECH_WEBSITE');
session_start();
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
}

header('Content-Type: application/json');

$account_id = $_SESSION['account_id'] ?? 0;

// 1. Check all items
$lowStockQuery = "SELECT item_id, name, stock FROM itemstbl";
$lowStockResult = mysqli_query($db_connection, $lowStockQuery);

if ($lowStockResult) {
    while ($item = mysqli_fetch_assoc($lowStockResult)) {
        $itemId = $item['item_id'];
        $itemName = $item['name'];
        $stock    = $item['stock'];

        // Build the unique message format
        $message = "Low stock alert [Item ID: {$item['item_id']}]: {$item['name']} has {$stock} stock/s left.";

        // Check if this message already exists
        $checkQuery = "SELECT notification_id FROM notificationstbl WHERE type = 'system' AND message LIKE ?";
        $likeMsg =  "Low stock alert [Item ID: {$itemId}]%"; // match item name regardless of stock number
        $checkStmt = mysqli_prepare($db_connection, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "s", $likeMsg);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $existingNotif = mysqli_fetch_assoc($checkResult);

        if ($stock < 10) {
            // Send only if none exists yet for this item
            if (!$existingNotif) {
                $type = "system";
                $recipients = ["Admin", "Super Admin"];
                sendNotification($db_connection, $type, $message, $recipients, 'inventory');
            }
        } else {
            // If stock is OK (>=10) and notif exists, delete it
            if ($existingNotif) {
                $notifId = $existingNotif['notification_id'];

                // Delete from user_notifreadtbl first
                $delUser = mysqli_prepare($db_connection, "DELETE FROM user_notifreadtbl WHERE notification_id = ?");
                mysqli_stmt_bind_param($delUser, "i", $notifId);
                mysqli_stmt_execute($delUser);

                // Delete from notifications
                $delNotif = mysqli_prepare($db_connection, "DELETE FROM notificationstbl WHERE notification_id = ?");
                mysqli_stmt_bind_param($delNotif, "i", $notifId);
                mysqli_stmt_execute($delNotif);
            }
        }
    }
}



// Fetch all notifications assigned to this user with read status
$query = "
SELECT n.notification_id AS notification_id,
        n.goTo,
        n.type,
        n.message,
        n.timestamp,
        COALESCE(un.isRead, 0) AS isRead
FROM notificationstbl n
INNER JOIN user_notifreadtbl un
    ON n.notification_id = un.notification_id
WHERE un.account_id = ?
ORDER BY n.timestamp DESC
";

$stmt = mysqli_prepare($db_connection, $query);
mysqli_stmt_bind_param($stmt, "i", $account_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
$unread_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;

    if ($row['isRead'] == 0) {
        $unread_count++;
    }
}

echo json_encode([
    'count' => $unread_count,
    'notifications' => $notifications
]);
exit;
