<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../image/icons/logo1.ico">
    <title>Registration</title>
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
            background-image: url('../image/bg.jpg');
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
            border: 3px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            color: #333;
            background: rgba(255, 255, 255, 0.5);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        #password, #cpassword {
            padding-right: 60px;
        }

        .input-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
        }

        .input-group input.valid {
            border-color: green;
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

        .input-group .valid-icon-email,
        .input-group .valid-icon-password {
            position: absolute;
            top: 50%;
            right: 25px;
            transform: translateY(-50%);
            color: green;
            font-size: 20px;
        }

        .error-message {
            color: #e63946;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 10px;
            text-align: left;
            min-height: 20px;
        }

        .password-strength {
            font-size: 12px;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 10px;
            text-align: left;
            min-height: 20px;
        }

        .password-strength.weak { color: #e63946; }
        .password-strength.medium { color: #ffaa00; }
        .password-strength.strong { color: green; }

        .form-message {
            font-size: 14px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
            display: none;
        }

        .form-message.error {
            color: #e63946;
            background: rgba(230, 57, 70, 0.1);
            border: 1px solid #e63946;
            display: block;
        }

        .form-message.success {
            color: green;
            background: rgba(0, 128, 0, 0.1);
            border: 1px solid green;
            display: block;
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
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

        .login-link {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .login-link:hover {
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

            .form-container .subtitle {
                font-size: 14px;
            }

            .input-group input {
                padding: 10px 35px;
                font-size: 14px;
            }

            .input-group .icon-left,
            .input-group .icon-right,
            .input-group .valid-icon-email,
            .input-group .valid-icon-password {
                font-size: 18px;
            }

            button {
                padding: 12px;
                font-size: 14px;
            }

            .error-message,
            .password-strength {
                font-size: 12px;
            }

            .form-message {
                font-size: 12px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<div class="form-container">
    <h5>Create Account</h5>
    <div class="subtitle">Sign up to get started</div>
    <div id="form-message" class="form-message"></div>
    <form action="../authentication/send.php" method="POST" id="registerForm">
        <div class="input-group">
            <i class='bx bxs-user icon-left'></i>
            <input type="email" name="email" id="email" placeholder="Enter your email" autocomplete="off" required>
            <i class='bx bxs-check-circle valid-icon-email' id="email-valid" style="display: none;"></i>
        </div>
        <div class="error-message" id="email-error"></div>

        <div class="input-group">
            <i class='bx bxs-lock-alt icon-left'></i>
            <i class='bx bx-show icon-right toggle-password' data-target="password"></i>
            <input type="password" name="password" id="password" placeholder="Create a password" autocomplete="off" required>
            <i class='bx bxs-check-circle valid-icon-password' id="password-valid" style="display: none;"></i>
        </div>
        <div class="error-message" id="password-error"></div>
        <div class="password-strength" id="password-strength"></div>

        <div class="input-group">
            <i class='bx bxs-lock-alt icon-left'></i>
            <i class='bx bx-show icon-right toggle-password' data-target="cpassword"></i>
            <input type="password" name="cpassword" id="cpassword" placeholder="Confirm your password" autocomplete="off" required>
            <i class='bx bxs-check-circle valid-icon-password' id="cpassword-valid" style="display: none;"></i>
        </div>
        <div class="error-message" id="cpassword-error"></div>

        <input type="hidden" name="otp" id="otp">
        <input type="hidden" name="subject" value="OTP Verification Code">
        <button type="submit" id="submit-btn" disabled><span class="button-content">Register <i class='bx bx-right-arrow-alt'></i></span></button>
        <a href="../index.php" class="login-link">Already have an account? Login</a>
    </form>
</div>

<script>
    function generateOTP() {
        return Math.floor(Math.random() * 900000) + 100000; // Temporary client-side OTP; server will override
    }

    document.getElementById("otp").value = generateOTP();

    const form = document.getElementById("registerForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("cpassword");
    const submitButton = document.getElementById("submit-btn");
    const emailError = document.getElementById("email-error");
    const passwordError = document.getElementById("password-error");
    const cpasswordError = document.getElementById("cpassword-error");
    const formMessage = document.getElementById("form-message");
    const passwordStrength = document.getElementById("password-strength");
    const emailValid = document.getElementById("email-valid");
    const passwordValid = document.getElementById("password-valid");
    const cpasswordValid = document.getElementById("cpassword-valid");

    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function validatePassword(password) {
        const minLength = password.length >= 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

        let strength = 0;
        if (minLength) strength++;
        if (hasUpperCase) strength++;
        if (hasLowerCase) strength++;
        if (hasNumber) strength++;
        if (hasSpecial) strength++;

        return {
            isValid: strength >= 4,
            strength: strength,
            message: strength === 0 ? '' :
                     strength <= 2 ? 'Weak password' :
                     strength === 3 ? 'Medium password' : 'Strong password',
            class: strength <= 2 ? 'weak' : strength === 3 ? 'medium' : 'strong'
        };
    }

    function validateForm() {
        let valid = true;
        emailError.textContent = '';
        passwordError.textContent = '';
        cpasswordError.textContent = '';
        formMessage.textContent = '';
        formMessage.className = 'form-message';
        emailValid.style.display = 'none';
        passwordValid.style.display = 'none';
        cpasswordValid.style.display = 'none';

        if (!validateEmail(emailInput.value)) {
            emailError.textContent = 'Please enter a valid email address.';
            valid = false;
        } else {
            emailValid.style.display = 'block';
            emailInput.classList.add('valid');
        }

        const passwordCheck = validatePassword(passwordInput.value);
        if (!passwordCheck.isValid) {
            passwordError.textContent = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
            valid = false;
        } else {
            passwordValid.style.display = 'block';
            passwordInput.classList.add('valid');
        }
        passwordStrength.textContent = passwordCheck.message;
        passwordStrength.className = `password-strength ${passwordCheck.class}`;

        if (passwordInput.value !== confirmPasswordInput.value) {
            cpasswordError.textContent = 'Passwords do not match.';
            valid = false;
        } else if (passwordInput.value && passwordCheck.isValid) {
            cpasswordValid.style.display = 'block';
            confirmPasswordInput.classList.add('valid');
        }

        submitButton.disabled = !valid;
    }

    emailInput.addEventListener('input', validateForm);
    passwordInput.addEventListener('input', validateForm);
    confirmPasswordInput.addEventListener('input', validateForm);

    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = toggle;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            icon.classList.toggle('bx-show');
            icon.classList.toggle('bx-hide');
        });
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        validateForm();

        if (!submitButton.disabled) {
            try {
                submitButton.disabled = true;
                submitButton.classList.add('loading');

                const formData = new FormData(form);
                const response = await fetch("../authentication/send.php", {
                    method: "POST",
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    formMessage.className = 'form-message success';
                    formMessage.textContent = 'Registration successful! OTP sent to your email.';
                    setTimeout(() => {
                        window.location.href = `../authentication/verify.php?email=${encodeURIComponent(formData.get('email'))}`;
                    }, 2000);
                } else {
                    formMessage.className = 'form-message error';
                    formMessage.textContent = data.message || 'Registration failed. Please try again.';
                    submitButton.disabled = false;
                    submitButton.classList.remove('loading');
                }
            } catch (error) {
                console.error('Fetch error:', {
                    message: error.message,
                    stack: error.stack
                });
                formMessage.className = 'form-message error';
                formMessage.textContent = `Registration error: ${error.message.includes('HTTP error') ? 'Server error. Please try again later.' : 'Network error. Please check your connection.'}`;
                submitButton.disabled = false;
                submitButton.classList.remove('loading');
            }
        }
    });
</script>
</body>
</html>