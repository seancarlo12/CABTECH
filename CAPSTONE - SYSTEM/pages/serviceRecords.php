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
    <title>Service Records</title>
    <link rel="stylesheet" href="style/serviceRecords.css">


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
                type: 'text'
            },
            {
                type: 'multi-select',
                options: ['In Progress', 'Completed', 'Invoice Issued']
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
            ]
        });


        // 1. Clear all DataTables search filters
        table.search('').columns().search('').draw(false);

        // 2. Clear all filter inputs and selects in the filter row
        $('#myTable thead tr.filters th').each(function() {
            $(this).find('input, select').val('');
        });


        $('#addServiceRequest-6').on('shown.bs.modal', function() {
            initTooltip(); // Bootstrap 4
            // or $('[data-bs-toggle="tooltip"]').tooltip(); // Bootstrap 5
        });


        let ActiveRowId = null;
        let RequestId = null;
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


        // Function to fetch row data
        function fetchRowData(rowId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'handlers/serviceRecords-handler.php',
                    method: 'POST',
                    data: {
                        row_id: rowId,
                        action: 'getRowData'
                    },
                    success: function(response) {
                        if (!response || response.request === null) {
                            reject(response.error || 'Invalid request data.');
                            return;
                        }

                        // Resolve only the data
                        resolve({
                            request_Details: response.request,
                            request_servicesInfo: response.services,
                            record_Details: response.record,
                            request_servicesDetails: response.requested_services, //comments
                            request_Client: response.client,
                            request_Vehicle: response.vehicle,
                            mechanics_assigned: response.mechanics_assigned,
                            items_needed: response.items_needed,
                            service_invoice: response.invoice
                        });
                    },
                    error: function(err) {
                        reject(err);
                    }
                });
            });
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
            RequestId = rowData[2];
            // const requestId = $(rowElement).data('request-id');
            localStorage.setItem('RowId', ActiveRowId);

            fetchRowData(ActiveRowId)
                .then(data => {
                    // Assign global variables if needed
                    request_Details = data.request_Details;
                    request_servicesInfo = data.request_servicesInfo;
                    record_Details = data.record_Details;
                    request_servicesDetails = data.request_servicesDetails;
                    request_Client = data.request_Client;
                    request_Vehicle = data.request_Vehicle;
                    mechanics_assigned = data.mechanics_assigned;
                    items_needed = data.items_needed;
                    service_invoice = data.service_invoice;
                    console.log('invoice:', service_invoice);
                    // You can now call your DOM/UI update functions here
                    row.child(format(rowData)).show();
                    tr.classList.add('shown', 'active-row');
                    activeRow = tr;

                    initializeItemSelect2();
                    initTooltip();
                    saveExpandedRows();
                })
                .catch(err => {
                    Swal.fire({
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error',
                        text: err
                    });
                    console.error('AJAX error:', err);
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
                RequestId = null;
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
                record_Details = null;
                request_servicesDetails = null;
                request_servicesInfo = null;
                request_Client = null;
                request_Vehicle = null;
                mechanics_assigned = null;
                items_needed = null;
                service_invoice = null;

                return;
            }

            // Step 4: Activate current row
            $('tr.active-row').removeClass('active-row');
            tr.classList.add('active-row');

            // Step 5: Store values
            ActiveRowId = rowData[1];
            RequestId = rowData[2];
            activeRow = tr;
            localStorage.setItem('RowId', ActiveRowId);


            fetchRowData(ActiveRowId)
                .then(data => {
                    // Assign global variables if needed
                    // request_Details = data.request_Details;
                    // record_Details = data.record_Details;
                    // request_servicesDetails = data.request_servicesDetails;
                    request_servicesInfo = data.request_servicesInfo;
                    request_Client = data.request_Client;
                    request_Vehicle = data.request_Vehicle;
                    mechanics_assigned = data.mechanics_assigned;
                    items_needed = data.items_needed;
                    service_invoice = data.service_invoice;
                    record_Details = data.record_Details;

                })
                .catch(err => {
                    Swal.fire({
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error',
                        text: err
                    });
                    console.error('AJAX error:', err);
                });
        }


        const formatDate = (dateInput) => {
            const date = new Date(dateInput); // ensures it's a Date object
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        };

        const formatTime = (dateInput) => {
            const date = new Date(dateInput);
            if (isNaN(date.getTime())) return 'Invalid time'; // Safe fallback
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
        };

        window.format = function(rowData) {


            const serviceInfo = request_servicesInfo;
            const requested_servicestbl = request_servicesDetails;
            const record = record_Details;
            const vehicle = request_Vehicle;
            const client = request_Client;
            const request = request_Details;
            const mechanics = mechanics_assigned;
            const needed = items_needed;

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

            function formatDateTime(dateStr) {
                if (!dateStr) return '<i>Not available</i>';
                const d = new Date(dateStr);
                return d.toLocaleString('en-US', {
                    timeZone: 'Asia/Manila',
                    hour12: true
                });
            }

            // Estimate end time by adding total duration to started_dt
            let estimatedFinishTime = '<i>Not available</i>';
            if (record.started_dt) {
                const startTime = new Date(record.started_dt);
                const finishTime = new Date(startTime.getTime() + totalDurationMinutes * 60000);
                estimatedFinishTime = finishTime.toLocaleString('en-US', {
                    timeZone: 'Asia/Manila',
                    hour12: true
                });
            }

            // Map for timeline display
            const timelineMap = {
                sched: request.request_type !== 'Walk-In' ?
                    `<b>Appointment</b><br> <span> ${formatDate(request.request_dt)} - ${formatTime(request.request_dt)}</span>` : '<b>Appointment</b><br> <span>Walk-In Service</span>',
                start: `<b>Started</b><br> <span> ${formatDate(record.started_dt)} - ${formatTime(record.started_dt)}`,
                est: `<b>Est. Finish</b><br> <span> ${formatDate(estimatedFinishTime)} - ${formatTime(estimatedFinishTime)}`,
                done: record.completion_dt ?
                    `<b>Completed</b><br> <span> ${formatDate(record.completion_dt)} - ${formatTime(record.completion_dt)}` : '<b>Completed</b><br> <span> Not yet completed'
            };

            setTimeout(() => {
                const $buttons = $('.dot-btn');

                $buttons.on('click', function() {
                    const type = $(this).data('type');
                    $buttons.removeClass('active');
                    $(this).addClass('active');
                    $('#timeline-time').html(` ${timelineMap[type]}`);
                });

                // Automatically click
                $buttons.eq(1).addClass('active').trigger('click');
            }, 0);


            setTimeout(() => {
                const mechBox = document.getElementById("mech-box");
                mechBox.innerHTML = ""; // clear before adding

                if (mechanics && mechanics.length > 0) {
                    mechanics.forEach(mech => {
                        mechBox.innerHTML += `
                        <button class="btn-sm mb-1 btn-secondary">
                            ${mech.last_name}, ${mech.first_name}
                        </button>
                    `;
                    });
                } else {
                    mechBox.innerHTML = "<small>No mechanics assigned.</small>";
                }
            }, 0);



            // ✅ Calculate totals consistently (round per line item)
            let itemsTotal = 0;
            let laborTotal = 0;
            let miscTotal = 0;

            // --- Service items + labor ---
            serviceInfo.forEach(service => {
                // Labor cost (already a whole amount, but round for consistency)
                const labor = parseFloat(service.labor_cost) || 0;
                laborTotal += +(labor.toFixed(2));

                // Items under this service
                if (Array.isArray(service.itemsNeeded)) {
                    service.itemsNeeded.forEach(item => {
                        const price = parseFloat(item.record_price) || 0;
                        const qty = parseFloat(item.quantity) || 0;

                        // Only include if it's not a misc item
                        if (item.rst_id !== 0) {
                            const lineTotal = +(price * qty).toFixed(2); // round per line item
                            itemsTotal += lineTotal;
                        }
                    });
                }
            });

            // --- Miscellaneous items (rst_id == 0) ---
            needed
                .filter(item => item.rst_id === 0)
                .forEach(item => {
                    const price = parseFloat(item.record_price) || 0;
                    const qty = parseFloat(item.quantity) || 0;
                    const lineTotal = +(price * qty).toFixed(2); // round per line item
                    miscTotal += lineTotal;
                });

            // --- Grand total ---
            const grandTotal = +(itemsTotal + laborTotal + miscTotal).toFixed(2);






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
            $(document).ready(function() {
                // Make sure the first service is selected by default
                const firstBtn = $('#services-box .service-btn').first();
                if (firstBtn.length) {
                    firstBtn.trigger('click'); // this runs your existing click handler
                }
            });
            return `
            <div id="details-row">

                
                <div class="second-col column">
                    <div id="timeline-block">
                    <b>Service Timeline</b>
                        <div class="timeline-dots">
                            <button class="dot-btn" data-type="sched" title="Scheduled">
                                <i class='bx bx-calendar-plus'></i>
                            </button><i class='bx bx-chevron-right' ></i>
                            <button class="dot-btn" data-type="start" title="Started">
                                <i class='bx bx-play-circle'></i>
                            </button><i class='bx bx-chevron-right' ></i>
                            <button class="dot-btn" data-type="est" title="Estimated Finish">
                                <i class='bx bx-time-five'></i>
                            </button><i class='bx bx-chevron-right' ></i>
                            <button class="dot-btn" data-type="done" title="Completed">
                                <i class='bx bx-check-circle'></i>
                            </button>
                        </div>
                        <p id="timeline-time"><i class="bx bx-time"></i> Click a dot to view time.</p>
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
                    <div id="assigned-mechanics"> 
                        <b>Mechanic/s Assigned</b> 

                        <?php if ($_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Admin'): ?>
                            ${service_invoice.status === "Draft" 
                            ? `<button id="manageAssignedMech"><i class="bx bx-user"></i><p>Manage</p></button><br>` 
                            : ""}
                        <?php endif; ?>

                        <div id="mech-box">
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

                    <div id="chosen-services"> 
                        
                        <b>(${requested_servicestbl.length}) Services and Items</b> 
                        <?php if ($_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Admin' || $_SESSION['User_role'] === 'Mechanic'): ?>
                        ${service_invoice.status === "Draft" 
                        ? `<button id="manageServicesbtn"><i class='bx bx-food-menu'></i><p>Manage</p></button>` 
                        : ""}
                        <?php endif; ?>
                        <br>
                        <div id="services-box">
                            ${requested_servicestbl.map((service, index) => `
                                <button class="choices service-btn${index === 0 ? ' active' : ''}" data-rst-id="${service.rst_id}">
                                    ${serviceInfo[index].service_name}
                                </button>
                            `).join('')}
                        </div>
                    </div>

                    <div id="items-used">
                        <b>Summarized Total</b>
                        <table class="table table-borderless table-sm">
                            <tbody>
                                <tr>
                                    <td>Items Used:</td>
                                    <td class="text-end">
                                        ₱ ${(itemsTotal + miscTotal).toLocaleString('en-US', {
                                            minimumFractionDigits: 2
                                        })}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Services Labor:</td>
                                    <td class="text-end">
                                        ₱ ${laborTotal.toLocaleString('en-US', {
                                            minimumFractionDigits: 2
                                        })}
                                    </td>
                                </tr>
                                    <tr>
                                    <td colspan="2" class="pt-3 border-top"></td>
                                    </tr>
                                <tr>
                                    <td><b>GRAND TOTAL:</b></td>
                                    <td class="text-end fw-bold">
                                        ₱ ${grandTotal.toLocaleString('en-US', {
                                            minimumFractionDigits: 2
                                        })}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="fifth-col column">
                
                    <div id="client-comments">
                        <b>Client's Comments/Notes <span id="selected-service-name"> - ${requested_servicestbl[0].service_name}</span></b>
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







        // Row click event handler
        document.querySelector('#myTable tbody').addEventListener('click', function(e) {
            const td = e.target.closest('td.dt-control');
            if (!td) return;
            const tr = td.closest('tr');
            toggleRow(tr);
        });

        function unsetActiveRow() {
            const tr = document.querySelector('.active-row');
            if (tr) {
                tr.classList.remove('active-row');
            }
            localStorage.setItem('activeRowIndex', null);
            localStorage.removeItem('RowId');
            ActiveRowId = null;
            RequestId = null;
            activeRow = null;
            closeRow(tr);
        }
        $('#myTable').on('order.dt page.dt search.dt length.dt', function() {
            unsetActiveRow();
        });


        loadExpandedRows();
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i>
            <span>
                service records
                <?php if ($_SESSION['User_role'] === 'Mechanic'): ?>
                    – <u>only your <b>assigned records</b></u>
                <?php endif; ?>
            </span>
        </p>
        <div id="table-box"><i class="bx bx-help-circle" title="help" id="helpicon"></i>

            <div id="buttons">
                <!-- <button id="itembtn"><i class='bx bx-notepad'></i><span>Manage Items Used</span></button> -->
                <button id="completebtn"><i class='bx bx-receipt' style="color: green;"></i>
                    <span>
                        <span>
                            <?php if ($_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Admin'): ?>
                                Complete Service & Invoice
                            <?php elseif ($_SESSION['User_role'] === 'Mechanic'): ?>
                                View Invoice
                            <?php else: ?>
                                View Invoice
                            <?php endif; ?>
                        </span>
                </button>

            </div>
            <div id="mesa">
                <!-- TABLE USES DATABLES LIBRARY -->
                <table id="myTable">

                    <thead>
                        <tr class="column-heads">
                            <th></th>
                            <th>Record ID</th>
                            <th>Request ID</th>
                            <th>Record Status</th>
                            <th>Client Vehicle <span id="sub" class="text-capitalize text-muted">(Plate Number | Color | Make&Model)</span></th>
                            <th>Client Name</th>
                            <th>Services</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        date_default_timezone_set('Asia/Manila');

                        $userRole = $_SESSION['User_role'];
                        $userId   = $_SESSION['User_id']; // mechanic’s ID from session

                        // Base query
                        $query = "
                            SELECT 
                                r.record_id, r.request_id, r.record_status, r.started_dt,
                                rq.request_type, rq.status AS request_status, 
                                rq.client_id, rq.vehicle_id
                            FROM recordstbl r
                            JOIN requeststbl rq ON r.request_id = rq.request_id
                        ";

                        // ✅ If role is mechanic, join assigned_mechanicstbl and filter by user_id
                        if ($userRole === 'Mechanic') {
                            $query .= "
                                JOIN assigned_mechanicstbl am ON r.record_id = am.record_id
                                WHERE am.user_id = '$userId'
                            ";
                        }

                        $rs = mysqli_query($db_connection, $query);



                        while ($rw = mysqli_fetch_assoc($rs)) {
                            $request_id = $rw['request_id'];
                            $client_id = $rw['client_id'];
                            $vehicle_id = $rw['vehicle_id'];
                            $startedDt = $rw['started_dt'];

                            date_default_timezone_set('Asia/Manila');


                            // Get client name
                            $client_sql = mysqli_query($db_connection, "SELECT * FROM clientstbl WHERE client_id = '$client_id'");
                            $client = mysqli_fetch_assoc($client_sql);
                            $client_name = $client['last_name'] . ', ' . $client['first_name'];

                            // Get vehicle info
                            $vehicle_sql = mysqli_query($db_connection, "SELECT * FROM vehiclestbl WHERE vehicle_id = '$vehicle_id'");
                            $vehicle = mysqli_fetch_assoc($vehicle_sql);
                            $plate = !empty($vehicle['plate_number'])
                                ? $vehicle['plate_number']
                                : '<small style="color:gray;">Not Provided</small>';

                            $vehicle_info = $plate . ' | ' . $vehicle['color'] . ' | <b>' . $vehicle['make'] . ' ' . $vehicle['model'] . '</b>';

                            // Get services acquired
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

                            // Output row
                            echo '
                                <tr class="data-rows" data-id="' . $rw['record_id'] . '" oncontextmenu="toggleRow(this); return false;" onclick="setActiveRow(this);">
                                    <td class="dt-control"><i class="bx bx-chevron-down"></i></td>
                                    <td id="aydis">' . $rw['record_id'] . '</td>
                                    <td data-role="request_id">' . $rw['request_id'] . '</td>
                                    <td data-role="record_status">' . $rw['record_status'] . '</td>
                                    <td data-role="client_vehicle" class="ellipsis-tooltip">' . $vehicle_info . '</td>
                                    <td data-role="client_name" class="ellipsis-tooltip">' . $client_name . '</td>
                                    <td data-role="services" class="ellipsis-tooltip">' . $services_display . '</td>
                                </tr>
                            ';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>







        <!-- BACK TO TOP BUTTON -->
        <a href="#top" title="Go up"><i class='bx bx-chevron-up' id="goTop"></i></a>
    </div>

    <!-- MODALS  -->

    <div class="offcanvas offcanvas-end" data-bs-focus="false" tabindex="-1" id="offCanvasManageItems" aria-labelledby="offCanvasManageItemsLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offCanvasManageItemsLabel">manage service items</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="card" id="itemsSum">
                <div class="card-header">
                    <div>
                        <span>Request #:</span> <span class="headerData request-id"><!-- display req id --></span> <br>
                        <span>Client: </span><span class="headerData client-name"><!-- display client name --></span>
                    </div>
                    <b id="neededheader">Items Needed/Used for:</b>
                    <h2 id="service-name-head"></h2>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item <span class="item-count"></span></th>
                                <th style="width:30%;">Quantity</th>
                                <th style="text-align: right;">Price (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>

                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-end col-total-price"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <hr>
            <div id="itemslistBox">
                <h5>Inventory</h5>
                <div class="d-flex gap-2 mb-2">
                    <input type="search" id="searchItems" class="form-control" placeholder="Search for Items">
                    <div class="btn-group" role="group" id="filterButtons">
                        <button class="btn btn-sm btn-outline-dark active" data-type="all">All</button>
                        <button class="btn btn-sm btn-outline-dark" data-type="part">Part</button>
                        <button class="btn btn-sm btn-outline-dark" data-type="consumable">Consumable</button>
                        <button class="btn btn-sm btn-outline-dark" data-type="product">Product</button>
                    </div>
                </div>
                <div id="itemsList">
                    <!-- inventory items load here -->
                </div>
            </div>
        </div>
    </div>

    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="ServiceInvoiceModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">Complete Service & Invoice</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="modal-body">

                    <div id="left-details">

                        <div id="invoice-details">
                            <b class="head-details">Invoice Details</b>
                            <p class="ellipsis-tooltip"><b>Invoice No: </b><span class="inv-no"> 25</span></p>
                            <p class="ellipsis-tooltip"><b>Status: </b><span class="inv-status"> Draft</span></p>
                            <p class="ellipsis-tooltip"><b>Issued: </b><br><span class="inv-issueDate"> Not yet completed</span></p>
                        </div>
                        <!-- <hr> -->
                        <div id="inv-client-info">
                            <b class="head-details">Client information</b>
                            <p class="ellipsis-tooltip inv-c-name">Tolentino, Sean Carlo</p>
                            <p class="ellipsis-tooltip inv-c-email">seant@gmail.com</p>
                            <p class="ellipsis-tooltip inv-c-number">09837878767</p>
                            <p class="ellipsis-tooltip address-only inv-c-address">Cabanatuan City Padre Crisostomo</p>
                        </div>

                        <div id="inv-vehicle-info">
                            <b class="head-details">Vehicle Information</b>
                            <p class="ellipsis-tooltip"><b>Model/Make: </b><span class="inv-v-model-make"> Toyota Corrola </span></p>
                            <p class="ellipsis-tooltip"><b>Plate Number: </b><span class="inv-v-plate"> ABC-123</span></p>
                            <p class="ellipsis-tooltip"><b>Color: </b><span class="inv-v-color"> Black</span></p>
                            <p class="ellipsis-tooltip"><b>Transmission: </b><span class="inv-v-transmission"> Manual</span></p>
                            <p class="ellipsis-tooltip"><b>Fuel Type: </b><span class="inv-v-fuel"> Gasoline</span></p>
                        </div>

                        <div id="inv-assigned-m">
                            <b class="head-details">Assigned Mechanic/s</b>
                            <p class="ellipsis-tooltip"><span class="inv-m-name">Gojar, Micahel Angelo</span></p>
                        </div>

                    </div>

                    <div id="right-invoicebox">
                        <table id="invoiceTable" cellspacing="0" cellpadding="8" width="100%">
                            <thead>
                                <tr>
                                    <th class="servicesAndItems">SERVICES & ITEMS</th>
                                    <th>PRICE (₱)</th>
                                    <th>QTY</th>
                                    <th>TOTAL (₱)</th>
                                </tr>
                            </thead>
                            <tbody>

                                <!-- this is where invioce will sow completebtn -->

                                <!-- Footer total -->
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right; font-size: 1.1em;"><b>Grand Total</b></td>
                                    <td style="font-size: 1.1em;"><b>₱0.00</b></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>



                    <?php if ($_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Admin'): ?>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                            <button id="printInvoice" class="btn btn-primary"><i class='bx bx-printer'></i>Print Invoice</button>
                            <button id="completePaid" class="btn btn-primary"><i class='bx bx-badge-check' style="color: green;"></i>Complete / Paid</button>
                            <button id="issueInvoice" class="btn btn-primary"><i class="bx bx-receipt"></i>Issue Invoice</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- MANAGE SERVICES MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="manageServicesModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage requested services</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <button id="add-another-service" class="btn btn-primary">
                        <i class="bx bx-plus"></i><span> Add another Service</span>
                    </button>
                    <button id="misc-btn" class="btn btn-secondary">
                        <i class="bx bx-notepad"></i><span>Miscellaneous Items</span>
                    </button>
                </div>
                <div class="table-container">
                    <table id="editServicesTable">
                        <thead>
                            <tr>
                                <th>SERVICE NAME</th>
                                <th>ESTIMATED DURATION</th>
                                <th>LABOR COST</th>
                                <th id="action-col">ACTION</th>
                                <th>ITEMS</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-start mb-1">
                    <p class="text-muted mb-0">
                        Values with "<b><span style="color:red">*</span></b>" indicate a custom service.
                    </p>
                </div>

                <!-- SUMMARY SECTION -->
                <div id="services-summary" class="mt-3 px-5">
                    <table class="table text-end">
                        <tbody>
                            <tr>
                                <td class="text-start">Items Total</td>
                                <td id="items-total" class="text-end">₱ 0.00</td>
                            </tr>
                            <tr>
                                <td class="text-start">Service Total</td>
                                <td id="labor-total" class="text-end">₱ 0.00</td>
                            </tr>
                            <tr class="fw-bold">
                                <td class="text-start">Grand Total</td>
                                <td id="grand-total" class="text-end">₱ 0.00</td>
                            </tr>
                        </tbody>
                    </table>
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

    <!-- Manage Assigned Mechanics Modal -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="manageMechAssignedModal" aria-hidden="true" aria-labelledby="manageMechAssignedLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="manageMechAssignedLabel">Manage Mechanics Assigned to Service</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <!-- Left: all mechanics (checkbox list) -->
                        <div id="all-mechanics" class="col-6">
                            <h6>All Mechanics</h6>
                            <input type="text" id="mechanicSearch" class="form-control mb-2" placeholder="Search mechanics...">
                            <div id="allMechanicsList" style="max-height: 320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                Loading...
                            </div>
                        </div>

                        <!-- Right: selected mechanics -->
                        <div class="col-6">
                            <h6>Assigned Mechanics</h6>
                            <ul id="selectedMechanicsList" style="list-style:none; padding-left:0; max-height:320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                <!-- populated by JS -->
                            </ul>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button id="confirmAssignedMech" class="btn btn-primary">
                            <i class="bx bx-save"></i> Confirm Assigned Mechanics
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>




    <script>
        $(document).on('click', '#manageAssignedMech', function() {
            // 🧹 Clear previous UI state
            $('#mechanicSearch').val('');
            $('#allMechanicsList').html('Loading...');


            $.ajax({
                url: 'handlers/serviceRecords-handler.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'getAllMech',
                    record_id: ActiveRowId
                },
                success: function(response) {
                    if (response.success) {
                        renderMechanicsList(response.data);
                        $('#manageMechAssignedModal').modal('show');
                    } else {
                        $('#allMechanicsList').html('<p class="text-muted">No active mechanics found.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#allMechanicsList').html('<p class="text-danger">Error loading mechanics.</p>');
                    console.error(error);
                }
            });
        });


        // 🔹 Render list of checkboxes
        function renderMechanicsList(mechanics) {
            // 🧹 Clear both panels before rendering new data
            $('#allMechanicsList').empty();
            $('#selectedMechanicsList').empty();
            let html = '';
            mechanics.forEach(m => {
                const checked = m.assigned ? 'checked' : '';
                html += `
            <div class="form-check">
                <input class="form-check-input mechanic-checkbox" type="checkbox" id="mech_${m.user_id}" data-id="${m.user_id}" data-name="${m.name}" ${checked}>
                <label class="form-check-label" for="mech_${m.user_id}">${m.name}</label>
            </div>
        `;

                // If already assigned, show in right panel immediately
                if (m.assigned) {
                    $('#selectedMechanicsList').append(`
                <li id="selected_${m.user_id}">
                    ${m.name}
                </li>
            `);
                }
            });
            $('#allMechanicsList').html(html);
        }

        // 🔹 When checkbox is toggled
        $(document).on('change', '.mechanic-checkbox', function() {
            const mechId = $(this).data('id');
            const mechName = $(this).data('name');

            if ($(this).is(':checked')) {
                // Add to selected list
                $('#selectedMechanicsList').append(`
                <li id="selected_${mechId}">
                    ${mechName}
                </li>
            `);
            } else {
                // Remove from selected list
                $(`#selected_${mechId}`).remove();
            }
        });

        // 🔹 Search mechanics
        $('#mechanicSearch').on('keyup', function() {
            const term = $(this).val().toLowerCase();
            $('#allMechanicsList .form-check').each(function() {
                const name = $(this).find('label').text().toLowerCase();
                $(this).toggle(name.includes(term));
            });
        });


        // 🔹 Confirm and save assigned mechanics
        $(document).on('click', '#confirmAssignedMech', function() {
            const record_id = ActiveRowId; // ensure this holds the active record ID
            const selectedMechanics = [];

            // Collect all checked mechanics
            $('.mechanic-checkbox:checked').each(function() {
                selectedMechanics.push($(this).data('id'));
            });

            if (selectedMechanics.length === 0) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'No Mechanics Selected',
                    text: 'Please select at least one mechanic before saving.',
                });
                return;
            }

            $.ajax({
                url: 'handlers/serviceRecords-handler.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'saveAssignedMech',
                    record_id: record_id,
                    mechanics: selectedMechanics
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: "Updated successfully",
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
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
                        $('#manageMechAssignedModal').modal('hide');
                    } else {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Failed',
                            text: response.message,
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error',
                        text: 'An unexpected error occurred while saving.',
                    });
                }
            });
        });










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

        $('#printInvoice').click(function() {
            Swal.fire({
                iconHtml: "<i class='bx bxs-file-pdf'></i>",
                title: "Processing...",
                text: "Please wait while the invoice is being generated.",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            $.ajax({
                url: "pages/invoicePdf.php",
                method: "POST",
                data: {
                    action: "PrintInvoice",
                    request_servicesInfo: JSON.stringify(request_servicesInfo),
                    items_needed: JSON.stringify(items_needed),
                    request_Client: JSON.stringify(request_Client),
                    request_Vehicle: JSON.stringify(request_Vehicle),
                    service_invoice: JSON.stringify(service_invoice),
                    mechanics_assigned: JSON.stringify(mechanics_assigned)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: "<i class='bx bx-check-circle'></i>",
                            title: "Invoice Completed",
                            text: "The invoice has been generated."
                        });
                        // Convert base64 to binary
                        const byteCharacters = atob(response.pdfBase64);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) {
                            byteNumbers[i] = byteCharacters.charCodeAt(i);
                        }
                        const byteArray = new Uint8Array(byteNumbers);

                        // Create a Blob
                        const blob = new Blob([byteArray], {
                            type: "application/pdf"
                        });

                        // Create a URL for the Blob
                        const blobUrl = URL.createObjectURL(blob);

                        // Open PDF in a new tab
                        window.open(blobUrl, "_blank");

                        // Optional: trigger download with service invoice number as filename
                        // const a = document.createElement("a");
                        // a.href = blobUrl;
                        // a.download = `Invoice_${service_invoice.invoice_id}.pdf`; // sets filename
                        // document.body.appendChild(a);
                        // a.click();
                        // document.body.removeChild(a);

                        // Clean up memory after a short delay
                        setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);

                    } else {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: "<i class='bx bx-x-circle'></i>",
                            title: "Error",
                            text: response.message || "Something went wrong."
                        });
                    }
                },
                error: function(er) {
                    console.log(er);
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: "<i class='bx bx-x-circle'></i>",
                        title: "Error",
                        text: "AJAX request failed."
                    });
                }
            });
        });

        $(document).on("click", "#misc-btn", function() {

            rstId = 0;
            console.log("rst_id:", rstId);
            $('#manageServicesModal').modal('hide');
            let offcanvas = new bootstrap.Offcanvas(document.getElementById('offCanvasManageItems'));
            offcanvas.show();


            $("#searchItems").val('');
            $("#filterButtons button").removeClass("active");
            $("#filterButtons button[data-type='all']").addClass("active");

            // Clear search
            $("#searchItems").val("");

            $('.request-id').text(request_Details.request_id);
            $('.client-name').text(request_Client.last_name + ', ' + request_Client.first_name);
            ManageItemsData(rstId);

        });

        $(document).on("click", "#issueInvoice", function() {
            let invoiceId = service_invoice.invoice_id;
            let grandTotal = $("#invoiceTable").data("grandTotal") || 0;
            let itemsTotal = $("#invoiceTable").data("itemsTotal") || 0;
            let servicesTotal = $("#invoiceTable").data("servicesTotal") || 0;
            let requestId = parseInt($(`#myTable tr[data-id="${ActiveRowId}"] td[data-role="request_id"]`).text(), 10);

            console.log(mechanics_assigned);

            Swal.fire({
                title: "Issue Invoice?",
                html: "The Service / Invoice will be <b>locked and finalized</b>, and no further changes will be possible until payment is made.",
                iconHtml: "<i class='bx bx-info-circle'></i>",
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Back',
            }).then((result) => {
                if (result.isConfirmed) {


                    // Show loading alert
                    Swal.fire({
                        iconHtml: '<i class="bx bx-loader-circle"></i>',
                        title: 'Processing...',
                        text: 'Processing invoice issuance and informing client...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });


                    $.ajax({
                        url: "handlers/serviceRecords-handler.php",
                        method: "POST",
                        data: {
                            action: "completeInvoice",
                            recordId: ActiveRowId,
                            requestId,
                            invoice_id: invoiceId,
                            items_total: itemsTotal,
                            services_total: servicesTotal,
                            grand_total: grandTotal,
                            mechanics_assigned: JSON.stringify(mechanics_assigned),
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-check-circle'></i>",
                                    title: "Invoice Completed",
                                    text: "The invoice has been updated successfully."
                                });

                                $(".inv-status").text("Issued");
                                $(".inv-issueDate").html(
                                    response.issued_dt ?
                                    formatDate(response.issued_dt) + "<br>" + formatTime(response.issued_dt) :
                                    "Not yet completed"
                                );
                                $("#completePaid, #printInvoice").prop("disabled", false);
                                $("#issueInvoice").prop("disabled", true);


                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                const rowObj = table.row(tr);

                                $(`tr[data-id="${ActiveRowId}"] td[data-role="record_status"]`).text('Invoice Issued');

                                rowObj.invalidate().draw(false); // Redraw the row

                                // Close if open
                                if (rowObj.child.isShown()) {
                                    rowObj.child.hide();
                                    tr.removeClass('shown');
                                }

                                // Reopen the expanded details
                                setTimeout(() => {
                                    tr.find('.dt-control').click();
                                }, 150);
                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-x-circle'></i>",
                                    title: "Error",
                                    text: response.message || "Something went wrong."
                                });
                            }
                        },
                        error: function(er) {
                            console.log(er);
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: "<i class='bx bx-x-circle'></i>",
                                title: "Error",
                                text: "AJAX request failed."
                            });
                        }
                    });
                }
            });


            // make the ajax to output the pd
            // $.ajax({
            //     url: "pages/invoicePdf.php",
            //     method: "POST",
            //     data: {
            //         action: "PrintInvoice",
            //         request_servicesInfo: JSON.stringify(request_servicesInfo),
            //         items_needed: JSON.stringify(items_needed),
            //         request_Client: JSON.stringify(request_Client),
            //         request_Vehicle: JSON.stringify(request_Vehicle),
            //         service_invoice: JSON.stringify(service_invoice),
            //         mechanics_assigned: JSON.stringify(mechanics_assigned)
            //     },






        });


        $(document).on("click", "#completePaid", function() {
            Swal.fire({
                title: "Finalizing Record and Invoice",
                html: `
            <div style="text-align:left">
                <p>Please confirm before completing:</p>
                <ul style="text-align:left; margin-left:20px;">
                    <li>Verify all service details</li>
                    <li>Verify all items and costs</li>
                    <li>Confirm customer has paid in full</li>
                </ul>
                <p style="color:red"><b>Once marked as Completed, no further changes will be possible.</b></p>
            </div>
        `,
                iconHtml: "<i class='bx bx-info-circle'></i>",
                showCancelButton: true,
                confirmButtonText: "Yes, Confirm",
                cancelButtonText: "Cancel",
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    let invoiceId = service_invoice.invoice_id;


                    // Show loading alert
                    Swal.fire({
                        iconHtml: '<i class="bx bx-loader-circle"></i>',
                        title: 'Processing...',
                        text: 'Finalizing invoice and updating service record...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: "handlers/serviceRecords-handler.php",
                        method: "POST",
                        data: {
                            action: "completePaid",
                            invoice_id: invoiceId,
                            mechanics_assigned: JSON.stringify(mechanics_assigned),
                            recordId: ActiveRowId
                        },
                        dataType: "json",
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-check-circle'></i>",
                                    title: "Invoice Paid, Service Completed",
                                    text: "The invoice has been marked as Paid and service has been completed."
                                });


                                $(`tr[data-id="${ActiveRowId}"] td[data-role="record_status"]`).text('Completed');
                                $("#completePaid").prop("disabled", true);

                                // update datatable row
                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                const rowObj = table.row(tr);
                                rowObj.invalidate().draw(false);
                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-x-circle'></i>",
                                    title: "Error",
                                    text: response.message || "Something went wrong."
                                });
                            }
                        },
                        error: function(xhr) {
                            console.log(xhr.responseText);
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: "<i class='bx bx-x-circle'></i>",
                                title: "Error",
                                text: "AJAX request failed."
                            });
                        }
                    });
                }
            });
        });







        $(document).on("click", "#completebtn", function() {

            if (checkSelection()) return;

            if (service_invoice.status === "Draft") {
                $("#printInvoice, #completePaid").prop("disabled", true);
                $("#issueInvoice").prop("disabled", false);
            } else {
                $("#printInvoice, #completePaid").prop("disabled", false);
                $("#issueInvoice").prop("disabled", true);
            }

            if (record_Details.record_status === "Completed") {
                $("#completePaid").prop("disabled", true);
            }
            // ---- Fill SERVICES & ITEMS header with counts ----
            const numServices = request_servicesInfo.length; // total services
            const numItems = items_needed.length; // total items (including misc)

            $("th.servicesAndItems").text(`(${numServices}) SERVICE/S AND (${numItems}) ITEM/S`);

            // ---- Fill Invoice Info ----
            $(".inv-no").text(service_invoice.invoice_id);
            $(".inv-status").text(service_invoice.status);
            $(".inv-issueDate").html(
                (service_invoice.issued_dt ?
                    formatDate(service_invoice.issued_dt) + "<br>" + formatTime(service_invoice.issued_dt) :
                    "Not yet completed")
            );

            // ---- Fill Client Info ----
            $(".inv-c-name").text(`${request_Client.last_name}, ${request_Client.first_name}`);
            $(".inv-c-email").text(request_Client.email);
            $(".inv-c-number").text(request_Client.contact_number);
            $(".inv-c-address").text(request_Client.address);

            // ---- Fill Vehicle Info ----
            $(".inv-v-model-make").text(`${request_Vehicle.make} ${request_Vehicle.model}`);
            $(".inv-v-plate").text(request_Vehicle.plate_number);
            $(".inv-v-color").text(request_Vehicle.color);
            $(".inv-v-transmission").text(request_Vehicle.transmission_type);
            $(".inv-v-fuel").text(request_Vehicle.fuel_type);

            // ---- Fill Mechanics ----
            let mechHtml = "";
            mechanics_assigned.forEach(m => {
                mechHtml += `<p class="ellipsis-tooltip"><span class="inv-m-name">${m.last_name}, ${m.first_name}</span></p>`;
            });
            $("#inv-assigned-m").html(`<b class="head-details">Assigned Mechanic/s</b>${mechHtml}`);

            let tbodyHtml = "";
            let miscItems = [];
            let grandTotal = 0;
            let servicesTotal = 0;
            let itemsTotal = 0;

            // Loop services
            request_servicesInfo.forEach(service => {
                let serviceSubtotal = 0; // reset per service

                tbodyHtml += `
        <tr class="inv-service-head">
            <td><b>${service.service_id === null ? '<span title="A Custom Service" style="color:red; cursor: default;"><b>* </b></span>' : ''}${service.service_name}</b></td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td class="inv-marleft">Labor</td>
            <td>₱${service.labor_cost.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
            <td>1</td>
            <td>₱${service.labor_cost.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        </tr>
    `;

                // add labor
                serviceSubtotal += parseFloat(service.labor_cost);
                servicesTotal += parseFloat(service.labor_cost);

                // Items for this service
                let serviceItems = items_needed.filter(itm => itm.rst_id === service.rst_id);
                serviceItems.forEach(itm => {
                    let itemTotal = itm.record_price * itm.quantity;
                    serviceSubtotal += itemTotal;
                    itemsTotal += itemTotal;

                    tbodyHtml += `
            <tr>
                <td class="inv-marleft">${itm.name}</td>
                <td>₱${itm.record_price.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                <td>${itm.quantity}</td>
                <td>₱${itemTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
            </tr>
        `;
                });

                // Subtotal per service
                tbodyHtml += `
        <tr class="subtotal">
            <td colspan="3" class="inv-subtotal"><b>Subtotal</b></td>
            <td><b>₱${serviceSubtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</b></td>
        </tr>
    `;

                grandTotal += serviceSubtotal;
            });

            // Miscellaneous (rst_id == 0)
            miscItems = items_needed.filter(itm => itm.rst_id === 0);
            if (miscItems.length > 0) {
                let miscTotal = 0;

                tbodyHtml += `
        <tr class="inv-service-head">
            <td><b>Miscellaneous</b></td>
            <td colspan="3"></td>
        </tr>
    `;

                miscItems.forEach(itm => {
                    let itemTotal = itm.record_price * itm.quantity;
                    miscTotal += itemTotal;
                    itemsTotal += itemTotal;

                    tbodyHtml += `
            <tr>
                <td class="inv-marleft">${itm.name}</td>
                <td>₱${itm.record_price.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                <td>${itm.quantity}</td>
                <td>₱${itemTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
            </tr>
        `;
                });

                tbodyHtml += `
        <tr class="subtotal">
            <td colspan="3" class="inv-subtotal"><b>Subtotal</b></td>
            <td><b>₱${miscTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</b></td>
        </tr>
    `;

                grandTotal += miscTotal;
            }

            // Final table
            $("#invoiceTable tbody").html(tbodyHtml);
            $("#invoiceTable tfoot").html(`
    <tr>
        <td colspan="3" class="inv-grand-txt"><b>Grand Total</b></td>
        <td class="inv-grand-val"><b>₱${grandTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</b></td>
    </tr>
`);

            // Save totals correctly
            $("#invoiceTable").data({
                grandTotal: grandTotal,
                itemsTotal: itemsTotal,
                servicesTotal: servicesTotal
            });

            $('#ServiceInvoiceModal').modal('show');
        });




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

        let editingRstId = null;

        function populateServicesTable(services) {

            updateServicesSummary(request_servicesInfo, items_needed);

            const tbody = document.querySelector("#editServicesTable tbody");
            tbody.innerHTML = ""; // Clear current rows

            services.forEach(service => {
                const durationText = formatDuration(service.estimated_duration);
                const laborText = formatPeso(service.labor_cost);

                // If custom, add a red * before the service name
                const serviceNameHTML = service.is_custom_service ?
                    `<b><span style="color:red">*</span></b> ${service.service_name}` :
                    service.service_name;

                const row = document.createElement("tr");
                row.innerHTML = `
            <td>${serviceNameHTML}</td>
            <td>${durationText}</td>
            <td>${laborText}</td>
            <td class="text-center">
                <button title="edit service" class="edit-service" data-id="${service.rst_id}">
                    <i class="bx bx-edit"></i>
                </button>
                <button title="delete service" class="delete-service" data-id="${service.rst_id}">
                    <i class="bx bx-trash"></i>
                </button>
            </td>
            <td>
                <button title="add item service" class="additem-service" data-id="${service.rst_id}">
                    <i class='bx bx-list-plus'></i>
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
                        html: `Are you sure you want to delete <b>${serviceName}</b> from <b>Request: ${ActiveRowId}</b>?<br><br>
                        <small>This will also remove all items used under this service.</small>`,
                        iconHtml: '<i class="bx bx-trash"></i>',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'handlers/serviceRecords-handler.php',
                                method: 'POST',
                                data: {
                                    action: 'deleteAssignedService',
                                    rst_id: editingRstId,
                                    recordId: ActiveRowId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    console.log("Delete response:", response);
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

        function updateServicesSummary(services, needed) {
            let itemsTotal = 0;
            let laborTotal = 0;

            // Sum service items and labor
            services.forEach(service => {
                if (Array.isArray(service.itemsNeeded)) {
                    itemsTotal += service.itemsNeeded.reduce((sum, item) => {
                        const price = parseFloat(item.record_price) || 0;
                        const qty = parseInt(item.quantity) || 0;

                        // Only count items belonging to a service (rst_id != 0)
                        return item.rst_id !== 0 ? sum + price * qty : sum;
                    }, 0);
                }

                laborTotal += parseFloat(service.labor_cost) || 0;
            });

            // Sum misc items (rst_id === 0)
            const miscTotal = needed
                .filter(item => item.rst_id === 0)
                .reduce((sum, item) => {
                    const price = parseFloat(item.record_price) || 0;
                    const qty = parseInt(item.quantity) || 0;
                    return sum + price * qty;
                }, 0);

            const grandTotal = itemsTotal + laborTotal + miscTotal;

            // Format numbers with commas and 2 decimals
            const formatPeso = amount => amount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Update the DOM
            document.getElementById("items-total").textContent = `₱ ${formatPeso(itemsTotal + miscTotal)}`; // include misc in items
            document.getElementById("labor-total").textContent = `₱ ${formatPeso(laborTotal)}`;
            document.getElementById("grand-total").textContent = `₱ ${formatPeso(grandTotal)}`;
        }


        $(document).on('click', '#manageServicesbtn', function() {

            populateServicesTable(request_servicesInfo);
            $('#manageServicesModal').modal('show');
        });


        $(document).on('click', '#saveAddedService', function() {
            const name = $('#add-service-name').val().trim();
            const costRaw = $('#add-service-cost').val().replace(/[₱,]/g, '').trim();
            const duration = $('#add-final-duration').val();
            const serviceComment = $('#add-comment').val();



            const labor_cost = parseFloat(costRaw || 0).toFixed(2);


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
                    request_id: RequestId,
                    recordId: ActiveRowId,
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
                    url: 'handlers/serviceRecords-handler.php',
                    method: 'POST',
                    dataType: 'json',
                    data: modifiedService,
                    success(response) {
                        $('#addServiceModal').modal('hide');
                        //REFRESH TABLE / REFRESH TOGGLEROW / UPDATE SHI


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
                request_id: RequestId,
                recordId: ActiveRowId,
                service_name: name,
                estimated_duration: duration,
                labor_cost: labor_cost,
                clients_comment: serviceComment,
                is_custom_duration: true,
                is_custom_labor: true,
                is_custom_service: true
            };

            $.ajax({
                url: 'handlers/serviceRecords-handler.php',
                method: 'POST',
                dataType: 'json',
                data: customService,
                success(response) {
                    $('#addServiceModal').modal('hide');


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


                            populateServicesTable(request_servicesInfo);
                            $('#manageServicesModal').modal('show');
                        });
                    } else {

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
                url: 'handlers/serviceRecords-handler.php',
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




            const updateData = {
                rst_id: editingRstId,
                service_name: serviceName,
                estimated_duration: finalDuration,
                labor_cost: laborCost,
                is_name_changed: isNameChanged,
                is_duration_changed: isDurationChanged,
                is_cost_changed: isCostChanged,
                action: "updateAssignedService",
                record_id: ActiveRowId
            };


            $.ajax({
                url: 'handlers/serviceRecords-handler.php',
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






        function formatDuration(timeStr) {
            if (!timeStr) return 'N/A';

            const parts = timeStr.split(':');
            const hours = parseInt(parts[0], 10);
            const minutes = parseInt(parts[1], 10);

            let result = [];
            if (hours > 0) result.push(hours + ' hour' + (hours > 1 ? 's' : ''));
            if (minutes > 0) result.push(minutes + ' minute' + (minutes > 1 ? 's' : ''));
            return result.join(' ');
        }


        function formatPeso(amount) {
            if (amount == null || isNaN(amount)) return '₱ 0.00';
            return Number(amount).toLocaleString('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            });
        }


























        let rstId = null;

        // When offcanvas is closed, show the manageServicesModal again
        $('#offCanvasManageItems').on('hidden.bs.offcanvas', function() {

            populateServicesTable(request_servicesInfo);
            $('#manageServicesModal').modal('show');
            rstId = null;
        });


        $(document).on("click", ".additem-service", function() {
            rstId = null;
            rstId = $(this).data("id"); // gets the value of data-id
            console.log("rst_id:", rstId);
            $('#manageServicesModal').modal('hide');
            let offcanvas = new bootstrap.Offcanvas(document.getElementById('offCanvasManageItems'));
            offcanvas.show();


            $("#searchItems").val('');
            $("#filterButtons button").removeClass("active");
            $("#filterButtons button[data-type='all']").addClass("active");

            // Clear search
            $("#searchItems").val("");

            $('.request-id').text(request_Details.request_id);
            $('.client-name').text(request_Client.last_name + ', ' + request_Client.first_name);
            ManageItemsData(rstId);


        });



        function ManageItemsData(rstId) {

            // Reset UI immediately
            $('.item-count').text("(0)");
            $('#service-name-head').text("");
            $('#itemsSum tbody').empty();
            $('#itemsSum tfoot th.col-total-price').text("₱ 0.00");
            $('#itemsList').empty();


            $.ajax({
                url: 'handlers/serviceRecords-handler.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'getItemsInventory',
                    record_id: ActiveRowId,
                    rst_id: rstId
                },
                success: function(response) {
                    if (response.status === 'success') {
                        console.log(response);

                        const itemsNeeded = response.itemsNeeded || [];
                        const inventoryItems = response.inventoryItems || [];
                        const serviceDetails = response.serviceInfo || {};
                        // Use service name from first item if available, otherwise from serviceDetails
                        const serviceName = serviceDetails.service_name || (itemsNeeded[0]?.service_name) || "Unknown Service";

                        $('#service-name-head').text(serviceName);

                        $('.item-count').text("(" + itemsNeeded.length + ")");
                        $('#service-name-head').text(serviceName);

                        // Clear existing rows in the items needed table
                        $('#itemsSum tbody').empty();

                        // Populate items needed table only if there are items
                        itemsNeeded.forEach(item => {
                            const rowTotal = (item.record_price * item.quantity).toLocaleString('en-US', {
                                minimumFractionDigits: 2
                            });
                            const row = `
                                <tr>
                                    <td class="itemnameBox ellipsis-tooltip">
                                        <button class="remove-btn"><i class='bx bx-minus-circle'></i></button>
                                        ${item.item_name}
                                    </td>
                                    <td>
                                        <input class="form-control qty-input" type="number" value="${item.quantity}" min="0" data-item-id="${item.item_id}">
                                    </td>
                                    <td class="text-end row-total-price">₱ ${rowTotal}</td>
                                </tr>
                            `;
                            $('#itemsSum tbody').append(row);
                        });

                        // ✅ Attach Swal confirmation on quantity change
                        $(document).off('change', '.qty-input').on('change', '.qty-input', function() {
                            const input = $(this);
                            const itemId = input.data('item-id');
                            const oldQty = parseInt(input.prop('defaultValue'), 10) || 0;
                            const newQty = parseInt(input.val(), 10) || 0;

                            // Decide if increase or decrease
                            let changeText = '';
                            if (newQty === oldQty || newQty === 0) {
                                input.val(oldQty); // reset if they try to zero it
                                return;
                            } else if (newQty > oldQty) {
                                changeText = `Increase quantity from ${oldQty} <i class='bx bxs-chevrons-right'></i> ${newQty}?`;
                            } else if (newQty < oldQty) {
                                changeText = `Decrease quantity from ${oldQty} <i class='bx bxs-chevrons-right'></i> ${newQty}?`;
                            } else {
                                return; // no change, no swal
                            }

                            Swal.fire({
                                iconHtml: '<i class="bx bx-help-circle"></i>',
                                title: 'Confirm Quantity Update',
                                html: changeText,
                                showCancelButton: true,
                                confirmButtonText: 'Yes, update',
                                cancelButtonText: 'Cancel',
                                target: document.getElementById('offCanvasManageItems')
                            }).then((result) => {
                                if (!result.isConfirmed) {
                                    // Revert value if cancelled
                                    input.val(oldQty);
                                } else {
                                    // Save new value as default
                                    input.prop('defaultValue', newQty);

                                    // ✅ Send update to PHP
                                    $.ajax({
                                        url: 'handlers/serviceRecords-handler.php',
                                        method: 'POST',
                                        dataType: 'json',
                                        data: {
                                            action: 'updateItemQuantity',
                                            record_id: ActiveRowId,
                                            item_id: itemId,
                                            quantity: newQty,
                                            rst_id: rstId
                                        },
                                        success: function(response) {
                                            if (response.status === 'success') {
                                                Swal.fire({
                                                    ...swalOptions,
                                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                                    title: 'Updated',
                                                    text: response.message || 'Quantity updated successfully!',
                                                    target: document.getElementById('offCanvasManageItems')
                                                });


                                                ManageItemsData(rstId); // Refresh data
                                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                                const rowObj = table.row(tr);

                                                rowObj.invalidate().draw(false); // Redraw the row

                                                // Close if open
                                                if (rowObj.child.isShown()) {
                                                    rowObj.child.hide();
                                                    tr.removeClass('shown');
                                                }

                                                // Reopen the expanded details
                                                setTimeout(() => {
                                                    tr.find('.dt-control').click();
                                                }, 150);
                                            } else {

                                                if (response.status === "lack_of_stock") {
                                                    Swal.fire({
                                                        iconHtml: '<i class="bx bx-error-circle"></i>',
                                                        title: 'Insufficient Stock',
                                                        html: response.message || 'The requested quantity exceeds available stock.',
                                                        confirmButtonText: 'OK',
                                                        target: document.getElementById('offCanvasManageItems')
                                                    });

                                                } else if (response.status === 'exists') {
                                                    // Item already exists for this service
                                                    Swal.fire({
                                                        iconHtml: '<i class="bx bx-error-circle"></i>',
                                                        title: 'Item Already Added',
                                                        html: response.message,
                                                        confirmButtonText: 'OK',
                                                        target: document.getElementById('offCanvasManageItems')
                                                    });
                                                } else {
                                                    Swal.fire({
                                                        ...swalOptions,
                                                        iconHtml: '<i class="bx bx-x-circle"></i>',
                                                        title: 'Error',
                                                        text: response.message || 'Failed to update quantity.',
                                                        target: document.getElementById('offCanvasManageItems')
                                                    });
                                                }
                                                input.val(oldQty); // revert back if failed
                                            }
                                        },
                                        error: function(err) {
                                            console.error('AJAX error:', err);
                                            Swal.fire({
                                                ...swalOptions,
                                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                                title: 'Error',
                                                text: 'Server error while updating quantity.',
                                                target: document.getElementById('offCanvasManageItems')
                                            });
                                            input.val(oldQty); // revert back on error
                                        }
                                    });
                                }
                            });
                        });

                        initTooltip();

                        // ✅ Attach Swal confirmation on remove button
                        $(document).off('click', '.remove-btn').on('click', '.remove-btn', function(e) {
                            e.preventDefault();

                            const row = $(this).closest('tr');
                            const itemId = row.find('.qty-input').data('item-id');
                            const qty = parseInt(row.find('.qty-input').val(), 10) || 0;
                            const itemName = row.find('.itemnameBox').text().trim(); // ✅ get item name

                            Swal.fire({
                                iconHtml: '<i class="bx bx-trash"></i>',
                                title: 'Remove Item',
                                html: `
                                    Are you sure you want to remove <b>${itemName}</b>?
                                    <br><br>
                                    <small>This will remove all <b>${qty}</b> of this item from used items.</small>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Yes, remove',
                                cancelButtonText: 'Cancel',
                                target: document.getElementById('offCanvasManageItems')
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: 'handlers/serviceRecords-handler.php',
                                        method: 'POST',
                                        dataType: 'json',
                                        data: {
                                            action: 'removeItem',
                                            record_id: ActiveRowId,
                                            item_id: itemId,
                                            rst_id: rstId
                                        },
                                        success: function(response) {
                                            if (response.status === 'success') {
                                                Swal.fire({
                                                    ...swalOptions,
                                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                                    title: 'Removed',
                                                    text: response.message || 'Item removed successfully!',
                                                    target: document.getElementById('offCanvasManageItems')
                                                });

                                                // Refresh items list
                                                ManageItemsData(rstId);

                                                // Refresh DataTable row
                                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                                const rowObj = table.row(tr);

                                                rowObj.invalidate().draw(false);

                                                // Reopen expanded details if needed
                                                if (rowObj.child.isShown()) {
                                                    rowObj.child.hide();
                                                    tr.removeClass('shown');
                                                }
                                                setTimeout(() => {
                                                    tr.find('.dt-control').click();
                                                }, 150);
                                            } else {
                                                Swal.fire({
                                                    ...swalOptions,
                                                    iconHtml: '<i class="bx bx-x-circle"></i>',
                                                    title: 'Error',
                                                    text: response.message || 'Failed to remove item.',
                                                    target: document.getElementById('offCanvasManageItems')
                                                });
                                            }
                                        },
                                        error: function(err) {
                                            console.error('AJAX error:', err);
                                            Swal.fire({
                                                ...swalOptions,
                                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                                title: 'Error',
                                                text: 'Server error while removing item.',
                                                target: document.getElementById('offCanvasManageItems')
                                            });
                                        }
                                    });
                                }
                            });
                        });


                        // Update total price in the footer
                        const totalPrice = (itemsNeeded.reduce((total, item) => total + (item.record_price * item.quantity), 0)).toLocaleString('en-US', {
                            minimumFractionDigits: 2
                        });
                        $('#itemsSum tfoot th.col-total-price').text(`₱ ${totalPrice}`);

                        // Clear existing inventory items
                        $('#itemsList').empty();

                        // Populate inventory items
                        inventoryItems.forEach(item => {
                            const itemClass = item.status === 'Available' ? 'avail' : 'unavail';
                            const buttonDisabled = item.status === 'Available' ? '' : 'disabled';
                            const inventoryItem = `
                                <div class="item-container ${item.status === 'Available' ? '' : 'unavailItem'}"
                                    data-type="${(item.type || '').toLowerCase()}">
                                    <b>${item.name}</b>
                                    <div class="status-stock">
                                        <p class="${itemClass}">${item.status}</p>
                                        <p class="stock">Stock: ${item.stock}</p>
                                    </div>
                                    <p class="item-price">₱ ${parseFloat(item.price).toLocaleString('en-US', { minimumFractionDigits: 2})}</p>
                                    <button ${buttonDisabled} data-item-id="${item.item_id}">
                                        <i class="bx bx-plus-circle"></i>
                                    </button>
                                </div>
                            `;
                            $('#itemsList').append(inventoryItem);
                        });
                        filterInventory();
                    } else {
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-info-circle"></i>',
                            title: 'No Data',
                            text: response.message || 'No items found.',
                            target: document.getElementById('offCanvasManageItems')
                        });
                    }


                },
                error: function(err) {

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error',
                        text: 'Something went wrong',
                        target: document.getElementById('offCanvasManageItems')
                    })
                    console.error('AJAX error:', err);
                }
            });
        }

        function filterInventory() {
            // grab search text (works for both inputs if you want consistency)
            let searchVal = $("#itemslistBox input[type='search']").val()?.toLowerCase().trim() || "";
            // grab filter (either dropdown OR button)
            let typeVal = $("#filterType").val() || $("#filterButtons .active").data("type") || "all";

            let typeLabel = typeVal === "all" ? "all items" : typeVal;
            let matches = 0;

            $("#itemsList .no-results").remove();

            $("#itemsList .item-container").each(function() {
                let $this = $(this);
                let itemType = $this.data("type");
                let itemName = $this.find("b").text().toLowerCase(); // ✅ only search name

                let matchesType = (typeVal === "all" || itemType === typeVal);
                let matchesSearch = (searchVal === "" || itemName.includes(searchVal));

                if (matchesType && matchesSearch) {
                    $this.show();
                    matches++;
                } else {
                    $this.hide();
                }
            });

            if (matches === 0) {
                let searchText = searchVal === "" ? "items" : `"${searchVal}"`;
                $("#itemsList").append(
                    `<p class="no-results">No ${searchText} found in ${typeLabel}.</p>`
                );
            }
        }

        // 🔗 bind once for both
        $(document).on("input", "#itemslistBox input[type='search']", filterInventory);
        $(document).on("change", "#filterType", filterInventory);
        $(document).on("click", "#filterButtons button", function() {
            $("#filterButtons button").removeClass("active");
            $(this).addClass("active");
            filterInventory();
        });

        // Handle clicking the plus button
        $(document).on("click", "#itemsList .item-container button", function() {
            let itemId = $(this).data("item-id");
            let itemName = $(this).closest(".item-container").find("b").text();
            let serviceName = $("#service-name-head").text();

            Swal.fire({
                title: "Adding item to service",
                html: `Confirm adding <b>${itemName}</b> to the service <b>${serviceName}</b>?`,
                iconHtml: "<i class='bx bx-info-circle'></i>",
                showCancelButton: true,
                confirmButtonText: "Confirm",
                cancelButtonText: "Cancel",
                reverseButtons: true,
                target: document.getElementById('offCanvasManageItems')
            }).then((result) => {
                if (result.isConfirmed) {
                    // 🔹 Do your AJAX call or logic here
                    $.ajax({
                        url: "handlers/serviceRecords-handler.php",
                        method: "POST",
                        data: {
                            action: "useItem",
                            item_id: itemId,
                            record_id: ActiveRowId, // if needed
                            rst_id: rstId
                        },
                        success: function(response) {
                            if (response.status === "success") {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-check-circle'></i>",
                                    title: "Success",
                                    text: `${itemName} has been added.`,
                                    target: document.getElementById('offCanvasManageItems')
                                });
                                ManageItemsData(rstId); // refresh the list

                                const tr = $(`tr[data-id="${ActiveRowId}"]`);
                                const rowObj = table.row(tr);

                                rowObj.invalidate().draw(false); // Redraw the row

                                // Close if open
                                if (rowObj.child.isShown()) {
                                    rowObj.child.hide();
                                    tr.removeClass('shown');
                                }

                                // Reopen the expanded details
                                setTimeout(() => {
                                    tr.find('.dt-control').click();
                                }, 150);
                            } else if (response.status === "exists") {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-error-circle'></i>",
                                    title: "Already Added",
                                    target: document.getElementById('offCanvasManageItems'),
                                    text: response.message
                                });
                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: "<i class='bx bx-x-circle'></i>",
                                    title: "Error",
                                    text: response.message || "Something went wrong.",
                                    target: document.getElementById('offCanvasManageItems')
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: "<i class='bx bx-x-circle'></i>",
                                title: "Error",
                                text: "Something went wrong.",
                                target: document.getElementById('offCanvasManageItems')
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




        function initializeItemSelect2() {
            const selectItems = $('#selectItems');

            // Destroy if already initialized
            if (selectItems.data('select2')) {
                selectItems.select2('destroy');
            }

            // Clear old options except the first/default
            selectItems.select2({
                dropdownParent: $('#details-row'), // or the correct modal/container ID
                placeholder: 'Select an item/product',
            });
        }



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
</body>

</html>