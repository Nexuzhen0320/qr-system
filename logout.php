<?php
// Start the session
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Log for debugging
error_log("Executing logout.php at " . date('Y-m-d H:i:s'));

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Log redirect attempt
error_log("Redirecting to http://oss.app-ictd.com/index.php");

// Redirect to login page with absolute URL
header("Location: http://oss.app-ictd.com/index.php", true, 302);
exit();
?>