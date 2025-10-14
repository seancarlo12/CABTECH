<?php

session_name('CABTECH_WEBSITE');
session_start();
// Check if the file exists before including
if (file_exists('../includes/header.php')) {
    include_once '../includes/header.php';
}

include_once '../includes/headNav.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabTech Auto Services - Services Offered</title>
    <link rel="stylesheet" href="../style/servicePage.css">
</head>

<body>

    <main>
        <!-- what we offer section -->
        <section class="mb-5">
            <div class="container pt-5 pb-3">
                <div class="text-center">
                    <h6 class="text-danger">What We Offer</h6>
                    <h1>CabTech Auto Services</h1>
                    <p class="w-75 mx-auto" style="text-align: justify;">At CabTech Auto Services, we provide a full range of automotive solutions designed to keep your vehicle running at its best.
                        From diagnostics and maintenance to specialized repairs, our expert technicians are committed to delivering fast, reliable, and high-quality service.
                        Whether you’re here for a quick check-up or a major repair, you can count on us to get the job done right.</p>
                </div>
            </div>

            <?php
            // Pagination configuration
            $limit = 21; // only 21 services shown per page
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $offset = ($page - 1) * $limit;

            // Get total number of active services
            $count_sql = "SELECT COUNT(*) AS total FROM servicestbl WHERE status = 'Active'";
            $count_result = $db_connection->query($count_sql);
            $total_services = 0;
            if ($count_result) {
                $row = $count_result->fetch_assoc();
                $total_services = (int)$row['total'];
            }

            $total_pages = $total_services > 0 ? (int)ceil($total_services / $limit) : 1;

            // Fetch paginated services (ordered so pagination is stable)
            $sql = "SELECT service_id, service_name, description FROM servicestbl WHERE status = 'Active' ORDER BY service_name ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            $result = $db_connection->query($sql);
            ?>

            <div class="container">
                <div class="row g-4">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($s = $result->fetch_assoc()): ?>
                            <div class="col-lg-4 col-md-6 col-sm-12 service-col">
                                <div class="service-card p-3 h-100 d-flex flex-column">
                                    <!-- scattered icons (pure CSS positioning) -->
                                    <div class="scattered-icons" aria-hidden="true">
                                        <i class="fa-solid fa-car"></i>
                                        <i class="fa-solid fa-wrench"></i>
                                        <i class="fa-solid fa-car-battery"></i>
                                        <i class="fa-solid fa-oil-can"></i>
                                        <i class="fa-solid fa-gas-pump"></i>
                                        <i class="fa-solid fa-car-side"></i>
                                        <i class="fa-solid fa-tools"></i>
                                        <i class="fa-solid fa-tire"></i>
                                        <i class="fa-solid fa-bolt"></i>
                                        <i class="fa-solid fa-gear"></i>
                                        <i class="fa-solid fa-dashboard"></i>
                                    </div>
                                    <!-- top-right name (absolute) -->
                                    <h4 class="service-name">
                                        <?php
                                        $words = explode(' ', htmlspecialchars($s['service_name']));
                                        foreach ($words as $w) {
                                            echo "<span>$w</span><br>";
                                        }
                                        ?>
                                    </h4>

                                    <!-- (optional) main body content; keep minimal so footer is the focus -->
                                    <div class="flex-grow-1">
                                        <!-- you can add an image or short lead here if you want -->
                                    </div>

                                    <!-- footer: left = small description, right = learn more -->
                                    <div class="service-footer">
                                        <p class="footer-desc"><?= nl2br(htmlspecialchars($s['description'])) ?></p>
                                        <a href="services/service.php?id=<?= (int)$s['service_id'] ?>"
                                            class="learnmorebtn" aria-label="Learn more about <?= htmlspecialchars($s['service_name']) ?>">
                                            Learn More
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-muted">No services available at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination controls -->
                <?php if ($total_pages > 1): ?>
                    <?php
                    // Build base URL for pagination links (keeps current script path)
                    $base_url = htmlspecialchars($_SERVER['PHP_SELF']);
                    // Determine a sensible range of pages to show
                    $range = 2; // show 2 pages before and after current
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);
                    ?>

                    <nav aria-label="Services pagination">
                        <ul class="pagination justify-content-center mt-4">
                            <!-- First page -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>?page=1" aria-label="First">&laquo; First</a>
                            </li>

                            <!-- Previous -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>?page=<?= max(1, $page - 1) ?>" aria-label="Previous">Previous</a>
                            </li>

                            <?php if ($start > 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>

                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>" aria-current="<?= $p === $page ? 'page' : '' ?>">
                                    <a class="page-link" href="<?= $base_url ?>?page=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>

                            <!-- Next -->
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>?page=<?= min($total_pages, $page + 1) ?>" aria-label="Next">Next</a>
                            </li>

                            <!-- Last page -->
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>?page=<?= $total_pages ?>" aria-label="Last">Last &raquo;</a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Small summary -->
                    <p class="text-center text-muted">Showing page <?= $page ?> of <?= $total_pages ?> — total <?= $total_services ?> active services.</p>
                <?php endif; ?>

            </div>

        </section>
    </main>


    <?php include '../includes/footer.php'; ?>
</body>

</html>