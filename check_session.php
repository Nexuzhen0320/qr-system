<?php
session_start();

// Prevent caching
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
$logged_in = isset($_SESSION['status_Account']) && 
             isset($_SESSION['email']) && 
             $_SESSION['status_Account'] === 'logged_in';

// Return JSON response
echo json_encode(['logged_in' => $logged_in]);
exit();
?>