<?php
session_name('CABTECH_SYSTEM');
session_start();
if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}

if (isset($_POST['submit'])) {
    // allow either username or email in the same input
    $identifier = trim($_POST['Username']); // username or email
    $password = trim($_POST['Password']);

    // Prepare statement to check username OR email
    $stmt = mysqli_prepare($db_connection, "
    SELECT a.account_id, a.username, a.password, u.user_id, u.role, u.status, u.email
    FROM accountstbl a
    LEFT JOIN userstbl u ON u.account_id = a.account_id
    WHERE a.username = ? OR u.email = ?
    LIMIT 1
");

    if (!$stmt) {
        // fallback error (you can customize this)
        showError('Server Error', 'Unable to prepare statement. Please try again later.', htmlspecialchars($identifier));
        exit;
    }

    // bind the same identifier for both params (username or email)
    mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    // close statement
    mysqli_stmt_close($stmt);

    if (!$row || is_null($row['user_id']) || !isset($row['password']) || !password_verify($password, $row['password'])) {
        showError('Login Failed', 'Invalid username or password.', $identifier);
    } elseif ($row['status'] !== 'Active') {
        showError('Access Denied', 'Your profile/account is deactivated. Please contact an administrator.', $identifier);
    } else {
        $_SESSION['Account_id'] = $row['account_id'];
        $_SESSION['User_id'] = $row['user_id'];
        $_SESSION['Username'] = $row['username'];
        $_SESSION['User_role'] = $row['role'];

        // Determine the dashboard based on user role and save it in session
        switch ($_SESSION["User_role"]) {
            case "Admin":
                $_SESSION['dashboardPage'] = "pages/dashboardAdmin.php";
                break;
            case "Super Admin":
                $_SESSION['dashboardPage'] = "pages/dashboardSuperAdmin.php";
                break;
            case "Mechanic":
                $_SESSION['dashboardPage'] = "pages/dashboardMechanic.php";
                break;
            default:
                $_SESSION['dashboardPage'] = "pages/denyAccess.html"; // fallback
        }

        // Log a login
        insertLog($db_connection, "Logged In", "LogInOut");

        header("Location: ../index.php");
        exit;
    }
}

function showError($title, $message, $username)
{
    // escape username so it's safe on the URL
    $safeUser = urlencode($username);
    echo "<link rel='stylesheet' href='../style/pages.css'>";
    echo "<script src='../JS/swal.js'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: " . json_encode($title) . ",
                text: " . json_encode($message) . ",
                iconHtml: '<i class=\"bx bx-x-circle\"></i>',
                confirmButtonText: 'Try Again'
            }).then(() => {
                window.location.href = '../login.php?username=' + " . json_encode($safeUser) . ";
            });
        });
    </script>";
}
