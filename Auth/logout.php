<?php
// Auth/logout.php
session_start();

// Destroy all session data
$_SESSION = array();

// Delete session cookie if exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>