<?php
session_name('CABTECH_SYSTEM');
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}



if (isset($_POST['row_id']) && isset($_POST['action']) && $_POST['action'] == 'getRowData') {
    $recordId = $_POST['row_id'];
    $response = [];

    if (!is_numeric($recordId) || intval($recordId) <= 0) {
        $response = [
            'request' => null,
            'error' => 'Invalid record ID.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Get request_id from recordstbl using record_id
    $getRecordQuery = "SELECT * FROM recordstbl WHERE record_id = ?";
    $stmt = $db_connection->prepare($getRecordQuery);
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $recordResult = $stmt->get_result();

    if ($recordResult && $recordResult->num_rows > 0) {
        $recordData = $recordResult->fetch_assoc();
        $requestId = $recordData['request_id'];
        $response['record'] = $recordData; // Add record info to response
    } else {
        $response['record'] = null;
        $response['error'] = 'Record not found.';
        echo json_encode($response);
        exit;
    }

    // Mechanics (only if requested)
    if (isset($_POST['getMechanics']) && $_POST['getMechanics'] == 'true') {
        $getMechanicsQuery = "SELECT * FROM userstbl WHERE role = 'Mechanic'";
        $mechanicsResult = $db_connection->query($getMechanicsQuery);

        $mechanics = [];

        if ($mechanicsResult && $mechanicsResult->num_rows > 0) {
            while ($row = $mechanicsResult->fetch_assoc()) {
                $mechanicId = $row['user_id'];
                $workStatus = $row['work_status'];

                // Fetch specialties
                $getSpecialtiesQuery = "
                    SELECT s.name FROM mechanic_specialtiestbl ms
                    JOIN specialtiestbl s ON ms.specialty_id = s.specialty_id
                    WHERE ms.user_id = ?
                ";
                $stmtSpec = $db_connection->prepare($getSpecialtiesQuery);
                $stmtSpec->bind_param("i", $mechanicId);
                $stmtSpec->execute();
                $specResult = $stmtSpec->get_result();

                $specialties = [];
                while ($specRow = $specResult->fetch_assoc()) {
                    $specialties[] = $specRow['name'];
                }

                // Count "In Progress"
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

    // Fetch request using request_id
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

    // Fetch client + vehicle using request info
    $clientId = $requestData['client_id'];
    $vehicleId = $requestData['vehicle_id'];

    // Client
    $stmt = $db_connection->prepare("SELECT * FROM clientstbl WHERE client_id = ?");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $clientResult = $stmt->get_result();
    $response['client'] = $clientResult->fetch_assoc() ?? null;

    // Vehicle
    $stmt = $db_connection->prepare("SELECT * FROM vehiclestbl WHERE vehicle_id = ?");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();
    $response['vehicle'] = $vehicleResult->fetch_assoc() ?? null;

    // Mechanics assigned
    $stmt = $db_connection->prepare("
        SELECT am.id AS assigned_id, am.record_id, am.user_id, u.*
        FROM assigned_mechanicstbl am
        INNER JOIN userstbl u ON am.user_id = u.user_id
        WHERE am.record_id = ?
    ");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $mechanicsResult = $stmt->get_result();

    $mechanics = [];
    while ($row = $mechanicsResult->fetch_assoc()) {
        $mechanics[] = $row;
    }
    $response['mechanics_assigned'] = $mechanics;

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

    // Fetch invoice linked to this record
    $invoiceSql = "SELECT * FROM invoicetbl WHERE record_id = ?";
    $stmt = $db_connection->prepare($invoiceSql);
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $invoiceResult = $stmt->get_result();

    if ($invoiceResult->num_rows > 0) {
        $response['invoice'] = $invoiceResult->fetch_assoc();
    } else {
        $response['invoice'] = null; // No invoice yet
    }

    // Final response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'getItemsInventory') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $rst_id    = intval($_POST['rst_id'] ?? 0);
    $response = [];

    // 1. Get all items from inventory
    $sqlItems = "SELECT * FROM itemstbl";
    $resultItems = $db_connection->query($sqlItems);
    $items = [];
    if ($resultItems && $resultItems->num_rows > 0) {
        while ($row = $resultItems->fetch_assoc()) {
            $items[] = $row;
        }
    }

    // 2. Service-linked OR Miscellaneous items
    if ($rst_id > 0) {
        // Service-specific items
        $sqlNeeded = "
            SELECT 
                rs.rst_id,
                rs.service_id,
                rs.custom_service,
                rs.custom_est_duration,
                rs.custom_labor_cost,
                COALESCE(rs.custom_service, s.service_name) AS service_name,
                n.itmn_id,
                n.item_id,
                n.record_id,
                n.record_price,
                n.quantity,
                i.name AS item_name
            FROM requested_servicestbl rs
            LEFT JOIN servicestbl s
                ON rs.service_id = s.service_id
            LEFT JOIN items_neededtbl n
                ON n.rst_id = rs.rst_id AND n.record_id = ?
            LEFT JOIN itemstbl i
                ON n.item_id = i.item_id
            WHERE rs.rst_id = ?
        ";

        $stmtNeeded = $db_connection->prepare($sqlNeeded);
        $stmtNeeded->bind_param("ii", $record_id, $rst_id);
    } else {
        // Miscellaneous items (no service, rst_id = 0)
        // Miscellaneous items (no service, rst_id = 0)
        $sqlNeeded = "
    SELECT 
        0 AS rst_id,
        NULL AS service_id,
        NULL AS custom_service,
        NULL AS custom_est_duration,
        NULL AS custom_labor_cost,
        'Miscellaneous' AS service_name,
        n.itmn_id,
        n.item_id,
        n.record_id,
        n.record_price,
        n.quantity,
        i.name AS item_name
    FROM items_neededtbl n
    LEFT JOIN itemstbl i
        ON n.item_id = i.item_id
    WHERE n.record_id = ? AND n.rst_id = 0
";

        $stmtNeeded = $db_connection->prepare($sqlNeeded);
        $stmtNeeded->bind_param("i", $record_id);
    }

    $stmtNeeded->execute();
    $resultNeeded = $stmtNeeded->get_result();

    $items_needed = [];
    $serviceInfo = null;

    if ($resultNeeded && $resultNeeded->num_rows > 0) {
        while ($row = $resultNeeded->fetch_assoc()) {
            $serviceInfo = [
                'rst_id' => $row['rst_id'],
                'service_id' => $row['service_id'],
                'custom_service' => $row['custom_service'],
                'custom_est_duration' => $row['custom_est_duration'],
                'custom_labor_cost' => $row['custom_labor_cost'],
                'service_name' => $row['service_name']
            ];

            if ($row['itmn_id']) {
                $items_needed[] = [
                    'itmn_id' => $row['itmn_id'],
                    'record_id' => $row['record_id'],
                    'item_id' => $row['item_id'],
                    'rst_id' => $row['rst_id'],
                    'record_price' => $row['record_price'],
                    'quantity' => $row['quantity'],
                    'item_name' => $row['item_name']
                ];
            }
        }
    }

    // Combine response
    $response['status'] = 'success';
    $response['inventoryItems'] = $items;
    $response['itemsNeeded'] = $items_needed; // empty if none
    $response['serviceInfo'] = $serviceInfo;  // still present even if no items

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'useItem') {
    $record_id = intval($_POST['record_id']);
    $item_id   = intval($_POST['item_id']);
    $rst_id    = intval($_POST['rst_id']); // include service id

    if ($record_id <= 0 || $item_id <= 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid record, item, or service ID."
        ]);
        exit;
    }

    try {
        // 1. Check if already exists for this record + item + service
        $checkStmt = $db_connection->prepare("
            SELECT quantity 
            FROM items_neededtbl 
            WHERE record_id = ? AND item_id = ? AND rst_id = ?
        ");
        $checkStmt->bind_param("iii", $record_id, $item_id, $rst_id);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            echo json_encode([
                "status"  => "exists",
                "message" => "This item is already added for this service. Please update the quantity manually."
            ]);
            exit;
        }

        // 2. Start transaction
        $db_connection->begin_transaction();

        // 3. Get current inventory price
        $priceStmt = $db_connection->prepare("SELECT price, stock FROM itemstbl WHERE item_id = ?");
        $priceStmt->bind_param("i", $item_id);
        $priceStmt->execute();
        $priceStmt->bind_result($inventory_price, $current_stock);
        $priceStmt->fetch();
        $priceStmt->close();

        if ($inventory_price === null) {
            throw new Exception("Item not found in inventory.");
        }
        if ($current_stock <= 0) {
            throw new Exception("Item is out of stock.");
        }

        // 4. Insert into items_neededtbl with quantity = 1
        $insertStmt = $db_connection->prepare("
            INSERT INTO items_neededtbl (record_id, item_id, rst_id, quantity, record_price)
            VALUES (?, ?, ?, 1, ?)
        ");
        $insertStmt->bind_param("iiid", $record_id, $item_id, $rst_id, $inventory_price);
        $insertStmt->execute();
        $insertStmt->close();

        // 5. Decrement stock
        $updateStockStmt = $db_connection->prepare("
            UPDATE itemstbl
            SET stock = stock - 1
            WHERE item_id = ? AND stock > 0
        ");
        $updateStockStmt->bind_param("i", $item_id);
        $updateStockStmt->execute();
        if ($updateStockStmt->affected_rows === 0) {
            $db_connection->rollback();
            echo json_encode([
                "status" => "error",
                "message" => "Failed to decrement stock. Item may be out of stock."
            ]);
            exit;
        }
        $updateStockStmt->close();

        // 6. Update item status
        $new_status = ($current_stock - 1 <= 0) ? 'No Stock' : 'Available';
        $statusUpdate = $db_connection->prepare("UPDATE itemstbl SET status = ? WHERE item_id = ?");
        $statusUpdate->bind_param("si", $new_status, $item_id);
        $statusUpdate->execute();
        $statusUpdate->close();

        // 7. Commit transaction
        $db_connection->commit();

        echo json_encode([
            "status"  => "success",
            "message" => "Item successfully used and stock updated."
        ]);

        insertLog($db_connection, "Added an item (ID:$item_id) to a service / Record ID: $record_id", "Record");
        exit;
    } catch (Exception $e) {
        $db_connection->rollback();
        echo json_encode([
            "status"  => "error",
            "message" => "Transaction failed: " . $e->getMessage()
        ]);
        exit;
    }
}



if (isset($_POST['action']) && $_POST['action'] === 'updateItemQuantity') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $item_id   = intval($_POST['item_id'] ?? 0);
    $new_qty   = intval($_POST['quantity'] ?? 0);
    $rst_id    = intval($_POST['rst_id'] ?? 0); // new: service id

    if ($record_id > 0 && $item_id > 0) {
        try {
            // Start transaction
            $db_connection->begin_transaction();

            // 1. Get old quantity for this record + item + service
            $stmt = $db_connection->prepare("
                SELECT quantity 
                FROM items_neededtbl 
                WHERE record_id = ? AND item_id = ? AND rst_id = ?
            ");
            $stmt->bind_param("iii", $record_id, $item_id, $rst_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $old_qty = $row ? intval($row['quantity']) : 0;
            $stmt->close();

            // 2. Compute difference
            $diff = $new_qty - $old_qty;

            if ($diff > 0) {
                // 🔎 Check available stock before increasing
                $stmt = $db_connection->prepare("SELECT stock FROM itemstbl WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $stmt->bind_result($current_stock);
                $stmt->fetch();
                $stmt->close();

                if ($current_stock < $diff) {
                    $db_connection->rollback();
                    echo json_encode([
                        'status' => 'lack_of_stock',
                        'message' => "Not enough stock available. Current stock: $current_stock"
                    ]);
                    exit;
                }
            }

            // 3. Update items_neededtbl quantity for this specific service
            $stmt = $db_connection->prepare("
                UPDATE items_neededtbl 
                SET quantity = ? 
                WHERE record_id = ? AND item_id = ? AND rst_id = ?
            ");
            $stmt->bind_param("iiii", $new_qty, $record_id, $item_id, $rst_id);

            if (!$stmt->execute()) {
                $db_connection->rollback();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update item quantity.'
                ]);
                exit;
            }
            $stmt->close();

            // 4. Update stock in itemstbl
            if ($diff !== 0) {
                $stmt = $db_connection->prepare("
                    UPDATE itemstbl 
                    SET stock = stock - ? 
                    WHERE item_id = ?
                ");
                $stmt->bind_param("ii", $diff, $item_id);
                $stmt->execute();
                $stmt->close();
            }

            // 5. Check final stock to update status
            $stmt = $db_connection->prepare("SELECT stock FROM itemstbl WHERE item_id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->bind_result($remaining_stock);
            $stmt->fetch();
            $stmt->close();

            $status = $remaining_stock <= 0 ? 'No Stock' : 'Available';
            $stmt = $db_connection->prepare("UPDATE itemstbl SET status = ? WHERE item_id = ?");
            $stmt->bind_param("si", $status, $item_id);
            $stmt->execute();
            $stmt->close();

            // ✅ Commit transaction
            $db_connection->commit();

            echo json_encode([
                'status' => 'success',
                'message' => 'Item quantity, stock, and status updated successfully.',
                'old_qty' => $old_qty,
                'new_qty' => $new_qty,
                'stock_change' => -$diff,
                'final_stock' => $remaining_stock
            ]);

            insertLog($db_connection, "Added an item ID:$item_id to a service / Record ID: $record_id", "Record");
            exit;
        } catch (Exception $e) {
            $db_connection->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid record, item, or service ID.'
        ]);
        exit;
    }
}



if (isset($_POST['action']) && $_POST['action'] === 'removeItem') {
    $recordId = intval($_POST['record_id'] ?? 0);
    $itemId   = intval($_POST['item_id'] ?? 0);
    $rstId    = intval($_POST['rst_id'] ?? 0); // corrected param name

    if ($recordId <= 0 || $itemId <= 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing or invalid record_id / item_id / rst_id.'
        ]);
        exit;
    }

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // 1. Get quantity before deleting
        $stmt = $db_connection->prepare("
            SELECT quantity 
            FROM items_neededtbl 
            WHERE record_id = ? AND item_id = ? AND rst_id = ?
        ");
        $stmt->bind_param("iii", $recordId, $itemId, $rstId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $qty = $row ? intval($row['quantity']) : 0;
        $stmt->close();

        if ($qty <= 0) {
            throw new Exception("Item not found or already removed.");
        }

        // 2. Delete from items_neededtbl
        $stmt = $db_connection->prepare("
            DELETE FROM items_neededtbl 
            WHERE record_id = ? AND item_id = ? AND rst_id = ?
        ");
        $stmt->bind_param("iii", $recordId, $itemId, $rstId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to remove item from used items.");
        }
        $stmt->close();

        // 3. Restore stock and update status
        $stmt = $db_connection->prepare("
            UPDATE itemstbl 
            SET stock = stock + ?, 
                status = 'Available'
            WHERE item_id = ?
        ");
        $stmt->bind_param("ii", $qty, $itemId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to restore item stock.");
        }
        $stmt->close();

        // 4. Commit if all successful
        $db_connection->commit();

        $response = [
            'status'   => 'success',
            'message'  => "Removed item and restored stock (+$qty).",
            'qty'      => $qty,
            'itemId'   => $itemId,
            'recordId' => $recordId,
            'rstId'    => $rstId
        ];
        insertLog($db_connection, "Removed an item (ID:$itemId) from a service / Record ID: $recordId", "Record");
    } catch (Exception $e) {
        // Rollback if anything fails
        $db_connection->rollback();
        $response = [
            'status'  => 'error',
            'message' => $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'deleteAssignedService') {
    $response = [];
    $rst_id = intval($_POST['rst_id']);
    $recordId = intval($_POST['recordId']);

    if ($rst_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid service ID.'
        ]);
        exit;
    }

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // 1. Delete items associated with this service
        $stmtItems = $db_connection->prepare("DELETE FROM items_neededtbl WHERE rst_id = ?");
        $stmtItems->bind_param("i", $rst_id);
        if (!$stmtItems->execute()) {
            throw new Exception("Failed to delete items for this service.");
        }
        $stmtItems->close();

        // 2. Delete the service itself
        $stmtService = $db_connection->prepare("DELETE FROM requested_servicestbl WHERE rst_id = ?");
        $stmtService->bind_param("i", $rst_id);
        if (!$stmtService->execute()) {
            throw new Exception("Failed to delete the service.");
        }
        $stmtService->close();

        // 3. Commit transaction
        $db_connection->commit();

        $response['status'] = 'success';
        $response['message'] = 'Service and its items deleted successfully.';
        insertLog($db_connection, "Removed a service and its items from a record / Record ID: $recordId", "Record");
    } catch (Exception $e) {
        $db_connection->rollback();
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'addServiceToRequest') {
    $response = [];

    $request_id = intval($_POST['request_id']);
    $record_id = intval($_POST['recordId']);
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

        insertLog($db_connection, "Added a service to a record / Record ID: $record_id", "Record");
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to add service.';
        $response['error'] = $stmt->error;
    }

    $stmt->close();
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


if (isset($_POST['action']) && $_POST['action'] === 'updateAssignedService') {
    $response = [];

    $rst_id = $_POST['rst_id'];
    $record_id = $_POST['record_id'];
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

            insertLog($db_connection, "Modified service's details / Record ID: $record_id", "Record");
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


if (isset($_POST['action']) && $_POST['action'] === "completeInvoice") {
    $response = [];

    $invoice_id     = intval($_POST['invoice_id'] ?? 0);
    $recordId     = intval($_POST['recordId'] ?? 0);
    $requestId     = intval($_POST['requestId'] ?? 0);
    $items_total    = floatval($_POST['items_total'] ?? 0);
    $services_total = floatval($_POST['services_total'] ?? 0);
    $grand_total    = floatval($_POST['grand_total'] ?? 0);
    $mechanics    = json_decode($_POST['mechanics_assigned'] ?? '[]');

    if ($invoice_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid invoice ID."
        ]);
        exit;
    }

    // 🔎 Check if already issued
    $checkQuery = "SELECT status, record_id FROM invoicetbl WHERE invoice_id = ?";
    $checkStmt  = $db_connection->prepare($checkQuery);
    $checkStmt->bind_param("i", $invoice_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $invoiceData = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$invoiceData) {
        echo json_encode([
            "success" => false,
            "message" => "Invoice not found."
        ]);
        exit;
    }

    if ($invoiceData['status'] === 'Issued') {
        echo json_encode([
            "success" => false,
            "message" => "This invoice has already been issued."
        ]);
        exit;
    }

    // 📝 Update invoice + completion date in records
    $query = "UPDATE invoicetbl i
                JOIN recordstbl r ON i.record_id = r.record_id
                SET i.items_total = ?, 
                    i.services_total = ?, 
                    i.grand_total = ?, 
                    i.issued_dt = NOW(), 
                    i.status = 'Issued',
                    r.record_status = 'Invoice Issued'
                WHERE i.invoice_id = ?";

    $stmt = $db_connection->prepare($query);
    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Prepare failed: " . $db_connection->error
        ]);
        exit;
    }

    $stmt->bind_param("dddi", $items_total, $services_total, $grand_total, $invoice_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['issued_dt'] = date("Y-m-d H:i:s"); // return current PHP server date

            // ✅ Update requesttbl.status = 'Completed'
            if ($requestId > 0) {
                $updateRequest = "UPDATE requeststbl SET status = 'Completed' WHERE request_id = ?";
                $reqStmt = $db_connection->prepare($updateRequest);
                if ($reqStmt) {
                    $reqStmt->bind_param("i", $requestId);
                    if ($reqStmt->execute()) {
                        if ($reqStmt->affected_rows > 0) {
                            $response['request_update'] = "Request updated successfully.";
                        } else {
                            $response['request_update'] = "No matching request found or already completed.";
                        }
                    } else {
                        $response['request_update'] = "Execution failed: " . $reqStmt->error;
                    }
                    $reqStmt->close();
                } else {
                    $response['request_update'] = "Prepare failed: " . $db_connection->error;
                }
            } else {
                $response['request_update'] = "Invalid request ID.";
            }

            // ✅ Get client_id from requeststbl
            $clientAccountId = null;
            $getClientQuery = "SELECT r .client_id, c.account_id 
                            FROM requeststbl r 
                            JOIN clientstbl c ON r.client_id = c.client_id
                            WHERE r.request_id = ?";
            $getClientStmt = $db_connection->prepare($getClientQuery);
            if ($getClientStmt) {
                $getClientStmt->bind_param("i", $requestId);
                $getClientStmt->execute();
                $clientRes = $getClientStmt->get_result();
                if ($clientRes && $clientRow = $clientRes->fetch_assoc()) {
                    $clientAccountId = intval($clientRow['account_id']);
                    $response['client_account_id'] = $clientAccountId;
                }
                $getClientStmt->close();
            }

            $type = "service";
            $message = "Service has been completed and Invoice Issued. [Record ID: $recordId]";

            // Normalize $mechanics to an array
            if (is_object($mechanics)) {
                $mechanics = [$mechanics];
            } elseif (!is_array($mechanics)) {
                $mechanics = [];
            }

            // Safely extract account_ids
            $mechanicAccounts = array_map(
                fn($m) => (is_object($m) && isset($m->account_id)) ? intval($m->account_id) : null,
                $mechanics
            );

            // Remove nulls
            $mechanicAccounts = array_filter($mechanicAccounts);

            // Log mechanic IDs
            error_log("Debug: mechanic account_ids: " . implode(', ', $mechanicAccounts));

            // Merge with Admin/SuperAdmin roles
            $recipients = array_merge(['Admin'], $mechanicAccounts);

            error_log("Debug: recipients to send: " . print_r($recipients, true));

            // Send notification
            $notifResult = sendNotification($db_connection, $type, $message, $recipients, 'requests');
            // Send notification only if account_id exists
            if (!empty($clientAccountId)) {
                sendNotification($db_connection, 'service', "Your Request #$requestId service is completed and invoice has been issued.", [$clientAccountId], 'requests');
            }
            insertLog($db_connection, "Completed a service and issued an invoice / Record ID: $recordId", "Record");

            // Optional: include result in response
            $response['notification'] = $notifResult ? "Sent successfully" : "Failed to send";
        } else {
            $response['success'] = false;
            $response['message'] = "No rows updated. Invoice may not exist.";
        }
    } else {
        $response['success'] = false;
        $response['message'] = "Execution failed: " . $stmt->error;
    }

    $stmt->close();

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === "completePaid") {
    $response = [];

    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $recordId = intval($_POST['recordId'] ?? 0);
    $mechanics    = json_decode($_POST['mechanics_assigned'] ?? '[]');

    if ($invoice_id <= 0) {
        $response['success'] = false;
        $response['message'] = "Invalid invoice ID.";
        echo json_encode($response);
        exit;
    }

    // 🔎 Check if invoice exists
    $checkQuery = "SELECT record_id, status FROM invoicetbl WHERE invoice_id = ?";
    $checkStmt = $db_connection->prepare($checkQuery);
    $checkStmt->bind_param("i", $invoice_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $invoice = $result->fetch_assoc();
    $checkStmt->close();

    if (!$invoice) {
        $response['success'] = false;
        $response['message'] = "Invoice not found.";
        echo json_encode($response);
        exit;
    }

    if ($invoice['status'] === 'Paid') {
        $response['success'] = false;
        $response['message'] = "Invoice already marked as Paid.";
        echo json_encode($response);
        exit;
    }

    // 📝 Update invoice → Paid
    $query = "UPDATE invoicetbl 
            SET status = 'Paid'
            WHERE invoice_id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $invoice_id);

    if ($stmt->execute()) {
        // ✅ Update record as Completed
        $record_id = intval($invoice['record_id']);
        if ($record_id > 0) {
            $updateRecord = "UPDATE recordstbl 
                            SET record_status = 'Completed',
                            completion_dt = NOW()
                            WHERE record_id = ?";
            $recStmt = $db_connection->prepare($updateRecord);
            $recStmt->bind_param("i", $record_id);
            $recStmt->execute();
            $recStmt->close();
        }


        // Normalize $mechanics to an array
        if (is_object($mechanics)) {
            $mechanics = [$mechanics];
        } elseif (!is_array($mechanics)) {
            $mechanics = [];
        }

        // Safely extract account_ids
        $mechanicAccounts = array_map(
            fn($m) => (is_object($m) && isset($m->account_id)) ? intval($m->account_id) : null,
            $mechanics
        );


        $recipients = array_merge(['Admin', 'Super Admin'], $mechanicAccounts);


        $type = "service";
        $message = "Service Record fully completed. Invoice has been paid. [Record ID: $recordId]";

        $notifResult = sendNotification($db_connection, $type, $message, $recipients, 'records');
        insertLog($db_connection, "Completed a service and marked invoice as paid / Record ID: $recordId", "Record");


        // 🧩 Separate: Notify Client
        $clientAccountId = null;
        $getClientQuery = "
            SELECT c.account_id, r.request_id 
            FROM recordstbl rec
            JOIN requeststbl r ON rec.request_id = r.request_id
            JOIN clientstbl c ON r.client_id = c.client_id
            WHERE rec.record_id = ?";
        $getClientStmt = $db_connection->prepare($getClientQuery);
        if ($getClientStmt) {
            $getClientStmt->bind_param("i", $record_id);
            $getClientStmt->execute();
            $clientRes = $getClientStmt->get_result();
            if ($clientRes && $clientRow = $clientRes->fetch_assoc()) {
                $clientAccountId = intval($clientRow['account_id']);
                $requestId = intval($clientRow['request_id']);
                $response['client_account_id'] = $clientAccountId;
            }
            $getClientStmt->close();
        }

        // Send notification only if account_id exists
        if (!empty($clientAccountId)) {
            sendNotification($db_connection, 'service', "Your Request #$requestId invoice has been marked Paid!", [$clientAccountId], 'requests');
        }

        $response['success'] = true;
    } else {
        $response['success'] = false;
        $response['message'] = "Failed to update invoice: " . $stmt->error;
    }

    $stmt->close();

    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'getAllMech') {
    $record_id = intval($_POST['record_id'] ?? 0);

    // 🔹 Get all active mechanics
    $sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS name 
            FROM userstbl 
            WHERE role = 'Mechanic' 
                AND status = 'Active' 
                AND work_status = 'In'";
    $result = $db_connection->query($sql);

    // 🔹 Get already assigned mechanics for this record
    $assigned = [];
    if ($record_id > 0) {
        $assignedSql = "SELECT user_id FROM assigned_mechanicstbl WHERE record_id = $record_id";
        $assignedRes = $db_connection->query($assignedSql);
        while ($r = $assignedRes->fetch_assoc()) {
            $assigned[] = (int)$r['user_id'];
        }
    }

    if ($result && $result->num_rows > 0) {
        $mechanics = [];
        while ($row = $result->fetch_assoc()) {
            $row['assigned'] = in_array((int)$row['user_id'], $assigned);
            $mechanics[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $mechanics;
    } else {
        $response['success'] = false;
        $response['message'] = 'No active mechanics found.';
    }

    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'saveAssignedMech') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $mechanics = $_POST['mechanics'] ?? [];

    if ($record_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
        exit;
    }

    // --- Step 1: Get currently assigned mechanics ---
    $currentSql = "SELECT user_id FROM assigned_mechanicstbl WHERE record_id = ?";
    $stmt = $db_connection->prepare($currentSql);
    $stmt->bind_param('i', $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentMechanics = [];
    while ($row = $result->fetch_assoc()) {
        $currentMechanics[] = intval($row['user_id']);
    }
    $stmt->close();

    // --- Step 2: Determine added and removed mechanics ---
    $newMechanics = array_map('intval', $mechanics);
    $added = array_diff($newMechanics, $currentMechanics);
    $removed = array_diff($currentMechanics, $newMechanics);

    // --- Step 3: Clear previous assignments ---
    $deleteSql = "DELETE FROM assigned_mechanicstbl WHERE record_id = ?";
    $stmt = $db_connection->prepare($deleteSql);
    $stmt->bind_param('i', $record_id);
    $stmt->execute();
    $stmt->close();

    // --- Step 4: Insert new assignments ---
    if (!empty($newMechanics)) {
        $insertSql = "INSERT INTO assigned_mechanicstbl (record_id, user_id) VALUES (?, ?)";
        $stmt = $db_connection->prepare($insertSql);
        foreach ($newMechanics as $mechId) {
            $stmt->bind_param('ii', $record_id, $mechId);
            $stmt->execute();
        }
        $stmt->close();
    }

    // --- Step 5: Send notifications using sendNotification() ---
    if (!empty($added)) {
        $message = "You have been assigned to service record #{$record_id}.";
        sendNotification($db_connection, 'service', $message, $added, 'records');
    }

    if (!empty($removed)) {
        $message = "You have been removed from service record #{$record_id}.";
        sendNotification($db_connection, 'service', $message, $removed, 'records');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Assigned mechanics updated successfully.'
    ]);
    exit;
}
