<?php
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

if (isset($_GET['action']) && $_GET['action'] === 'fetchLogs') {
    header('Content-Type: application/json');

    // Get filters from AJAX
    $keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($db_connection, $_GET['keyword']) : '';
    $allTime = isset($_GET['allTime']) && $_GET['allTime'] == 1;
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $userRoles = isset($_GET['userRoles']) ? explode(',', $_GET['userRoles']) : [];
    $activityType = isset($_GET['activityType']) ? $_GET['activityType'] : '';

    // Base query: join logstbl -> userstbl -> accountstbl
    $query = "
        SELECT l.activity, l.timestamp, a.username, u.role
        FROM logstbl l
        JOIN userstbl u ON l.account_id = u.account_id
        JOIN accountstbl a ON u.account_id = a.account_id
        WHERE 1
    ";

    // Keyword filter
    if ($keyword !== '') {
        $query .= " AND (l.activity LIKE '%$keyword%' OR a.username LIKE '%$keyword%')";
    }

    // Date filter
    if (!$allTime && $dateFrom !== '' && $dateTo !== '') {
        $query .= " AND l.timestamp BETWEEN '$dateFrom 00:00:00' AND '$dateTo 23:59:59'";
    }

    // User role filter (case-insensitive)
    if (!empty($userRoles)) {
        $roleConditions = array_map(function ($role) use ($db_connection) {
            $r = mysqli_real_escape_string($db_connection, $role);
            return "LOWER(u.role) = LOWER('$r')";
        }, $userRoles);
        $query .= " AND (" . implode(" OR ", $roleConditions) . ")";
    }

    // Apply activity type filter
    if ($activityType !== '' && strtolower($activityType) !== 'all') {
        $activityTypeEscaped = mysqli_real_escape_string($db_connection, $activityType);
        $query .= " AND l.activity_type = '$activityTypeEscaped'";
    }

    $query .= " ORDER BY l.timestamp DESC";

    error_log("SQL Query: $query"); // Debug

    $result = mysqli_query($db_connection, $query);
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }

    echo json_encode($logs);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <link rel="stylesheet" href="style/activityLogs.css">
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>activity logs</span></p>

        <div class="act-logs-box">

            <!-- Filters Section -->
            <div class="filters">
                <h2>Filters</h2>

                <label for="search">Search for Keywords</label>
                <input class="form-control" type="text" id="search" placeholder="Search Logs">

                <hr>

                <div class="date-range">
                    <label class="date-option">
                        <span>Date Range</span>
                        <input type="checkbox" id="all-time" checked>
                        <span class="ms-1">All time</span>
                    </label>
                    <div>
                        <label>From</label>
                        <input class="form-control" type="date" id="date-from" disabled>
                        <label>to</label>
                        <input class="form-control" type="date" id="date-to" disabled>
                    </div>
                </div>

                <div class="user-role">
                    <p>User Role</p>
                    <label class="role-option">
                        <input type="checkbox" value="Super Admin" checked>
                        <span>Super Admin</span>
                    </label>
                    <label class="role-option">
                        <input type="checkbox" value="Admin" checked>
                        <span>Admin</span>
                    </label>
                    <label class="role-option">
                        <input type="checkbox" value="Mechanic" checked>
                        <span>Mechanic</span>
                    </label>
                </div>

                <div class="activity-type">
                    <p>Activity Type</p>
                    <select class="form-select">
                        <option value="All">All</option>
                        <option value="LogInOut">Login</option>
                        <option value="Request">Request</option>
                        <option value="Service Offered">Service Offered</option>
                        <option value="Item">Item</option>
                    </select>
                </div>

                <button class="apply-btn">Apply Filters</button>
            </div>

            <!-- Logs Section -->
            <div class="logs">
                <table>
                    <thead>
                        <tr>
                            <th>ROLE</th>
                            <th>USERNAME</th>
                            <th>ACTIVITY</th>
                            <th>TIMESTAMP</th>
                        </tr>
                    </thead>
                    <tbody id="logs-body">
                        <!-- Logs will be loaded here -->
                    </tbody>
                </table>
            </div>

        </div>



    </div>

    <!-- BACK TO TOP BUTTON -->
    <a href="#top" title="Go up"><i class='bx bx-chevron-up' id="goTop"></i></a>
    </div>
    </div>


    <script>
        // Handle All Time checkbox
        $("#all-time").on("change", function() {
            if ($(this).is(":checked")) {
                // Disable and clear date inputs
                $("#date-from, #date-to").prop("disabled", true).val('');
            } else {
                // Enable date inputs when All Time is unchecked
                $("#date-from, #date-to").prop("disabled", false);
            }
        });

        // Handle changes in date inputs
        $("#date-from, #date-to").on("input", function() {
            // If user edits date, uncheck All Time
            if ($(this).val() !== "") {
                $("#all-time").prop("checked", false);
                $("#date-from, #date-to").prop("disabled", false);
            }
        });


        function loadLogs() {
            $.get('pages/activityLogs', {
                action: 'fetchLogs'
            }, function(response) {
                console.log(response);
                let tbody = $("#logs-body");
                tbody.empty();

                if (response.length === 0) {
                    tbody.append("<tr><td colspan='3'>No logs found</td></tr>");
                } else {
                    response.forEach(function(log) {
                        let date = new Date(log.timestamp);

                        // Format like: April 1, 2025
                        let formattedDate = date.toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric"
                        });

                        // Format like: 10:45 PM
                        let formattedTime = date.toLocaleTimeString("en-US", {
                            hour: "numeric",
                            minute: "2-digit",
                            hour12: true
                        });

                        let formatted = `${formattedDate}<br>${formattedTime}`;

                        tbody.append(`
                    <tr>
                        <td>${log.role}</td>
                        <td><b>${log.username}</b></td>
                        <td>${log.activity}</td>
                        <td>${formatted}</td>
                    </tr>
                `);
                    });

                    // Add professional final row
                    tbody.append(`
                <tr>
                    <td colspan="4" style="text-align: center; color: gray;">
                        End of Logs
                    </td>
                </tr>
            `);
                }
            }, "json");
        }
        // Load immediately + refresh every 5 seconds
        loadLogs();
        // setInterval(loadLogs, 5000);
        // Apply filters on button click
        $(".apply-btn").on("click", function() {
            // Collect filters
            let keyword = $("#search").val().trim();
            let allTime = $("#all-time").is(":checked");
            let dateFrom = $("#date-from").val();
            let dateTo = $("#date-to").val();
            let userRoles = [];
            $(".user-role input:checked").each(function() {
                userRoles.push($(this).val()); // <-- make sure checkboxes have value attributes
            });
            let activityType = $(".activity-type select").val();

            // Debug: log collected filter values
            console.log("Filters:");
            console.log("Keyword:", keyword);
            console.log("All Time:", allTime);
            console.log("Date From:", dateFrom);
            console.log("Date To:", dateTo);
            console.log("User Roles:", userRoles);
            console.log("Activity Type:", activityType);

            $.get('pages/activityLogs', {
                action: 'fetchLogs',
                keyword: keyword,
                allTime: allTime ? 1 : 0,
                dateFrom: dateFrom,
                dateTo: dateTo,
                userRoles: userRoles.join(","), // send as comma-separated string
                activityType: activityType
            }, function(response) {
                // Debug: log the raw response from PHP
                console.log("AJAX response:", response);

                let tbody = $("#logs-body");
                tbody.empty();

                if (response.length === 0) {
                    console.log("No logs found."); // Debug
                    tbody.append("<tr><td colspan='3'>No logs found</td></tr>");
                } else {
                    console.log("Logs found:", response.length); // Debug
                    response.forEach(function(log) {
                        console.log("Processing log:", log); // Debug each log

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
                        <td>${log.role}</td>
                        <td><b>${log.username}</b></td>
                        <td>${log.activity}</td>
                        <td>${formatted}</td>
                    </tr>
                `);
                    });

                    // Add final professional row
                    tbody.append(`
                <tr>
                    <td colspan="4" style="text-align: center; color: gray;">
                        End of Logs
                    </td>
                </tr>
            `);
                }
            }, "json").fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error:", textStatus, errorThrown); // Debug AJAX errors
                console.log("Raw response:", jqXHR.responseText); // <-- see what PHP actually returned
            });
        });
    </script>
</body>



</div>
</body>

</html>