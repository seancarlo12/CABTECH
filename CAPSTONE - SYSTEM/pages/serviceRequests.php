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
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests</title>
    <link rel="stylesheet" href="style/serviceRequests.css">


    <script>
        initTooltip();


        window.onload = function() {
            const cssFile = document.getElementById("css-check");
            if (!cssFile || !cssFile.sheet) {
                document.body.innerHTML = '<h1 style="color: red; text-align: center; margin-top: 20%;">CSS file missing. Page cannot be displayed.</h1>' +
                    '<p style="text-align: center;"> <a href="javascript:history.back()" style="color: blue; text-decoration: underline;">Go Back</a></p>';
            }
        };


        // Clone header and add filter row
        $('#myTable thead tr').clone(true).addClass('filters').appendTo('#myTable thead');

        const columnFilters = [{
                type: 'none'
            },
            {
                type: 'text'
            },
            {
                type: 'multi-select',
                options: ['All', 'Pending', 'Approved', 'Rescheduling']
            },
            {
                type: 'select',
                options: ['Appointment', 'Walk-In']
            },
            {
                type: 'text'
            },
            {
                type: 'text'
            },
            {
                type: 'text'
            },
            {
                type: 'text'
            },
        ];

        $('#myTable thead tr.filters th').each(function(i) {
            const filter = columnFilters[i];

            if (!filter || filter.type === 'none') {
                $(this).html(''); // No filter
                return;
            }

            if (!filter) return; // Skip if undefined

            if (filter.type === 'multi-select') {
                // Create the select element
                let html = `<select multiple class="form-select status-multiselect" data-index="${i}">`;
                html += `<option value="All" selected>All</option>`; // pre-selected All
                filter.options.forEach(opt => {
                    if (opt !== 'All') html += `<option value="${opt}">${opt}</option>`;
                });
                html += `</select>`;
                $(this).html(html);
                const $select = $(this).find('select');

                // Initialize Select2
                $select.select2({
                    placeholder: 'Filter By Status',
                    allowClear: false,
                    minimumResultsForSearch: Infinity,
                    width: '100%',
                    closeOnSelect: false
                });


                // Explicitly set and trigger selection of "All"
                $select.val(['All']).trigger('change.select2');

                $select.on('change', function() {
                    const selected = $(this).val() || [];

                    if (selected.includes('All') && selected.length > 1) {
                        // If "All" and others selected → keep only "All"
                        $(this).val(['All']).trigger('change.select2');
                        table.column(i).search('', true, false).draw();
                    } else if (!selected.includes('All')) {
                        // Filter with other selections
                        const regex = selected.join('|');
                        table.column(i).search(regex, true, false).draw();
                    } else {
                        // Only "All" selected
                        table.column(i).search('', true, false).draw();
                    }
                });
                $select.on('select2:close', function() {
                    const selected = $(this).val() || [];

                    if (selected.length === 0) {
                        // User closed dropdown with nothing selected → auto-select "All"
                        $(this).val(['All']).trigger('change.select2');
                        table.column(i).search('', true, false).draw();
                    }
                });
            } else if (filter.type === 'select') {
                let selectHtml = `<select class="form-select"><option value="">All</option>`;
                filter.options.forEach(opt => {
                    selectHtml += `<option value="${opt}">${opt}</option>`;
                });
                selectHtml += `</select>`;

                $(this).html(selectHtml);

                $('select', this).on('change', function() {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                });

            } else if (filter.type === 'text') {
                var title = $(this).clone().children().remove().end().text().trim();
                $(this).html(`<input type="text" class="form-control" placeholder="Search ${title}" />`);

                $('input', this).on('keyup change', function() {
                    if (table.column(i).search() !== this.value) {
                        table
                            .column(i)
                            .search(this.value)
                            .draw();
                    }
                });
            }
        });



        // Initialize the table
        const table = new DataTable('#myTable', {
            stateSave: true,
            responsive: true,
            orderCellsTop: true,
            fixedHeader: true,
            search: {
                regex: true
            },
            columnDefs: [{
                    className: 'dt-control',
                    orderable: false,
                    data: null,
                    defaultContent: '',
                    targets: 0
                },
                {
                    width: '200px',
                    targets: [1, 2]
                },
                {
                    width: '450px',
                    targets: [4]
                }
            ]
        });



        // Clear all DataTables search filters
        table.search('').columns().search('').draw(false);

        // Clear all filter inputs and selects in the filter row
        $('#myTable thead tr.filters th').each(function() {
            $(this).find('input, select').val('');
        });


        $('#addServiceRequest-6').on('shown.bs.modal', function() {
            initTooltip();
        });


        let ActiveRowId = null;
        let activeRow = null;





        function closeRow(tr) {
            const row = table.row(tr);
            if (row.child.isShown()) {
                row.child.hide();
                tr.classList.remove('shown');
                if (activeRow === tr) {
                    // activeRow = null;
                    // ActiveRowId = null;
                    localStorage.setItem('expandedRowIndex', null);
                }
            }
        }


        function toggleRow(tr) {
            const row = table.row(tr);
            const isOpen = row.child.isShown();
            const rowData = row.data();

            localStorage.setItem('activeRowIndex', row.index());

            // If the clicked row is already open, close it
            if (activeRow === tr && isOpen) {
                closeRow(tr);

                return;
            }

            // Close previously opened row if any
            if (activeRow && activeRow !== tr) {
                closeRow(activeRow);
                activeRow.classList.remove('active-row'); // Remove active from old one
            }

            ActiveRowId = rowData[1];
            localStorage.setItem('RowId', ActiveRowId);

            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                data: {
                    row_id: ActiveRowId,
                    action: 'getRowData'
                },
                success: function(response) {
                    if (!response || response.request === null) {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Request Not Found',
                            text: response.error || 'Invalid request data.'
                        });
                        return;
                    }

                    request_Details = response.request;
                    request_servicesInfo = response.services;
                    request_servicesDetails = response.requested_services;
                    request_Client = response.client;
                    request_Vehicle = response.vehicle;
                    // console.log(response);

                    row.child(format(rowData)).show();
                    tr.classList.add('shown', 'active-row');
                    activeRow = tr;

                    setTimeout(() => {
                        const commentBox = document.querySelector("#comment-text p");
                        const serviceButtons = document.querySelectorAll(".service-btn");

                        serviceButtons.forEach(button => {
                            button.addEventListener("click", function() {
                                const serviceId = parseInt(this.dataset.serviceId);
                                const match = request_servicesDetails.find(s => s.service_id == serviceId);

                                if (match && match.clients_comment) {
                                    commentBox.innerHTML = match.clients_comment.replace(/\n/g, "<br>");
                                } else {
                                    commentBox.innerHTML = "No comment/notes provided.";
                                }
                            });
                        });
                    }, 0);

                    initTooltip();
                    saveExpandedRows();
                },
                error: function(err) {
                    Swal.fire({
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error',
                        text: 'Something went wrong'
                    });
                    console.error('AJAX error:', err);
                }
            });
        }


        function setActiveRow(tr) {
            const row = table.row(tr);
            const rowData = row.data();
            const isOpen = row.child.isShown();
            const isActive = tr.classList.contains('active-row');

            localStorage.setItem('activeRowIndex', row.index());

            const currentlyVisibleRows = table.rows({
                search: 'applied'
            }).nodes().toArray();

            // Step 1: Clear out previously active row if it's not in visible rows (filtered/paginated out)
            if (activeRow && !currentlyVisibleRows.includes(activeRow)) {
                table.row(activeRow).child.hide();
                activeRow.classList.remove('active-row', 'shown');
                localStorage.removeItem('RowId');
                activeRow = null;
                ActiveRowId = null;
            }

            // Step 2: If a different row is currently toggled, close it
            if (activeRow && activeRow !== tr) {
                table.row(activeRow).child.hide();
                activeRow.classList.remove('active-row', 'shown');
            }

            // Step 3: If this row is already open, close and deactivate it
            if (isActive && !isOpen) {
                unsetActiveRow();

                request_Details = null;
                request_servicesInfo = null;
                request_servicesDetails = null;
                request_Client = null;
                request_Vehicle = null;
                return;
            }

            // Step 4: Activate current row
            $('tr.active-row').removeClass('active-row');
            tr.classList.add('active-row');

            // Step 5: Store values
            ActiveRowId = rowData[1];
            activeRow = tr;
            localStorage.setItem('RowId', ActiveRowId);
        }


        const formatDate = (dateInput) => {
            const date = new Date(dateInput); // ensures it's a Date object
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        };

        const formatTime = (date) =>
            date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });


        // Use event delegation to handle clicks on service buttons
        $(document).on('click', '.service-btn', function() {
            $(this).siblings('.service-btn').removeClass('active');
            $(this).addClass('active');

            const selectedServiceName = $(this).text().trim();
            $('#selected-service-name').text(` - ${selectedServiceName}`);

            const rstId = $(this).data('rst-id');
            const selected = request_servicesDetails.find(s => s.rst_id == rstId);

            const comment = selected?.clients_comment?.trim() || 'No comment/notes provided.';
            $('#comment-text').html(`<p>${comment}</p>`);
        });

        window.format = function(rowData) {


            const serviceInfo = request_servicesInfo;
            const requested_servicestbl = request_servicesDetails;

            const vehicle = request_Vehicle;
            const client = request_Client;
            const request = request_Details;

            const requestDate = new Date(request.request_dt);
            let schedDate;

            // Check if scheduled date exists
            if (request.sched_dt) {
                schedDate = new Date(request.sched_dt);
            } else {
                schedDate = requestDate;
            }

            // Check if scheduled date and time are exactly the same as requested
            const schedIsSameAsRequest = schedDate.getTime() === requestDate.getTime();



            function parseTimeToMinutes(timeStr) {
                if (!timeStr) return 0; // or handle it differently
                const parts = timeStr.split(':');
                const hours = parseInt(parts[0], 10);
                const minutes = parseInt(parts[1], 10);
                return hours * 60 + minutes;
            }

            const ALLOWANCE_MINUTES = 30;

            const totalDurationMinutes = serviceInfo.reduce((sum, service) => {
                const timeStr = service.estimated_duration;
                const duration = parseTimeToMinutes(timeStr);
                return sum + duration; // dito yung allowance kung gusto mo na kada service may plus 30minutes
            }, 0) + ALLOWANCE_MINUTES; // dito naman kung per request ang pag plus ng 30mins

            const days = Math.floor(totalDurationMinutes / (24 * 60));
            const hours = Math.floor((totalDurationMinutes % (24 * 60)) / 60);
            const minutes = totalDurationMinutes % 60;

            const formattedDuration = [
                days > 0 ? `${days} DAY${days > 1 ? 'S' : ''}` : '',
                hours > 0 ? `${hours} HOUR${hours > 1 ? 'S' : ''}` : '',
                minutes > 0 ? `${minutes} MINUTE${minutes > 1 ? 'S' : ''}` : ''
            ].filter(Boolean).join(' ') || 'N/A';

            return `
            <div id="details-row">
                <div class="first-col column">                    
                    <div id="req-date" class="${request.sched_dt ? 'opacity-50' : ''}">
                        <b>Requested Date & Time</b>
                        <p class="ellipsis-tooltip">${formatDate(requestDate)}</p>
                        <p class="ellipsis-tooltip">${formatTime(requestDate)}</p>
                    </div>

                    <div id="sched-date">
                        <b>Scheduled Date & Time</b>
                        ${schedIsSameAsRequest ? '<p class="note-text">Same as client requested*</p>' : `
                            <p class="ellipsis-tooltip">${formatDate(schedDate)}</p>
                            <p class="ellipsis-tooltip">${formatTime(schedDate)}</p>
                        `}
                                                    
                        ${['Pending', 'Approved', 'Rescheduling'].includes(request.status) ? `
                            <button id="resched-btn" ${request.resched_count >= 2 ? 'disabled style="cursor:not-allowed; background: gray;"' : ''}>
                                <i class="bx bx-calendar"></i>
                                <p>${request.resched_count >= 2 ? 'Resched Limit Reached' : 'Reschedule'}</p>
                            </button>` : ''}
                    </div>
                </div>

                
                <div class="second-col column">
                    <div id="est-duration">
                        <b>Estimated Duration</b>
                        <p class="ellipsis-tooltip">${formattedDuration}</p>
                        <small class="text-muted">*30 Minutes buffer time included </small>
                    </div>

                    <div id="client-info">
                        <b>Client information</b>
                        <p class="ellipsis-tooltip">${client.last_name}, ${client.first_name}${client.middle_name ? ', ' + client.middle_name : ''}</p>
                        <p class="ellipsis-tooltip">${client.email}</p>
                        <p class="ellipsis-tooltip">${client.contact_number}</p>
                        <p class="ellipsis-tooltip address-only">${client.address}</p>
                    </div>
                </div>

                <div class="third-col column">
                    <div id="chosen-services"> 
                        <b>(${requested_servicestbl.length}) Service/s</b>
                            ${['Pending', 'Approved', 'Rescheduling'].includes(request.status) ? `
                                <button id="manageServicesbtn">
                                    <i class="bx bx-pencil"></i>
                                    <p>MANAGE</p>
                                </button>` : ''}
                            <br>
                        <div id="services-box">
                            ${requested_servicestbl.map((service, index) => `
                                <button class="choices service-btn${index === 0 ? ' active' : ''}" data-rst-id="${service.rst_id}">
                                    ${serviceInfo[index].service_name}
                                </button>
                            `).join('')}
                        </div>
                    </div>

                    <div id="vehicle-info">
                        <b>Vehicle Information</b>
                        <p class="ellipsis-tooltip"><strong>Model/Make: </strong>${vehicle.make} ${vehicle.model}</p>
                        <p class="ellipsis-tooltip"><strong>Plate Number: </strong>${vehicle.plate_number}</p>
                        <p class="ellipsis-tooltip"><strong>Color: </strong>${vehicle.color}</p>
                        <p class="ellipsis-tooltip"><strong>Transmission: </strong>${vehicle.transmission_type}</p>
                        <p class="ellipsis-tooltip"><strong>Fuel Type: </strong>${vehicle.fuel_type}</p>
                    </div>
                </div>


                <div class="fourth-col column">
                    <div id="client-comments">
                        <b>Client's Comments/Notes <span id="selected-service-name"> - ${serviceInfo[0].service_name}</span></b>
                        <div id="wraptext">
                            <div id="comment-text">
                                <p>${requested_servicestbl[0].clients_comment || 'No comment/notes provided.'}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
                `;
        };


        // Save the currently expanded row index
        function saveExpandedRows() {
            const index = activeRow ? table.row(activeRow).index() : null;
            localStorage.setItem('expandedRowIndex', index);
        }

        // Load and re-toggle the previously expanded row
        function loadExpandedRows() {
            const activeIndex = JSON.parse(localStorage.getItem('activeRowIndex'));
            const expandedIndex = JSON.parse(localStorage.getItem('expandedRowIndex'));

            if (activeIndex !== null) {
                const row = table.row(activeIndex);
                if (row) {
                    const tr = row.node();

                    setActiveRow(tr);

                    // Only expand if it was actually expanded before reload
                    if (expandedIndex === activeIndex) {
                        toggleRow(tr);
                    }
                }
            }
        }


        function unsetActiveRow() {
            const tr = document.querySelector('.active-row');
            if (tr) {
                tr.classList.remove('active-row');
            }
            localStorage.setItem('activeRowIndex', null);
            localStorage.removeItem('RowId');
            ActiveRowId = null;
            activeRow = null;
            closeRow(tr);
        }


        // Row click event handler
        document.querySelector('#myTable tbody').addEventListener('click', function(e) {
            const td = e.target.closest('td.dt-control');
            if (!td) return;
            const tr = td.closest('tr');
            toggleRow(tr);
        });

        $('#myTable').on('order.dt page.dt search.dt length.dt', function() {
            unsetActiveRow();
        });


        loadExpandedRows();
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>service requests</span></p>
        <div id="table-box"><i class="bx bx-help-circle" title="help" id="helpicon"></i>

            <div id="buttons">
                <button id="addbtn" type="button"><i class='bx bx-plus'></i><span>Add</span></button>
                <button id="approvebtn" class="approve-btn"><i class='bx bx-check-circle'></i><span>Approve</span></button>
                <button id="cancelbtn" class="cancel-btn"><i class='bx bx-x-circle'></i><span>Cancel</span></button>
                <button id="startbtn"><i class='bx bx-cog'></i><span>Start Service</span></button>


            </div>
            <div id="mesa">
                <!-- TABLE USES DATABLES LIBRARY -->
                <table id="myTable">

                    <thead>
                        <tr class="column-heads">
                            <th></th>
                            <th>Request ID</th>
                            <th>Status</th>
                            <th>Request Type</th>
                            <th>Client Vehicle <span id="sub" class="text-capitalize text-muted">(Plate Number | Color | Make&Model)</span></th>
                            <th>Client Name</th>
                            <th>Services</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        date_default_timezone_set('Asia/Manila'); // Set timezone


                        $rs = mysqli_query($db_connection, "SELECT * FROM requeststbl WHERE status IN ('Approved', 'Pending', 'Rescheduling')");

                        while ($rw = mysqli_fetch_array($rs)) {
                            $request_id = $rw['request_id'];
                            $client_id = $rw['client_id'];
                            $vehicle_id = $rw['vehicle_id'];

                            $request_dt = strtotime($rw['request_dt']);
                            $sched_dt = isset($rw['sched_dt']) ? strtotime($rw['sched_dt']) : null;
                            $now = time();

                            $urgencyBadge = ''; // Default no badge

                            // 3 hours
                            $threshold = 3 * 60 * 60;

                            // Choose date to compare: sched_dt if exists, otherwise request_dt
                            $compareTime = $sched_dt ?: $request_dt;

                            if ($compareTime < $now) {
                                // Past due - Red
                                $urgencyBadge = 'past-due';
                            } elseif (($compareTime - $now) <= $threshold) {
                                // Near (within 3 hours) - Orange
                                $urgencyBadge = 'near-due';
                            } else {
                                $urgencyBadge = 'def-due'; // No badge if not urgent
                            }

                            // Get client
                            $client_sql = mysqli_query($db_connection, "SELECT * FROM clientstbl WHERE client_id = '$client_id'");
                            $client = mysqli_fetch_assoc($client_sql);
                            $client_name = $client['last_name'] . ', ' . $client['first_name'];

                            // Get vehicle
                            $vehicle_sql = mysqli_query($db_connection, "SELECT * FROM vehiclestbl WHERE vehicle_id = '$vehicle_id'");
                            $vehicle = mysqli_fetch_assoc($vehicle_sql);
                            $plate = !empty($vehicle['plate_number'])
                                ? $vehicle['plate_number']
                                : '<small style="color:gray;">Not Provided</small>';

                            $vehicle_info = $plate . ' | ' . $vehicle['color'] . ' | <b>' . $vehicle['make'] . ' ' . $vehicle['model'] . '</b>';

                            // Get services
                            $services_sql = mysqli_query($db_connection, "
                                SELECT s.service_name
                                FROM requested_servicestbl rs
                                JOIN servicestbl s ON rs.service_id = s.service_id
                                WHERE rs.request_id = '$request_id'
                            ");
                            $service_names = [];
                            while ($s = mysqli_fetch_assoc($services_sql)) {
                                $service_names[] = $s['service_name'];
                            }
                            $services_display = implode(', ', $service_names);

                            echo '
                                <tr class="data-rows" data-id="' . $request_id . '" oncontextmenu="toggleRow(this); return false;" onclick="setActiveRow(this);">
                                    <td class="dt-control">
                                    <i class="bx bx-chevron-down"></i>
                                    </td>
                                    <td id="aydis">' . $request_id . '</td>
                                    <td data-role="status">' . $rw['status'] .  '</td>
                                    <td data-role="request_type">' . $rw['request_type'] . '</td>
                                    <td data-role="client_vehicle" class="ellipsis-tooltip">' . $vehicle_info . '</td>
                                    <td data-role="client_name" class="ellipsis-tooltip">' . $client_name . '</td>
                                    <td data-role="services" class="ellipsis-tooltip ' . $urgencyBadge . '">' . $services_display . '</td>
                                </tr>
                            ';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BACK TO TOP BUTTON -->
        <a href="#top" id="goTop" title="Go up"><i class='bx bx-chevron-up'></i></a>
    </div>
    </div>

    <!-- MODALS -->

    <!-- MANAGE SERVICES MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="manageServicesModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage requested services</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">
                    <button id="add-another-service"><i class="bx bx-plus"></i><span> Add another Service</span></button>
                    <div class="table-container">
                        <table id="editServicesTable">
                            <thead>
                                <tr>
                                    <th>SERVICE NAME</th>
                                    <th>ESTIMATED DURATION</th>
                                    <th>LABOR COST</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mb-1">
                        <p class="text-muted mb-0">
                            Values with "<b><span style="color:red">*</span></b>" indicate a custom service.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- RESHCED MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="reschedModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">reschedule request</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">
                    <label for="og-sched">Current Schedule</label>
                    <input class="form-control" type="text" id="og-sched" name="og_sched" readonly tabindex="-1">
                    <hr>
                    <label for="newSched">New Schedule Date/Time</label>
                    <input class="form-control" type="date" id="newSched" name="new-date" placeholder="Select date and time" required>

                    <label for="resched-reason">Reason for Rescheduling Request</label>
                    <select id="resched-reason" name="resched-reason" class="form-select">
                        <option value="" hidden selected>Select a Reason</option>
                        <option value="Parts not in stock">Required parts not in stock</option>
                        <option value="Conflict booking">Conflict with another booking</option>
                        <option value="Customer requested">Customer requested change</option>
                        <option value="Shop emergency">Emergency at the shop</option>
                        <option value="Vehicle not brought in">Customer did not bring in the vehicle</option>
                        <option value="Other">Other...</option>
                    </select>

                    <div id="custom-reason-group" style="display:none; margin-top:10px;">
                        <input type="text" id="custom-reason" name="custom_reason" class="form-control" placeholder="Please specify...">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="reschedbtn" class="btn btn-primary"> <i class="bx bx-plus"></i>Reschedule</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 1 ADD REQUEST - CLIENT INFO MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-1" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <div class="text-muted mb-3">
                        <small>Step 1 of 6</small><br>
                        <strong>Client Information</strong>
                    </div>
                    <hr>

                    <form id="clientFormModal" novalidate>
                        <input type="checkbox" id="enableSelectClient">
                        <label for="enableSelectClient">Select Existing Client Accounts / Previous Clients</label>
                        <select class="form-select" id="selectClient" style="width: 100%;" disabled>
                            <option value="">-- Select Client --</option>
                        </select>
                        <div id="clientSelectNote" class="form-text text-muted" style="display: none;">
                            Selected client's contact number, email, and address can be updated.
                        </div>
                        <hr>
                        <label for="firstName">First Name</label>
                        <input class="form-control" type="text" id="firstName" name="first_name" placeholder="Enter Client's First Name" required autocomplete="off">

                        <label for="lastName">Last Name</label>
                        <input class="form-control" type="text" id="lastName" name="last_name" placeholder="Enter Client's Last Name" required autocomplete="off">

                        <label for="middleName">Middle Name</label>
                        <input type="checkbox" id="enableMiddleName" checked>
                        <input class="form-control" type="text" id="middleName" name="middle_name" placeholder="Enter Client's Middle Name" autocomplete="off">

                        <label for="mobile">Mobile Number</label>
                        <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="mobile" name="mobile_number" placeholder="Enter Client's Mobile Number" maxlength="11" required autocomplete="off">

                        <label for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" placeholder="Enter Client's Email" autocomplete="off">

                        <label for="address">Address</label>
                        <input class="form-control" type="text" id="address" name="address" placeholder="Enter Client's Address" autocomplete="off">
                        <div class="form-text">
                            Please enter the address in the format: <strong>House Number, Barangay, City</strong>
                        </div>
                    </form>
                    <div class="modal-footer">
                        <button id="closeAddRequestModal" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="showVehiclesModal" class="btn btn-primary"><i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 2 ADD REQUEST - CLIENT'S VEHICLE INFO MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-2" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <div class="text-muted mb-3">
                        <small>Step 2 of 6</small><br>
                        <strong>Select Client's Vehicle</strong>
                    </div>
                    <hr>

                    <div id="vehicleList" class="vehicle-list">
                        <!-- Add Vehicle Card -->
                        <div class="vehicle-card add-vehicle-card" id="addVehicleCard" style="cursor:pointer;">
                            <div class="vehicle-card-body" id="addVehicleBtn">
                                <h6 class="vehicle-card-title"><i class="bx bx-plus"></i> Add Vehicle</h6>
                                <p class="vehicle-card-text">Register a new vehicle for this client</p>
                            </div>
                        </div>
                        <!-- The rest of the vehicle cards will be injected here by JS -->
                    </div>


                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-1" data-bs-toggle="modal">Back</button>
                        <button type="submit" id="showServicesModal" class="btn btn-primary"> <i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 3 ADD REQUEST - SELECT FROM SERVICES OFFERED MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-3" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <div class="text-muted mb-3">
                        <small>Step 3 of 6</small><br>
                        <strong>Select Services</strong>
                    </div>
                    <hr>

                    <!-- HERE -->
                    <div class="mb-3">
                        <label for="serviceSearch" class="form-label">Search Services</label>
                        <input type="text" id="serviceSearch" class="form-control" placeholder="Search for services...">
                    </div>

                    <div id="serviceList" class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                        <!-- Services will be rendered here -->
                    </div>
                    <hr>
                    <div id="serviceSummary" class="mt-2">
                        <p><strong>Selected Services:</strong> <span id="selectedNames">None</span></p>
                        <p><strong>Total Estimated Time:</strong> <span id="totalDuration">00:00:00</span></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-2" data-bs-toggle="modal">Back</button>
                        <button type="submit" id="showCommentsModal" class="btn btn-primary"><i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 4 ADD REQUEST - COMMENTS FOR SELECTED SERVICE MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-4" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <div class="text-muted mb-3">
                        <small>Step 4 of 6</small><br>
                        <strong>Provide Comments/Descriptions For Each Service</strong>
                    </div>
                    <hr>

                    <div id="service-comments-container">
                    </div>
                    <!-- <pre id="output"></pre> -->
                    <div class="modal-footer">
                        <button onclick="saveServiceDescriptions();" type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-3" data-bs-toggle="modal">Back</button>
                        <button onclick="saveServiceDescriptions();" type="submit" id="showSchedModal" class="btn btn-primary"> <i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 5 ADD REQUEST -  DATE SCHEDULE PICKER MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-5" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">


                    <div class="text-muted mb-3">
                        <small>Step 5 of 6</small><br>
                        <strong>Schedule Appointment</strong>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <label class="form-label d-block">Request Type</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="requestType" id="appointmentRadio" value="appointment" checked>
                            <label class="form-check-label" for="appointmentRadio">Appointment</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="requestType" id="walkinRadio" value="walk-in">
                            <label class="form-check-label" for="walkinRadio">Walk-in</label>
                        </div>
                    </div>

                    <!-- Schedule Input -->
                    <div class="mb-3">
                        <label for="requestSched" class="form-label">Schedule Request</label>
                        <input type="text" id="requestSched" class="form-control" placeholder="Select date and time" autocomplete="off">
                    </div>

                    <!-- Hidden inputs to submit -->
                    <input type="hidden" id="finalRequestType" name="request_type">
                    <input type="hidden" id="finalRequestStatus" name="status">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-4" data-bs-toggle="modal">Back</button>
                        <button type="submit" id="showSummaryModal" class="btn btn-primary"> <i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 6 ADD REQUEST - FINAL - SUMMARY MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceRequest-6" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service request</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">
                    <div class="text-muted mb-3">
                        <small>Step 6 of 6</small><br>
                        <strong>Summary of Service Request</strong>
                    </div>
                    <hr>

                    <div id="summary-details">
                        <!-- Client Information -->
                        <div id="sum-client" class="alert alert-light" role="alert">
                            <b>Client Information</b>
                            <!-- Content will be injected here -->
                        </div>

                        <!-- Vehicle Information -->
                        <div id="sum-vehicle" class="alert alert-light" role="alert">
                            <b>Vehicle Information</b>
                            <!-- Content will be injected here -->
                        </div>

                        <!-- Chosen Services -->
                        <div id="sum-service" class="alert alert-light" role="alert">
                            <b>Chosen Services</b><br>
                            <!-- Buttons injected here -->
                        </div>

                        <!-- Comments/Description -->
                        <div id="sum-comment" class="alert alert-light" role="alert">
                            <b>Descriptions/Comments</b>
                            <p>No comment provided.</p>
                        </div>

                        <!-- Schedule -->
                        <div id="sum-sched" class="alert alert-light" role="alert">
                            <b>Service Schedule</b>
                            <!-- Date & Time will be injected -->
                            <p></p>
                            <p></p>
                        </div>

                        <!-- Reminders -->
                        <div id="sum-reminders" class="alert alert-info" role="alert">
                            <b>Reminder</b>
                            <ul class="mb-0">
                                <li>Check client and vehicle info.</li>
                                <li>Review selected services.</li>
                                <li>Confirm date and time.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-5" data-bs-toggle="modal">Back</button>
                        <button type="submit" id="addRequest" class="btn btn-primary"> <i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 2.1 ADD REQUEST - NEW VEHICLE FOR CLIENT MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addVehicleModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add client vehicle</h1>
                    <button type="button" class="btn-close closeAddRequestModal" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">
                    <label for="make-inpt" class="form-label">Make</label>
                    <input type="text" id="make-inpt" class="form-control" placeholder="Enter Vehicle Make" autocomplete="off">

                    <label for="model-inpt" class="form-label">Model</label>
                    <input type="text" id="model-inpt" class="form-control" placeholder="Enter Vehicle Model" autocomplete="off">

                    <label for="plate-inpt" class="form-label">Plate Number (Optional)</label>
                    <input type="text" id="plate-inpt" class="form-control" placeholder="Enter Plate Number" autocomplete="off">

                    <label for="color-inpt" class="form-label">Color</label>
                    <input type="text" id="color-inpt" class="form-control" placeholder="Enter Vehicle Color" autocomplete="off">

                    <!-- Vehicle Transmission -->
                    <label for="vtr-inpt" class="form-label">Vehicle Transmission</label>
                    <select id="vtr-inpt" class="form-select">
                        <option value="">Select Transmission</option>
                        <option value="Manual">Manual</option>
                        <option value="Automatic">Automatic</option>
                        <option value="Other">Other...</option>
                    </select>
                    <input type="text" id="vtr-other" class="form-control mt-2 d-none" placeholder="Enter transmission type">

                    <!-- Vehicle Fuel Type -->
                    <label for="vft-inpt" class="form-label">Vehicle Fuel Type</label>
                    <select id="vft-inpt" class="form-select">
                        <option value="">Select Fuel Type</option>
                        <option value="Gasoline">Gasoline (Petrol)</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Electric">Electric</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Other">Other...</option>
                    </select>
                    <input type="text" id="vft-other" class="form-control mt-2 d-none" placeholder="Enter fuel type">

                    <p class="text-muted small mt-3">
                        <i class="bx bx-info-circle"></i> This vehicle will only be linked to the client if you submit the request with this vehicle selected.
                    </p>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#addServiceRequest-2" data-bs-toggle="modal">Back</button>
                        <button id="addVehicleM" class="btn btn-primary"> <i class="bx bx-right-arrow-alt"></i>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ADDING A WALK IN REQUEST -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="startServiceModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">Assign Mechanic</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="closeAssignModal();"></button>
                </div>
                <div class="modal-body">
                    <div id="assign-modal">
                        <!-- <h2 class="modal-title fs-5 mb-2" id="exampleModalToggleLabel">Request Details</h2> -->
                        <!-- Left: Request Details -->
                        <div id="request-details" class="alert alert-secondary">
                            <p><b>Request ID<br></b><span id="assign-request-id">—</span><br><br>
                                <b>Request Type<br></b><span id="assign-request-type">—</span>
                            </p>
                            <p><b>Mechanic/s</b><br><span id="assigned-mechanics">—</span><br></p>
                            <p><b>Service/s</b><br><span id="assign-services"></span></p>
                            <p><b>Vehicle Details</b><br><span id="assign-vehicle">—</span></p>
                            <p><b>Client Information</b><br><span id="assign-client">—</span></p>
                        </div>
                        <hr style="width: 80%; margin: 20px auto; border-color:#11101d; border-width:3px; opacity: 50%;">
                        <div class="d-flex align-items-center mb-2">
                            <input type="text" id="mechanicSearch" class="form-control " placeholder="Find mechanics based on service needed…" autocomplete="off">
                        </div>
                        <div id="assign-mechanics-box" class="alert alert-light">
                            <p class="text-muted mt-3">
                                <i class="bx bx-info-circle me-1"></i>
                                Select a mechanic by clicking the header/mechanic's name
                            </p>
                            <div id="assign-mechanics-list">
                                <!-- Dynamically insert mechanic cards here -->
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeAssignModal();">Cancel</button>
                        <button id="confirmAssignMechanic" class="btn btn-primary"><i class="bx bx-right-arrow-alt"></i>Start Service</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- EDIT SERVICE MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editServiceModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">edit requested service</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">
                    <label for="service-name" class="form-label">Service Name</label>
                    <input type="text" id="service-name" class="form-control" placeholder="Enter Service Name" autocomplete="off">

                    <label for="duration-value" class="form-label">Estimated Duration</label>
                    <div class="row mb-2">
                        <div class="col-4">
                            <label for="duration-days" class="form-label">Day/s</label>
                            <input type="number" min="0" id="duration-days" class="form-control" placeholder="Days">
                        </div>
                        <div class="col-4">
                            <label for="duration-hours" class="form-label">Hour/s</label>
                            <input type="number" min="0" max="23" id="duration-hours" class="form-control" placeholder="Hours">
                        </div>
                        <div class="col-4">
                            <label for="duration-minutes" class="form-label">Minute/s</label>
                            <input type="number" min="0" max="59" id="duration-minutes" class="form-control" placeholder="Minutes" step="10">
                        </div>
                    </div>

                    <input type="hidden" id="final-duration">

                    <label for="service-cost" class="form-label">Labor Cost</label>
                    <div class="input-group">
                        <span class="input-group-text"><b>₱</b></span>
                        <input type="text" id="service-cost" class="form-control" placeholder="Enter Labor Cost" autocomplete="off">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#manageServicesModal" data-bs-toggle="modal">Back</button>
                        <button id="saveEditedService" class="btn btn-primary"><i class="bx bx-save"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD SERVICE MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add requested service</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <input type="checkbox" id="enableSelectServices">
                    <label for="enableSelectServices">Select From Services Offered</label>
                    <select class="form-select" id="selectServices" style="width: 100%;" disabled>
                        <option value="">-- Select Services --</option>
                    </select>
                    <div id="serviceSelectNote" class="form-text text-muted" style="display: none;">
                        Only the estimated duration and labor cost can be modified.
                    </div>
                    <hr>
                    <label for="add-service-name" class="form-label">Service Name</label>
                    <input type="text" id="add-service-name" class="form-control" placeholder="Enter Service Name" autocomplete="off">

                    <label for="add-duration-value" class="form-label">Estimated Duration</label>
                    <div class="row mb-2">
                        <div class="col-4">
                            <label for="add-duration-days" class="form-label">Day/s</label>
                            <input type="number" min="0" id="add-duration-days" class="form-control" placeholder="Days">
                        </div>
                        <div class="col-4">
                            <label for="add-duration-hours" class="form-label">Hour/s</label>
                            <input type="number" min="0" max="23" id="add-duration-hours" class="form-control" placeholder="Hours">
                        </div>
                        <div class="col-4">
                            <label for="add-duration-minutes" class="form-label">Minute/s</label>
                            <input type="number" min="0" max="59" id="add-duration-minutes" class="form-control" placeholder="Minutes" step="10">
                        </div>
                    </div>

                    <input type="hidden" id="add-final-duration">

                    <label for="add-service-cost" class="form-label">Labor Cost</label>
                    <div class="input-group">
                        <span class="input-group-text"><b>₱</b></span>
                        <input type="text" id="add-service-cost" class="form-control" placeholder="Enter Labor Cost" autocomplete="off">
                    </div>
                    <br>
                    <hr>
                    <label for="add-comment" class="form-label text-muted">Service Comments(Optional)</label>
                    <textarea class="form-control" id="add-comment" placeholder="Enter comment for this service"></textarea>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-target="#manageServicesModal" data-bs-toggle="modal">Back</button>
                        <button id="saveAddedService" class="btn btn-primary"><i class="bx bx-plus"></i>Add Service</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        serviceSelected = null;

        document.getElementById('enableSelectServices').addEventListener('change', function() {
            const isChecked = this.checked;

            // Enable or disable the dropdown
            $('#selectServices').prop('disabled', !isChecked);
            $('#serviceSelectNote').toggle(isChecked);

            const selectedId = $('#selectServices').val();

            if (!isChecked && selectedId) {
                // If a service was selected, clear and reset fields
                serviceSelected = null;
                $('#selectServices').val('').trigger('change');

                // Clear and re-enable input fields
                $('#add-service-name').prop('readonly', false);
                $('#add-service-name, #add-duration-days, #add-service-cost, #add-duration-hours, #add-duration-minutes, #add-final-duration, #add-comment').val('');

                serviceSelected = null;
            }
            // If no client was selected, do nothing — keep user-typed inputs intact
        });


        $('#service-cost, #add-service-cost').on('input', function() {
            const input = this;
            const rawValue = input.value;
            const caretPos = input.selectionStart;

            // Remove all non-numeric characters except period
            const numericOnly = rawValue.replace(/[^\d.]/g, '');

            if (numericOnly === '') {
                input.value = '';
                return;
            }

            // Store number of digits before caret
            const digitsBeforeCaret = rawValue.slice(0, caretPos).replace(/[^\d]/g, '').length;

            // Format the number
            const num = parseFloat(numericOnly);
            if (isNaN(num)) return;

            const formatted = num.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            input.value = formatted;

            // Restore caret position
            let newPos = 0;
            let digitCount = 0;
            for (; newPos < input.value.length; newPos++) {
                if (/\d/.test(input.value[newPos])) {
                    digitCount++;
                    if (digitCount === digitsBeforeCaret) break;
                }
            }
            input.setSelectionRange(newPos + 1, newPos + 1);
        });

        function bindDurationLimits(prefix, targetId) {
            // Days
            $(`#${prefix}-days`).on('input change', function() {
                let val = parseInt($(this).val(), 10);
                $(this).val(isNaN(val) ? 0 : Math.max(val, 0)); // days can be any non-negative number
                updateDurationFields(prefix, targetId);
            });

            // Hours + Minutes
            $(`#${prefix}-hours, #${prefix}-minutes`).on('input change', function() {
                let isHours = this.id.includes('hours');
                let max = isHours ? 23 : 59;
                let val = parseInt($(this).val(), 10);
                $(this).val(isNaN(val) ? 0 : Math.min(Math.max(val, 0), max));
                updateDurationFields(prefix, targetId);
            });
        }

        // Example usage
        bindDurationLimits('duration', 'final-duration');
        bindDurationLimits('add-duration', 'final-duration');

        function updateDurationFields(prefix, targetId) {

            const days = parseInt($(`#${prefix}-days`).val(), 10) || 0;
            const hours = parseInt($(`#${prefix}-hours`).val(), 10) || 0;
            const minutes = parseInt($(`#${prefix}-minutes`).val(), 10) || 0;

            const totalHours = (days * 24) + hours;
            const formatted = `${String(totalHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`;

            $(`#${targetId}`).val(formatted);
        }


        const swalOptions = {
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowEscapeKey: false,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };


        $(document).on('click', '.approve-btn, .cancel-btn', function() {
            if (checkSelection()) {
                return; // Exit if no valid selection
            }

            const isApprove = $(this).hasClass('approve-btn');
            const actionType = isApprove ? 'approveRequest' : 'cancelRequest';
            const statusCell = activeRow.cells[2].textContent.trim().toLowerCase();
            const requestId = activeRow.dataset.id;

            // Prevent duplicate actions
            if (
                (isApprove && statusCell === 'approved')
            ) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'Already Approved',
                    text: `This request is already Approved.`
                });
                return;
            }

            if (isApprove && statusCell !== 'pending') {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'Invalid Action',
                    text: `Only pending requests can be approved.`
                });
                return;
            }


            // Dynamic confirmation message
            const confirmTitle = isApprove ? 'Approve Request' : 'Cancel Request';
            const htmlList = isApprove ?
                `
                    <ul style="text-align: left; padding-left: 1.5em;">
                        <li>This request will be <b>Approved</b>.</li>
                        <li>Client will be notified.</li>
                    </ul>
                        <small>Make sure to review all request details before approving.</small>
                ` :
                `
                    <ul style="text-align: left; padding-left: 1.5em;">
                    <li>This request will be <b>Cancelled</b> and <b>Archived</b>.</li>
                    </ul>
                    <div style="margin-top:10px; text-align:left;">
                        <label for="cancelReason">Select a reason:</label>
                        <select id="cancelReason" class="swal2-select form-select" style="width:80%; margin-top:5px;">
                            <option value="">-- Choose a reason --</option>
                            <option value="Client requested cancellation">Client requested cancellation</option>
                            <option value="Duplicate request">Duplicate request</option>
                            <option value="Invalid information provided">Invalid information provided</option>
                            <option value="Scheduling conflict">Scheduling conflict</option>
                            <option value="Other">Other</option>
                        </select>
                        <input id="otherReason" type="text" class="swal2-input form-control"
                            placeholder="Enter custom reason" style="display:none; width:85% !important; margin-top:10px;">
                        <small><b>Verify if cancellation is intentional.</b> This action cannot be undone.</small>
                    </div>
                `;

            Swal.fire({
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: confirmTitle,
                html: htmlList,
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Approve Request' : 'Cancel Request',
                cancelButtonText: 'Cancel',
                didOpen: () => {
                    if (!isApprove) {
                        const reasonSelect = document.getElementById('cancelReason');
                        const otherInput = document.getElementById('otherReason');
                        reasonSelect.addEventListener('change', function() {
                            otherInput.style.display = this.value === 'Other' ? 'block' : 'none';
                        });
                    }
                },
                preConfirm: () => {
                    if (isApprove) return true;
                    const reason = document.getElementById('cancelReason')?.value;
                    const other = document.getElementById('otherReason')?.value.trim();
                    if (!reason) {
                        Swal.showValidationMessage('Please select a reason for cancellation.');
                        return false;
                    }
                    if (reason === 'Other' && !other) {
                        Swal.showValidationMessage('Please specify a custom reason.');
                        return false;
                    }
                    return reason === 'Other' ? other : reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading alert
                    Swal.fire({
                        iconHtml: '<i class="bx bx-loader-circle"></i>',
                        title: 'Processing...',
                        text: isApprove 
                            ? 'Approving the request and updating the client. Please wait.' 
                            : 'Cancelling the request and notifying the client. Please wait.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'handlers/serviceRequests-handler.php',
                        method: 'POST',
                        dataType: 'json',
                        data: isApprove ? {
                            action: actionType,
                            request_id: requestId
                        } : {
                            action: actionType,
                            request_id: requestId,
                            reason: result.value // send reason when canceling
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                if (isApprove) {
                                    $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`).text('Approved');
                                } else {
                                    $(`tr[data-id="${ActiveRowId}"]`).remove();
                                }
                            }
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: response.status === 'success' ? '<i class="bx bx-check-circle"></i>' : '<i class="bx bx-x-circle"></i>',
                                title: response.status === 'success' ? 'Success' : 'Error',
                                text: response.message
                            }).then(() => {
                                if (response.status === 'success') {


                                }
                            });
                        },
                        error: function() {
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-error-circle"></i>',
                                title: 'Server Error',
                                text: 'Something went wrong while processing the request.'
                            });
                        }
                    });
                }
            });
        });














        function checkSelection() {
            if (!ActiveRowId) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'No Record Selected',
                    text: 'Please select a record before performing this action.',
                });
                return true;
            } else {
                return false;
            }
        }


        $(document).on('click', '#startbtn', function() {
            if (checkSelection()) {
                return; // Exit if no valid selection
            }
            document.getElementById("mechanicSearch").value = "";
            document.getElementById("assigned-mechanics").innerHTML = "Assign a mechanic";
            AssignedMechanics.request_id = null;
            AssignedMechanics.mechanics = [];

            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                data: {
                    row_id: ActiveRowId,
                    action: 'getRowData',
                    getMechanics: true
                },
                success: function(response) {

                    if (!response || response.request === null) {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Request Not Found',
                            text: response.error || 'Invalid request data.'
                        });
                        return;
                    }

                    request_Details = response.request;
                    request_servicesInfo = response.services;
                    request_servicesDetails = response.requested_services;
                    request_Client = response.client;
                    request_Vehicle = response.vehicle;
                    mechanics = response.mechanics;
                    // console.log(mechanics);

                    if (request_Details.status === 'In Progress') {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-error-circle"></i>',
                            title: 'Already In Progress',
                            html: 'This request is already in progress and cannot be started again.<br><br><i>to reassign or modify mechanics assignment, go to the <i>Records</i> section.</i>',
                        });
                        return true;
                    }

                    if (request_Details.status !== 'Approved') {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-error-circle"></i>',
                            title: 'Request Not Approved',
                            html: `This request is currently marked as <b>"${request_Details.status}"</b> and cannot be started.<br><br><i>Only <b>Approved</b> requests can be started.</i>`,
                        });
                        return true;
                    }
                    // console.log(response.mechanics);
                    populateAssignMechanicModal();
                    $('#startServiceModal').modal('show');
                },
                error: function(err) {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error',
                        text: 'Something went wrong'
                    });
                    console.error('AJAX error:', err);
                }
            });
        });

        const AssignedMechanics = {
            request_id: null,
            mechanics: []
        };

        function renderMechanics(search = "") {
            const keyword = search.toLowerCase();

            const mechList = mechanics
                .filter(m =>
                    m.status.toLowerCase() === 'active' &&
                    m.work_status?.toLowerCase() === 'in' // Only show if currently "In"
                )
                .map(m => {
                    let matchCount = 0;

                    const specialties = m.specialties.map(s => {
                        const lower = s.toLowerCase();
                        const isMatch = keyword && lower.includes(keyword);

                        if (isMatch) matchCount++;

                        return `<span class="btn btn-sm mb-1 ${isMatch ? 'bg-dark text-light' : 'btn-light'}">${s}</span>`;
                    }).join(' ');


                    const isSelected = AssignedMechanics.mechanics.some(mec => mec.id === m.user_id);
                    const selectedClass = isSelected ? 'bg-dark text-white' : '';
                    const checkboxChecked = isSelected ? 'checked' : '';
                    const matchInfo = matchCount > 0 ?
                        `<small class="text-muted"><b>${matchCount}</b> ${matchCount === 1 ? 'match' : 'matches'} matched</small>` :
                        `<small class="text-muted"><b>0</b> matches found</small>`;

                    return `
                    <div class="card mb-3 mechanic-card"
                        data-name="${m.full_name.toLowerCase()}"
                        data-specialties="${m.specialties.map(s => s.toLowerCase()).join('|')}">
                        <div class="card-header header-click ${selectedClass}" 
                            style="cursor: pointer;" 
                            data-id="${m.user_id}">
                            <span>
                                <b>${m.full_name}</b><br>
                                ${
                                    m.working_count >= 3
                                        ? `<i><span class='text-muted'>Unavailable (3 services assigned)</span></i>`
                                        : m.working_count >= 2
                                            ? `<i><span class='text-danger'>Assigned to ${m.working_count} services</span></i>`
                                            : m.working_count >= 1
                                                ? `<i><span style="color: #b58900;">Assigned to ${m.working_count} service${m.working_count > 1 ? 's' : ''}</span></i>`
                                                : `<i><span class='text-success'>Available</span></i>`
                                }
                            </span>
                            <input type="checkbox" data-mechanic-id="${m.user_id}" id="mech-${m.user_id}" ${checkboxChecked} ${m.working_count >= 3 ? 'disabled' : ''} >

                        </div>
                        <div class="card-body">
                            ${matchInfo}
                            <div id="specialties-box">${specialties}</div>
                        </div>
                    </div>
                `;
                }).join('');

            document.getElementById("assign-mechanics-list").innerHTML = mechList;
        }


        function populateAssignMechanicModal() {
            const request = request_Details;
            const client = request_Client;
            const vehicle = request_Vehicle;
            const serviceInfos = request_servicesInfo;

            // 1. Set left panel info
            $('#assign-request-id').text(request.request_id);
            $('#assign-request-type').text(request.request_type);

            // Services
            const servicesHtml = serviceInfos.map(s =>
                `<span class="ellipsis-tooltip">${s.service_name}</span><br>`
            ).join('');
            $('#assign-services').html(servicesHtml);

            const vehicleHtml =
                `
                <p class="ellipsis-tooltip">${vehicle.make} ${vehicle.model}</p>
                <p class="ellipsis-tooltip"> ${vehicle.plate_number || 'No Plate Number'}</p>
                <p class="ellipsis-tooltip">${vehicle.color}</p>
                <p class="ellipsis-tooltip">${vehicle.transmission_type}</p>
                <p class="ellipsis-tooltip">${vehicle.fuel_type}</p>
                `;
            // Vehicle
            $('#assign-vehicle').html(vehicleHtml);

            const clientHtml =
                `
                <p class="ellipsis-tooltip">${client.last_name}, ${client.first_name}</p>
                <p class="ellipsis-tooltip">${client.contact_number || ''}</p>
                <p class="ellipsis-tooltip">${client.email}</p>
                `;
            // Client
            $('#assign-client').html(clientHtml);



            renderMechanics('');
        }

        document.getElementById("assign-mechanics-list").addEventListener("click", function(e) {
            const header = e.target.closest(".header-click");
            if (!header || e.target.matches("input[type='checkbox']")) return;

            const checkbox = header.querySelector("input[type='checkbox']");
            if (!checkbox || checkbox.disabled) return; // Prevent interaction if checkbox is disabled

            const userId = checkbox.getAttribute("data-mechanic-id");
            const fullName = header.querySelector("b").textContent.trim();

            // Toggle checked state
            checkbox.checked = !checkbox.checked;

            // Ensure ActiveRowId is tracked
            AssignedMechanics.request_id = ActiveRowId;

            if (checkbox.checked) {
                AssignedMechanics.mechanics.push({
                    id: userId,
                    name: fullName
                });
                header.classList.add("bg-dark", "text-white");
            } else {
                AssignedMechanics.mechanics = AssignedMechanics.mechanics.filter(m => m.id !== userId);
                header.classList.remove("bg-dark", "text-white");
            }

            updateAssignedMechanicsDisplay();
        });


        document.getElementById("mechanicSearch").addEventListener("input", function() {
            const keyword = this.value.toLowerCase();

            // Re-render with highlights
            renderMechanics(keyword);
        });

        function updateAssignedMechanicsDisplay() {
            const displaySpan = document.getElementById("assigned-mechanics");

            const names = AssignedMechanics.mechanics.map(m => m.name);
            displaySpan.innerHTML = names.length > 0 ?
                names.map(name => `<p class="ellipsis-tooltip">${name}</p>`).join('') :
                "Assign a mechanic";
        }


        $(document).on('click', '#confirmAssignMechanic', function() {
            if (!AssignedMechanics.request_id || AssignedMechanics.mechanics.length === 0) {
                $('#startServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'No Mechanics Selected',
                    text: 'Please select at least one mechanic before confirming.'
                }).then((result) => {
                    $('#startServiceModal').modal('show');
                });
                return;
            }

            const mechList = AssignedMechanics.mechanics
                .map(m => `• ${m.name}`)
                .join('<br>');

            $('#startServiceModal').modal('hide');
            Swal.fire({
                iconHtml: '<i class="bx bx-user-check"></i>',
                title: 'Confirm Assignment',
                html: `Assign the following mechanic(s) to <b>Request #${AssignedMechanics.request_id}</b>?<br><br>${mechList}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Assign',
                cancelButtonText: 'Back'
            }).then((result) => {
                if (result.isConfirmed) {

                    
                    // Show loading alert
                    Swal.fire({
                        iconHtml: '<i class="bx bx-loader-circle"></i>',
                        title: 'Processing...',
                        text: 'Processing mechanic assignments and notifying the client...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    console.log('Assigning mechanics:', AssignedMechanics);
                    $.ajax({
                        url: 'handlers/serviceRequests-handler.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            request_id: AssignedMechanics.request_id,
                            mechanics: AssignedMechanics.mechanics,
                            action: 'assignMechanicsToRequest'
                        }),
                        success: function(response) {
                            console.log('AJAX response:', response);
                            if (response.status === 'error') {
                                // Show error alert
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class="bx bx-x-circle"></i>',
                                    title: 'Assignment Failed',
                                    text: response.message || 'An unexpected error occurred.',
                                    icon: 'error'
                                });
                                return; // Stop further success logic
                            }

                            if (response.status === 'success') {
                                // If status is success
                                $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`).text('In Progress');
                                $('#startServiceModal').modal('hide');

                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                const row = table.row(tr);

                                // Force close first if open
                                if (row.child.isShown()) {
                                    row.child.hide();
                                    tr.removeClass('shown');
                                }

                                // Redraw row
                                row.invalidate().draw(false);

                                // Delay open to ensure DOM is ready
                                setTimeout(() => {
                                    tr.find('.dt-control').click();
                                }, 150);


                                // console.log('Assignment confirmed:', AssignedMechanics);
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                    title: 'Assigned Successfully',
                                    text: response.message || 'Mechanic(s) have been assigned to the request.',
                                    icon: 'success'
                                })
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', xhr.responseText);
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Error',
                                text: 'Something went wrong. Please check the console.'
                            });
                        }
                    });
                } else {
                    $('#startServiceModal').modal('show');
                }
            });
        });




        function closeAssignModal() {
            document.getElementById("mechanicSearch").value = "";
            document.getElementById("assigned-mechanics").innerHTML = "Assign a mechanic";
            AssignedMechanics.request_id = null;
            AssignedMechanics.mechanics = [];
        }



        let editingRstId = null;

        function populateServicesTable(services) {
            const tbody = document.querySelector("#editServicesTable tbody");
            tbody.innerHTML = ""; // Clear current rows

            services.forEach(service => {
                const durationText = formatDuration(service.estimated_duration);
                const laborText = formatPeso(service.labor_cost);
                console.log(service);
                // If custom, add a red * before the service name
                const serviceNameHTML = service.is_custom_service ?
                    `<b><span style="color:red">*</span></b> ${service.service_name}` :
                    service.service_name;

                const row = document.createElement("tr");
                row.innerHTML = `
            <td>${serviceNameHTML}</td>
            <td>${durationText}</td>
            <td>${laborText}</td>
            <td>
                <button class="edit-service" data-id="${service.rst_id}">
                    <i class="bx bx-edit"></i>
                </button>
                <button class="delete-service" data-id="${service.rst_id}">
                    <i class="bx bx-trash"></i>
                </button>
            </td>
        `;
                tbody.appendChild(row);
            });
            // Attach event listeners
            tbody.querySelectorAll(".edit-service").forEach(btn => {
                btn.addEventListener("click", () => {
                    const rstId = parseInt(btn.dataset.id, 10);
                    editingRstId = rstId; // Store for later use

                    const service = request_servicesInfo.find(s => s.rst_id === rstId);
                    if (!service) {
                        console.warn("Service not found for rst_id:", rstId);
                        return;
                    }

                    // Set values
                    $('#service-name').val(service.service_name);

                    // Toggle readonly based on whether it's custom or not
                    if (!service.is_custom_service) {
                        $('#service-name').attr('readonly', true);
                    } else {
                        $('#service-name').removeAttr('readonly');
                    }

                    $('#service-cost')
                        .val(parseFloat(service.labor_cost).toFixed(2))
                        .trigger('input'); // trigger formatting

                    const [hoursStr, minutesStr] = service.estimated_duration.split(':');
                    const totalHours = parseInt(hoursStr, 10);
                    const minutes = parseInt(minutesStr, 10);

                    const days = Math.floor(totalHours / 24);
                    const hours = totalHours % 24;

                    $('#duration-days').val(days);
                    $('#duration-hours').val(hours);
                    $('#duration-minutes').val(minutes);

                    $('#final-duration').val(service.estimated_duration);

                    $('#manageServicesModal').modal('hide');
                    $('#editServiceModal').modal('show');
                });
            }); // Attach event listeners
            tbody.querySelectorAll(".delete-service").forEach(btn => {
                btn.addEventListener("click", () => {
                    if (services.length <= 1) {
                        $('#manageServicesModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Cannot Delete',
                            text: 'At least one service must remain for this request.'
                        }).then((result) => {
                            $('#manageServicesModal').modal('show');
                        });
                        return; // Prevent further execution
                    }
                    const rstId = parseInt(btn.dataset.id, 10);
                    editingRstId = rstId; // Store for later use

                    const matchedService = services.find(s => parseInt(s.rst_id) === rstId);
                    const serviceName = matchedService ? `"${matchedService.service_name}"` : '"this service"';

                    $('#manageServicesModal').modal('hide');
                    Swal.fire({
                        title: 'Delete Requested Service?',
                        html: `Are you sure you want to delete ${serviceName} from <b>Request: ${ActiveRowId}</b>?<br>`,
                        iconHtml: '<i class="bx bx-trash"></i>',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'handlers/serviceRequests-handler.php',
                                method: 'POST',
                                data: {
                                    action: 'deleteAssignedService',
                                    rst_id: editingRstId,
                                    request_id: ActiveRowId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    $('#manageServicesModal').modal('hide');
                                    if (response.status === 'success') {
                                        //remove the deleted service 
                                        request_servicesInfo = request_servicesInfo.filter(service => parseInt(service.rst_id) !== rstId);

                                        const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                        const row = table.row(tr);

                                        // Force close first if open
                                        if (row.child.isShown()) {
                                            row.child.hide();
                                            tr.removeClass('shown');
                                        }

                                        // Redraw row
                                        row.invalidate().draw(false);

                                        // Delay open to ensure DOM is ready
                                        setTimeout(() => {
                                            tr.find('.dt-control').click();
                                        }, 150);
                                        Swal.fire({
                                            ...swalOptions,
                                            iconHtml: '<i class="bx bx-check-circle"></i>',
                                            title: 'Service Deleted!',
                                            text: response.message
                                        }).then((result) => {
                                            populateServicesTable(request_servicesInfo);
                                            $('#manageServicesModal').modal('show');
                                        });
                                    } else {
                                        Swal.fire({
                                            ...swalOptions,
                                            iconHtml: '<i class="bx bx-x-circle"></i>',
                                            title: 'Failed Deleting Service',
                                            text: response.message || 'Deletion failed.'
                                        }).then((result) => {
                                            $('#manageServicesModal').modal('show');
                                        });
                                    }
                                },
                                error: function(err) {
                                    Swal.fire({
                                        ...swalOptions,
                                        iconHtml: '<i class="bx bx-x-circle"></i>',
                                        title: 'AJAX Error',
                                        text: 'Something went wrong.'
                                    });
                                    console.error(err);
                                }
                            });
                        } else {
                            $('#manageServicesModal').modal('show');
                        }
                    });
                });
            });
        }


        $(document).on('click', '#manageServicesbtn', function() {
            // console.log(request_servicesInfo);
            populateServicesTable(request_servicesInfo);
            $('#manageServicesModal').modal('show');
        });

        $(document).on('click', '#saveAddedService', function() {
            const name = $('#add-service-name').val().trim();
            const costRaw = $('#add-service-cost').val().replace(/[₱,]/g, '').trim();
            const duration = $('#add-final-duration').val();
            const serviceComment = $('#add-comment').val();

            const labor_cost = parseFloat(costRaw || 0).toFixed(2);
            // console.log(duration);

            // Basic validation
            if (!name || !duration || isNaN(parseFloat(labor_cost))) {
                $('#addServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'Missing Fields',
                    text: 'Please complete all fields.'
                }).then((result) => {
                    $('#addServiceModal').modal('show');
                });
                return;
            }
            if (serviceSelected) {
                const modifiedService = {
                    action: 'addServiceToRequest',
                    request_id: ActiveRowId,
                    service_id: serviceSelected.service_id,
                    service_name: name,
                    estimated_duration: duration,
                    labor_cost: labor_cost,
                    clients_comment: serviceComment,
                    is_custom_duration: false,
                    is_custom_labor: false,
                    is_custom_service: false
                };

                // Check if estimated_duration differs
                if (serviceSelected.estimated_duration !== duration) {
                    modifiedService.is_custom_duration = true;
                }

                // Check if labor_cost differs
                if (parseFloat(serviceSelected.labor_cost) !== parseFloat(labor_cost)) {
                    modifiedService.is_custom_labor = true;
                }

                // Check if service_name is edited (shouldn't be possible unless made editable)
                if (serviceSelected.service_name !== name) {
                    modifiedService.is_custom_service = true;
                }


                $.ajax({
                    url: 'handlers/serviceRequests-handler.php',
                    method: 'POST',
                    dataType: 'json',
                    data: modifiedService,
                    success(response) {
                        $('#addServiceModal').modal('hide');
                        //REFRESH TABLE / REFRESH TOGGLEROW / UPDATE SHI

                        // console.log('Modified:', modifiedService);
                        if (response.status === 'success') {
                            // Add the new service to request_servicesInfo
                            const newService = {
                                rst_id: response.insert_id,
                                service_id: modifiedService.service_id,
                                service_name: modifiedService.service_name,
                                estimated_duration: modifiedService.estimated_duration,
                                labor_cost: modifiedService.labor_cost,
                                clients_comment: modifiedService.clients_comment,
                                is_custom_service: modifiedService.is_custom_service,
                                is_custom_duration: modifiedService.is_custom_duration,
                                is_custom_labor: modifiedService.is_custom_labor,
                            };
                            request_servicesInfo.push(newService);
                            const tr = $(`tr[data-id="${ActiveRowId}"]`);
                            const row = table.row(tr);

                            // Force close first if open
                            if (row.child.isShown()) {
                                row.child.hide();
                                tr.removeClass('shown');
                            }

                            // Redraw row
                            row.invalidate().draw(false);

                            // Delay open to ensure DOM is ready
                            setTimeout(() => {
                                tr.find('.dt-control').click();
                            }, 150);
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-check-circle"></i>',
                                title: 'Service Added!',
                                text: response.message
                            }).then((result) => {

                                populateServicesTable(request_servicesInfo);
                                $('#manageServicesModal').modal('show');
                            });
                        } else {
                            console.log(response);
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Failed Adding Service',
                                text: response.message || 'Adding failed.'
                            }).then((result) => {
                                $('#addServiceModal').modal('show');
                            });
                        }


                    },
                    error: function(err) {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'AJAX Error',
                            text: 'Something went wrong.'
                        });
                        console.error(err);
                    }
                });

                return;
            }

            // Handle fully custom service
            const customService = {
                action: 'addServiceToRequest',
                request_id: ActiveRowId,
                service_name: name,
                estimated_duration: duration,
                labor_cost: labor_cost,
                clients_comment: serviceComment,
                is_custom_duration: true,
                is_custom_labor: true,
                is_custom_service: true
            };

            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                dataType: 'json',
                data: customService,
                success(response) {
                    $('#addServiceModal').modal('hide');

                    console.log('New:', customService);
                    if (response.status === 'success') {
                        // Add the new service to request_servicesInfo
                        const newService = {
                            rst_id: response.insert_id,
                            service_id: customService.service_id,
                            service_name: customService.service_name,
                            estimated_duration: customService.estimated_duration,
                            labor_cost: customService.labor_cost,
                            clients_comment: customService.clients_comment,
                            is_custom_service: customService.is_custom_service,
                            is_custom_duration: customService.is_custom_duration,
                            is_custom_labor: customService.is_custom_labor,
                        };
                        request_servicesInfo.push(newService);

                        const tr = $(`tr[data-id="${ActiveRowId}"]`);
                        const row = table.row(tr);

                        // Force close first if open
                        if (row.child.isShown()) {
                            row.child.hide();
                            tr.removeClass('shown');
                        }

                        // Redraw row
                        row.invalidate().draw(false);

                        // Delay open to ensure DOM is ready
                        setTimeout(() => {
                            tr.find('.dt-control').click();
                        }, 150);
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: 'Service Added!',
                            text: response.message
                        }).then((result) => {

                            console.log(request_servicesInfo);
                            populateServicesTable(request_servicesInfo);
                            $('#manageServicesModal').modal('show');
                        });
                    } else {
                        console.log(response);
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Failed Adding Service',
                            text: response.message || 'Adding failed.'
                        }).then((result) => {
                            $('#addServiceModal').modal('show');
                        });
                    }
                },
                error(err) {
                    console.error('Failed to add custom service:', err);
                    Swal.fire('Error', 'Failed to add custom service.', 'error');
                }
            });
        });



        $(document).on('click', '#add-another-service', function() {
            serviceSelected = null;
            $('#serviceSelectNote').hide();
            $('#add-service-name').prop('readonly', false);
            $('#add-service-name, #add-duration-days, #add-duration-hours, #add-service-cost, #add-duration-minutes, #add-final-duration, #add-comment').val('');
            document.getElementById('enableSelectServices').checked = false;
            $('#manageServicesModal').modal('hide');
            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                data: {
                    row_id: ActiveRowId,
                    action: 'getAllServices'
                },
                success: function(response) {

                    allServices = response.services;
                    select2ForAddService();
                },
                error: function(err) {
                    Swal.fire({
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error',
                        text: 'Something went wrong'
                    });
                    console.error('AJAX error:', err);
                }
            });
            $('#addServiceModal').modal('show');
        });

        function select2ForAddService() {
            const serviceSelect = $('#selectServices');

            // Destroy previous Select2 if already initialized
            if (serviceSelect.data('select2')) {
                serviceSelect.select2('destroy');
            }

            // Clear all options except the placeholder
            serviceSelect.find('option:not(:first)').remove();

            // Get all service_ids already requested | di na lalabas yung mga added na service na
            const existingServiceIds = request_servicesInfo
                .map(s => Number(s.service_id))
                .filter(id => !isNaN(id));

            allServices.forEach(service => {
                if (
                    service.status.toLowerCase() === 'active' &&
                    !existingServiceIds.includes(Number(service.service_id))
                ) {
                    const display = `${service.service_name}`;
                    const option = new Option(display, service.service_id, false, false);
                    serviceSelect.append(option);
                }
            });

            // Initialize Select2
            serviceSelect.select2({
                dropdownParent: $('#addServiceModal'),
                placeholder: 'Select a service',
                width: '100%'
            });

            // Custom search placeholder
            serviceSelect.on('select2:open', () => {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.placeholder = 'Search for services...';
                    }
                }, 0);
            });

            // Prefill and control inputs
            serviceSelect.on('change', function() {
                const selectedId = $(this).val();
                const selectedService = allServices.find(s => s.service_id == selectedId);

                serviceSelected = selectedService;


                if (selectedService) {
                    // Set values
                    $('#add-service-name')
                        .val(selectedService.service_name)
                        .prop('readonly', true); // Make readonly

                    const [hh, mm] = selectedService.estimated_duration.split(':').map(Number);
                    const days = Math.floor(hh / 24);
                    const hours = hh % 24;

                    $('#add-duration-days').val(days);
                    $('#add-duration-hours').val(hours);
                    $('#add-duration-minutes').val(mm);
                    updateDurationFields('add-duration', 'add-final-duration');

                    const formatted = parseFloat(selectedService.labor_cost || 0).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    $('#add-service-cost').val(formatted);
                } else {
                    // Clear fields and make name editable
                    $('#add-service-name')
                        .val('')
                        .prop('readonly', false);

                    $('#add-duration-days').val('');
                    $('#add-duration-hours').val('');
                    $('#add-duration-minutes').val('');
                    $('#add-service-cost').val('');
                    $('#add-final-duration').val('');
                }
            });
        }


        // Bind duration inputs to update final duration dynamically
        $('#add-duration-days, #add-duration-hours, #add-duration-minutes').on('input change', function() {
            updateDurationFields('add-duration', 'add-final-duration');
        });


        $(document).on('click', '#saveEditedService', function() {
            const serviceName = $('#service-name').val().trim();
            const formattedCost = $('#service-cost').val(); // "2,800.00" may comma
            const laborCost = formattedCost.replace(/,/g, ''); // "2800.00" walang comma
            const finalDuration = $('#final-duration').val();

            if (!serviceName) {
                $('#editServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Missing Service Name',
                    text: 'Please enter the service name.'
                }).then(result => {
                    $('#editServiceModal').modal('show');
                });
                return;
            }
            // Parse to check if it's at least 1 second
            const [hours, minutes, seconds] = finalDuration.split(':').map(Number);
            const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;

            if (finalDuration == '' || totalSeconds <= 540) { //540 seconds is 9mins
                $('#editServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Invalid Duration',
                    text: 'Please enter a duration greater than 0. At least 10 minute is required.'
                }).then(result => {
                    $('#editServiceModal').modal('show');
                });
                return;
            }


            if (isNaN(laborCost) || laborCost <= 0) {
                $('#editServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Invalid Labor Cost',
                    text: 'Please enter a valid labor cost greater than 0.'
                }).then(result => {
                    $('#editServiceModal').modal('show');
                });
                return;
            }



            const rstId = editingRstId;
            const original = request_servicesInfo.find(s => s.rst_id == rstId);

            if (!original) {
                console.error('Original service not found');
                return;
            }

            // Normalize cost for comparison
            const originalCost = parseFloat(original.labor_cost).toFixed(2);
            const newCost = parseFloat(laborCost).toFixed(2);

            // Track changes
            const isNameChanged = original.service_name.trim() !== serviceName;
            const isDurationChanged = original.estimated_duration !== finalDuration;
            const isCostChanged = originalCost !== newCost;

            const anyChanges = isNameChanged || isDurationChanged || isCostChanged;

            if (!anyChanges) {
                $('#editServiceModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'No Changes Detected',
                    text: 'You did not make any changes to this service.'
                }).then(() => {
                    $('#editServiceModal').modal('show');
                });
                return;
            }


            // console.log(editingRstId);
            // console.log({
            //     isNameChanged,
            //     isDurationChanged,
            //     isCostChanged
            // });
            // console.log('Final Duration:', finalDuration, 'final cost:', laborCost, 'final name:', serviceName);

            const updateData = {
                rst_id: editingRstId,
                service_name: serviceName,
                estimated_duration: finalDuration,
                labor_cost: laborCost,
                is_name_changed: isNameChanged,
                is_duration_changed: isDurationChanged,
                is_cost_changed: isCostChanged,
                action: "updateAssignedService",
                request_id: ActiveRowId
            };


            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                dataType: 'json',
                data: updateData,
                success: function(response) {
                    $('#editServiceModal').modal('hide');

                    if (response.status === 'success') {
                        //code to reload table// Find the service by rst_id
                        const targetService = request_servicesInfo.find(s => s.rst_id === parseInt(editingRstId));

                        if (targetService) {
                            if (isNameChanged) {
                                targetService.service_name = serviceName;
                                targetService.is_custom_service = true;
                            }

                            if (isDurationChanged) {
                                targetService.estimated_duration = finalDuration;
                                targetService.is_custom_duration = true;
                            }

                            if (isCostChanged) {
                                targetService.labor_cost = parseFloat(laborCost);
                                targetService.is_custom_labor = true;
                            }

                        }
                        const tr = $(`tr[data-id="${ActiveRowId}"]`);
                        const row = table.row(tr);

                        // Force close first if open
                        if (row.child.isShown()) {
                            row.child.hide();
                            tr.removeClass('shown');
                        }

                        // Redraw row 
                        row.invalidate().draw(false);

                        // Delay open to ensure DOM is ready
                        setTimeout(() => {
                            tr.find('.dt-control').click();
                        }, 150);
                        // console.log(request_servicesInfo);
                        populateServicesTable(request_servicesInfo);
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: 'Service Updated',
                            text: response.message
                        }).then(() => {

                            $('#manageServicesModal').modal('show');
                        });

                    } else if (response.status === 'no_changes') {
                        $('#editServiceModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-info-circle"></i>',
                            title: 'No Changes Made',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            $('#editServiceModal').modal('show');
                        });
                    } else {
                        $('#editServiceModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Update Failed',
                            text: response.message || 'Something went wrong while updating the service.',
                        }).then(() => {
                            $('#editServiceModal').modal('show');
                        });
                    }
                },
                error: function(err) {
                    $('#editServiceModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error',
                        text: 'Something went wrong'
                    }).then(() => {
                        $('#editServiceModal').modal('show');
                    });
                    console.error('AJAX error:', err);
                }
            });
        });



        const newSched_fp = flatpickr("#newSched", {
            enableTime: true,
            time_24hr: false,
            dateFormat: "l, F j, Y at h:i K",
            minDate: "today",
            minTime: "07:00",
            maxTime: "18:00", // 7am to 6pm lang yung pasok sa shop
            allowInput: false,
            disable: [
                function(date) {
                    // Disable Sundays and Saturdays : para sa dayoffs, kung meron
                    return date.getDay() === 0 || date.getDay() === 6;
                }
            ]
        });


        $(document).on('change', '#resched-reason', function() {
            if ($(this).val() === 'Other') {
                $('#custom-reason-group').show();
                $('#custom-reason').prop('required', true);
            } else {
                $('#custom-reason-group').hide();
                $('#custom-reason').val('').prop('required', false);
            }
        });


        $(document).on('click', '#reschedbtn', function() {

            const selectedDate = newSched_fp.selectedDates[0]; // Date object

            if (!(selectedDate instanceof Date)) {
                $('#reschedModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Missing New Schedule',
                    text: 'Please select a new schedule date and time.',
                }).then((result) => {
                    $('#reschedModal').modal('show');
                });
                return;
            }

            const newSched = newSched_fp.formatDate(selectedDate, 'Y-m-d H:i:S');
            
            const selectedReason = $('#resched-reason').val();
            const customReasonVisible = $('#custom-reason-group').is(':visible');
            const customReason = $('#custom-reason').val().trim();

            const reason = (selectedReason === 'Other' && customReasonVisible && customReason)
            ? customReason
            : selectedReason;

            // Validate reason
            if (reason === '') {
                $('#reschedModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Missing Reason',
                    text: 'Please select a reason for rescheduling.',
                }).then((result) => {
                    $('#reschedModal').modal('show');
                });
                return;
            }

            // Validate custom reason if 'other' is selected
            if (reason === 'other' && customReason === '') {
                $('#reschedModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Missing Custom Reason',
                    text: 'Please specify your reason for rescheduling.',
                }).then((result) => {
                    $('#reschedModal').modal('show');
                });
                return;
            }


            // console.log('Proceeding with reschedule...');
            // console.log('New Schedule:', newSched);
            // console.log('Reason:', reason === 'other' ? customReason : reason);


            $.ajax({
                url: 'handlers/serviceRequests-handler.php',
                method: 'POST',
                data: {
                    request_id: ActiveRowId,
                    new_schedule: newSched,
                    resched_reason: reason === 'other' ? customReason : reason,
                    action: 'updateSchedule'
                },
                success: function(response) {
                    $('#reschedModal').modal('hide');

                    if (response.status === 'error') {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Rescheduling Failed',
                            text: response.message || 'An unexpected error occurred.'
                        }).then((result) => {
                            $('#reschedModal').modal('show');
                        });
                        return;
                    }


                    const tr = $(`tr[data-id="${ActiveRowId}"]`);
                    const row = table.row(tr);

                    // Force close first if open
                    if (row.child.isShown()) {
                        row.child.hide();
                        tr.removeClass('shown');
                    }

                    // Redraw row 
                    row.invalidate().draw(false);

                    // Delay open to ensure DOM is ready
                    setTimeout(() => {
                        tr.find('.dt-control').click();
                    }, 150);

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Reschuled Successfully',
                        text: response.message || 'Schedule has been updated successfully.'
                    });

                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error',
                        text: 'Something went wrong. Please check the console.'
                    });
                }
            });
        });


        $(document).on('click', '#resched-btn', function() {
            document.getElementById('newSched').value = null;
            document.getElementById('resched-reason').value = '';

            const proceedReschedule = () => {
                const displayDate = formatScheduleTime(request_Details.sched_dt || request_Details.request_dt);

                document.getElementById('og-sched').value = displayDate;
                $('#reschedModal').modal('show');
                customReason();
            };

            if (request_Details.status !== 'Pending') {
                if (request_Details.status === 'Approved') {
                    Swal.fire({
                        iconHtml: '<i class="bx bx-info-circle"></i>',
                        title: 'Reschedule Approved Request?',
                        html: 'This request has already been approved.<br><br><i>Rescheduling is allowed, but not recommended unless necessary.</i>',
                        showCancelButton: true,
                        confirmButtonText: 'Continue',
                        cancelButtonText: 'Cancel',
                    }).then(result => {
                        if (result.isConfirmed) {
                            proceedReschedule();
                        }
                    });
                } else {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-error-circle"></i>',
                        title: 'Reschedule Not Allowed',
                        html: 'Only pending/approved requests can be rescheduled.<br>',
                    });
                }
            } else {
                proceedReschedule();
            }
        });








        // BACK TO TOP BUTTON FUNCTION
        const goTopButton = document.getElementById('goTop');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 400) {
                goTopButton.classList.add('show');
            } else {
                goTopButton.classList.remove('show');
            }
        });

        goTopButton.addEventListener('click', (e) => {
            e.preventDefault(); // prevent navigation
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>

    <!-- ADDING REQUEST LOGIC  -->
    <script src="JS/addRequest.js"></script>
</body>

</html>