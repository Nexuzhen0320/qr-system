<?php
session_start();
ob_start();
include 'smtp2goconfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
include 'db.php';

// Initialize messages
$error_message = '';
$success_message = '';
$session_expired = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['step']) && $_POST['step'] === 'request_otp') {
        // Step 1: Request OTP
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } else {
            // Check if email exists
            $check_sql = "SELECT COUNT(*) FROM data WHERE email = ?";
            $check_stmt = $connection->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($count == 0) {
                $error_message = 'Email not found.';
            } else {
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $_SESSION['forgot_email'] = $email;
                $_SESSION['forgot_otp'] = $otp;
                $_SESSION['forgot_otp_time'] = time();

                // Send OTP via email
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
                    $mail->Subject = 'Password Reset OTP';
                    $mail->Body = "<h2>Your OTP Code is: <strong>$otp</strong></h2><p>This code expires in 3 minutes.</p><p>Please do not share this code with anyone.</p><p>If you did not request this code, please ignore this email.</p><h2>Residences System</h2>";

                    $mail->send();
                    $success_message = 'OTP has been sent to your email.';
                    $_SESSION['forgot_step'] = 'verify_otp';
                } catch (Exception $e) {
                    $error_message = 'Failed to send OTP: ' . $mail->ErrorInfo;
                    unset($_SESSION['forgot_email'], $_SESSION['forgot_otp'], $_SESSION['forgot_otp_time']);
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'verify_otp') {
        // Step 2: Verify OTP
        $user_otp = $_POST['otp'] ?? '';
        $email = $_SESSION['forgot_email'] ?? '';

        if (empty($email) || empty($_SESSION['forgot_otp'])) {
            $session_expired = true;
        } elseif ((time() - ($_SESSION['forgot_otp_time'] ?? 0)) > 180) {
            $error_message = 'OTP has expired. Please request a new one.';
            session_destroy();
        } elseif ($user_otp == $_SESSION['forgot_otp']) {
            $_SESSION['forgot_step'] = 'reset_password';
        } else {
            $error_message = 'Invalid OTP. Please try again.';
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'reset_password') {
        // Step 3: Reset Password
        $email = $_SESSION['forgot_email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email)) {
            $session_expired = true;
        } elseif (empty($password) || empty($confirm_password)) {
            $error_message = 'Please fill all fields.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE data SET password = ? WHERE email = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("ss", $hashed_password, $email);

            if ($stmt->execute()) {
                $success_message = 'Password reset successful!';
                session_destroy();
            } else {
                $error_message = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <title>Forgot Password</title>

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
        }

        .form-container h5 {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 30px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            color: #333;
            background: rgba(255, 255, 255, 0.5);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
        }

        .input-group .icon-left {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: #666;
            font-size: 20px;
        }

        .error-message {
            color: #e63946;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            display: none;
            text-align: left;
        }

        .success-message {
            color: green;
            font-size: 14px;
            margin-bottom: 10px;
            padding: 10px;
            background: rgba(0, 128, 0, 0.1);
            border: 1px solid green;
            border-radius: 8px;
            display: none;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transform: translateY(-50px);
            animation: slideIn 0.3s ease forwards;
        }

        .modal-content i {
            font-size: 48px;
            color: #e63946;
            margin-bottom: 20px;
        }

        .modal-content h3 {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .modal-content p {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .modal-content button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #e63946, #b32d39);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .modal-content button:hover {
            background: linear-gradient(135deg, #b32d39, #8b232f);
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            to { transform: translateY(0); }
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .back-link {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
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

            .input-group input {
                padding: 10px 35px;
                font-size: 14px;
            }

            .input-group .icon-left {
                font-size: 18px;
            }

            button {
                padding: 12px;
                font-size: 14px;
            }

            .success-message {
                font-size: 12px;
                padding: 8px;
            }

            .modal-content {
                padding: 20px;
            }

            .modal-content h3 {
                font-size: 20px;
            }

            .modal-content p {
                font-size: 14px;
            }

            .modal-content i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h5>Forgot Password</h5>
        <?php if (!empty($success_message)): ?>
            <div class="success-message" id="success-message"><?php echo $success_message; ?></div>
        <?php elseif (!empty($error_message)): ?>
            <div class="error-message" id="error-message" style="display: block; text-align: center;"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['forgot_step']) && $_SESSION['forgot_step'] === 'reset_password'): ?>
            <!-- Step 3: Reset Password Form -->
            <form action="" method="POST" id="resetPasswordForm">
                <input type="hidden" name="step" value="reset_password">
                <div class="input-group">
                    <i class='bx bxs-lock icon-left'></i>
                    <input type="password" name="password" id="password" placeholder="New Password" autocomplete="off" required>
                </div>
                <div class="input-group">
                    <i class='bx bxs-lock icon-left'></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" autocomplete="off" required>
                </div>
                <div class="error-message" id="password-error"></div>
                <button type="submit">Reset Password <i class='bx bx-right-arrow-alt'></i></button>
                <a href="index.php" class="back-link">Back to Login</a>
            </form>
        <?php elseif (isset($_SESSION['forgot_step']) && $_SESSION['forgot_step'] === 'verify_otp'): ?>
            <!-- Step 2: Verify OTP Form -->
            <form action="" method="POST" id="verifyOtpForm">
                <input type="hidden" name="step" value="verify_otp">
                <div class="input-group">
                    <i class='bx bxs-key icon-left'></i>
                    <input type="text" name="otp" id="otp" placeholder="Enter OTP" autocomplete="off" required>
                </div>
                <div class="error-message" id="otp-error"></div>
                <button type="submit">Verify OTP <i class='bx bx-right-arrow-alt'></i></button>
                <a href="index.php" class="back-link">Back to Login</a>
            </form>
        <?php else: ?>
            <!-- Step 1: Request OTP Form -->
            <form action="" method="POST" id="requestOtpForm">
                <input type="hidden" name="step" value="request_otp">
                <div class="input-group">
                    <i class='bx bxs-envelope icon-left'></i>
                    <input type="email" name="email" id="email" placeholder="Enter Email" autocomplete="off" required>
                </div>
                <div class="error-message" id="email-error"></div>
                <button type="submit">Send OTP <i class='bx bx-right-arrow-alt'></i></button>
                <a href="index.php" class="back-link">Back to Login</a>
            </form>
        <?php endif; ?>
    </div>

    <!-- Session Expired Modal -->
    <?php if ($session_expired): ?>
        <div class="modal show" id="sessionExpiredModal">
            <div class="modal-content">
                <i class='bx bxs-error-circle'></i>
                <h3>Session Expired</h3>
                <p>Your session has expired. Please try again.</p>
                <button onclick="window.location.href='index.php'">Back to Login</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const requestOtpForm = document.getElementById("requestOtpForm");
        const verifyOtpForm = document.getElementById("verifyOtpForm");
        const resetPasswordForm = document.getElementById("resetPasswordForm");

        if (requestOtpForm) {
            const emailInput = document.getElementById("email");
            const emailError = document.getElementById("email-error");

            requestOtpForm.addEventListener("submit", (e) => {
                emailError.style.display = 'none';
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailRegex.test(emailInput.value)) {
                    emailError.textContent = 'Please enter a valid email address.';
                    emailError.style.display = 'block';
                    e.preventDefault();
                }
            });
        }

        if (verifyOtpForm) {
            const otpInput = document.getElementById("otp");
            const otpError = document.getElementById("otp-error");

            verifyOtpForm.addEventListener("submit", (e) => {
                otpError.style.display = 'none';

                if (!/^\d{6}$/.test(otpInput.value)) {
                    otpError.textContent = 'Please enter a valid 6-digit OTP.';
                    otpError.style.display = 'block';
                    e.preventDefault();
                }
            });
        }

        if (resetPasswordForm) {
            const passwordInput = document.getElementById("password");
            const confirmPasswordInput = document.getElementById("confirm_password");
            const passwordError = document.getElementById("password-error");

            resetPasswordForm.addEventListener("submit", (e) => {
                passwordError.style.display = 'none';

                if (passwordInput.value.length < 8) {
                    passwordError.textContent = 'Password must be at least 8 characters long.';
                    passwordError.style.display = 'block';
                    e.preventDefault();
                } else if (passwordInput.value !== confirmPasswordInput.value) {
                    passwordError.textContent = 'Passwords do not match.';
                    passwordError.style.display = 'block';
                    e.preventDefault();
                }
            });
        }

        // Show error message if it exists after PHP processing
        <?php if (!empty($error_message)): ?>
            document.getElementById("error-message").style.display = 'block';
        <?php endif; ?>

        // Handle success message and redirect
        <?php if (!empty($success_message) && (empty($_SESSION['forgot_step']) || $_SESSION['forgot_step'] === 'reset_password')): ?>
            const successMessage = document.getElementById("success-message");
            successMessage.style.display = 'block';
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        <?php elseif (!empty($success_message)): ?>
            document.getElementById("success-message").style.display = 'block';
        <?php endif; ?>
    </script>
</body>
</html>