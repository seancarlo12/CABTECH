<?php
session_name('CABTECH_SYSTEM');
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}

if (isset($_POST['action']) && $_POST['action'] === 'addService') {
    $service_name = trim($_POST['service_name'] ?? '');
    $est_duration = trim($_POST['est_duration'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $labor_cost   = isset($_POST['labor_cost']) ? floatval($_POST['labor_cost']) : 0;

    $response = [];

    // VALIDATION
    if (!$service_name || !$est_duration || !$description || $labor_cost <= 0) {
        $response['status']  = 'error';
        $response['message'] = 'Please complete all fields.';
        echo json_encode($response);
        exit;
    }
    if (!preg_match('/^\d+:[0-5]\d:[0-5]\d$/', $est_duration)) {
        $response['status']  = 'error';
        $response['message'] = 'Invalid time format.';
        echo json_encode($response);
        exit;
    }
    // Convert to seconds & check minimum 10 mins
    list($hours, $minutes, $seconds) = array_map('intval', explode(':', $est_duration));
    $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
    if ($totalSeconds < 600) {
        $response['status']  = 'error';
        $response['message'] = 'Duration must be at least 10 minutes.';
        echo json_encode($response);
        exit;
    }
    // Name allowed characters
    if (!preg_match("/^[a-zA-Z0-9 .,'-]+$/", $service_name)) {
        $response['status']  = 'error';
        $response['message'] = 'Service name contains invalid characters.';
        echo json_encode($response);
        exit;
    }

    $status = 'Active';

    // INSERT INTO DATABASE
    $query = "INSERT INTO servicestbl (service_name, estimated_duration, description, labor_cost, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("sssds", $service_name, $est_duration, $description, $labor_cost, $status);

    if ($stmt->execute()) {
        $insert_id = $db_connection->insert_id; // <-- Get the ID of the new row
        $response['status']  = 'success';
        $response['message'] = 'Service has been successfully added.';
        $response['insert_id']  = $insert_id;

        
        $notifType = "system";
        $message = "New service offered: \"$service_name\" has been added. [Service ID: $insert_id]";
        $recipients = ['Admin', 'Super Admin', 'Mechanic'];

        $notifResult = sendNotification($db_connection, $notifType, $message, $recipients, 'serviceOffered');
        insertLog($db_connection, "Added new Service Offered [$service_name / $insert_id]", "ServicesOffered");
    } else {
        $response['status']  = 'error';
        $response['message'] = 'Failed to add service. Please try again.';
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'getService') {
    $response = [];
    $serviceId = $_POST['serviceId'];

    $query = "
        SELECT *
        FROM servicestbl
        WHERE service_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $service = $result->fetch_assoc();
        $response['status'] = true;
        $response['service'] = $service;
    } else {
        $response['status'] = false;
        $response['message'] = 'Service not found.';
    }


    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'updateService') {
    $response = [];

    $serviceId     = $_POST['service_id'] ?? null;
    $serviceName   = trim($_POST['service_name'] ?? '');
    $estDuration   = trim($_POST['est_duration'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $laborCost     = trim($_POST['labor_cost'] ?? '');

    if (!$serviceId || !$serviceName || !$estDuration) {
        $response['status']  = 'error';
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit;
    }

    $query = "
        UPDATE servicestbl
        SET service_name = ?,
            estimated_duration = ?,
            description = ?,
            labor_cost = ?
        WHERE service_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("sssdi", $serviceName, $estDuration, $description, $laborCost, $serviceId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['status']  = 'success';
            $response['message'] = 'Service updated successfully.';

                
            $notifType = "system";
            $message = "Service offered: \"$serviceName\" has been updated successfully. [Service ID: $serviceId]";
            $recipients = ['Admin', 'Super Admin', 'Mechanic'];

            $notifResult = sendNotification($db_connection, $notifType, $message, $recipients, 'serviceOffered');
            insertLog($db_connection, "Updated Service Offered details [$serviceName / $serviceId]", "ServicesOffered");
            
        } else {
            $response['status']  = 'error';
            $response['message'] = 'No changes made or service not found.';
        }
    } else {
        $response['status']  = 'error';
        $response['message'] = 'Database error: ' . $stmt->error;
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'activateService' || $_POST['action'] === 'deactivateService') {
    $response = [];

    $service_id = intval($_POST['service_id'] ?? 0);
    $new_status = ($_POST['action'] === 'activateService') ? 'Active' : 'Inactive';

    if ($service_id <= 0) {
        $response['status']  = 'error';
        $response['message'] = 'Invalid service ID.';
        echo json_encode($response);
        exit;
    }

    $query = "UPDATE servicestbl SET status = ? WHERE service_id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("si", $new_status, $service_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['status']  = 'success';
            $response['message'] = "Service has been {$new_status}.";
            insertLog($db_connection, "Changed Service Offered (ID: $service_id) status to \"$new_status\"", "ServicesOffered");
        } else {
            $response['status']  = 'error';
            $response['message'] = 'No changes were made. Service may already have that status.';
        }
    } else {
        $response['status']  = 'error';
        $response['message'] = 'Database error: ' . $stmt->error;
    }

    echo json_encode($response);
    exit;
}
?>
