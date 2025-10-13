<?php
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
    // echo 'db working';
}

header('Content-Type: application/json');

// Prepare response
$response = [
    'success' => false,
    'services' => [],
    'message' => ''
];

// Query to get active services
$sql = "SELECT service_id, service_name, description, estimated_duration, labor_cost 
        FROM servicestbl 
        WHERE status = 'Active'
        ORDER BY service_name ASC";

$result = mysqli_query($db_connection, $sql);

if ($result) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Format estimated duration to HH:MM (e.g. 01:30)
            $duration = date('H:i', strtotime($row['estimated_duration']));

            $response['services'][] = [
                'service_id' => (int)$row['service_id'],
                'service_name' => $row['service_name'],
                'description' => $row['description'],
                'estimated_duration' => $duration,
                'labor_cost' => number_format($row['labor_cost'], 2)
            ];
        }
        $response['success'] = true;
    } else {
        $response['message'] = 'No active services found.';
    }
} else {
    $response['message'] = 'Database query failed: ' . mysqli_error($db_connection);
}

// Close connection 
mysqli_close($db_connection);

// Return JSON
echo json_encode($response);
