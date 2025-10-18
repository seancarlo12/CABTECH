<?php
session_name('CABTECH_SYSTEM');
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}


if (isset($_POST['row_id']) && isset($_POST['action']) && $_POST['action'] == 'getRowData') {
    $requestId = $_POST['row_id'];
    $response = [];

    if (!is_numeric($requestId) || intval($requestId) <= 0) {
        $response = [
            'request' => null,
            'error' => 'Invalid request ID.'
        ];

        echo json_encode($response);
        exit;
    }

    // Fetch Mechanics only if getMechanics is true
    if (isset($_POST['getMechanics']) && $_POST['getMechanics'] == 'true') {
        $getMechanicsQuery = "SELECT * FROM userstbl WHERE role = 'Mechanic'";
        $mechanicsResult = $db_connection->query($getMechanicsQuery);

        $mechanics = [];

        if ($mechanicsResult && $mechanicsResult->num_rows > 0) {
            while ($row = $mechanicsResult->fetch_assoc()) {
                $mechanicId = $row['user_id'];
                $workStatus = $row['work_status']; //  Make sure it's retrieved from query

                //  Fetch specialties for this mechanic
                $getSpecialtiesQuery = "
                    SELECT s.name
                    FROM mechanic_specialtiestbl ms
                    JOIN specialtiestbl s ON ms.specialty_id = s.specialty_id
                    WHERE ms.user_id = ?
                ";
                $stmt = $db_connection->prepare($getSpecialtiesQuery);
                $stmt->bind_param("i", $mechanicId);
                $stmt->execute();
                $specialtiesResult = $stmt->get_result();

                $specialties = [];
                if ($specialtiesResult && $specialtiesResult->num_rows > 0) {
                    while ($specRow = $specialtiesResult->fetch_assoc()) {
                        $specialties[] = $specRow['name'];
                    }
                }

                //  Count how many "In Progress" services this mechanic is assigned to
                $countQuery = "
                    SELECT COUNT(*) AS working_count
                    FROM assigned_mechanicstbl am
                    JOIN recordstbl r ON am.record_id = r.record_id
                    WHERE am.user_id = ? AND r.record_status = 'In Progress'
                ";
                $countStmt = $db_connection->prepare($countQuery);
                $countStmt->bind_param("i", $mechanicId);
                $countStmt->execute();
                $countStmt->bind_result($workingCount);
                $countStmt->fetch();
                $countStmt->close();

                $mechanics[] = [
                    'user_id' => $mechanicId,
                    'full_name' => $row['last_name'] . ', ' . $row['first_name'],
                    'status' => $row['status'],
                    'work_status' => $workStatus,
                    'specialties' => $specialties,
                    'working_count' => $workingCount
                ];
            }

            $response['mechanics'] = $mechanics;
        } else {
            $response['mechanics'] = null;
            $response['mechanics_message'] = 'No mechanics found.';
        }
    }


    // Fetch Request Details
    $getRequestQuery = "SELECT * FROM requeststbl WHERE request_id = ?";
    $stmt = $db_connection->prepare($getRequestQuery);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $requestResult = $stmt->get_result();

    if ($requestResult && $requestResult->num_rows > 0) {
        $requestData = $requestResult->fetch_assoc();
        $response['request'] = $requestData;
    } else {
        $response['request'] = null;
        $response['error'] = 'Request not found.';
        echo json_encode($response);
        exit;
    }

    // Extract client_id and vehicle_id from request
    $clientId = $requestData['client_id'];
    $vehicleId = $requestData['vehicle_id'];

    // Fetch Client Info
    $getClientQuery = "SELECT * FROM clientstbl WHERE client_id = ?";
    $stmt = $db_connection->prepare($getClientQuery);
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $clientResult = $stmt->get_result();

    if ($clientResult && $clientResult->num_rows > 0) {
        $response['client'] = $clientResult->fetch_assoc();
    } else {
        $response['client'] = null;
        $response['client_message'] = 'Client not found.';
    }

    // Fetch Vehicle Info
    $getVehicleQuery = "SELECT * FROM vehiclestbl WHERE vehicle_id = ?";
    $stmt = $db_connection->prepare($getVehicleQuery);
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();

    if ($vehicleResult && $vehicleResult->num_rows > 0) {
        $response['vehicle'] = $vehicleResult->fetch_assoc();
    } else {
        $response['vehicle'] = null;
        $response['vehicle_message'] = 'Vehicle not found.';
    }

    // Fetch Service Names
    $getServicesQuery = "
    SELECT 
        rs.rst_id,
        rs.clients_comment,
        rs.service_id,
        (CASE WHEN rs.service_id IS NULL THEN 1 ELSE 0 END) AS is_custom_service,
        IF(rs.service_id IS NULL, rs.custom_service, s.service_name) AS service_name,
        rs.custom_est_duration AS estimated_duration,
        rs.custom_labor_cost AS labor_cost,
        rs.custom_est_duration IS NOT NULL AS is_custom_duration,
        rs.custom_labor_cost IS NOT NULL AS is_custom_labor
    FROM requested_servicestbl rs
    LEFT JOIN servicestbl s ON rs.service_id = s.service_id
    WHERE rs.request_id = ?
";
    $stmt = $db_connection->prepare($getServicesQuery);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $servicesResult = $stmt->get_result();

    $services = [];
    if ($servicesResult && $servicesResult->num_rows > 0) {
        while ($row = $servicesResult->fetch_assoc()) {
            $services[] = [
                'rst_id' => $row['rst_id'],
                'service_id' => $row['service_id'],
                'service_name' => $row['service_name'],
                'estimated_duration' => $row['estimated_duration'],
                'labor_cost' => $row['labor_cost'],
                'is_custom_duration' => (bool) $row['is_custom_duration'],
                'is_custom_labor' => (bool) $row['is_custom_labor'],
                'is_custom_service' => (bool) $row['is_custom_service'],
                'clients_comment' => $row['clients_comment']
            ];
        }
        $response['services'] = $services;
    } else {
        $response['services'] = null;
        $response['services_message'] = 'No services found.';
    }

    // Fetch requested_servicestbl data
    $getRequestedServicesQuery = "SELECT * FROM requested_servicestbl WHERE request_id = ?";
    $stmt = $db_connection->prepare($getRequestedServicesQuery);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $requestedServicesResult = $stmt->get_result();

    $requestedServices = [];
    if ($requestedServicesResult && $requestedServicesResult->num_rows > 0) {
        while ($row = $requestedServicesResult->fetch_assoc()) {
            $requestedServices[] = $row;
        }
        $response['requested_services'] = $requestedServices;
    } else {
        $response['requested_services'] = null;
        $response['requested_services_message'] = 'No requested services data found.';
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] == 'getAllServices') {
    $response = [];
    // Fetch all services from servicestbl
    $getServicesQuery = "SELECT * FROM servicestbl";
    $servicesResult = $db_connection->query($getServicesQuery);

    if ($servicesResult && $servicesResult->num_rows > 0) {
        $servicesData = [];
        while ($row = $servicesResult->fetch_assoc()) {
            $servicesData[] = $row;
        }
        $response['services'] = $servicesData;
    } else {
        $response['services'] = null;
        $response['services_message'] = 'No services found.';
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] == 'getClientsAndServices') {
    $rowId = $_POST['row_id'];
    $response = [];

    // Get all clients
    $getClientQuery = "SELECT * FROM clientstbl";
    $stmt = $db_connection->prepare($getClientQuery);
    $stmt->execute();
    $clientResult = $stmt->get_result();

    $clients = [];

    if ($clientResult && $clientResult->num_rows > 0) {
        while ($row = $clientResult->fetch_assoc()) {
            $client_id = $row['client_id'];

            // Check if client has 'In Progress' record
            $check = $db_connection->prepare("
                SELECT 1
                FROM requeststbl r
                LEFT JOIN recordstbl rec ON r.request_id = rec.request_id
                WHERE r.client_id = ? AND (
                    LOWER(TRIM(rec.record_status)) = 'in progress'
                    OR LOWER(TRIM(r.status)) IN ('pending', 'approved', 'rescheduling')
                )
                LIMIT 1
            ");
            $check->bind_param("i", $client_id);
            $check->execute();
            $check->store_result();

            $row['is_unavailable'] = $check->num_rows > 0;

            $clients[] = $row;
        }
        $response['clients'] = $clients;
    } else {
        $response['clients'] = [];
        $response['client_message'] = 'No clients found.';
    }

    // Fetch all services
    $getServicesQuery = "SELECT * FROM servicestbl";
    $servicesResult = $db_connection->query($getServicesQuery);

    if ($servicesResult && $servicesResult->num_rows > 0) {
        $servicesData = [];
        while ($row = $servicesResult->fetch_assoc()) {
            $servicesData[] = $row;
        }
        $response['services'] = $servicesData;
    } else {
        $response['services'] = [];
        $response['services_message'] = 'No services found.';
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'getClientsVehicles') {
    $clientId = $_POST['clientId'] ?? null;
    $response = [];

    if ($clientId) {
        // Fetch vehicles for the given client ID
        $query = "SELECT * FROM vehiclestbl WHERE client_id = ? AND status = 'Active'";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        $vehicles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $vehicles[] = $row;
            }
            $response['vehicles'] = $vehicles;
        } else {
            $response['vehicles'] = [];
            $response['message'] = 'No vehicles found for this client.';
        }
    } else {
        $response['vehicles'] = [];
        $response['message'] = 'Invalid client ID.';
    }


    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'validateSchedule' && isset($_POST['schedDate']) && isset($_POST['services'])) {
    $response = [];
    $schedDate = $_POST['schedDate'];
    $services = $_POST['services']; // expected array of {id,...}

    // quick validation
    if (!is_array($services) || count($services) === 0) {
        $response['status'] = false;
        $response['message'] = 'No services selected.';
        echo json_encode($response);
        exit;
    }

    // --- Step 1: Calculate total duration in seconds ---
    $totalSeconds = 0;
    foreach ($services as $service) {
        $serviceId = intval($service['id'] ?? 0);
        if ($serviceId <= 0) {
            $response['status'] = false;
            $response['message'] = 'Invalid service id.';
            echo json_encode($response);
            exit;
        }

        $q = "SELECT estimated_duration FROM servicestbl WHERE service_id = ?";
        $s = $db_connection->prepare($q);
        if ($s === false) {
            $response['status'] = false;
            $response['message'] = 'DB prepare error (service duration): ' . $db_connection->error;
            echo json_encode($response);
            exit;
        }
        $s->bind_param("i", $serviceId);
        $s->execute();
        $res = $s->get_result();
        $row = $res->fetch_assoc();
        $s->close();

        $duration = $row['estimated_duration'] ?? null;
        if (!$duration) {
            $response['status'] = false;
            $response['message'] = "Service ID {$serviceId} not found or missing duration.";
            echo json_encode($response);
            exit;
        }

        // duration expected in HH:MM:SS, HH:MM, or minutes fallback
        $parts = explode(':', $duration);
        if (count($parts) === 3) {
            [$h, $m, $ssec] = $parts;
        } elseif (count($parts) === 2) {
            [$h, $m] = $parts;
            $ssec = 0;
        } else {
            // treat as minutes
            $h = 0;
            $m = (int)$parts[0];
            $ssec = 0;
        }
        $totalSeconds += ((int)$h * 3600) + ((int)$m * 60) + (int)$ssec;
    }

    // --- candidate interval ---
    try {
        $start = new DateTime($schedDate);
    } catch (Exception $e) {
        $response['status'] = false;
        $response['message'] = 'Invalid schedule datetime.';
        echo json_encode($response);
        exit;
    }
    $end = (clone $start)->add(new DateInterval("PT{$totalSeconds}S"));

    $startStr = $start->format('Y-m-d H:i:s');
    $endStr   = $end->format('Y-m-d H:i:s');

    // concurrency limit (change as needed)
    $MAX_CONCURRENT = 4;
    // debug flag: set to true temporarily if you want overlapping rows returned
    $DEBUG = false;

    // --- overlap SQL: compute start (coalesce sched_dt/request_dt) and end per request, then count overlaps ---
    $overlapSql = "
        SELECT COUNT(*) AS overlap_count
        FROM (
            SELECT
                r.request_id,
                COALESCE(r.sched_dt, r.request_dt) AS start_dt,
                ADDTIME(
                    COALESCE(r.sched_dt, r.request_dt),
                    SEC_TO_TIME(SUM(TIME_TO_SEC(
                        COALESCE(NULLIF(rs.custom_est_duration, ''), s.estimated_duration)
                    )))
                ) AS end_dt
            FROM requeststbl r
            JOIN requested_servicestbl rs ON r.request_id = rs.request_id
            JOIN servicestbl s ON rs.service_id = s.service_id
            WHERE LOWER(r.status) IN ('pending','approved','in progress')
                AND COALESCE(r.sched_dt, r.request_dt) IS NOT NULL
            GROUP BY r.request_id
        ) AS sub
        WHERE sub.start_dt < ? 
            AND sub.end_dt   > ?
    ";

    $stmt = $db_connection->prepare($overlapSql);
    if ($stmt === false) {
        $response['status'] = false;
        $response['message'] = 'DB prepare error (overlap): ' . $db_connection->error;
        echo json_encode($response);
        exit;
    }
    $stmt->bind_param("ss", $endStr, $startStr);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r->fetch_assoc();
    $overlapCount = intval($row['overlap_count'] ?? 0);
    $stmt->close();

    // optional debug: return the overlapping request rows (IDs + start/end)
    if ($DEBUG) {
        $listSql = "
            SELECT sub.request_id, sub.start_dt, sub.end_dt
            FROM (
                SELECT
                    r.request_id,
                    COALESCE(r.sched_dt, r.request_dt) AS start_dt,
                    ADDTIME(
                        COALESCE(r.sched_dt, r.request_dt),
                        SEC_TO_TIME(SUM(TIME_TO_SEC(
                            COALESCE(NULLIF(rs.custom_est_duration, ''), s.estimated_duration)
                        )))
                    ) AS end_dt
                FROM requeststbl r
                JOIN requested_servicestbl rs ON r.request_id = rs.request_id
                JOIN servicestbl s ON rs.service_id = s.service_id
                WHERE LOWER(r.status) IN ('pending','approved','in progress')
                    AND COALESCE(r.sched_dt, r.request_dt) IS NOT NULL
                GROUP BY r.request_id
            ) AS sub
            WHERE sub.start_dt < ? 
                AND sub.end_dt   > ?
            ORDER BY sub.start_dt
        ";
        $s2 = $db_connection->prepare($listSql);
        if ($s2 !== false) {
            $s2->bind_param("ss", $endStr, $startStr);
            $s2->execute();
            $res2 = $s2->get_result();
            $debugRows = [];
            while ($rr = $res2->fetch_assoc()) {
                $debugRows[] = $rr;
            }
            $s2->close();
            $response['debug_overlaps'] = $debugRows;
        } else {
            $response['debug_prepare_error'] = $db_connection->error;
        }
    }


    // --- If overlap count meets or exceeds allowed concurrency, try to suggest alternative times ---
    if ($overlapCount >= $MAX_CONCURRENT) {

        $shopOpen = '08:30:00';
        $shopClose = '17:00:00';
        
        $interval = new DateInterval('PT20M'); // 20 minute increments
        $candidate = clone $start;
        $maxTries = 10;
        $found = false;

        for ($i = 1; $i <= $maxTries; $i++) {
            $candidate->add($interval);
            $candidateStartStr = $candidate->format('Y-m-d H:i:s');
            $candidateEndStr = (clone $candidate)->add(new DateInterval("PT{$totalSeconds}S"))->format('Y-m-d H:i:s');

            // shop hours check (time-only)
            $startOnly = (clone $candidate)->format('H:i:s');
            $endOnly = (new DateTime($candidateEndStr))->format('H:i:s');
            if ($startOnly < $shopOpen || $endOnly > $shopClose) {
                continue;
            }

            // re-run overlap count for candidate interval
            $stmt2 = $db_connection->prepare($overlapSql);
            if ($stmt2 === false) {
                // if prepare fails, skip this candidate
                continue;
            }
            $stmt2->bind_param("ss", $candidateEndStr, $candidateStartStr);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            $row2 = $r2->fetch_assoc();
            $candidateOverlap = intval($row2['overlap_count'] ?? 0);
            $stmt2->close();

            if ($candidateOverlap < $MAX_CONCURRENT) {
                $response['suggested'] = $candidateStartStr;
                $found = true;
                break;
            }
        }

        $response['overlap'] = $overlapCount;
        $response['status']  = false;
        $response['message'] = $found ? 'Slot busy — suggested alternative provided.' : 'No available slot within suggestions; schedule busy.';
    } else {
        $response['overlap'] = $overlapCount;
        $response['status']  = true;
        $response['message'] = 'Slot available.';
    }

    echo json_encode($response);
    exit;
}



if (isset($_POST['action']) && $_POST['action'] === 'submit_request') {

    $response = [];
    $clientData = $_POST['client_data'];
    $vehicleData = $_POST['vehicle_data'];
    $services = $_POST['selected_services'] ?? [];
    $schedule = $_POST['schedule'] ?? null;
    $request_type = $_POST['request_type'] ?? null;
    $request_status = $_POST['request_status'] ?? null;

    if (!$schedule || empty($services)) {
        $response['status'] = 'error';
        $response['message'] = 'Missing schedule or services.';
        echo json_encode($response);
        exit;
    }

    // start transaction
    $db_connection->begin_transaction();

    try {
        // --- Handle Client (new or existing)
        $clientId = null;
        if (isset($clientData['isNew']) && $clientData['isNew']) {
            $query = "INSERT INTO clientstbl (first_name, last_name, middle_name, contact_number, email, address) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param(
                "ssssss",
                $clientData['first_name'],
                $clientData['last_name'],
                $clientData['middle_name'],
                $clientData['contact_number'],
                $clientData['email'],
                $clientData['address']
            );
            if (!$stmt->execute()) {
                throw new Exception('Failed to create new client: ' . $stmt->error);
            }
            $clientId = $stmt->insert_id;
            $stmt->close();
        } else {
            $clientId = intval($clientData['client_id'] ?? 0);
            if ($clientId <= 0) {
                throw new Exception('Invalid client_id provided.');
            }

            if (isset($clientData['detailsChanged']) && $clientData['detailsChanged']) {
                $query = "UPDATE clientstbl 
                            SET contact_number = ?, email = ?, address = ?
                            WHERE client_id = ?";
                $stmt = $db_connection->prepare($query);
                $stmt->bind_param(
                    "sssi",
                    $clientData['contact_number'],
                    $clientData['email'],
                    $clientData['address'],
                    $clientId
                );

                if (!$stmt->execute()) {
                    throw new Exception('Failed to update client details: ' . $stmt->error);
                }
                $stmt->close();
            }
        }

        // --- Handle Vehicle (new or existing) with safer check for temp IDs
        $vehicleId = null;
        $isNewVehicle = isset($vehicleData['isNew']) && $vehicleData['isNew'];

        // If not flagged new, validate the provided vehicle_id; treat non-positive as "new"
        if (!$isNewVehicle) {
            $rawVid = $vehicleData['vehicle_id'] ?? null;
            $vid = intval($rawVid);
            if ($vid <= 0) {
                // client likely sent a temp id -> force creation
                $isNewVehicle = true;
            } else {
                $vehicleId = $vid;
            }
        }

        if ($isNewVehicle) {
            $query = "INSERT INTO vehiclestbl (client_id, make, model, plate_number, color, transmission_type, fuel_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param(
                "issssss",
                $clientId,
                $vehicleData['make'],
                $vehicleData['model'],
                $vehicleData['plate_number'],
                $vehicleData['color'],
                $vehicleData['transmission_type'],
                $vehicleData['fuel_type']
            );
            if (!$stmt->execute()) {
                throw new Exception('Failed to create vehicle: ' . $stmt->error);
            }
            $vehicleId = $stmt->insert_id;
            $stmt->close();

            if ($vehicleId <= 0) {
                throw new Exception('Vehicle insert returned invalid insert_id.');
            }
        } else {
            // already sanitized above
            if ($vehicleId <= 0) {
                throw new Exception('Invalid vehicle_id provided.');
            }
        }

        // --- Insert into Service Request Table
        $query = "INSERT INTO requeststbl (client_id, vehicle_id, request_type, sched_dt, request_dt, status) VALUES (?, ?, ?, NULL, ?, ?)";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("iisss", $clientId, $vehicleId, $request_type, $schedule, $request_status);
        if (!$stmt->execute()) {
            throw new Exception('Failed to submit service request: ' . $stmt->error);
        }
        $requestId = $stmt->insert_id;
        $stmt->close();

        // --- Insert Selected Services
        $insertService = $db_connection->prepare("INSERT INTO requested_servicestbl (service_id, request_id, clients_comment, custom_est_duration, custom_labor_cost) VALUES (?, ?, ?, ?, ?)");
        foreach ($services as $svc) {
            // normalize fields to avoid warnings when some fields missing
            $svcId = intval($svc['id'] ?? 0);
            $clientsComment = $svc['comment'] ?? '';
            $estimatedDuration = $svc['estimated_duration'] ?? '';
            $laborCost = (float)($svc['labor_cost'] ?? 0.0);

            if ($svcId <= 0) {
                throw new Exception('Invalid service id in selected_services.');
            }

            $insertService->bind_param("iissd", $svcId, $requestId, $clientsComment, $estimatedDuration, $laborCost);
            if (!$insertService->execute()) {
                throw new Exception('Failed to add service details: ' . $insertService->error);
            }
        }
        $insertService->close();

        // commit
        $db_connection->commit();

        $response['status'] = 'success';
        $response['message'] = 'Service request successfully submitted.';

        // notifications / logs (your existing functions)
        $type = 'service';
        $message = "New Service Request submitted. [Request ID: $requestId]";
        if (function_exists('sendNotification')) {
            sendNotification($db_connection, $type, $message, ['Admin', "Super Admin"], 'requests');
        }
        if (function_exists('insertLog')) {
            insertLog($db_connection, "Submitted a new service request / Request ID: $requestId", "Request");
        }

        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $db_connection->rollback();
        error_log('Request submission error: ' . $e->getMessage());
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
        echo json_encode($response);
        exit;
    }
}


$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? null;


if ($action == 'assignMechanicsToRequest') {


    $requestId = $data['request_id'] ?? null;
    $mechanics = $data['mechanics'] ?? [];

    if (!$requestId) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid request ID.';
        echo json_encode($response);
        exit;
    }

    if (empty($mechanics)) {
        $response['status'] = 'error';
        $response['message'] = 'Mechanics list is empty.';
        echo json_encode($response);
        exit;
    }

    // Get the client_id from requeststbl
    $stmtClient = $db_connection->prepare("SELECT client_id FROM requeststbl WHERE request_id = ? LIMIT 1");
    $stmtClient->bind_param("i", $requestId);
    $stmtClient->execute();
    $stmtClient->bind_result($client_id);
    $stmtClient->fetch();
    $stmtClient->close();

    if (empty($client_id)) {
        $response['status'] = 'error';
        $response['message'] = 'Client not found for this request.';
        echo json_encode($response);
        exit;
    }

    // Get the client’s account_id from clientstbl
    $cli_acc_id = null;
    $stmtAcc = $db_connection->prepare("SELECT account_id FROM clientstbl WHERE client_id = ? LIMIT 1");
    $stmtAcc->bind_param("i", $client_id);
    $stmtAcc->execute();
    $stmtAcc->bind_result($cli_acc_id);
    $stmtAcc->fetch();
    $stmtAcc->close();



    // Check if record already exists for request_id
    $checkStmt = $db_connection->prepare("SELECT record_id FROM recordstbl WHERE request_id = ?");
    $checkStmt->bind_param("i", $requestId);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        // Stop everything here
        $response['status'] = 'error';
        $response['message'] = 'A record already exists for this request.';
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();

    date_default_timezone_set('Asia/Manila');
    $now = date('Y-m-d H:i:s');

    // para di matuloy lahat if may isang nag error
    $db_connection->begin_transaction();

    // Insert into recordstbl (no user_id)
    $insertRecordStmt = $db_connection->prepare("
        INSERT INTO recordstbl (request_id, started_dt, completion_dt, record_status)
        VALUES (?, ?, NULL, 'In Progress')
    ");
    if (!$insertRecordStmt) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare recordstbl insert.';
        $response['error'] = $db_connection->error;
        echo json_encode($response);
        exit;
    }

    $insertRecordStmt->bind_param("is", $requestId, $now);
    if (!$insertRecordStmt->execute()) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to insert into recordstbl.';
        $response['error'] = $insertRecordStmt->error;
        echo json_encode($response);
        exit;
    }
    $recordId = $db_connection->insert_id;

    // Prepare insert for assigned_mechanicstbl
    $insertJunctionStmt = $db_connection->prepare("
        INSERT INTO assigned_mechanicstbl (record_id, user_id)
        VALUES (?, ?)
    ");
    if (!$insertJunctionStmt) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare assigned_mechanicstbl insert.';
        $response['error'] = $db_connection->error;
        echo json_encode($response);
        exit;
    }

    // Loop through mechanics and insert into junction table
    foreach ($mechanics as $mechanic) {
        $userId = $mechanic['id'];
        $insertJunctionStmt->bind_param("ii", $recordId, $userId);

        if (!$insertJunctionStmt->execute()) {
            $db_connection->rollback();
            $response['status'] = 'error';
            $response['message'] = "Failed to insert into assigned mechanics for request#: $requestId";
            $response['error'] = $insertJunctionStmt->error;
            echo json_encode($response);
            exit;
        }
    }

    // Insert into invoicetbl with default status (e.g., 'Draft') and total_amount 0.00
    $insertInvoiceStmt = $db_connection->prepare("
        INSERT INTO invoicetbl (record_id, items_total, services_total, grand_total, status)
        VALUES (?, 0.00, 0.00, 0.00, 'Draft')
        ");
    if (!$insertInvoiceStmt) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare invoicetbl insert.';
        $response['error'] = $db_connection->error;
        echo json_encode($response);
        exit;
    }

    $insertInvoiceStmt->bind_param("i", $recordId);
    if (!$insertInvoiceStmt->execute()) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to insert into invoicetbl.';
        $response['error'] = $insertInvoiceStmt->error;
        echo json_encode($response);
        exit;
    }

    // Update request status
    $updateRequestStatusStmt = $db_connection->prepare("
        UPDATE requeststbl SET status = 'In Progress' WHERE request_id = ?
    ");
    if (!$updateRequestStatusStmt) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare requeststbl update.';
        $response['error'] = $db_connection->error;
        echo json_encode($response);
        exit;
    }

    $updateRequestStatusStmt->bind_param("i", $requestId);
    if (!$updateRequestStatusStmt->execute()) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = 'Failed to update request status.';
        $response['error'] = $updateRequestStatusStmt->error;
        echo json_encode($response);
        exit;
    }
    // Commit all
    $db_connection->commit();
    $response['status'] = 'success';
    $response['message'] = 'Mechanics assigned, record created, and request status updated.';
    $response['assigned_count'] = count($mechanics);
    $response['request_id'] = $requestId;

    // --- Send notifications to assigned mechanics ---
    $recipients = [];
    foreach ($mechanics as $m) {
        $stmt = mysqli_prepare($db_connection, "SELECT account_id FROM userstbl WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $m['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $account_id);
        if (mysqli_stmt_fetch($stmt)) {
            $recipients[] = $account_id;
        }
        mysqli_stmt_close($stmt);
    }

    // Notify assigned mechanics
    $type = "service";
    $message = "You have been assigned to a service. [Request ID: $requestId]";
    sendNotification($db_connection, $type, $message, $recipients, 'records');

    // Notify admin
    $adminMessage = "Service request has been started. [Request ID: $requestId]";
    sendNotification($db_connection, $type, $adminMessage, ['Admin'], 'records');

    // Notify client (if account_id exists)
    if (!empty($cli_acc_id)) {
        sendNotification(
            $db_connection,
            'service',
            "Your Request #$requestId has been started! / Status: In Progress",
            [$cli_acc_id],
            'requests'
        );
    }

    // --- Send Email Update to Client ---
    $emailResult = sendRequestStatusEmail($db_connection, $client_id, $requestId, 'In Progress');
    $response['email_status'] = $emailResult['success'] ? 'sent' : 'failed';
    $response['email_message'] = $emailResult['message'];

    // --- Log Action ---
    $logMsg = "Assigned Mechanics and Started a Service Request / Request ID: $requestId > Record ID: $recordId";
    if ($emailResult['success']) {
        $logMsg .= " / Email: Sent";
    } else {
        $logMsg .= " / Email: Failed";
    }
    insertLog($db_connection, $logMsg, "Request");

    // --- Return Response ---
    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'updateSchedule') {
    $request_id = $_POST['request_id'] ?? null;
    $new_schedule = $_POST['new_schedule'] ?? null;
    $reason = $_POST['resched_reason'] ?? null;

    if (!$request_id || !$new_schedule) {
        $response['status'] = 'error';
        $response['message'] = 'Missing request ID or new schedule.';
        echo json_encode($response);
        exit;
    }

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Step 1: Check current resched_count
        $check = $db_connection->prepare("SELECT resched_count FROM requeststbl WHERE request_id = ?");
        $check->bind_param('i', $request_id);
        $check->execute();
        $check_result = $check->get_result();
        $row = $check_result->fetch_assoc();
        $check->close();

        $currentCount = (int)($row['resched_count'] ?? 0);

        if ($currentCount >= 2) {
            throw new Exception("Reschedule limit reached. You can only reschedule up to 2 times.");
        }

        // Step 2: Update sched_dt and increment resched_count
        $stmt = $db_connection->prepare("
            UPDATE requeststbl 
            SET sched_dt = ?, resched_count = COALESCE(resched_count, 0) + 1 
            WHERE request_id = ?
        ");
        $stmt->bind_param('si', $new_schedule, $request_id);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            throw new Exception("Failed to update schedule: " . $db_connection->error);
        }

        // Step 3: Fetch updated row
        $select = $db_connection->prepare("SELECT * FROM requeststbl WHERE request_id = ?");
        $select->bind_param('i', $request_id);
        $select->execute();
        $result = $select->get_result();
        $updatedRow = $result->fetch_assoc();
        $select->close();

        // Commit transaction
        $db_connection->commit();

        $response['status'] = 'success';
        $response['message'] = 'Schedule updated and reschedule count incremented.';
        $response['updated_request'] = $updatedRow;
        insertLog($db_connection, "Rescheduled a service request / Request ID: $request_id", "Request");

        // ✅ Fetch client’s account_id from clientstbl
        $client_account_id = null;
        if (!empty($updatedRow['client_id'])) {
            $clientLookup = $db_connection->prepare("SELECT account_id FROM clientstbl WHERE client_id = ?");
            $clientLookup->bind_param('i', $updatedRow['client_id']);
            $clientLookup->execute();
            $clientResult = $clientLookup->get_result();
            if ($clientRow = $clientResult->fetch_assoc()) {
                $client_account_id = $clientRow['account_id'];
            }
            $clientLookup->close();
        }

        // ✅ Format the new schedule for notification (example: "October 15, 2025 at 2:30 PM")
        $formatted_schedule = date('F j, Y \\a\\t g:i A', strtotime($new_schedule));

        // ✅ Send notification only if client_account_id is valid
        if (!empty($client_account_id)) {
            sendNotification(
                $db_connection,
                'service',
                "Your Request #$request_id has been rescheduled to - $formatted_schedule / Reason: $reason",
                [$client_account_id],
                'requests'
            );
        }
    } catch (Exception $e) {
        // Rollback transaction on any error
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }


    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'updateAssignedService') {
    $response = [];

    $rst_id = $_POST['rst_id'];
    $request_id = $_POST['request_id'];
    $service_name = $_POST['service_name'];
    $estimated_duration = $_POST['estimated_duration'];
    $labor_cost = $_POST['labor_cost'];

    $is_name_changed = filter_var($_POST['is_name_changed'], FILTER_VALIDATE_BOOLEAN);
    $is_duration_changed = filter_var($_POST['is_duration_changed'], FILTER_VALIDATE_BOOLEAN);
    $is_cost_changed = filter_var($_POST['is_cost_changed'], FILTER_VALIDATE_BOOLEAN);

    // Only update changed fields
    $fieldsToUpdate = [];
    $params = [];
    $types = '';

    if ($is_name_changed) {
        $fieldsToUpdate[] = "custom_service = ?";
        $params[] = $service_name;
        $types .= 's';
    }
    if ($is_duration_changed) {
        $fieldsToUpdate[] = "custom_est_duration = ?";
        $params[] = $estimated_duration;
        $types .= 's';
    }
    if ($is_cost_changed) {
        $fieldsToUpdate[] = "custom_labor_cost = ?";
        $params[] = $labor_cost;
        $types .= 'd';
    }

    if (!empty($fieldsToUpdate)) {
        $params[] = $rst_id;
        $types .= 'i';
        $query = "UPDATE requested_servicestbl SET " . implode(', ', $fieldsToUpdate) . " WHERE rst_id = ?";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Service updated.';
            insertLog($db_connection, "Modified service's details / Request ID: $request_id", "Request");
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Update failed.';
        }

        $stmt->close();
    } else {
        $response['status'] = 'no_changes';
        $response['message'] = 'No fields were modified.';
    }

    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'deleteAssignedService') {
    $response = [];
    $rst_id = intval($_POST['rst_id']);
    $request_id = intval($_POST['request_id']);

    $stmt = $db_connection->prepare("DELETE FROM requested_servicestbl WHERE rst_id = ?");
    $stmt->bind_param('i', $rst_id);

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Service deleted successfully.';
        insertLog($db_connection, "Removed a service from a request / Request ID: $request_id", "Request");
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to delete the service.';
    }

    $stmt->close();
    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'addServiceToRequest') {
    $response = [];

    $request_id = intval($_POST['request_id']);
    $is_custom = ($_POST['is_custom_service'] ?? '') === 'true';
    $clients_comment = $_POST['clients_comment'] ?? '';

    if ($is_custom) {
        // Always handle custom service with its own details
        $custom_name     = $_POST['service_name'] ?? '';
        $custom_duration = $_POST['estimated_duration'] ?? '';
        $custom_cost     = $_POST['labor_cost'] ?? '0.00';

        $stmt = $db_connection->prepare("
            INSERT INTO requested_servicestbl 
                (request_id, custom_service, custom_est_duration, custom_labor_cost, clients_comment) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $request_id, $custom_name, $custom_duration, $custom_cost, $clients_comment);
    } else {
        // Always handle predefined service, optionally with custom values
        $service_id      = intval($_POST['service_id'] ?? 0);
        $custom_duration = $_POST['estimated_duration'] ?? null;
        $custom_cost     = $_POST['labor_cost'] ?? null;

        $stmt = $db_connection->prepare("
            INSERT INTO requested_servicestbl 
                (request_id, service_id, custom_est_duration, custom_labor_cost, clients_comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $request_id, $service_id, $custom_duration, $custom_cost, $clients_comment);
    }

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Service added successfully.';
        $response['insert_id'] = $stmt->insert_id;

        insertLog($db_connection, "Added a service to a request / Request ID: $request_id", "Request");
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to add service.';
        $response['error'] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $requestId = intval($_POST['request_id']);
    $action = $_POST['action'];
    $reason = $_POST['reason'] ?? '';

    $response = [];

    // Always fetch client_id first (and status for approve)
    $client_id = null;
    $status = null;

    $check = $db_connection->prepare("SELECT status, client_id FROM requeststbl WHERE request_id = ? LIMIT 1");
    $check->bind_param("i", $requestId);
    $check->execute();
    $check->bind_result($status, $client_id);
    $check->fetch();
    $check->close();

    // Get account_id from clientstbl
    $account_id = null;
    if ($client_id) {
        $acctQuery = $db_connection->prepare("SELECT account_id FROM clientstbl WHERE client_id = ? LIMIT 1");
        $acctQuery->bind_param("i", $client_id);
        $acctQuery->execute();
        $acctQuery->bind_result($account_id);
        $acctQuery->fetch();
        $acctQuery->close();
    }

    if ($action === 'approveRequest') {
        if ($status !== 'Pending') {
            $response['status'] = 'error';
            $response['message'] = 'Only pending requests can be approved.';
        } else {
            $approve = $db_connection->prepare("UPDATE requeststbl SET status = 'Approved' WHERE request_id = ?");
            $approve->bind_param("i", $requestId);

            if ($approve->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Request approved successfully.';

                // --- Send in-app notification only if account exists ---
                if (!empty($account_id)) {
                    sendNotification($db_connection, 'service', "Your Request #$requestId has been approved!", [$account_id], 'requests');
                }

                // --- Send email update ---
                $emailResult = sendRequestStatusEmail($db_connection, $client_id, $requestId, 'Approved');

                // --- Include email status in response ---
                $response['email_status'] = $emailResult['success'] ? 'sent' : 'failed';
                $response['email_message'] = $emailResult['message'];

                // --- Log approval with email status ---
                $emailLog = $emailResult['success'] ? 'Email sent successfully' : 'Email failed to send';
                insertLog($db_connection, "Approved a Request / Request ID: $requestId / {$emailLog}", "Request");

                // --- Append email status to success message ---
                $response['message'] .= $emailResult['success']
                    ? ' Email notification sent successfully.'
                    : ' However, email notification failed to send.';
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to approve request.';
            }
        }
    } elseif ($action === 'cancelRequest') {
        $cancel = $db_connection->prepare("UPDATE requeststbl SET status = 'Cancelled' WHERE request_id = ?");
        $cancel->bind_param("i", $requestId);

        if ($cancel->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Request cancelled successfully.';

            // Send notification only if account_id exists
            if (!empty($account_id)) {
                sendNotification(
                    $db_connection,
                    'service',
                    "Your Request #$requestId has been cancelled. / Reason: \"$reason\"",
                    [$account_id],
                    'requests'
                );
            }

            // Send email update
            $emailResult = sendRequestStatusEmail($db_connection, $client_id, $requestId, 'Cancelled', $reason);
            $response['email_status'] = $emailResult['success'] ? 'sent' : 'failed';
            $response['email_message'] = $emailResult['message'];

            // Log with email status
            $emailLog = $emailResult['success'] ? 'Email sent' : 'Email failed';
            insertLog($db_connection, "Cancelled a Request / Request ID: $requestId ($emailLog)", "Request");
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to cancel request.';
        }
    }

    echo json_encode($response);
    exit;
}
