<?php
session_start();
include_once '../config/db.php';


// ALLOW ACCES IF AJAX REQUEST AND POSTS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ALLOW ACCES IF AJAX REQUEST ONLY
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        die('<h1 style="color: red; text-align: center; margin-top: 20%;">Direct access to this page is not allowed.</h1>
            <p style="text-align: center;"> <a href="javascript:history.back()" style="color: blue; text-decoration: underline;">Go Back</a></p>');
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Offered</title>
    <link rel="stylesheet" href="style/servicesOffered.css">
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
                type: 'text'
            },
            {
                type: 'select',
                options: ['Active', 'Inactive']
            },
            {
                type: 'text'
            },
            {
                type: 'none'
            },
            {
                type: 'none'
            },
            {
                type: 'none'
            }
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
                var title = $(this).text();
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
                    type: 'string',
                    targets: [5] // adjust if needed based on actual string columns
                },
                {
                    width: '200px',
                    targets: [0, 1, 5]
                },
                {
                    width: '500px',
                    targets: [3]
                }
            ]
        });

        // ✅ 1. Clear all DataTables search filters
        table.search('').columns().search('').draw(false); //false para di mag reset pagination on reload

        // ✅ 2. Clear all filter inputs and selects in the filter row
        $('#myTable thead tr.filters th').each(function() {
            $(this).find('input, select').val('');
        });

        let ActiveRowId = null;
        let activeRow = null;



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


            $('.description-text').removeClass('expanded');
            $(tr).find('.description-text').addClass('expanded');

            // Step 5: Store values
            ActiveRowId = rowData[0];
            activeRow = tr;
            localStorage.setItem('RowId', ActiveRowId);
        }

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
                }
            }
        }

        // Row click event handler
        document.querySelector('#myTable tbody').addEventListener('click', function(e) {
            const td = e.target.closest('td.dt-control');
            if (!td) return;
            const tr = td.closest('tr');
        });

        function unsetActiveRow() {
            const tr = document.querySelector('.active-row');
            if (tr) {
                tr.classList.remove('active-row');
                $('.description-text').removeClass('expanded');
                localStorage.setItem('activeRowIndex', null);
                localStorage.removeItem('RowId');
                ActiveRowId = null;
                activeRow = null;
            }
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
        <p class="content-id"><i class='bx bx-circle'></i> <span>services offered</span></p>
        <div id="table-box"><i class="bx bx-help-circle" title="help" id="helpicon"></i>

            <?php if ($_SESSION['User_role'] === 'Super Admin' || $_SESSION['User_role'] === 'Admin'): ?>
                <div id="buttons"><!-- BUTTONS FOR CRUD FUNCTIONS -->
                    <button id="addbtn" type="button"><i class='bx bx-plus'></i><span>Add</span></button>
                    <button id="editbtn"><i class='bx bx-pencil'></i><span>Edit</span></button>
                    <button id="activatebtn" class="activate-btn"><i class='bx bxs-circle'></i><span>Activate</span></button>
                    <button id="deacbtn" class="deactivate-btn"><i class='bx bxs-circle'></i><span>Deactivate</span></button>

                </div>
            <?php endif; ?>


            <div id="mesa">
                <!-- TABLE USES DATABLES LIBRARY -->
                <table id="myTable">

                    <thead>
                        <tr class="column-heads">
                            <th>Service ID</th>
                            <th>Status</th>
                            <th>Service Name</span></th>
                            <th>Description</th>
                            <th>Estimated Duration</th>
                            <th>Labor Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Format duration from HH:MM:SS to "1d 2h 30m"
                        function formatDuration($duration)
                        {
                            [$hours, $minutes, $seconds] = explode(':', $duration);

                            $totalMinutes = ($hours * 60) + $minutes + round($seconds / 60);
                            $output = [];

                            if ($totalMinutes >= 1440) {
                                $days = floor($totalMinutes / 1440);
                                $output[] = $days . ' ' . ($days == 1 ? 'day' : 'days');
                                $totalMinutes %= 1440;
                            }

                            if ($totalMinutes >= 60) {
                                $hrs = floor($totalMinutes / 60);
                                $output[] = $hrs . ' ' . ($hrs == 1 ? 'hour' : 'hours');
                                $totalMinutes %= 60;
                            }

                            if ($totalMinutes > 0 || empty($output)) {
                                $output[] = $totalMinutes . ' ' . ($totalMinutes == 1 ? 'minute' : 'minutes');
                            }

                            return implode(' ', $output);
                        }

                        // Format labor cost with ₱ and commas
                        function formatLaborCost($cost)
                        {
                            return '₱ ' . number_format((float)$cost, 2);
                        }
                        //POPULATING THE TABLE WITH VALUES FROM DB
                        $rs = mysqli_query($db_connection, 'SELECT * FROM servicestbl');

                        while ($rw = mysqli_fetch_array($rs)) {
                            echo
                            '
                        <tr  class="data-rows" data-id="' . $rw['service_id'] . '" onclick = "setActiveRow(this);">
                        <td  id="aydis">' . $rw['service_id'] . '</td>
                        <td  data-role ="status">' . $rw['status'] . '</td>
                        <td data-role ="service_name" class="ellipsis-tooltip">' . $rw['service_name'] . '</td>
                        <td data-role="description" class="toggle-description">
                            <div class="description-text">' . htmlspecialchars($rw['description']) . '</div>
                        </td>
                        <td data-role="estimated_duration">' . formatDuration($rw['estimated_duration']) . '</td>
                        <td data-role="labor_cost" class="ellipsis-tooltip">' . formatLaborCost($rw['labor_cost']) . '</td>
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

            <!-- BACK TO TOP BUTTON -->
            <a href="#top" title="Go up"><i class='bx bx-chevron-up' id="goTop"></i></a>
        </div>
    </div>


    <!-- ADD SERVICE MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addServiceModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add service</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="closeAddServiceModal()"></button>
                </div>
                <div class="modal-body">
                    <label for="service-name" class="form-label">Service Name</label>
                    <input type="text" id="service-name" class="form-control" placeholder="Enter Service Name" autocomplete="off">

                    <label for="description" class="form-label text-muted">Service Description</label>
                    <textarea class="form-control" id="description" placeholder="Enter description for this service..."></textarea>

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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeAddServiceModal()">Cancel</button>
                        <button id="addService" class="btn btn-primary"><i class="bx bx-plus"></i>Add Service</button>
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
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">edit service</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="closeEditServiceModal()"></button>
                </div>
                <div class="modal-body">
                    <label for="edit-service-name" class="form-label">Service Name</label>
                    <input type="text" id="edit-service-name" class="form-control" placeholder="Enter Service Name" autocomplete="off">

                    <label for="edit-description" class="form-label text-muted">Service Description</label>
                    <textarea class="form-control" id="edit-description" placeholder="Enter description for this service..."></textarea>

                    <label for="edit-duration-value" class="form-label">Estimated Duration</label>
                    <div class="row mb-2">
                        <div class="col-4">
                            <label for="edit-duration-days" class="form-label">Day/s</label>
                            <input type="number" min="0" id="edit-duration-days" class="form-control" placeholder="Days">
                        </div>
                        <div class="col-4">
                            <label for="edit-duration-hours" class="form-label">Hour/s</label>
                            <input type="number" min="0" max="23" id="edit-duration-hours" class="form-control" placeholder="Hours">
                        </div>
                        <div class="col-4">
                            <label for="edit-duration-minutes" class="form-label">Minute/s</label>
                            <input type="number" min="0" max="59" id="edit-duration-minutes" class="form-control" placeholder="Minutes" step="10">
                        </div>
                    </div>

                    <input type="hidden" id="edit-final-duration">

                    <label for="edit-service-cost" class="form-label">Labor Cost</label>
                    <div class="input-group">
                        <span class="input-group-text"><b>₱</b></span>
                        <input type="text" id="edit-service-cost" class="form-control" placeholder="Enter Labor Cost" autocomplete="off">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeEditServiceModal()">Cancel</button>
                        <button id="editService" class="btn btn-primary"><i class="bx bx-save"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>



</div>
<script>
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
    $('#edit-service-cost, #service-cost').on('input', function() {
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




    function updateDurationFields(prefix, targetId) {

        const days = parseInt($(`#${prefix}-days`).val(), 10) || 0;
        const hours = parseInt($(`#${prefix}-hours`).val(), 10) || 0;
        const minutes = parseInt($(`#${prefix}-minutes`).val(), 10) || 0;

        const totalHours = (days * 24) + hours;
        const formatted = `${String(totalHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`;

        $(`#${targetId}`).val(formatted);
    }


    function bindDurationLimits(prefix, targetId) {
        $(`#${prefix}-days`).on('input change', function() {
            let val = parseInt($(this).val(), 10);
            $(this).val(isNaN(val) ? 0 : Math.max(val, 0)); // allow any positive days
            updateDurationFields(prefix, targetId);
        });

        $(`#${prefix}-hours`).on('input change', function() {
            let val = parseInt($(this).val(), 10);
            $(this).val(isNaN(val) ? 0 : Math.min(Math.max(val, 0), 23));
            updateDurationFields(prefix, targetId);
        });

        $(`#${prefix}-minutes`).on('input change', function() {
            let val = parseInt($(this).val(), 10);
            $(this).val(isNaN(val) ? 0 : Math.min(Math.max(val, 0), 59));
            updateDurationFields(prefix, targetId);
        });
    }

    // Bind for both add & edit
    bindDurationLimits('duration', 'final-duration');
    bindDurationLimits('edit-duration', 'edit-final-duration');









    $(document).on('click', '#addbtn', function() {
        $('#service-name, #final-duration, #description, #service-cost').val('');

        $('#addServiceModal').modal('show');
    });

    function closeAddServiceModal() {
        const name = $('#service-name').val().trim();
        const est_duration = $('#final-duration').val();
        const serviceDescription = $('#description').val();
        const costRaw = $('#service-cost').val().replace(/[₱,]/g, '').trim();
        const labor_cost = parseFloat(costRaw || 0).toFixed(2);

        // Check if at least one field has data
        const hasInput = name || est_duration || serviceDescription || parseFloat(labor_cost) > 0;

        if (!hasInput) {
            // No inputs filled → close immediately
            $('#addServiceModal').modal('hide');
            return;
        }

        // Ask for confirmation before discarding
        Swal.fire({
            title: 'Discard Changes?',
            html: 'Are you sure you want to discard the changes you made?',
            iconHtml: '<i class="bx bx-info-circle"></i>',
            showCancelButton: true,
            cancelButtonText: 'Keep Editing',
            confirmButtonText: 'Discard',
            allowOutsideClick: false,
            focusConfirm: true,
        }).then((result) => {
            if (result.isConfirmed) {
                $('#addServiceModal').modal('hide');
                // Optionally clear the fields here
                $('#service-name, #final-duration, #description, #service-cost').val('');
            } else {
                $('#addServiceModal').modal('show');
            }
        });
    }


    // Helper for showing errors on any modal
    function showError(modalSelector, title, text) {
        // Close whichever modal is active
        $(modalSelector).modal('hide');

        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-info-circle"></i>',
            title,
            text
        }).then(() => {
            // Re-open the same modal after alert
            $(modalSelector).modal('show');
        });
    }



    $(document).on('click', '#addService', function() {
        const name = $('#service-name').val().trim();
        const est_duration = $('#final-duration').val();
        const serviceDescription = $('#description').val();
        console.log(est_duration);


        const costRaw = $('#service-cost').val().replace(/[₱,]/g, '').trim();
        const labor_cost = parseFloat(costRaw || 0).toFixed(2);


        // Extra validation rules
        if (!name || !serviceDescription || isNaN(parseFloat(labor_cost))) {
            showError('#addServiceModal', 'Missing Fields', 'Please complete all fields.');
            return;
        }

        // Name length restriction
        if (name.length < 3 || name.length > 50) {
            showError('#addServiceModal', 'Invalid Name', 'Service name must be between 3 and 50 characters.');
            return;
        }

        // Name allowed characters (letters, numbers, spaces, basic punctuation)
        if (!/^[a-zA-Z0-9\s.,'-]+$/.test(name)) {
            showError('#addServiceModal', 'Invalid Name', 'Service name contains invalid characters.');
            return;
        }

        // Labor cost must be a positive number
        if (parseFloat(labor_cost) <= 0) {
            showError('#addServiceModal', 'Invalid Cost', 'Labor cost must be greater than 0.');
            return;
        }

        // Optional: limit description length
        if (serviceDescription.length < 10) {
            showError('#addServiceModal', 'Description Too Short', 'Please provide more details in the description.');
            return;
        }

        // Convert to total seconds
        const [hours, minutes, seconds] = est_duration.split(':').map(Number);
        const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;

        if (!est_duration || totalSeconds < 300) { // Less than 5 minutes
            showError('#addServiceModal', 'Invalid Duration', 'Duration must be at least 5 minutes.');
            return;
        }


        $.ajax({
            url: 'handlers/servicesOffered-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'addService',
                service_name: name,
                est_duration,
                description: serviceDescription,
                labor_cost,
            },
            success(response) {
                $('#addServiceModal').modal('hide');

                console.log(response);
                if (response.status === 'success') {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Service Added!',
                        text: response.message
                    }).then((result) => {


                    });
                    const newRowHtml = `
                    <tr class="data-rows" data-id="${response.insert_id}" onclick="setActiveRow(this);">
                        <td id="aydis">${response.insert_id}</td>
                        <td data-role="status">Active</td>
                        <td data-role="service_name" class="ellipsis-tooltip">${name}</td>
                        <td data-role="description" class="toggle-description">
                            <div class="description-text">${serviceDescription}</div>
                        </td>
                        <td data-role="estimated_duration">${est_duration}</td>
                        <td data-role="labor_cost" class="ellipsis-tooltip">${labor_cost}</td>
                    </tr>`;

                    table.row.add($(newRowHtml)).draw(false);
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

    });












    let service;
    $(document).on('click', '#editbtn', function() {
        if (checkSelection()) {
            return; // Exit if no valid selection
        }
        $('#edit-service-name, #edit-final-duration, #edit-description, #edit-service-cost').val('');
        $.ajax({
            url: 'handlers/servicesOffered-handler.php',
            type: 'POST',
            dataType: 'json',
            data: {
                serviceId: ActiveRowId,
                action: 'getService'
            },
            success: function(response) {
                if (response.status) {
                    service = response.service;

                    $('#edit-service-name').val(service.service_name);
                    $('#edit-description').val(service.description);
                    $('#edit-service-cost').val(parseFloat(service.labor_cost).toFixed(2));

                    let [h, m, s] = service.estimated_duration.split(':').map(Number);
                    let days = Math.floor(h / 24);
                    let hours = h % 24;
                    let minutes = m;

                    $('#edit-duration-days').val(days);
                    $('#edit-duration-hours').val(hours);
                    $('#edit-duration-minutes').val(minutes);

                    // Keep original format in the hidden field for submission
                    $('#edit-final-duration').val(service.estimated_duration);

                    $('#editServiceModal').modal('show');

                } else {
                    console.warn("Service not found:", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading service record:", error);
            }
        });

    });


    function hasServiceChanges() {
        // Current form values
        const name = $('#edit-service-name').val().trim();
        const est_duration = $('#edit-final-duration').val();
        const serviceDescription = $('#edit-description').val().trim();
        const costRaw = $('#edit-service-cost').val().replace(/[₱,]/g, '').trim();
        const labor_cost = parseFloat(costRaw || 0).toFixed(2);

        // Original values from service object
        const ogName = service.service_name.trim();
        const ogDuration = service.estimated_duration.trim();
        const ogDescription = (service.description || '').trim();
        const ogCost = parseFloat(service.labor_cost || 0).toFixed(2);

        return (
            name !== ogName ||
            est_duration !== ogDuration ||
            serviceDescription !== ogDescription ||
            labor_cost !== ogCost
        );
    }


    function closeEditServiceModal() {

        if (hasServiceChanges()) {
            Swal.fire({
                title: 'Discard Changes?',
                html: 'Are you sure you want to discard the changes you made?',
                iconHtml: '<i class="bx bx-info-circle"></i>',
                showCancelButton: true,
                cancelButtonText: 'Keep Editing',
                confirmButtonText: 'Discard',
                allowOutsideClick: false,
                focusConfirm: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#editServiceModal').modal('hide');
                } else {

                    $('#editServiceModal').modal('show');
                }
            });
        } else {
            $('#editServiceModal').modal('hide');
        }
    }

    $(document).on('click', '#editService', function() {

        const name = $('#edit-service-name').val().trim();
        const est_duration = $('#edit-final-duration').val();
        const serviceDescription = $('#edit-description').val().trim();
        const costRaw = $('#edit-service-cost').val().replace(/[₱,]/g, '').trim();
        const labor_cost = parseFloat(costRaw || 0).toFixed(2);

        if (!hasServiceChanges()) {
            $('#editServiceModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Changes Detected',
                html: 'You have not made any changes to the service.'
            }).then((result) => {
                $('#editServiceModal').modal('show');
            })
            return; // Stop here if nothing changed
        }

        // Extra validation rules
        if (!name || !serviceDescription || isNaN(parseFloat(labor_cost))) {
            showError('#editServiceModal', 'Missing Fields', 'Please complete all fields.');
            return;
        }

        // Name length restriction
        if (name.length < 3 || name.length > 50) {
            showError('#editServiceModal', 'Invalid Name', 'Service name must be between 3 and 50 characters.');
            return;
        }

        // Name allowed characters (letters, numbers, spaces, basic punctuation)
        if (!/^[a-zA-Z0-9\s.,'-]+$/.test(name)) {
            showError('#editServiceModal', 'Invalid Name', 'Service name contains invalid characters.');
            return;
        }

        // Labor cost must be a positive number
        if (parseFloat(labor_cost) <= 0) {
            showError('#editServiceModal', 'Invalid Cost', 'Labor cost must be greater than 0.');
            return;
        }

        // Optional: limit description length
        if (serviceDescription.length < 10) {
            showError('#editServiceModal', 'Description Too Short', 'Please provide more details in the description.');
            return;
        }

        // Convert to total seconds
        const [hours, minutes, seconds] = est_duration.split(':').map(Number);
        const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;

        if (!est_duration || totalSeconds < 300) { // Less than 5 minutes
            showError('#editServiceModal', 'Invalid Duration', 'Duration must be at least 5 minutes.');
            return;
        }

        $.ajax({
            url: 'handlers/servicesOffered-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'updateService',
                service_id: ActiveRowId,
                service_name: name,
                est_duration,
                description: serviceDescription,
                labor_cost
            },
            success: function(response) {

                if (response.status === 'error') {
                    // Show error alert
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Update Failed',
                        text: response.message || 'An unexpected error occurred.',
                    });
                    return; // Stop further success logic
                }

                if (response.status === 'success') {
                    // Close the edit modal
                    $('#editServiceModal').modal('hide');

                    // Locate the active row in the DOM
                    const tr = $(`tr[data-id="${ActiveRowId}"]`);

                    // Update HTML cells directly
                    tr.find('td[data-role="service_name"]').text(name);
                    tr.find('td[data-role="description"] .description-text').text(serviceDescription);

                    // Format the duration same way as PHP (HH:MM:SS → "Xd Xh Xm")
                    tr.find('td[data-role="estimated_duration"]').text(formatDuration(est_duration));

                    // Format cost like PHP
                    tr.find('td[data-role="labor_cost"]').text(formatLaborCost(labor_cost));

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Service Updated',
                        text: response.message,
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status);
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Error',
                    text: 'Something went wrong. Please check the console.'
                });
            }
        });

    });





    $(document).on('click', '.activate-btn, .deactivate-btn', function() {
        if (checkSelection()) {
            return; // Exit if no valid selection
        }

        const isActivate = $(this).hasClass('activate-btn');
        const actionType = isActivate ? 'activateService' : 'deactivateService';
        const statusCell = activeRow.cells[1].textContent.trim().toLowerCase(); // adjust column index if needed
        // Prevent invalid actions
        if (isActivate && statusCell === 'active') {
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'Already Active',
                text: 'This service is already active.'
            });
            return;
        }

        if (!isActivate && statusCell === 'inactive') {
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'Already Inactive',
                text: 'This service is already inactive.'
            });
            return;
        }

        // Dynamic confirmation message
        const confirmTitle = isActivate ? 'Activate Service' : 'Deactivate Service';
        const htmlList = isActivate ?
            `
            <ul style="text-align: left; padding-left: 1.5em;">
                <li>This service will be marked as <b>Active</b>.</li>
                <li>Clients will be able to request it again.</li>
            </ul>
            <small>Please review service details before activation.</small>
        ` :
            `
            <ul style="text-align: left; padding-left: 1.5em;">
                <li>This service will be marked as <b>Inactive</b>.</li>
                <li>Clients will no longer see it as available.</li>
            </ul>
            <small><b>This can be reversed later</b> by reactivating the service.</small>
        `;
        Swal.fire({
            iconHtml: '<i class="bx bx-info-circle"></i>',
            title: confirmTitle,
            html: htmlList,
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'handlers/servicesOffered-handler.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: actionType,
                        service_id: ActiveRowId
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            // Update status cell in the table
                            $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`)
                                .text(isActivate ? 'Active' : 'Inactive');
                        }

                        Swal.fire({
                            ...swalOptions,
                            iconHtml: response.status === 'success' ?
                                '<i class="bx bx-check-circle"></i>' : '<i class="bx bx-x-circle"></i>',
                            title: response.status === 'success' ? 'Success' : 'Error',
                            text: response.message
                        });
                    },
                    error: function(xhr, status, error) {

                        console.log('Response text:', xhr.responseText);
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-error-circle"></i>',
                            title: 'Server Error',
                            text: 'Something went wrong while processing the service update.'
                        });
                    }
                });
            }
        });
    });















    // JS versions of your PHP formatters
    function formatDuration(duration) {
        const [hoursStr, minutesStr, secondsStr] = duration.split(':');
        let hours = parseInt(hoursStr, 10) || 0;
        let minutes = parseInt(minutesStr, 10) || 0;
        let seconds = parseInt(secondsStr, 10) || 0;

        let totalMinutes = (hours * 60) + minutes + Math.round(seconds / 60);
        let output = [];

        if (totalMinutes >= 1440) {
            const days = Math.floor(totalMinutes / 1440);
            output.push(days + ' ' + (days === 1 ? 'day' : 'days'));
            totalMinutes %= 1440;
        }

        if (totalMinutes >= 60) {
            const hrs = Math.floor(totalMinutes / 60);
            output.push(hrs + ' ' + (hrs === 1 ? 'hour' : 'hours'));
            totalMinutes %= 60;
        }

        if (totalMinutes > 0 || output.length === 0) {
            output.push(totalMinutes + ' ' + (totalMinutes === 1 ? 'minute' : 'minutes'));
        }

        return output.join(' ');
    }

    function formatLaborCost(cost) {
        return '₱ ' + parseFloat(cost).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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