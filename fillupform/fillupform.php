<?php
include '../database/db.php';
session_start();


// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (empty($_SESSION['status_Account']) || empty($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Fetch user_id
$email = $_SESSION['email'];
$stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$stmt->close();

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appointment'])) {
    // Collect form data
    $last_name = filter_input(INPUT_POST, 'lastName', );
    $first_name = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $middle_name = filter_input(INPUT_POST, 'middleName', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $other_gender = filter_input(INPUT_POST, 'othergender', FILTER_SANITIZE_STRING);
    $birthdate = filter_input(INPUT_POST, 'birthdate', FILTER_SANITIZE_STRING);
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $region = filter_input(INPUT_POST, 'region', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
    $appointment_date = filter_input(INPUT_POST, 'appointmentDate', FILTER_SANITIZE_STRING);
    $appointment_time = filter_input(INPUT_POST, 'appointmentTime', FILTER_SANITIZE_STRING);
    $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING);
    $profile_photo = $_SESSION['profilePhoto'] ?? '';

    // Basic validation
    if (empty($last_name)) $errors['lastName'] = 'Last name is required.';
    if (empty($first_name)) $errors['firstName'] = 'First name is required.';
    if (empty($gender)) $errors['gender'] = 'Gender is required.';
    if ($gender === 'Other' && empty($other_gender)) $errors['othergender'] = 'Please specify your gender.';
    if (empty($birthdate)) $errors['birthdate'] = 'Date of birth is required.';
    if (empty($age) || $age < 1 || $age > 120) $errors['age'] = 'Valid age is required.';
    if (empty($occupation)) $errors['occupation'] = 'Occupation is required.';
    if (empty($address)) $errors['address'] = 'Address is required.';
    if (empty($region)) $errors['region'] = 'Region is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if (empty($contact) || !preg_match('/^[0-9]{10}$/', $contact)) $errors['contact'] = 'Valid 10-digit contact number is required.';
    if (empty($appointment_date)) $errors['appointmentDate'] = 'Appointment date is required.';
    if (empty($appointment_time)) $errors['appointmentTime'] = 'Appointment time is required.';
    if (empty($purpose)) $errors['purpose'] = 'Purpose is required.';
    if (empty($profile_photo)) $errors['myFile'] = 'Profile photo is required.';

    // Validate appointment date (Thursdays only)
    $date = new DateTime($appointment_date);
    if ($date->format('N') !== '4') {
        $errors['appointmentDate'] = 'Appointment date must be a Thursday.';
    }

    if (empty($errors)) {
        // Start transaction to ensure all inserts succeed
        $connection->begin_transaction();

        try {
            // Insert or update user_information
            $stmt = $connection->prepare("
                INSERT INTO user_information (
                    user_id, last_name, first_name, middle_name, gender, other_gender, birthdate, age, 
                    occupation, address, region, email, contact, profile_photo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    last_name = VALUES(last_name),
                    first_name = VALUES(first_name),
                    middle_name = VALUES(middle_name),
                    gender = VALUES(gender),
                    other_gender = VALUES(other_gender),
                    birthdate = VALUES(birthdate),
                    age = VALUES(age),
                    occupation = VALUES(occupation),
                    address = VALUES(address),
                    region = VALUES(region),
                    email = VALUES(email),
                    contact = VALUES(contact),
                    profile_photo = VALUES(profile_photo),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param(
                "issssssissssss",
                $user_id, $last_name, $first_name, $middle_name, $gender, $other_gender, $birthdate, $age,
                $occupation, $address, $region, $email, $contact, $profile_photo
            );
            $stmt->execute();
            $stmt->close();

            // Insert into appointments table
            $stmt = $connection->prepare("
                INSERT INTO appointments (
                    user_id, last_name, first_name, middle_name, gender, other_gender, birthdate, age, 
                    occupation, address, region, email, contact, appointment_date, appointment_time, 
                    purpose, profile_photo, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->bind_param(
                "issssssisssssssss",
                $user_id, $last_name, $first_name, $middle_name, $gender, $other_gender, $birthdate, $age,
                $occupation, $address, $region, $email, $contact, $appointment_date, $appointment_time,
                $purpose, $profile_photo
            );
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $connection->commit();

            $success_message = 'Appointment and user information submitted successfully!';
            // Clear profile photo session
            unset($_SESSION['profilePhoto']);

            // Check if request is AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Submit Application Successfully']);
                exit();
            }

            // Non-AJAX: Redirect to dashboard
            header("Location: ../dashboard/dashboard.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $connection->rollback();
            $errors['general'] = 'Failed to submit appointment and user information. Please try again.';

            // Check if request is AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errors['general']]);
                exit();
            }
        }
    } else {
        // Validation errors for AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Validation failed', 'errors' => $errors]);
            exit();
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
    <!-- Add Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <title>New Registration Form</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        /* CSS Variables */
        :root {
            --primary-color: #003087; /* Deep blue for a formal look */
            --primary-hover: #00205b;
            --secondary-color: #6b7280; /* Muted gray for secondary elements */
            --error-color: #b91c1c; /* Muted red for errors */
            --success-color: #15803d; /* Muted green for success */
            --border-color: #d1d5db;
            --bg-light: #f9fafb;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --text-color: #1f2937;
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
        }

        .form-container {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
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
            transition: background 0.3s ease, color 0.3s ease;
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
        }

        .label.required::after {
            content: '*';
            color: var(--error-color);
            margin-left: 4px;
            font-size: 12px;
        }

        input[type="text"],
        input[type="email"],
        input[type="time"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            color: var(--text-color);
            background: #fff;
            transition: border-color 0.3s ease;
        }

        input:focus,
        select:focus {
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
        }

        .photo-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 4px;
            object-fit: cover;
            display: none;
        }

        .photo-preview.active {
            display: block;
        }

        .photo-upload-note {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 5px;
        }

        /* Buttons */
        .upload-button,
        button[type="submit"] {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .upload-button:hover,
        button[type="submit"]:hover {
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

        /* Loading Spinner */
        .loading-spinner {
            display: none;
            border: 3px solid #e5e7eb;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Flatpickr Custom Styling */
        .flatpickr-day.thursday {
            color: #000 !important; /* Thursdays in black */
            font-weight: 500;
        }

        .flatpickr-day:not(.thursday) {
            color: #999 !important; /* Non-Thursdays in gray */
            pointer-events: none; /* Prevent clicking on non-Thursdays */
        }

        .flatpickr-day.selected {
            background: var(--primary-color) !important;
            color: #fff !important;
            border-color: var(--primary-color) !important;
        }

        .flatpickr-day.today {
            border-color: var(--primary-color) !important;
        }

        .flatpickr-day.today.thursday {
            color: #000 !important;
        }

        .flatpickr-day.today:not(.thursday) {
            color: #999 !important;
        }

        /* Media Queries */
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

            .upload-button,
            button[type="submit"] {
                width: 100%;
            }
        }

        /* Accessibility */
        .upload-button:focus,
        button[type="submit"]:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        #othergenderGroup {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="form-container" role="main">
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h1>New Registration Form</h1>
        <nav class="navbar">
            <a href="new_registration_form.php" class="active" aria-current="page">Registration Form</a>
            <a href="../logout/logout.php">Logout</a>
        </nav>

        <form id="newRegisterForm" action="" method="POST" enctype="multipart/form-data" aria-label="Registration Form">
            <!-- Photo Upload Section -->
            <div class="form-group">
                <label for="myFile" class="label required">Profile Photo (2x2, JPG/JPEG)</label>
                <div class="photo-upload-group">
                    <div class="photo-placeholder" id="profilePhotoPreview">
                        <img id="profilePhotoImg" class="photo-preview <?php echo !empty($_SESSION['profilePhoto']) ? 'active' : ''; ?>" src="<?php echo htmlspecialchars($_SESSION['profilePhoto'] ?? ''); ?>" alt="Profile Photo Preview" aria-hidden="<?php echo empty($_SESSION['profilePhoto']) ? 'true' : 'false'; ?>">
                        <?php if (empty($_SESSION['profilePhoto'])): ?>
                            <span>No Photo Uploaded</span>
                        <?php endif; ?>
                    </div>
                    <a href="../photo config upload/photo_upload.php" class="upload-button" aria-label="Upload profile photo">Upload Photo</a>
                    <div class="photo-upload-note">Supports: JPG, JPEG (Max 2MB)</div>
                </div>
                <span class="error" id="myFile-error"><?php echo $errors['myFile'] ?? ''; ?></span>
            </div>

            <!-- Personal Information -->
            <div class="form-group name-group">
                <div class="form-group">
                    <label for="lastName" class="label required">Last Name</label>
                    <input type="text" id="lastName" name="lastName" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                    <span class="error" id="lastName-error"><?php echo $errors['lastName'] ?? ''; ?></span>
                </div>
                <div class="form-group">
                    <label for="firstName" class="label required">First Name</label>
                    <input type="text" id="firstName" name="firstName" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                    <span class="error" id="firstName-error"><?php echo $errors['firstName'] ?? ''; ?></span>
                </div>
            </div>
            <div class="form-group">
                <label for="middleName" class="label">Middle Name (Optional)</label>
                <input type="text" id="middleName" name="middleName" autocomplete="off" value="<?php echo htmlspecialchars($middle_name ?? ''); ?>">
                <span class="error" id="middleName-error"><?php echo $errors['middleName'] ?? ''; ?></span>
            </div>
            <div class="form-group">
                <label for="gender" class="label required">Gender</label>
                <select id="gender" name="gender" required aria-required="true">
                    <option value="" selected disabled>Select Gender</option>
                    <option value="Male" <?php echo (isset($gender) && $gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($gender) && $gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($gender) && $gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <span class="error" id="gender-error"><?php echo $errors['gender'] ?? ''; ?></span>
                <div class="form-group" id="othergenderGroup" style="display: none;">
                    <label for="othergender" class="label">Specify Gender</label>
                    <input type="text" id="othergender" name="othergender" placeholder="Specify your gender" value="<?php echo htmlspecialchars($other_gender ?? ''); ?>">
                    <span class="error" id="othergender-error"><?php echo $errors['othergender'] ?? ''; ?></span>
                </div>
            </div>
            <div class="form-group side-by-side">
                <div class="form-group">
                    <label for="birthdate" class="label required">Date of Birth</label>
                    <input type="date" id="birthdate" name="birthdate" required autocomplete="off" max="2025-04-17" aria-required="true" value="<?php echo htmlspecialchars($birthdate ?? ''); ?>">
                    <span class="error" id="birthdate-error"><?php echo $errors['birthdate'] ?? ''; ?></span>
                </div>
                <div class="form-group">
                    <label for="age" class="label required">Age</label>
                    <input type="number" id="age" name="age" required autocomplete="off" min="1" max="120" aria-required="true" value="<?php echo htmlspecialchars($age ?? ''); ?>">
                    <span class="error" id="age-error"><?php echo $errors['age'] ?? ''; ?></span>
                </div>
            </div>
            <div class="form-group">
                <label for="occupation" class="label required">Occupation</label>
                <input type="text" id="occupation" name="occupation" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($occupation ?? ''); ?>">
                <span class="error" id="occupation-error"><?php echo $errors['occupation'] ?? ''; ?></span>
            </div>
            <div class="form-group">
                <label for="address" class="label required">Complete Address</label>
                <input type="text" id="address" name="address" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($address ?? ''); ?>">
                <span class="error" id="address-error"><?php echo $errors['address'] ?? ''; ?></span>
            </div>
            <div class="form-group">
                <label for="region" class="label required">Region</label>
                <input type="text" id="region" name="region" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($region ?? ''); ?>">
                <span class="error" id="region-error"><?php echo $errors['region'] ?? ''; ?></span>
            </div>
            <div class="form-group side-by-side">
                <div class="form-group">
                    <label for="email" class="label required">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <span class="error" id="email-error"><?php echo $errors['email'] ?? ''; ?></span>
                </div>
                <div class="form-group">
                    <label for="contact" class="label required">Contact Number</label>
                    <div class="contact-group">
                        <input type="text" value="+63" readonly aria-label="Country code">
                        <input type="text" id="contact" name="contact" required autocomplete="off" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" aria-required="true" value="<?php echo htmlspecialchars($contact ?? ''); ?>">
                    </div>
                    <span class="error" id="contact-error"><?php echo $errors['contact'] ?? ''; ?></span>
                </div>
            </div>
            <div class="form-group side-by-side">
                <div class="form-group">
                    <label for="appointmentDate" class="label required">Appointment Date (Thursdays)</label>
                    <input type="text" id="appointmentDate" name="appointmentDate" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($appointment_date ?? ''); ?>" placeholder="Select a Thursday">
                    <div class="photo-upload-note">Only Thursdays are selectable</div>
                    <span class="error" id="appointmentDate-error"><?php echo $errors['appointmentDate'] ?? ''; ?></span>
                </div>
                <div class="form-group">
                    <label for="appointmentTime" class="label required">Appointment Time</label>
                    <input type="time" id="appointmentTime" name="appointmentTime" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($appointment_time ?? ''); ?>">
                    <span class="error" id="appointmentTime-error"><?php echo $errors['appointmentTime'] ?? ''; ?></span>
                </div>
            </div>
            <div class="form-group">
                <label for="purpose" class="label required">Purpose of Appointment</label>
                <input type="text" id="purpose" name="purpose" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($purpose ?? ''); ?>">
                <span class="error" id="purpose-error"><?php echo $errors['purpose'] ?? ''; ?></span>
            </div>

            <!-- Feedback and Submit -->
            <div class="form-group">
                <div class="success-message" id="successMessage" role="alert"><?php echo $success_message; ?></div>
                <div class="error-message" id="errorMessage" role="alert"><?php echo $errors['general'] ?? ''; ?></div>
                <div class="loading-spinner" id="loadingSpinner" aria-label="Loading"></div>
            </div>
            <div class="form-group" style="text-align: center;">
                <button type="submit" aria-label="Submit appointment">Submit Appointment</button>
            </div>
            <input type="hidden" name="submit_appointment" value="1">
        </form>
    </div>

    <!-- Add Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Elements
            const form = document.getElementById("newRegisterForm");
            const genderSelect = document.getElementById("gender");
            const othergenderGroup = document.getElementById("othergenderGroup");
            const birthdateInput = document.getElementById("birthdate");
            const ageInput = document.getElementById("age");
            const contactInput = document.getElementById("contact");
            const appointmentDateInput = document.getElementById("appointmentDate");
            const successMessage = document.getElementById("successMessage");
            const errorMessage = document.getElementById("errorMessage");
            const loadingSpinner = document.getElementById("loadingSpinner");

            // Initialize Flatpickr for Appointment Date
            flatpickr("#appointmentDate", {
                dateFormat: "Y-m-d",
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    const date = new Date(dayElem.dateObj);
                    if (date.getDay() === 4) { // Thursday (0 = Sunday, 4 = Thursday)
                        dayElem.classList.add("thursday");
                    }
                },
                disable: [
                    function(date) {
                        // Disable all days that are not Thursdays
                        return date.getDay() !== 4;
                    }
                ],
                minDate: "today",
                defaultDate: "<?php echo htmlspecialchars($appointment_date ?? ''); ?>"
            });

            // Toggle Other Gender Input
            function toggleOtherInput() {
                othergenderGroup.style.display = genderSelect.value === "Other" ? "block" : "none";
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

            // Form Validation
            function validateForm() {
                let isValid = true;
                successMessage.style.display = "none";
                errorMessage.style.display = "none";

                const fields = [
                    { id: "lastName", errorId: "lastName-error", message: "Last name is required" },
                    { id: "firstName", errorId: "firstName-error", message: "First name is required" },
                    { id: "gender", errorId: "gender-error", message: "Please select a gender" },
                    { id: "birthdate", errorId: "birthdate-error", message: "Date of birth is required" },
                    { id: "age", errorId: "age-error", message: "Age is required" },
                    { id: "occupation", errorId: "occupation-error", message: "Occupation is required" },
                    { id: "address", errorId: "address-error", message: "Address is required" },
                    { id: "region", errorId: "region-error", message: "Region is required" },
                    { id: "email", errorId: "email-error", message: "Email is required" },
                    { id: "contact", errorId: "contact-error", message: "Contact number is required" },
                    { id: "appointmentDate", errorId: "appointmentDate-error", message: "Appointment date is required" },
                    { id: "appointmentTime", errorId: "appointmentTime-error", message: "Appointment time is required" },
                    { id: "purpose", errorId: "purpose-error", message: "Purpose is required" }
                ];

                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const error = document.getElementById(field.errorId);
                    if (!input.value.trim()) {
                        error.textContent = field.message;
                        error.style.display = "block";
                        isValid = false;
                    } else {
                        error.style.display = "none";
                    }
                });

                // Validate Email
                const email = document.getElementById("email").value;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    document.getElementById("email-error").textContent = "Please enter a valid email.";
                    document.getElementById("email-error").style.display = "block";
                    isValid = false;
                }

                // Validate Contact Number
                const contact = document.getElementById("contact").value;
                if (!/^[0-9]{10}$/.test(contact)) {
                    document.getElementById("contact-error").textContent = "Please enter a valid 10-digit number.";
                    document.getElementById("contact-error").style.display = "block";
                    isValid = false;
                }

                // Validate Other Gender
                if (genderSelect.value === "Other") {
                    const othergenderInput = document.getElementById("othergender");
                    const othergenderError = document.getElementById("othergender-error");
                    if (!othergenderInput.value.trim()) {
                        othergenderError.textContent = "Please specify your gender.";
                        othergenderError.style.display = "block";
                        isValid = false;
                    } else {
                        othergenderError.style.display = "none";
                    }
                }

                // Validate Profile Photo
                <?php if (empty($_SESSION['profilePhoto'])): ?>
                    document.getElementById("myFile-error").textContent = "Please upload a profile photo.";
                    document.getElementById("myFile-error").style.display = "block";
                    isValid = false;
                <?php else: ?>
                    document.getElementById("myFile-error").style.display = "none";
                <?php endif; ?>

                return isValid;
            }

            // Form Submission with AJAX
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                if (!validateForm()) {
                    return;
                }

                // Show loading spinner
                loadingSpinner.style.display = "block";
                successMessage.style.display = "none";
                errorMessage.style.display = "none";

                // Create FormData object
                const formData = new FormData(form);

                // Send AJAX request
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    loadingSpinner.style.display = "none";
                    if (data.success) {
                        successMessage.textContent = data.message || "Submit Application Successfully";
                        successMessage.style.display = "block";
                        // Delay redirect to show the success message
                        setTimeout(() => {
                            window.location.href = "../dashboard/dashboard.php";
                        }, 2000);
                    } else {
                        errorMessage.textContent = data.error || "Failed to submit appointment. Please try again.";
                        errorMessage.style.display = "block";
                    }
                })
                .catch(error => {
                    loadingSpinner.style.display = "none";
                    errorMessage.textContent = "An error occurred during submission. Please try again.";
                    errorMessage.style.display = "block";
                    console.error('Error:', error);
                });
            });

            // Initialize error and success message visibility
            if (successMessage.textContent) {
                successMessage.style.display = "block";
            }
            if (errorMessage.textContent) {
                errorMessage.style.display = "block";
            }
        });
    </script>
</body>
</html>