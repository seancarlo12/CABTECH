<?php
header('Content-Type: application/json');
$response = [];

// Include database connection
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['payload'])) {
    echo json_encode(['success' => false, 'message' => 'Missing request payload.']);
    exit;
}

$payload = json_decode($_POST['payload'], true);
if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$client = $payload['client'] ?? null;
$vehicle = $payload['vehicle'] ?? null;
$services = $payload['services'] ?? [];
$schedule = $payload['schedule'] ?? null;
$request_type = $payload['request_type'] ?? 'Appointment';
$request_status = $payload['request_status'] ?? 'Pending';

if (!$client || !$vehicle || !$schedule) {
    echo json_encode(['success' => false, 'message' => 'Required data missing.']);
    exit;
}

// Start transaction
mysqli_begin_transaction($db_connection);

try {
    // --- Handle client ---
    if ($client['isNew']) {
        $stmt = mysqli_prepare($db_connection, "INSERT INTO clientstbl (first_name, middle_name, last_name, contact_number, email, address, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        mysqli_stmt_bind_param(
            $stmt,
            "ssssss",
            $client['first_name'],
            $client['middle_name'],
            $client['last_name'],
            $client['contact_number'],
            $client['email'],
            $client['address']
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($db_connection));
        $client_id = mysqli_insert_id($db_connection);
        mysqli_stmt_close($stmt);
    } else {
        $client_id = $client['client_id'];
    }

    // --- Handle vehicle ---
    if ($vehicle['isNew']) {
        $stmt = mysqli_prepare($db_connection, "INSERT INTO vehiclestbl (client_id, plate_number, make, model, color, transmission_type, fuel_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $client_id,
            $vehicle['plate_number'],
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['color'],
            $vehicle['transmission_type'],
            $vehicle['fuel_type']
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($db_connection));
        $vehicle_id = mysqli_insert_id($db_connection);
        mysqli_stmt_close($stmt);
    } else {
        $vehicle_id = $vehicle['vehicle_id'];
    }

    // --- CHECK ACTIVE REQUEST ---
    $checkStmt = mysqli_prepare($db_connection, "
    SELECT COUNT(*) 
    FROM requeststbl 
    WHERE vehicle_id = ? 
    AND LOWER(TRIM(status)) IN ('pending', 'approved', 'in progress')
    ");
    mysqli_stmt_bind_param($checkStmt, "i", $vehicle_id);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $activeExists);
    mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);

    if ($activeExists > 0) {
        throw new Exception("This vehicle already has an active request. Please wait until it is completed before submitting a new one.");
    }




    // --- Insert into requeststbl ---
    $stmtRequest = mysqli_prepare($db_connection, "INSERT INTO requeststbl (client_id, vehicle_id, request_type, request_dt, status) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param(
        $stmtRequest,
        "iisss",
        $client_id,
        $vehicle_id,
        $request_type,
        $schedule,
        $request_status
    );
    if (!mysqli_stmt_execute($stmtRequest)) throw new Exception(mysqli_error($db_connection));
    $request_id = mysqli_insert_id($db_connection);
    mysqli_stmt_close($stmtRequest);

    // --- Insert requested services with custom duration and labor cost ---
    $stmtInsert = mysqli_prepare($db_connection, "INSERT INTO requested_servicestbl (request_id, service_id, clients_comment, custom_est_duration, custom_labor_cost) VALUES (?, ?, ?, ?, ?)");

    foreach ($services as $s) {
        $service_id = $s['service_id'];
        $comment = $s['clients_comment'] ?? '';

        // Get the current estimated_duration and labor_cost from servicestbl
        $query = "SELECT estimated_duration, labor_cost FROM servicestbl WHERE service_id = ?";
        $stmtSelect = mysqli_prepare($db_connection, $query);
        mysqli_stmt_bind_param($stmtSelect, "i", $service_id);
        mysqli_stmt_execute($stmtSelect);
        mysqli_stmt_bind_result($stmtSelect, $custom_est_duration, $custom_labor_cost);
        mysqli_stmt_fetch($stmtSelect);
        mysqli_stmt_close($stmtSelect);

        // Insert into requested services
        mysqli_stmt_bind_param($stmtInsert, "iissd", $request_id, $service_id, $comment, $custom_est_duration, $custom_labor_cost);
        if (!mysqli_stmt_execute($stmtInsert)) {
            throw new Exception("Failed to insert requested service: " . mysqli_error($db_connection));
        }
    }

    mysqli_stmt_close($stmtInsert);

    mysqli_commit($db_connection);

    $response['success'] = true;
    // notifications / logs (your existing functions)
    $type = 'service';
    $message = "A client submitted a service request. [Request ID: $request_id]";
    if (function_exists('sendNotification')) {
        sendNotification($db_connection, $type, $message, ['Admin', "Super Admin"], 'requests');
    }
    $response['message'] = "Service request created successfully.";
} catch (Exception $e) {
    mysqli_rollback($db_connection);
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
