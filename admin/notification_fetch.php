<?php
session_start();
header('Content-Type: application/json');
include '../database/db.php';

$query = $connection->prepare("SELECT client_name, service, DATE_FORMAT(created_at, '%b %d, %Y %H:%i') AS created_at FROM appointments WHERE status = 'Pending' ORDER BY created_at DESC");
$query->execute();
$result = $query->get_result();

$newAppointments = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($newAppointments);
