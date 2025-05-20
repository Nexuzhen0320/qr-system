<?php
session_start();

function logError($message) {
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ": $message\n", FILE_APPEND);
}

$success_message = '';
$otp_expired_message = '';
$session_expired = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = $_POST['otp'] ?? '';
    $email = $_SESSION['email'] ?? '';

    if (empty($email) || empty($_SESSION['otp'])) {
        $session_expired = true;
        include "../database/db.php";
        $delete_sql = "DELETE FROM data WHERE email = ? AND status_Account = 'pending'";
        $delete_stmt = $connection->prepare($delete_sql);
        if (!$delete_stmt) {
            logError("Delete prepare error: " . $connection->error);
        } else {
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    } else {
        $otp_time = $_SESSION['otp_time'] ?? 0;
        if ((time() - $otp_time) > 180) { // Updated to 3 minutes
            $otp_expired_message = 'OTP has expired. Please click "Resend OTP" to receive a new code.';
        } elseif ($user_otp == $_SESSION['otp']) {
            include "../database/db.php";
            $sql = "UPDATE data SET status_Account = 'verified', otp = NULL, verify_otp = ? WHERE email = ?";
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                $error_message = 'Database prepare error: ' . $connection->error;
                logError($error_message);
            } else {
                $stmt->bind_param("ss", $user_otp, $email);
                if ($stmt->execute()) {
                    $success_message = 'Registration successful!';
                    session_destroy();
                } else {
                    $error_message = 'Database execution error: ' . $stmt->error;
                    logError($error_message);
                }
                $stmt->close();
            }
        } else {
            $error_message = 'Invalid OTP. Please try again.';
            logError($error_message);
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
    <title>OTP Verification</title>
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

        .input-group {
            position: relative;
            margin-bottom: 10px;
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

        .error-message-otp {
            color: #e63946;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 10px;
            text-align: left;
            min-height: 20px;
        }

        .success-message,
        .expired-message {
            font-size: 14px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out, shake 0.5s ease-in-out;
            display: none;
        }

        .success-message {
            color: green;
            background: rgba(0, 128, 0, 0.1);
            border: 1px solid green;
        }

        .expired-message {
            color: #e63946;
            background: rgba(230, 57, 70, 0.1);
            border: 1px solid #e63946;
        }

        .timer {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
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
            animation: popIn 0.4s ease-in-out;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(230, 57, 70, 0.2);
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
            width: 50%;
            padding: 12px;
            background: linear-gradient(135deg, #e63946, #b32d39);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .modal-content button:hover {
            background: linear-gradient(135deg, #b32d39, #8b232f);
            transform: translateY(-2px);
        }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
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

        .register-link,
        .resendotp-link {
            display: inline-block;
            margin-top: 20px;
            margin-left: 10px;
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .resendotp-link.disabled {
            color: #999;
            cursor: not-allowed;
            pointer-events: none;
        }

        .register-link:hover,
        .resendotp-link:not(.disabled):hover {
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
            .timer {
                font-size: 14px;
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

            .error-message-otp,
            .success-message,
            .expired-message {
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

            .register-link,
            .resendotp-link {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h5>Verify OTP</h5>
        <div class="subtitle">Please check your email or spam for the OTP</div>
        <div class="timer" id="otp-timer"></div>
        <?php if (!empty($success_message)): ?>
            <div class="success-message" id="success-message" style="display: block;"><?php echo $success_message; ?></div>
        <?php elseif (!empty($otp_expired_message)): ?>
            <div class="expired-message" id="expired-message" style="display: block;"><?php echo $otp_expired_message; ?></div>
        <?php endif; ?>
        <form action="" method="POST" id="verifyForm" <?php echo (!empty($success_message)) ? 'style="display: none;"' : ''; ?>>
            <div class="input-group">
                <i class='bx bxs-key icon-left'></i>
                <input type="text" name="otp" id="otp" placeholder="Enter 6-digit OTP" pattern="\d{6}" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6)" autocomplete="off" required>
            </div>
            <div class="error-message-otp" id="otp-error"><?php echo isset($_POST['otp']) && $_POST['otp'] != $_SESSION['otp'] ? 'Invalid OTP. Please try again.' : ''; ?></div>
            <button type="submit" id="submit-btn"><span class="button-content">Verify <i class='bx bx-right-arrow-alt'></i></span></button>
            <a href="../authentication/resendotp.php" class="resendotp-link" id="resend-otp">Resend OTP</a>
            <a href="../registration/register.php" class="register-link">Back to Register</a>
        </form>
    </div>

    <?php if ($session_expired): ?>
        <div class="modal show" id="sessionExpiredModal">
            <div class="modal-content">
                <i class='bx bxs-error-circle'></i>
                <h3>Session Expired</h3>
                <p>Your session has expired. Please register again to receive a new OTP.</p>
                <button onclick="window.location.href='../registration/register.php'">Back to Register</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const form = document.getElementById("verifyForm");
        const otpInput = document.getElementById("otp");
        const otpError = document.getElementById("otp-error");
        const submitButton = document.getElementById("submit-btn");
        const resendLink = document.getElementById("resend-otp");
        const timerDisplay = document.getElementById("otp-timer");
        const expiredMessage = document.getElementById("expired-message");

        // OTP expiration timer
        <?php if (!empty($_SESSION['otp_time']) && empty($success_message) && empty($otp_expired_message)): ?>
            let timeLeft = 180 - (Math.floor(Date.now() / 1000) - <?php echo $_SESSION['otp_time']; ?>);
            if (timeLeft > 0) {
                const timerInterval = setInterval(() => {
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        timerDisplay.textContent = "OTP has expired.";
                        expiredMessage.style.display = "block";
                        expiredMessage.textContent = 'OTP has expired. Please click "Resend OTP" to receive a new code.';
                        otpInput.value = '';
                        otpError.textContent = '';
                        submitButton.disabled = false;
                    } else {
                        timerDisplay.textContent = `OTP expires in ${timeLeft} seconds`;
                        timeLeft--;
                    }
                }, 1000);
            } else {
                timerDisplay.textContent = "OTP has expired.";
                expiredMessage.style.display = "block";
                expiredMessage.textContent = 'OTP has expired. Please click "Resend OTP" to receive a new code.';
                otpInput.value = '';
                otpError.textContent = '';
                submitButton.disabled = false;
            }
        <?php endif; ?>

        // Input validation
        otpInput?.addEventListener("input", () => {
            otpInput.value = otpInput.value.replace(/[^0-9]/g, '').slice(0, 6);
            otpError.textContent = '';
            if (expiredMessage.style.display === "block") {
                expiredMessage.style.display = "none";
            }
        });

        form?.addEventListener("submit", (e) => {
            e.preventDefault();
            otpError.textContent = '';

            if (!/^\d{6}$/.test(otpInput.value)) {
                otpError.textContent = 'Please enter a valid 6-digit OTP.';
                return;
            }

            submitButton.disabled = true;
            submitButton.classList.add('loading');
            form.submit();
        });

        // Resend OTP timer with sessionStorage persistence
        const RESEND_COOLDOWN = 60; // 60 seconds cooldown
        let resendCooldown = parseInt(sessionStorage.getItem('resendCooldown')) || 0;
        let resendTimerStart = parseInt(sessionStorage.getItem('resendTimerStart')) || 0;

        function startResendTimer() {
            resendLink.classList.add('disabled');
            const now = Math.floor(Date.now() / 1000);
            resendTimerStart = now;
            resendCooldown = RESEND_COOLDOWN;
            sessionStorage.setItem('resendTimerStart', resendTimerStart);
            sessionStorage.setItem('resendCooldown', resendCooldown);

            const resendInterval = setInterval(() => {
                const elapsed = Math.floor(Date.now() / 1000) - resendTimerStart;
                resendCooldown = RESEND_COOLDOWN - elapsed;

                if (resendCooldown <= 0) {
                    clearInterval(resendInterval);
                    resendLink.classList.remove('disabled');
                    resendLink.textContent = 'Resend OTP';
                    sessionStorage.removeItem('resendCooldown');
                    sessionStorage.removeItem('resendTimerStart');
                } else {
                    resendLink.textContent = `Resend OTP (${resendCooldown}s)`;
                    sessionStorage.setItem('resendCooldown', resendCooldown);
                }
            }, 1000);
        }

        // Check if the timer should resume on page load
        if (resendTimerStart > 0) {
            const elapsed = Math.floor(Date.now() / 1000) - resendTimerStart;
            resendCooldown = RESEND_COOLDOWN - elapsed;
            if (resendCooldown > 0) {
                startResendTimer();
            } else {
                sessionStorage.removeItem('resendCooldown');
                sessionStorage.removeItem('resendTimerStart');
            }
        }

        resendLink?.addEventListener("click", (e) => {
            if (resendLink.classList.contains('disabled')) {
                e.preventDefault();
            } else {
                startResendTimer();
            }
        });

        // Handle success message
        <?php if (!empty($success_message)): ?>
            document.getElementById("success-message").style.display = "block";
            setTimeout(() => {
                window.location.href = '../index.php';
            }, 2000);
        <?php elseif (!empty($otp_expired_message)): ?>
            document.getElementById("expired-message").style.display = "block";
        <?php endif; ?>

        <?php if (isset($_POST['otp']) && $_POST['otp'] != $_SESSION['otp']): ?>
            otpError.textContent = 'Invalid OTP. Please try again.';
        <?php endif; ?>
    </script>
</body>
</html>