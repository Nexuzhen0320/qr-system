<?php
include './database/db.php';
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to dashboard if already logged in
if (isset($_SESSION['status_Account']) && $_SESSION['status_Account'] === 'logged_in' && isset($_SESSION['email'])) {
    try {
        $email = $_SESSION['email'];
        $stmt = $connection->prepare("SELECT status_Account, user_status FROM data WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['status_Account'] === 'verified') {
                if ($row['user_status'] == 1) {
                    header("Location: ./admin/admin_dashboard.php");
                } else {
                    header("Location: ./dashboard/dashboard.php");
                }
                exit;
            } else {
                header("Location: ./authentication/verify.php");
                exit;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Log error if needed, but proceed to clear session
    }
    // If user not found or error, clear session
    $_SESSION = [];
    session_regenerate_id(true);
}

// Clear session if not logged in to prevent stale data
if (!isset($_SESSION['status_Account']) || $_SESSION['status_Account'] !== 'logged_in') {
    $_SESSION = [];
    session_regenerate_id(true);
}

// Initialize login attempts, cooldown, and forgot password flag if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['cooldown_start'])) {
    $_SESSION['cooldown_start'] = 0;
}
if (!isset($_SESSION['show_forgot_password'])) {
    $_SESSION['show_forgot_password'] = false;
}

$errors = [
    'email' => '',
    'password' => ''
];

// Check if cooldown is active (2 minutes = 120 seconds)
$cooldown_duration = 120;
$is_cooldown = false;
$remaining_time = 0;

if ($_SESSION['login_attempts'] >= 5 && $_SESSION['cooldown_start'] > 0) {
    $elapsed_time = time() - $_SESSION['cooldown_start'];
    if ($elapsed_time < $cooldown_duration) {
        $is_cooldown = true;
        $remaining_time = $cooldown_duration - $elapsed_time;
        $errors['password'] = 'Too many login attempts. Please wait ' . $remaining_time . ' seconds.';
    } else {
        // Reset attempts, cooldown, and forgot password flag after cooldown period
        $_SESSION['login_attempts'] = 0;
        $_SESSION['cooldown_start'] = 0;
        $_SESSION['show_forgot_password'] = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && !$is_cooldown) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $_SESSION['last_login_email'] = $email;
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors['email'] = empty($email) ? 'Email is required.' : '';
        $errors['password'] = empty($password) ? 'Password is required.' : '';
    } else {
        try {
            $stmt = $connection->prepare("SELECT * FROM data WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $connection->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    // Successful login
                    session_regenerate_id(true);
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['cooldown_start'] = 0;
                    $_SESSION['show_forgot_password'] = false;
                    unset($_SESSION['last_login_email']);
                    $_SESSION['email'] = $email;
                    $_SESSION['status_Account'] = 'logged_in';
                    $_SESSION['verify_otp'] = $row['verify_otp'];

                    if ($row['status_Account'] === 'verified') {
                        if ($row['user_status'] == 1) {
                            header("Location: ./admin/admin_dashboard.php");
                        } else {
                            header("Location: ./dashboard/dashboard.php");
                        }
                        exit;
                    } else {
                        header("Location: ./authentication/verify.php");
                        exit;
                    }
                } else {
                    // Failed password
                    $_SESSION['login_attempts']++;
                    $_SESSION['show_forgot_password'] = true;
                    $errors['password'] = 'Invalid Email or Password. Please try again.';
                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['cooldown_start'] = time();
                        $errors['password'] = 'Too many login attempts. Please wait ' . $cooldown_duration . ' seconds.';
                    }
                    if ($_SESSION['show_forgot_password']) {
                        $errors['password'] .= ' <a href="./forgot_password/forgotpas.php">Forgot Password?</a>';
                    }
                }
            } else {
                // Email not found
                $_SESSION['login_attempts']++;
                $_SESSION['show_forgot_password'] = true;
                $errors['email'] = 'No account found with that email.';
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['cooldown_start'] = time();
                    $errors['password'] = 'Too many login attempts. Please wait ' . $cooldown_duration . ' seconds.';
                }
                if ($_SESSION['show_forgot_password']) {
                    $errors['email'] .= ' <a href="./registration/register.php">Register Here</a>';
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors['password'] = 'An error occurred: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$connection->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <title>Login</title>
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

        .input-group .icon-right {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            color: #666;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .input-group .icon-right:hover {
            color: #007bff;
        }

        .error-message-email {
            color: #e63946;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 10px;
            text-align: left;
            min-height: 20px;
        }
        .error-message-password {
            color: #e63946;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 10px;
            text-align: left;
            min-height: 20px;
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

        .register-link {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

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

            .input-group input {
                padding: 10px 35px;
                font-size: 14px;
            }

            .input-group .icon-left,
            .input-group .icon-right {
                font-size: 18px;
            }

            button {
                padding: 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h5>Login</h5>
        <form method="POST" action="" id="login-form">
            <div class="input-group">
                <i class='bx bxs-user icon-left'></i>
                <input type="email" name="email" id="email" placeholder="Email" value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>
            <div class="error-message-email"><?= $errors['email'] ?></div>
            <div class="input-group">
                <i class='bx bxs-lock-alt icon-left'></i>
                <i class='bx bx-show icon-right toggle-password'></i>
                <input type="password" name="password" id="password" placeholder="Password" required>
            </div>
            <div class="error-message-password" id="password-error"><?= $errors['password'] ?></div>
            <button type="submit" name="login" id="login-button" <?= $is_cooldown ? 'disabled' : '' ?>>Login</button>
            <a href="./registration/register.php" class="register-link">Don't have an account? Register</a>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('.toggle-password');
        const passwordInput = document.querySelector('#password');
        const loginButton = document.querySelector('#login-button');
        const errorMessage = document.querySelector('#password-error');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('bx-show');
            togglePassword.classList.toggle('bx-hide');
        });

        <?php if ($is_cooldown): ?>
        let remainingTime = <?= $remaining_time ?>;
        loginButton.disabled = true;

        function updateCooldown() {
            if (remainingTime <= 0) {
                loginButton.disabled = false;
                errorMessage.textContent = '';
                return;
            }

            let errorText = `Too many login attempts. Please wait ${remainingTime} seconds.`;
            <?php if ($_SESSION['show_forgot_password']): ?>
            errorText += ' <a href="./forgot_password/forgotpas.php">Forgot Password?</a>';
            <?php endif; ?>
            errorMessage.innerHTML = errorText;
            remainingTime--;
            setTimeout(updateCooldown, 1000);
        }

        updateCooldown();
        <?php endif; ?>
    </script>
</body>
</html>