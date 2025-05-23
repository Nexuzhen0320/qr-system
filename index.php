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
        $stmt = $connection->prepare("SELECT status_Account FROM data WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['status_Account'] === 'verified') {
                header("Location: ./dashboard/dashboard.php");
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
    $_SESSION['last_login_email'] = $email; // Store email for forgot password validation
    $password = $_POST['password'];

    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
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
                    session_regenerate_id(true); // Regenerate session ID for security
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['cooldown_start'] = 0;
                    $_SESSION['show_forgot_password'] = false;
                    unset($_SESSION['last_login_email']);
                    $_SESSION['email'] = $email;
                    $_SESSION['status_Account'] = 'logged_in';
                    $_SESSION['verify_otp'] = $row['verify_otp'];

                    if ($row['status_Account'] === 'verified') {
                        header("Location: ./dashboard/dashboard.php");
                        exit;
                    } else {
                        header("Location: ./authentication/verify.php");
                        exit;
                    }
                } else {
                    // Failed attempt
                    $_SESSION['login_attempts']++;
                    $_SESSION['show_forgot_password'] = true;
                    $errors['password'] = 'Invalid Email or Password. Please try again.';
                    if ($_SESSION['show_forgot_password']) {
                        $errors['password'] .= ' <a href="./forgot_password/forgotpas.php" class="text-blue-600 hover:underline">Forgot Password?</a>';
                    }
                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['cooldown_start'] = time();
                        $errors['password'] = 'Too many login attempts. Please wait ' . $cooldown_duration . ' seconds.';
                        if ($_SESSION['show_forgot_password']) {
                            $errors['password'] .= ' <a href="./forgot_password/forgotpas.php" class="text-blue-600 hover:underline">Forgot Password?</a>';
                        }
                    }
                }
            } else {
                // Failed attempt
                $_SESSION['login_attempts']++;
                $_SESSION['show_forgot_password'] = true;
                $errors['email'] = 'No account found with that email.';
                if ($_SESSION['show_forgot_password']) {
                    $errors['email'] .= ' <a href="./registration/register.php" class="text-blue-600 hover:underline">Register Here</a>';
                }
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['cooldown_start'] = time();
                    $errors['password'] = 'Too many login attempts. Please wait ' . $cooldown_duration . ' seconds.';
                    if ($_SESSION['show_forgot_password']) {
                        $errors['password'] .= '<a href="./forgot_password/forgotpas.php" class="text-blue-600 hover:underline">Forgot Password?</a>';
                    }
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
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-image: url('./image/bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
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
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .error-message {
            animation: slideIn 0.5s ease-in-out;
        }

        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        #login-button {
            position: relative; /* Establish positioning context for the spinner */
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1.25rem; /* Slightly smaller for better fit */
            height: 1.25rem;
            border: 2px solid #fff; /* Thinner border for proportionality */
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            z-index: 10; /* Ensure spinner is above button content */
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .loading .button-content {
            opacity: 0; /* Fade out content smoothly */
            transition: opacity 0.2s ease-in-out;
        }

        .button-content {
            opacity: 1;
            transition: opacity 0.2s ease-in-out;
        }

        /* Ensure all links within the form-container are blue */
        .form-container a {
            color: #2563eb !important; /* Tailwind's text-blue-600 equivalent */
            text-decoration: none;
        }

        .form-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container max-w-md w-full bg-white/95 backdrop-blur-lg rounded-2xl shadow-2xl p-8 sm:p-10 transition-all duration-300">
        <h5 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-4">Login</h5>
        <div class="subtitle text-sm sm:text-base text-gray-600 text-center mb-6">Sign in to your account</div>
        <form method="POST" action="" id="login-form" class="space-y-4">
            <div class="input-group relative">
                <i class='bx bxs-user absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl'></i>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    placeholder="Enter your email" 
                    autocomplete="off" 
                    required 
                    value="<?= htmlspecialchars($email ?? '') ?>"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg bg-white/50 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 <?= $errors['email'] ? 'border-red-500' : '' ?>"
                    aria-label="Email address"
                >
                <i class='bx bxs-check-circle absolute top-1/2 right-3 transform -translate-y-1/2 text-green-500 text-lg sm:text-xl hidden' id="email-valid"></i>
            </div>
            <div class="error-message text-red-600 text-xs sm:text-sm ml-2" id="email-error"><?= $errors['email'] ?></div>

            <div class="input-group relative">
                <i class='bx bxs-lock-alt absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl'></i>
                <i class='bx bx-show absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl cursor-pointer toggle-password'></i>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    placeholder="Enter your password" 
                    autocomplete="off" 
                    required 
                    class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg bg-white/50 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 <?= $errors['password'] ? 'border-red-500' : '' ?>"
                    aria-label="Password"
                >
                <i class='bx bxs-check-circle absolute top-1/2 right-9 transform -translate-y-1/2 text-green-500 text-lg sm:text-xl hidden' id="password-valid"></i>
            </div>
            <div class="error-message text-red-600 text-xs sm:text-sm ml-2" id="password-error"><?= $errors['password'] ?></div>

            <button 
                type="submit" 
                name="login" 
                id="login-button" 
                <?= $is_cooldown ? 'disabled' : '' ?>
                class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-800 text-white font-medium rounded-lg text-sm sm:text-base hover:from-blue-700 hover:to-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
                <span class="button-content flex items-center gap-2">
                    Login <i class='bx bx-right-arrow-alt'></i>
                </span>
            </button>
            <a href="./registration/register.php" class="login-link text-blue-600 hover:underline transition-colors duration-200 text-sm sm:text-base block text-center mt-4">Don't have an account? Register</a>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('.toggle-password');
        const passwordInput = document.querySelector('#password');
        const loginButton = document.querySelector('#login-button');
        const loginForm = document.querySelector('#login-form');
        const emailInput = document.querySelector('#email');
        const emailError = document.querySelector('#email-error');
        const passwordError = document.querySelector('#password-error');
        const emailValid = document.querySelector('#email-valid');
        const passwordValid = document.querySelector('#password-valid');

        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function validateForm() {
            let valid = true;
            emailError.textContent = '';
            passwordError.textContent = '';
            emailValid.classList.add('hidden');
            passwordValid.classList.add('hidden');
            emailInput.classList.remove('border-green-500', 'border-red-500');
            passwordInput.classList.remove('border-green-500', 'border-red-500');

            if (emailInput.value && !validateEmail(emailInput.value)) {
                emailError.textContent = 'Please enter a valid email address.';
                emailInput.classList.add('border-red-500');
                valid = false;
            } else if (emailInput.value) {
                emailValid.classList.remove('hidden');
                emailInput.classList.add('border-green-500');
            }

            if (passwordInput.value.length < 1 && passwordInput.value !== '') {
                passwordError.textContent = 'Password is required.';
                passwordInput.classList.add('border-red-500');
                valid = false;
            } else if (passwordInput.value) {
                passwordValid.classList.remove('hidden');
                passwordInput.classList.add('border-green-500');
            }

            loginButton.disabled = !valid;
            loginButton.classList.toggle('disabled:bg-gray-400', !valid);
            loginButton.classList.toggle('disabled:cursor-not-allowed', !valid);
        }

        emailInput.addEventListener('input', validateForm);
        passwordInput.addEventListener('input', validateForm);

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('bx-show');
            togglePassword.classList.toggle('bx-hide');
        });

        loginForm.addEventListener('submit', (e) => {
            validateForm(); // Validate on submit
            if (!loginButton.disabled) {
                loginButton.classList.add('loading');
                loginButton.querySelector('.button-content').style.opacity = '0';
                setTimeout(() => {
                    loginButton.classList.remove('loading');
                    loginButton.querySelector('.button-content').style.opacity = '1';
                }, 2000); // Simulate loading for 2 seconds
            } else {
                e.preventDefault(); // Prevent form submission if invalid
            }
        });

        <?php if ($is_cooldown): ?>
        let remainingTime = <?= $remaining_time ?>;
        loginButton.disabled = true;
        loginButton.classList.add('disabled:bg-gray-400', 'disabled:cursor-not-allowed');

        function updateCooldown() {
            if (remainingTime <= 0) {
                loginButton.disabled = false;
                loginButton.classList.remove('disabled:bg-gray-400', 'disabled:cursor-not-allowed');
                passwordError.textContent = '';
                return;
            }

            let errorText = `Too many login attempts. Please wait ${remainingTime} seconds.`;
            <?php if ($_SESSION['show_forgot_password']): ?>
            errorText += ' <a href="./forgot_password/forgotpas.php" class="text-blue-600 hover:underline">Forgot Password?</a>';
            <?php endif; ?>
            passwordError.innerHTML = errorText;
            remainingTime--;
            setTimeout(updateCooldown, 1000);
        }

        updateCooldown();
        <?php endif; ?>
    </script>
</body>
</html>