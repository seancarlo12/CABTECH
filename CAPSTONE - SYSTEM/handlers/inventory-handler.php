<?php
session_name('CABTECH_SYSTEM');
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}



if (isset($_POST['action']) && $_POST['action'] === 'addItem') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $price   = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $stock = intval($_POST['stock'] ?? 0);
    $status = "Available";
    $response = [];

    // VALIDATION
    if (!$name || !$type || $price <= 0 || $stock < 0) {
        $response['status']  = 'error';
        $response['message'] = 'Please complete all fields.';
        echo json_encode($response);
        exit;
    }

    // Name allowed characters
    if (!preg_match("/^[a-zA-Z0-9 .,'-]+$/", $name)) {
        $response['status']  = 'error';
        $response['message'] = 'Item name contains invalid characters.';
        echo json_encode($response);
        exit;
    }

    // INSERT INTO DATABASE
    $query = "INSERT INTO itemstbl (name, type, price, stock, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("ssdis", $name, $type, $price, $stock, $status);

    if ($stmt->execute()) {
        $insert_id = $db_connection->insert_id; // <-- Get the ID of the new row
        $response['status']  = 'success';
        $response['message'] = 'Item has been successfully added.';
        $response['insert_id']  = $insert_id;


        $notifType = "system";
        $message = "New \"$type\" item has been added. [Item ID: $insert_id] ";
        $recipients = ['Admin', 'Super Admin', 'Mechanic'];

        $notifResult = sendNotification($db_connection, $notifType, $message, $recipients, 'inventory');
        insertLog($db_connection, "Added new item [$name / $insert_id]", "Item");

    } else {
        $response['status']  = 'error';
        $response['message'] = 'Failed to add iten. Please try again.';
    }

    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'getItem') {
    $response = [];
    $itemId = $_POST['itemId'];

    $query = "
        SELECT *
        FROM itemstbl
        WHERE item_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $response['status'] = true;
        $response['item'] = $item;
    } else {
        $response['status'] = false;
        $response['message'] = 'Item not found.';
    }


    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'updateItem') {
    $response = [];

    $itemId     = $_POST['item_id'] ?? null;
    $name   = trim($_POST['name'] ?? '');
    $type   = trim($_POST['type'] ?? '');
    $price   = trim($_POST['price'] ?? '');
    $stock = (int) ($_POST['stock'] ?? 0);

    if (!$itemId || !$name || !$type || !$price) {
        $response['status']  = 'error';
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit;
    }

    $query = "
        UPDATE itemstbl
        SET name = ?,
            type = ?,
            price = ?,
            stock = ?
        WHERE item_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("ssdii", $name, $type, $price, $stock, $itemId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['status']  = 'success';
            $response['message'] = 'Item updated successfully.';

            $type = "system";
            $message = "\"$name\" Details Updated. [Item ID: $itemId] ";
            $recipients = ['Admin', 'Super Admin'];

            $notifResult = sendNotification($db_connection, $type, $message, $recipients, 'inventory');
            insertLog($db_connection, "Updated item details [$name / $itemId]", "Item");
        } else {
            $response['status']  = 'error';
            $response['message'] = 'No changes made or item not found.';
        }
    } else {
        $response['status']  = 'error';
        $response['message'] = 'Database error: ' . $stmt->error;
    }

    echo json_encode($response);
    exit;
}



if (isset($_POST['action']) && ($_POST['action'] === 'activateItem' || $_POST['action'] === 'deactivateItem')) {
    $response = [];

    $item_id = intval($_POST['item_id'] ?? 0);
    $new_status = ($_POST['action'] === 'activateItem') ? 'Available' : 'Unavailable';

    if ($item_id <= 0) {
        $response['status']  = 'error';
        $response['message'] = 'Invalid item ID.';
        echo json_encode($response);
        exit;
    }

    $query = "UPDATE itemstbl SET status = ? WHERE item_id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("si", $new_status, $item_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['status']  = 'success';
            $response['message'] = "Item is now {$new_status}.";
            
            insertLog($db_connection, "Changed Item (ID: $item_id) status to \"$new_status\"", "Item");
        } else {
            $response['status']  = 'error';
            $response['message'] = 'No changes were made. Item may already have that status.';
        }
    } else {
        $response['status']  = 'error';
        $response['message'] = 'Database error: ' . $stmt->error;
    }

    echo json_encode($response);
    exit;
}
