<?php
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
    // echo 'db working';
}
header('Content-Type: application/json; charset=utf-8');

// read + trim inputs
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';

// basic input requirement
if ($email === '' && $contact === '') {
    echo json_encode(['success' => false, 'message' => 'Email or contact required.']);
    exit;
}

// Prepare client lookup (case-insensitive email check; only matches non-empty email column)
$sql = "SELECT client_id, account_id, first_name, middle_name, last_name, contact_number, email, address, status
        FROM clientstbl
        WHERE ((LOWER(email) = LOWER(?) AND email <> '') OR contact_number = ?)
        LIMIT 1";

if (!$stmt = $db_connection->prepare($sql)) {
    error_log("check_client prepare failed: " . $db_connection->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (prepare).']);
    exit;
}

if (!$stmt->bind_param('ss', $email, $contact)) {
    error_log("check_client bind_param failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (bind).']);
    exit;
}

if (!$stmt->execute()) {
    error_log("check_client execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (execute).']);
    exit;
}

$res = $stmt->get_result();
$client = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($client) {
    // add a simple boolean for convenience
    $client['is_active'] = (isset($client['status']) && $client['status'] === 'Active') ? true : false;

    // fetch vehicles for this client
    $cid = (int)$client['client_id'];
    $vehicles = [];

    $vsql = "SELECT vehicle_id, client_id, make, model, plate_number, color, transmission_type, fuel_type
            FROM vehiclestbl WHERE client_id = ? ORDER BY vehicle_id DESC";

    if ($vs_st = $db_connection->prepare($vsql)) {
        if ($vs_st->bind_param('i', $cid) && $vs_st->execute()) {
            $vres = $vs_st->get_result();
            while ($row = $vres->fetch_assoc()) {
                $vehicles[] = $row;
            }
        } else {
            // non-fatal: log and continue (we still return client)
            error_log("check_client vehicles query failed: " . $vs_st->error);
        }
        $vs_st->close();
    } else {
        error_log("check_client vehicles prepare failed: " . $db_connection->error);
    }

    // Respond: client exists (include status and vehicles)
    echo json_encode([
        'success'  => true,
        'exists'   => true,
        'client'   => $client,
        'vehicles' => $vehicles
    ], JSON_UNESCAPED_UNICODE);
    exit;
} else {
    // Client not found
    echo json_encode([
        'success' => true,
        'exists'  => false
    ]);
    exit;
}
