<?php

include_once('header.php');


?>

<body>
    <div class="sidebar close">
        <div class="logo-details">
            <img src="assets/img/secondary_logo.svg" alt="">
            <span class="logo_name"><span id="cab">Cab</span>Tech</span>
        </div>
        <ul class="nav-links">
            <li>
                <a onclick="loadPage(dashboardPage)">
                    <i class='bx bx-home'></i>
                    <span class="link_name">Dashboard</span>
                </a>
                <ul class="sub-menu blank">
                    <li><a class="link_name" onclick="loadPage(dashboardPage)">Dashboard</a></li>
                </ul>
            </li>


            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Mechanic'): ?>
                <li>
                    <a onclick="loadPage('pages/shopCalendar.php')">
                        <i class='bx bx-calendar-star'></i>
                        <span class="link_name">Shop Calendar</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/shopCalendar.php')"><a class="link_name">Shop Calendar</a></li>
                    </ul>
                </li>
            <?php endif; ?>



            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin'): ?>
                <li>
                    <div class="iocn-link">
                        <a>
                            <i class='bx bx-group'></i>
                            <span class="link_name" id="nat">Manage users</span>
                        </a>
                        <i class='bx bxs-chevron-down arrow'></i>
                    </div>
                    <ul class="sub-menu">
                        <li class="def-cur"><a class="link_name">Manage Users</a></li>
                        <li onclick="loadPage('pages/manageUsers.php')"><a>Users</a></li>
                        <li onclick="loadPage('pages/manageClients.php')"><a>Clients</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin'): ?>
                <li>
                    <a onclick="loadPage('pages/serviceRequests.php')">
                        <i class='bx bxs-inbox'></i>
                        <span class="link_name">Service Requests</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/serviceRequests.php')"><a class="link_name">Service Requests</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Mechanic'): ?>
                <li>
                    <a onclick="loadPage('pages/serviceRecords.php')">
                        <i class='bx bx-archive'></i>
                        <span class="link_name">Service Records</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/serviceRecords.php')"><a class="link_name">Service Records</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Mechanic'): ?>
                <li>
                    <a onclick="loadPage('pages/servicesOffered.php')">
                        <i class='bx bx-briefcase'></i>
                        <span class="link_name">Services Offered</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/servicesOffered.php')"><a class="link_name">Services Offered</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Mechanic'): ?>
                <li>
                    <a onclick="loadPage('pages/inventory.php')">
                        <i class='bx bx-box'></i>
                        <span class="link_name">Inventory</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/inventory.php')"><a class="link_name">Inventory</a></li>
                    </ul>
                </li>
            <?php endif; ?>



            <?php if ($_SESSION['User_role'] === 'Super Admin'): ?>
                <li>
                    <a onclick="loadPage('pages/reports.php')">
                        <i class='bx bx-bar-chart-square'></i>
                        <span class="link_name">Reports</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/reports.php')"><a class="link_name">Reports</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($_SESSION['User_role'] === 'Super Admin'): ?>
                <li>
                    <a onclick="loadPage('pages/activityLogs.php')">
                        <i class='bx bx-file'></i>
                        <span class="link_name">Activity logs</span>
                    </a>
                    <ul class="sub-menu blank">
                        <li onclick="loadPage('pages/activityLogs.php')"><a class="link_name">Activity logs</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <!-- <li>
                <a>
                    <i class='bx bx-cog'></i>
                    <span class="link_name">Setting</span>
                </a>
                <ul class="sub-menu blank">
                    <li><a class="link_name">Setting</a></li>
                </ul>
            </li> -->
            <li>
                <div class="profile-details">
                    <div class="profile-content">
                        <img src="assets/img/lerong.png" alt="profileImg">
                    </div>
                    <div class="name-job">
                        <div class="profile_name"><?php echo $_SESSION["Username"]; ?></div>
                        <div class="job" id="UserRole"><?php echo $_SESSION["User_role"]; ?></div>
                    </div>
                    <i class='bx bx-log-out' onclick="Logout()" id="logout-btn"></i>
                </div>
            </li>
        </ul>
    </div>
    <section class="home-section">



        <div class="home-content">
            <i class='bx bx-menu'></i>
            <span class="text" id="top">Welcome to CabTech Web-System</span>
            <div class="dropdown">
                <button id="open-notif" class="btn btn-primary position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-bell"></i> Notifications
                    <span id="notif-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        0
                    </span>
                </button>

                <div class="dropdown-menu">
                    <ul id="notif-list">
                        <li class="cat-buttons">
                            <button class="active" data-filter="all">All</button>
                            <button data-filter="service">Service</button>
                            <button data-filter="system">System</button>
                        </li>
                        <!-- Notifications will be appended here -->
                    </ul>

                    <div id="manage-notif">
                        <button id="markaAllRead"><i class='bx bx-envelope-open'></i> Mark all as Read</button>
                        <button id="deleteAllRead"><i class='bx bx-trash-alt'></i> Delete All (Read)</button>
                    </div>
                </div>
            </div>
        </div>


        <script>
            $('.dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });

            let currentFilter = "all";
            let allNotifications = []; // store fetched notifications

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

            function renderNotifications() {
                let notifList = $('#notif-list');
                notifList.find(".notif-item, .no-results").remove(); // clear old rendered only
                notifList.find(".notif-item, .no-results, .end-marker").remove();

                // Filter in-memory
                let filtered = allNotifications.filter(n => {
                    if (currentFilter === "all") return true;
                    return n.type === currentFilter;
                });

                if (filtered.length === 0) {
                    notifList.append('<li class="dropdown-item text-center no-results">No notifications</li>');
                } else {
                    if (filtered.length === 0) {
                        notifList.append('<p class="text-center text-muted end-marker mt-3">— No notifications —</p>');
                    } else {
                        filtered.forEach(n => {
                            let itemClass = n.isRead == 0 ? 'fw-bold' : '';
                            let readText = n.isRead == 0 ? '- Unread' : '- Read';
                            let typeBadge = n.type === 'system' ?
                                '<span class="badge bg-secondary ms-1 sys">system</span>' :
                                '<span class="badge bg-primary ms-1 ser">service</span>';
                            // console.log(n);
                            // 🔥 Now n exists, safe to check goTo
                            let goToMap = {
                                users: "pages/manageUsers.php",
                                clients: "pages/manageClients.php",
                                requests: "pages/serviceRequests.php",
                                records: "pages/serviceRecords.php",
                                inventory: "pages/inventory.php",
                                servicesOffered: "pages/servicesOffered.php",
                                activityLogs: "pages/activityLogs.php",
                                calendar: "pages/shopCalendar.php"
                            };
                            let targetPage = n.goTo && goToMap[n.goTo] ? goToMap[n.goTo] : "";

                            notifList.append(`
                            <li ondblclick="loadPage('${targetPage}'); return false;  " class="dropdown-item notif-item ${itemClass}" data-id="${n.notification_id}" data-type="${n.type}">
                                ${typeBadge}<br>
                                <small class="text-muted">${formatTimestamp(n.timestamp)} 
                                    <span class="${itemClass}">${readText}</span>
                                </small><br>
                                ${n.message} <br>
                            </li>
                        `);
                        }); // 👇 Add "That's all" marker at the end
                        notifList.append('<p class="text-center text-muted end-marker mt-3 mb-3">— That’s all notifications —</p>');
                    }
                }
            }

            function loadNotifications() {
                $.get('includes/fetchNotifications.php', function(response) {
                    let count = response.count || 0;
                    $('#notif-count').text(count).toggle(count > 0);

                    // Save all notifications to memory
                    allNotifications = Array.isArray(response.notifications) ? response.notifications : [];

                    // Render based on current filter
                    renderNotifications();
                }, 'json').fail(function(xhr, status, error) {
                    console.error("DEBUG: loadNotifications AJAX error:", status, error, xhr.responseText);
                });
            }

            // Handle filter buttons
            $('#notif-list').on('click', 'button[data-filter]', function(e) {
                $('#notif-list button[data-filter]').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                renderNotifications(); // just re-render, no AJAX
            });

            // Mark as read
            $('#notif-list').on('click', 'li.notif-item', function(e) {

                let notifId = $(this).data('id');
                let li = $(this);

                $.post('includes/markRead.php', {
                    id: notifId
                }, function(response) {
                    if (response.success) {
                        // Find the notification in local array
                        let notif = allNotifications.find(n => n.notification_id == notifId);

                        if (notif && notif.isRead == 0) { // 👈 only if it was unread
                            // Update DOM
                            li.removeClass('fw-bold');
                            li.find('small span').text('- Read').removeClass('fw-bold');

                            // Update local array
                            notif.isRead = 1;

                            // Update badge count
                            let currentCount = parseInt($('#notif-count').text()) || 0;
                            let newCount = Math.max(currentCount - 1, 0);
                            $('#notif-count').text(newCount).toggle(newCount > 0);
                        }
                    } else {
                        console.warn("DEBUG: Could not mark as read:", response.message);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error("DEBUG: markRead AJAX error:", status, error, xhr.responseText);
                });
            });


            // Mark all as read
            $('#markaAllRead').on('click', function() {
                // Get unread notifications only
                let unreadIds = allNotifications.filter(n => n.isRead == 0).map(n => n.notification_id);

                if (unreadIds.length === 0) return; // nothing to mark

                $.post('includes/markRead.php', {
                    ids: unreadIds
                }, function(response) {
                    if (response.success) {
                        // Update in-memory array
                        allNotifications.forEach(n => n.isRead = 1);

                        // Update DOM
                        $('#notif-list li.notif-item').removeClass('fw-bold').find('small span').text('- Read');

                        // Reset badge count
                        $('#notif-count').text(0).hide();
                    } else {
                        console.warn("Could not mark all as read:", response.message);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error("DEBUG: mark all read AJAX error:", status, error, xhr.responseText);
                });
            });

            // Delete all read notifications
            $('#deleteAllRead').on('click', function() {
                // Get read notifications
                let readIds = allNotifications.filter(n => n.isRead == 1).map(n => n.notification_id);

                if (readIds.length === 0) return; // nothing to delete

                $.post('includes/deleteRead.php', {
                    ids: readIds
                }, function(response) {
                    if (response.success) {
                        // Remove from in-memory array
                        allNotifications = allNotifications.filter(n => n.isRead == 0);

                        // Remove from DOM
                        $('#notif-list li.notif-item').filter(function() {
                            return $(this).data('type') && $(this).find('small span').text() === '- Read';
                        }).remove();

                        // Re-render in case the end-marker or no-results needs updating
                        renderNotifications();

                        // Badge count remains the same (only unread count)
                    } else {
                        console.warn("Could not delete read notifications:", response.message);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error("DEBUG: delete read AJAX error:", status, error, xhr.responseText);
                });
            });

            // Initial + auto-refresh
            loadNotifications();
            setInterval(loadNotifications, 5000);
        </script>