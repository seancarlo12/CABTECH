<?php

// --- Database connection credentials ---
$host = 'localhost';
$user = 'root';
$password = '';
// $dbname = 'cabtech_db'; FOR TESTINGS
$dbname = 'cabtech_db_final';
// $dbname = 'cabtechsystem_db'; //TESTS

global $db_connection;

// 1. Connect to MySQL server WITHOUT specifying the DB yet
$db_connection = new mysqli($host, $user, $password);

if ($db_connection->connect_error) {
    die("Failed to connect to the MySQL server: " . $db_connection->connect_error);
}

// 2. Utility to fetch single value from SQL
function GetValue($sql_query)
{
    global $db_connection;
    $result = mysqli_query($db_connection, $sql_query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);
        return $row[0];
    }
    return null;
}

// 3. Check if the database exists
function isDBExisting($dbname)
{
    return GetValue("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$dbname'") + 0;
}

function insertLog($db_connection, $activity, $activity_type = 'Other')
{
    // Ensure session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if account_id and username exist in session
    if (!isset($_SESSION['Account_id']) || !isset($_SESSION['Username'])) {
        error_log("Insert Log Error: account_id/username not found in session");
        return false;
    }

    $account_id = $_SESSION['Account_id'];
    $username   = $_SESSION['Username']; // useful for debugging/logging locally

    // SQL insert (only account_id goes to DB)
    $sql = "INSERT INTO logstbl (account_id, activity, activity_type) 
            VALUES (?, ?, ?)";

    if ($stmt = mysqli_prepare($db_connection, $sql)) {
        mysqli_stmt_bind_param($stmt, "iss", $account_id, $activity, $activity_type);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        } else {
            error_log("Insert Log Error: " . mysqli_stmt_error($stmt) . " | User: " . $username);
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        error_log("Prepare Failed: " . mysqli_error($db_connection));
        return false;
    }
}

// 4. Send notification to users
function sendNotification($db_connection, $type, $message, $recipients, $goTo = null)
{
    $account_ids = [];

    // --- Normalize recipients ---
    if ($recipients === 'all') {
        $result = mysqli_query($db_connection, "SELECT account_id FROM accountstbl");
        while ($row = mysqli_fetch_assoc($result)) $account_ids[] = $row['account_id'];
    } elseif (is_array($recipients)) {
        $role_list = [];
        $ids_list = [];

        foreach ($recipients as $r) {
            if (is_numeric($r)) {
                $ids_list[] = intval($r); // account_id
            } elseif (is_string($r)) {
                $role_list[] = "'" . mysqli_real_escape_string($db_connection, $r) . "'"; // role
            }
        }

        if (!empty($role_list)) {
            $roles_csv = implode(',', $role_list);
            $result = mysqli_query($db_connection, "
                SELECT u.account_id
                FROM userstbl u
                JOIN accountstbl a ON u.account_id = a.account_id
                WHERE u.role IN ($roles_csv)
            ");
            while ($row = mysqli_fetch_assoc($result)) $account_ids[] = $row['account_id'];
        }

        $account_ids = array_merge($account_ids, $ids_list);
    } elseif (is_string($recipients)) {
        $role = mysqli_real_escape_string($db_connection, $recipients);
        $result = mysqli_query($db_connection, "
            SELECT u.account_id
            FROM userstbl u
            JOIN accountstbl a ON u.account_id = a.account_id
            WHERE u.role = '$role'
        ");
        while ($row = mysqli_fetch_assoc($result)) $account_ids[] = $row['account_id'];
    }

    $account_ids = array_unique(array_map('intval', $account_ids));

    if (empty($account_ids)) {
        error_log("sendNotification: No recipients found.");
        return false;
    }

    // --- Insert notification (with goTo) ---
    $stmt = mysqli_prepare($db_connection, "INSERT INTO notificationstbl (type, message, goTo) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sss", $type, $message, $goTo);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("sendNotification: Failed to insert notification: " . mysqli_stmt_error($stmt));
        return false;
    }
    $notif_id = mysqli_insert_id($db_connection);

    // --- Insert recipients into user_notifreadtbl ---
    $values = [];
    $types = str_repeat('ii', count($account_ids));
    $params = [];
    foreach ($account_ids as $account_id) {
        $values[] = '(?, ?, 0)';
        $params[] = $notif_id;
        $params[] = $account_id;
    }
    $insertUsersSql = "INSERT INTO user_notifreadtbl (notification_id, account_id, isRead) VALUES " . implode(',', $values);
    $stmt = mysqli_prepare($db_connection, $insertUsersSql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    return mysqli_stmt_execute($stmt);
}








// 5. Create DB, Tables, and Default Super Admin Account
function createDatabaseAndTables($dbname)
{
    global $db_connection;

    // Create the database
    if (!mysqli_query($db_connection, "CREATE DATABASE `$dbname`")) {
        die("Failed to create database '$dbname': " . mysqli_error($db_connection));
    }

    // Select the new DB
    mysqli_select_db($db_connection, $dbname);

    // Full schema (multiple tables)
    $schema = "
    CREATE TABLE `accountstbl` (
        `account_id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` text NOT NULL,
        `date_created` date NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`account_id`)
    );

    CREATE TABLE `assigned_mechanicstbl` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `record_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    );

    CREATE TABLE `clientstbl` (
        `client_id` int(11) NOT NULL AUTO_INCREMENT,
        `account_id` int(11) DEFAULT NULL,
        `first_name` varchar(50) NOT NULL,
        `last_name` varchar(50) NOT NULL,
        `middle_name` varchar(50) DEFAULT NULL,
        `contact_number` varchar(15) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `address` varchar(255) DEFAULT NULL,
        `status` enum('Active','Deactivated','Restricted','') NOT NULL,
        PRIMARY KEY (`client_id`)
    );

    CREATE TABLE `invoicetbl` (
        `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
        `record_id` int(11) NOT NULL,
        `items_total` double(10,2) NOT NULL,
        `services_total` double(10,2) NOT NULL,
        `grand_total` double(10,2) NOT NULL,
        `issued_dt` datetime DEFAULT NULL,
        `status` varchar(255) NOT NULL,
        PRIMARY KEY (`invoice_id`)
    );

    CREATE TABLE `itemstbl` (
        `item_id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `type` varchar(255) NOT NULL,
        `status` varchar(255) NOT NULL,
        `price` double(10,2) NOT NULL,
        `stock` int(11) NOT NULL,
        PRIMARY KEY (`item_id`)
    );

    CREATE TABLE `items_neededtbl` (
        `itmn_id` int(11) NOT NULL AUTO_INCREMENT,
        `record_id` int(11) NOT NULL,
        `item_id` int(11) NOT NULL,
        `rst_id` int(11) NOT NULL,
        `record_price` double(10,2) DEFAULT NULL,
        `quantity` int(11) NOT NULL,
        PRIMARY KEY (`itmn_id`)
    );

    CREATE TABLE `logstbl` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `account_id` int(11) NOT NULL,
        `activity` varchar(255) NOT NULL,
        `activity_type` enum('LogInOut','Request','Service','Item','Record','User','Client','Reports','Specialty','Other') DEFAULT 'Other',
        `timestamp` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`log_id`)
    );

    CREATE TABLE `mechanic_specialtiestbl` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `specialty_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    );

    CREATE TABLE `notificationstbl` (
        `notification_id` int(11) NOT NULL AUTO_INCREMENT,
        `goTo` enum('users','clients','requests','records','inventory','servicesOffered') DEFAULT NULL,
        `type` enum('service','system') NOT NULL,
        `message` text NOT NULL,
        `timestamp` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`notification_id`)
    );

    CREATE TABLE `recordstbl` (
        `record_id` int(11) NOT NULL AUTO_INCREMENT,
        `request_id` int(11) NOT NULL,
        `completion_dt` datetime DEFAULT NULL,
        `started_dt` datetime NOT NULL,
        `record_status` varchar(255) NOT NULL,
        PRIMARY KEY (`record_id`)
    );

    CREATE TABLE `requested_servicestbl` (
        `rst_id` int(11) NOT NULL AUTO_INCREMENT,
        `service_id` int(11) DEFAULT NULL,
        `request_id` int(11) NOT NULL,
        `custom_service` varchar(255) DEFAULT NULL,
        `custom_est_duration` varchar(10) DEFAULT NULL,
        `custom_labor_cost` double(10,2) DEFAULT NULL,
        `clients_comment` text DEFAULT NULL,
        PRIMARY KEY (`rst_id`)
    );

    CREATE TABLE `requeststbl` (
        `request_id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `vehicle_id` int(11) NOT NULL,
        `request_type` enum('Appointment','Walk-In') NOT NULL,
        `sched_dt` datetime DEFAULT NULL,
        `request_dt` datetime NOT NULL,
        `status` enum('Pending','Approved','In Progress','Completed','Cancelled') NOT NULL,
        `resched_count` int(11) NOT NULL,
        PRIMARY KEY (`request_id`)
    );

    CREATE TABLE `servicestbl` (
        `service_id` int(11) NOT NULL AUTO_INCREMENT,
        `service_name` varchar(255) NOT NULL,
        `description` varchar(255) NOT NULL,
        `status` varchar(100) NOT NULL,
        `estimated_duration` time NOT NULL,
        `labor_cost` double(10,2) NOT NULL,
        PRIMARY KEY (`service_id`)
    );

    CREATE TABLE `specialtiestbl` (
        `specialty_id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `description` varchar(255) NOT NULL,
        PRIMARY KEY (`specialty_id`)
    );

    CREATE TABLE `userstbl` (
        `user_id` int(11) NOT NULL AUTO_INCREMENT,
        `account_id` int(11) DEFAULT NULL,
        `role` enum('Super Admin','Admin','Mechanic','test') NOT NULL,
        `first_name` varchar(100) NOT NULL,
        `last_name` varchar(100) NOT NULL,
        `middle_name` varchar(100) DEFAULT NULL,
        `contact_number` varchar(15) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `address` varchar(255) DEFAULT NULL,
        `status` enum('Active','Deactivated','','') NOT NULL,
        `work_status` enum('In','Out') DEFAULT NULL,
        PRIMARY KEY (`user_id`)
    );

    CREATE TABLE `user_notifreadtbl` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `notification_id` int(11) NOT NULL,
        `account_id` int(11) NOT NULL,
        `isRead` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`)
    );

    CREATE TABLE `vehiclestbl` (
        `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `make` varchar(100) NOT NULL,
        `model` varchar(255) NOT NULL,
        `plate_number` varchar(20) DEFAULT NULL,
        `color` varchar(50) NOT NULL,
        `transmission_type` varchar(100) NOT NULL,
        `fuel_type` varchar(100) NOT NULL,
        `status` enum('active','removed') NOT NULL DEFAULT 'active',
        PRIMARY KEY (`vehicle_id`)
    );
";


    // Run schema
    if (!mysqli_multi_query($db_connection, $schema)) {
        die("Failed to create tables: " . mysqli_error($db_connection));
    }

    // Clean up remaining results
    while (mysqli_more_results($db_connection) && mysqli_next_result($db_connection)) {
    }

    // Insert default Super Admin account
    $defaultUsername = 'superadmin';
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);

    $insertAccount = "INSERT INTO accountstbl (username, password) VALUES ('$defaultUsername', '$defaultPassword')";
    if (!mysqli_query($db_connection, $insertAccount)) {
        die("Failed to insert default account: " . mysqli_error($db_connection));
    }

    $accountId = mysqli_insert_id($db_connection);

    $insertUser = "
        INSERT INTO userstbl (account_id, role, first_name, last_name, status)
        VALUES ($accountId, 'Super Admin', 'System', 'Administrator', 'Active')
    ";

    if (!mysqli_query($db_connection, $insertUser)) {
        die("Failed to insert default user: " . mysqli_error($db_connection));
    }

    // Insert 10 default items
    $defaultItems = [
        ['name' => 'Oil Filter', 'type' => 'Part', 'status' => 'Available', 'price' => 150.00, 'stock' => 20],
        ['name' => 'Air Filter', 'type' => 'Part', 'status' => 'Available', 'price' => 200.00, 'stock' => 15],
        ['name' => 'Spark Plug', 'type' => 'Part', 'status' => 'Available', 'price' => 75.00, 'stock' => 50],
        ['name' => 'Engine Oil', 'type' => 'Consumable', 'status' => 'Available', 'price' => 350.00, 'stock' => 30],
        ['name' => 'Brake Fluid', 'type' => 'Consumable', 'status' => 'Available', 'price' => 120.00, 'stock' => 25],
        ['name' => 'Coolant', 'type' => 'Consumable', 'status' => 'Available', 'price' => 180.00, 'stock' => 20],
        ['name' => 'Car Shampoo', 'type' => 'Product', 'status' => 'Available', 'price' => 250.00, 'stock' => 40],
        ['name' => 'Microfiber Towel', 'type' => 'Product', 'status' => 'Available', 'price' => 100.00, 'stock' => 60],
        ['name' => 'Battery', 'type' => 'Part', 'status' => 'Available', 'price' => 2500.00, 'stock' => 10],
        ['name' => 'Transmission Fluid', 'type' => 'Consumable', 'status' => 'Available', 'price' => 400.00, 'stock' => 14],
    ];

    foreach ($defaultItems as $item) {
        $name = $db_connection->real_escape_string($item['name']);
        $type = $db_connection->real_escape_string($item['type']);
        $status = $db_connection->real_escape_string($item['status']);
        $price = $item['price'];
        $stock = $item['stock'];

        $insertItem = "
        INSERT INTO itemstbl (name, type, status, price, stock)
        VALUES ('$name', '$type', '$status', $price, $stock)
    ";

        if (!mysqli_query($db_connection, $insertItem)) {
            die("Failed to insert item '$name': " . mysqli_error($db_connection));
        }
    }

    $mechanics = [
        ['username' => 'mechanic1', 'password' => password_hash('mech123', PASSWORD_DEFAULT), 'first_name' => 'Juan', 'last_name' => 'Dela Cruz'],
        // ['username' => 'mechanic2', 'password' => password_hash('mech123', PASSWORD_DEFAULT), 'first_name' => 'Pedro', 'last_name' => 'Santos']
    ];

    foreach ($mechanics as $mech) {
        $insertMechAccount = "INSERT INTO accountstbl (username, password) VALUES ('{$mech['username']}', '{$mech['password']}')";
        if (!mysqli_query($db_connection, $insertMechAccount)) {
            die("Failed to insert mechanic account: " . mysqli_error($db_connection));
        }

        $mechAccountId = mysqli_insert_id($db_connection);

        $insertMechUser = "
            INSERT INTO userstbl (account_id, role, first_name, last_name, status, work_status)
            VALUES ($mechAccountId, 'Mechanic', '{$mech['first_name']}', '{$mech['last_name']}', 'Active', 'In')
        ";
        if (!mysqli_query($db_connection, $insertMechUser)) {
            die("Failed to insert mechanic user: " . mysqli_error($db_connection));
        }
    }

    $defaultServices = [
        ['service_name' => 'Oil Change', 'description' => 'Replace engine oil and filter', 'status' => 'Active', 'duration' => '00:30:00', 'cost' => 500.00],
        ['service_name' => 'Brake Inspection', 'description' => 'Check and adjust brake system', 'status' => 'Active', 'duration' => '00:45:00', 'cost' => 350.00],
        ['service_name' => 'Battery Check', 'description' => 'Test battery performance', 'status' => 'Active', 'duration' => '00:20:00', 'cost' => 200.00],
        ['service_name' => 'Tire Rotation', 'description' => 'Rotate tires for even wear', 'status' => 'Active', 'duration' => '00:30:00', 'cost' => 300.00],
        ['service_name' => 'Aircon Cleaning', 'description' => 'Clean and sanitize aircon system', 'status' => 'Active', 'duration' => '01:00:00', 'cost' => 700.00],
        ['service_name' => 'Engine Tune-Up', 'description' => 'Optimize engine performance', 'status' => 'Active', 'duration' => '01:30:00', 'cost' => 1200.00],
        ['service_name' => 'Transmission Service', 'description' => 'Check and refill transmission fluid', 'status' => 'Active', 'duration' => '01:15:00', 'cost' => 850.00],
        ['service_name' => 'Wheel Alignment', 'description' => 'Align wheels for proper tracking', 'status' => 'Active', 'duration' => '00:40:00', 'cost' => 600.00],
        ['service_name' => 'Suspension Check', 'description' => 'Inspect shocks, struts, and suspension system', 'status' => 'Active', 'duration' => '00:50:00', 'cost' => 550.00],
        ['service_name' => 'Spark Plug Replacement', 'description' => 'Replace spark plugs for smoother engine performance', 'status' => 'Active', 'duration' => '00:40:00', 'cost' => 450.00]
    ];

    foreach ($defaultServices as $srv) {
        $insertService = "
            INSERT INTO servicestbl (service_name, description, status, estimated_duration, labor_cost)
            VALUES ('{$srv['service_name']}', '{$srv['description']}', '{$srv['status']}', '{$srv['duration']}', {$srv['cost']})
        ";
        if (!mysqli_query($db_connection, $insertService)) {
            die("Failed to insert service: " . mysqli_error($db_connection));
        }
    }


    $defaultSpecialties = [
        ['name' => 'Engine Specialist', 'description' => 'Focuses on diagnosing and repairing engine-related issues'],
        ['name' => 'Brake Specialist', 'description' => 'Expert in brake system inspection, repair, and replacement'],
        ['name' => 'Transmission Specialist', 'description' => 'Handles transmission system maintenance and repairs'],
        ['name' => 'Electrical Specialist', 'description' => 'Specializes in diagnosing and fixing electrical and wiring problems'],
        ['name' => 'Suspension Specialist', 'description' => 'Inspects and repairs shocks, struts, and suspension systems'],
        ['name' => 'Air Conditioning Specialist', 'description' => 'Maintains and repairs vehicle air conditioning systems'],
        ['name' => 'Tire and Wheel Specialist', 'description' => 'Focuses on wheel alignment, balancing, and tire services'],
        ['name' => 'Battery Specialist', 'description' => 'Performs testing, maintenance, and replacement of vehicle batteries'],
        ['name' => 'Oil and Lubrication Technician', 'description' => 'Performs oil changes and lubrication services'],
        ['name' => 'General Auto Mechanic', 'description' => 'Performs a wide range of vehicle inspection, maintenance, and repair tasks']
    ];

    foreach ($defaultSpecialties as $spec) {
        $insertSpecialty = "
            INSERT INTO specialtiestbl (name, description)
            VALUES ('{$spec['name']}', '{$spec['description']}')
        ";
        if (!mysqli_query($db_connection, $insertSpecialty)) {
            die('Failed to insert specialty: ' . mysqli_error($db_connection));
        }
    }


    echo "<script>console.log('✅ Database and all tables created successfully. Default Super Admin added.');</script>";
}

// 5. Final execution: Check if DB exists, create if not
if (!isDBExisting($dbname)) {
    createDatabaseAndTables($dbname);
} else {
    // If it exists, connect to it
    mysqli_select_db($db_connection, $dbname);
}
