<?php
session_name('CABTECH_WEBSITE');
session_start();
// myservices.php
// include header/navigation if present (adjust relative paths as needed)
if (file_exists('../includes/header.php')) {
    include_once '../includes/header.php';
}
include_once '../includes/headNav.php';

// start page
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CabTech Auto Services - My Services</title>
    <link rel="stylesheet" href="../style/servicePage.css">
    <!-- Make sure Bootstrap + jQuery are loaded in your includes/header or headNav.
         If not, include them here. -->
</head>

<body>
    <main class="py-4">
        <section class="container mb-5">
            <?php

            // include DB connection
            $path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
            if (file_exists($path)) include_once($path);

            // ensure DB connection exists
            if (!isset($db_connection) || !$db_connection) {
                echo '<div class="alert alert-danger">Database connection not available.</div>';
                echo '</section></main></body></html>';
                exit;
            }

            // ensure logged in (client_id session)
            $client_id = $_SESSION['client_id'] ?? null;
            if (!$client_id) {
                echo '<div class="alert alert-warning">You must be logged in to view your services. <a href="#" class="open-login">Log in</a></div>';
                echo '</section></main></body></html>';
                exit;
            }

            /**
             * Fetch requests for a client filtered by status condition.
             * Uses your real tables and COALESCE to pick sched_dt over request_dt when available.
             */
            function fetch_requests($db, $client_id, $status_condition_sql)
            {
                $sql = "
                SELECT
                    sr.request_id,
                    sr.status,
                    sr.sched_dt,
                    sr.request_dt,
                    COALESCE(NULLIF(sr.sched_dt, ''), NULLIF(sr.request_dt, '')) AS display_dt,
                    sr.resched_count,
                    v.plate_number,
                    v.make,
                    v.model,
                    GROUP_CONCAT(
                        CONCAT(
                            CASE
                                WHEN rst.service_id IS NULL OR rst.service_id = 0 THEN rst.custom_service
                                ELSE svc.service_name
                            END,
                            '||',
                            COALESCE(rst.clients_comment, ''),
                            '||',
                            rst.rst_id,
                            '||',
                            COALESCE(rst.custom_labor_cost, 0)
                        ) SEPARATOR '%%'
                    ) AS service_comment_pairs,
                    sr.request_type
                FROM requeststbl sr
                LEFT JOIN vehiclestbl v 
                    ON v.vehicle_id = sr.vehicle_id
                LEFT JOIN requested_servicestbl rst 
                    ON rst.request_id = sr.request_id
                LEFT JOIN servicestbl svc 
                    ON svc.service_id = rst.service_id
                WHERE sr.client_id = ?
                AND ($status_condition_sql)
                GROUP BY sr.request_id
                ORDER BY display_dt DESC, sr.request_dt DESC;
                ";

                $stmt = mysqli_prepare($db, $sql);
                if (!$stmt) return [];

                mysqli_stmt_bind_param($stmt, 'i', $client_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);

                $rows = [];
                while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

                mysqli_stmt_close($stmt);
                return $rows;
            }

            // define status groups: use exact enum values from your schema
            $ongoingStatuses = "sr.status IN ('Pending','Approved','In Progress')";
            $historyStatuses = "sr.status IN ('Completed','Cancelled')";

            $ongoing = fetch_requests($db_connection, $client_id, $ongoingStatuses);
            $history = fetch_requests($db_connection, $client_id, $historyStatuses);

            ?>
            <h2 class="mb-4">My Services</h2>

            <div id="myservices-alerts"></div>

            <!-- ONGOING -->
            <h4 class="mt-3">Ongoing Services</h4>
            <?php if (empty($ongoing)): ?>
                <div class="alert alert-info">No ongoing service requests found.</div>
            <?php else: ?>
                <?php foreach ($ongoing as $req):
                    $rid = (int)$req['request_id'];
                    $displayDate = '—';
                    if (!empty($req['display_dt'])) {
                        $ts = strtotime($req['display_dt']);
                        if ($ts !== false) $displayDate = date('M d, Y h:i A', $ts);
                    }

                    // badge color by status
                    $status = trim((string)($req['status'] ?? ''));
                    switch (strtolower($status)) {
                        case 'completed':
                            $badgeClass = 'bg-success';
                            break;
                        case 'cancelled':
                            $badgeClass = 'bg-secondary';
                            break;
                        case 'pending':
                            $badgeClass = 'bg-warning text-dark';
                            break;
                        case 'approved':
                            $badgeClass = 'bg-info text-dark';
                            break;
                        case 'in progress':
                            $badgeClass = 'bg-primary';
                            break;
                        default:
                            $badgeClass = 'bg-secondary';
                    }
                ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Request #<?= htmlspecialchars($rid) ?></strong>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <p><strong>Vehicle:</strong><br><?= htmlspecialchars(($req['plate_number'] ? "{$req['plate_number']} — " : '') . trim(($req['make'] ?? '') . ' ' . ($req['model'] ?? ''))) ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Request Type:</strong><br><?= htmlspecialchars($req['request_type'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Appointment Date:</strong><br><?= htmlspecialchars($displayDate) ?></p>
                                </div>
                            </div>


                            <div class="d-flex align-items-center mt-3">
                                <h6 class="mb-0 me-3">Requested Services:</h6>
                                <?php if (strtolower($req['status']) === 'pending'): ?>
                                    <button class="btn btn-sm btn-success request-another-service"
                                        data-request-id="<?= htmlspecialchars($req['request_id'] ?? '') ?>">
                                        + Request Another Service
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered request-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Service Name</th>
                                            <th>Estimated Labor Cost</th>
                                            <th>Your Comments/Description</th>
                                            <?php if (strtolower($req['status']) === 'pending'): ?>
                                                <th>Action</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $pairs = array_filter(array_map('trim', explode('%%', $req['service_comment_pairs'] ?? '')));
                                        $i = 1;
                                        $totalEstimated = 0;

                                        foreach ($pairs as $pair):
                                            // Split into 4 parts: [service, comment, rst_id, labor_cost]
                                            $parts = explode('||', $pair, 4);
                                            $parts = array_pad($parts, 4, '');
                                            [$svc, $comment, $rst_id, $labor_cost] = array_map('trim', $parts);

                                            $numericCost = is_numeric($labor_cost) ? (float)$labor_cost : 0;
                                            $totalEstimated += $numericCost;

                                            $formattedCost = '₱' . number_format($numericCost, 2);
                                        ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= htmlspecialchars($svc ?: '') ?></td>
                                                <td><?= $formattedCost ?></td>
                                                <td class="service-comment"><?= htmlspecialchars($comment ?: '// No Comments Provided') ?></td>

                                                <?php if (strtolower($req['status']) === 'pending'): ?>
                                                    <td>
                                                        <button
                                                            class="btn btn-sm btn-outline-primary edit-comment"
                                                            data-rst-id="<?= htmlspecialchars($rst_id ?: '') ?>">
                                                            Edit Comment
                                                        </button>
                                                        <button
                                                            class="btn btn-sm btn-danger remove-service"
                                                            data-rst-id="<?= htmlspecialchars($rst_id ?: '') ?>">
                                                            Remove
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="<?php echo strtolower($req['status']) === 'pending' ? 5 : 4; ?>" class="">
                                                <strong>Total Estimated Labor Cost:</strong>
                                                <span class="text-primary">₱<?= number_format($totalEstimated, 2) ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="<?php echo strtolower($req['status']) === 'pending' ? 5 : 4; ?>" class="text-muted small fst-italic">
                                                <em>Note:</em> The total shown above is an <strong>estimated cost</strong> and may vary based on products and parts used.
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>

                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-view-invoice"
                                    data-request-id="<?= $rid ?>"
                                    data-status="<?= htmlspecialchars($req['status']) ?>">
                                    View Invoice
                                </button>

                                <?php if (in_array($req['status'], ['Pending', 'Approved'])): ?>
                                    <button class="btn btn-sm btn-danger btn-cancel-request" data-request-id="<?= $rid ?>">Cancel Request</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>


            <!-- HISTORY -->
            <h4 class="mt-4">Previous Services / History</h4>
            <?php if (empty($history)): ?>
                <div class="alert alert-info">No previous service history found.</div>
            <?php else: ?>
                <div class="accordion" id="historyAccordion">
                    <?php foreach ($history as $req):
                        $rid = (int)$req['request_id'];
                        $headingId = "historyHeading{$rid}";
                        $collapseId = "historyCollapse{$rid}";

                        $displayDate = '—';
                        if (!empty($req['display_dt'])) {
                            $ts = strtotime($req['display_dt']);
                            if ($ts !== false) $displayDate = date('M d, Y h:i A', $ts);
                        }

                        $status = trim((string)($req['status'] ?? ''));
                        switch (strtolower($status)) {
                            case 'completed':
                                $badgeClass = 'bg-success';
                                break;
                            case 'cancelled':
                                $badgeClass = 'bg-secondary';
                                break;
                            case 'invoice issued':
                                $badgeClass = 'bg-dark';
                                break;
                            default:
                                $badgeClass = 'bg-secondary';
                        }
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?= htmlspecialchars($headingId) ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($collapseId) ?>">
                                    <strong>Request #<?= htmlspecialchars($rid) ?></strong> — <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                </button>
                            </h2>
                            <div id="<?= htmlspecialchars($collapseId) ?>" class="accordion-collapse collapse" aria-labelledby="<?= htmlspecialchars($headingId) ?>" data-bs-parent="#historyAccordion">
                                <div class="accordion-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <p><strong>Vehicle:</strong><br><?= htmlspecialchars(($req['plate_number'] ? "{$req['plate_number']} — " : '') . trim(($req['make'] ?? '') . ' ' . ($req['model'] ?? ''))) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Final Status:</strong><br><?= htmlspecialchars($req['status']) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Appointment Date:</strong><br><?= htmlspecialchars($displayDate) ?></p>
                                        </div>
                                    </div>

                                    <h6 class="mt-3">Services Requested:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered request-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Service Name</th>
                                                    <th>Your Comments/Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $pairs = array_map('trim', explode('%%', $req['service_comment_pairs'] ?? ''));
                                                $i = 1;

                                                foreach ($pairs as $pair):
                                                    if (empty($pair)) continue;
                                                    [$svc, $comment] = array_map('trim', explode('||', $pair . '||')); // prevents undefined offset
                                                ?>
                                                    <tr>
                                                        <td><?= $i++ ?></td>
                                                        <td><?= htmlspecialchars($svc ?: '// No Service Name') ?></td>
                                                        <td><?= htmlspecialchars($comment ?: '// No Comments Provided') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary btn-view-invoice"
                                            data-request-id="<?= $rid ?>"
                                            data-status="<?= htmlspecialchars($req['status']) ?>">
                                            View Invoice
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


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


                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                            <button id="printInvoice" class="btn btn-primary"><i class='bx bx-printer'></i>Print Invoice</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addServiceModalLabel">Request Another Service</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <form id="addServiceForm">
                                <input type="hidden" id="request_id" name="request_id">

                                <div class="mb-3">
                                    <label for="service_select" class="form-label">Select Service</label>
                                    <select class="form-control" id="service_select" name="service_select" style="width: 100%;"></select>
                                </div>

                                <div class="mb-3">
                                    <label for="custom_service" class="form-label">Or enter a custom service</label>
                                    <input type="text" class="form-control" id="custom_service" name="custom_service" placeholder="Custom service name (optional)">
                                </div>

                                <div class="mb-3">
                                    <label for="service_comment" class="form-label">Comments</label>
                                    <textarea class="form-control" id="service_comment" name="service_comment" rows="2" placeholder="Enter additional details..."></textarea>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </main>

    <?php include '../includes/footer.php'; ?>

    <!-- JS: cancel request AJAX -->
    <script>
        $(document).on('click', '.remove-service', function() {
            const rst_id = $(this).data('rst-id');
            console.log("rst ID:", rst_id);

            Swal.fire({
                title: 'Are you sure?',
                text: "This service will be permanently removed from the request.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageRequest.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            rst_id: rst_id,
                            action: 'remove_service'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Removed', response.message, 'success');
                                $(`[data-rst-id="${rst_id}"]`).closest('tr').fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire('Error', 'An error occurred while processing your request.', 'error');
                        }
                    });
                }
            });
        });


        $(document).on('click', '.edit-comment', function() {
            const $btn = $(this);
            const rst_id = $btn.data('rst-id');
            const $row = $btn.closest('tr');
            const $commentCell = $row.find('.service-comment'); // assumes you have a <td class="service-comment">...</td>

            // Get current comment text
            const currentComment = $commentCell.text().trim();

            // Replace text with input
            $commentCell.html(`
                <div class="d-flex align-items-center gap-2">
                    <input type="text" class="form-control form-control-sm new-comment" value="${currentComment}">
                    <button class="btn btn-sm btn-success save-comment" data-rst-id="${rst_id}">Save</button>
                    <button class="btn btn-sm btn-secondary cancel-comment">Cancel</button>
                </div>
            `);

            // Hide edit button to avoid duplicates
            $btn.prop('disabled', true); // disable the button
        });

        // Save updated comment
        $(document).on('click', '.save-comment', function() {
            const $btn = $(this);
            const rst_id = $btn.data('rst-id');
            const $row = $btn.closest('tr');
            const $commentCell = $row.find('.service-comment');
            const newComment = $row.find('.new-comment').val().trim();

            if (newComment === "") {
                Swal.fire('Warning', 'Comment cannot be empty.', 'warning');
                return;
            }

            $.ajax({
                url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageRequest.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    rst_id: rst_id,
                    comment: newComment,
                    action: 'update_comment'
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Updated', response.message, 'success');
                        $commentCell.text(newComment);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire('Error', 'An error occurred while updating comment.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                    $('.edit-comment').prop('disabled', false); // re-enable the button
                }
            });
        });

        // Cancel editing
        $(document).on('click', '.cancel-comment', function() {
            const $row = $(this).closest('tr');
            const $commentCell = $row.find('.service-comment');
            const currentComment = $row.find('.new-comment').val();
            $commentCell.text(currentComment);
            $('.edit-comment').prop('disabled', false); // re-enable the button
        });


        $(document).on('click', '.request-another-service', function() {
            const requestId = $(this).data('request-id');
            $('#request_id').val(requestId);
            $('#addServiceForm')[0].reset();

            // Destroy Select2 if already initialized
            if ($.fn.select2 && $('#service_select').hasClass("select2-hidden-accessible")) {
                $('#service_select').select2('destroy');
            }

            // Fetch all services once via AJAX
            $.ajax({
                url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageRequest.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'fetch_available_services',
                    request_id: requestId
                },
                success: function(data) {
                    if (!Array.isArray(data)) {
                        Swal.fire('Error', 'Invalid data received from server.', 'error');
                        return;
                    }

                    // Build local options for Select2
                    const services = data.map(svc => {
                        const formattedPrice = Number(svc.labor_cost || 0).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        return {
                            id: svc.service_id,
                            text: `${svc.service_name} - ₱${formattedPrice}`
                        };
                    });

                    // Initialize Select2 (client-side search)
                    $('#service_select').select2({
                        placeholder: 'Select a service',
                        dropdownParent: $('#addServiceModal'),
                        data: services,
                        allowClear: true,
                        width: '100%'
                    }).val(null).trigger('change'); // ensure nothing is preselected

                    // Disable/enable custom service input based on selection
                    $('#service_select').on('change', function() {
                        const hasSelection = $(this).val() && $(this).val() !== '';
                        $('#custom_service').prop('disabled', hasSelection);
                        $('#custom_service').val('');
                    });

                    // Make sure it's enabled by default
                    $('#custom_service').prop('disabled', false);

                    $('#addServiceModal').modal('show');


                    $('#addServiceModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire('Error', 'Failed to load services.', 'error');
                }
            });
        });





        // Handle form submission
        $('#addServiceForm').on('submit', function(e) {
            e.preventDefault();



            const serviceId = $('#service_select').val(); // selected from dropdown
            const customService = $('#custom_service').val()?.trim(); // your input for custom service
            const requestId = $('#request_id').val();
            const comment = $('#service_comment').val()?.trim();

            // ✅ Validation: must have either a selected or custom service
            if (!requestId) {
                Swal.fire('Missing Request', 'Request ID is missing. Please try again.', 'warning');
                return;
            }

            if (!serviceId && !customService) {
                Swal.fire('Missing Service', 'Please select a service or enter a custom service.', 'warning');
                return;
            }

            if (comment.length > 255) {
                Swal.fire('Comment Too Long', 'Please limit your comment to 255 characters.', 'warning');
                return;
            }


            const formData = $(this).serializeArray();
            formData.push({
                name: 'action',
                value: 'add_service_to_request'
            });

            $.ajax({
                url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageRequest.php',
                type: 'POST',
                dataType: 'json',
                data: formData,
                beforeSend: function() {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Adding service to your request.',
                        didOpen: () => Swal.showLoading(),
                        allowOutsideClick: false
                    });
                },
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire('Added', response.message, 'success').then(() => {
                            location.reload(); // reload to reflect changes
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                    console.error(error);
                }
            });
        });












        function formatSqlDate(sqlDate, includeTime = true) {
            if (!sqlDate) return 'Not yet completed';
            const date = new Date(sqlDate);
            if (isNaN(date.getTime())) return 'Invalid date';

            const options = {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            };

            if (includeTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
                options.hour12 = true;
            }

            return date.toLocaleString('en-US', options);
        }

        function formatCurrency(amount) {
            if (isNaN(amount)) return '₱0.00';
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }



        let services = []; //DITO
        let itemsNeededGlobal = [];
        let mechanics = [];
        let invoice = {};
        let client = {};
        let vehicle = {};

        $('.btn-view-invoice').on('click', function() {
            let requestId = $(this).data('request-id');
            const status = $(this).data('status');
            const statusLower = status.toLowerCase();
            console.log('Fetching details for request ID:', requestId);

            if (statusLower === 'cancelled') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invoice Not Available',
                    text: 'This request has been cancelled and has no invoice.'
                });
                return;
            }

            if (statusLower === 'pending') {
                Swal.fire({
                    icon: 'info',
                    title: 'Invoice Not Ready',
                    text: 'This request is still pending and the invoice is not yet available.'
                });
                return;
            }

            if (statusLower === 'approved') {
                Swal.fire({
                    icon: 'success',
                    title: 'Invoice Not Issued Yet',
                    text: 'This request has been approved but the invoice has not been issued.'
                });
                return;
            }

            $.ajax({
                url: '/CABTECH/CAPSTONE - WEBSITE/handlers/get_fullService.php',
                type: 'POST',
                data: {
                    action: 'getRowData',
                    request_id: requestId
                },
                dataType: 'json',
                success: function(response) {

                    if (!response.success) return alert(response.error || 'Failed to fetch service details.');


                    // --- SAFE ASSIGNMENTS / FALLBACKS ---
                    const req = response || {};
                    services = req.services || [];
                    itemsNeededGlobal = req.items_needed || req.itemsNeeded || []; // accept both naming conventions
                    mechanics = req.mechanics || [];
                    invoice = req.invoice || {};
                    client = req.client || {};
                    vehicle = req.vehicle || {};

                    // --- CLIENT ---
                    $('.inv-c-name').text(client.full_name || `${client.last_name || ''} ${client.first_name || ''}`.trim());
                    $('.inv-c-email').text(client.email || '');
                    $('.inv-c-number').text(client.contact_number || '');
                    $('.inv-c-address').text(client.address || '');

                    // --- VEHICLE ---
                    $('.inv-v-model-make').text((vehicle.make || '') + (vehicle.model ? ' / ' + vehicle.model : ''));
                    $('.inv-v-plate').text(vehicle.plate_number || '');
                    $('.inv-v-color').text(vehicle.color || '');
                    $('.inv-v-transmission').text(vehicle.transmission_type || '');
                    $('.inv-v-fuel').text(vehicle.fuel_type || '');

                    // --- MECHANICS ---
                    let mechHtml = '';
                    if (mechanics.length) {
                        mechanics.forEach(m => {
                            const last = m.last_name || m.ln || '';
                            const first = m.first_name || m.fn || '';
                            mechHtml += `<p class="ellipsis-tooltip"><span class="inv-m-name">${last}${last && first ? ', ' : ''}${first}</span></p>`;
                        });
                    } else {
                        mechHtml = '<p class="ellipsis-tooltip">Not Assigned</p>';
                    }
                    $("#inv-assigned-m").html(`<b class="head-details">Assigned Mechanic/s</b>${mechHtml}`);

                    // --- INVOICE META ---
                    $('.inv-no').text(invoice.invoice_id || invoice.invoice_no || '—');
                    $('.inv-status').text(invoice.status || 'Draft');
                    $('.inv-issueDate').html(invoice.issued_dt ? formatSqlDate(invoice.issued_dt, true) : 'Not yet completed');

                    // --- BUILD TABLE: Services (with subtotals) + misc items ---
                    let tbody = '';
                    let grandTotal = 0;

                    // Loop services
                    services.forEach(service => {
                        // field fallbacks
                        const rstId = service.rst_id ?? service.rstId ?? null;
                        const svcName = service.service_name || service.name || service.custom_service || 'Service';
                        const laborCost = parseFloat(service.labor_cost ?? service.custom_labor_cost ?? service.price ?? 0) || 0;
                        const svcQty = parseInt(service.quantity || 1) || 1;

                        let serviceSubtotal = 0;

                        // Service header row
                        const isCustom = (service.service_id === null || service.service_id === undefined) || !!service.custom_service;
                        tbody += `
                        <tr class="inv-service-head">
                            <td><b>${isCustom ? '<span title="Custom Service" style="color:red;">* </span>' : ''}${htmlEscape(svcName)}</b></td>
                            <td colspan="3"></td>
                        </tr>
                        `;

                        // Labor row
                        const laborTotal = laborCost * svcQty;
                        serviceSubtotal += laborTotal;
                        tbody += `
                        <tr>
                            <td class="inv-marleft">Labor</td>
                            <td>${formatCurrency(laborCost)}</td>
                            <td>${svcQty}</td>
                            <td>${formatCurrency(laborTotal)}</td>
                        </tr>
                        `;

                        // Items attached to this service
                        const svcItems = (service.itemsNeeded || []).slice(); // ensure array
                        svcItems.forEach(item => {
                            // item field fallbacks
                            const itemName = item.item_name || item.name || item.itemName || 'Item';
                            const itemPrice = parseFloat(item.price ?? item.record_price ?? 0) || 0;
                            const itemQty = parseInt(item.quantity || 1) || 1;
                            const itemTotal = itemPrice * itemQty;

                            serviceSubtotal += itemTotal;

                            tbody += `
                        <tr>
                            <td class="inv-marleft">— ${htmlEscape(itemName)}</td>
                            <td>${formatCurrency(itemPrice)}</td>
                            <td>${itemQty}</td>
                            <td>${formatCurrency(itemTotal)}</td>
                        </tr>
                        `;
                        });

                        // Subtotal for the service
                        tbody += `
                        <tr class="subtotal">
                            <td colspan="3" class="inv-subtotal"><b>Subtotal</b></td>
                            <td><b>${formatCurrency(serviceSubtotal)}</b></td>
                        </tr>
                        `;

                        grandTotal += serviceSubtotal;
                    });

                    // --- MISCELLANEOUS items (rst_id === 0) from global items array ---
                    const miscItems = (itemsNeededGlobal || []).filter(itm => (itm.rst_id === 0 || itm.rstId === 0));
                    if (miscItems.length > 0) {
                        let miscSubtotal = 0;
                        tbody += `
                        <tr class="inv-service-head">
                            <td><b>Miscellaneous</b></td>
                            <td colspan="3"></td>
                        </tr>
                        `;
                        miscItems.forEach(item => {
                            const itemName = item.item_name || item.name || 'Item';
                            const itemPrice = parseFloat(item.price ?? item.record_price ?? 0) || 0;
                            const itemQty = parseInt(item.quantity || 1) || 1;
                            const itemTotal = itemPrice * itemQty;
                            miscSubtotal += itemTotal;

                            tbody += `
                            <tr>
                                <td class="inv-marleft">${htmlEscape(itemName)}</td>
                                <td>${formatCurrency(itemPrice)}</td>
                                <td>${itemQty}</td>
                                <td>${formatCurrency(itemTotal)}</td>
                            </tr>
                            `;
                        });

                        tbody += `
                        <tr class="subtotal">
                            <td colspan="3" class="inv-subtotal"><b>Subtotal</b></td>
                            <td><b>${formatCurrency(miscSubtotal)}</b></td>
                        </tr>
                        `;
                        grandTotal += miscSubtotal;
                    }

                    // --- RENDER TABLE ---
                    $('#invoiceTable tbody').html(tbody);

                    // Replace entire tfoot so it matches your layout
                    $('#invoiceTable tfoot').html(`
                    <tr>
                        <td colspan="3" class="inv-grand-txt"><b>Grand Total</b></td>
                        <td class="inv-grand-val"><b>${formatCurrency(grandTotal)}</b></td>
                    </tr>
                    `);

                    /* --- small helpers (add these once somewhere reachable) --- */
                    function htmlEscape(str) {
                        if (str === undefined || str === null) return '';
                        return String(str)
                            .replace(/&/g, '&amp;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;');
                    }


                    // Show Bootstrap 5 modal
                    var modal = new bootstrap.Modal(document.getElementById('ServiceInvoiceModal'));
                    modal.show();
                },
                error: function(err) {
                    console.error(err);
                    alert('Error fetching service details.');
                }
            });
        });

        $('#printInvoice').click(function() {
            Swal.fire({
                title: 'Loading...',
                text: 'Please wait while the invoice is being generated.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading(); // show the spinner
                }
            });
            $.ajax({
                url: "/CABTECH/CAPSTONE - SYSTEM/pages/invoicePdf.php",
                method: "POST",
                data: {
                    action: "PrintInvoice", //DITO
                    request_servicesInfo: JSON.stringify(services),
                    items_needed: JSON.stringify(itemsNeededGlobal),
                    request_Client: JSON.stringify(client),
                    request_Vehicle: JSON.stringify(vehicle),
                    service_invoice: JSON.stringify(invoice),
                    mechanics_assigned: JSON.stringify(mechanics)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: "success", // built-in icons: success, error, warning, info, question
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
























        $(document).on('click', '.btn-cancel-request', function(e) {
            e.preventDefault();
            const btn = $(this);
            const requestId = btn.data('request-id');
            if (!requestId) return;

            // Confirm with SweetAlert
            Swal.fire({
                title: 'Cancel Request?',
                text: 'This action may not be reversible. Do you wish to continue?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'No, keep it',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                btn.prop('disabled', true).text('Cancelling...');

                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageRequest.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        request_id: requestId,
                        action: 'cancel_request' // ✅ added this line
                    },
                    success: function(resp) {
                        if (resp && resp.success) {
                            Swal.fire({
                                title: 'Cancelled',
                                text: resp.message || 'Your request has been successfully cancelled.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: resp && resp.message ? resp.message : 'Failed to cancel request. Please try again.',
                                icon: 'error'
                            });
                            btn.prop('disabled', false).text('Cancel Request');
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Network Error',
                            text: 'A network error occurred while cancelling your request. Please try again later.',
                            icon: 'error'
                        });
                        btn.prop('disabled', false).text('Cancel Request');
                    }
                });
            });
        });
    </script>
</body>

</html>