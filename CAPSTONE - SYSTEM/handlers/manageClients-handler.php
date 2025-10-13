<?php
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}


if (isset($_POST['client_data']) && isset($_POST['action']) && $_POST['action'] == 'add') {
    $client = $_POST['client_data'];
    $response = [];

    // Extract and sanitize
    $firstName = trim($client['first_name']);
    $lastName = trim($client['last_name']);
    $middleName = trim($client['middle_name']);
    $mobile = trim($client['mobile_number']);
    $email = trim($client['email']);
    $address = trim($client['address']);


    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($mobile)) {
        $response['status'] = 'error';
        $response['message'] = 'Missing Fields required.';
        echo json_encode($response);
        exit;
    }

    // Check if contact number or email already exists
    $checkQuery = "SELECT * FROM clientstbl WHERE contact_number = ? OR email = ?";
    $checkStmt = $db_connection->prepare($checkQuery);
    $checkStmt->bind_param("ss", $mobile, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result && $result->num_rows > 0) {
        $response['status'] = 'existing';
        $response['message'] = 'Duplicate entry detected: A client record with this contact number/email already exists.';
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();



    $query = "INSERT INTO clientstbl (first_name, last_name, middle_name, contact_number, email, address) 
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $db_connection->prepare($query);

    if ($stmt) {
        $stmt->bind_param("ssssss", $firstName, $lastName, $middleName, $mobile, $email, $address);
        $success = $stmt->execute();

        if ($success) {
            $insert_id = $db_connection->insert_id; // <-- Get the ID of the new row
            $response['status'] = 'success';
            $response['message'] = 'The new client record has been saved.';

            
            insertLog($db_connection, "Added a new client / Client ID: $insert_id", "Client");
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Client record not saved.';
        }

        $stmt->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Database error';
    }

    $db_connection->close();

    
    echo json_encode($response);
    exit;
}

if (isset($_POST['row_id']) && isset($_POST['action']) && $_POST['action'] == 'getRowData') {
    // Get the row_id sent by AJAX
    $rowId = $_POST['row_id'];
    $response = [];

    // Fetch Account
    $getAccountQuery = "
        SELECT a.* 
        FROM accountstbl a
        INNER JOIN clientstbl c ON a.account_id = c.account_id
        WHERE c.client_id = ?
    ";
    $stmt = $db_connection->prepare($getAccountQuery);
    $stmt->bind_param("i", $rowId);
    $stmt->execute();
    $accountResult = $stmt->get_result();

    if ($accountResult && $accountResult->num_rows > 0) {
        $accountData = $accountResult->fetch_assoc();
        $response['account'] = $accountData;
    } else {
        $response['account'] = null;
        $response['account_message'] = 'Account does not exist.';
    }


        // ---------------- Fetch Service History ----------------
        $serviceHistoryQuery = "
        SELECT
            sr.record_id,
            sr.request_id,
            sr.record_status,
            sr.completion_dt,
            req.client_id,
            c.first_name,
            c.last_name,
            CONCAT(c.first_name, ' ', c.last_name) AS client_fullname,
            req.vehicle_id,
            COALESCE(req.sched_dt, req.request_dt) AS scheduled_or_request_dt,
            inv.grand_total AS total_billing
        FROM recordstbl sr
        INNER JOIN requeststbl req   ON sr.request_id = req.request_id
        INNER JOIN clientstbl c     ON req.client_id = c.client_id
        LEFT JOIN invoicetbl inv    ON sr.record_id = inv.record_id
        WHERE req.client_id = ? AND req.status = 'Completed'
        ORDER BY sr.completion_dt DESC;
            ";
        $stmt2 = $db_connection->prepare($serviceHistoryQuery);
        $stmt2->bind_param("i", $rowId);
        $stmt2->execute();
        $historyResult = $stmt2->get_result();

        $serviceHistory = [];

        while ($row = $historyResult->fetch_assoc()) {
            $requestId = $row['request_id'];

            // --- Vehicle info ---
            $vehicleQuery = "SELECT make, model FROM vehiclestbl WHERE vehicle_id = ?";
            $vStmt = $db_connection->prepare($vehicleQuery);
            $vStmt->bind_param("i", $row['vehicle_id']);
            $vStmt->execute();
            $vResult = $vStmt->get_result()->fetch_assoc();
            $row['vehicle'] = ($vResult) ? $vResult['make'] . " " . $vResult['model'] : "N/A";

            // --- Requested services with client comments ---
            $serviceQuery = "
                SELECT s.service_name, rs.clients_comment
                FROM requested_servicestbl rs
                INNER JOIN servicestbl s ON rs.service_id = s.service_id
                WHERE rs.request_id = ?
            ";
            $sStmt = $db_connection->prepare($serviceQuery);
            $sStmt->bind_param("i", $requestId);
            $sStmt->execute();
            $sResult = $sStmt->get_result();
            $services = [];
            while ($sRow = $sResult->fetch_assoc()) {
                $services[] = [
                    'service_name' => $sRow['service_name'],
                    'clients_comment' => $sRow['clients_comment']
                ];
            }
            $row['services'] = $services;

            // --- Assigned mechanics ---
            $mechanicsQuery = "
                SELECT u.first_name, u.last_name
                FROM assigned_mechanicstbl am
                INNER JOIN userstbl u ON am.user_id = u.user_id
                WHERE am.record_id = ?
            ";
            $mStmt = $db_connection->prepare($mechanicsQuery);
            $mStmt->bind_param("i", $row['record_id']);
            $mStmt->execute();
            $mResult = $mStmt->get_result();
            $mechanics = [];
            while ($mRow = $mResult->fetch_assoc()) {
                $mechanics[] = $mRow['first_name'] . " " . $mRow['last_name'];
            }
            $row['mechanics'] = $mechanics;

            $serviceHistory[] = $row;
        }
        error_log("Service history count: " . count($serviceHistory));
        $response['service_history'] = $serviceHistory;



    // Fetch Vehicle Data
    $getVehicleQuery = "
        SELECT v.* 
        FROM vehiclestbl v
        WHERE v.client_id = ? AND v.status = 'Active'
    ";
    $stmt = $db_connection->prepare($getVehicleQuery);
    $stmt->bind_param("i", $rowId);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();

    if ($vehicleResult && $vehicleResult->num_rows > 0) {
        $vehicleData = [];
        while ($row = $vehicleResult->fetch_assoc()) {
            $vehicleData[] = $row;
        }
        $response['vehicles'] = $vehicleData;
    } else {
        $response['vehicles'] = null;
        $response['vehicle_message'] = 'No vehicles found.';
    }

    
    echo json_encode($response);

    exit;
}

if (isset($_POST['statusChange']) && isset($_POST['rowId'])) {
    $response = [];
    $action = $_POST['statusChange'];
    $rowId = $_POST['rowId'];
    if ($action == 'deactivate') {
        $query = "
                    UPDATE clientstbl
                    SET status = 'Deactivated'
                    WHERE client_id = ?;
                ";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $rowId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['statusUpdate'] = true;
            $response['message'] = 'Client profile successfully deactivated.';

            insertLog($db_connection, "Client profile deactivated / Client ID: $rowId", "Client");
        } else {
            $response['statusUpdate'] = null;
            $response['message'] = 'Client profile does not exist or is already deactivated.';
        }

        
        echo json_encode($response);
        exit;
    } else if ($action == 'activate') {
        $query = "
                    UPDATE clientstbl
                    SET status = 'Active'
                    WHERE client_id = ?;
                ";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $rowId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['statusUpdate'] = true;
            $response['message'] = 'Client profile successfully activated.';
            insertLog($db_connection, "Client profile activated / Client ID: $rowId", "Client");
        } else {
            $response['statusUpdate'] = null;
            $response['message'] = 'Client profile does not exist or is already active.';
        }

        
        echo json_encode($response);
        exit;
    } else if ($action == 'restrict') {
        $query = "
                    UPDATE clientstbl
                    SET status = 'Restricted'
                    WHERE client_id = ?;
                ";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $rowId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['statusUpdate'] = true;
            $response['message'] = 'Client profile successfully restricted.';
            insertLog($db_connection, "Client profile restricted / Client ID: $rowId", "Client");
        } else {
            $response['statusUpdate'] = null;
            $response['message'] = 'Client profile does not exist or is already restricted.';
        }

        
        echo json_encode($response);
        exit;
    } else {
        $response['statusUpdate'] = null;
        $response['message'] = 'Invalid status action.';
        
        echo json_encode($response);
        exit;
    }
}



if (isset($_POST['action']) && $_POST['action'] === 'getClientRecord' && isset($_POST['clientId'])) {
    $response = [];
    $clientId = $_POST['clientId'];

    $query = "
        SELECT *
        FROM clientstbl
        WHERE client_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $client = $result->fetch_assoc();
        $response['status'] = true;
        $response['client'] = $client;
    } else {
        $response['status'] = false;
        $response['message'] = 'Client not found.';
    }

    
    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['client_data'])) {
    $response = [];
    $clientData = $_POST['client_data'];

    // Make sure client_id exists
    if (!isset($clientData['client_id'])) {
        $response['status'] = 'error';
        $response['message'] = 'Client ID is required.';
        
        echo json_encode($response);
        exit;
    }

    $clientId = intval($clientData['client_id']);
    unset($clientData['client_id']); // Remove it from fields to be updated

    if (empty($clientData)) {
        $response['status'] = 'error';
        $response['message'] = 'No changes were submitted.';
        
        echo json_encode($response);
        exit;
    }

    // --- 🛑 DUPLICATE CHECK SECTION (IMPORTANT!) ---
    $mobile = $clientData['contact_number'] ?? null;
    $email = $clientData['email'] ?? null;

    if ($mobile || $email) {
        $checkQuery = "SELECT * FROM clientstbl 
                        WHERE (contact_number = ? OR email = ?) 
                        AND client_id != ?";
        $checkStmt = $db_connection->prepare($checkQuery);
        $checkStmt->bind_param("ssi", $mobile, $email, $clientId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result && $result->num_rows > 0) {
            $response['status'] = 'existing';
            $response['message'] = 'Duplicate contact number or email found.';
            
            echo json_encode($response);
            exit;
        }
        $checkStmt->close();
    }

    // --- ✅ Proceed with update ---
    $setClauses = [];
    $params = [];
    $types = '';

    foreach ($clientData as $column => $value) {
        $setClauses[] = "$column = ?";
        $params[] = $value;
        $types .= 's'; // assuming all values are strings (adjust as needed)
    }

    $query = "UPDATE clientstbl SET " . implode(", ", $setClauses) . " WHERE client_id = ?";
    $params[] = $clientId;
    $types .= 'i';

    $stmt = $db_connection->prepare($query);
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare the SQL statement.';
        
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['status'] = 'success';
        $response['message'] = 'Client information successfully updated.';
        insertLog($db_connection, "Updated client profile / Client ID: $clientId", "Client");
    } else {
        $response['status'] = 'error';
        $response['message'] = 'No changes made or client does not exist.';
    }

    
    echo json_encode($response);
    exit;
}

?>