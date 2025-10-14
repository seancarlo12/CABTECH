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


$response = [];

if (isset($_SESSION['User_role']) && $_SESSION['User_role'] == "Super Admin") {
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
    $sqlRecords = "SELECT COUNT(*) AS all_record FROM recordstbl";
    $resRecords = mysqli_query($db_connection, $sqlRecords);
    if ($resRecords) {
        $rowRecords = mysqli_fetch_assoc($resRecords);
        $response['records'] = $rowRecords;
    }

    $sqlRequests = "
        SELECT 
            CASE 
                WHEN status IN ('Pending', 'Approved') THEN 'pending_group'
                WHEN status = 'Cancelled' THEN 'Cancelled'
                ELSE status
            END AS status_group,
            COUNT(*) AS count_status
        FROM requeststbl
        GROUP BY status_group
    ";
    $resRequests = mysqli_query($db_connection, $sqlRequests);

    if ($resRequests) {
        $requestCounts = [];
        while ($rowReq = mysqli_fetch_assoc($resRequests)) {
            $requestCounts[strtolower($rowReq['status_group'])] = $rowReq['count_status'];
        }
        $response['requests'] = $requestCounts;
    }

    // ✅ Users count
    $sqlUsers = "SELECT COUNT(*) AS total_users FROM userstbl";
    $resUsers = mysqli_query($db_connection, $sqlUsers);
    if ($resUsers) {
        $rowUsers = mysqli_fetch_assoc($resUsers);
        $response['users'] = $rowUsers;
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
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="style/dashboardSuperAdmin.css">


    <script>
        var dashboardStats = <?php echo json_encode($response); ?>;
        // Example: dashboardStats object from PHP
        // console.log("Dashboard Stats:", dashboardStats);

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
            console.log(dashboardStats.requests);

            // All requests = sum of all statuses
            // let totalRequests = Object.values(dashboardStats.requests).reduce((a, b) => a + parseInt(b), 0);
            document.querySelector(".all-req span").textContent = dashboardStats.requests.pending_group || 0;

            // Cancelled requests
            document.querySelector(".can-req span").textContent =
                dashboardStats.requests.cancelled ? dashboardStats.requests.cancelled : 0;
        }

        if (dashboardStats.records) {
            // Completed records
            document.querySelector(".all-rec span").textContent = dashboardStats.records.all_record || 0;
        }

        if (dashboardStats.users) {
            // Users count
            document.querySelector(".new-users span").textContent = dashboardStats.users.total_users || 0;
        }


        // Populate SYSTEM NOTIFICATIONS card using a different ID
        if (dashboardStats.notifications && dashboardStats.notifications.length > 0) {
            console.log(dashboardStats.notifications);

            const notifCardList = document.getElementById('notif-card-list');
            notifCardList.innerHTML = ''; // Clear old notifications

            dashboardStats.notifications.forEach(notif => {
                const li = document.createElement('li');
                li.innerHTML = `<b>${notif.source}</b> – ${notif.message} ${notif.link ? `<a href="${notif.link}">${notif.linkText}</a>` : ''}`;
                notifCardList.appendChild(li);
            });
        } else {
            document.getElementById('notif-card-list').innerHTML = '<li>No notifications</li>';
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
        <p class="content-id"><i class='bx bx-circle'></i> <span>dashboard - super admin</span></p>


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
                            <button onclick="loadPage('pages/reports.php')">
                                View Reports <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/activityLogs.php')">
                                View System Activities <i class='bx bx-right-arrow-alt'></i>
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
                        <div class="all-req stat">
                            <i class='bx bx-calendar-star'></i>
                            <span>0</span>
                            <p>All Requests</p>
                        </div>
                        <div class="all-rec stat">
                            <i class='bx bx-receipt'></i>
                            <span>0</span>
                            <p>All Records</p>
                        </div>
                        <div class="can-req stat">
                            <i class='bx bx-calendar-x'></i>
                            <span>0</span>
                            <p>Cancelled Requests</p>
                        </div>
                        <div class="new-users stat">
                            <i class='bx bx-user'></i>
                            <span>0</span>
                            <p>All Users</p>
                        </div>
                    </div>
                </div>

                <div class="card activities">
                    <h3 class="box-head">RECENT SYSTEM ACTIVITIES</h3>
                    <div class="inside-box">
                        <table id="activities-table">
                            <thead>
                                <tr>
                                    <th>User/Role</th>
                                    <th>Activity</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody id="logs-body">
                                <tr>
                                    <td colspan="4" style="text-align:center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>


                <div class="card notifications">
                    <h3 class="box-head">SYSTEM NOTIFICATIONS</h3>
                    <div class="inside-box">
                        <ul id="notif-card-list">
                            <!-- Notifications will appear here -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>


    </div>
    <script>
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }


        function loadNotificationstoAdmin() {
            $.get("includes/fetchNotifications.php", function(response) {
                const notifList = $("#notif-card-list");
                notifList.empty();

                if (response.notifications && response.notifications.length > 0) {
                    // Filter only system notifications
                    const systemNotifs = response.notifications.filter(notif => notif.type === 'system');

                    if (systemNotifs.length > 0) {
                        systemNotifs.forEach(notif => {
                            let li = `<li>
                        <b>${notif.type}</b> <br>
                        <small class="text-muted">${formatTimestamp(notif.timestamp)}</small>
                        <p>${notif.message}</p>
                    </li>`;
                            notifList.append(li);
                        });
                    } else {
                        notifList.append('<li>No system notifications</li>');
                    }

                } else {
                    notifList.append('<li>No system notifications</li>');
                }

                // Optionally, update unread count if you want
                $("#notif-count").text(response.count || 0);
            }, "json");
        }

        // Load immediately and refresh every minute
        loadNotificationstoAdmin();
        setInterval(loadNotificationstoAdmin, 5000);

        function loadRecentActivities(keyword = '', allTime = true, dateFrom = '', dateTo = '', userRoles = ['Admin', 'Super Admin', 'Mechanic'], activityType = 'all') {
            $.get('pages/activityLogs', {
                action: 'fetchLogs',
                keyword: keyword,
                allTime: allTime ? 1 : 0,
                dateFrom: dateFrom,
                dateTo: dateTo,
                userRoles: userRoles.join(","), // comma-separated
                activityType: activityType
            }, function(response) {
                console.log("AJAX response:", response); // Debug

                let tbody = $("#logs-body");
                tbody.empty();

                if (!response || response.length === 0) {
                    tbody.append("<tr><td colspan='4' style='text-align:center;'>No logs found</td></tr>");
                } else {
                    response.forEach(function(log) {
                        let date = new Date(log.timestamp);
                        let formattedDate = date.toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric"
                        });
                        let formattedTime = date.toLocaleTimeString("en-US", {
                            hour: "numeric",
                            minute: "2-digit",
                            hour12: true
                        });
                        let formatted = `${formattedDate}<br>${formattedTime}`;

                        tbody.append(`
                    <tr>
        <td><b>${log.username}</b><br><small>${log.role}</small></td>
                        <td>${log.activity}</td>
                        <td>${formatted}</td>
                    </tr>
                `);
                    });

                    // Optional final professional row
                    tbody.append(`
                <tr>
                    <td colspan="4" style="text-align:center; color: gray;">End of Logs</td>
                </tr>
            `);
                }
            }, "json").fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error:", textStatus, errorThrown);
                $("#logs-body").html(`<tr><td colspan='4' style='text-align:center; color:red;'>Failed to fetch logs</td></tr>`);
            });
        }

        // Initial load
        loadRecentActivities();

        // Optional: refresh every minute
        setInterval(() => loadRecentActivities(), 60000);
    </script>
</body>

</html>