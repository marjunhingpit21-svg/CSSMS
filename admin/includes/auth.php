<?php
session_start();

// AUTO-LOGIN FOR DEVELOPMENT (remove or secure this later!)
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_name'] = 'Admin';
    $_SESSION['admin_email'] = 'admin@trendywear.com';
    // Optional: add $_SESSION['role'] = 'superadmin'; etc.
}

// If you ever want to log out, just add this somewhere:
// unset($_SESSION['admin_logged_in']); session_destroy();
?>