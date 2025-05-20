<?php
session_start();
ob_start();
include '../smtp_configuration/smtp2goconfig.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
include '../database/db.php';

$response = ['success' => false, 'message' => 'Something went wrong'];


// === Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// =========================== Get and Validate Inputs ==========================  //
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$otp = $_POST['otp'] ?? '';
$subject = $_POST['subject'] ?? 'OTP Verification';
$ip_address = $_SERVER['REMOTE_ADDR'];

if (empty($email) || empty($password) || empty($otp)) {
    $response['message'] = 'Please fill all required fields.';
    echo json_encode($response);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address.';
    echo json_encode($response);
    exit;
}

// ================================= Check if Email Already Exists ================================ //
$check_sql = "SELECT COUNT(*) FROM data WHERE email = ?";
$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_stmt->bind_result($count);
$check_stmt->fetch();
$check_stmt->close();

if ($count > 0) {
    $response['message'] = 'Email already registered.';
    echo json_encode($response);
    exit;
}

// ================================= Hash Password and Store Session Data ==============================//
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$_SESSION['email'] = $email;
$_SESSION['otp'] = $otp;
$_SESSION['password'] = $hashed_password;
$_SESSION['otp_time'] = time();

$otp_send_time = date('Y-m-d H:i:s');
$status = 'pending';
$insert_sql = "INSERT INTO data (email, password, otp, otp_send_time, ip, status_Account) 
               VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $connection->prepare($insert_sql);
if (!$stmt) {
    $response['message'] = 'Database prepare error: ' . $connection->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ssssss", $email, $hashed_password, $otp, $otp_send_time, $ip_address, $status);
if (!$stmt->execute()) {
    $response['message'] = 'Database execution error: ' . $stmt->error;
    $stmt->close();
    echo json_encode($response);
    exit;
}
$stmt->close();

// ====================== Send Email =============================================== //
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
    $mail->Subject = $subject;
    $mail->Body = "<h2>Your OTP Code is: <strong>$otp</strong></h2><p>This code expires in 3 minutes.</p><p>Please do not share this code with anyone.</p><p>If you did not request this code, please ignore this email.</p><h2>Residences System</h2>";

    $mail->SMTPDebug = 0;
    $mail->Debugoutput = function($str, $level) {
        file_put_contents('phpmailer_debug.log', "PHPMailer[$level]: $str\n", FILE_APPEND);
    };

    $mail->send();
    $response = ['success' => true, 'message' => 'OTP has been Sent to your Email.'];

} catch (Exception $e) {

    $delete_sql = "DELETE FROM data WHERE email = ? AND status = 'pending'";
    $delete_stmt = $connection->prepare($delete_sql);
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();

    $response['message'] = 'Mailer Error: ' . $mail->ErrorInfo;
}

// === Debug Output ===
$rawOutput = ob_get_clean();
if ($rawOutput) {
    file_put_contents('debug_output.log', $rawOutput);
}

echo json_encode($response);
?>
