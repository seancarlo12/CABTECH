<?php

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
    <title>CabTech Auto Services - Location</title>
    <link rel="stylesheet" href="../style/location.css">
</head>

<body>

    <main>
        <div class="container mt-3 mt-md-5" id="containerLocation">
            <div class="mb-4">
                <h6 class="text-primary">Where we are located</h5>
                <h1 class="fw-bold">Cabtech Auto Services Location</h1>
            </div>

            <!-- picture ng cabtech  -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="position-relative">
                        <img src="../assets/img/gallery1.png"
                            class="img-fluid rounded-3 w-100"
                            style="height: 426px; object-fit: cover;"
                            alt="CAB Tech Main Location">

                            <div class="bottom-gradient"></div>

                        <div class="position-absolute top-0 start-0 w-100 h-100 rounded-3 d-flex flex-column justify-content-center" style="padding-bottom: 3rem;">
                            <div class="text-center text-white mt-auto">
                                <h3 class="mb-3">Visit us at</h3>
                                <p class="mb-3">CABTECH Auto Services, KM 110 - Maharlika High Way <br> Sumacab Este, Cabanatuan City, Nueva Ecija</p>
                                <a href="https://www.google.com/maps/dir//CABTECH+Auto+Services+Cabanatuan+City+Nueva+Ecija/@15.4493757,120.9437979,16z/data=!4m8!4m7!1m0!1m5!1m1!1s0x339728810dea1b91:0xb76b5ebf033aede!2m2!1d120.9457505!2d15.4500996?entry=ttu&g_ep=EgoyMDI1MTAwMS4wIKXMDSoASAFQAw%3D%3D " class="btn btn-primary button-format" target="_blank" rel="noopener noreferrer">Get Directions</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <img src="../assets/img/gallery2.png"
                                class="img-fluid rounded-3 w-100"
                                style="height: 200px; object-fit: cover;"
                                alt="CAB Tech Shop">
                        </div>
                        <div class="col-12">
                            <img src="../assets/img/mechanic.jpg"
                                class="img-fluid rounded-3 w-100"
                                style="height: 200px; object-fit: cover;"
                                alt="CAB Tech Service">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Get Direction Section -->
            <div class="mt-4 mb-5">
                <h2 class="h2 fw-bold text-dark mb-4">Get Direction</h2>

                <!-- Google Maps Embed (CABTECH Auto Services) -->
                <div class="rounded-3 overflow-hidden border box-shadow-div" style="height: 400px;">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3845.6375882426923!2d120.94317557512298!3d15.450099585143224!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x339728810dea1b91%3A0xb76b5ebf033aede!2sCABTECH%20Auto%20Services!5e0!3m2!1sen!2sph!4v1752398408837!5m2!1sen!2sph"
                        width="100%"
                        height="100%"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>

        </div>
    </main>
</body>

</html>