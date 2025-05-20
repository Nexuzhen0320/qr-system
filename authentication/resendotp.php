<?php
session_start();
ob_start();
require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
include '../database/db.php';
include '../smtp_configuration/smtp2goconfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$success_message = '';
$notification = '';
$cooldown_active = false;
$remaining_time = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resendotp'])) {
    $email = $_SESSION['email'] ?? '';

    // Validate session
    if (empty($email)) {
        $notification = 'Session expired. Please register again.';
        header('Refresh: 3; URL=index.php');
    } else {
        // Check cooldown (120 seconds)
        $otp_time = $_SESSION['otp_time'] ?? 0;
        if ((time() - $otp_time) < 120) {
            $remaining_time = 120 - (time() - $otp_time);
            $notification = "Please wait <span id='countdown'>$remaining_time</span> seconds before requesting a new OTP.";
            $cooldown_active = true;
        } else {
            // Generate new OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_time'] = time();

            // Update OTP in database
            $otp_send_time = date('Y-m-d H:i:s');
            $update_sql = "UPDATE data SET otp = ?, otp_send_time = ? WHERE email = ? AND status_Account = 'pending'";
            $stmt = $connection->prepare($update_sql);
            $stmt->bind_param("sss", $otp, $otp_send_time, $email);

            if ($stmt->execute()) {
                // Send OTP email
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
                    $mail->Subject = "Resend OTP Verification";
                    $mail->Body = "<h2>Your New OTP Code: <strong>$otp</strong></h2><p>This code expires in 1 minute.</p><p>Please do not share this code with anyone.</p><p>If you did not request this code, please ignore this email.</p><h2>Residences System</h2>";

                    $mail->SMTPDebug = 0;
                    $mail->Debugoutput = function($str, $level) {
                        file_put_contents('phpmailer_debug.log', "PHPMailer[$level]: $str\n", FILE_APPEND);
                    };

                    $mail->send();
                    $success_message = "A new OTP has been sent to your email.";
                } catch (Exception $e) {
                    // Delete pending record on email failure
                    $delete_sql = "DELETE FROM data WHERE email = ? AND status_Account = 'pending'";
                    $delete_stmt = $connection->prepare($delete_sql);
                    $delete_stmt->bind_param("s", $email);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    $notification = "Failed to send OTP. Please try again later.";
                    error_log("PHPMailer Error: " . $e->getMessage(), 3, 'phpmailer_error.log');
                }
            } else {
                $notification = "Database error. Please try again.";
                error_log("Database Error: " . $stmt->error, 3, 'database_error.log');
            }
            $stmt->close();
        }
    }
}

// Clean output buffer
$rawOutput = ob_get_clean();
if ($rawOutput) {
    file_put_contents('debug_output.log', $rawOutput, FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Resend OTP</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(180deg, #f4f7fa 0%, #e8ecef 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }

        .form-container {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .success-message,
        .notification-message {
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(-5px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="form-container max-w-md w-full bg-white rounded-lg shadow-md p-6 sm:p-8 md:p-10">
        <h5 class="text-xl sm:text-2xl font-semibold text-gray-800 text-center mb-4">Resend OTP</h5>
        <div class="subtitle text-sm sm:text-base text-gray-600 text-center mb-6">Request a new one-time password if you did not receive it</div>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded-md mb-6 text-center text-sm sm:text-base" id="success-message" style="display: block;">
                <?php echo $success_message; ?>
            </div>
        <?php elseif (!empty($notification)): ?>
            <div class="notification-message bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md mb-6 text-center text-sm sm:text-base" id="notification-message" style="display: block;">
                <?php echo $notification; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="resendForm" class="space-y-6">
            <input type="hidden" name="resendotp" value="1">
            <button 
                type="submit" 
                id="resend-btn" 
                class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-medium rounded-md text-sm sm:text-base hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 transition-colors duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed <?php echo $cooldown_active ? 'disabled' : ''; ?>"
            >
                <span class="button-content flex items-center gap-2">
                    Resend OTP <i class='bx bx-refresh'></i>
                </span>
            </button>
            <div class="flex flex-col sm:flex-row sm:justify-between text-sm sm:text-base space-y-2 sm:space-y-0">
                <a href="verify.php" class="verify-link text-blue-600 hover:underline transition-colors duration-200">Return to OTP Verification</a>
                <a href="register.php" class="register-link text-blue-600 hover:underline transition-colors duration-200">Return to Registration</a>
            </div>
        </form>
    </div>

    <script>
        // Initialize variables
        const form = document.getElementById('resendForm');
        const resendButton = document.getElementById('resend-btn');
        const notificationMessage = document.getElementById('notification-message');
        const successMessage = document.getElementById('success-message');

        // Handle countdown for cooldown
        <?php if ($cooldown_active): ?>
            const countdownElement = document.getElementById('countdown');
            let timeLeft = parseInt(countdownElement.textContent);

            const countdown = setInterval(() => {
                timeLeft--;
                countdownElement.textContent = timeLeft;

                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    notificationMessage.style.display = 'none';
                    resendButton.disabled = false;
                    resendButton.classList.remove('disabled');
                }
            }, 1000);
        <?php endif; ?>

        // Handle success message and redirect
        <?php if (!empty($success_message)): ?>
            setTimeout(() => {
                window.location.href = 'verify.php';
            }, 2000);
        <?php endif; ?>

        // Form submission with loading state
        form?.addEventListener('submit', (e) => {
            if (!resendButton.disabled) {
                resendButton.disabled = true;
                resendButton.classList.add('loading');
                resendButton.querySelector('.button-content').style.visibility = 'hidden';
            }
        });
    </script>
</body>
</html>