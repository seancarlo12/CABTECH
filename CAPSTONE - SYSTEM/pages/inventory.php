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
    <title>Inventory</title>
    <link rel="stylesheet" href="style/inventory.css">
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
                options: ['Available', 'Unavailable']
            },
            {
                type: 'text'
            },
            {
                type: 'none'
            },
            {
                type: 'multi-select',
                options: ['Part', 'Consumable', 'Product']
            },
            {
                type: 'text'
            }
        ];

        $('#myTable thead tr.filters th').each(function(i) {
            const filter = columnFilters[i];

            if (!filter || filter.type === 'none') {
                $(this).html(''); // No filter
                return;
            }

            if (!filter) return; // Skip if undefined (extra column?)


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
                    targets: [0, 5]
                },
                {
                    width: '200px',
                    targets: [4]
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
            }
            localStorage.setItem('activeRowIndex', null);
            localStorage.removeItem('RowId');
            ActiveRowId = null;
            activeRow = null;
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
        <p class="content-id"><i class='bx bx-circle'></i> <span>inventory</span></p>
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
                            <th>Item ID</th>
                            <th>Status</th>
                            <th>Item Name</span></th>
                            <th>Price</th>
                            <th>Type</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //POPULATING THE TABLE WITH VALUES FROM DB
                        $rs = mysqli_query($db_connection, 'SELECT * FROM itemstbl');
                        function formatPrice($cost)
                        {
                            return '₱ ' . number_format((float)$cost, 2);
                        }
                        while ($rw = mysqli_fetch_array($rs)) {
                            echo
                            '
                        <tr  class="data-rows" data-id="' . $rw['item_id'] . '" onclick = "setActiveRow(this);">
                        <td  id="aydis">' . $rw['item_id'] . '</td>
                        <td  data-role ="status">' . $rw['status'] . '</td>
                        <td data-role ="name" class="ellipsis-tooltip">' . $rw['name'] . '</td>
                        <td data-role ="price">' . formatPrice($rw['price']) . '</td>
                        <td data-role ="type">' . $rw['type'] . '</td>
                        <td data-role ="stock" class="ellipsis-tooltip">' . $rw['stock'] . '</td>
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
    <!-- MODALS -->

    <!-- ADD ITEM MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="addItemModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">add item</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="closeAddItemModal()"></button>
                </div>
                <div class="modal-body">


                    <label for="item-name" class="form-label">Item Name</label>
                    <input type="text" id="item-name" class="form-control" placeholder="Enter Item Name" autocomplete="off">
                    <small>
                        <i class="bx bx-info-circle"></i> Enter the item name with the needed details (<b>brand, type, or specification such as size, capacity</b>).
                    </small><br>

                    <label for="item-type" class="form-label mt-3">Item Type</label>
                    <select id="item-type" class="form-select">
                        <option value="" selected disabled hidden>Select Item Type</option>
                        <option value="Part">Part</option>
                        <option value="Consumable">Consumable</option>
                        <option value="Product">Product</option>
                    </select>

                    <label for="item-price" class="form-label">Item Price</label>
                    <div class="input-group">
                        <span class="input-group-text"><b>₱</b></span>
                        <input type="text" id="item-price" class="form-control" placeholder="Enter Labor Price" autocomplete="off">
                    </div>

                    <label for="item-stock" class="form-label mt-3">Stock</label>
                    <input type="number" id="item-stock" class="form-control" placeholder="Enter Stock Quantity" min="0" autocomplete="off">

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeAddItemModal()">Cancel</button>
                        <button id="addItem" class="btn btn-primary"><i class="bx bx-plus"></i>Add Item</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT ITEM MODAL -->
    <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editItemModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalToggleLabel">edit item</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="closeEditItemModal()"></button>
                </div>
                <div class="modal-body">


                    <label for="edit-item-name" class="form-label">Item Name</label>
                    <input type="text" id="edit-item-name" class="form-control" placeholder="Enter Item Name" autocomplete="off">
                    <small>
                        <i class="bx bx-info-circle"></i> Enter the item name with the needed details (<b>brand, type, or specification such as size, capacity</b>).
                    </small><br>

                    <label for="edit-item-type" class="form-label mt-3">Item Type</label>
                    <select id="edit-item-type" class="form-select">
                        <option value="" selected disabled hidden>Select Item Type</option>
                        <option value="Part">Part</option>
                        <option value="Consumable">Consumable</option>
                        <option value="Product">Product</option>
                    </select>

                    <label for="edit-item-price" class="form-label">Item Price</label>
                    <div class="input-group">
                        <span class="input-group-text"><b>₱</b></span>
                        <input type="text" id="edit-item-price" class="form-control" placeholder="Enter Labor Price" autocomplete="off">
                    </div>

                    <label for="edit-item-stock" class="form-label mt-3">Stock</label>
                    <input type="number" id="edit-item-stock" class="form-control" placeholder="Enter Stock Quantity" min="0" autocomplete="off">

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeEditItemModal()">Cancel</button>
                        <button id="saveEditItem" class="btn btn-primary"><i class="bx bx-save"></i>Save Changes</button>
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

    function formatItemPrice(cost) {
        return '₱ ' + parseFloat(cost).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    $('#item-price, #edit-item-price').on('input', function() {
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


    function closeAddItemModal() {
        const name = $('#item-name').val().trim();
        const type = $('#item-type').val();
        const price = $('#item-price').val().trim();
        const stock = parseInt($('#item-stock').val().trim()) || 0;

        // Check if at least one field has data
        const hasInput = name || type || price || parseFloat(price) > 0;

        if (!hasInput) {
            // No inputs filled → close immediately
            $('#addItemModal').modal('hide');
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
                $('#addItemModal').modal('hide');
                // Optionally clear the fields here
                $('#item-name, #item-type, #item-price, #item-stock').val('');
            } else {
                $('#addItemModal').modal('show');
            }
        });
    }

    function hasItemChanges() {
        // Current form values
        const name = $('#edit-item-name').val().trim();
        const type = $('#edit-item-type').val();
        const price = parseFloat($('#edit-item-price').val().replace(/,/g, '').trim()) || 0;
        const stock = parseInt($('#edit-item-stock').val().trim()) || 0;

        // Original values from item object
        const ogName = item.name.trim();
        const ogType = item.type.trim();
        const ogPrice = parseFloat(item.price) || 0;
        const ogStock = parseInt(item.stock) || 0;

        console.log(price, ogPrice);
        return (
            name !== ogName ||
            type !== ogType ||
            price !== ogPrice ||
            stock !== ogStock
        );
    }



    function closeEditItemModal() {
        if (hasItemChanges()) {
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
                    $('#editItemModal').modal('hide');
                } else {

                    $('#editItemModal').modal('show');
                }
            });
        } else {
            $('#editItemModal').modal('hide');
        }
    }



    $(document).on('click', '#addbtn', function() {

        $('#addItemModal').modal('show');
    });

    let item;
    $(document).on('click', '#editbtn', function() {
        if (checkSelection()) {
            return;
        }
        $.ajax({
            url: 'handlers/inventory-handler.php',
            type: 'POST',
            dataType: 'json',
            data: {
                itemId: ActiveRowId,
                action: 'getItem'
            },
            success: function(response) {
                if (response.status) {
                    item = response.item;

                    $('#edit-item-name').val(item.name);
                    $('#edit-item-type').val(item.type);
                    // $('#edit-item-price').val(parseFloat(item.price).toFixed(2));
                    $('#edit-item-price').val(
                        parseFloat(item.price).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })
                    );
                    $('#edit-item-stock').val(item.stock);

                    $('#editItemModal').modal('show');

                } else {
                    console.warn("Item not found:", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading item record:", error);
            }
        });
    });

    $(document).on('click', '#saveEditItem', function() {

        const name = $('#edit-item-name').val().trim();
        const type = $('#edit-item-type').val();
        let priceRaw = $('#edit-item-price').val(); // "24,000.00"
        let price = priceRaw.replace(/,/g, '');
        const stock = parseInt($('#edit-item-stock').val().trim()) || 0;

        console.log(price);
        if (!hasItemChanges()) {
            $('#editItemModal').modal('hide');
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'No Changes Detected',
                html: 'You have not made any changes to the item.'
            }).then((result) => {
                $('#editItemModal').modal('show');
            })
            return; // Stop here if nothing changed
        }

        // Name length restriction
        if (name.length < 3 || name.length > 50) {
            showError('#editItemModal', 'Invalid Name', 'Item name must be between 3 and 50 characters.');
            return;
        }

        // Name allowed characters (letters, numbers, spaces, basic punctuation)
        if (!/^[a-zA-Z0-9\s.,'()-]+$/.test(name)) {
            showError('#editItemModal', 'Invalid Name', 'Item name contains invalid characters.');
            return;
        }

        // Price validation
        if (price <= 0 || !price) {
            showError('#editItemModal', 'Invalid Price', 'Item price must be greater than 0.');
            return;
        }

        // Extra validation rules
        if (!name || !type || !price) {
            showError('#editItemModal', 'Missing Fields', 'Please complete all fields.');
            return;
        }


        $.ajax({
            url: 'handlers/inventory-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'updateItem',
                item_id: ActiveRowId,
                name,
                type,
                price,
                stock
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
                    $('#editItemModal').modal('hide');

                    // Locate the active row in the DOM
                    const tr = $(`tr[data-id="${ActiveRowId}"]`);

                    // Update HTML cells directly
                    tr.find('td[data-role="name"]').text(name);
                    tr.find('td[data-role="type"]').text(type);
                    tr.find('td[data-role="stock"]').text(stock);

                    // Format cost like PHP
                    tr.find('td[data-role="price"]').text(formatItemPrice(price));

                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Item Updated',
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

    $(document).on('click', '#addItem', function() {

        const name = $('#item-name').val().trim();
        const type = $('#item-type').val();
        const price = parseFloat($('#item-price').val().trim()) || 0;
        const stock = parseInt($('#item-stock').val().trim()) || 0;

        // Name length restriction
        if (name.length < 3 || name.length > 50) {
            showError('#addItemModal', 'Invalid Name', 'Item name must be between 3 and 50 characters.');
            return;
        }

        // Name allowed characters (letters, numbers, spaces, basic punctuation)
        if (!/^[a-zA-Z0-9\s.,'()-]+$/.test(name)) {
            showError('#addItemModal', 'Invalid Name', 'Item name contains invalid characters.');
            return;
        }

        // Price validation
        if (price <= 0 || !price) {
            showError('#addItemModal', 'Invalid Price', 'Item price must be greater than 0.');
            return;
        }

        // Stock validation
        if (stock < 0 || !stock) {
            showError('#addItemModal', 'Invalid Stock', 'Stock cannot be less than 0.');
            return;
        }

        // Extra validation rules
        if (!name || !type || isNaN(price)) {
            showError('#addItemModal', 'Missing Fields', 'Please complete all fields.');
            return;
        }

        $.ajax({
            url: 'handlers/inventory-handler.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'addItem',
                name,
                type,
                price,
                stock
            },
            success(response) {
                $('#addItemModal').modal('hide');

                if (response.status === 'success') {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Item Added!',
                        text: response.message
                    })
                    const newRowHtml = `
                    <tr class="data-rows" data-id="${response.insert_id}" onclick="setActiveRow(this);">
                        <td id="aydis">${response.insert_id}</td>
                        <td data-role="status">Available</td>
                        <td data-role="name" class="ellipsis-tooltip">${name}</td>
                        <td data-role="price">₱ ${price}</td>
                        <td data-role="type" class="ellipsis-tooltip">${type}</td>
                        <td data-role="stock" class="ellipsis-tooltip">${stock}</td>
                    </tr>`;

                    table.row.add($(newRowHtml)).draw(false);
                } else {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Failed Adding Item',
                        text: response.message || 'Adding failed.'
                    }).then((result) => {
                        $('#addItemModal').modal('show');
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



    $(document).on('click', '.activate-btn, .deactivate-btn', function() {
        if (checkSelection()) {
            return; // Exit if no valid selection
        }

        const isActivate = $(this).hasClass('activate-btn');
        const actionType = isActivate ? 'activateItem' : 'deactivateItem';

        // adjust column index if needed
        const statusCell = activeRow.cells[1].textContent.trim().toLowerCase();

        // Prevent invalid actions
        if (isActivate && statusCell === 'available') {
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'Already Available',
                text: 'This item is already marked as available.'
            });
            return;
        }
        if (!isActivate && statusCell === 'unavailable') {
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-info-circle"></i>',
                title: 'Already Unavailable',
                text: 'This item is already marked as unavailable.'
            });
            return;
        }

        // Dynamic confirmation message
        const confirmTitle = isActivate ? 'Make Item Available' : 'Make Item Unavailable';
        const htmlList = isActivate ?
            `
        <ul style="text-align: left; padding-left: 1.5em;">
            <li>This item will be marked as <b>Available</b>.</li>
            <li>It can be selected for service records item management again.</li>
        </ul>
        <small>Please review item details before proceeding.</small>
        ` :
            `
        <ul style="text-align: left; padding-left: 1.5em;">
            <li>This item will be marked as <b>Unavailable</b>.</li>
            <li>Users will no longer see it as selectable.</li>
        </ul>
        <small><b>This can be reversed later</b> by making the item available again.</small>
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
                    url: 'handlers/inventory-handler.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: actionType,
                        item_id: ActiveRowId
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            // Update status cell in the table
                            $(`tr[data-id="${ActiveRowId}"] td[data-role="status"]`)
                                .text(isActivate ? 'Available' : 'Unavailable');
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
                            text: 'Something went wrong while processing the item update.'
                        });
                    }
                });
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