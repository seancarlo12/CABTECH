<?php
// Start the session
session_start();

// include DB (adjust path if your handlers folder differs)
$path = dirname(__DIR__, 2) . '/CAPSTONE - SYSTEM/config/db.php';
if (file_exists($path)) include_once($path);

// Clear session data and destroy the session
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/'); // Delete session cookie


// Redirect to the login page
header('Location: index.php');
exit;
?>
