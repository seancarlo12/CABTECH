<?php
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
}

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

    // ===============================
    // FETCH AVAILABLE SERVICES
    // ===============================
    if ($action === 'fetch_available_services' && isset($_POST['request_id'])) {
        $sql = "SELECT * FROM servicestbl";
        $result = $db_connection->query($sql);

        $services = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $services[] = $row;
            }
        }

        echo json_encode($services);
        exit;
    }

    // ===============================
    // ADD SERVICE TO REQUEST
    // ===============================
    // ===============================
    if ($action === 'add_service_to_request' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        $service_id = !empty($_POST['service_select']) ? (int)$_POST['service_select'] : null;
        $custom_service = trim($_POST['custom_service'] ?? '');
        $comment = trim($_POST['service_comment'] ?? '');

        try {
            // First, check if the service already exists in the request
            if ($service_id) {
                $check = $db_connection->prepare("
                SELECT COUNT(*) FROM requested_servicestbl 
                WHERE request_id = ? AND service_id = ?
            ");
                $check->bind_param("ii", $request_id, $service_id);
            } else {
                $check = $db_connection->prepare("
                SELECT COUNT(*) FROM requested_servicestbl 
                WHERE request_id = ? AND custom_service = ?
            ");
                $check->bind_param("is", $request_id, $custom_service);
            }

            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();

            if ($count > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This service has already been added to the request.'
                ]);
                exit;
            }

            // Default values for labor cost and duration
            $labor_cost = null;
            $estimated_duration = null;

            // If a service_id is selected, fetch its details
            if ($service_id) {
                $getDetails = $db_connection->prepare("
                SELECT labor_cost, estimated_duration 
                FROM servicestbl 
                WHERE service_id = ?
            ");
                $getDetails->bind_param("i", $service_id);
                $getDetails->execute();
                $getDetails->bind_result($labor_cost, $estimated_duration);
                $getDetails->fetch();
                $getDetails->close();
            }

            // Prepare insert depending on whether it's a predefined or custom service
            if ($service_id) {
                $stmt = $db_connection->prepare("
                INSERT INTO requested_servicestbl 
                (request_id, service_id, clients_comment, custom_labor_cost, custom_est_duration)
                VALUES (?, ?, ?, ?, ?)
            ");
                $stmt->bind_param("iisds", $request_id, $service_id, $comment, $labor_cost, $estimated_duration);
            } else {
                $stmt = $db_connection->prepare("
                INSERT INTO requested_servicestbl 
                (request_id, service_id, custom_service, clients_comment, custom_labor_cost, custom_est_duration)
                VALUES (?, NULL, ?, ?, 0.00, '00:00:00')
            ");
                $stmt->bind_param("iss", $request_id, $custom_service, $comment);
            }

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'New service successfully added.'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to add service.'];
            }

            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }

        echo json_encode($response);
        exit;
    }


    // ===============================
    // REMOVE SERVICE
    // ===============================
    if ($action === 'remove_service' && isset($_POST['rst_id'])) {
        $rst_id = (int)$_POST['rst_id'];

        try {
            // Step 1: Get the related request_id for this rst_id
            $getRequest = $db_connection->prepare("
                SELECT request_id FROM requested_servicestbl WHERE rst_id = ?
            ");
            $getRequest->bind_param("i", $rst_id);
            $getRequest->execute();
            $getRequest->bind_result($request_id);
            $getRequest->fetch();
            $getRequest->close();

            if (!$request_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid service record.'
                ]);
                exit;
            }

            // Step 2: Count how many services this request currently has
            $countStmt = $db_connection->prepare("
                SELECT COUNT(*) FROM requested_servicestbl WHERE request_id = ?
            ");
            $countStmt->bind_param("i", $request_id);
            $countStmt->execute();
            $countStmt->bind_result($serviceCount);
            $countStmt->fetch();
            $countStmt->close();

            // Step 3: Prevent deletion if only one service left
            if ($serviceCount <= 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot remove the last service from this request.'
                ]);
                exit;
            }

            // Step 4: Proceed to delete
            $stmt = $db_connection->prepare("
                DELETE FROM requested_servicestbl WHERE rst_id = ?
            ");
            $stmt->bind_param("i", $rst_id);

            if ($stmt->execute()) {
                $response = [
                    'success' => true,
                    'message' => 'Service successfully removed.'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Failed to remove service.'
                ];
            }

            $stmt->close();
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }

        echo json_encode($response);
        exit;
    }

    // ===============================
    // UPDATE COMMENT
    // ===============================
    if ($action === 'update_comment' && isset($_POST['rst_id'], $_POST['comment'])) {
        $rst_id = (int)$_POST['rst_id'];
        $comment = trim($_POST['comment']);

        try {
            $stmt = $db_connection->prepare("UPDATE requested_servicestbl SET clients_comment = ? WHERE rst_id = ?");
            $stmt->bind_param("si", $comment, $rst_id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Comment successfully updated.'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update comment.'];
            }

            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }

        echo json_encode($response);
        exit;
    }


    if ($_POST['action'] === 'cancel_request') {
        $requestId = (int) $_POST['request_id'];
    
        if ($requestId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request ID.'
            ]);
            exit;
        }
    
        try {
            // Check if request exists and not already cancelled/completed
            $checkStmt = $db_connection->prepare("SELECT status FROM requeststbl WHERE request_id = ?");
            $checkStmt->bind_param("i", $requestId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
    
            if ($result->num_rows === 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Request not found.'
                ]);
                exit;
            }
    
            $row = $result->fetch_assoc();
            if (in_array(strtolower($row['status']), ['cancelled', 'completed'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This request can no longer be cancelled.'
                ]);
                exit;
            }
    
            // Update status to Cancelled
            $updateStmt = $db_connection->prepare("UPDATE requeststbl SET status = 'Cancelled' WHERE request_id = ?");
            $updateStmt->bind_param("i", $requestId);
            $success = $updateStmt->execute();
    
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Request has been successfully cancelled.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to cancel the request. Please try again.'
                ]);
            }
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    
        exit;
    }
}

// Default fallback response
echo json_encode($response);
exit;
