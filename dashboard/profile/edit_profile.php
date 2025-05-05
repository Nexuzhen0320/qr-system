<?php
include '../../database/db.php';
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (empty($_SESSION['status_Account']) || empty($_SESSION['email'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch user data
$email = $_SESSION['email'];
$stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$stmt->close();

// Fetch user information
$stmt = $connection->prepare("SELECT * FROM user_information WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();
$stmt->close();

$errors = [];
$success = '';
$debug_log = []; // For debugging purposes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $other_gender = filter_input(INPUT_POST, 'other_gender', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $birthdate = filter_input(INPUT_POST, 'birthdate', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    // Explicitly sanitize occupation as a string
    $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $region = filter_input(INPUT_POST, 'region', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $profile_photo = $user_info['profile_photo'];

    // Debug: Log the raw occupation value
    $debug_log[] = "Raw occupation value: '$occupation'";

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($gender) || empty($birthdate) || empty($address) || empty($region) || empty($contact)) {
        $errors[] = "All required fields must be filled.";
    }

    // Validate occupation explicitly as a non-empty string
    if (empty($occupation) || !is_string($occupation) || !preg_match('/^[a-zA-Z\s]+$/', $occupation)) {
        $errors[] = "Occupation must be a valid string (letters and spaces only).";
    }

    // Validate contact number
    if (!preg_match('/^[0-9]{10}$/', $contact)) {
        $errors[] = "Contact number must be 10 digits.";
    }

    // Calculate age from birthdate
    $birth_date = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;

    // Handle profile photo upload
    if (!empty($_FILES['profile_photo']['name'])) {
        $file = $_FILES['profile_photo'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPEG or PNG images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Image size must not exceed 2MB.";
        } else {
            $image = file_get_contents($file['tmp_name']);
            $profile_photo = 'data:' . $file['type'] . ';base64,' . base64_encode($image);
        }
    }

    if (empty($errors)) {
        // Begin transaction
        $connection->begin_transaction();

        try {
            // Debug: Log the occupation before updating
            $debug_log[] = "Occupation before user_information update: '$occupation'";

            // Update user information (ensure occupation is bound as a string with "s")
            $stmt = $connection->prepare("
                UPDATE user_information 
                SET first_name = ?, last_name = ?, middle_name = ?, gender = ?, other_gender = ?, 
                    birthdate = ?, age = ?, occupation = ?, address = ?, region = ?, contact = ?, profile_photo = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param(
                "ssssssssssisi",
                $first_name,
                $last_name,
                $middle_name,
                $gender,
                $other_gender,
                $birthdate,
                $age,
                $occupation, // "s" ensures it's treated as a string
                $address,
                $region,
                $contact,
                $profile_photo,
                $user_id
            );
            $stmt->execute();
            $stmt->close();

            // Debug: Log the occupation before appointments update
            $debug_log[] = "Occupation before appointments update: '$occupation'";

            // Update all appointments for the user (ensure occupation is bound as a string with "s")
            $stmt = $connection->prepare("
                UPDATE appointments 
                SET first_name = ?, last_name = ?, middle_name = ?, gender = ?, other_gender = ?, 
                    birthdate = ?, age = ?, occupation = ?, address = ?, region = ?, email = ?, 
                    contact = ?, profile_photo = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param(
                "ssssssssssissi",
                $first_name,
                $last_name,
                $middle_name,
                $gender,
                $other_gender,
                $birthdate,
                $age,
                $occupation, // "s" ensures it's treated as a string
                $address,
                $region,
                $email,
                $contact,
                $profile_photo,
                $user_id
            );
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $connection->commit();
            $success = "Profile and appointments updated successfully.";
        } catch (Exception $e) {
            // Rollback transaction on error
            $connection->rollback();
            $errors[] = "Failed to update profile and appointments: " . $e->getMessage();
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
    <link rel="icon" type="image/x-icon" href="../image/icons/logo1.ico">
    <title>Edit Profile</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        /* CSS Variables */
        :root {
            --primary-color: #003087;
            --primary-hover: #00205b;
            --secondary-color: #6b7280;
            --error-color: #b91c1c;
            --success-color: #15803d;
            --border-color: #d1d5db;
            --bg-light: #f9fafb;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --text-color: #1f2937;
            --transition-speed: 0.3s;
        }

        /* General Layout */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .form-container {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            opacity: 0;
            transform: translateY(20px);
            animation: cardAppear 0.5s ease forwards 0.2s;
        }

        @keyframes cardAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            font-size: 22px;
            font-weight: 500;
            color: var(--text-color);
            text-align: center;
            margin-bottom: 20px;
        }

        /* Logo */
        .logo {
            width: 80px;
            height: auto;
            display: block;
            margin: 0 auto 20px;
            transition: transform var(--transition-speed) ease;
        }

        .logo:hover {
            transform: scale(1.1);
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .navbar a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background var(--transition-speed), color var(--transition-speed);
        }

        .navbar a:hover,
        .navbar a.active {
            background: var(--primary-color);
            color: #fff;
        }

        /* Form Layout */
        .form-group {
            margin-bottom: 15px;
        }

        .name-group {
            display: flex;
            gap: 20px;
        }

        .name-group .form-group {
            flex: 1;
        }

        .side-by-side {
            display: flex;
            gap: 20px;
        }

        .side-by-side .form-group {
            flex: 1;
        }

        /* Form Elements */
        .label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 6px;
            transition: color var(--transition-speed);
        }

        .label.required::after {
            content: '*';
            color: var(--error-color);
            margin-left: 4px;
            font-size: 12px;
        }

        .label:hover {
            color: var(--primary-color);
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            color: var(--text-color);
            background: #fff;
            transition: border-color var(--transition-speed);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        select {
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23444' viewBox='0 0 16 16'%3E%3Cpath d='M8 12L2 6h12l-6 6z'/%3E%3C/svg%3E") no-repeat right 10px center;
            appearance: none;
        }

        input[disabled] {
            background: #e5e7eb;
            color: #666;
            cursor: not-allowed;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        input[type="file"] {
            padding: 3px;
            font-size: 14px;
            color: var(--text-color);
        }

        .contact-group {
            display: flex;
            gap: 10px;
        }

        .contact-group input[type="text"]:first-child {
            width: 70px;
            background: #e5e7eb;
            pointer-events: none;
        }

        /* Photo Upload Section */
        .photo-upload-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .photo-placeholder {
            position: relative;
            width: 192px;
            height: 192px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: var(--secondary-color);
            text-align: center;
            transition: transform var(--transition-speed);
        }

        .photo-placeholder:hover {
            transform: scale(1.05);
        }

        .photo-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 4px;
            object-fit: cover;
            display: block;
        }

        .photo-preview.hidden {
            display: none;
        }

        .photo-upload-note {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 5px;
        }

        /* Buttons */
        .submit-btn {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition-speed);
            width: 100%;
            text-decoration: none;
            display: inline-block;
        }

        .submit-btn:hover {
            background: var(--primary-hover);
        }

        /* Feedback Messages */
        .error-message,
        .success-message {
            font-size: 13px;
            padding: 8px;
            border-radius: 4px;
            margin: 10px 0;
            text-align: center;
            display: none;
        }

        .error-message {
            color: var(--error-color);
            background: #fef2f2;
        }

        .success-message {
            color: var(--success-color);
            background: #f0fdf4;
            font-size: 16px;
            font-weight: 700;
            border: 2px solid var(--success-color);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        .error {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Debug Section */
        .debug-log {
            font-size: 12px;
            color: #555;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            display: none;
        }

        /* Media Queries for Responsiveness */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                max-width: 400px;
            }

            h1 {
                font-size: 20px;
            }

            .logo {
                width: 60px;
            }

            .name-group,
            .side-by-side {
                flex-direction: column;
                gap: 15px;
            }

            .photo-placeholder,
            .photo-preview {
                width: 150px;
                height: 150px;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .submit-btn {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 15px;
                max-width: 100%;
            }

            h1 {
                font-size: 18px;
            }

            .logo {
                width: 50px;
            }

            .navbar a {
                font-size: 12px;
                padding: 6px 12px;
            }

            .form-group {
                margin-bottom: 10px;
            }

            input[type="text"],
            input[type="email"],
            input[type="date"],
            input[type="number"],
            select,
            textarea {
                font-size: 12px;
                padding: 8px;
            }

            .label {
                font-size: 12px;
            }

            .photo-placeholder,
            .photo-preview {
                width: 120px;
                height: 120px;
            }

            .photo-upload-note {
                font-size: 10px;
            }

            .submit-btn {
                font-size: 12px;
                padding: 8px;
            }

            .success-message,
            .error-message {
                font-size: 12px;
                padding: 6px;
            }
        }

        /* Accessibility */
        .submit-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        #other_gender_group {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="form-container" role="main">
        <img src="../../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h1>Edit Profile</h1>
        <nav class="navbar">
            <a href="../../dashboard/dashboard.php">Dashboard</a>
            <a href="../../profile/edit_profile.php" class="active" aria-current="page">Edit Profile</a>
            <a href="../../logout/logout.php">Logout</a>
        </nav>

        <form method="POST" enctype="multipart/form-data" aria-label="Edit Profile Form">
            <!-- Photo Upload Section -->
            <div class="form-group">
                <label for="profile_photo" class="label required">Profile Photo (JPG/JPEG, max 2MB)</label>
                <div class="photo-upload-group">
                    <div class="photo-placeholder" id="profilePhotoPreview">
                        <img id="profilePhotoImg" class="photo-preview <?php echo empty($user_info['profile_photo']) ? 'hidden' : ''; ?>" src="<?php echo htmlspecialchars($user_info['profile_photo'] ?? ''); ?>" alt="Profile Photo Preview" aria-hidden="<?php echo empty($user_info['profile_photo']) ? 'true' : 'false'; ?>">
                        <?php if (empty($user_info['profile_photo'])): ?>
                            <span>No Photo Uploaded</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png">
                    <div class="photo-upload-note">Supports: JPG, JPEG, PNG (Max 2MB)</div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-group name-group">
                <div class="form-group">
                    <label for="first_name" class="label required">First Name</label>
                    <input type="text" id="first_name" name="first_name" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="last_name" class="label required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="middle_name" class="label">Middle Name (Optional)</label>
                <input type="text" id="middle_name" name="middle_name" autocomplete="off" value="<?php echo htmlspecialchars($user_info['middle_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="gender" class="label required">Gender</label>
                <select id="gender" name="gender" required aria-required="true">
                    <option value="" disabled>Select Gender</option>
                    <option value="Male" <?php echo (isset($user_info['gender']) && $user_info['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($user_info['gender']) && $user_info['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($user_info['gender']) && $user_info['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <div class="form-group" id="other_gender_group" style="display: <?php echo (isset($user_info['gender']) && $user_info['gender'] === 'Other') ? 'block' : 'none'; ?>;">
                    <label for="other_gender" class="label">Specify Gender</label>
                    <input type="text" id="other_gender" name="other_gender" value="<?php echo htmlspecialchars($user_info['other_gender'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group side-by-side">
                <div class="form-group">
                    <label for="birthdate" class="label required">Date of Birth</label>
                    <input type="date" id="birthdate" name="birthdate" required autocomplete="off" max="2025-05-05" aria-required="true" value="<?php echo htmlspecialchars($user_info['birthdate'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="age" class="label required">Age</label>
                    <input type="number" id="age" name="age" required autocomplete="off" min="1" max="120" aria-required="true" value="<?php echo htmlspecialchars($user_info['age'] ?? ''); ?>" disabled>
                </div>
            </div>
            <div class="form-group">
                <label for="occupation" class="label required">Occupation</label>
                <input type="text" id="occupation" name="occupation" required autocomplete="off" aria-required="true" pattern="[a-zA-Z\s]+" title="Occupation must contain only letters and spaces" value="<?php echo htmlspecialchars($user_info['occupation'] ?? ''); ?>">
                <span class="error" id="occupation-error"></span>
            </div>
            <div class="form-group">
                <label for="address" class="label required">Complete Address</label>
                <textarea id="address" name="address" required autocomplete="off" aria-required="true"><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="region" class="label required">Region</label>
                <input type="text" id="region" name="region" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($user_info['region'] ?? ''); ?>">
            </div>
            <div class="form-group side-by-side">
                <div class="form-group">
                    <label for="email" class="label required">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($email ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="contact" class="label required">Contact Number</label>
                    <div class="contact-group">
                        <input type="text" value="+63" readonly aria-label="Country code">
                        <input type="text" id="contact" name="contact" required autocomplete="off" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" aria-required="true" value="<?php echo htmlspecialchars($user_info['contact'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Debug Log -->
            <?php if (!empty($debug_log)): ?>
                <div class="debug-log">
                    <h3>Debug Log:</h3>
                    <?php foreach ($debug_log as $log): ?>
                        <p><?php echo htmlspecialchars($log); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Feedback and Submit -->
            <div class="form-group">
                <?php if (!empty($errors)): ?>
                    <div class="error-message" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group" style="text-align: center;">
                <button type="submit" class="submit-btn" aria-label="Update Profile">Update Profile</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const genderSelect = document.getElementById("gender");
            const otherGenderGroup = document.getElementById("other_gender_group");
            const birthdateInput = document.getElementById("birthdate");
            const ageInput = document.getElementById("age");
            const contactInput = document.getElementById("contact");
            const occupationInput = document.getElementById("occupation");
            const occupationError = document.getElementById("occupation-error");
            const profilePhotoInput = document.getElementById("profile_photo");
            const profilePhotoImg = document.getElementById("profilePhotoImg");
            const profilePhotoPreview = document.getElementById("profilePhotoPreview");

            // Toggle Other Gender Input
            function toggleOtherInput() {
                otherGenderGroup.style.display = genderSelect.value === "Other" ? "block" : "none";
            }
            genderSelect.addEventListener("change", toggleOtherInput);
            toggleOtherInput();

            // Auto-calculate Age
            birthdateInput.addEventListener("change", function() {
                const birthdate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }
                ageInput.value = age >= 0 ? age : "";
            });

            // Restrict Contact Number Input
            contactInput.addEventListener("input", function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, "").slice(0, 10);
            });

            // Validate Occupation Input
            occupationInput.addEventListener("input", function(e) {
                const value = e.target.value;
                const valid = /^[a-zA-Z\s]+$/.test(value);
                if (!valid && value !== "") {
                    occupationError.textContent = "Occupation must contain only letters and spaces.";
                    occupationError.style.display = "block";
                } else {
                    occupationError.style.display = "none";
                }
            });

            // Preview Profile Photo
            profilePhotoInput.addEventListener("change", function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        profilePhotoImg.src = event.target.result;
                        profilePhotoImg.classList.remove("hidden");
                        const noPhotoText = profilePhotoPreview.querySelector("span");
                        if (noPhotoText) noPhotoText.remove();
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Initialize feedback message visibility
            const successMessage = document.querySelector(".success-message");
            const errorMessage = document.querySelector(".error-message");
            const debugLog = document.querySelector(".debug-log");
            if (successMessage && successMessage.textContent) {
                successMessage.style.display = "block";
            }
            if (errorMessage && errorMessage.textContent) {
                errorMessage.style.display = "block";
            }
            if (debugLog && debugLog.textContent) {
                debugLog.style.display = "block";
            }
        });
    </script>
</body>
</html>