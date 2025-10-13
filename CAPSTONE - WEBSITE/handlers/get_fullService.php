<?php
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
}

header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['request_id'], $_POST['action']) && $_POST['action'] === 'getRowData') {

    $requestId = (int)$_POST['request_id'];
    $recordId = null;

    // Fetch the first record_id for this request
    $sql = "SELECT record_id FROM recordstbl WHERE request_id = ? LIMIT 1";
    if ($stmt = mysqli_prepare($db_connection, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $requestId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $recordId = (int)$row['record_id'];
        }
        mysqli_stmt_close($stmt);
    }

    if (!$recordId) {
        echo json_encode(['success' => false, 'error' => 'No record found for this request']);
        exit;
    }

    $response = ['success' => true, 'record_id' => $recordId];

    // Fetch request
    $stmt = $db_connection->prepare("SELECT * FROM requeststbl WHERE request_id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $requestResult = $stmt->get_result();
    $requestData = $requestResult->fetch_assoc() ?? null;
    $response['request'] = $requestData;

    // Fetch client
    $clientId = $requestData['client_id'] ?? 0;
    $stmt = $db_connection->prepare("
    SELECT last_name, first_name, email, contact_number, address 
    FROM clientstbl 
    WHERE client_id = ?
");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $clientResult = $stmt->get_result();
    $response['client'] = $clientResult->fetch_assoc() ?? null;

    // Fetch vehicle
    $vehicleId = $requestData['vehicle_id'] ?? 0;
    $stmt = $db_connection->prepare("SELECT make, model, plate_number, color, transmission_type, fuel_type FROM vehiclestbl WHERE vehicle_id = ?");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();
    $response['vehicle'] = $vehicleResult->fetch_assoc() ?? null;

    // Assigned mechanics
    $stmt = $db_connection->prepare("
        SELECT u.user_id, u.first_name, u.last_name
        FROM assigned_mechanicstbl am
        INNER JOIN userstbl u ON am.user_id = u.user_id
        WHERE am.record_id = ?
        ");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $mechResult = $stmt->get_result();

    $mechanics = [];
    while ($row = $mechResult->fetch_assoc()) {
        // Option 1: keep names separate
        $mechanics[] = $row;

        // Option 2: combine in PHP if needed
        // $row['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
        // $mechanics[] = $row['full_name'];
    }

    $response['mechanics'] = $mechanics;

    // Services
    // Keep your current services query as-is
    $getServicesQuery = "
    SELECT 
        rs.rst_id,
        rs.service_id,
        rs.clients_comment,
        (CASE WHEN rs.service_id IS NULL THEN 1 ELSE 0 END) AS is_custom_service,
        IF(rs.service_id IS NULL, rs.custom_service, s.service_name) AS service_name,
        rs.custom_est_duration AS estimated_duration,
        rs.custom_labor_cost AS labor_cost
    FROM requested_servicestbl rs
    LEFT JOIN servicestbl s ON rs.service_id = s.service_id
    WHERE rs.request_id = ?
    ";
    $stmt = $db_connection->prepare($getServicesQuery);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $servicesResult = $stmt->get_result();

    $services = [];
    while ($row = $servicesResult->fetch_assoc()) {
        $services[] = $row;
    }

    // Now for each service, fetch its items
    foreach ($services as &$service) {
        $rstId = $service['rst_id'];

        $sqlItems = "
        SELECT 
            n.itmn_id,
            n.item_id,
            n.record_id,
            n.record_price,
            n.quantity,
            i.name AS item_name
        FROM items_neededtbl n
        LEFT JOIN itemstbl i ON n.item_id = i.item_id
        WHERE n.rst_id = ?
    ";
        $stmtItems = $db_connection->prepare($sqlItems);
        $stmtItems->bind_param("i", $rstId);
        $stmtItems->execute();
        $resultItems = $stmtItems->get_result();

        $itemsNeeded = [];
        while ($itemRow = $resultItems->fetch_assoc()) {
            $itemsNeeded[] = $itemRow;
        }

        // Add items to the service array
        $service['itemsNeeded'] = $itemsNeeded;
    }

    $response['services'] = $services;

    // Raw requested_servicestbl rows (for internal use)
    $stmt = $db_connection->prepare("SELECT * FROM requested_servicestbl WHERE request_id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $rsResult = $stmt->get_result();
    $response['requested_services'] = $rsResult->fetch_all(MYSQLI_ASSOC);


    if ($requestId) {
        // Query the items needed for this request
        $sql = "SELECT n.*, i.name FROM items_neededtbl n INNER JOIN itemstbl i ON n.item_id = i.item_id WHERE n.record_id = ?";
        $stmt = $db_connection->prepare($sql);
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items_needed = [];
        while ($row = $result->fetch_assoc()) {
            $items_needed[] = $row;
        }

        $response['items_needed'] = $items_needed;
    } else {
        $response['items_needed'] = [];
    }

    // Fetch invoice
    $stmt = $db_connection->prepare("SELECT invoice_id, record_id, status, issued_dt FROM invoicetbl WHERE record_id = ?");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $invoiceResult = $stmt->get_result();
    $response['invoice'] = $invoiceResult->fetch_assoc() ?? ['invoice_id' => '—', 'status' => 'Draft', 'issued_dt' => 'Not yet completed'];

    echo json_encode($response);
    exit;
}
