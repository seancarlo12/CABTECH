<?php
session_start();
header('Content-Type: application/json');


if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}


if (isset($_POST['user_data']) && isset($_POST['action']) && $_POST['action'] == 'add') {
    $user = $_POST['user_data'];
    $response = [];

    // Extract and sanitize
    $userRole = trim($user['role']);
    $firstName = trim($user['first_name']);
    $lastName = trim($user['last_name']);
    $middleName = trim($user['middle_name']);
    $mobile = trim($user['mobile_number']);
    $email = trim($user['email']);
    $address = trim($user['address']);


    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($mobile)) {
        $response['status'] = 'error';
        $response['message'] = 'Missing Fields required.';
        echo json_encode($response);
        exit;
    }

    // Check if contact number or email already exists
    $checkQuery = "SELECT * FROM userstbl WHERE contact_number = ? OR email = ?";
    $checkStmt = $db_connection->prepare($checkQuery);
    $checkStmt->bind_param("ss", $mobile, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result && $result->num_rows > 0) {
        $response['status'] = 'existing';
        $response['message'] = 'Duplicate entry detected: A user record with this contact number/email already exists.';
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();



    $query = "INSERT INTO userstbl (role, first_name, last_name, middle_name, contact_number, email, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db_connection->prepare($query);

    if ($stmt) {
        $stmt->bind_param("sssssss", $userRole, $firstName, $lastName, $middleName, $mobile, $email, $address);
        $success = $stmt->execute();

        if ($success) {
            $insert_id = $db_connection->insert_id; // <-- Get the ID of the new row

            // if role looks like a mechanic, set work_status = 'Out' (and nothing else)
            if (stripos($userRole, 'mech') !== false) {
                $upd = $db_connection->prepare("UPDATE userstbl SET work_status = 'Out' WHERE user_id = ?");
                if ($upd) {
                    $upd->bind_param('i', $insert_id);
                    $upd->execute();
                    $upd->close();
                }
            }


            $response['status'] = 'success';
            $response['message'] = 'The new user record has been saved.';
            insertLog($db_connection, "Added a new \"$userRole\" / User ID: $insert_id", "User");
        } else {
            $response['status'] = 'error';
            $response['message'] = 'User record not saved.';
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


if (isset($_POST['action']) && $_POST['action'] === 'addAccount') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user_id = intval($_POST['userId'] ?? 0);

    $response = [];

    // Basic validation
    if (empty($username) || empty($password) || $user_id === 0) {
        $response['message'] = 'Username, password, and user ID are required.';
        echo json_encode($response);
        exit;
    }

    // Check if username already exists
    $check = $db_connection->prepare("SELECT account_id FROM accountstbl WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $response['message'] = 'Username already exists. Please choose another.';
        echo json_encode($response);
        exit;
    }
    $check->close();

    // Check if user already has a linked account
    $checkLink = $db_connection->prepare("SELECT account_id FROM userstbl WHERE user_id = ? AND account_id IS NOT NULL");
    $checkLink->bind_param("i", $user_id);
    $checkLink->execute();
    $checkLink->store_result();

    if ($checkLink->num_rows > 0) {
        $response['message'] = 'This user already has an account linked.';
        echo json_encode($response);
        exit;
    }

    $checkLink->close();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into accountstbl
    date_default_timezone_set('Asia/Manila');
    $dateNow = date('Y-m-d');
    $stmt = $db_connection->prepare("
        INSERT INTO accountstbl (username, password, date_created)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("sss", $username, $hashedPassword, $dateNow);

    if ($stmt->execute()) {
        $account_id = $stmt->insert_id;

        // Now link this account to the user
        $link = $db_connection->prepare("UPDATE userstbl SET account_id = ? WHERE user_id = ?");
        $link->bind_param("ii", $account_id, $user_id);

        if ($link->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Account successfully linked to user.';
            insertLog($db_connection, "Created an account for User ID: $user_id ", "User");
        } else {
            $response['message'] = 'Account created but failed to link to user.';
        }

        $link->close();
    } else {
        $response['message'] = 'Failed to create account. Please try again.';
    }

    $stmt->close();
    echo json_encode($response);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'updateAccount') {
    $accountId = $_POST['account_id'] ?? '';
    $userId = $_POST['userId'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $response = [];

    if (empty($username)) {
        $response = ['status' => 'error', 'message' => 'Username cannot be empty.'];
    } elseif (!empty($password) && strlen($password) < 6) {
        $response = ['status' => 'error', 'message' => 'Password must be at least 6 characters.'];
    } else {
        // Prepare query based on whether password is updated
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE accountstbl SET username = ?, password = ? WHERE account_id = ?";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param("ssi", $username, $hashedPassword, $accountId);
        } else {
            $query = "UPDATE accountstbl SET username = ? WHERE account_id = ?";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param("si", $username, $accountId);
        }

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Account updated successfully.'];
            insertLog($db_connection, "Updated account details of User ID: $userId ", "User");
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to update account.'];
        }
    }


    echo json_encode($response);
    exit;
}


if (isset($_POST['row_id']) && isset($_POST['action']) && $_POST['action'] == 'getRowData') {

    $rowId = $_POST['row_id'];
    $response = [];

    // Fetch Account
    $getAccountQuery = "
        SELECT a.* 
        FROM accountstbl a
        INNER JOIN userstbl u ON a.account_id = u.account_id
        WHERE u.user_id = ?
    ";
    $stmt = $db_connection->prepare($getAccountQuery);
    $stmt->bind_param("i", $rowId);
    $stmt->execute();
    $accountResult = $stmt->get_result();

    if ($accountResult && $accountResult->num_rows > 0) {
        $accountData = $accountResult->fetch_assoc();
        unset($accountData['password']);
        $response['account'] = $accountData;



        // ---------------- Fetch Service History ----------------
        $serviceHistoryQuery = "
        SELECT 
            sr.record_id,
            sr.request_id,
            sr.record_status,
            sr.completion_dt,
            req.vehicle_id,
            COALESCE(req.sched_dt, req.request_dt) AS scheduled_or_request_dt,
            inv.grand_total AS total_billing
        FROM recordstbl sr
        INNER JOIN requeststbl req ON sr.request_id = req.request_id
        LEFT JOIN invoicetbl inv ON sr.record_id = inv.record_id
        INNER JOIN assigned_mechanicstbl am ON sr.record_id = am.record_id
        WHERE am.user_id = ?
        ORDER BY sr.completion_dt DESC
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

        $response['service_history'] = $serviceHistory;


                // ---------------- Fetch Mechanic Specialties ----------------
                $specialtiesQuery = "
                SELECT ms.specialty_id, s.name AS specialty_name
                FROM mechanic_specialtiestbl ms
                INNER JOIN specialtiestbl s ON ms.specialty_id = s.specialty_id
                WHERE ms.user_id = ?
            ";
            $stmt3 = $db_connection->prepare($specialtiesQuery);
            $stmt3->bind_param("i", $rowId);
            $stmt3->execute();
            $specialtiesResult = $stmt3->get_result();
    
            $specialties = [];
            while ($specRow = $specialtiesResult->fetch_assoc()) {
                $specialties[] = [
                    'specialty_id' => $specRow['specialty_id'],
                    'specialty_name' => $specRow['specialty_name']
                ];
            }
            $response['specialties'] = $specialties;
    } else {
        $response['account'] = null;
        $response['account_message'] = 'Account does not exist.';
        $response['service_history'] = [];
        $response['specialties'] = [];
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
                    UPDATE userstbl
                    SET status = 'Deactivated'
                    WHERE user_id = ?;
                ";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $rowId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['statusUpdate'] = true;
            $response['message'] = 'User profile successfully deactivated.';
            insertLog($db_connection, "User profile deactivated / User ID: $rowId", "User");
        } else {
            $response['statusUpdate'] = null;
            $response['message'] = 'User profile does not exist or is already deactivated.';
        }


        echo json_encode($response);
        exit;
    } else if ($action == 'activate') {
        $query = "
                    UPDATE userstbl
                    SET status = 'Active'
                    WHERE user_id = ?;
                ";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $rowId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['statusUpdate'] = true;
            $response['message'] = 'User profile successfully activated.';
            insertLog($db_connection, "User profile activated / User ID: $rowId", "User");
        } else {
            $response['statusUpdate'] = null;
            $response['message'] = 'User profile does not exist or is already active.';
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



if (isset($_POST['action']) && $_POST['action'] === 'getUserRecord' && isset($_POST['userId'])) {
    $response = [];
    $userId = $_POST['userId'];

    $query = "
        SELECT *
        FROM userstbl
        WHERE user_id = ?;
    ";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $response['status'] = true;
        $response['user'] = $user;
    } else {
        $response['status'] = false;
        $response['message'] = 'User not found.';
    }


    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['user_data'])) {
    $response = [];
    $userData = $_POST['user_data'];

    // Make sure user_id exists
    if (!isset($userData['user_id'])) {
        $response['status'] = 'error';
        $response['message'] = 'User ID is required.';

        echo json_encode($response);
        exit;
    }

    $userId = intval($userData['user_id']);
    unset($userData['user_id']); // Remove it from fields to be updated

    if (empty($userData)) {
        $response['status'] = 'error';
        $response['message'] = 'No changes were submitted.';

        echo json_encode($response);
        exit;
    }

    // DUPLICATE CHECK SECTION (IMPORTANT!)
    $mobile = $userData['contact_number'] ?? null;
    $email = $userData['email'] ?? null;

    if ($mobile || $email) {
        $checkQuery = "SELECT * FROM userstbl 
                        WHERE (contact_number = ? OR email = ?) 
                        AND user_id != ?";
        $checkStmt = $db_connection->prepare($checkQuery);
        $checkStmt->bind_param("ssi", $mobile, $email, $userId);
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

    // Proceed with update
    $setClauses = [];
    $params = [];
    $types = '';

    foreach ($userData as $column => $value) {
        $setClauses[] = "$column = ?";
        $params[] = $value;
        $types .= 's'; // assuming all values are strings
    }

    $query = "UPDATE userstbl SET " . implode(", ", $setClauses) . " WHERE user_id = ?";
    $params[] = $userId;
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
        $response['message'] = 'User information successfully updated.';
        insertLog($db_connection, "Updated user profile / User ID: $userId", "User");
    } else {
        $response['status'] = 'error';
        $response['message'] = 'No changes made or user does not exist.';
    }


    echo json_encode($response);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'fetchSpecialties') {
    $result = mysqli_query($db_connection, "SELECT specialty_id, name, description FROM specialtiestbl");

    if ($result && mysqli_num_rows($result) > 0) {
        $specialties = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $specialties[] = $row;
        }
        echo json_encode([
            'status' => 'success',
            'data' => $specialties
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No specialties found.'
        ]);
    }
}



if (isset($_POST['action']) && $_POST['action'] === 'updateSpecialty') {
    $specialty_id = intval($_POST['specialty_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($specialty_id > 0 && $name !== '') {
        $stmt = $db_connection->prepare("UPDATE specialtiestbl SET name = ?, description = ? WHERE specialty_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $description, $specialty_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Specialty updated successfully!";
                insertLog($db_connection, "Updated specialty details of \"$name\"", "Specialty");
            } else {
                $response['message'] = "Failed to update specialty. DB error: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $response['message'] = "Failed to prepare statement.";
        }
    } else {
        $response['message'] = "Missing or invalid fields.";
    }
    // return clean JSON always
    echo json_encode($response);
}

if (isset($_POST['action']) && $_POST['action'] === 'deleteSpecialty') {
    $id = intval($_POST['specialty_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid specialty ID.'
        ]);
        exit;
    }

    // ✅ Restrict who can delete
    if ($_SESSION['User_role'] !== 'Super Admin' && $_SESSION['User_role'] !== 'Admin') {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized action.'
        ]);
        exit;
    }

    // 🔹 Start transaction (to ensure both deletes succeed)
    mysqli_begin_transaction($db_connection);

    try {
        // 1. Delete all mechanic-specialty relations first
        $stmt1 = mysqli_prepare($db_connection, "DELETE FROM mechanic_specialtiestbl WHERE specialty_id = ?");
        mysqli_stmt_bind_param($stmt1, "i", $id);
        mysqli_stmt_execute($stmt1);

        // 2. Delete specialty itself
        $stmt2 = mysqli_prepare($db_connection, "DELETE FROM specialtiestbl WHERE specialty_id = ?");
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);

        if (mysqli_stmt_affected_rows($stmt2) > 0) {
            mysqli_commit($db_connection);
            echo json_encode([
                'status' => 'success',
                'message' => 'Specialty deleted successfully.'
            ]);
            insertLog($db_connection, "Deleted specialty \"$name\"", "Specialty");
        } else {
            mysqli_rollback($db_connection);
            echo json_encode([
                'status' => 'error',
                'message' => 'Specialty not found or already deleted.'
            ]);
        }

        if ($stmt1) mysqli_stmt_close($stmt1);
        if ($stmt2) mysqli_stmt_close($stmt2);
    } catch (Exception $e) {
        mysqli_rollback($db_connection);
        echo json_encode([
            'status' => 'error',
            'message' => 'Error during delete: ' . $e->getMessage()
        ]);
    }

    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'addSpecialty') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (strlen($name) < 3 || strlen($name) > 50) {
        $response = ['status' => 'error', 'message' => 'Specialty name must be between 3 and 50 characters'];
    } elseif (strlen($description) < 5) {
        $response = ['status' => 'error', 'message' => 'Description must be at least 5 characters long'];
    } else {
        // ✅ check for duplicate
        $stmt = $db_connection->prepare("SELECT specialty_id FROM specialtiestbl WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $response = ['status' => 'error', 'message' => 'Specialty name already exists'];
        } else {
            $stmt->close();

            $stmt = $db_connection->prepare("INSERT INTO specialtiestbl (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);

            if ($stmt->execute()) {
                $response = [
                    'status' => 'success',
                    'message' => 'Specialty added successfully',
                    'id' => $stmt->insert_id
                ];

                insertLog($db_connection, "Added specialty \"$name\"", "Specialty");
            } else {
                $response = ['status' => 'error', 'message' => 'Database insert failed'];
            }
            $stmt->close();
        }
    }

    echo json_encode($response);
}



if (isset($_POST['action']) && $_POST['action'] === 'getMechanicsSpecialty' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // ✅ 1. Get specialties assigned to this mechanic
    $query = "
        SELECT ms.specialty_id, s.name
        FROM mechanic_specialtiestbl ms
        INNER JOIN specialtiestbl s ON ms.specialty_id = s.specialty_id
        WHERE ms.user_id = $user_id
    ";
    $result = mysqli_query($db_connection, $query);

    $mechanicSpecialties = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $mechanicSpecialties[] = $row;
        }
    }

    // ✅ 2. Get ALL specialties
    $allResult = mysqli_query($db_connection, "SELECT specialty_id, name, description FROM specialtiestbl");
    $allSpecialties = [];
    if ($allResult && mysqli_num_rows($allResult) > 0) {
        while ($row = mysqli_fetch_assoc($allResult)) {
            $allSpecialties[] = $row;
        }
    }

    // ✅ 3. Return both in one response
    echo json_encode([
        'status' => 'success',
        'allSpecialties' => $allSpecialties,         // all specialties (for full list)
        'assignedSpecialties' => $mechanicSpecialties // only mechanic's specialties
    ]);
}


// Save mechanic specialties (replace or add to your existing handler file)
if (isset($_POST['action']) && $_POST['action'] === 'saveMechanicSpecialties') {
    header('Content-Type: application/json');

    // validate user_id
    if (!isset($_POST['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'User ID required.']);
        exit;
    }
    $user_id = intval($_POST['user_id']);

    // specialties may come as array or single value, cast safely
    $rawSpecs = $_POST['specialties'] ?? [];
    if (!is_array($rawSpecs)) {
        // if jQuery sent as specialties=1 or specialties[]=1, handle both
        $rawSpecs = [$rawSpecs];
    }

    // sanitize & dedupe ints
    $specIds = array_values(array_unique(array_map('intval', $rawSpecs)));

    // start transaction
    $db_connection->begin_transaction();

    try {
        // 1) delete existing specialties for this user
        $delStmt = $db_connection->prepare("DELETE FROM mechanic_specialtiestbl WHERE user_id = ?");
        if ($delStmt === false) {
            throw new Exception('Prepare failed: ' . $db_connection->error);
        }
        $delStmt->bind_param("i", $user_id);
        $delStmt->execute();
        $delStmt->close();

        // 2) insert new ones (if any)
        if (!empty($specIds)) {
            $insStmt = $db_connection->prepare("INSERT INTO mechanic_specialtiestbl (user_id, specialty_id) VALUES (?, ?)");
            if ($insStmt === false) {
                throw new Exception('Prepare failed: ' . $db_connection->error);
            }
            foreach ($specIds as $sid) {
                $insStmt->bind_param("ii", $user_id, $sid);
                $insStmt->execute();
                // optionally check $insStmt->affected_rows
            }
            $insStmt->close();
        }

        $db_connection->commit();

        echo json_encode(['status' => 'success', 'message' => 'Mechanic specialties updated successfully.']);
        exit;

    } catch (Exception $ex) {
        $db_connection->rollback();
        error_log('Error saving specialties: ' . $ex->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while saving specialties.']);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'updateWorkStatus') {

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$work_status = isset($_POST['work_status']) && $_POST['work_status'] === 'In' ? 'In' : 'Out';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user id']);
    exit;
}

// using mysqli prepared statement (adjust variable $db_connection to your DB connection)
if ($stmt = $db_connection->prepare("UPDATE userstbl SET work_status = ? WHERE user_id = ?")) {
    $stmt->bind_param('si', $work_status, $user_id);
    if ($stmt->execute()) {
        // choose icon html (use your icon library classes)
        $icon = ($work_status === 'In') 
        ? '<i class="bx bxs-log-in text-success" title="In" style="font-size: 1.2rem;"></i>' 
        : '<i class="bx bxs-log-out text-danger" title="In" style="font-size: 1.2rem;"></i>';
        echo json_encode(['success' => true, 'work_status' => $work_status, 'iconHtml' => $icon]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => $db_connection->error]);
}

}