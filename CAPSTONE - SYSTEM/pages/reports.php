<?php
session_name('CABTECH_SYSTEM');
session_start();
include_once '../config/db.php';

/**
 * computeReports
 * computes and returns an associative array of report data based on filters.
 *
 * NOTE: Date filtering uses invoicetbl.issued_dt (inv.issued_dt). If your app needs to
 * filter by a different column (request created date, record date, etc.) change the
 * DATE(inv.issued_dt) references accordingly.
 *
 * @param mysqli $db_connection
 * @param bool $allTime
 * @param string $fromDate (YYYY-MM-DD)
 * @param string $toDate   (YYYY-MM-DD)
 * @param string $serviceType
 * @return array
 */
/**
 * computeReports
 * Computes and returns an associative array of report data based on filters.
 *
 * @param mysqli $db_connection
 * @param bool $allTime
 * @param string $fromDate (YYYY-MM-DD)
 * @param string $toDate   (YYYY-MM-DD)
 * @param string $serviceType
 * @return array
 */
function computeReports($db_connection, $allTime = true, $fromDate = '', $toDate = '', $serviceType = '')
{
    $result = [];

    // -------------------------
    // Build date clauses
    // -------------------------
    // Invoice date clause (only valid when invoicetbl is joined)
    $dateWhereForInv = "";
    if (!$allTime && $fromDate && $toDate) {
        $from = $db_connection->real_escape_string($fromDate);
        $to   = $db_connection->real_escape_string($toDate);
        $dateWhereForInv = " AND DATE(inv.issued_dt) BETWEEN '{$from}' AND '{$to}' ";
    }

    // Request date clause (for queries without invoicetbl)
    $dateWhereForReq = "";
    if (!$allTime && $fromDate && $toDate) {
        $from = $db_connection->real_escape_string($fromDate);
        $to   = $db_connection->real_escape_string($toDate);
        $dateWhereForReq = " AND DATE(req.request_dt) BETWEEN '{$from}' AND '{$to}' ";
    }

    // -------------------------
    // Service type clause
    // -------------------------
    $serviceTypeWhere = "";
    if ($serviceType !== '') {
        $stype = $db_connection->real_escape_string($serviceType);
        $serviceTypeWhere = " AND req.request_type = '{$stype}' ";
    }

    // Base record status filter (only Completed or Invoice Issued)
    $baseRecordStatusWhere = " (r.record_status = 'Completed' OR r.record_status = 'Invoice Issued') ";

    // -------------------------
    // 1) Total completed records
    // -------------------------
    $totalRecords = 0;
    $sql = "SELECT COUNT(DISTINCT r.record_id) AS total
            FROM recordstbl r
            LEFT JOIN requeststbl req ON req.request_id = r.request_id
            LEFT JOIN invoicetbl inv ON inv.record_id = r.record_id
            WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}";
    if ($res = $db_connection->query($sql)) {
        $row = $res->fetch_assoc();
        $totalRecords = (int)($row['total'] ?? 0);
        $res->free();
    }
    $result['totalRecords'] = $totalRecords;

    // -------------------------
    // 2a) Services revenue
    // -------------------------
    $servicesRevenue = 0.0;
    $sql = "SELECT COALESCE(SUM(inv.services_total),0) AS services_rev
            FROM invoicetbl inv
            JOIN recordstbl r ON inv.record_id = r.record_id
            LEFT JOIN requeststbl req ON req.request_id = r.request_id
            WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}";
    if ($res = $db_connection->query($sql)) {
        $row = $res->fetch_assoc();
        $servicesRevenue = (float)($row['services_rev'] ?? 0);
        $res->free();
    }
    $result['servicesRevenue'] = $servicesRevenue;

    // -------------------------
    // 2b) Items revenue split by type
    // -------------------------
    $productsRevenue = 0.0;
    $partsRevenue = 0.0;
    $consumableRevenue = 0.0;

    $sql = "
        SELECT LOWER(TRIM(i.`type`)) AS itm_type,
            COALESCE(SUM(COALESCE(inm.record_price, i.price) * inm.quantity), 0) AS rev
        FROM items_neededtbl inm
        JOIN itemstbl i ON inm.item_id = i.item_id
        JOIN recordstbl r ON inm.record_id = r.record_id
        LEFT JOIN invoicetbl inv ON inv.record_id = r.record_id
        LEFT JOIN requeststbl req ON req.request_id = r.request_id
        WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}
        GROUP BY itm_type
    ";
    if ($res = $db_connection->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $type = $row['itm_type'] ?? '';
            $rev  = (float)($row['rev'] ?? 0);
            if (strpos($type, 'product') !== false) {
                $productsRevenue += $rev;
            } elseif (strpos($type, 'part') !== false) {
                $partsRevenue += $rev;
            } else {
                $consumableRevenue += $rev;
            }
        }
        $res->free();
    }
    $itemsRevenue = $productsRevenue + $partsRevenue + $consumableRevenue;
    $result['itemsRevenue'] = [
        'total' => $itemsRevenue,
        'products' => $productsRevenue,
        'parts' => $partsRevenue,
        'consumables' => $consumableRevenue
    ];

    // -------------------------
    // 2c) Total revenue (grand total)
    // -------------------------
    $totalRevenue = 0.0;
    $sql = "SELECT COALESCE(SUM(inv.grand_total),0) AS total_rev
            FROM invoicetbl inv
            JOIN recordstbl r ON inv.record_id = r.record_id
            LEFT JOIN requeststbl req ON req.request_id = r.request_id
            WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}";
    if ($res = $db_connection->query($sql)) {
        $row = $res->fetch_assoc();
        $totalRevenue = (float)($row['total_rev'] ?? 0);
        $res->free();
    }
    $result['totalRevenue'] = $totalRevenue;

    // -------------------------
    // 3) Record-based status counts
    // -------------------------
    $statusCounts = [];
    $sql = "SELECT req.status AS r_status, COUNT(*) AS cnt
            FROM requeststbl req
            LEFT JOIN recordstbl r ON req.request_id = r.request_id
            WHERE 1=1 {$dateWhereForReq} {$serviceTypeWhere}
            GROUP BY req.status";
    if ($res = $db_connection->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $statusKey = $row['r_status'] ?? '';
            $statusCounts[$statusKey] = (int)$row['cnt'];
        }
        $res->free();
    }
    $result['statusCounts'] = $statusCounts;
    $result['completedCount'] = $statusCounts['Completed'] ?? 0;
    $result['cancelledCount'] = $statusCounts['Cancelled'] ?? 0;

    // -------------------------
    // 4) Top 10 requested services
    // -------------------------
    $topServices = [];
    $sql = "
        SELECT COALESCE(s.service_name, rs.custom_service) AS service_name,
            COUNT(*) AS cnt
        FROM requested_servicestbl rs
        LEFT JOIN servicestbl s ON rs.service_id = s.service_id
        LEFT JOIN requeststbl req ON rs.request_id = req.request_id
        LEFT JOIN recordstbl r ON req.request_id = r.request_id
        LEFT JOIN invoicetbl inv ON inv.record_id = r.record_id
        WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}
        GROUP BY service_name
        ORDER BY cnt DESC
        LIMIT 10
    ";
    if ($res = $db_connection->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $topServices[] = [
                'name' => $row['service_name'] ?? 'Unknown',
                'cnt'  => (int)$row['cnt']
            ];
        }
        $res->free();
    }
    $result['topServices'] = $topServices;

    // -------------------------
    // 5) Top 10 products
    // -------------------------
    $topProducts = [];
    $sql = "
        SELECT i.name AS item_name, COALESCE(SUM(inm.quantity),0) AS qty
        FROM items_neededtbl inm
        JOIN itemstbl i ON inm.item_id = i.item_id
        JOIN recordstbl r ON inm.record_id = r.record_id
        LEFT JOIN invoicetbl inv ON inv.record_id = r.record_id
        LEFT JOIN requeststbl req ON req.request_id = r.request_id
        WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}
        AND LOWER(i.`type`) LIKE '%product%'
        GROUP BY inm.item_id
        ORDER BY qty DESC
        LIMIT 10
    ";
    if ($res = $db_connection->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $topProducts[] = [
                'name' => $row['item_name'] ?? 'Unknown',
                'qty'  => (int)$row['qty']
            ];
        }
        $res->free();
    }
    $result['topProducts'] = $topProducts;

    // -------------------------
    // 6) Top 10 parts
    // -------------------------
    $topParts = [];
    $sql = "
        SELECT i.name AS item_name, COALESCE(SUM(inm.quantity),0) AS qty
        FROM items_neededtbl inm
        JOIN itemstbl i ON inm.item_id = i.item_id
        JOIN recordstbl r ON inm.record_id = r.record_id
        LEFT JOIN invoicetbl inv ON inv.record_id = r.record_id
        LEFT JOIN requeststbl req ON req.request_id = r.request_id
        WHERE {$baseRecordStatusWhere} {$dateWhereForInv} {$serviceTypeWhere}
        AND LOWER(i.`type`) LIKE '%part%'
        GROUP BY inm.item_id
        ORDER BY qty DESC
        LIMIT 10
    ";
    if ($res = $db_connection->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $topParts[] = [
                'name' => $row['item_name'] ?? 'Unknown',
                'qty'  => (int)$row['qty']
            ];
        }
        $res->free();
    }
    $result['topParts'] = $topParts;

    return $result;
}


// --------------------
// AJAX handler (fetchReports)
// --------------------
if (isset($_GET['action']) && $_GET['action'] === 'fetchReports') {
    header('Content-Type: application/json');

    // sanitize incoming GET params
    $allTime = isset($_GET['allTime']) && ($_GET['allTime'] == '1' || $_GET['allTime'] === 'true');
    $fromDate = isset($_GET['fromDate']) ? $db_connection->real_escape_string($_GET['fromDate']) : '';
    $toDate   = isset($_GET['toDate'])   ? $db_connection->real_escape_string($_GET['toDate']) : '';
    $serviceType = isset($_GET['serviceType']) ? $db_connection->real_escape_string($_GET['serviceType']) : '';

    // compute
    $data = computeReports($db_connection, $allTime, $fromDate, $toDate, $serviceType);

    // chart-friendly arrays
    $data['services_labels'] = array_values(array_map(fn($r) => $r['name'], $data['topServices']));
    $data['services_values'] = array_values(array_map(fn($r) => $r['cnt'], $data['topServices']));
    if (empty($data['services_labels'])) {
        $data['services_labels'] = ['No data'];
        $data['services_values'] = [1];
    }
    $data['products_labels'] = array_values(array_map(fn($r) => $r['name'], $data['topProducts']));
    $data['products_values'] = array_values(array_map(fn($r) => $r['qty'], $data['topProducts']));
    if (empty($data['products_labels'])) {
        $data['products_labels'] = ['No data'];
        $data['products_values'] = [1];
    }
    $data['parts_labels'] = array_values(array_map(fn($r) => $r['name'], $data['topParts']));
    $data['parts_values'] = array_values(array_map(fn($r) => $r['qty'], $data['topParts']));
    if (empty($data['parts_labels'])) {
        $data['parts_labels'] = ['No data'];
        $data['parts_values'] = [1];
    }

    echo json_encode($data);
    exit;
}

// --------------------
// Non-AJAX page render - initial (all time)
// --------------------
$pageData = computeReports($db_connection, true, '', '', '');

$totalRecords = $pageData['totalRecords'];
$servicesRevenue = $pageData['servicesRevenue'];
$itemsRevenueArr = $pageData['itemsRevenue'];
$totalRevenue = $pageData['totalRevenue'];
$completedCount = $pageData['completedCount'];
$cancelledCount = $pageData['cancelledCount'];
$topServices = $pageData['topServices'];
$topProducts = $pageData['topProducts'];
$topParts = $pageData['topParts'];

$services_labels = array_map(fn($r) => $r['name'], $topServices);
$services_values = array_map(fn($r) => $r['cnt'], $topServices);
if (empty($services_labels)) {
    $services_labels = ['No data'];
    $services_values = [1];
}
$products_labels = array_map(fn($r) => $r['name'], $topProducts);
$products_values = array_map(fn($r) => $r['qty'], $topProducts);
if (empty($products_labels)) {
    $products_labels = ['No data'];
    $products_values = [1];
}
$parts_labels = array_map(fn($r) => $r['name'], $topParts);
$parts_values = array_map(fn($r) => $r['qty'], $topParts);
if (empty($parts_labels)) {
    $parts_labels = ['No data'];
    $parts_values = [1];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reports</title>
    <link rel="stylesheet" href="style/reports.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>reports</span></p>

        <div class="dashboard">

            <!-- Filters -->
            <div class="filters">
                <b>Detailed Overall Filters</b>
                <div class="filter-row">
                    <label><input type="checkbox" id="allTime" checked> All time</label>
                </div>
                <div class="filter-row">
                    <label for="fromDate">From</label>
                    <input type="date" id="fromDate">
                </div>
                <div class="filter-row">
                    <label for="toDate">To</label>
                    <input type="date" id="toDate">
                </div>
                <div class="filter-row">
                    <label for="serviceType">Service Type</label>
                    <select id="serviceType" class="form-select">
                        <option value="">All</option>
                        <option value="Appointment">Appointment</option>
                        <option value="Walk In">Walk In</option>
                    </select>
                </div>
                <div class="filter-row">
                    <button type="button" id="applyFilters"><i class='bx bx-check-square' ></i>Apply</button>
                    <button id="exportPdfBtn"><i class='bx bx-download'></i>Download Report</button>
                </div>
            </div>

            <div id="box-container">
                <!-- Stats -->
                <div class="stats" style="display:flex; gap:12px; flex-wrap:wrap;">
                    <div class="stat-box" style="min-width:180px;">
                        <h3>TOTAL COMPLETED RECORDS</h3>
                        <p id="totalRecords"><?php echo $totalRecords; ?></p>
                    </div>

                    <div class="stat-box" style="min-width:180px;">
                        <h3>SERVICES REVENUE</h3>
                        <p id="servicesRevenue">P <?php echo number_format($servicesRevenue, 2); ?></p>
                    </div>

                    <div class="stat-box itemsRev" style="min-width:240px;">
                        <h3>ITEMS REVENUE</h3>
                        <small id="itemsBreakdown">
                            Products: P <?php echo number_format($itemsRevenueArr['products'], 2); ?><br>
                            Parts: P <?php echo number_format($itemsRevenueArr['parts'], 2); ?><br>
                            Consumables: P <?php echo number_format($itemsRevenueArr['consumables'], 2); ?>
                        </small>
                        <p id="itemsRevenueTotal">P <?php echo number_format($itemsRevenueArr['total'], 2); ?></p>
                    </div>

                    <div class="stat-box" style="min-width:180px;">
                        <h3>TOTAL REVENUE</h3>
                        <p id="totalRevenue">P <?php echo number_format($totalRevenue, 2); ?></p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts">
                    <div class="chart-box">
                        <h3>SERVICE REQUESTS OVERVIEW</h3>
                        <div class="insidebox">
                            <div class="canvas-wrapper">
                                <canvas id="completionChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="chart-box">
                        <h3>10 MOST REQUESTED SERVICES</h3>
                        <div class="insidebox">
                            <div class="canvas-wrapper">
                                <canvas id="servicesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="chart-box">
                        <h3>10 MOST USED PRODUCTS</h3>
                        <div class="insidebox">
                            <div class="canvas-wrapper">
                                <canvas id="productsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="chart-box">
                        <h3>10 MOST USED PARTS</h3>
                        <div class="insidebox">
                            <div class="canvas-wrapper">
                                <canvas id="partsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BACK TO TOP BUTTON -->
    <a href="#top" title="Go up"><i class='bx bx-chevron-up' id="goTop"></i></a>

    <script>
        async function exportDashboardToPDF() {
            const dashboard = document.querySelector('#box-container');
            if (!dashboard) {
                alert('Cannot locate .dashboard element');
                return;
            }

            // --- Build a clone so we don't disturb the visible UI ---
            const clone = dashboard.cloneNode(true); // after you append the clone into the hidden holder


            // --- Replace canvases in the clone with image elements (to ensure they render) ---
            const origCanvases = dashboard.querySelectorAll('canvas');
            const cloneCanvases = clone.querySelectorAll('canvas');

            // Convert visible canvases to dataURL and swap in the clone
            cloneCanvases.forEach((c, i) => {
                const origCanvas = origCanvases[i];
                if (!origCanvas) return;
                try {
                    const dataUrl = origCanvas.toDataURL('image/png');
                    const img = document.createElement('img');
                    img.src = dataUrl;
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    img.className = 'replaced-canvas-image';
                    c.parentNode.replaceChild(img, c);
                } catch (err) {
                    console.warn('Could not convert canvas to image:', err);
                }
            });


            // --- Put clone in an offscreen container (so styles apply) ---
            const holder = document.createElement('div');
            holder.style.position = 'fixed';
            holder.style.left = '-9999px';
            holder.style.top = '0';
            holder.style.width = "1520px"; // fits A4 landscape
            holder.appendChild(clone);
            document.body.appendChild(holder);

            // --- Use html2canvas to capture the clone ---
            try {
                const canvas = await html2canvas(clone, {
                    scale: 2, // increase quality
                    useCORS: true, // allow cross-origin images if any (requires proper headers)
                    allowTaint: false,
                    backgroundColor: '#ffffff'
                });

                // get base64 PNG
                const dataUrl = canvas.toDataURL('image/png');

                // send to server
                const payload = {
                    image: dataUrl, // include the data: prefix
                    filters: {
                        allTime: !!document.querySelector('#allTime')?.checked,
                        fromDate: document.querySelector('#fromDate')?.value || '',
                        toDate: document.querySelector('#toDate')?.value || '',
                        serviceType: document.querySelector('#serviceType')?.value || ''
                    }
                };

                // POST JSON to endpoint
                const res = await fetch('pages/reportPdf.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) {
                    const text = await res.text();
                    console.error('PDF generation failed:', text);
                    alert('PDF generation failed. Check console.');
                    return;
                }

                // the response will be the generated PDF stream — to prompt download:
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;

                const now = new Date();
                
                const formattedDate = now.toISOString().slice(0, 10); // YYYY-MM-DD
                a.download = `CabTechSystemReport_${formattedDate}.pdf`; //name: report_2025-10-05-03-52-10.pdf

                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);

            } catch (err) {
                console.error('Capture/send error:', err);
                alert('Failed to capture dashboard: ' + err.message);
            } finally {
                // cleanup
                document.body.removeChild(holder);
            }
        }

        document.getElementById('exportPdfBtn').addEventListener('click', async () => {

            const filters = {
                action: 'fetchReports',
                allTime: $("#allTime").is(":checked") ? 1 : 0,
                fromDate: FPfromDate.input.value,
                toDate: FPtoDate.input.value,
                serviceType: $("#serviceType").val()
            };
            console.log(filters);

            // Basic validation
            if (!filters.allTime && (!filters.fromDate || !filters.toDate)) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Invalid Dates',
                    text: 'Please select both From and To dates or check All Time.',
                    confirmButtonText: 'OK'
                });
                return;
            } else {

                // Show loading Swal
                Swal.fire({
                    iconHtml: "<i class='bx bxs-file-pdf'></i>",
                    title: 'Generating Report...',
                    text: 'Please wait while charts are loading.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Apply filters first
                await applyFilters();

                // Wait for charts/animations to finish rendering
                setTimeout(async () => {
                    await exportDashboardToPDF();

                    // Close loading Swal and show success
                    Swal.close();
                }, 1000); // adjust delay as needed
            }
        });



















        /* Endpoint (same page handles AJAX) */
        const endpoint = "pages/reports.php";

        /* Bootstrap initial data from server-rendered PHP variables */
        const servicesInitialLabels = <?= json_encode($services_labels) ?>;
        const servicesInitialValues = <?= json_encode($services_values) ?>;
        const productsInitialLabels = <?= json_encode($products_labels) ?>;
        const productsInitialValues = <?= json_encode($products_values) ?>;
        const partsInitialLabels = <?= json_encode($parts_labels) ?>;
        const partsInitialValues = <?= json_encode($parts_values) ?>;

        /* Chart contexts */
        const completionCtx = document.getElementById("completionChart");
        const servicesCtx = document.getElementById("servicesChart");
        const productsCtx = document.getElementById("productsChart");
        const partsCtx = document.getElementById("partsChart");

        /* ----------------- Helpers ----------------- */
        /** Escape HTML to avoid XSS in legends (still handy if you later put labels in HTML) */
        function escapeHtml(unsafe) {
            return String(unsafe).replace(/[&<>"'`=\/]/g, function(s) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '/': '&#x2F;',
                    '`': '&#x60;',
                    '=': '&#x3D;'
                })[s];
            });
        }

        /**
         * Normalize labels & values so Chart.js never receives a dataset that sums to 0.
         * Returns { labels: [...], values: [...], isEmpty: boolean }
         */
        function normalizeChartData(labels, values) {
            labels = Array.isArray(labels) ? labels.slice() : ['No data'];
            values = Array.isArray(values) ? values.map(v => Number(v || 0)) : [1];

            let filteredLabels = [];
            let filteredValues = [];
            for (let i = 0; i < labels.length; i++) {
                filteredLabels.push(labels[i] === null || labels[i] === undefined || labels[i] === '' ? 'Unknown' : String(labels[i]));
                filteredValues.push(values[i] === undefined ? 0 : Number(values[i]));
            }
            const sum = filteredValues.reduce((a, b) => a + b, 0);

            if (sum === 0) {
                return {
                    labels: ['No data'],
                    values: [1],
                    isEmpty: true
                };
            }
            return {
                labels: filteredLabels,
                values: filteredValues,
                isEmpty: false
            };
        }

        /* ----------------- Legend label generator helper (used by Chart.js options) ----------------- */
        function legendLabelGenerator(chart) {
            // Chart passed in by Chart.js
            const data = chart.data;
            const ds = data.datasets[0] || {};
            const values = ds.data || [];
            const bg = ds.backgroundColor || [];
            const total = values.reduce((a, b) => a + Number(b || 0), 0);

            return data.labels.map((label, i) => {
                const value = Number(values[i] || 0);
                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                // choose fillStyle whether backgroundColor is array or single color
                let fillStyle = Array.isArray(bg) ? (bg[i] ?? bg[i % bg.length]) : bg;
                // Chart.js expects `hidden` to indicate whether the item is hidden
                const hidden = (typeof chart.getDataVisibility === 'function') ? !chart.getDataVisibility(i) : false;


                // return {
                //     text: `${label}  ${total > 0 ? ' (' + pct + '%)' : ''}`,
                //     fillStyle,
                //     hidden,
                //     index: i
                // };

                return {
                    text: `${label}`,
                    fillStyle,
                    hidden,
                    index: i
                };
            });
        }

        /* ----------------- Create charts with normalized initial data (use Chart.js legend) ----------------- */
        /* Chart common options for doughnuts that place a rich legend on the right */
        const commonDoughnutOptions = {
            responsive: false,
            animation: false,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 9,
                        // generate custom labels that include value and percent
                        generateLabels: function(chart) {
                            return legendLabelGenerator(chart);
                        }
                    },
                    // clicking a legend item toggles visibility of that slice
                    onClick: function(e, legendItem, legend) {
                        const chart = legend.chart;
                        const idx = legendItem.index;
                        // toggle slice visibility (Chart.js helper)
                        if (typeof chart.toggleDataVisibility === 'function') {
                            chart.toggleDataVisibility(idx);
                        } else {
                            // Fallback: toggle via internal visibility state (older versions)
                            const meta = chart.getDatasetMeta(0);
                            meta.data[idx].hidden = !meta.data[idx].hidden;
                        }
                        chart.update();
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const data = context.dataset.data[context.dataIndex] || 0;
                            const total = context.dataset.data.reduce((a, b) => a + Number(b || 0), 0);
                            const pct = total > 0 ? ((Number(data) / total) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${Number(data).toLocaleString()} (${pct}%)`;
                        }
                    }
                }
            }
        };

        /* Completion uses Completed/Cancelled */
        const compInit = normalizeChartData(["Completed", "Cancelled"], [<?= (int)$completedCount ?>, <?= (int)$cancelledCount ?>]);
        let completionChart = new Chart(completionCtx, {
            type: 'doughnut',
            data: {
                labels: compInit.labels,
                datasets: [{
                    data: compInit.values,
                    backgroundColor: ["#4CAF50", "#f44336"]
                }]
            },
            options: {
                ...commonDoughnutOptions,
                plugins: {
                    ...commonDoughnutOptions.plugins,
                    legend: {
                        ...commonDoughnutOptions.plugins.legend,
                        // small tweak: show legend title for completion chart
                    }
                }
            }
        });

        /* Services */
        const sInit = normalizeChartData(servicesInitialLabels, servicesInitialValues);
        let servicesChart = new Chart(servicesCtx, {
            type: 'doughnut',
            data: {
                labels: sInit.labels,
                datasets: [{
                    data: sInit.values,
                    backgroundColor: ["#4CAF50", "#2196F3", "#9C27B0", "#FF9800", "#795548", "#9E9E9E", "#E91E63", "#00BCD4", "#8BC34A", "#FFEB3B"]
                }]
            },
            options: {
                ...commonDoughnutOptions,
                plugins: {
                    ...commonDoughnutOptions.plugins,
                    legend: {
                        ...commonDoughnutOptions.plugins.legend,
                    }
                }
            }
        });

        /* Products */
        const pInit = normalizeChartData(productsInitialLabels, productsInitialValues);
        let productsChart = new Chart(productsCtx, {
            type: 'doughnut',
            data: {
                labels: pInit.labels,
                datasets: [{
                    data: pInit.values,
                    backgroundColor: ["#4CAF50", "#2196F3", "#9C27B0", "#FF9800", "#795548", "#9E9E9E", "#E91E63", "#00BCD4", "#8BC34A", "#FFEB3B"]
                }]
            },
            options: {
                ...commonDoughnutOptions,
                plugins: {
                    ...commonDoughnutOptions.plugins,
                    legend: {
                        ...commonDoughnutOptions.plugins.legend,
                    }
                }
            }
        });

        /* Parts */
        const partInit = normalizeChartData(partsInitialLabels, partsInitialValues);
        let partsChart = new Chart(partsCtx, {
            type: 'doughnut',
            data: {
                labels: partInit.labels,
                datasets: [{
                    data: partInit.values,
                    backgroundColor: ["#4CAF50", "#2196F3", "#9C27B0", "#FF9800", "#795548", "#9E9E9E", "#E91E63", "#00BCD4", "#8BC34A", "#FFEB3B"]
                }]
            },
            options: {
                ...commonDoughnutOptions,
                plugins: {
                    ...commonDoughnutOptions.plugins,
                    legend: {
                        ...commonDoughnutOptions.plugins.legend,
                    }
                }
            }
        });

        /* ----------------- SweetAlert options ----------------- */
        const swalOptions = {
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowEscapeKey: false,
            didOpen: (popup) => {
                Swal.getConfirmButton().focus();
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        /* ----------------- applyFilters() ----------------- */
        function applyFilters(showSuccess = false) {
            const filters = {
                action: 'fetchReports',
                allTime: $("#allTime").is(":checked") ? 1 : 0,
                fromDate: FPfromDate.input.value,
                toDate: FPtoDate.input.value,
                serviceType: $("#serviceType").val()
            };
            console.log(filters);

            // Basic validation
            if (!filters.allTime && (!filters.fromDate || !filters.toDate)) {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Invalid Dates',
                    text: 'Please select both From and To dates or check All Time.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            $.get(endpoint, filters, function(response) {
                if (!response || typeof response !== 'object') {
                    console.error('Invalid response', response);
                    alert('Invalid response from server. Check console.');
                    return;
                }

                // ----------------- Update Stats Text -----------------
                $("#totalRecords").text(response.totalRecords ?? 0);
                $("#servicesRevenue").text('P ' + (Number(response.servicesRevenue ?? 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })));
                $("#itemsBreakdown").html(
                    'Products: P ' + Number(response.itemsRevenue?.products ?? 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + '<br>' +
                    'Parts: P ' + Number(response.itemsRevenue?.parts ?? 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + '<br>' +
                    'Consumables: P ' + Number(response.itemsRevenue?.consumables ?? 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })
                );
                $("#itemsRevenueTotal").text('P ' + Number(response.itemsRevenue?.total ?? 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
                $("#totalRevenue").text('P ' + Number(response.totalRevenue ?? 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));

                // ----------------- Completion Chart -----------------
                const completed = Number(response.completedCount ?? 0);
                const cancelled = Number(response.cancelledCount ?? 0);
                const comp = normalizeChartData(["Completed", "Cancelled"], [completed, cancelled]);
                completionChart.data.labels = comp.labels;
                completionChart.data.datasets[0].data = comp.values;
                completionChart.update();

                // ----------------- Services Chart -----------------
                const sLabels = response.services_labels ?? ['No data'];
                const sValues = (response.services_values ?? [1]).map(v => Number(v || 0));
                const sNorm = normalizeChartData(sLabels, sValues);
                servicesChart.data.labels = sNorm.labels;
                servicesChart.data.datasets[0].data = sNorm.values;
                servicesChart.update();

                // ----------------- Products Chart -----------------
                const pLabels = response.products_labels ?? ['No data'];
                const pValues = (response.products_values ?? [1]).map(v => Number(v || 0));
                const pNorm = normalizeChartData(pLabels, pValues);
                productsChart.data.labels = pNorm.labels;
                productsChart.data.datasets[0].data = pNorm.values;
                productsChart.update();

                // ----------------- Parts Chart -----------------
                const partsLabels = response.parts_labels ?? ['No data'];
                const partsValues = (response.parts_values ?? [1]).map(v => Number(v || 0));
                const partNorm = normalizeChartData(partsLabels, partsValues);
                partsChart.data.labels = partNorm.labels;
                partsChart.data.datasets[0].data = partNorm.values;
                partsChart.update();

                // Show SweetAlert success only if response is valid
                // Show success Swal only if requested (click)
                if (showSuccess) {
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Filters Applied',
                        text: 'Your selected filters have been applied successfully.'
                    });
                }

            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Failed to fetch reports:", textStatus, errorThrown, jqXHR.responseText);
                alert("Failed to fetch reports. See console for details.");
            });
        }

        /* ----------------- UI hooks ----------------- */

        // On page load → no Swal
        $(document).ready(function() {
            applyFilters(false);
        });

        // On click → show Swal
        $("#applyFilters").on("click", function() {
            applyFilters(true);
        });

        // From Date picker
        const FPfromDate = flatpickr("#fromDate", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            allowInput: false,
            disable: [
                function(date) {
                    return date.getDay() === 0 || date.getDay() === 6;
                }
            ]
        });

        // To Date picker
        const FPtoDate = flatpickr("#toDate", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            allowInput: false,
            disable: [
                function(date) {
                    return date.getDay() === 0 || date.getDay() === 6;
                }
            ]
        });

        $("#allTime").on("change", function() {
            const isAll = $(this).is(":checked");
            $("#fromDate, #toDate")
                .prop("disabled", isAll)
                .val(isAll ? "" : $("#fromDate").val());
        }).trigger('change');
    </script>


</body>

</html>