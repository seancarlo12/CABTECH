<?php

// Check if the file exists before including
if (file_exists('includes/headNav.php')) {
    include_once 'includes/headNav.php';
    // echo "File included successfully.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabTech Auto Services - Home</title>
</head>

<body>
    <main>
        <!-- Hero Section -->
        <section class="hero-section bg-hero-image d-flex align-items-center">
            <div class="container">
                <div class="row">
                    <div class="col mx-auto text-center text-white" style="max-width:650px; margin:0 auto;">
                        <h1 class="display-3 fw-bold mb-3">Reliable Car Service You Can Trust</h1>
                        <p class="lead mb-4">From booking to repair, experience seamless car maintenance with expert service and updates.</p>
                    </div>
                    <div class="d-flex justify-content-center flex-wrap gap-3">
                    <!-- <button class="schedulebtn" href="#" data-bs-toggle="modal" data-bs-target="#serviceRequestModal">Schedule Service</button> -->
                        <button class="btn btn-primary button-format px-4 py-2" data-bs-toggle="modal" data-bs-target="#serviceRequestModal">Schedule Now!</button>
                        <a href="tel:+639973353468" class="btn btn-outline-light px-4 py-2">
                            <i class="fas fa-phone-alt me-2"></i>+63 997 335 3468
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Working Process Section -->
        <section>
            <div class="container working-process-container">
                <div class="process-box">
                    <div class="working-process mb-4">
                        <h6 class="m-2">Working Process</h6>
                        <h2 class="mb-0">Our Online Service Process</h2>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="process-item">
                                <div class="process-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h5>Online Appointment</h5>
                                <p class="text-muted small">Schedule a service anytime, anywhere.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="process-item">
                                <div class="process-icon">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h5>Fast Repairs</h5>
                                <p class="text-muted small">Quick diagnostics and expert fixes.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="process-item">
                                <div class="process-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <h5>Service Tracking</h5>
                                <p class="text-muted small">Monitor your vehicle's service progress.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="process-item">
                                <div class="process-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h5>Instant Confirmation</h5>
                                <p class="text-muted small">Get notified as soon as your booking is confirmed.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Us Section -->
        <section class="py-5 mt-4">
            <div class="container">
                <div class="row align-items-center gx-5">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="assets/img/aboutpic.jpg" alt="Mechanic working on car" class="img-fluid about-image box-shadow-div">
                    </div>
                    <div class="col-md-6">
                        <h6>About Us</h6>
                        <h2 class="section-heading">Reliable & Expert Auto Care Since 2022</h2>
                        <p>CabTech Auto Services has been delivering quality vehicle maintenance and repair for 3+ years in
                            Cabanatuan City. With our team of certified mechanics, we're committed to reliable service. From
                            routine checkups to major repairs.</p>

                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <div class="p-3 h-100 box-shadow-div">
                                    <h5>Expert Auto Care</h5>
                                    <p class="small text-muted">Our certified technicians understand how vehicle systems
                                        work and deliver quality maintenance and repairs.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="p-3 h-100 box-shadow-div">
                                    <h5>Trusted since 2022</h5>
                                    <p class="small text-muted">We've built a reputation for honesty, reliability, and
                                        excellence, serving customers the right way.</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center">

                            <i class="fas fa-user-circle fa-2x text-secondary me-3 ms-3 mt-2"></i>
                            <div>
                                <p class="mb-0 fw-bold">Joey | Michael | Jason | Carlo</p>
                                <p class="text-muted small mb-0">Shop owners</p>
                            </div>
                        </div>
                    </div>
                </div>
        </section>

        <!-- PMS Service Section -->
        <section class="bg-light py-5">
            <div class="container">
                <div class="row align-items-center gx-5">
                    <div class="col-lg-6 pe-lg-5">
                        <h6>PMS Service</h6>
                        <h2 class="section-heading">Our Preventive Maintenance Services</h2>
                        <p>Keep your vehicle running smoothly with expert maintenance tailored to your needs. Choose from:
                        </p>

                        <ul class="list-unstyled mt-4">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                SEMI-SYNTHETIC (Good/Better)
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                FULLY-SYNTHETIC (Good/Better)
                            </li>
                        </ul>

                        <p>Plus, Enjoy a FREE Safety Inspection!</p>
                        <p class="mb-4">
                            <i class="fas fa-phone-alt text-success me-2"></i>
                            +63 997 335 3468
                        </p>

                        <button href="#" class="btn btn-primary button-format">Contact Us</button>
                    </div>
                    <div class="col-lg-6 mt-4 mt-lg-0 pms-image">
                        <img src="assets/img/primitive.jpg" alt="Car maintenance" class="img-fluid rounded pms-image">
                    </div>
                </div>
            </div>
        </section>


        <!-- Services Section -->
        <section class="py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <h6>Our Services</h6>
                    <h2>Discover the Auto Services<br>We Proudly Provide</h2>
                </div>

                <?php

                // Fetch active services from the database
                $sql = "SELECT service_id, service_name, description, status FROM servicestbl WHERE status = 'Active' LIMIT 6";
                $result = $db_connection->query($sql);

                if ($result->num_rows > 0) {
                    echo '<div class="row g-3">';
                    while ($service = $result->fetch_assoc()) {
                ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="service-card">
                                <div class="service-icon">
                                </div>
                                <h5><?= htmlspecialchars($service['service_name']) ?></h5>
                                <p class="small text-muted"><?= htmlspecialchars($service['description']) ?></p>
                                <a href="pages/services/service.php?id=<?= $service['service_id'] ?>" class="btn btn-sm mt-2">Learn More</a>
                            </div>
                        </div>
                <?php
                    }
                    echo '</div>';
                } else {
                    echo '<p class="text-muted">No services available at the moment.</p>';
                }
                ?>

                <div class="text-center mt-4">
                    <button class="btn btn-primary button-format" id="viewServicesBtn">View All Services</button>
                </div>
            </div>
        </section>


        <!-- Why Choose Us -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-5">
                    <h6>Why Choose Us</h6>
                    <h2>Get Your Ride Back On Track With Our<br>Expert Car Repair & Services</h2>
                </div>

                <div class="row g-4">
                    <!-- Reason 1 -->
                    <div class="col-md-4">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h5>Expert Service And Technology</h5>
                            <p class="small text-muted">Our team uses advanced tools to detect issues in your vehicle's
                                engine, transmission, and electronic systems.</p>
                        </div>
                    </div>

                    <!-- Reason 2 -->
                    <div class="col-md-4">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h5>Service Progress Tracking</h5>
                            <p class="small text-muted">Stay updated on your vehicle's repair status with our transparent
                                and hassle-free experience.</p>
                        </div>
                    </div>

                    <!-- Reason 3 -->
                    <div class="col-md-4">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h5>Affordable & Reliable</h5>
                            <p class="small text-muted">We offer affordable services without compromising on excellence.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        $("#viewServicesBtn").on("click", function() {
            window.location.href = "pages/servicesPage.php";
        });
    </script>
</body>

</html>