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
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Registration</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-image: url('../image/bg.jpg');
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

        .form-message,
        .error-message,
        .password-strength {
            animation: slideIn 0.5s ease-in-out;
        }

        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        #submit-btn {
            position: relative; /* Create positioning context for the spinner */
        }

        #submit-btn.loading .button-content {
            display: none; /* Hide button content during loading */
        }

        #submit-btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1.25rem; /* Slightly smaller spinner for better fit */
            height: 1.25rem;
            border: 3px solid #fff;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 10; /* Ensure spinner is above other content */
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="form-container max-w-md w-full bg-white/95 backdrop-blur-lg rounded-2xl shadow-2xl p-8 sm:p-10 transition-all duration-300">
        <h5 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-4">Create Account</h5>
        <div class="subtitle text-sm sm:text-base text-gray-600 text-center mb-6">Sign up to get started</div>
        <div id="form-message" class="form-message hidden text-sm sm:text-base text-center px-4 py-3 rounded-lg mb-6"></div>
        <form action="../authentication/send.php" method="POST" id="registerForm" class="space-y-4">
            <div class="input-group relative">
                <i class='bx bxs-user absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl'></i>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    placeholder="Enter your email" 
                    autocomplete="off" 
                    required 
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg bg-white/50 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                >
                <i class='bx bxs-check-circle absolute top-1/2 right-3 transform -translate-y-1/2 text-green-500 text-lg sm:text-xl hidden' id="email-valid"></i>
            </div>
            <div class="error-message text-red-600 text-xs sm:text-sm ml-2" id="email-error"></div>

            <div class="input-group relative">
                <i class='bx bxs-lock-alt absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl'></i>
                <i class='bx bx-show absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl cursor-pointer toggle-password' data-target="password"></i>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    placeholder="Create a password" 
                    autocomplete="off" 
                    required 
                    class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg bg-white/50 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                >
                <i class='bx bxs-check-circle absolute top-1/2 right-9 transform -translate-y-1/2 text-green-500 text-lg sm:text-xl hidden' id="password-valid"></i>
            </div>
            <div class="error-message text-red-600 text-xs sm:text-sm ml-2" id="password-error"></div>
            <div class="password-strength text-xs sm:text-sm ml-2" id="password-strength"></div>

            <div class="input-group relative">
                <i class='bx bxs-lock-alt absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl'></i>
                <i class='bx bx-show absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500 text-lg sm:text-xl cursor-pointer toggle-password' data-target="cpassword"></i>
                <input 
                    type="password" 
                    name="cpassword" 
                    id="cpassword" 
                    placeholder="Confirm your password" 
                    autocomplete="off" 
                    required 
                    class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg bg-white/50 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                >
                <i class='bx bxs-check-circle absolute top-1/2 right-9 transform -translate-y-1/2 text-green-500 text-lg sm:text-xl hidden' id="cpassword-valid"></i>
            </div>
            <div class="error-message text-red-600 text-xs sm:text-sm ml-2" id="cpassword-error"></div>

            <input type="hidden" name="otp" id="otp">
            <input type="hidden" name="subject" value="OTP Verification Code">
            <button 
                type="submit" 
                id="submit-btn" 
                disabled 
                class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-800 text-white font-medium rounded-lg text-sm sm:text-base hover:from-blue-700 hover:to-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
                <span class="button-content flex items-center gap-2">
                    Register <i class='bx bx-right-arrow-alt'></i>
                </span>
            </button>
            <a href="../index.php" class="login-link text-blue-600 hover:underline transition-colors duration-200 text-sm sm:text-base block text-center mt-4">Already have an account? Login</a>
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
            formMessage.className = 'form-message hidden';
            emailValid.style.display = 'none';
            passwordValid.style.display = 'none';
            cpasswordValid.style.display = 'none';
            emailInput.classList.remove('border-green-500');
            passwordInput.classList.remove('border-green-500');
            confirmPasswordInput.classList.remove('border-green-500');

            if (!validateEmail(emailInput.value)) {
                emailError.textContent = 'Please enter a valid email address.';
                valid = false;
            } else {
                emailValid.style.display = 'block';
                emailInput.classList.add('border-green-500');
            }

            const passwordCheck = validatePassword(passwordInput.value);
            if (!passwordCheck.isValid) {
                passwordError.textContent = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
                valid = false;
            } else {
                passwordValid.style.display = 'block';
                passwordInput.classList.add('border-green-500');
            }
            passwordStrength.textContent = passwordCheck.message;
            passwordStrength.className = `password-strength text-${passwordCheck.class === 'weak' ? 'red' : passwordCheck.class === 'medium' ? 'yellow' : 'green'}-600`;

            if (passwordInput.value !== confirmPasswordInput.value) {
                cpasswordError.textContent = 'Passwords do not match.';
                valid = false;
            } else if (passwordInput.value && passwordCheck.isValid) {
                cpasswordValid.style.display = 'block';
                confirmPasswordInput.classList.add('border-green-500');
            }

            submitButton.disabled = !valid;
            submitButton.classList.toggle('disabled', !valid);
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
                    submitButton.querySelector('.button-content').style.display = 'none';

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
                        formMessage.className = 'form-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-center';
                        formMessage.textContent = 'Registration successful! OTP sent to your email.';
                        setTimeout(() => {
                            window.location.href = `../authentication/verify.php?email=${encodeURIComponent(formData.get('email'))}`;
                        }, 2000);
                    } else {
                        formMessage.className = 'form-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-center';
                        formMessage.textContent = data.message || 'Registration failed. Please try again.';
                        submitButton.disabled = false;
                        submitButton.classList.remove('loading');
                        submitButton.querySelector('.button-content').style.display = 'flex';
                    }
                } catch (error) {
                    console.error('Fetch error:', {
                        message: error.message,
                        stack: error.stack
                    });
                    formMessage.className = 'form-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-center';
                    formMessage.textContent = `Registration error: ${error.message.includes('HTTP error') ? 'Server error. Please try again later.' : 'Network error. Please check your connection.'}`;
                    submitButton.disabled = false;
                    submitButton.classList.remove('loading');
                    submitButton.querySelector('.button-content').style.display = 'flex';
                }
            }
        });
    </script>
</body>
</html>