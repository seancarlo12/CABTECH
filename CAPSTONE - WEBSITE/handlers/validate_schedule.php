<?php
header('Content-Type: application/json');
$response = [];

$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
    // echo 'db working';
}

$schedDate = $_POST['schedDate'] ?? null;
$servicesJson = $_POST['services'] ?? '[]';
$services = json_decode($servicesJson, true);

if (!is_array($services) || count($services) === 0) {
    echo json_encode(['status' => false, 'message' => 'No services selected.']);
    exit;
}

if (!$schedDate) {
    echo json_encode(['status' => false, 'message' => 'Schedule date/time is required.']);
    exit;
}

if (!is_array($services) || count($services) === 0) {
    echo json_encode(['status' => false, 'message' => 'No services selected.']);
    exit;
}

// --- Step 1: Calculate total duration in seconds ---
$totalSeconds = 0;
foreach ($services as $service) {
    $serviceId = intval($service['service_id'] ?? 0);
    if ($serviceId <= 0) {
        echo json_encode(['status' => false, 'message' => 'Invalid service ID.']);
        exit;
    }

    $q = "SELECT estimated_duration FROM servicestbl WHERE service_id = ?";
    $s = $db_connection->prepare($q);
    if ($s === false) {
        echo json_encode(['status' => false, 'message' => 'DB prepare error: ' . $db_connection->error]);
        exit;
    }
    $s->bind_param("i", $serviceId);
    $s->execute();
    $res = $s->get_result();
    $row = $res->fetch_assoc();
    $s->close();

    $duration = $row['estimated_duration'] ?? null;
    if (!$duration) {
        echo json_encode(['status' => false, 'message' => "Service ID {$serviceId} missing duration."]);
        exit;
    }

    $parts = explode(':', $duration);
    if (count($parts) === 3) {
        [$h, $m, $ssec] = $parts;
    } elseif (count($parts) === 2) {
        [$h, $m] = $parts;
        $ssec = 0;
    } else {
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
    echo json_encode(['status' => false, 'message' => 'Invalid schedule datetime.']);
    exit;
}
$end = (clone $start)->add(new DateInterval("PT{$totalSeconds}S"));

$startStr = $start->format('Y-m-d H:i:s');
$endStr   = $end->format('Y-m-d H:i:s');

// concurrency limit
$MAX_CONCURRENT = 4;

// --- check overlaps ---
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
WHERE sub.start_dt < ? AND sub.end_dt > ?
";

$stmt = $db_connection->prepare($overlapSql);
if ($stmt === false) {
    echo json_encode(['status' => false, 'message' => 'DB prepare error (overlap): ' . $db_connection->error]);
    exit;
}
$stmt->bind_param("ss", $endStr, $startStr);
$stmt->execute();
$r = $stmt->get_result();
$row = $r->fetch_assoc();
$overlapCount = intval($row['overlap_count'] ?? 0);
$stmt->close();


// --- Suggest alternative if needed ---
if ($overlapCount >= $MAX_CONCURRENT) {
    $interval = new DateInterval('PT20M'); // 20-minute increments
    $candidate = clone $start;
    $maxTries = 10;
    $found = false;

    $shopOpen = '07:00:00';
    $shopClose = '18:00:00';

    for ($i = 1; $i <= $maxTries; $i++) {
        $candidate->add($interval);
        $candidateStartStr = $candidate->format('Y-m-d H:i:s');
        $candidateEndStr   = (clone $candidate)->add(new DateInterval("PT{$totalSeconds}S"))->format('Y-m-d H:i:s');

        $startOnly = $candidate->format('H:i:s');
        $endOnly = (new DateTime($candidateEndStr))->format('H:i:s');
        if ($startOnly < $shopOpen || $endOnly > $shopClose) continue;

        $stmt2 = $db_connection->prepare($overlapSql);
        if ($stmt2 === false) continue;
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

    $response['status'] = false;
    $response['overlap'] = $overlapCount;
    $response['message'] = $found ? 'Slot busy — suggested alternative provided.' : 'No available slot within suggestions; schedule busy.';
} else {
    $response['status']  = true;
    $response['overlap'] = $overlapCount;
    $response['message'] = 'Slot available.';
}

echo json_encode($response);
exit;
