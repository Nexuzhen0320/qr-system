<?php
session_start();
ob_start();

require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
include '../database/db.php';
include '../smtp config/smtp2goconfig.php';

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
                        file_put_contents('../debug_&_error_log/phpmailer_debug.log', "PHPMailer[$level]: $str\n", FILE_APPEND);
                    };

                    $mail->send();
                    $success_message = "A new OTP has been sent to your email!";
                } catch (Exception $e) {
                    // Delete pending record on email failure
                    $delete_sql = "DELETE FROM data WHERE email = ? AND status_Account = 'pending'";
                    $delete_stmt = $connection->prepare($delete_sql);
                    $delete_stmt->bind_param("s", $email);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    $notification = "Failed to send OTP. Please try again later.";
                    error_log("PHPMailer Error: " . $e->getMessage(), 3, '../debug_&_error_log/phpmailer_error.log');
                }
            } else {
                $notification = "Database error. Please try again.";
                error_log("Database Error: " . $stmt->error, 3, '../debug_&_error_log/database_error.log');
            }
            $stmt->close();
        }
    }
}

// Clean output buffer
$rawOutput = ob_get_clean();
if ($rawOutput) {
    file_put_contents('../debug_&_error_log/debug_output.log', $rawOutput, FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <title>Resend OTP</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('image/bg.jpg');
            background-size: cover;
            background-position: center;
        }

        .form-container {
            width: 420px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInContainer 0.5s ease-in-out;
        }

        @keyframes fadeInContainer {
            0% { opacity: 0; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }

        .form-container h5 {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 15px;
        }

        .form-container .subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .success-message,
        .notification-message {
            font-size: 14px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
            display: none;
        }

        .success-message {
            color: green;
            background: rgba(0, 128, 0, 0.1);
            border: 1px solid green;
        }

        .notification-message {
            color: #e63946;
            background: rgba(230, 57, 70, 0.1);
            border: 1px solid #e63946;
        }

        button {
            width: 50%;
            padding: 14px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            position: relative;
        }

        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        button:hover:not(:disabled) {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        button:active:not(:disabled) {
            transform: translateY(0);
        }

        button.loading .button-content {
            visibility: hidden;
        }

        button.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 3px solid #fff;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .verify-link,
        .register-link {
            display: inline-block;
            margin-top: 20px;
            margin-left: 10px;
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .verify-link:hover,
        .register-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .form-container {
                width: 90%;
                padding: 20px;
            }

            .form-container h5 {
                font-size: 24px;
            }

            .form-container .subtitle,
            .success-message,
            .notification-message {
                font-size: 14px;
            }

            button {
                padding: 12px;
                font-size: 14px;
            }

            .verify-link,
            .register-link {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
<div class="form-container">
    <h5>Resend OTP</h5>
    <div class="subtitle">Request a new OTP if you didn't receive it</div>
    <?php if (!empty($success_message)): ?>
        <div class="success-message" id="success-message" style="display: block;"><?php echo $success_message; ?></div>
    <?php elseif (!empty($notification)): ?>
        <div class="notification-message" id="notification-message" style="display: block;"><?php echo $notification; ?></div>
    <?php endif; ?>
    <form action="" method="POST" id="resendForm">
        <input type="hidden" name="resendotp" value="1">
        <button type="submit" id="resend-btn" <?php echo $cooldown_active ? 'disabled' : ''; ?>>
            <span class="button-content">Resend OTP <i class='bx bx-refresh'></i></span>
        </button>
        <a href="../authentication/verify.php" class="verify-link">Back to Verify OTP</a>
        <a href="../authentication/register.php" class="register-link">Back to Register</a>
    </form>
</div>

<script>
    // Declare variables once
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
            }
        }, 1000);
    <?php endif; ?>

    // Handle success message and redirect
    <?php if (!empty($success_message)): ?>
        setTimeout(() => {
            window.location.href = '../authentication/verify.php';
        }, 2000);
    <?php endif; ?>

    // Form submission with loading state
    form?.addEventListener('submit', (e) => {
        if (!resendButton.disabled) {
            resendButton.disabled = true;
            resendButton.classList.add('loading');
        }
    });
</script>
</body>
</html>