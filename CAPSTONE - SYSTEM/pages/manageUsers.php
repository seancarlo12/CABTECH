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
    <title>Manage Users</title>
    <link rel="stylesheet" href="style/manageUsers.css">
    <script>
        // init
        initTooltip();
        initializeSelect2();

        //wont run if no css - pages.css
        window.onload = function() {
            const cssFile = document.getElementById("css-check");
            if (!cssFile || !cssFile.sheet) {
                document.body.innerHTML = '<h1 style="color: red; text-align: center; margin-top: 20%;">CSS file missing. Page cannot be displayed.</h1>' +
                    '<p style="text-align: center;"> <a href="javascript:history.back()" style="color: blue; text-decoration: underline;">Go Back</a></p>';
            }
        };


        // table filtering
        $('#myTable thead tr').clone(true).addClass('filters').appendTo('#myTable thead');

        //types of filters
        const columnFilters = [{
                type: 'none'
            },
            {
                type: 'text'
            },
            {
                type: 'select',
                options: ['Active', 'Deactivated']
            },
            {
                type: 'text'
            },
            {
                type: 'select',
                options: ['Admin', 'Mechanic', 'Super Admin'],
                match: 'exact'
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
                type: 'select',
                options: ['In', 'Out']
            }
        ];

        // apply filters
        $('#myTable thead tr.filters th').each(function(i) {
            const filter = columnFilters[i];

            // Skip if none or undefined
            if (!filter || filter.type === 'none') {
                $(this).html('');
                return;
            }

            if (filter.type === 'select') { // for Select filter
                const matchType = filter.match;
                let selectHtml = `<select class="form-select"><option value="">All</option>`;
                filter.options.forEach(opt => {
                    selectHtml += `<option value="${opt}">${opt}</option>`;
                });
                selectHtml += `</select>`;
                $(this).html(selectHtml);

                // Apply search on change
                $('select', this).on('change', function() {
                    const val = this.value.trim();
                    if (matchType === 'exact') {
                        const escapedVal = $.fn.dataTable.util.escapeRegex(val);
                        table
                            .column(i)
                            .search(val ? `^${escapedVal}$` : '', true, false)
                            .draw();
                    } else {
                        table
                            .column(i)
                            .search(val)
                            .draw();
                    }
                });
            } else if (filter.type === 'text') { //for search filter
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

        // table initialized
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
                    targets: [5] //column 5: contact number
                },
                {
                    width: '200px',
                    targets: 7 // column: email
                }
            ]
        });

        // Clear all filters every reload
        table.search('').columns().search('').draw(false);
        $('#myTable thead tr.filters th').each(function() {
            $(this).find('input, select').val('');
        });


        //selected row variable
        let ActiveRowId = null;
        let activeRow = null;


        // Initialize Select2 for service history
        function initializeSelect2() {
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
                <p class="ellipsis-tooltip"><b>Total Billing:</b> P ${formatCurrency(selectedService.total_billing)}</p>
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


        function toggleRow(tr) {
            const row = table.row(tr);
            const isOpen = row.child.isShown();
            const rowData = row.data();

            localStorage.setItem('activeRowIndex', row.index()); //save active row for re load - keep state

            // close row if already open
            if (activeRow === tr && isOpen) {
                closeRow(tr);

                return;
            }


            // Close previously opened row if any
            if (activeRow && activeRow !== tr) {
                closeRow(activeRow);
                activeRow.classList.remove('active-row'); // Remove active from old one
            }

            //sets active row id
            ActiveRowId = rowData[1];
            localStorage.setItem('RowId', ActiveRowId);

            $.ajax({
                url: 'handlers/manageUsers-handler.php',
                method: 'POST',
                data: {
                    row_id: ActiveRowId,
                    action: 'getRowData'
                },
                success: function(response) {
                    if (response.account) {
                        userAccount = response.account;
                    } else {
                        userAccount = null;
                    }
                    serviceHistory = response.service_history
                    mechanic_specialty = response.specialties
                    row.child(format(rowData)).show();
                    tr.classList.add('shown', 'active-row');
                    activeRow = tr;


                    initTooltip();
                    initializeSelect2();
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


        function closeRow(tr) {
            const row = table.row(tr);
            if (row.child.isShown()) {
                row.child.hide();
                tr.classList.remove('shown');
                if (activeRow === tr) {
                    localStorage.setItem('expandedRowIndex', null);
                }
                userAccount = null
            }
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

            // Clear out previously active row if it's not in visible rows (filtered / in other page)
            if (activeRow && !currentlyVisibleRows.includes(activeRow)) {
                table.row(activeRow).child.hide();
                activeRow.classList.remove('active-row', 'shown');
                localStorage.removeItem('RowId');
                activeRow = null;
                ActiveRowId = null;
            }

            //If a different row is currently toggled, close it
            if (activeRow && activeRow !== tr) {
                table.row(activeRow).child.hide();
                activeRow.classList.remove('active-row', 'shown');
            }

            // If this row is already open, close and deactivate it 
            if (isActive && !isOpen) {
                unsetActiveRow();
                return;
            }

            // Activate current row
            $('tr.active-row').removeClass('active-row');

            //highlight active row
            tr.classList.add('active-row');

            //Store values
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

        window.format = function(rowData) {
            service_history = serviceHistory;
            console.log(mechanic_specialty);


            const specialties = mechanic_specialty || [];
            const isMechanic = rowData[4]?.trim().toLowerCase() === 'mechanic';
            const specialtiesHtml = isMechanic ? `
                <div class="specialties mt-2">
                    <span class="specialties-line">
                        <b>SPECIALTIES</b> 
                        <button id="manageMechSpecialty"><i class="bx bx-wrench"></i> Manage Specialty</button>
                    </span>
                    <div id="specialties-box">
                        ${specialties.length > 0
                            ? specialties.map(spec => `<span class="badge text-bg-secondary">${spec.specialty_name}</span>`).join('')
                            : '<span class="text-muted">No specialties assigned</span>'}
                    </div>
                </div>
            ` : '';
            // Build service history options dynamically
            const historyOptions = serviceHistory && serviceHistory.length > 0 ?
                serviceHistory.map((s, i) => `<option value="${i}" ${i === 0 ? 'selected' : ''}>Request ID: ${s.request_id}</option>`).join('') :
                '<option selected>No history</option>';

            return `
        <div id="details-row">
            <div id="personal-info">
                <b>PERSONAL INFORMATION</b>
                <p class="ellipsis-tooltip martop text-uppercase fw-bold">${rowData[4]}</p>
                <p class="ellipsis-tooltip">${rowData[3]}</p>
                <p class="ellipsis-tooltip">${rowData[5]}</p>
                <p class="ellipsis-tooltip">${rowData[7]}</p>
                <p class="ellipsis-tooltip address-only">${rowData[6]}</p>
            </div>
            <div id="account-info">
                <b>ACCOUNT INFORMATION</b>
                ${userAccount ? `
                    <p class="ellipsis-tooltip martop"><b>Account ID: </b>${userAccount.account_id}</p>
                    <p class="ellipsis-tooltip"><b>Username: </b>${userAccount.username}</p>
                    <p class="ellipsis-tooltip"><b>Date Created: </b>${userAccount.date_created}</p>
                    <button id="editAccount" class="mt-2"><i class="bx bx-user-plus"></i>Edit Account</button>
                </br></br>` : `<p class="martop">No account linked</p><button id="addAccount" class="mt-2"><i class="bx bx-user-plus"></i>Make Account</button></br></br>`}
                ${specialtiesHtml}
            </div>
            ${isMechanic ? `
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
                </div>` : ''}
        </div>
    `;
        };


        // Saves the currently expanded row index
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

                    // Only expand if it was expanded before reload
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


        // Row click event handler - expand row button
        document.querySelector('#myTable tbody').addEventListener('click', function(e) {
            const td = e.target.closest('td.dt-control');
            if (!td) return;
            const tr = td.closest('tr');
            toggleRow(tr);
        });

        // reset active row if table filtered
        $('#myTable').on('order.dt page.dt search.dt length.dt', function() {
            unsetActiveRow();
        });

        // Load previously expanded row after table renders
        loadExpandedRows();
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>manage users</span></p>
        <div id="table-box"><i class="bx bx-help-circle" title="help" id="helpicon"></i>

            <div id="buttons"><!-- BUTTONS FOR CRUD FUNCTIONS -->
                <?php if ($_SESSION['User_role'] === 'Super Admin'): ?>

                    <button id="addbtn" type="button" data-bs-target="#addUserModal" data-bs-toggle="modal"><i class='bx bx-plus'></i><span>Add</span></button>
                    <button id="editbtn"><i class='bx bx-pencil'></i><span>Edit</span></button>
                    <button id="activatebtn" onclick="changeAccStatus('activate');"><i class='bx bxs-circle'></i><span>Activate</span></button>
                    <button id="deacbtn" onclick="changeAccStatus('deactivate');"><i class='bx bxs-circle'></i><span>Deactivate</span></button>

                <?php endif; ?>

                <button id="manageSpecialties"><i class='bx bx-wrench'></i><span>Manage All Specialties</span></button>
            </div>
            <div id="mesa">
                <!-- TABLE USES DATABLES LIBRARY -->
                <table id="myTable">

                    <thead>
                        <tr class="column-heads">
                            <th></th>
                            <th>User ID</th>
                            <th>Status</th>
                            <th>Full Name <span id="sub" class="text-capitalize text-muted">(LastName, FirstName, MiddleName)</span></th>
                            <th>Role</th>
                            <th>Contact Number</th>
                            <th>Address <span id="sub" class="text-capitalize text-muted">(#HouseNo, Street, City, Province)</span></th>
                            <th>Email</th>
                            <th>Work Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //POPULATING THE TABLE WITH VALUES FROM DB

                        $query = "";

                        if ($_SESSION['User_role'] === 'Super Admin') {
                            // Super Admin sees Super Admin + Admin
                            $query = "SELECT * FROM userstbl WHERE role IN ('Super Admin', 'Admin')";
                        } else {
                            // Admin or others see mechanics only
                            $query = "SELECT * FROM userstbl WHERE role = 'Mechanic'";
                        }

                        $rs = mysqli_query($db_connection, $query);

                        while ($rw = mysqli_fetch_array($rs)) {
                            $workStatus = $rw['work_status'];
                            $statusIcon = '';

                            if ($workStatus === 'In') {
                                $statusIcon = '<i class="bx bxs-log-in text-success" title="In" style="font-size: 1.2rem;"></i>';
                            } elseif ($workStatus === 'Out') {
                                $statusIcon = '<i class="bx bxs-log-out text-danger" title="Out" style="font-size: 1.2rem;"></i>';
                            }

                            echo
                            '
                        <tr  class="data-rows" data-id="' . $rw['user_id'] . '" oncontextmenu="toggleRow(this); return false;" onclick = "setActiveRow(this);">
                        <td class="dt-control"><i class="bx bx-chevron-down"></i></td>
                        <td  id="aydis">' . $rw['user_id'] . '</td>
                        <td  data-role ="status">' . $rw['status'] . '</td>
                        <td data-role ="fullname" class="ellipsis-tooltip">' . $rw['last_name'] . ', ' . $rw['first_name'] . (!empty($rw['middle_name']) ? ', ' . $rw['middle_name'] : '') . '</td>
                        <td data-role ="role">' . $rw['role'] . '</td>
                        <td data-role ="contact_number">' . $rw['contact_number'] . '</td>
                        <td data-role ="address" class="ellipsis-tooltip">' . $rw['address'] . '</td>
                        <td data-role ="email" class="ellipsis-tooltip">' . $rw['email'] . '</td>
                        <td data-role="work_status" class="ellipsis-tooltip">
                            <button
                            class="btn btn-sm btn-toggle-work"
                            data-userid="' . $rw['user_id'] . '"
                            data-status="' . htmlspecialchars($workStatus, ENT_QUOTES) . '">
                            ' . $statusIcon . ' ' . htmlspecialchars($workStatus) . '
                            </button>
                        </td>
                        </tr>
                        ';
                        }


                        ?>
                    </tbody>

                </table>
            </div>


        </div>
        <!-- MODALSS -->


        <!-- ADD USER MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addUserModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add user information</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="resetAddForm();"></button>
                    </div>
                    <div class="modal-body">
                        <form action="" method="POST" id="addUserForm" onsubmit="return validateNewUserForm();">
                            <label for="userRole">User Role</label>
                            <select id="userRole" name="user-role" class="form-control" required></select>

                            <label for="firstName">First Name</label>
                            <input class="form-control" type="text" id="firstName" name="first_name" placeholder="Enter User's First Name" required>

                            <label for="lastName">Last Name</label>
                            <input class="form-control" type="text" id="lastName" name="last_name" placeholder="Enter User's Last Name" required>

                            <label for="middleName">Middle Name</label>
                            <input type="checkbox" id="enableMiddleName" checked>
                            <input class="form-control" type="text" id="middleName" name="middle_name" placeholder="Enter User's Middle Name">

                            <label for="mobile">Mobile Number</label>
                            <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="mobile" name="mobile_number" placeholder="Enter User's Mobile Number" maxlength="11" required>

                            <label for="email">Email</label>
                            <input class="form-control" type="email" id="email" name="email" placeholder="Enter User's Email">

                            <label for="address">Address</label>
                            <input class="form-control" type="text" id="address" name="address" placeholder="Enter User's Address">
                            <div id="passwordHelpBlock" class="form-text">
                                Please enter the address in the format: <strong>House Number, Barangay, City</strong>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetAddForm();">Cancel</button>
                                <button type="submit" id="addUserbtn" class="btn btn-primary"> <i class="bx bx-plus"></i>Add Record</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <!-- EDIT USER MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editUserModal" aria-hidden="true" aria-labelledby="editUserLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="editUserLabel">Edit User Information</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="resetEditForm();"></button>
                    </div>
                    <div class="modal-body">
                        <form action="" id="editUserForm" method="POST" onsubmit="return validateUpdatedUserForm();">
                            <label for="editFirstName">First Name</label>
                            <input class="form-control" type="text" id="editFirstName" name="first_name" placeholder="Enter User's First Name" required>

                            <label for="editLastName">Last Name</label>
                            <input class="form-control" type="text" id="editLastName" name="last_name" placeholder="Enter User's Last Name" required>

                            <label for="editMiddleName">Middle Name</label>
                            <input type="checkbox" id="enableEditMiddleName" checked>
                            <input class="form-control" type="text" id="editMiddleName" name="middle_name" placeholder="Enter User's Middle Name">

                            <label for="editMobile">Mobile Number</label>
                            <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="editMobile" name="mobile_number" placeholder="Enter User's Mobile Number" maxlength="11" required>

                            <label for="editEmail">Email</label>
                            <input class="form-control" type="email" id="editEmail" name="email" placeholder="Enter User's Email">

                            <label for="editAddress">Address</label>
                            <input class="form-control" type="text" id="editAddress" name="address" placeholder="Enter User's Address">
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


        <!-- EDIT ACCOUTN MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editAccountModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">edit user account</h1>
                        <button type="button" class="btn-close" onclick="discardChanges('edit')" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <label for="username-inpt" class="form-label">Username</label>
                        <input type="text" id="username-inpt" class="form-control" placeholder="Enter Username" autocomplete="off">
                        <div class="mt-4">
                            <label for="password-inpt" class="form-label">Password</label>

                            <div class="input-group">
                                <input type="password" id="password-inpt" class="form-control" placeholder="Leave blank to keep current password" autocomplete="off">
                                <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('password-inpt', 'eye-icon')">
                                    <i class="bx bx-show" id="eye-icon"></i>
                                </span>
                            </div>
                        </div>

                        <p class="text-muted small mt-3">
                            <i class="bx bx-info-circle"></i> You are editing a user's account information. Please proceed with caution.
                        </p>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="discardChanges('edit')">Cancel</button>
                            <button id="saveEditedAccount" class="btn btn-primary"> <i class="bx bx-save"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- CREATE ACCOUNT MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="makeAccountModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">make user account</h1>
                        <button type="button" onclick="discardChanges('make')" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <label for="make-username-inpt" class="form-label">Username</label>
                        <input type="text" id="make-username-inpt" class="form-control" placeholder="Enter Username" autocomplete="off">
                        <div class="mt-4">
                            <label for="make-password-inpt" class="form-label">Password</label>

                            <div class="input-group">
                                <input type="password" id="make-password-inpt" class="form-control" placeholder="Enter Password" autocomplete="off">
                                <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('make-password-inpt', 'eye-icon-make')">
                                    <i class="bx bx-show" id="eye-icon-make"></i>
                                </span>
                            </div>
                        </div>

                        <p class="text-muted small mt-3">
                            <i class="bx bx-info-circle"></i> You are linking a system account to this user. Please review the details carefully.
                        </p>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="discardChanges('make')">Cancel</button>
                            <button id="saveNewAccount" class="btn btn-primary"> <i class="bx bx-save"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- MANAGE SPECIALTIES MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="ManageSpecialtiesModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage specialties</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <button id="add-specialty"><i class="bx bx-plus"></i><span> Add a Specialty</span></button>
                        <div class="table-container">
                            <table id="manageSpecialtiesTable">
                                <thead>
                                    <tr>
                                        <th>NAME</th>
                                        <th>DESCRIPTION</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- EDIT SPECIALTY MODAL -->
        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editSpecialtyModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage specialties</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="specialty-id" id="specialty-id">

                        <div class="mb-3">
                            <label for="specialty-name" class="form-label">Specialty Name</label>
                            <input type="text" class="form-control" id="specialty-name" placeholder="Enter Specialty Name"
                                <?php if ($_SESSION['User_role'] !== 'Super Admin'): ?>readonly<?php endif; ?>>
                            <small><i class="bx bx-info-circle"></i>Only Super Admin can change specialty name</small>
                        </div>

                        <div class="mb-3">
                            <label for="specialty-description" class="form-label">Description</label>
                            <textarea class="form-control" id="specialty-description" rows="3" placeholder="Enter Specialty Description..."></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-target="#ManageSpecialtiesModal" data-bs-toggle="modal">Back</button>
                            <button id="saveEditedSpecialty" class="btn btn-primary"> <i class="bx bx-save"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addSpecialtyModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage specialties</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="specialty-id" id="specialty-id">


                        <div class="mb-3">
                            <label for="add-specialty-name" class="form-label">Specialty Name</label>
                            <input type="text" class="form-control" id="add-specialty-name" placeholder="Enter Specialty Name">
                        </div>

                        <div class="mb-3">
                            <label for="add-specialty-description" class="form-label">Description</label>
                            <textarea class="form-control" id="add-specialty-description" rows="3" placeholder="Enter Specialty Description..."></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-target="#ManageSpecialtiesModal" data-bs-toggle="modal">Back</button>
                            <button id="addSpecialty" class="btn btn-primary"> <i class="bx bx-plus"></i>Add Specialty</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>




        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="manageMechSpecialtyModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage mechanic specialties</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Left: all specialties (checkbox list) -->
                            <div id="all-specialties" class="col-6">
                                <h6>All Specialties</h6>
                                <input type="text" id="specialtySearch" class="form-control mb-2" placeholder="Search specialties...">
                                <div id="allSpecialtiesList" style="max-height: 320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                    <!-- populated by JS -->
                                    Loading...
                                </div>
                            </div>

                            <!-- Right: selected specialties -->
                            <div class="col-6">
                                <h6>Mechanics Specialties</h6>
                                <ul id="selectedSpecialtiesList" style="list-style:none; padding-left:0; max-height:320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                    <!-- populated by JS -->
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                            <button id="confirmSpecialty" class="btn btn-primary"> <i class="bx bx-plus"></i>Confirm Specialties</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>




        <!-- BACK TO TOP BUTTON -->
        <a href="#top" title="Go up"><i class='bx bx-chevron-up' id="goTop"></i></a>
    </div>
    </div>
</body>



</div>
<script>
    $(document).on('click', '.btn-toggle-work', function(e) {
        e.preventDefault();
        const btn = $(this);
        const userId = btn.data('userid');
        const current = String(btn.data('status') || '').trim();
        const newStatus = (current.toLowerCase() === 'in') ? 'Out' : 'In';


        btn.prop('disabled', true);

        $.ajax({
            url: 'handlers/manageUsers-handler.php', // create this endpoint (see PHP below)
            method: 'POST',
            dataType: 'json',
            data: {
                user_id: userId,
                work_status: newStatus,
                action: 'updateWorkStatus'
            },
            success: function(resp) {
                console.log(resp);
                // resp should be JSON { success: true, work_status: "In"|"Out", iconHtml: "<i...>" }
                if (typeof resp === 'string') {
                    try {
                        resp = JSON.parse(resp);
                    } catch (err) {
                        alert('Invalid response');
                        return;
                    }
                }
                if (resp.success) {
                    console.log(resp);
                    btn.data('status', resp.work_status);
                    btn.html((resp.iconHtml || '') + ' ' + resp.work_status);
                    // optional: tiny visual cue
                    btn.addClass('active');
                    setTimeout(() => btn.removeClass('active'), 400);
                } else {
                    alert(resp.message || 'Update failed');
                }
            },
            error: function(resp) {
                console.log(resp);
                alert('Request failed — check your network or server.');
            },
            complete: function() {
                btn.prop('disabled', false);
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
    // for validateUpdatedUserForm function
    const modifiedData = {};

    //stores current selected user data
    let user;

    // stores the logged in user's role
    const currentUserRole = "<?php echo $_SESSION['User_role']; ?>";



    //MIDDLE NAME CHECK BOX
    document.getElementById('enableMiddleName').addEventListener('change', function() {
        document.getElementById('middleName').disabled = !this.checked;
        document.getElementById('middleName').value = '';
    });
    document.getElementById('enableEditMiddleName').addEventListener('change', function() {
        document.getElementById('editMiddleName').disabled = !this.checked;
        document.getElementById('editMiddleName').value = '';
    });


    // DISPLAYS ROLE SELECT WHEN ADDING A USER
    $(document).on('shown.bs.modal', '#addUserModal', function() {
        const $select = $('#userRole');
        $select.empty(); // Clear previous options

        // Add placeholder
        $select.append(new Option('Select User Role', '', true, true)).prop('disabled', false);

        const roles = ['Mechanic', 'Admin', 'Super Admin'];

        const filtered = currentUserRole === 'Super Admin' ?
            roles // Super Admin sees all roles
            :
            ['Mechanic', 'Admin']; // Non-Super users see only Mechanic

        if (filtered.length === 1) {
            // Only one role → auto-select it
            $select.append(new Option(filtered[0], filtered[0], true, true));
            $select.prop('disabled', true); // optionally disable dropdown
        } else {
            // Multiple roles → show placeholder

            filtered.forEach(role => {
                $select.append(new Option(role, role));
            });

        }
        $select.find('option:first').attr('disabled', true); // placeholder not selectable
    });


    function addUserRecord(userData) {
        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json', // <-- this is important
            data: {
                user_data: userData,
                action: 'add'
            },
            success: function(response) {
                if (response.status == 'success') {
                    $('#addUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-check-circle\'></i>',
                        title: 'User Added Successfully',
                        text: response.message
                    }).then(() => {

                        $('#addUserForm')[0].reset();

                        location.reload();
                    });
                } else if (response.status == 'existing') {
                    $('#addUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Existing User Record',
                        text: response.message
                    }).then(() => {
                        $('#addUserModal').modal('show');
                    });
                } else {
                    $('#addUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                        title: 'Error Adding User',
                        text: response.message
                    }).then(() => {
                        $('#addUserModal').modal('show');
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#addUserModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                    title: 'Error',
                    text: 'Something went wrong while saving the user.'
                }).then(() => {
                    $('#addUserModal').modal('show');
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


    function validateUpdatedUserForm() {

        //  Clear all previous keys while keeping the same object reference
        for (let key in modifiedData) {
            delete modifiedData[key];
        }

        const firstName = document.getElementById('editFirstName').value;
        const lastName = document.getElementById('editLastName').value;
        const middleName = document.getElementById('editMiddleName').value;
        const mobile = document.getElementById('editMobile').value;
        const email = document.getElementById('editEmail').value;
        const address = document.getElementById('editAddress').value;

        const normalize = (val) => (val || "").trim();

        const mobilePattern = /^09\d{9}$/;

        if (!mobilePattern.test(mobile)) {
            $('#editUserModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Mobile Number',
                text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
            }).then(() => {
                // Show modal again after Swal closes
                $('#editUserModal').modal('show');
            });
            return false;
        }

        if (normalize(firstName) !== normalize(user.first_name)) {
            modifiedData.first_name = firstName;
        }
        if (normalize(lastName) !== normalize(user.last_name)) {
            modifiedData.last_name = lastName;
        }
        if (normalize(middleName) !== normalize(user.middle_name)) {
            modifiedData.middle_name = middleName;
        }
        if (normalize(mobile) !== normalize(user.contact_number)) {
            modifiedData.contact_number = mobile;
        }
        if (normalize(email) !== normalize(user.email)) {
            modifiedData.email = email;
        }
        if (normalize(address) !== normalize(user.address)) {
            modifiedData.address = address;
        }

        if (Object.keys(modifiedData).length === 0) {
            $('#editUserModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Changes Made',
                text: 'Make changes before saving.'
            }).then(() => {
                $('#editUserModal').modal('show');
            });
            return false;
        }

        // Include the user's ID or identifier for update
        modifiedData.user_id = user.user_id;


        updateUserRecord(modifiedData);
        return false;
    }


    function updateUserRecord(updatedUserData) {

        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json', // Important: expecting JSON from PHP
            data: {
                user_data: updatedUserData,
                action: 'update'
            },
            success: function(response) {
                if (response.status === 'success') {
                    $('#editUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'User Updated Successfully',
                        text: response.message
                    });

                    const row = table.row(`tr[data-id="${updatedUserData.user_id}"]`);

                    // Get and update row data
                    let rowData = row.data();

                    // change the text to the updated details without reload
                    if (modifiedData.last_name || modifiedData.first_name || modifiedData.middle_name) {
                        const fullLast = modifiedData.last_name ?? user.last_name;
                        const fullFirst = modifiedData.first_name ?? user.first_name;
                        const fullMiddle = modifiedData.middle_name !== undefined ? modifiedData.middle_name : user.middle_name;

                        rowData[3] = `${fullLast}, ${fullFirst}` + (fullMiddle ? `, ${fullMiddle}` : '');

                        //  Update user object
                        if (modifiedData.first_name) user.first_name = modifiedData.first_name;
                        if (modifiedData.last_name) user.last_name = modifiedData.last_name;
                        if (modifiedData.middle_name !== undefined) user.middle_name = modifiedData.middle_name;
                    }
                    if (modifiedData.contact_number) {
                        rowData[5] = modifiedData.contact_number;
                        user.contact_number = modifiedData.contact_number;
                    }
                    if (modifiedData.email) {
                        rowData[7] = modifiedData.email;
                        user.email = modifiedData.email;
                    }
                    if (modifiedData.address) {
                        rowData[6] = modifiedData.address;
                        user.address = modifiedData.address;
                    }
                    // Apply changes and redraw
                    row.data(rowData).invalidate().draw(false);

                    // Refresh the expanded child row
                    setTimeout(() => {
                        $(`tr[data-id="${updatedUserData.user_id}"]`).find('.dt-control').click();
                    }, 150);

                } else if (response.status === 'existing') {
                    $('#editUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Duplicate Record',
                        text: response.message
                    }).then(() => {
                        $('#editUserModal').modal('show');
                    });

                } else {
                    $('#editUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Update Failed',
                        text: response.message
                    }).then(() => {
                        $('#editUserModal').modal('show');
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#editUserModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Error',
                    text: 'Something went wrong while updating the user.'
                }).then(() => {
                    $('#editUserModal').modal('show');
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
            url: 'handlers/manageUsers-handler.php',
            type: 'POST',
            data: {
                userId: ActiveRowId,
                action: 'getUserRecord'
            },
            success: function(data) {
                if (data.status) {
                    user = data.user;

                    $('#editFirstName').val(user.first_name);
                    $('#editLastName').val(user.last_name);
                    $('#editMiddleName').val(user.middle_name);
                    $('#editMobile').val(user.contact_number);
                    $('#editEmail').val(user.email);
                    $('#editAddress').val(user.address);


                    if (!user.middle_name) {
                        $('#enableEditMiddleName').prop('checked', false);
                        $('#editMiddleName').prop('disabled', true).val('');
                    } else {
                        $('#enableEditMiddleName').prop('checked', true);
                        $('#editMiddleName').prop('disabled', false);
                    }


                    $('#editUserModal').modal('show');

                } else {
                    console.warn("User not found:", data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading user record:", error);
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
            normalize(firstName) !== normalize(user.first_name) ||
            normalize(lastName) !== normalize(user.last_name) ||
            normalize(middleName) !== normalize(user.middle_name) ||
            normalize(mobile) !== normalize(user.contact_number) ||
            normalize(email) !== normalize(user.email) ||
            normalize(address) !== normalize(user.address)) {
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
                    $('#editUserModal').modal('show');
                }
            });
        } else {
            clearEditInputs();
        }

    }


    function clearEditInputs() {
        $('#editFirstName, #editLastName, #editMiddleName, #editMobile, #editEmail, #editAddress').val('');
        $('#enableEditMiddleName').prop('checked', true);
        user = null;
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
                html: 'Do you want to <b>DEACTIVATE</b> the profile of <br>User ID: ' + ActiveRowId,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Do your deactivation logic here (e.g., AJAX request)
                    $.ajax({
                        url: 'handlers/manageUsers-handler.php',
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
                                    title: 'User Status Changed',
                                    html: response.message
                                });
                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                                    title: 'Error Updating User Status',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = 'Something went wrong while updating user status.';

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
                html: 'Do you want to <b>ACTIVATE</b> the profile of <br>User ID: ' + ActiveRowId,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'No',
                confirmButtonText: 'Yes!',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Do your activation logic here (e.g., AJAX request)
                    $.ajax({
                        url: 'handlers/manageUsers-handler.php',
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
                                    title: 'User Status Changed',
                                    html: response.message
                                });
                            } else {
                                Swal.fire({
                                    ...swalOptions,
                                    iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                                    title: 'Error Updating User Status',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = 'Something went wrong while updating user status.';

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
                        title: 'User Status Changed',
                        html: 'The profile has been <b>activated</b> successfully.'
                    });
                }
            });
            return;
        }
    }


    function discardChanges(modalType) {
        const isEdit = modalType === 'edit';

        const usernameId = isEdit ? '#username-inpt' : '#make-username-inpt';
        const passwordId = isEdit ? '#password-inpt' : '#make-password-inpt';
        const modalId = isEdit ? '#editAccountModal' : '#makeAccountModal';

        const username = $(usernameId).val().trim();
        const password = $(passwordId).val().trim();

        const hasChanges = isEdit ?
            password !== '' || username !== userAccount.username :
            username !== '' || password !== '';

        if (hasChanges) {
            Swal.fire({
                title: 'Discard changes?',
                text: "You have unsaved input. Do you want to discard it?",
                iconHtml: '<i class="bx bx-info-circle"></i>',
                showCancelButton: true,
                confirmButtonText: 'Yes, discard',
                cancelButtonText: 'Keep Editing'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(usernameId).val('');
                    $(passwordId).val('');
                } else {
                    $(modalId).modal('show');
                }
            });
        }
    }


    $(document).on('click', '#addAccount', function() {

        $('#make-username-inpt').val('');
        $('#make-password-inpt').val('');

        $('#makeAccountModal').modal('show');
    });


    $(document).on('click', '#editAccount', function() {
        $('#username-inpt').val(userAccount.username);
        $('#password-inpt').val('');

        $('#editAccountModal').modal('show');
    });


    $(document).on('click', '#saveEditedAccount', function() {
        const username = $('#username-inpt').val().trim();
        const password = $('#password-inpt').val().trim();
        const accountId = userAccount.account_id;


        if (username === userAccount.username && password === '') {
            $('#editAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Changes Made',
                text: 'Make changes before saving'
            }).then(() => {
                $('#editAccountModal').modal('show');
            });
            return;
        }

        //  Username validation
        if (username === '') {
            $('#editAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Missing Username',
                text: 'Username cannot be empty.'
            }).then(() => {
                $('#editAccountModal').modal('show');
            });
            return;
        }

        if (username.length < 6) {
            $('#editAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Username Too Short',
                text: 'Username must be at least 6 characters long.'
            }).then(() => {
                $('#editAccountModal').modal('show');
            });
            return;
        }

        const usernamePattern = /^[A-Za-z0-9_]+$/;
        if (!usernamePattern.test(username)) {
            $('#editAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Invalid Username',
                html: 'Only <strong>letters</strong>, <strong>numbers</strong>, and <strong>underscore (_)</strong> are allowed.'
            }).then(() => {
                $('#editAccountModal').modal('show');
            });
            return;
        }

        //  Password validations (only if changed)
        if (password !== '') {
            if (password.length < 6) {
                $('#editAccountModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Weak Password',
                    text: 'Password must be at least 6 characters.'
                }).then(() => {
                    $('#editAccountModal').modal('show');
                });
                return;
            }

            const allowedPattern = /^[A-Za-z0-9_]+$/;
            if (!allowedPattern.test(password)) {
                $('#editAccountModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Invalid Password',
                    html: 'Only <strong>letters</strong>, <strong>numbers</strong>, and <strong>underscore (_)</strong> are allowed.'
                }).then(() => {
                    $('#editAccountModal').modal('show');
                });
                return;
            }
        }
        // Proceed with update via AJAX
        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'updateAccount',
                account_id: accountId,
                username: username,
                password: password, // may be empty
                userId: ActiveRowId
            },
            success: function(response) {
                if (response.status === 'success') {
                    $('#editAccountModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Account Updated',
                        text: response.message
                    });

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
                    // Update local object
                    userAccount.username = username;
                } else {
                    Swal.fire({
                        ...swalOptions,
                        title: 'Update Failed',
                        text: response.message
                    });
                }
            }
        });
    });


    $(document).on('click', '#saveNewAccount', function() {
        const username = $('#make-username-inpt').val().trim();
        const password = $('#make-password-inpt').val().trim();


        if (username === '' || password === '') {
            $('#makeAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'Missing Input',
                text: 'Please fill in the required fields.'
            }).then(() => {
                $('#makeAccountModal').modal('show');
            });
            return;
        }

        if (username.length < 6) {
            $('#makeAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Username Too Short',
                text: 'Username must be at least 6 characters long.'
            }).then(() => {
                $('#makeAccountModal').modal('show');
            });
            return;
        }

        const usernamePattern = /^[A-Za-z0-9_]+$/;
        if (!usernamePattern.test(username)) {
            $('#makeAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Invalid Username',
                html: 'Only <strong>letters</strong>, <strong>numbers</strong>, and <strong>underscore (_)</strong> are allowed.'
            }).then(() => {
                $('#makeAccountModal').modal('show');
            });
            return;
        }

        if (password.length < 6) {
            $('#makeAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Weak Password',
                text: 'Password must be at least 6 characters.'
            }).then(() => {
                $('#makeAccountModal').modal('show');
            });
            return;
        }

        const allowedPattern = /^[A-Za-z0-9_]+$/;
        if (!allowedPattern.test(password)) {
            $('#makeAccountModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Invalid Password',
                html: 'Only <strong>letters</strong>, <strong>numbers</strong>, and <strong>underscore (_)</strong> are allowed.'
            }).then(() => {
                $('#makeAccountModal').modal('show');
            });
            return;
        }


        // Proceed with adding via AJAX
        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'addAccount',
                username: username,
                password: password,
                userId: ActiveRowId
            },
            success: function(response) {
                if (response.status && response.status === 'success') {
                    $('#makeAccountModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Account Linked',
                        text: response.message || 'The account was successfully linked.'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    $('#makeAccountModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Failed to Link Account',
                        text: response.message || 'Something went wrong. Please try again.'
                    });

                }
            }
        });
    });





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


    function togglePassword(pw_input, eye_icon) {
        const passwordInput = document.getElementById(pw_input);
        const eyeIcon = document.getElementById(eye_icon);

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('bx-show');
            eyeIcon.classList.add('bx-hide');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('bx-hide');
            eyeIcon.classList.add('bx-show');
        }
    }


    function validateNewUserForm() {
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
        const userRole = document.getElementById("userRole")?.value.trim();
        const mobilePattern = /^09\d{9}$/;




        if (!firstName || !lastName || !mobile || !email) {
            $('#addUserModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Missing Required Fields',
                text: 'Please fill in First Name, Last Name, and Mobile Number.'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addUserModal').modal('show');
            });
            return false;
        }

        if (!mobilePattern.test(mobile)) {
            $('#addUserModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Mobile Number',
                text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addUserModal').modal('show');
            });
            return false;
        }

        if (email && !email.includes("@")) {
            $('#addUserModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                title: 'Invalid Email',
                text: 'Please enter a valid email address.'
            }).then(() => {
                // Show modal again after Swal closes
                $('#addUserModal').modal('show');
            });
            return false;
        }

        addUserRecord({
            role: userRole,
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
                    $('#addUserForm')[0].reset();
                } else {
                    $('#addUserModal').modal('show');
                }
            });
        }



    }




    let specialties = []; // global cache

    function populateSpecialtiesTable(specialties) {
        const tbody = document.querySelector("#manageSpecialtiesTable tbody");
        tbody.innerHTML = ""; // Clear current rows

        specialties.forEach(spec => {
            const row = document.createElement("tr");
            row.innerHTML = `
            <td>${spec.name}</td>
            <td>${spec.description || ''}</td>
            <td class="action-buttons d-flex gap-2">
                <button class="edit-specialty" data-id="${spec.specialty_id}">
                    <i class="bx bx-edit"></i>
                </button>
                <button class="delete-specialty" data-id="${spec.specialty_id}">
                    <i class="bx bx-trash"></i>
                </button>
            </td>
        `;
            tbody.appendChild(row);
        });

        // ✅ Edit handler
        tbody.querySelectorAll(".edit-specialty").forEach(btn => {
            btn.addEventListener("click", () => {
                const specialtyId = btn.dataset.id; // keep as string
                editingSpecialtyId = specialtyId;

                const specialty = specialties.find(s => s.specialty_id === specialtyId);
                if (!specialty) {
                    console.warn("Specialty not found for specialty_id:", specialtyId);
                    return;
                }

                $('#editSpecialtyId').val('');
                $('#specialty-name').val('');
                $('#specialty-description').val('');

                // Fill modal fields
                $('#specialty-id').val(specialty.specialty_id); // hidden id
                $('#specialty-name').val(specialty.name);
                $('#specialty-description').val(specialty.description);

                $('#ManageSpecialtiesModal').modal('hide');
                $('#editSpecialtyModal').modal('show');
            });
        });

        // ✅ Delete handler
        tbody.querySelectorAll(".delete-specialty").forEach(btn => {
            btn.addEventListener("click", () => {
                const specialtyId = parseInt(btn.dataset.id, 10);
                editingSpecialtyId = specialtyId;

                const matchedSpecialty = specialties.find(s => parseInt(s.specialty_id) === specialtyId);
                const specialtyName = matchedSpecialty ? `"${matchedSpecialty.name}"` : '"this specialty"';

                $('#ManageSpecialtiesModal').modal('hide');
                Swal.fire({
                    title: 'Delete Specialty?',
                    html: `Are you sure you want to delete ${specialtyName}?<br>`,
                    iconHtml: '<i class="bx bx-trash"></i>',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'handlers/manageUsers-handler.php',
                            method: 'POST',
                            data: {
                                action: 'deleteSpecialty',
                                specialty_id: editingSpecialtyId,
                                name: specialtyName
                            },
                            dataType: 'json',
                            success: function(response) {
                                $('#ManageSpecialtiesModal').modal('hide');
                                if (response.status === 'success') {
                                    // Remove deleted specialty
                                    specialties = specialties.filter(s => parseInt(s.specialty_id) !== specialtyId);

                                    Swal.fire({
                                        ...swalOptions,
                                        iconHtml: '<i class="bx bx-check-circle"></i>',
                                        title: 'Specialty Deleted!',
                                        text: response.message
                                    }).then(() => {
                                        populateSpecialtiesTable(specialties);
                                        $('#ManageSpecialtiesModal').modal('show');
                                    });
                                } else {
                                    Swal.fire({
                                        ...swalOptions,
                                        iconHtml: '<i class="bx bx-x-circle"></i>',
                                        title: 'Failed Deleting Specialty',
                                        text: response.message || 'Deletion failed.'
                                    }).then(() => {
                                        $('#ManageSpecialtiesModal').modal('show');
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
                        $('#ManageSpecialtiesModal').modal('show');
                    }
                });
            });
        });
    }

    $(document).on('click', '#addSpecialty', function() {
        let name = $('#add-specialty-name').val().trim();
        let description = $('#add-specialty-description').val().trim();

        // ✅ Validation
        if (name.length < 3 || name.length > 50) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Name',
                text: 'Specialty name must be between 3 and 50 characters.'
            });
            return;
        }

        if (description.length < 5) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Description',
                text: 'Description must be at least 5 characters long.'
            });
            return;
        }

        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'addSpecialty',
                name: name,
                description: description
            },
            success: function(response) {

                if (response.status === 'success') {
                    $('#addSpecialtyModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Specialty Added',
                        text: response.message || 'New specialty has been added successfully!'
                    }).then(() => {

                        // 🔄 Refresh specialties list dynamically
                        $('#manageSpecialties').trigger('click');
                    });
                } else {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Add Failed',
                        text: response.message || 'Failed to add specialty.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Request Failed',
                    text: 'Could not add specialty.'
                });
            }
        });
    });


    $(document).on('click', '#add-specialty', function() {

        $('#ManageSpecialtiesModal').modal('hide');
        $('#addSpecialtyModal').modal('show');
    });


    $(document).on('click', '#manageSpecialties', function() {
        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            data: {
                action: 'fetchSpecialties'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // ✅ Populate table with specialties
                    specialties = response.data; // Cache globally
                    populateSpecialtiesTable(response.data);
                    $('#ManageSpecialtiesModal').modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Load Specialties',
                        text: response.message || 'No specialties found.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong while fetching specialties.'
                });
            }
        });
    });

    $(document).on('click', '#saveEditedSpecialty', function() {
        let id = $('#specialty-id').val();
        let name = $('#specialty-name').val().trim();
        let description = $('#specialty-description').val().trim();


        // ✅ Validation
        if (name.length < 3 || name.length > 50) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Name',
                text: 'Specialty name must be between 3 and 50 characters.'
            });
            return;
        }

        if (description.length < 5) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Description',
                text: 'Description must be at least 5 characters long.'
            });
            return;
        }
        if (!name) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Input',
                text: 'Please enter a specialty name.'
            });
            return;
        }

        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            type: 'POST',
            data: {
                action: 'updateSpecialty',
                specialty_id: id,
                name: name,
                description: description
            },
            dataType: 'json', // 👈 correct, use comma not dot
            success: function(res) { // 👈 already parsed, no JSON.parse

                if (res.success) {
                    $('#editSpecialtyModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Updated',
                        text: 'Specialty updated successfully!'
                    }).then(() => {

                        // 🔄 Refresh specialties list dynamically
                        $('#manageSpecialties').trigger('click');
                    });
                } else {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Update Failed',
                        text: res.message || 'Something went wrong.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Request Failed',
                    text: 'Could not update specialty.'
                });
            }
        });

    });







    $(document).on('click', '#manageMechSpecialty', function() {
        $("#specialtySearch").val('');
        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'getMechanicsSpecialty',
                user_id: ActiveRowId
            },
            success: function(response) {
                console.log(response);
                if (response.status === 'success') {
                    let specialties = response.allSpecialties; // <-- using backend key 'all'
                    let assigned = response.assignedSpecialties.map(s => parseInt(s.specialty_id)); // get assigned IDs as array
                    let listHtml = '';

                    if (specialties.length > 0) {
                        specialties.forEach(spec => {
                            let isChecked = assigned.includes(parseInt(spec.specialty_id)) ? 'checked' : '';

                            listHtml += `
                                <div class="form-check">
                                    <input class="form-check-input specialty-checkbox" 
                                        type="checkbox" 
                                        value="${spec.specialty_id}" 
                                        id="spec_${spec.specialty_id}" ${isChecked}>
                                    <label class="form-check-label" for="spec_${spec.specialty_id}">
                                        ${spec.name}
                                    </label>
                                </div>
                            `;
                        });
                    } else {
                        listHtml = '<p>No specialties available.</p>';
                    }

                    // Place checkboxes in left panel
                    $('#allSpecialtiesList').html(listHtml);

                    // Build selected list from assigned
                    if (assigned.length > 0) {
                        let selectedHtml = '<ul>';
                        specialties.forEach(spec => {
                            if (assigned.includes(parseInt(spec.specialty_id))) {
                                selectedHtml += `<li>${spec.name}</li>`;
                            }
                        });
                        selectedHtml += '</ul>';
                        $('#selectedSpecialtiesList').html(selectedHtml);
                    } else {
                        $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
                    }

                    // Show modal
                    $('#manageMechSpecialtyModal').modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to fetch specialties.'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong while loading specialties.'
                });
                console.error("AJAX Error:", status, error);
            }
        });



    });
    // ✅ Search functionality
    $(document).on('keyup', '#specialtySearch', function() {
        let searchVal = $(this).val().toLowerCase();

        $('#allSpecialtiesList .form-check').each(function() {
            let labelText = $(this).find('label').text().toLowerCase();
            if (labelText.includes(searchVal)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // ✅ Handle checkbox changes → update right-side list
    $(document).on('change', '.specialty-checkbox', function() {
        let selected = [];
        $('.specialty-checkbox:checked').each(function() {
            selected.push($(this).next('label').text());
        });

        if (selected.length > 0) {
            let selectedHtml = '<ul>';
            selected.forEach(name => {
                selectedHtml += `<li>${name}</li>`;
            });
            selectedHtml += '</ul>';
            $('#selectedSpecialtiesList').html(selectedHtml);
        } else {
            $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
        }
    });


    // Confirm specialties -> save to DB
    $(document).on('click', '#confirmSpecialty', function(e) {
        e.preventDefault();

        const $btn = $(this);
        // collect selected IDs as integers and dedupe
        let selectedIds = $('.specialty-checkbox:checked').map(function() {
            return parseInt($(this).val(), 10);
        }).get().filter(n => !isNaN(n));

        selectedIds = Array.from(new Set(selectedIds)); // dedupe

        // optional: ensure we have a mechanic id
        const userId = typeof ActiveRowId !== 'undefined' ? ActiveRowId : 0;
        if (!userId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Mechanic ID is missing.'
            });
            return;
        }

        // give user feedback
        $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> Saving...');

        $.ajax({
            url: 'handlers/manageUsers-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'saveMechanicSpecialties',
                user_id: userId,
                specialties: selectedIds // jQuery serializes as specialties[]=1&specialties[]=2
            },
            success: function(response) {
                if (response && response.status === 'success') {
                    // update UI: refresh right-side selected list
                    let selectedHtml = '';
                    if (selectedIds.length > 0) {
                        selectedIds.forEach(id => {
                            // find name by matching checkbox label
                            const label = $(`#spec_${id}`).closest('label').text().trim() || $(`#spec_${id}`).next('label').text().trim();
                            selectedHtml += `<li data-id="${id}" style="padding:6px 0; border-bottom:1px solid #f1f1f1;">${label}</li>`;
                        });
                        $('#selectedSpecialtiesList').html(selectedHtml);
                    } else {
                        $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
                    }

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Mechanics Specialties Updated',
                        text: response.message || 'Specialties updated.'
                    });
                    // optionally close modal:
                    setTimeout(function() {
                        $('#manageMechSpecialtyModal').modal('hide');
                    }, 400);
                } else {
                    Swal.fire({
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error Updating Mechanics Specialties',
                        text: (response && response.message) ? response.message : 'Failed to save specialties.'
                    });
                }
            },
            error: function(xhr, status, err) {
                Swal.fire({
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Error Updating Mechanics Specialties',
                    text: 'Something went wrong while saving specialties.'
                });
                console.error('Save specialties AJAX error:', status, err, xhr.responseText);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bx bx-plus"></i>Confirm Specialties');
            }
        });
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
</body>

</html>