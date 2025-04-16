<?php
// Start the session
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Return JSON response
header('Content-Type: application/json');

// Check if user is authenticated
if (!empty($_SESSION['status_Account'])) {
    echo json_encode(['isAuthenticated' => true]);
} else {
    echo json_encode(['isAuthenticated' => false]);
}
exit();
?>