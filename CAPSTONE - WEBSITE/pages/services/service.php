<?php
// Check if the file exists before including
if (file_exists('../../includes/header.php')) {

    include_once '../../includes/header.php';
}

include_once '../../includes/headNav.php';

// Get and validate id
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo '<div class="container py-5"><div class="alert alert-warning">Invalid service ID.</div></div>';
    exit;
}
function formatDuration($duration) {
    if (empty($duration)) return '';

    list($hours, $minutes, $seconds) = explode(':', $duration);
    $hours = (int)$hours;
    $minutes = (int)$minutes;

    $parts = [];

    if ($hours >= 23) {
        $days = floor($hours / 23);
        $remainingHours = $hours % 23;
        $parts[] = $days . ' ' . ($days === 1 ? 'Day' : 'Days');
        if ($remainingHours > 0) {
            $parts[] = $remainingHours . ' ' . ($remainingHours === 1 ? 'Hour' : 'Hours');
        }
    } elseif ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours === 1 ? 'Hour' : 'Hours');
    }

    if ($minutes > 0) {
        $parts[] = $minutes . ' ' . ($minutes === 1 ? 'Minute' : 'Minutes');
    }

    if (empty($parts)) return '0 Minutes';

    // Join with commas and 'and' for the last part
    if (count($parts) > 1) {
        $last = array_pop($parts);
        return implode(', ', $parts) . ' and ' . $last;
    }

    return $parts[0];
}

// Prepared statement to avoid injection
$stmt = $db_connection->prepare("SELECT * FROM servicestbl WHERE service_id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="container py-5"><div class="alert alert-info">Service not found.</div></div>';
    exit;
}

$service = $res->fetch_assoc();

// Helper values with safe fallbacks
$service_name = htmlspecialchars($service['service_name'] ?? 'Service');
$description  = trim($service['description'] ?? '');
$description_html = nl2br(htmlspecialchars($description));
$tagline_field = trim($service['tagline'] ?? $service['short_desc'] ?? '');
// if no tagline field, derive a short tagline from first sentence of description
if ($tagline_field === '') {
    $firstSentence = '';
    if ($description !== '') {
        $parts = preg_split('/(\.|\!|\?)/', $description, 2);
        $firstSentence = trim($parts[0]);
        if (strlen($firstSentence) > 120) $firstSentence = substr($firstSentence, 0, 120) . '...';
    }
    $tagline_field = $firstSentence;
}
$tagline = htmlspecialchars($tagline_field);

// common detail fields (use DB columns if present)
$duration = htmlspecialchars(formatDuration($service['estimated_duration']) ?? '—');
$price    = htmlspecialchars($service['labor_cost'] ?? '—');
$note     = trim($service['note'] ?? 'Service time and pricing may vary depending on the products/parts used, specific issue, and vehicle model.');

// for booking link - adjust as needed
$booking_link = "/booking-page.php?service_id=" . (int)$service['service_id'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= $service_name ?> — Service Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Page styling to match the image */
        :root {
            --accent-red: #e32929;
            /* left bar & small text */
            --light-red: #ffdad6;
            --note-bg: #fff3cd;
            --note-border: #ffe8a8;
            --muted-gray: #6b7280;
            --bs-button-font: 'Lunasima', sans-serif;
        }
        *{
            
            font-family: var(--bs-button-font);
        }

        .service-page {
            font-family: var(--bs-button-font);
            color: #0b1320;
            margin: 50px auto;
            padding: 0 10%;
        }

        .service-title {
            font-weight: 800;
            font-size: 2.25rem;
            /* large */
            letter-spacing: -0.02em;
            margin-bottom: .25rem;
        }

        .service-tagline {
            color: var(--accent-red);
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        /* Section header with left accent bar */
        .section-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
            margin-bottom: .75rem;
        }

        .section-title .bar {
            width: 6px;
            height: 36px;
            background: var(--accent-red);
            border-radius: 2px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
        }

        /* Note box */
        .service-note {
            background: var(--note-bg);
            border: 1px solid var(--note-border);
            padding: .85rem 1rem;
            border-radius: .45rem;
            margin-bottom: 1rem;
            color: #6b4f00;
        }

        .service-note strong {
            color: #5c3e00;
        }

        /* Details table */
        .details-table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 1rem;
            border-radius: .35rem;
            overflow: hidden;
            border: 1px solid #e6e6e6;
        }

        .details-table thead th {
            background: #fafafa;
            padding: .85rem 1rem;
            text-align: left;
            font-weight: 700;
            border-bottom: 1px solid #eaeaea;
            vertical-align: middle;
        }

        .details-table tbody td {
            padding: 1rem;
            vertical-align: top;
            border-top: 1px solid #f1f1f1;
            color: #111827;
        }

        /* Schedule button centered */
        .schedule-wrap {
            text-align: right;
            margin-top: 50px;
        }

        .btn-schedule {
            background: var(--accent-red);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: .6rem;
            transition: 100ms;
            font-weight: bold;
            text-decoration: none;
        }

        .btn-schedule:hover{
            opacity: 90%;
            color: white;
        }

        @media (min-width: 992px) {
            .service-title {
                font-size: 2.75rem;
            }

            .section-title .bar {
                height: 44px;
            }
        }
    </style>
</head>

<body>
    <div class="container service-page">
        <a href="../servicesPage.php" class="btn btn-sm button-format" style="color: white; font-weight: bold; margin-bottom: 20px;">&larr; Back to Services</a>

        <!-- top card/area -->
        <div class="mb-3">
            <h1 class="service-title"><?= $service_name ?></h1>
            <?php if ($tagline !== ''): ?>
                <div class="service-tagline"><?= $tagline ?></div>
            <?php endif; ?>
        </div>

        <!-- Service Details header -->
        <div class="section-title">
            <div class="bar" aria-hidden="true"></div>
            <h2>Service Details</h2>
        </div>

        <!-- Note -->
        <div class="service-note">
            <strong>Note:</strong> <?= nl2br(htmlspecialchars($note)) ?>
        </div>

        <!-- Details table -->
        <div class="table-responsive">
            <table class="details-table">
                <thead>
                    <tr>
                        <th style="width:60%;">Description</th>
                        <th style="width:20%;">Duration</th>
                        <th style="width:20%;">Estimated Labor Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $description_html ?: '&mdash;' ?></td>
                        <td><?= !empty($duration) ? $duration : '&mdash;' ?></td>
                        <td>₱ <?= !empty($price) ? $price : '&mdash;' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>