<?php
// handlers/manageAccount.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// include DB (adjust path if your handlers folder differs)
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);



$account_id = $_SESSION['account_id'] ?? 0;

if ($account_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

// Make sure 'action' exists
$action = $_POST['action'] ?? '';

if ($action === 'updateProfile') {
    // Sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validate required fields
    if ($first_name === '' || $last_name === '' || $email === '') {
        echo json_encode(['status' => 'error', 'message' => 'First name, Last name and Email are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    $stmt = $db_connection->prepare("UPDATE clientstbl SET first_name=?, last_name=?, middle_name=?, contact_number=?, email=?, address=? WHERE account_id=?");
    $stmt->bind_param("ssssssi", $first_name, $last_name, $middle_name, $contact_number, $email, $address, $account_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $db_connection->error]);
    }

    $stmt->close();
    $db_connection->close();
    exit; // end this action
}

// Example of another future action
if ($action === 'changePassword') {
    $password = $_POST['password'] ?? '';
    if ($password === '') {
        echo json_encode(['status' => 'error', 'message' => 'Password cannot be empty.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db_connection->prepare("UPDATE accountstbl SET password=? WHERE account_id=?");
    $stmt->bind_param("si", $hashedPassword, $account_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $db_connection->error]);
    }

    $stmt->close();
    $db_connection->close();
    exit;
}

if ($action === 'updateAccount') {
    $accountId = $_SESSION['account_id'] ?? 0;
    if ($accountId == 0) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';

    if ($username === '') {
        echo json_encode(['success' => false, 'message' => 'Username cannot be empty.']);
        exit;
    }

    // Fetch current account info
    $stmt = $db_connection->prepare("SELECT username, password FROM accountstbl WHERE account_id=? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $db_connection->error]);
        exit;
    }
    $stmt->bind_param("i", $accountId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    $stmt->bind_result($currentUsername, $hash);
    $stmt->fetch();
    $stmt->close();

    // Check if username is already taken by another account
    $stmt = $db_connection->prepare("SELECT account_id FROM accountstbl WHERE username=? AND account_id<>? LIMIT 1");
    $stmt->bind_param("si", $username, $accountId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken by another account.']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Determine password to store
    if ($currentPw || $newPw) {
        if (!password_verify($currentPw, $hash)) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }
        if ($newPw === '') {
            echo json_encode(['success' => false, 'message' => 'New password cannot be empty.']);
            exit;
        }
        $newHash = password_hash($newPw, PASSWORD_DEFAULT);
    } else {
        $newHash = $hash; // keep current password
    }

    // Update account
    $stmt = $db_connection->prepare("UPDATE accountstbl SET username=?, password=? WHERE account_id=?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $db_connection->error]);
        exit;
    }
    $stmt->bind_param("ssi", $username, $newHash, $accountId);
    $exec = $stmt->execute();
    if (!$exec) {
        echo json_encode(['success' => false, 'message' => 'Failed to update account: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Update session username
    $_SESSION['username'] = $username;
    echo json_encode([
        'success' => true,
        'message' => 'Account updated successfully.',
        'username' => $username
    ]);
    exit;
}


if ($action === 'getVehicle') {
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $client_id = $_SESSION['client_id'] ?? 0;

    if (!$vehicle_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID.']);
        exit;
    }

    $stmt = $db_connection->prepare("SELECT * FROM vehiclestbl WHERE vehicle_id = ? AND client_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $client_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();

    if ($vehicle) {
        echo json_encode(['success' => true, 'data' => $vehicle]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
    }
    exit;
}

if ($action === 'updateVehicle') {
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $client_id = $_SESSION['client_id'] ?? 0;
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $plate = trim($_POST['plate_number'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $transmission = trim($_POST['transmission_type'] ?? '');
    $fuel = trim($_POST['fuel_type'] ?? '');

    // Validate inputs
    if (!$vehicle_id || !$make || !$model || !$transmission || !$fuel) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    // Check if vehicle is in an active request (In Progress)
    $check = $db_connection->prepare("
        SELECT COUNT(*) FROM requeststbl 
        WHERE vehicle_id = ? AND status = 'In Progress'
    ");
    $check->bind_param("i", $vehicle_id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'You cannot edit this vehicle while it has an active service request.']);
        exit;
    }

    // Proceed with update
    $stmt = $db_connection->prepare("
        UPDATE vehiclestbl
        SET make = ?, model = ?, plate_number = ?, color = ?, transmission_type = ?, fuel_type = ?
        WHERE vehicle_id = ? AND client_id = ?
    ");
    $stmt->bind_param("ssssssii", $make, $model, $plate, $color, $transmission, $fuel, $vehicle_id, $client_id);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update vehicle.']);
    }
    exit;
}

if ($action === 'removeVehicle') {
    $vehicle_id = (int)$_POST['vehicle_id'];
    $client_id = $_SESSION['client_id'] ?? 0;

    // Prevent deactivation if vehicle has an ongoing request
    $check = $db_connection->prepare("
        SELECT COUNT(*) FROM requeststbl 
        WHERE vehicle_id = ? AND status = 'In Progress'
    ");
    $check->bind_param("i", $vehicle_id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'This vehicle cannot be deactivated while it has an active service request.']);
        exit;
    }

    // Proceed with deactivation
    $stmt = $db_connection->prepare("UPDATE vehiclestbl SET status = 'removed' WHERE vehicle_id = ? AND client_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $client_id);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Vehicle deactivated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate vehicle.']);
    }
    exit;
}


if ($action === 'addVehicle') {
    $client_id = $_SESSION['client_id'] ?? 0;
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $plate = trim($_POST['plate_number'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $transmission = trim($_POST['transmission_type'] ?? '');
    $fuel = trim($_POST['fuel_type'] ?? '');

    // Validate inputs
    if (!$client_id || !$make || !$model || !$transmission || !$fuel) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    // Optional: check if plate number already exists for this client
    $check = $db_connection->prepare("
        SELECT COUNT(*) FROM vehiclestbl
        WHERE plate_number = ? AND client_id = ? AND status != 'removed'
    ");
    $check->bind_param("si", $plate, $client_id);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'This plate number is already registered under your account.']);
        exit;
    }

    // Proceed with insertion
    $stmt = $db_connection->prepare("
        INSERT INTO vehiclestbl (client_id, make, model, plate_number, color, transmission_type, fuel_type, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param("issssss", $client_id, $make, $model, $plate, $color, $transmission, $fuel);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Vehicle added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add vehicle.']);
    }
    exit;
}


// If action not recognized
echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
