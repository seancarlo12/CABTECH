<?php
session_start();
header('Content-Type: application/json');

require_once '../dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

use Dompdf\Options;

// Get POSTed data
$action = $_POST['action'] ?? '';
$request_servicesInfo = isset($_POST['request_servicesInfo']) ? json_decode($_POST['request_servicesInfo'], true) : [];
$items_needed = isset($_POST['items_needed']) ? json_decode($_POST['items_needed'], true) : [];
$requestClient = isset($_POST['request_Client']) ? json_decode($_POST['request_Client'], true) : [];
$request_Vehicle = isset($_POST['request_Vehicle']) ? json_decode($_POST['request_Vehicle'], true) : [];
$service_invoice = isset($_POST['service_invoice']) ? json_decode($_POST['service_invoice'], true) : [];
$mechanics_assigned = isset($_POST['mechanics_assigned']) ? json_decode($_POST['mechanics_assigned'], true) : [];

if ($action === 'PrintInvoice') {

    function formatDate($dateInput)
    {
        if (empty($dateInput)) return 'Not yet completed';
        $dt = new DateTime($dateInput);
        return $dt->format('F j, Y'); // e.g., "September 22, 2025"
    }

    function formatTime($dateInput)
    {
        if (empty($dateInput)) return 'Not yet completed';
        $dt = new DateTime($dateInput);
        return $dt->format('h:i A'); // e.g., "03:45 PM"
    }
    // You could update DB here to mark invoice as completed
    // For this example, we just generate HTML

    $grandTotal = 0;
    $servicesTotal = 0;
    $itemsTotal = 0;

    ob_start(); // capture HTML

    $logoPath = __DIR__ . '/../assets/img/primary_logo.svg';
    $logoBase64 = base64_encode(file_get_contents($logoPath));

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Service Invoice <?= htmlspecialchars($service_invoice['invoice_id'] ?? 'N/A') ?></title>
        <!-- <link rel="stylesheet" href="style/serviceRecords.css">
        <link rel="stylesheet" href="style/pages.css"> -->
        <style>
            body {
                font-family: "Poppins";
                font-size: 14px !important;
            }


            #invoiceTable {
                width: 100%;
            }

            #ServiceInvoiceModal .modal-body {
                display: flex;
            }


            #invoiceTable thead {
                background: #cacaca;
                border: none !important;
                position: sticky;
                top: 0;
            }

            #invoiceTable thead th {
                padding-top: 5px;
                padding-left: 10px;
            }




            #invoiceTable tr {
                border-bottom: 2px solid #cacaca;
                /* padding: 10px !important; */
            }

            #invoiceTable td {
                padding: 4px 10px;
            }

            #invoiceTable tfoot tr {
                position: sticky;
                bottom: 0;
                background: #fff;
                /* match table bg */
                z-index: 10;
            }


            #right-invoicebox {
                height: 700px;
                max-height: 700px;
                overflow-y: auto;
                /* vertical scroll if content exceeds */
                overflow-x: hidden;
                border: 1px solid #ddd;
                /* optional border */
            }

            .inv-marleft {
                display: block;
                margin-left: 30px;
            }

            .inv-subtotal {
                text-align: right;
                font-weight: bold;
                /* background-color: rgba(233, 233, 233, 0.8); */
            }

            .inv-service-head {
                background-color: rgba(233, 233, 233, 0.8);
            }

            .inv-grand-txt {
                text-align: right;
                text-transform: uppercase;
                /* font-size: 20px; */
                background-color: rgba(17, 16, 29, 0.9);
                color: white;
            }

            .inv-grand-val {
                font-size: 20px;
                background-color: rgba(17, 16, 29, 0.9);
                color: white;
            }

            thead tr th {
                text-align: left !important;
            }

            thead tr {
                background-color: #11101d;
                color: #f5f5f5;
            }


            .qty {
                text-align: center !important;
            }



            #details>div {
                display: inline-block;
                vertical-align: top;
                width: 35%;
                /* or adjust so 4 divs fit in a row */
                margin-right: 1%;
                box-sizing: border-box;
                border: 1px solid black;
                border-radius: 2px;
                /* max-height: 130px; */
                min-height: 150px;
                margin-bottom: 5px;
            }

            #details div p {
                margin: 3px 5px;
            }

            #details {
                font-size: 12px;
                width: 100%;
            }

            .head-details {
                text-transform: uppercase;
                background-color: #11101d;
                color: #f5f5f5;
                display: block;
                padding: 3px 5px;
            }

            #invoice-details {
                float: right;
                margin-right: 0px !important;
                min-width: 160px !important;
                max-width: 160px !important;
            }

            #cabtech-details {
                width: 100%;
                margin-bottom: 30px;
                box-sizing: border-box;
            }

            #cabtech-details #leftside {
                display: inline-block;
                vertical-align: top;
            }

            #cabtech-details #rightside {
                float: right;
            }

            #cabtech-details #rightside h2 {
                border-radius: 5px;
                padding: 5px 10px;
                background-color: #11101d;
                color: #f5f5f5;
                margin-top: 4px;
            }

            #cabtech-details p {
                font-size: 12px;
                margin-left: 10px;
            }

            #cabtech-details img {
                height: 70px;
            }

            #sign {
                border-top: 1px solid black;
                /* float: right; */
                margin-top: 50px;
                width: 300px;
            }

            #inv-assigned-m {
                margin-top: 30px;
            }
        </style>
        <script>
            const ctx = $('#myChart')[0].getContext('2d');

            new Chart(ctx, {
                type: 'pie', // bar, line, pie, doughnut, etc.
                data: {
                    labels: ['January', 'February', 'March', 'April'],
                    datasets: [{
                        label: 'Sales',
                        data: [12, 19, 3, 5],
                        backgroundColor: ['#3498db', '#2ecc71', '#f1c40f', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
            
        </script>
    </head>

    <body>
        <div id="cabtech-details">
            <canvas id="myChart"></canvas>
        <img id="chartImg" style="display:none;" />
            <div id="leftside">
                <img src="data:image/png;base64,<?= $logoBase64 ?>">
                <!-- <p>Bolisay St., Poblacion West, General Tinio, 3104, Nueva Ecija</p> -->
                <p>KM 110, Maharlika High Way, <br> Sumacab Este, Cabanatuan City <br>Phone No.:0997-335-3488</p>
            </div>
            <div id="rightside">
                <h2>SERVICE INVOICE</h2>
            </div>
        </div>


        <div id="details">

            <!-- Client Info -->
            <div id="inv-client-info">
                <b class="head-details">Client Information</b>
                <p class="ellipsis-tooltip inv-c-name"><b>Name: </b><span class="inv-issueDate">
                        <?= htmlspecialchars(
                            ($requestClient['last_name'] ?? '') . ', ' . ($requestClient['first_name'] ?? '')
                        ) ?>
                </p>
                <p class="ellipsis-tooltip"><b>Email: </b><span class="inv-c-email"><?= htmlspecialchars($requestClient['email'] ?? '') ?></span></p>
                <p class="ellipsis-tooltip"><b>Contact No.: </b><span class="inv-inv-c-number"><?= htmlspecialchars($requestClient['contact_number'] ?? '') ?></span></p>
                <p class="ellipsis-tooltip address-only"><b>Address: </b><span class="inv-inv-c-address"><?= htmlspecialchars($requestClient['address'] ?? '') ?></span></p>
            </div>

            <!-- Vehicle Info -->
            <div id="inv-vehicle-info">
                <b class="head-details">Vehicle Information</b>
                <p class="ellipsis-tooltip"><b>Model/Make: </b><span class="inv-v-model-make"><?= htmlspecialchars(($request_Vehicle['make'] ?? '') . ' ' . ($request_Vehicle['model'] ?? '')) ?></span></p>
                <p class="ellipsis-tooltip"><b>Plate Number: </b><span class="inv-v-plate"><?= htmlspecialchars($request_Vehicle['plate_number'] ?? '') ?></span></p>
                <p class="ellipsis-tooltip"><b>Color: </b><span class="inv-v-color"><?= htmlspecialchars($request_Vehicle['color'] ?? '') ?></span></p>
                <p class="ellipsis-tooltip"><b>Transmission: </b><span class="inv-v-transmission"><?= htmlspecialchars($request_Vehicle['transmission_type'] ?? '') ?></span></p>
                <p class="ellipsis-tooltip"><b>Fuel Type: </b><span class="inv-v-fuel"><?= htmlspecialchars($request_Vehicle['fuel_type'] ?? '') ?></span></p>
            </div>

            <!-- Invoice Details -->
            <div id="invoice-details">
                <b class="head-details">Invoice Details</b>
                <p class="ellipsis-tooltip"><b>Invoice No: </b><span class="inv-no"><?= htmlspecialchars($service_invoice['invoice_id'] ?? 'N/A') ?></span></p>
                <p class="ellipsis-tooltip"><b>Record ID: </b><span class="inv-no"><?= htmlspecialchars($service_invoice['record_id'] ?? 'N/A') ?></span></p>
                <p class="ellipsis-tooltip"><b>Status: </b><span class="inv-status"><?= htmlspecialchars($service_invoice['status'] ?? 'N/A') ?></span></p>
                <p class="ellipsis-tooltip">
                    <b>Date Issued: </b><br>
                    <span class="inv-issueDate">
                        <?= !empty($service_invoice['issued_dt'])
                            ? formatDate($service_invoice['issued_dt']) . "<br>" . formatTime($service_invoice['issued_dt'])
                            : 'Not yet completed'
                        ?>
                    </span>
                </p>

            </div>

        </div>

        <table id="invoiceTable" cellspacing="0" cellpadding="8">
            <thead>
                <tr>
                    <th>SERVICES & ITEMS</th>
                    <th>PRICE (₱)</th>
                    <th class="qty">QTY</th>
                    <th>TOTAL (₱)</th>
                </tr>
            </thead>
            <tbody>

                <?php
                // Loop services
                foreach ($request_servicesInfo as $service) {
                    $serviceSubtotal = 0;
                    echo '<tr class="inv-service-head">';
                    echo '<td><b>' . ($service['service_id'] === null ? '<span style="color:red;"><b>*</b></span> ' : '') . htmlspecialchars($service['service_name']) . '</b></td>';
                    echo '<td colspan="3"  class="inv-subtotal"></td></tr>';

                    // Labor row
                    echo '<tr>';
                    echo '<td class="inv-marleft">Labor</td>';
                    echo '<td>₱' . number_format($service['labor_cost'], 2) . '</td>';
                    echo '<td class="qty">1</td>';
                    echo '<td>₱' . number_format($service['labor_cost'], 2) . '</td>';
                    echo '</tr>';

                    $serviceSubtotal += $service['labor_cost'];
                    $servicesTotal += $service['labor_cost'];

                    // Items for this service
                    foreach ($items_needed as $itm) {
                        if ($itm['rst_id'] === $service['rst_id']) {
                            $itemTotal = $itm['record_price'] * $itm['quantity'];
                            $serviceSubtotal += $itemTotal;
                            $itemsTotal += $itemTotal;

                            echo '<tr>';
                            echo '<td class="inv-marleft">' . htmlspecialchars($itm['name']) . '</td>';
                            echo '<td>₱' . number_format($itm['record_price'], 2) . '</td>';
                            echo '<td class="qty">' . $itm['quantity'] . '</td>';
                            echo '<td>₱' . number_format($itemTotal, 2) . '</td>';
                            echo '</tr>';
                        }
                    }

                    // Service subtotal
                    echo '<tr class="subtotal">';
                    echo '<td colspan="3"  class="inv-subtotal">Subtotal</td>';
                    echo '<td><b>₱' . number_format($serviceSubtotal, 2) . '</b></td>';
                    echo '</tr>';

                    $grandTotal += $serviceSubtotal;
                }

                // Misc items (rst_id == 0)
                $miscTotal = 0;
                foreach ($items_needed as $itm) {
                    if ($itm['rst_id'] === 0) {
                        $itemTotal = $itm['record_price'] * $itm['quantity'];
                        $miscTotal += $itemTotal;
                        $itemsTotal += $itemTotal;

                        if ($miscTotal == $itemTotal) echo '<tr class="inv-service-head"><td><b>Miscellaneous</b></td><td colspan="3"  class="inv-subtotal"></td></tr>';

                        echo '<tr>';
                        echo '<td class="inv-marleft">' . htmlspecialchars($itm['name']) . '</td>';
                        echo '<td>₱' . number_format($itm['record_price'], 2) . '</td>';
                        echo '<td class="qty">' . $itm['quantity'] . '</td>';
                        echo '<td>₱' . number_format($itemTotal, 2) . '</td>';
                        echo '</tr>';
                    }
                }
                if ($miscTotal > 0) {
                    echo '<tr class="subtotal">';
                    echo '<td colspan="3"  class="inv-subtotal">Subtotal</td>';
                    echo '<td><b>₱' . number_format($miscTotal, 2) . '</b></td>';
                    echo '</tr>';
                    $grandTotal += $miscTotal;
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="inv-grand-txt"><b>Grand Total</b></td>
                    <td class="inv-grand-val"><b>₱<?= number_format($grandTotal, 2) ?></b></td>
                </tr>
            </tfoot>
        </table>

        <div id="inv-assigned-m">
            <b>Assigned Mechanic/s: </b>
            <?php if (!empty($mechanics_assigned)) : ?>
                <?php foreach ($mechanics_assigned as $mech) : ?>
                    <span class="">
                        <?= htmlspecialchars(
                            ($mech['last_name'] ?? '') . ', ' . ($mech['first_name'] ?? '') . '; '
                        ) ?>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="ellipsis-tooltip">Not Assigned</p>
            <?php endif; ?>
        </div>

        <div id="sign">

            <b>Authorized Representative</b>
        </div>

    <?php

    $html = ob_get_clean();
    $dompdf = new Dompdf();
    $dompdf->set_option('defaultFont', 'DejaVu Sans');
    $options = new Options();
    $options->setIsRemoteEnabled(true); // modern method
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // get PDF as string
    $pdfOutput = $dompdf->output();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'pdfBase64' => base64_encode($pdfOutput)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

exit; // important to stop further output

    ?>