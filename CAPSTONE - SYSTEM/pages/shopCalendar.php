<?php
// pages/shopCalendar.php
session_start();
include_once '../config/db.php'; // expects $db_connection (mysqli)

// --- Show PHP errors while debugging (remove in production) -----------------
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Helper: debug mode when ?debug=1
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

// --- AJAX endpoint: return events as JSON ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'events') {
    // Require AJAX header unless debug override is used
    if ((!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
        && !$debug
    ) {
        include 'denyAccess.html';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    // SQL: coalesce sched_dt with request_dt and gather services per request.
    // Joins clients and vehicles so we can return their details for the modal.
    $sql = "
        SELECT
            r.request_id,
            r.client_id,
            r.vehicle_id,
            r.request_type,
            COALESCE(r.sched_dt, r.request_dt) AS event_dt,
            r.status,
            GROUP_CONCAT(s.service_name SEPARATOR '||') AS services_concat,
            COUNT(s.service_id) AS services_count,
            c.first_name AS client_first_name,
            c.last_name  AS client_last_name,
            c.middle_name AS client_middle_name,
            c.contact_number AS client_contact_number,
            c.email AS client_email,
            c.address AS client_address,
            v.make AS vehicle_make,
            v.model AS vehicle_model,
            v.plate_number AS vehicle_plate,
            v.color AS vehicle_color,
            v.transmission_type AS vehicle_transmission,
            v.fuel_type AS vehicle_fuel
        FROM requeststbl r
        LEFT JOIN requested_servicestbl rs ON rs.request_id = r.request_id
        LEFT JOIN servicestbl s ON s.service_id = rs.service_id
        LEFT JOIN clientstbl c ON c.client_id = r.client_id
        LEFT JOIN vehiclestbl v ON v.vehicle_id = r.vehicle_id
        WHERE r.status IN ('Pending', 'Approved')
        GROUP BY r.request_id
        ORDER BY event_dt ASC
    ";

    $events = [];
    if ($result = $db_connection->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            // Build services array
            $servicesArr = [];
            if (!empty($row['services_concat'])) {
                $servicesArr = explode('||', $row['services_concat']);
            }

            // Title: show first service, add +N if more
            if (count($servicesArr) > 0) {
                $first = $servicesArr[0];
                $extra = count($servicesArr) - 1;
                $title = $first . ($extra > 0 ? " +{$extra}" : '');
            } else {
                $title = "Request #{$row['request_id']}";
            }

            $start = $row['event_dt']; // expected "YYYY-MM-DD HH:MM:SS"

            // Build client & vehicle objects for extendedProps
            $clientObj = [
                'client_id' => $row['client_id'],
                'first_name' => $row['client_first_name'],
                'last_name' => $row['client_last_name'],
                'middle_name' => $row['client_middle_name'],
                'contact_number' => $row['client_contact_number'],
                'email' => $row['client_email'],
                'address' => $row['client_address'],
            ];
            $vehicleObj = [
                'vehicle_id' => $row['vehicle_id'],
                'make' => $row['vehicle_make'],
                'model' => $row['vehicle_model'],
                'plate_number' => $row['vehicle_plate'],
                'color' => $row['vehicle_color'],
                'transmission_type' => $row['vehicle_transmission'],
                'fuel_type' => $row['vehicle_fuel'],
            ];

            $events[] = [
                'id' => (int)$row['request_id'],
                'title' => $title,
                'start' => $start,
                'extendedProps' => [
                    'client_id' => $row['client_id'],
                    'vehicle_id' => $row['vehicle_id'],
                    'request_type' => $row['request_type'],
                    'status' => $row['status'],
                    'services' => $servicesArr,
                    'services_count' => (int)$row['services_count'],
                    'client' => $clientObj,
                    'vehicle' => $vehicleObj,
                ],
            ];
        }
        $result->free();

        // Return events; if debug requested, include SQL and count
        $payload = ['ok' => true, 'count' => count($events), 'events' => $events];
        if ($debug) $payload['sql'] = $sql;
        echo json_encode($payload);
        exit;
    } else {
        // SQL error
        http_response_code(500);
        $payload = ['ok' => false, 'error' => $db_connection->error];
        if ($debug) $payload['sql'] = $sql;
        echo json_encode($payload);
        exit;
    }
}

// --- Otherwise render the page (normal GET allowed) -----------------------------------------
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Shop Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="style/shopCalendar.css">

    <!-- FullCalendar v6+ (JS + CSS) from CDN -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" /> -->
    <script src="JS/fullCalendar.js"></script>

</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>shop calendar</span></p>

        <div id="calendar"></div>

        <!-- Debug panel (client-side logs) -->
        <div id="debugPanel" class="debug-panel"></div>
    </div>

    <!-- Bootstrap Modal markup -->
    <div class="modal fade" id="showRequestDetails" tabindex="-1" aria-labelledby="exampleModalToggleLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">Request details</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- dynamic content injected here -->
                    <div id="exampleModalBodyContent"><b>Request details will appear here</b></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const debugPanel = document.getElementById('debugPanel');
            const showDebug = new URLSearchParams(window.location.search).get('debug') === '1';
            if (showDebug) debugPanel.style.display = 'block';

            function logDbg(msg) {
                // console.log(msg);
                if (showDebug) {
                    const now = new Date().toISOString();
                    debugPanel.innerText = now + ' — ' + String(msg) + '\n' + debugPanel.innerText;
                }
            }

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function formatDateTime(dt) {
                if (!dt) return '';
                const options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                };
                return new Intl.DateTimeFormat(undefined, options).format(new Date(dt));
            }

            const eventsUrl = 'pages/shopCalendar.php?action=events' + (showDebug ? '&debug=1' : '');

            function loadEventsWithAjax(callback) {
                $.ajax({
                    url: eventsUrl,
                    method: 'GET',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(payload, textStatus, jqXHR) {
                        logDbg('HTTP status: ' + jqXHR.status + ' ' + jqXHR.statusText);
                        logDbg('Parsed payload: ' + JSON.stringify(payload).substring(0, 2000));
                        if (!payload || !payload.ok) {
                            logDbg('Server returned error payload: ' + JSON.stringify(payload));
                            alert('Server error while fetching events. Check debug panel / console.');
                            callback([]);
                            return;
                        }
                        logDbg('Events count reported by server: ' + payload.count);
                        callback(payload.events || []);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        logDbg('AJAX error: ' + textStatus + ' : ' + errorThrown);
                        try {
                            logDbg('Server response (first 2000 chars): ' + jqXHR.responseText.substring(0, 2000));
                        } catch (e) {}
                        alert('Network/server error while fetching events. See console/debug panel.');
                        callback([]);
                    }
                });
            }

            $(document).ready(function() {
                loadEventsWithAjax(function(initialEvents) {
                    logDbg('Parsed events array: ' + JSON.stringify(initialEvents, null, 2));

                    const calendarEl = document.getElementById('calendar');
                    const calendar = new FullCalendar.Calendar(calendarEl, {
                        themeSystem: 'bootstrap5',
                        initialView: 'dayGridMonth',
                        height: '80vh',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        }, // ... your other options (events, eventClick, etc.)

                        // runs when the view/date changes (initial render + navigation)
                        datesSet: function(info) {
                            const prefix = 'Pending/Approved Requests on - ';
                            const titleText = info.view.title; // FullCalendar's formatted title
                            const titleEl = calendarEl.querySelector('.fc-toolbar-title');
                            if (titleEl) titleEl.textContent = prefix + titleText;
                        },
                        navLinks: true,
                        nowIndicator: true,
                        events: initialEvents,
                        eventClick: function(info) {
                            logDbg('eventClick fired for id=' + info.event.id);
                            // pass the EventApi object directly
                            openRequestModal(info.event);
                        },
                        eventDidMount: function(info) {
                            const status = info.event.extendedProps.status;
                            if (status === 'Completed') info.el.style.opacity = 0.7;
                            else if (status === 'In Progress') info.el.style.borderLeft = '4px solid #ffb86b';
                        }
                    });
                    calendar.render();
                });

            });

            // Modal open helper: accepts a FullCalendar EventApi object
            function openRequestModal(eventApi) {
                const props = eventApi.extendedProps || {};
                const services = Array.isArray(props.services) ? props.services : [];
                const servicesHtml = services.length ?
                    '<ul class="ps-3 mb-0">' + services.map(s => `<li>${escapeHtml(s)}</li>`).join('') + '</ul>' :
                    '<div class="text-muted">No services listed</div>';

                // client details
                const client = props.client || {};
                const clientHtml = `
            <div><b>${escapeHtml(client.first_name || '')} ${escapeHtml(client.middle_name || '')} ${escapeHtml(client.last_name || '')}</b></div>
            <div><small>Contact: ${escapeHtml(client.contact_number || '')} — ${escapeHtml(client.email || '')}</small></div>
            <div><small>Address: ${escapeHtml(client.address || '')}</small></div>
        `;

                // vehicle details
                const vehicle = props.vehicle || {};
                const vehicleHtml = `
            <div><b>${escapeHtml(vehicle.make || '')} ${escapeHtml(vehicle.model || '')} ${escapeHtml(vehicle.plate_number || '')}</b></div>
            <div><small>Color: ${escapeHtml(vehicle.color || '')} — Transmission: ${escapeHtml(vehicle.transmission_type || '')} — Fuel: ${escapeHtml(vehicle.fuel_type || '')}</small></div>
        `;

                // header/title
                const title = eventApi.title || `Request #${eventApi.id}`;
                document.getElementById('exampleModalToggleLabel').textContent = title;

                // fill body
                document.getElementById('exampleModalBodyContent').innerHTML = `
            <div class="mb-2"><b>Date / Time:</b> ${formatDateTime(eventApi.start)}</div>
            <div class="mb-2"><b>Type:</b> ${escapeHtml(props.request_type || '')} &nbsp; <b>Status:</b> ${escapeHtml(props.status || '')}</div>
            <hr/>
            <h6>Client</h6>
            ${clientHtml}
            <hr/>
            <h6>Vehicle</h6>
            ${vehicleHtml}
            <hr/>
            <h6>Services (${props.services_count || services.length})</h6>
            ${servicesHtml}
        `;

                // show modal with Bootstrap API
                let bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('showRequestDetails'), {
                    keyboard: true
                });
                bsModal.show();
            }

            // expose helper globally (if you want)
            window.openRequestModal = openRequestModal;

        })();
    </script>
</body>

</html>