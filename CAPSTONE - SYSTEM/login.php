<?php
session_name('CABTECH_SYSTEM');
session_start();
if (file_exists('config/db.php')) {
    include_once('config/db.php');
}


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
            * {
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
                                text: 'You have been logged out.',
                                iconHtml: '<i class="bx bx-log-out-circle"></i>',
                                showConfirmButton: false, // hide the OK button
                                timer: 3000, // 3000ms = 3 seconds
                                timerProgressBar: true, // optional progress bar
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
    <div class="container">

        <!-- Left side login form -->
        <div class="form-side">
            <div class="wrapper">
                <form action="handlers/login-handler.php" method="POST">
                    <h1 id="welcome"><img src="assets/img/123.svg" alt=""></h1>

                    <div class="input-box">
                    <label for="username" class="form-label">Username or Email</label>
                        <input id="username" class="form-control" type="text" placeholder="Enter Username or Email" autocomplete="off" name="Username" required
                            value="<?= isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '' ?>">
                    </div>

                    <div class="input-box position-relative">
                    <label for="password" class="form-label">Password</label>
                        <input class="form-control" type="password" placeholder="Enter Password" name="Password" id="password" required>
                        <i class='bx bx-show' id="togglePassword" style="font-size: 25px; position:absolute; right:10px; top:70%; transform:translateY(-50%); cursor:pointer;"></i>
                    </div>

                    <button type="submit" class="button" name="submit">Login</button>
                </form>
            </div>
        </div>
        <!-- Right side image -->
        <div class="image-side">
            <img src="assets/img/loginbg.png" alt="CabTech" />
        </div>
    </div>

    <script>
        const password = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function() {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // toggle the eye / eye-off icon
            this.classList.toggle('bx-show');
            this.classList.toggle('bx-hide');
        });
    </script>
</body>

</html>