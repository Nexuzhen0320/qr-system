<?php
session_start();
ob_start();

include '../smtp_configuration/smtp2goconfig.php';
require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
include '../database/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Something went wrong'];

// === Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// === Get and Validate Inputs ===
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$appointment_id = $_POST['appointment_id'] ?? '';
$status = $_POST['status'] ?? ''; // 'Approved' or 'Rejected'

if (empty($email) || empty($appointment_id) || empty($status)) {
    $response['message'] = 'Missing required data.';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format.';
    echo json_encode($response);
    exit;
}

// Validate status value to be either 'Approved' or 'Rejected'
if (!in_array($status, ['Approved', 'Rejected'])) {
    $response['message'] = 'Invalid status value.';
    echo json_encode($response);
    exit;
}

// === Update Appointment Status ===
$update_sql = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
$update_stmt = $connection->prepare($update_sql);
if (!$update_stmt) {
    $response['message'] = 'Database prepare error: ' . $connection->error;
    echo json_encode($response);
    exit;
}
$update_stmt->bind_param("si", $status, $appointment_id);
if (!$update_stmt->execute()) {
    $response['message'] = 'Failed to update appointment: ' . $update_stmt->error;
    $update_stmt->close();
    echo json_encode($response);
    exit;
}
$update_stmt->close();

// === Get User Info for Email ===
$getUserSql = "SELECT first_name, last_name, appointment_date FROM appointments WHERE appointment_id = ?";
$getUserStmt = $connection->prepare($getUserSql);
$getUserStmt->bind_param("i", $appointment_id);
$getUserStmt->execute();
$getUserStmt->bind_result($first_name, $last_name, $appointment_date);
$getUserStmt->fetch();
$getUserStmt->close();

$reference_id = '';
$emailBody = '';
$fullName = "$first_name $last_name";

if ($status === 'Approved') {
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $datePart = date("Ymd", strtotime($appointment_date));
    $reference_id = $initials . '-' . $datePart;

    // Save reference_id to DB
    $refUpdate = $connection->prepare("UPDATE appointments SET reference_id = ? WHERE appointment_id = ?");
    $refUpdate->bind_param("si", $reference_id, $appointment_id);
    $refUpdate->execute();
    $refUpdate->close();

    // Email content for Approved
    $emailBody = "
        <p>Dear $fullName,</p>
        <p>Your appointment has been <strong>approved</strong>.</p>
        <p>Your reference number is: <strong>$reference_id</strong></p>
        <p>Please check your schedule for full details.</p>
        <br>
        <p>Thank you,<br>Appointment Team</p>
    ";
} else {
    // Email content for Rejected
    $emailBody = "
        <p>Dear $fullName,</p>
        <p>We regret to inform you that your appointment has been <strong>rejected</strong>.</p>
        <p>You may log in and create a new appointment if needed.</p>
        <br>
        <p>Thank you,<br>Appointment Team</p>
    ";
}

// === Send Email Notification ===
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = "Appointment " . $status;
    $mail->Body = $emailBody;

    $mail->SMTPDebug = 0;
    $mail->Debugoutput = function ($str, $level) {
        file_put_contents('appointment_mailer_debug.log', "PHPMailer[$level]: $str\n", FILE_APPEND);
    };

    $mail->send();

    $successMsg = "Appointment $status. Email sent to $email.";
    if (!empty($reference_id)) {
        $successMsg .= " Reference number: $reference_id.";
    }

    $response = [
        'success' => true,
        'message' => $successMsg
    ];
} catch (Exception $e) {
    $response['message'] = 'Mailer Error: ' . $mail->ErrorInfo;
}

// === Optional Debug Log ===
$rawOutput = ob_get_clean();
if ($rawOutput) {
    file_put_contents('debug_output.log', $rawOutput);
}

echo json_encode($response);
?>