<?php
// Start the session
session_start();
include_once 'config/db.php';

insertLog($db_connection, "Logged Out", "LogInOut");
// Clear session data and destroy the session
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/'); // Delete session cookie


// Redirect to the login page
header('Location: login.php');
exit;
?>
