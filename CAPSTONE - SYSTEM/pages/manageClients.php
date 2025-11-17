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
    <title>Manage Clients</title>
    <link rel="stylesheet" href="style/manageClients.css">
    <script>
        initTooltip();
        initializeSelect2();
        initializeSelect2forHistory();
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
                type: 'select',
                options: ['Active', 'Deactivated', 'Restricted']
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

            if (!filter) return; // Skip if undefined (extra column?)

            if (filter.type === 'select') {
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
                    type: 'string',
                    targets: [4, 7] // adjust if needed based on actual string columns
                },
                {
                    width: '200px',
                    targets: 6
                },
                {
                    width: '200px',
                    targets: [1, 7]
                }
            ]
        });

        // ✅ 1. Clear all DataTables search filters
        table.search('').columns().search('').draw(false);

        // ✅ 2. Clear all filter inputs and selects in the filter row
        $('#myTable thead tr.filters th').each(function() {
            $(this).find('input, select').val('');
        });


        let clientAccount = null;
        let ActiveRowId = null;
        let activeRow = null;

        // Initialize Select2 for service history
        function initializeSelect2forHistory() {
            const historySelect = $('#req-id');

            if (historySelect.data('select2')) {
                historySelect.select2('destroy');
            }

            historySelect.select2({
                width: '100%'
            });

            historySelect.on('select2:open', function() {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) searchField.placeholder = 'Look for Request History...';
                }, 0);
            });

            // Update history details when selection changes
            historySelect.on('change', function() {
                const selectedIndex = $(this).val();
                const selectedService = serviceHistory[selectedIndex];
                if (!selectedService) return;

                $('#history-details').html(`
            <div class="leftbox">
                <p class="ellipsis-tooltip"><b>Request ID:</b> ${selectedService.request_id}</p>
                <p class="ellipsis-tooltip"><b>Status:</b> ${selectedService.record_status}</p>
                <p class="ellipsis-tooltip"><b>Vehicle:</b> ${selectedService.vehicle}</p>
                <p class="ellipsis-tooltip"><b>Scheduled Date:</b> ${formatDate(selectedService.scheduled_or_request_dt)}</p>
                <p class="ellipsis-tooltip"><b>Total Billing:</b> ${formatCurrency(selectedService.total_billing)}</p>
            </div>
            <div class="rightbox">
                <b>Services:</b>
                <div class="ellipsis-tooltip" id="history-services">
                    <span>${selectedService.services.map(s => s.service_name).join(', ')}</span>
                </div>
                <b>Mechanic/s:</b>
                <div id="history-mechanics" class="ellipsis-tooltip">
                    <span>${selectedService.mechanics.join('; ')}</span>
                </div>
            </div>
        `);
                initTooltip();
            });
        }

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
                clientAccount = null;
                clientVehicles = [];
            }
        }



        // Function to initialize select2 with vehicle data
        function initializeSelect2() {
            const vehicleSelect = $('#vehicle-select');

            // If select2 is already initialized, destroy it first
            if (vehicleSelect.data('select2')) {
                vehicleSelect.select2('destroy');
            }

            // Re-initialize
            vehicleSelect.select2();

            // Bind the change event to update vehicle details when a new vehicle is selected
            vehicleSelect.on('change', function() {
                const selectedIndex = $(this).val(); // Get selected index from the dropdown
                const selectedVehicle = clientVehicles[selectedIndex]; // Get the selected vehicle

                // Update vehicle details based on the selected vehicle
                $('#vehicle-details').html(`
            <p class="ellipsis-tooltip"><b>Plate Number:</b> ${selectedVehicle.plate_number}</p>
            <p class="ellipsis-tooltip"><b>Color:</b> ${selectedVehicle.color}</p>
            <p class="ellipsis-tooltip"><b>Transmission:</b> ${selectedVehicle.transmission_type}</p>
            <p class="ellipsis-tooltip"><b>Fuel Type:</b> ${selectedVehicle.fuel_type}   </p>
        `);
                initTooltip();
            });

            // Set placeholder for the search field inside the Select2 dropdown
            vehicleSelect.on('select2:open', function() {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.placeholder = 'Look for Client Vehicles...';
                    }
                }, 0);
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
                // Clear active info when closed
                // tr.classList.remove('active-row');
                // ActiveRowId = null;
                // localStorage.removeItem('RowId');
                // activeRow = null;

                return;
            }

            // Close previously opened row if any
            if (activeRow && activeRow !== tr) {
                closeRow(activeRow);
                activeRow.classList.remove('active-row'); // Remove active from old one
            }

            ActiveRowId = rowData[1];
            localStorage.setItem('RowId', ActiveRowId);

            // AJAX PRESENTATION
            $.ajax({
                url: 'handlers/manageClients-handler.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    row_id: ActiveRowId,
                    action: 'getRowData'
                },
                success: function(response) {
                    if (response.account) {
                        clientAccount = response.account;
                    } else {
                        clientAccount = null;
                    }

                    clientVehicles = response.vehicles || [];
                    serviceHistory = response.service_history;

                    row.child(format(rowData)).show();
                    tr.classList.add('shown', 'active-row');
                    activeRow = tr;



                    initTooltip();
                    initializeSelect2();
                    initializeSelect2forHistory();
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

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        }

        function formatCurrency(amount) {
            if (amount == null) return 'P 0';
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }

        // Expandable row content template
        window.format = function(rowData) {
            service_history = serviceHistory;

            // Check if clientVehicles exists and is an array with data
            const vehicleOptions = clientVehicles && clientVehicles.length > 0 ?
                clientVehicles.map((vehicle, index) =>
                    `<option value="${index}" ${index === 0 ? 'selected' : ''}>
                    ${vehicle.make} ${vehicle.model}
                </option>`
                ).join('') : '';

            // Display actual details of the first vehicle if available
            const firstVehicle = clientVehicles && clientVehicles.length > 0 ? clientVehicles[0] : null;

            // Correct usage of template literals with backticks
            const vehicleDetailsDisplay = firstVehicle ?
                `<div id="vehicle-details">
                    <p class="ellipsis-tooltip"><b>Plate Number:</b> ${firstVehicle.plate_number}</p>
                    <p class="ellipsis-tooltip"><b>Color:</b> ${firstVehicle.color}</p>
                    <p class="ellipsis-tooltip"><b>Transmission:</b> ${firstVehicle.transmission_type}</p>
                    <p class="ellipsis-tooltip"><b>Fuel Type:</b> ${firstVehicle.fuel_type}</p>
                </div>` : `<p>No vehicles linked yet</p>`;

            // Build service history options dynamically
            const historyOptions = serviceHistory && serviceHistory.length > 0 ?
                serviceHistory.map((s, i) => `<option value="${i}" ${i === 0 ? 'selected' : ''}>Request ID: ${s.request_id}</option>`).join('') :
                '<option selected>No history</option>';

            return `
                <div id="details-row">
                    <div id="personal-info">
                        <b>PERSONAL INFORMATION</b>
                        <p class="ellipsis-tooltip martop">${rowData[3]}</p>
                        <p class="ellipsis-tooltip">${rowData[4]}</p>
                        <p class="ellipsis-tooltip">${rowData[6]}</p>
                        <p class="ellipsis-tooltip">${rowData[5]}</p>
                    </div>
                    <div id="account-info">
                        <b>ACCOUNT INFORMATION</b>
                        ${clientAccount ? `
                        <p class="ellipsis-tooltip martop"><b>Username: </b>${clientAccount.username}</p>
                        <p class="ellipsis-tooltip"><b>Date Created: </b>${clientAccount.date_created}</p></br></br>
                        ` : `<p class="martop">No account linked</p></br></br></br>
                        `}
                            <p class="ellipsis-tooltip" style="margin-top: 25px;"><b>Total Services Made: </b>${service_history.length}</p>
                    </div>
                    <div id="vehicle">
                        <b >VEHICLE/S</b> </br>
                        <div class="infos">
                            ${clientVehicles.length > 0 ? `
                            <select id="vehicle-select" class="vehicle-select">
                                ${vehicleOptions}
                            </select>
                            ${vehicleDetailsDisplay}
                            ` : `<p>No vehicles linked</p>`}
                        </div>
                    </div>
                    <div id="history">
                    <b>SERVICE HISTORY</b>
                    <select id="req-id" class="marbot">${historyOptions}</select>
                    <div id="history-details">
                        ${serviceHistory && serviceHistory.length > 0 ? `
                        <div class="leftbox">
                            <p class="ellipsis-tooltip"><b>Request ID: </b>${serviceHistory[0].request_id}</p>
                            <p class="ellipsis-tooltip"><b>Status: </b>${serviceHistory[0].record_status}</p>
                            <p class="ellipsis-tooltip"><b>Vehicle: </b>${serviceHistory[0].vehicle}</p>
                            <p class="ellipsis-tooltip"><b>Scheduled Date: </b>${formatDate(serviceHistory[0].scheduled_or_request_dt)}</p>
                            <p class="ellipsis-tooltip"><b>Total Billing: </b>${formatCurrency(serviceHistory[0].total_billing)}</p>
                        </div>
                        <div class="rightbox">
                            <b>Services:</b>
                            <div class="ellipsis-tooltip" id="history-services">
                                <span>${serviceHistory[0].services.map(s => s.service_name).join(', ')}</span>
                            </div>
                            <b>Mechanic/s:</b>
                            <div id="history-mechanics" class="ellipsis-tooltip">
                                <span>${serviceHistory[0].mechanics.join('; ')}</span>
                            </div>
                        </div>` : `<p>No history</p>`}
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
            activeRow = null;
            closeRow(tr);
        }
        $('#myTable').on('order.dt page.dt search.dt length.dt', function() {
            unsetActiveRow();
        });

        // Load previously expanded row after table renders
        loadExpandedRows();
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>manage Clients</span></p>
        <div id="table-box"><i class="bx bx-help-circle" title="help" id="helpicon"></i>

            <div id="buttons"><!-- BUTTONS FOR CRUD FUNCTIONS -->
                <button id="addbtn" type="button" data-bs-target="#addClientModal" data-bs-toggle="modal"><i class='bx bx-plus'></i><span>Add</span></button>
                <button id="editbtn"><i class='bx bx-pencil'></i><span>Edit</span></button>
                <button id="restrictbtn" onclick="changeAccStatus('restrict');"><i class='bx bxs-circle'></i><span>Restrict</span></button>
                <button id="activatebtn" onclick="changeAccStatus('activate');"><i class='bx bxs-circle'></i><span>Activate</span></button>
                <button id="deacbtn" onclick="changeAccStatus('deactivate');"><i class='bx bxs-circle'></i><span>Deactivate</span></button>

            </div>
            <div id="mesa">
                <!-- TABLE USES DATABLES LIBRARY -->
                <table id="myTable">

                    <thead>
                        <tr class="column-heads">
                            <th></th>
                            <th>Client ID</th>
                            <th>Status</th>
                            <th>Full Name <span id="sub" class="text-capitalize text-muted">(LastName, FirstName, MiddleName)</span></th>
                            <th>Contact Number</th>
                            <th>Address <span id="sub" class="text-capitalize text-muted">(#HouseNo, Street, City, Province)</span></th>
                            <th>Email</th>
                            <th>Account ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //POPULATING THE TABLE WITH VALUES FROM DB
                        $rs = mysqli_query($db_connection, 'SELECT * FROM clientstbl');

                        while ($rw = mysqli_fetch_array($rs)) {

                            echo
                            '
                        <tr  class="data-rows" data-id="' . $rw['client_id'] . '" oncontextmenu="toggleRow(this); return false;" onclick = "setActiveRow(this);">
                        <td class="dt-control"><i class="bx bx-chevron-down"></i></td>
                        <td  id="aydis">' . $rw['client_id'] . '</td>
                        <td  data-role ="status">' . $rw['status'] . '</td>
                        <td data-role ="fullname" class="ellipsis-tooltip">' . $rw['last_name'] . ', ' . $rw['first_name'] . (!empty($rw['middle_name']) ? ', ' . $rw['middle_name'] : '') . '</td>
                        <td data-role ="contact_number">' . $rw['contact_number'] . '</td>
                        <td data-role ="address" class="ellipsis-tooltip">' . $rw['address'] . '</td>
                        <td data-role ="email" class="ellipsis-tooltip">' . $rw['email'] . '</td>
                        <td data-role ="account_id">' . $rw['account_id'] . '</td>
                        </tr>
                        ';
                        }

                        // function moreDetails(){
                        //     echo '
                        //     ';
                        // }


                        ?>
                    </tbody>

                </table>
            </div>


        </div>
        <!-- BACK TO TOP BUTTON -->
        <a href="#top" id="goTop" title="Go up"><i class='bx bx-chevron-up'></i></a>
    </div>
    </div>

    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addClientModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add client information</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="resetAddForm();"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" id="addClientForm" onsubmit="return validateNewClientForm();">
                        <label for="firstName">First Name</label>
                        <input class="form-control" type="text" id="firstName" name="first_name" placeholder="Enter Client's First Name" required>

                        <label for="lastName">Last Name</label>
                        <input class="form-control" type="text" id="lastName" name="last_name" placeholder="Enter Client's Last Name" required>

                        <label for="middleName">Middle Name</label>
                        <input type="checkbox" id="enableMiddleName" checked>
                        <input class="form-control" type="text" id="middleName" name="middle_name" placeholder="Enter Client's Middle Name">

                        <label for="mobile">Mobile Number</label>
                        <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="mobile" name="mobile_number" placeholder="Enter Client's Mobile Number" maxlength="11" required>

                        <label for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" placeholder="Enter Client's Email">

                        <label for="address">Address</label>
                        <input class="form-control" type="text" id="address" name="address" placeholder="Enter Client's Address">
                        <div id="passwordHelpBlock" class="form-text">
                            Please enter the address in the format: <strong>House Number, Barangay, City</strong> (e.g., 123 Purok 1, Barangay Sampaguita, Cabanatuan City).
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetAddForm();">Cancel</button>
                            <button type="submit" id="addClientbtn" class="btn btn-primary"> <i class="bx bx-plus"></i>Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editClientModal" aria-hidden="true" aria-labelledby="editClientLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="editClientLabel">Edit Client Information</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="resetEditForm();"></button>
                </div>
                <div class="modal-body">
                    <form action="" id="editClientForm" method="POST" onsubmit="return validateUpdatedClientForm();">
                        <label for="editFirstName">First Name</label>
                        <input class="form-control" type="text" id="editFirstName" name="first_name" placeholder="Enter Client's First Name" required>

                        <label for="editLastName">Last Name</label>
                        <input class="form-control" type="text" id="editLastName" name="last_name" placeholder="Enter Client's Last Name" required>

                        <label for="editMiddleName">Middle Name</label>
                        <input type="checkbox" id="enableEditMiddleName" checked>
                        <input class="form-control" type="text" id="editMiddleName" name="middle_name" placeholder="Enter Client's Middle Name">

                        <label for="editMobile">Mobile Number</label>
                        <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="editMobile" name="mobile_number" placeholder="Enter Client's Mobile Number" maxlength="11" required>

                        <label for="editEmail">Email</label>
                        <input class="form-control" type="email" id="editEmail" name="email" placeholder="Enter Client's Email">

                        <label for="editAddress">Address</label>
                        <input class="form-control" type="text" id="editAddress" name="address" placeholder="Enter Client's Address">
                        <div class="form-text">
                            Please enter the address in the format: <strong>House Number, Barangay, City</strong> (e.g., 123 Purok 1, Barangay Sampaguita, Cabanatuan City).
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetEditForm();">Cancel</button>
                            <button type="submit" class="btn btn-primary"> <i class="bx bx-save"></i>Update Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>



</div>
<script>
    let client;

    document.getElementById('enableMiddleName').addEventListener('change', function() {
        document.getElementById('middleName').disabled = !this.checked;
        document.getElementById('middleName').value = '';
    });
    document.getElementById('enableEditMiddleName').addEventListener('change', function() {
        document.getElementById('editMiddleName').disabled = !this.checked;
        document.getElementById('editMiddleName').value = '';
    });





    const modifiedData = {};

    function validateUpdatedClientForm() {
        // ✅ Clear all previous keys while keeping the same object reference
        for (let key in modifiedData) {
            delete modifiedData[key];
        }
        const swalOptions = {
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        const firstName = document.getElementById('editFirstName').value;
        const lastName = document.getElementById('editLastName').value;
        const middleName = document.getElementById('editMiddleName').value;
        const mobile = document.getElementById('editMobile').value;
        const email = document.getElementById('editEmail').value;
        const address = document.getElementById('editAddress').value;

        const normalize = (val) => (val || "").trim();

        const mobilePattern = /^09\d{9}$/;

        if (!mobilePattern.test(mobile)) {
            $('#editClientModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Mobile Number',
                text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
            }).then(() => {
                // Show modal again after Swal closes
                $('#editClientModal').modal('show');
            });
            return false;
        }

        if (normalize(firstName) !== normalize(client.first_name)) {
            modifiedData.first_name = firstName;
        }
        if (normalize(lastName) !== normalize(client.last_name)) {
            modifiedData.last_name = lastName;
        }
        if (normalize(middleName) !== normalize(client.middle_name)) {
            modifiedData.middle_name = middleName;
        }
        if (normalize(mobile) !== normalize(client.contact_number)) {
            modifiedData.contact_number = mobile;
        }
        if (normalize(email) !== normalize(client.email)) {
            modifiedData.email = email;
        }
        if (normalize(address) !== normalize(client.address)) {
            modifiedData.address = address;
        }

        if (Object.keys(modifiedData).length === 0) {
            $('#editClientModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Changes Made',
                text: 'Make changes before saving.'
            }).then(() => {
                $('#editClientModal').modal('show');
            });
            return false;
        }

        // Include the client's ID or identifier for update
        modifiedData.client_id = client.client_id;
        console.log('new:', modifiedData);

        updateClientRecord(modifiedData);
        return false;
    }

    function updateClientRecord(updatedClientData) {
        const swalOptions = {
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };


        $.ajax({
            url: 'handlers/manageClients-handler.php',
            method: 'POST',
            dataType: 'json', // Important: expecting JSON from PHP
            data: {
                client_data: updatedClientData,
                action: 'update'
            },
            success: function(response) {
                if (response.status === 'success') {
                    $('#editClientModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Client Updated Successfully',
                        text: response.message
                    });
                    const row = table.row(`tr[data-id="${updatedClientData.client_id}"]`);

                    // Get and update row data
                    let rowData = row.data();

                    if (modifiedData.last_name || modifiedData.first_name || modifiedData.middle_name !== undefined) {
                        const fullLast = modifiedData.last_name ?? client.last_name;
                        const fullFirst = modifiedData.first_name ?? client.first_name;
                        const fullMiddle = modifiedData.middle_name !== undefined ? modifiedData.middle_name : client.middle_name;

                        rowData[3] = `${fullLast}, ${fullFirst}` + (fullMiddle ? `, ${fullMiddle}` : '');

                        // ✅ Update client object
                        if (modifiedData.first_name) client.first_name = modifiedData.first_name;
                        if (modifiedData.last_name) client.last_name = modifiedData.last_name;
                        if (modifiedData.middle_name !== undefined) client.middle_name = modifiedData.middle_name;
                    }

                    if (modifiedData.contact_number) {
                        rowData[4] = modifiedData.contact_number;
                        client.contact_number = modifiedData.contact_number;
                    }
                    if (modifiedData.address) {
                        rowData[5] = modifiedData.address;
                        client.address = modifiedData.address;
                    }
                    if (modifiedData.email) {
                        rowData[6] = modifiedData.email;
                        client.email = modifiedData.email;
                    }
                    // console.log(client);

                    // Apply changes and redraw
                    row.data(rowData).invalidate().draw(false);

                    // Refresh the expanded child row
                    setTimeout(() => {
                        $(`tr[data-id="${updatedClientData.client_id}"]`).find('.dt-control').click();
                    }, 150);

                } else if (response.status === 'existing') {
                    $('#editClientModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Duplicate Record',
                        text: response.message
                    }).then(() => {
                        $('#editClientModal').modal('show');
                    });

                } else {
                    $('#editClientModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Update Failed',
                        text: response.message
                    }).then(() => {
                        $('#editClientModal').modal('show');
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#editClientModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Error',
                    text: 'Something went wrong while updating the client.'
                }).then(() => {
                    $('#editClientModal').modal('show');
                });
                console.error("AJAX Error:", status, error);
            }
        });
    }


    $(document).on('click', '#editbtn', function() { //Make Record Editable
        event.preventDefault();
        if (checkSelection()) {
            return; // Exit if no valid selection
        }
        $.ajax({
            url: 'handlers/manageClients-handler.php',
            type: 'POST',
            dataType: 'json',
            data: {
                clientId: ActiveRowId,
                action: 'getClientRecord'
            },
            success: function(data) {
                if (data.status) {
                    client = data.client;

                    $('#editFirstName').val(client.first_name);
                    $('#editLastName').val(client.last_name);
                    $('#editMiddleName').val(client.middle_name);
                    $('#editMobile').val(client.contact_number);
                    $('#editEmail').val(client.email);
                    $('#editAddress').val(client.address);


                    if (!client.middle_name) {
                        $('#enableEditMiddleName').prop('checked', false);
                        $('#editMiddleName').prop('disabled', true).val('');
                    } else {
                        $('#enableEditMiddleName').prop('checked', true);
                        $('#editMiddleName').prop('disabled', false);
                    }


                    $('#editClientModal').modal('show');

                } else {
                    console.warn("Client not found:", data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading client record:", error);
            }
        });
    });

    function resetEditForm() {

        const firstName = document.getElementById('editFirstName').value;
        const lastName = document.getElementById('editLastName').value;
        const middleName = document.getElementById('editMiddleName').value;
        const mobile = document.getElementById('editMobile').value;
        const email = document.getElementById('editEmail').value;
        const address = document.getElementById('editAddress').value;

        const normalize = (val) => (val || "").trim();

        if (
            normalize(firstName) !== normalize(client.first_name) ||
            normalize(lastName) !== normalize(client.last_name) ||
            normalize(middleName) !== normalize(client.middle_name) ||
            normalize(mobile) !== normalize(client.contact_number) ||
            normalize(email) !== normalize(client.email) ||
            normalize(address) !== normalize(client.address)) {
            Swal.fire({
                title: 'Discard Changes?',
                html: 'Are you sure you want discard changes you made?',
                iconHtml: '<i class="bx bx-info-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'Keep Editing',
                confirmButtonText: 'Discard',
                allowOutsideClick: false,
                focusConfirm: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    clearEditInputs();
                } else {
                    $('#editClientModal').modal('show');
                }
            });
        } else {
            clearEditInputs();
        }

    }

    function clearEditInputs() {
        $('#editFirstName, #editLastName, #editMiddleName, #editMobile, #editEmail, #editAddress').val('');
        $('#enableEditMiddleName').prop('checked', true);
        client = null;
    }


    function validateMobile(input) {
        let value = input.value;

        // Remove non-digit characters
        value = value.replace(/\D/g, '');

        // Enforce it starts with "09"
        if (!value.startsWith("09")) {
            if (value.length >= 2) {
                value = "09" + value.slice(2);
            } else {
                value = "09";
            }
        }

        // Limit to 11 digits
        value = value.slice(0, 11);

        input.value = value;
    }

    function validateNewClientForm() {
        const firstName = document.getElementById("firstName").value.trim();
        const lastName = document.getElementById("lastName").value.trim();


        const middleNameInput = document.getElementById("middleName");
        let middleName = "";
        if (!middleNameInput.disabled) {
            middleName = middleNameInput.value.trim();
        }

        const mobile = document.getElementById("mobile").value.trim();
        const email = document.getElementById("email").value.trim();
        const address = document.getElementById("address").value.trim();
        const mobilePattern = /^09\d{9}$/;




        const swalOptions = {
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        if (!firstName || !lastName || !mobile || !email) {
            $('#addClientModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Missing Required Fields',
                text: 'Please fill in First Name, Last Name, and Mobile Number.'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addClientModal').modal('show');
            });
            return false;
        }

        if (!mobilePattern.test(mobile)) {
            $('#addClientModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Mobile Number',
                text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addClientModal').modal('show');
            });
            return false;
        }

        if (email && !email.includes("@")) {
            $('#addClientModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Email',
                text: 'Please enter a valid email address.'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addClientModal').modal('show');
            });
            return false;
        }

        addClientRecord({
            first_name: firstName,
            last_name: lastName,
            middle_name: middleName,
            mobile_number: mobile,
            email: email,
            address: address
        });
        return false;
    }

    function resetAddForm() {

        const firstName = document.getElementById('firstName').value;
        const lastName = document.getElementById('lastName').value;
        const middleName = document.getElementById('middleName').value;
        const mobile = document.getElementById('mobile').value;
        const email = document.getElementById('email').value;
        const address = document.getElementById('address').value;

        if (
            firstName !== '' ||
            lastName !== '' ||
            middleName !== '' ||
            (mobile !== '' && mobile !== '09') ||
            email !== '' ||
            address !== '') {
            Swal.fire({
                title: 'Discard Changes?',
                html: 'Are you sure you want to clear your current inputs?',
                iconHtml: '<i class="bx bx-info-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
                allowOutsideClick: false,
                focusConfirm: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#addClientForm')[0].reset();
                } else {
                    $('#addClientModal').modal('show');
                }
            });
        }



    }

    function addClientRecord(clientData) {
        const swalOptions = {
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        $.ajax({
            url: 'handlers/manageClients-handler.php',
            method: 'POST',
            dataType: 'json', // <-- this is important
            data: {
                client_data: clientData,
                action: 'add'
            },
            success: function(response) {
                if (response.status == 'success') {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-check-circle\'></i>',
                        title: 'Client Added Successfully',
                        text: response.message
                    }).then(() => {
                        $('#addClientForm')[0].reset();
                        $('#addClientModal').modal('hide');

                        location.reload();
                    });
                } else if (response.status == 'existing') {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Existing Client Record',
                        text: response.message
                    });
                } else {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error Adding Client',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                    title: 'Error',
                    text: 'Something went wrong while saving the client.'
                });
                console.error("AJAX Error:", status, error);
            }
        });

    }

    function checkSelection() {
        if (!ActiveRowId) {
            Swal.fire({
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Record Selected',
                text: 'Please select a record before performing this action.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                didOpen: (popup) => {

                    Swal.getConfirmButton().focus();

                    // Pause timer when hovering over modal
                    popup.addEventListener('mouseenter', Swal.stopTimer);
                    popup.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            return true;
        } else {
            return false;
        }
    }


    function changeAccStatus(action) {
        checkSelection();

        const swalOptions = {
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        const statusCell = activeRow.cells[2].textContent.trim().toLowerCase();


        if (action == 'deactivate') {

            if (statusCell == "deactivated") {
                Swal.fire({
                    ...swalOptions,
                    title: 'No Changes Made',
                    html: `This profile is already ${statusCell}`,
                    iconHtml: '<i class="bx bx-info-circle"></i>'
                });
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                html: `Do you want to <b>DEACTIVATE</b> the profile of <br>Client ID: ${ActiveRowId}?
                    <br><br>
                    <small>Deactivating the profile <b>prevents the client from logging in and accessing website features.</b></small>
                    `,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Do your deactivation logic here (e.g., AJAX request)
                    $.ajax({
                        url: 'handlers/manageClients-handler.php',
                        method: 'POST',
                        dataType: 'json', // <-- this is important
                        data: {
                            rowId: ActiveRowId,
                            statusChange: action
                        },
                        success: function(response) {
                            if (response.statusUpdate === true) {
                                $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`).text('Deactivated');
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                    title: 'Client Status Changed',
                                    html: response.message
                                });

                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                                    title: 'Error Updating Client Status',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = 'Something went wrong while updating client status.';

                            // Try to extract more detailed error message from the response body
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Error',
                                text: errorMessage
                            });
                            console.error("AJAX Error:", status, error);
                            console.error("Response:", xhr.responseText); // Good for debugging raw output
                        }
                    });
                }
            });
        }

        if (action == 'activate') {

            if (statusCell == "active") {
                Swal.fire({
                    ...swalOptions,
                    title: 'No Changes Made',
                    html: `This profile is already ${statusCell}`,
                    iconHtml: '<i class="bx bx-info-circle"></i>'
                });
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                html: `Do you want to <b>ACTIVATE</b> the profile of <br>Client ID:  ${ActiveRowId}?
                    <br><br>
                    <small>Activating the profile grants the client <b>full access to website features</b>.</small>
                `,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Do your activation logic here (e.g., AJAX request)
                    $.ajax({
                        url: 'handlers/manageClients-handler.php',
                        method: 'POST',
                        dataType: 'json', // <-- this is important
                        data: {
                            rowId: ActiveRowId,
                            statusChange: action
                        },
                        success: function(response) {
                            if (response.statusUpdate === true) {

                                $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`).text('Active');
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                    title: 'Client Status Changed',
                                    html: response.message
                                });

                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                                    title: 'Error Updating Client Status',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = 'Something went wrong while updating client status.';

                            // Try to extract more detailed error message from the response body
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Error',
                                text: errorMessage
                            });
                            console.error("AJAX Error:", status, error);
                            console.error("Response:", xhr.responseText); // Good for debugging raw output
                        }
                    });

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Client Status Changed',
                        html: 'The profile has been <b>activated</b> successfully.'
                    });
                }
            });
            return;
        }

        if (action == 'restrict') {

            if (statusCell == "restricted") {
                Swal.fire({
                    ...swalOptions,
                    title: 'No Changes Made',
                    html: `This profile is already ${statusCell}`,
                    iconHtml: '<i class="bx bx-info-circle"></i>'
                });
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                html: `
                    Do you want to <b>RESTRICT</b> the profile of <br>Client ID: ${ActiveRowId}?
                    <br><br>
                    <small>Restricted profile blocks service requests but allows website access.</b></small>
                `,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Do your restriction logic here (e.g., AJAX request)
                    $.ajax({
                        url: 'handlers/manageClients-handler.php',
                        method: 'POST',
                        dataType: 'json', // <-- this is important
                        data: {
                            rowId: ActiveRowId,
                            statusChange: action
                        },
                        success: function(response) {
                            if (response.statusUpdate === true) {

                                $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`).text('Restricted');
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class="bx bx-check-circle"></i>',
                                    title: 'Client Status Changed',
                                    html: response.message
                                });

                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                                    title: 'Error Updating Client Status',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = 'Something went wrong while updating client status.';

                            // Try to extract more detailed error message from the response body
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                ...swalOptions,
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Error',
                                text: errorMessage
                            });
                            console.error("AJAX Error:", status, error);
                            console.error("Response:", xhr.responseText); // Good for debugging raw output
                        }
                    });
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Client Status Changed',
                        html: 'The profile has been <b>restricted</b> successfully.'
                    });
                }
            });
            return;
        }
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