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



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendRequestStatusEmail($db_connection, $client_id, $request_id, $requestStatus, $reason = null)
{
    // --- 1. Fetch client info ---
    $query = "SELECT account_id, first_name, last_name, email FROM clientstbl WHERE client_id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Client not found.'];
    }

    $client = $result->fetch_assoc();

    // --- 2. Skip clients who already have an account ---
    // if (!empty($client['account_id'])) {
    //     return ['success' => false, 'message' => 'Client has an account; handled by another notification system.'];
    // }

    // --- 3. Prepare client fields ---
    $clientName = trim($client['first_name'] . ' ' . $client['last_name']);
    $clientEmail = $client['email'];

    if (empty($clientEmail)) {
        return ['success' => false, 'message' => 'Client email is missing.'];
    }

    // --- 4. Fetch requested service names for this request_id ---
    $svcSql = "
        SELECT rs.custom_service, s.service_name
        FROM requested_servicestbl rs
        LEFT JOIN servicestbl s ON rs.service_id = s.service_id
        WHERE rs.request_id = ?
    ";
    $svcStmt = $db_connection->prepare($svcSql);
    $svcStmt->bind_param("i", $request_id);
    $svcStmt->execute();
    $svcResult = $svcStmt->get_result();

    $services = [];
    while ($r = $svcResult->fetch_assoc()) {
        // prefer custom_service if present (custom request), otherwise use service_name
        $name = null;
        if (!empty(trim((string)$r['custom_service']))) {
            $name = trim($r['custom_service']);
        } elseif (!empty(trim((string)$r['service_name']))) {
            $name = trim($r['service_name']);
        }
        if ($name !== null && $name !== '') {
            $services[] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    $svcStmt->close();

    $servicesList = count($services) ? implode(', ', $services) : 'requested service(s)';

    // --- 5. Normalize and prepare status-specific messages ---
    $statusKey = strtolower(trim($requestStatus));
    if ($statusKey === 'canceled') $statusKey = 'cancelled';
    $displayStatus = ucwords($statusKey);

    // Better phrasing for "is now" vs "has been"
    $intro = in_array($displayStatus, ['In Progress', 'Invoice Issued'])
        ? "Your Request #{$request_id} for {$servicesList} is now <b style='font-size: 18px; text-transform: uppercase;'>{$displayStatus}</b>."
        : "Your Request #{$request_id} for {$servicesList} has been <b style='font-size: 18px; text-transform: uppercase;'>{$displayStatus}</b>.";

    $details = "";

    switch ($statusKey) {
        case 'cancelled':
            $reasonText = !empty($reason) ? htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'No reason provided.';
            $details = "
            We regret to inform you that your request has been cancelled.
            <br><b>Reason:</b> {$reasonText}
            <br><br>We sincerely apologize for any inconvenience this may have caused. Please don't hesitate to contact us if you wish to reschedule or need further assistance.
        ";
            break;

        case 'approved':
            $details = "
            Please be prepared to bring your vehicle to the shop on the scheduled date and time.
            We recommend arriving about <b>30 minutes earlier</b> to ensure a smooth check-in process.
            Our team will prepare for your requested service(s) and you'll receive further updates if anything changes.
        ";
            break;

        case 'in progress':
            $details = "
            Work on your vehicle has started. Our mechanics are currently performing the requested service(s).
            We will notify you once the work is completed and your invoice is ready for review.
        ";
            break;

        case 'invoice issued':
            $details = "
            The invoice for your service request has been issued and is now available.<br><br>
            If you have an account, you may log in to your CabTech Auto Services account to review your invoice and proceed with payment.<br>
            For clients without an account, please visit CabTech Auto Services to request and review your invoice in person.<br><br>
            Once payment is confirmed, your request will be marked as <b>Completed</b>.
        ";
            break;

        case 'completed':
            $details = "
            Payment has been confirmed and your request is now fully completed.
            Thank you for trusting <b>CabTech Auto Services</b>! We appreciate your business and hope to serve you again.
        ";
            break;

        default:
            $details = "
            There is an update to your service request. Please contact us if you need more details.
        ";
            break;
    }


    // --- 6. Compose email body ---
    $subject = "CabTech Auto Services - Service Request Status Update";
    $body = "
    <html>
    <body style='font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 20px; color: #333;'>
        <table style='max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
            <tr>
                <td style='background-color: #D42A2A; padding: 20px 30px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; text-transform: uppercase; font-weight: bolder;'>CabTech Auto Services</h1>
                </td>
            </tr>
            <tr>
                <td style='padding: 30px;'>
                    <p style='font-size: 16px;'>Dear <b>" . htmlspecialchars($clientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>,</p>
                    <p style='font-size: 15px; line-height: 1.6; color: #444;'>{$intro}</p>
                    <p style='font-size: 15px; line-height: 1.6; color: #444;'>{$details}</p>
                    <br>
                </td>
            </tr>
            <tr>
                <td style='background-color: #f0f0f0; text-align: center; padding: 15px;'>
                    <p style='font-size: 12px; color: #777; margin: 0;'>This is an automated message. Please do not reply.</p>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    // --- 7. Send email using PHPMailer ---
    require_once(__DIR__ . '/../../plugins/PHPMailer/src/PHPMailer.php');
    require_once(__DIR__ . '/../../plugins/PHPMailer/src/Exception.php');
    require_once(__DIR__ . '/../../plugins/PHPMailer/src/SMTP.php');

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabtech.system@gmail.com';
        $mail->Password   = 'xpze ongj ijau zyiu'; // keep this in config for production
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('cabtech.system@gmail.com', 'CabTech Auto Services');
        $mail->addAddress($clientEmail, $clientName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return ['success' => true, 'message' => 'Email sent to client without account.'];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'General Error: ' . $e->getMessage()];
    }
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
        `items_total` double(10,2) NOT NULL DEFAULT 0.00,
        `services_total` double(10,2) NOT NULL DEFAULT 0.00,
        `grand_total` double(10,2) NOT NULL DEFAULT 0.00,
        `issued_dt` datetime DEFAULT NULL,
        `status` varchar(255) NOT NULL DEFAULT 'Draft',
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
