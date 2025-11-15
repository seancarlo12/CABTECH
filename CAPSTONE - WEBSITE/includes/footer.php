<footer class="footer">
    <div class="container">
        <hr>
        <div class="row g-lg-5">
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0 text-center text-md-start">
                <img src="/CABTECH/CAPSTONE - WEBSITE/assets/img/primarylogo.png" alt="" class="img-fluid mb-3" style="height: 60px;">
                <p class="text-white small ">CABTECH Auto Services is
                    your trusted partner
                    in car maintenance. We're
                    dedicated to keeping your vehicle in optimal condition for your safety.</p>
                <div class="social-icons mt-3 d-flex justify-content-center justify-content-md-start">
                    <a href="https://www.facebook.com/profile.php?id=100086424147451" target="_blank" class="me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-instagram"></i></a>
                </div>

            </div>


            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0 text-center text-md-start">
                <h5 class="text-white mb-4 mt-3">Our Services</h5>
                <ul class="list-unstyled">
                    <?php
                    // Fetch first 4 active services
                    $sql = "SELECT service_id, service_name FROM servicestbl WHERE status = 'Active' LIMIT 4";
                    $result = $db_connection->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while ($service = $result->fetch_assoc()) {
                            $name = htmlspecialchars($service['service_name']);
                            $id = $service['service_id'];
                            echo "<li class='mb-2'><a href='/CABTECH/CAPSTONE - WEBSITE/pages/services/service.php?id={$id}'>{$name}</a></li>";
                        }
                        // Add "See More" as the 5th item
                        echo "<li class='mb-2'><a href='/CABTECH/CAPSTONE - WEBSITE/pages/servicesPage.php' class='fw-semibold'>See More...</a></li>";
                    } else {
                        echo "<li class='mb-2 text-muted'>No services available</li>";
                    }
                    ?>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0 text-center text-md-start">
                <h5 class="text-white mb-4 mt-3">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="/CABTECH/CAPSTONE - WEBSITE/index.php">Home</a></li>
                    <li class="mb-2"><a href="/CABTECH/CAPSTONE - WEBSITE/pages/servicesPage.php">Services</a></li>
                    <li class="mb-2"><a href="/CABTECH/CAPSTONE - WEBSITE/pages/location.php">Location</a></li>
                    <li class="mb-2"><a href="#" data-bs-toggle="modal" data-bs-target="#serviceRequestModal">Schedule Now</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0 text-center text-md-start">
                <h5 class="text-white mb-4 mt-3">Get In Touch</h5>
                <p class="text-white small mb-2"><i class="fas fa-map-marker-alt me-2"></i> KM 110, Maharlika High Way, Sumacab Este, Cabanatuan City
</p>
                <p class="text-white small mb-2"><i class="fas fa-phone-alt me-2"></i> +63 997 335 3468</p>
                <p class="text-white small mb-2"><i class="fas fa-envelope me-2"></i> cabtech.system@gmail.com</p>
            </div>
        </div>
        <hr>
        <div class="footer-bottom text-center">
            <p class="text-white small mb-0">© 2022-2025 CABTECH Auto Services. All rights reserved.</p>

        </div>
    </div>
</footer>