<?php
session_name('CABTECH_SYSTEM');
session_start();
include_once '../config/db.php';


// ALLOW ACCES IF AJAX REQUEST AND POSTS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ALLOW ACCES IF AJAX REQUEST ONLY
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        include 'denyAccess.html';
        exit;
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'applyCalendar') {
    $from = isset($_GET['from']) ? $_GET['from'] : null; // expected YYYY-MM-DD
    $to   = isset($_GET['to'])   ? $_GET['to']   : null;

    if (!$from || !$to) {
        echo json_encode(['success' => false, 'message' => 'Missing date range']);
        exit;
    }

    // Basic date format validation
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    $sql = "
    SELECT 
        r.request_id,
        DATE(COALESCE(r.sched_dt, r.request_dt)) AS date,
        TIME_FORMAT(COALESCE(r.sched_dt, r.request_dt), '%h:%i %p') AS time,
        s.service_name,
        CONCAT(c.last_name, ', ', c.first_name) AS client_name,
        v.make AS vehicle_make,
        v.model AS vehicle_model
    FROM requeststbl r
    JOIN requested_servicestbl rs ON r.request_id = rs.request_id
    JOIN servicestbl s ON rs.service_id = s.service_id
    JOIN clientstbl c ON r.client_id = c.client_id
    LEFT JOIN vehiclestbl v ON r.vehicle_id = v.vehicle_id
    WHERE DATE(COALESCE(r.sched_dt, r.request_dt)) BETWEEN ? AND ?
        AND r.status = 'Approved'
    ORDER BY (COALESCE(r.sched_dt, r.request_dt)) ASC, r.request_id ASC
    ";

    if (!($stmt = $db_connection->prepare($sql))) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error']);
        exit;
    }

    $stmt->bind_param('ss', $from, $to);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'DB execute error']);
        exit;
    }

    $res = $stmt->get_result();
    $services = [];
    while ($row = $res->fetch_assoc()) {
        $services[] = [
            'id' => isset($row['request_id']) ? (int)$row['request_id'] : null,
            'date' => $row['date'],
            'time' => $row['time'],
            'service_name' => $row['service_name'],
            'client_name' => $row['client_name'],
            'vehicle' => trim(($row['vehicle_make'] ?? '') . ' ' . ($row['vehicle_model'] ?? ''))
        ];
    }

    echo json_encode(['success' => true, 'services' => $services]);
    exit;
}



$response = [];

if (isset($_SESSION['User_role']) && $_SESSION['User_role'] == "Admin") {
    // ✅ Invoices (sum totals)
    $sqlInvoices = "SELECT 
                            SUM(items_total) AS items_total, 
                            SUM(services_total) AS services_total, 
                            SUM(grand_total) AS grand_total 
                        FROM invoicetbl";
    $resInvoices = mysqli_query($db_connection, $sqlInvoices);
    if ($resInvoices) {
        $rowInvoices = mysqli_fetch_assoc($resInvoices);
        $response['invoices'] = $rowInvoices; // direct assign
    }

    // ✅ Completed records
    $sqlRecords = "SELECT COUNT(*) AS total_completed FROM recordstbl WHERE record_status = 'completed'";
    $resRecords = mysqli_query($db_connection, $sqlRecords);
    if ($resRecords) {
        $rowRecords = mysqli_fetch_assoc($resRecords);
        $response['records'] = $rowRecords;
    }

    // ✅ Count pending and cancelled requests
    $sqlRequests = "
        SELECT 
            SUM(CASE WHEN status = 'Pending' OR status = 'Approved' THEN 1 ELSE 0 END) AS request_count,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM requeststbl
    ";
    $resRequests = mysqli_query($db_connection, $sqlRequests);

    if ($resRequests) {
        $rowReq = mysqli_fetch_assoc($resRequests);
        $response['requests'] = [
            'requestCount'   => (int)$rowReq['request_count'],
            'cancelled' => (int)$rowReq['cancelled_count']
        ];
    }

    // ✅ Users count
    $sqlUsers = "SELECT COUNT(*) AS total_clients FROM clientstbl";
    $resUsers = mysqli_query($db_connection, $sqlUsers);
    if ($resUsers) {
        $rowUsers = mysqli_fetch_assoc($resUsers);
        $response['clients'] = $rowUsers;
    }
}


$sqlRequestsOverview = "
SELECT 
    status, 
    COUNT(*) AS count
FROM requeststbl
GROUP BY status
";
$resRequestsOverview = mysqli_query($db_connection, $sqlRequestsOverview);

if ($resRequestsOverview) {
    $overview = [];
    while ($row = mysqli_fetch_assoc($resRequestsOverview)) {
        // Convert status to key-friendly format
        $key = strtolower($row['status']); // e.g., pending, approved, cancelled
        $overview[$key] = (int)$row['count'];
    }
    $response['requests_overview'] = $overview;
}



?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style/dashboardAdmin.css">


    <script>
        var dashboardStats = <?php echo json_encode($response); ?>;
        // Example: dashboardStats object from PHP
        console.log("Dashboard Stats:", dashboardStats);

        // ✅ Insert revenue (grand_total)
        if (dashboardStats.invoices) {
            // Grand total
            document.querySelector(".grand-total").textContent =
                "₱ " + parseFloat(dashboardStats.invoices.grand_total).toLocaleString();

            // Items total
            document.querySelector(".items-total").textContent =
                "Items: ₱ " + parseFloat(dashboardStats.invoices.items_total).toLocaleString();

            // Services total
            document.querySelector(".services-total").textContent =
                "Services: ₱ " + parseFloat(dashboardStats.invoices.services_total).toLocaleString();
        }

        // ✅ Insert quick stats
        if (dashboardStats.requests) {
            // Pending only
            document.querySelector(".pen-req span").textContent = dashboardStats.requests.requestCount;

            // Cancelled only
            let totalCancelledRequests = dashboardStats.requests.cancelled ?
                parseInt(dashboardStats.requests.cancelled) :
                0;
            document.querySelector(".can-req span").textContent = totalCancelledRequests;
        }

        if (dashboardStats.records) {
            // Completed records
            document.querySelector(".all-rec span").textContent = dashboardStats.records.total_completed;
        }

        if (dashboardStats.clients) {
            // Users count
            document.querySelector(".new-clients span").textContent = dashboardStats.clients.total_clients;
        }

        // Extract request overview
        const overview = dashboardStats.requests_overview || {};

        // Define the statuses you want to show and their colors
        const statuses = ['pending', 'approved', 'in progress', 'completed', 'cancelled'];
        const colors = {
            'pending': 'rgba(255, 205, 86, 0.8)', // yellow
            'approved': 'rgba(75, 192, 192, 0.8)', // green
            'in progress': 'rgba(54, 162, 235, 0.8)', // blue
            'completed': 'rgba(153, 102, 255, 0.8)', // purple
            'cancelled': 'rgba(255, 99, 132, 0.8)' // red
        };
        const borderColors = {
            'pending': 'rgba(255, 205, 86, 1)',
            'approved': 'rgba(75, 192, 192, 1)',
            'in progress': 'rgba(54, 162, 235, 1)',
            'completed': 'rgba(153, 102, 255, 1)',
            'cancelled': 'rgba(255, 99, 132, 1)'
        };

        // Prepare chart data
        const labels = [];
        const data = [];
        const backgroundColor = [];
        const borderColor = [];

        statuses.forEach(status => {
            labels.push(status.charAt(0).toUpperCase() + status.slice(1));
            data.push(overview[status] || 0);
            backgroundColor.push(colors[status]);
            borderColor.push(borderColors[status]);
        });

        // Create Chart.js doughnut chart
        const ctx = document.getElementById('requestsChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Requests Overview',
                    data: data,
                    backgroundColor: backgroundColor,
                    borderColor: borderColor,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                size: 18 // change this to your preferred size in px
                            }
                        }
                    }
                }
            }
        });

        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('date-time').textContent =
                now.toLocaleDateString('en-US', options);
        }

        // update immediately and every minute
        updateDateTime();
        setInterval(updateDateTime, 60000);
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>dashboard - admin</span></p>


        <div style="display: flex; justify-content: center; align-items: center; height: 80vh;">
            <div class="dashboard">
                <!-- Left Column -->
                <div class="card welcome">
                    <h3 class="box-head">QUICK ACCESS - WELCOME, <?php echo htmlspecialchars($_SESSION["Username"]); ?>! 👋</h3>
                    <div class="inside-box">
                        <p id="date-time"></p>
                        <div class="quick-access">
                            <button onclick="window.open('index.php', '_self')">
                                Visit Website <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/manageUsers.php')">
                                Manage Users <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/inventory.php')">
                                Manage Inventory <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/servicesOffered.php')">
                                Manage Services Offered <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/shopCalendar.php')">
                                View Calendar <i class='bx bx-right-arrow-alt'></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card requests-overview">
                    <h3 class="box-head">REQUESTS OVERVIEW</h3>
                    <div class="inside-box">
                        <canvas id="requestsChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <div class="card revenue">
                    <h3 class="box-head">TOTAL REVENUE</h3>

                    <div class="inside-box revenue-box">
                        <p class="grand-total">₱ 0.00</p>
                        <div class="sub-totals">
                            <p class="items-total">Items: ₱ 0.00</p>
                            <p class="services-total">Services: ₱ 0.00</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="quick-stats card">
                    <h3 class="box-head">QUICK STATS</h3>
                    <div class="inside-box">
                        <div class="pen-req stat">
                            <i class='bx bx-calendar-star'></i>
                            <span>0</span>
                            <p>Pending Requests</p>
                        </div>
                        <div class="all-rec stat">
                            <i class='bx bx-receipt'></i>
                            <span>0</span>
                            <p>Completed Services</p>
                        </div>
                        <div class="can-req stat">
                            <i class='bx bx-calendar-x'></i>
                            <span>0</span>
                            <p>Cancelled Requests</p>
                        </div>
                        <div class="new-clients stat">
                            <i class='bx bx-user'></i>
                            <span>0</span>
                            <p>All Clients</p>
                        </div>
                    </div>
                </div>

                <div class="card agenda">
                    <h3 class="box-head">AGENDA VIEW</h3>
                    <div class="inside-box">
                        <div class="dashboard-row" style="display:flex; gap:24px; align-items:flex-start;">
                            <!-- left: calendar + quick buttons -->
                            <div style="width:360px;">

                                <!-- this input will render an inline flatpickr -->
                                <input id="rangePicker" type="text" style="display:block; width:87%;" />

                                <!-- small summary above right panel (week range + count) -->
                                <div id="rangeSummary" style="margin-top:12px; font-weight:600;"></div>


                                <div style="margin-bottom:12px;">
                                    <button class="date-quick" data-action="today">Today</button>
                                    <button class="date-quick" data-action="tomorrow">Tomorrow</button>
                                    <button class="date-quick" data-action="this-week">This Week</button>
                                </div>
                            </div>

                            <!-- right: services list -->
                            <div id="servicesContainer" style="flex:1; max-height:700px; overflow:auto; padding-left:6px;">
                                <div id="servicesHeader" style="margin-bottom:12px; font-size:18px; font-weight:700;"></div>
                                <div id="servicesList"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>


    </div>

    <script>
        // ----------------- helpers (keep your existing helpers) -----------------
        function formatYMD(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function capitalizeFirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function niceDisplayRange(from, to) {
            const opt = {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            };
            const f = capitalizeFirst(from.toLocaleDateString(undefined, opt).toLowerCase());
            const t = capitalizeFirst(to.toLocaleDateString(undefined, opt).toLowerCase());
            if (formatYMD(from) === formatYMD(to)) return f;
            return `${f} to ${t}`;
        }

        function escapeHtml(str) {
            if (str == null) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ----------------- groupRequests: flat rows -> requests with services[] -----------------
        function groupRequests(rawList) {
            // rawList: array of rows where each row might be:
            // - grouped already: { id, date, time, client_name, vehicle, services: [ ... ] }
            // - row per service: { id (or request_id), date, time, client_name, vehicle, service_name }
            if (!Array.isArray(rawList)) return [];

            // If already grouped (first item has services array), normalize and return
            if (rawList.length && Array.isArray(rawList[0].services)) {
                return rawList.map(r => {
                    // Normalize services to array of strings
                    const svcNames = (r.services || []).map(s => {
                        if (typeof s === 'string') return s;
                        if (s && (s.service_name || s.name)) return s.service_name || s.name;
                        return String(s);
                    });
                    return {
                        id: r.id ?? r.request_id,
                        date: r.date,
                        time: r.time,
                        client_name: r.client_name ?? r.clientName ?? '',
                        vehicle: r.vehicle ?? '',
                        services: svcNames
                    };
                });
            }

            const map = new Map();
            rawList.forEach(row => {
                const id = row.id ?? row.request_id;
                if (id == null) return; // skip rows with no id

                if (!map.has(id)) {
                    map.set(id, {
                        id: Number(id),
                        date: row.date,
                        time: row.time,
                        client_name: row.client_name ?? row.clientName ?? '',
                        vehicle: row.vehicle ?? '',
                        services: []
                    });
                }
                const entry = map.get(id);

                // Push service_name if present
                if (row.service_name) {
                    entry.services.push(row.service_name);
                } else if (row.service) {
                    if (typeof row.service === 'string') entry.services.push(row.service);
                    else if (row.service.service_name) entry.services.push(row.service.service_name);
                }

                // If row has services array (sometimes mixed shapes), merge them
                if (Array.isArray(row.services)) {
                    row.services.forEach(s => {
                        if (typeof s === 'string') entry.services.push(s);
                        else if (s && (s.service_name || s.name)) entry.services.push(s.service_name || s.name);
                    });
                }
            });

            return Array.from(map.values());
        }

        // ----------------- renderServiceCard: one card per request, multiple service badges -----------------
        function renderServiceCard(req) {
            // req: { id, date, time, client_name, vehicle, services: ['A', 'B'] }
            const dateObj = new Date((req.date || '') + "T00:00:00");
            const validDate = !isNaN(dateObj.getTime());
            const dayName = validDate ? dateObj.toLocaleString(undefined, {
                weekday: 'short'
            }).toUpperCase() : '';
            const dayNum = validDate ? dateObj.getDate() : '';

            const svcNames = Array.isArray(req.services) ? req.services : [];
            const primary = svcNames[0] ?? '';
            const extraCount = Math.max(0, svcNames.length - 1);

            // Build badges HTML (shows all services)
            const badgesHtml = svcNames.map(n => `<span class="svc-badge" style="display:inline-block;padding:4px 8px;margin:3px;border-radius:6px;background:#f3f3f3;font-size:12px;">${escapeHtml(n)}</span>`).join(' ');

            // Compact primary + +N
            const compact = `${escapeHtml(primary)}${extraCount ? ` <span style="color:#333;font-weight:600;">+${extraCount}</span>` : ''}`;

            return `
            <div class="service-card" style="display:flex;gap:12px;padding:10px;margin-bottom:10px;align-items:flex-start;">
                <div class="service-date" style="text-align:center;width:72px;">
                <div style="font-size:12px;color:#777">${dayName}</div>
                <div style="font-size:28px;font-weight:600;">${dayNum}</div>
                </div>
                <div class="service-main" style="flex:1;">
                <div class="service-time" style="font-size:14px;margin-bottom:6px;"><i class="bx bx-time"></i> ${escapeHtml(req.time || '')} &nbsp;&nbsp; •  <i class="bx bx-wrench"></i> ${compact}</div>
                <div class="service-title" style="font-weight:600">${escapeHtml(req.client_name || '')}</div>
                <div class="service-vehicle" style="color:#555;font-size:13px;margin-bottom:6px;">${escapeHtml(req.vehicle || '')}</div>
                </div>
                <div style="display:flex;align-items:center;">
                <button class="manage-btn" onclick="loadPage('pages/serviceRequests.php')">Manage</button>
                </div>
            </div>
            `;
        }

        function handleManage(id) {
            console.log('manage', id);
            // open modal or navigate to manage URL
        }

        // ----------------- updateServicesPanel (unchanged except wording) -----------------
        function updateServicesPanel(fromDate, toDate, requests) {
            const $header = $('#servicesHeader');
            const $list = $('#servicesList');

            const totalRequests = requests.length;
            const totalServices = requests.reduce((acc, r) => acc + (Array.isArray(r.services) ? r.services.length : 0), 0);

            $header.text(`${niceDisplayRange(fromDate, toDate)} — ${totalRequests} Approved Request/s`);

            if (!requests || requests.length === 0) {
                $list.html('<p>No approved requests for this date range.</p>');
                return;
            }
            const html = requests.map(r => renderServiceCard(r)).join('');
            $list.html(html);
        }

        // ----------------- fetchServicesForRange: group server rows first -----------------
        function fetchServicesForRange(fromYMD, toYMD) {

            $.get('pages/dashboardAdmin.php', {
                    from: fromYMD,
                    to: toYMD,
                    action: 'applyCalendar'
                })
                .done(function(resp) {
                    if (typeof resp === 'string') {
                        try {
                            resp = JSON.parse(resp);
                        } catch (e) {
                            console.error('Invalid JSON', resp);
                            resp = {
                                success: false
                            };
                        }
                    }
                    if (resp && resp.success) {
                        const raw = resp.services || [];
                        const grouped = groupRequests(raw); // <<< IMPORTANT: grouping here

                        const fromParts = fromYMD.split('-'),
                            toParts = toYMD.split('-');
                        updateServicesPanel(
                            new Date(fromParts[0], fromParts[1] - 1, fromParts[2]),
                            new Date(toParts[0], toParts[1] - 1, toParts[2]),
                            grouped
                        );
                    } else {
                        $('#servicesList').html('<p>Error loading services.</p>');
                    }
                })
                .fail(function(xhr, status, err) {
                    console.error('fetchServices error', status, err, xhr.responseText);
                    $('#servicesList').html('<p>Error fetching services.</p>');
                });
        }

        // ----------------- rest of your existing flatpickr + quick-buttons + initial load -----------------
        // keep your current init code that clears quick buttons on calendar change etc.
        // e.g. use the block you already have for fp init, quick-button handlers, and initialLoad.
        // Just make sure fetchServicesForRange is the one above.


        // --- Initialize flatpickr ---
        // default to today
        const today = new Date();
        const fp = flatpickr("#rangePicker", {
            inline: true,
            mode: "range",
            defaultDate: [today, today], // show today selected
            onChange: function(selectedDates) {
                // If user interacts with the calendar, clear quick-button active state
                $('.date-quick').removeClass('active');

                if (!selectedDates || selectedDates.length === 0) return;
                const start = selectedDates[0];
                const end = selectedDates[1] || selectedDates[0];
                const fromYMD = formatYMD(start);
                const toYMD = formatYMD(end);
                fetchServicesForRange(fromYMD, toYMD);
            }
        });

        // --- Quick-button handlers (Today/Tomorrow/This Week) ---
        $('.date-quick').on('click', function() {
            // read action
            const action = $(this).data('action');

            const now = new Date();
            let start = new Date(now);
            let end = new Date(now);

            if (action === 'today') {
                // start = end = today
            } else if (action === 'tomorrow') {
                start.setDate(start.getDate() + 1);
                end.setDate(end.getDate() + 1);
            } else if (action === 'this-week') {
                // Week starts Monday: compute Monday as start
                // (Mon=0, Tue=1, ..., Sun=6) => dayIndex = (getDay()+6)%7
                const dayIndex = (now.getDay() + 6) % 7;
                start = new Date(now);
                start.setDate(now.getDate() - dayIndex);
                end = new Date(start);
                end.setDate(start.getDate() + 6);
            }

            // programmatically set flatpickr selected dates (this will trigger onChange)
            // we set active class AFTER setDate so onChange won't clear our intended active state
            fp.setDate([start, end], true);

            // activate this quick button and deactivate others
            $('.date-quick').removeClass('active');
            $(this).addClass('active');
        });

        // --- Initial display: load today range immediately ---
        (function initialLoad() {
            // Keep quick buttons unactivated initially
            $('.date-quick').addClass('active');

            // Set flatpickr UI and fetch for today
            fp.setDate([today, today], true); // triggers onChange -> fetchServicesForRange
        })();
    </script>
</body>

</html>