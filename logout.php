<?php
// Start the session
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>