<?php
// session_name('CABTECH_WEBSITE');
// session_start();
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) {
    include_once($path);
    // echo 'db working';
}


// Strong cache prevention headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>


<!DOCTYPE html>
<html lang="en" dir="ltr">

</html>

<head>
    <meta charset="UTF-8">
    <!-- <title> CabTech Auto Services - Website</title> -->

    <link rel="stylesheet" href="/CABTECH/CAPSTONE - WEBSITE/style/global.css">
    <link rel="stylesheet" href="/CABTECH/CAPSTONE - WEBSITE/style/bootstrap.css">
    <link rel="stylesheet" href="/CABTECH/CAPSTONE - WEBSITE/style/fontawesome.css">
    <link rel="stylesheet" href="/CABTECH/CAPSTONE - WEBSITE/style/flatpickr.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="/CABTECH/CAPSTONE - WEBSITE/JS/bootstrap.js"></script>
    <script src="/CABTECH/CAPSTONE - WEBSITE/JS/fontawesome.js"></script>
    <script src="/CABTECH/CAPSTONE - WEBSITE/JS/swal.js"></script>
    <script src="/CABTECH/CAPSTONE - WEBSITE/JS/jquery.js"></script>
    <script src="/CABTECH/CAPSTONE - WEBSITE/JS/flatpickr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        
        function Logout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will be logged out!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to logout.php if confirmed
                    window.location.href = '/CABTECH/CAPSTONE - WEBSITE/logout.php';
                }
            });
        }
    </script>
</head>