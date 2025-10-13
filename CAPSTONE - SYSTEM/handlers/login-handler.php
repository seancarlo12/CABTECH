<?php
session_start();
if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}

if (isset($_POST['submit'])) {
    $username = trim($_POST['Username']);
    $password = trim($_POST['Password']);

    $stmt = mysqli_prepare($db_connection, "
        SELECT a.account_id, a.username, a.password, u.user_id, u.role, u.status
        FROM accountstbl a
        LEFT JOIN userstbl u ON u.account_id = a.account_id
        WHERE a.username = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row || is_null($row['user_id']) || !password_verify($password, $row['password'])) {
        showError('Login Failed', 'Invalid username or password.', $username);
    } elseif ($row['status'] !== 'Active') {
        showError('Access Denied', 'Your profile/account is deactivated. Please contact an administrator.', $username);
    } else {
        $_SESSION['Account_id'] = $row['account_id'];
        $_SESSION['User_id'] = $row['user_id'];
        $_SESSION['Username'] = $row['username'];
        $_SESSION['User_role'] = $row['role'];



        // Determine the dashboard based on user role
        $dashboardPage = "pages/denyAcces.html"; // default

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
    echo "<link rel='stylesheet' href='../style/pages.css'>";
    echo "<script src='../JS/swal.js'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '$title',
                text: '$message',
                iconHtml: '<i class=\"bx bx-x-circle\"></i>',
                confirmButtonText: 'Try Again'
            }).then(() => {
                window.location.href = '../login.php?username=" . urlencode($username) . "';
            });
        });
    </script>";
}
