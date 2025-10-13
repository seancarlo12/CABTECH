<?php
session_start();
if (file_exists('config/db.php')) {
    include_once('config/db.php');
}

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (isset($_SESSION['User_id'])) {
    // Already logged in → show only Swal, no login form
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Confirm Logout</title>
        <link rel="stylesheet" href="style/modSwal.css">
        <link rel="stylesheet" href="style/bx-icon.css">
        <script src="JS/swal.js"></script>
        <style>
            *{
                font-family: 'Poppins', sans-serif;
            }
        </style>
        <script>
            
        // If the page is loaded from the browser cache (Back button), force reload
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
        </script>
    </head>

    <body>
        <script>
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will be logged out!',
                iconHtml: '<i class="bx bx-help-circle\"></i>',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out!',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('logout.php')
                        .then(() => {
                            sessionStorage.removeItem('currentPage');
                            Swal.fire({
                                title: 'Logged Out',
                                text: 'You have been logged out successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Reload login.php → now shows the form
                                window.location.href = 'login.php';
                            });
                        });
                } else {
                    window.location.href = 'index.php';
                }
            });
        </script>
    </body>

    </html>
<?php
    exit(); // stop further execution, don't render the login form
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>

    <link rel="stylesheet" href="style/login.css">
    <link rel="stylesheet" href="style/bootstrap.css">
    <link rel="stylesheet" href="style/pages.css">
    <link rel="stylesheet" href="style/modSwal.css">

    <script src="JS/swal.js"></script>
    <script>
        // If the page is loaded from the browser cache (Back button), force reload
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</head>

<body>
    <div>
        <div class="alert alert-warning" role="alert">
            <b>username:</b> superadmin <br>
            <b>password:</b> admin123
        </div>
        <div class="wrapper">
            <form action="handlers/login-handler.php" method="POST">
                <h1 id="welcome">Welcome to <span>CAB</span>TECH</h1>

                <div class="input-box">
                    <input type="text" placeholder="Enter Username" name="Username" required
                        value="<?= isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '' ?>">
                </div>

                <div class="input-box">
                    <input type="password" placeholder="Enter Password" name="Password" required>
                </div>

                <button type="submit" class="button" name="submit">Login</button>
            </form>
        </div>
    </div>
</body>

</html>