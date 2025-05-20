<?php
include '../database/db.php';
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if not logged in
if (empty($_SESSION['status_Account']) || empty($_SESSION['email'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not logged in.']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}

// Fetch user_id
$email = $_SESSION['email'];
$stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'] ?? null;
$stmt->close();

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

$errors = [];
$success_message = '';
$debug_log = [];

function logError($message) {
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ": $message\n", FILE_APPEND);
}

// Directory paths
define('PHYSICAL_UPLOAD_DIR', '../ProfileImage/image/IdPhoto/');
define('RELATIVE_UPLOAD_DIR', '../ProfileImage/image/IdPhoto/');
define('PROFILE_PHYSICAL_UPLOAD_DIR', '../ProfileImage/image/Profile_Photo/');
define('PROFILE_RELATIVE_UPLOAD_DIR', '../ProfileImage/image/Profile_Photo/');

// Function to generate a unique appointment ID
function generateAppointmentId($connection) {
    $maxRetries = 5;
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $randomNumber = mt_rand(0, 99999999);
        $appointment_id = sprintf("%08d", $randomNumber);
        $stmt = $connection->prepare("SELECT COUNT(*) AS count FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("s", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['count'] == 0) {
            return $appointment_id;
        }

        $attempt++;
    }

    throw new Exception("Unable to generate a unique appointment ID after $maxRetries attempts.");
}

// Handle ID photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['idPhoto']) && isset($_POST['upload_id_photo'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors['general'] = 'Invalid CSRF token.';
        $debug_log[] = 'CSRF token validation failed.';
        logError('CSRF token validation failed for ID photo upload.');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors, 'debug' => $debug_log]);
            exit();
        }
    }

    $id_type = filter_input(INPUT_POST, 'idType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $id_number = filter_input(INPUT_POST, 'idNumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $file = $_FILES['idPhoto'];
    $validTypes = ['image/jpeg', 'image/jpg'];

    $baseFileName = 'id_' . $user_id;
    $fileName = $baseFileName . '.jpg';
    $uploadPath = PHYSICAL_UPLOAD_DIR . $fileName;
    $relativePath = RELATIVE_UPLOAD_DIR . $fileName;

    $counter = 1;
    while (file_exists($uploadPath)) {
        $fileName = $baseFileName . '_' . $counter . '.jpg';
        $uploadPath = PHYSICAL_UPLOAD_DIR . $fileName;
        $relativePath = RELATIVE_UPLOAD_DIR . $fileName;
        $counter++;
    }

    if (!is_dir(PHYSICAL_UPLOAD_DIR)) {
        if (!mkdir(PHYSICAL_UPLOAD_DIR, 0777, true)) {
            $errors['idPhoto'] = 'Failed to create upload directory.';
            $debug_log[] = "Failed to create directory: " . PHYSICAL_UPLOAD_DIR;
            logError("Failed to create upload directory: " . PHYSICAL_UPLOAD_DIR);
        }
    }

    if (empty($id_type)) {
        $errors['idType'] = "Please select a valid ID type.";
    } elseif (empty($id_number) || !preg_match('/^[A-Za-z0-9-]{1,50}$/', $id_number)) {
        $errors['idNumber'] = "ID number must be alphanumeric (up to 50 characters).";
    } elseif (!in_array($file['type'], $validTypes)) {
        $errors['idPhoto'] = "Please upload a valid .jpg or .jpeg file.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['idPhoto'] = "Upload failed: " . [
            UPLOAD_ERR_INI_SIZE => "File exceeds server size limit.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form size limit.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Temporary folder missing.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
        ][$file['error']] ?? "Unknown error.";
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errors['idPhoto'] = "Security error: Invalid file upload.";
    } else {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors['idPhoto'] = "Invalid image file. Please upload a valid JPEG.";
        } elseif ($imageInfo[0] < 180 || $imageInfo[1] < 180 || abs($imageInfo[0] - $imageInfo[1]) > 20) {
            $errors['idPhoto'] = "Image must be approximately 2x2 inches (~192x192 pixels at 96 DPI).";
        } else {
            if (extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($file['tmp_name']);
                if ($image === false) {
                    $errors['idPhoto'] = "Failed to process image. Try another file.";
                } else {
                    if (imagejpeg($image, $uploadPath, 75)) {
                        imagedestroy($image);
                        $_SESSION['idPhoto'] = $relativePath;
                        $_SESSION['idType'] = $id_type;
                        $_SESSION['idNumber'] = $id_number;
                        $debug_log[] = "ID photo uploaded to: $uploadPath, stored as: $relativePath";
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'ID uploaded successfully.', 'idPhoto' => $relativePath]);
                            session_write_close();
                            exit();
                        }
                    } else {
                        imagedestroy($image);
                        $errors['idPhoto'] = "Failed to save image to server.";
                        $debug_log[] = "Failed to save image to: $uploadPath";
                        logError("Failed to save ID photo to: $uploadPath");
                    }
                }
            } else {
                if (!empty($_POST['compressedImage'])) {
                    $data = $_POST['compressedImage'];
                    $data = str_replace('data:image/jpeg;base64,', '', $data);
                    $data = base64_decode($data);
                    if ($data === false) {
                        $errors['idPhoto'] = "Invalid compressed image data.";
                    } elseif (file_put_contents($uploadPath, $data)) {
                        $_SESSION['idPhoto'] = $relativePath;
                        $_SESSION['idType'] = $id_type;
                        $_SESSION['idNumber'] = $id_number;
                        $debug_log[] = "Compressed ID photo uploaded to: $uploadPath, stored as: $relativePath";
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'ID uploaded successfully.', 'idPhoto' => $relativePath]);
                            session_write_close();
                            exit();
                        }
                    } else {
                        $errors['idPhoto'] = "Failed to save compressed image to server.";
                        $debug_log[] = "Failed to save compressed image to: $uploadPath";
                        logError("Failed to save compressed ID photo to: $uploadPath");
                    }
                } else {
                    $errors['idPhoto'] = "Server image processing unavailable.";
                }
            }
        }
    }

    if (!empty($errors) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors, 'debug' => $debug_log]);
        exit();
    }
}

// Handle photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['removeIdPhoto'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors['general'] = 'Invalid CSRF token.';
        $debug_log[] = 'CSRF token validation failed for photo removal.';
        logError('CSRF token validation failed for photo removal.');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors, 'debug' => $debug_log]);
            exit();
        }
    }

    if (!empty($_SESSION['idPhoto'])) {
        $fullPath = str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto']);
        if (file_exists($fullPath)) {
            unlink($fullPath);
            $debug_log[] = "Removed ID photo: $fullPath";
        } else {
            $debug_log[] = "ID photo not found for removal: $fullPath";
        }
    }
    unset($_SESSION['idPhoto'], $_SESSION['idType'], $_SESSION['idNumber']);
    $debug_log[] = "Cleared ID photo session data.";
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'ID photo removed successfully.']);
        session_write_close();
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appointment'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors['general'] = 'Invalid CSRF token.';
        $debug_log[] = 'CSRF token validation failed for form submission.';
        logError('CSRF token validation failed for form submission.');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors, 'debug' => $debug_log]);
            exit();
        }
    }

    $last_name = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $first_name = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $middle_name = filter_input(INPUT_POST, 'middleName', FILTER_SANITIZE_STRING) ?: null;
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $other_gender = filter_input(INPUT_POST, 'othergender', FILTER_SANITIZE_STRING) ?: null;
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
    $id_type = $_SESSION['idType'] ?? '';
    $id_number = $_SESSION['idNumber'] ?? '';
    $id_photo = $_SESSION['idPhoto'] ?? '';

    // Validate inputs
    if (empty($last_name)) $errors['lastName'] = 'Last name is required.';
    if (empty($first_name)) $errors['firstName'] = 'First name is required.';
    if (empty($gender)) $errors['gender'] = 'Gender is required.';
    if ($gender === 'Other' && empty($other_gender)) $errors['othergender'] = 'Please specify your gender.';
    if (empty($birthdate) || !DateTime::createFromFormat('Y-m-d', $birthdate)) $errors['birthdate'] = 'Valid date of birth is required.';
    if (empty($age) || $age < 1 || $age > 120) $errors['age'] = 'Valid age (1-120) is required.';
    if (empty($occupation)) $errors['occupation'] = 'Occupation is required.';
    if (empty($address)) $errors['address'] = 'Address is required.';
    if (empty($region)) $errors['region'] = 'Region is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if (empty($contact) || !preg_match('/^[0-9]{10}$/', $contact)) $errors['contact'] = 'Valid 10-digit contact number is required.';
    if (empty($appointment_date)) $errors['appointmentDate'] = 'Appointment date is required.';
    if (empty($appointment_time)) {
        $errors['appointmentTime'] = 'Appointment time is required.';
    } elseif (!preg_match('/^([0-1][0-9]|2[0-2]):[0-5][0-9]$/', $appointment_time) || $appointment_time < '05:00' || $appointment_time > '22:00') {
        $errors['appointmentTime'] = 'Appointment time must be between 5:00 AM and 10:00 PM.';
        $debug_log[] = "Invalid appointment time: $appointment_time";
        logError("Invalid appointment time: $appointment_time");
    }
    if (empty($purpose)) $errors['purpose'] = 'Purpose is required.';
    if (empty($profile_photo)) {
        $errors['myFile'] = 'Profile photo is required.';
        $debug_log[] = "Profile photo missing: $profile_photo";
    } elseif (!file_exists(str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $profile_photo))) {
        $errors['myFile'] = 'Profile photo file not found.';
        $debug_log[] = "Profile photo not found: " . str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $profile_photo);
        logError("Profile photo not found: " . str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $profile_photo));
    }
    if (empty($id_type)) $errors['idType'] = 'ID type is required.';
    if (empty($id_number)) $errors['idNumber'] = 'ID number is required.';
    if (empty($id_photo)) {
        $errors['idPhoto'] = 'ID photo is required.';
        $debug_log[] = "ID photo missing: $id_photo";
    } elseif (!file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $id_photo))) {
        $errors['idPhoto'] = 'ID photo file not found.';
        $debug_log[] = "ID photo not found: " . str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $id_photo);
        logError("ID photo not found: " . str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $id_photo));
    }

    if (!empty($appointment_date)) {
        $date = DateTime::createFromFormat('Y-m-d', $appointment_date);
        if ($date === false || $date->format('N') !== '4') {
            $errors['appointmentDate'] = 'Appointment date must be a Thursday.';
        }
    }

    if (empty($errors)) {
        $connection->begin_transaction();
        try {
            $appointment_id = generateAppointmentId($connection);

            $userStmt = $connection->prepare("
                INSERT INTO user_information (
                    user_id, last_name, first_name, middle_name, gender, other_gender, birthdate, age, 
                    occupation, address, region, email, contact, profile_photo, id_type, id_number, id_photo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    id_type = VALUES(id_type),
                    id_number = VALUES(id_number),
                    id_photo = VALUES(id_photo),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $userStmt->bind_param(
                "issssssisssssssss",
                $user_id, $last_name, $first_name, $middle_name, $gender, $other_gender, $birthdate, $age,
                $occupation, $address, $region, $email, $contact, $profile_photo, $id_type, $id_number, $id_photo
            );
            $userStmt->execute();

            $apptStmt = $connection->prepare("
                INSERT INTO appointments (
                    appointment_id, user_id, last_name, first_name, middle_name, gender, other_gender, birthdate, age, 
                    occupation, address, region, email, contact, appointment_date, appointment_time, 
                    purpose, profile_photo, id_type, id_number, id_photo, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $apptStmt->bind_param(
                "sissssssissssssssssss",
                $appointment_id, $user_id, $last_name, $first_name, $middle_name, $gender, $other_gender, $birthdate, $age,
                $occupation, $address, $region, $email, $contact, $appointment_date, $appointment_time,
                $purpose, $profile_photo, $id_type, $id_number, $id_photo
            );
            $apptStmt->execute();

            $connection->commit();
            $success_message = "Appointment submitted successfully with ID: $appointment_id.";
            unset($_SESSION['profilePhoto'], $_SESSION['idPhoto'], $_SESSION['idNumber'], $_SESSION['idType']);
            $debug_log[] = "Appointment submitted successfully for user_id: $user_id with appointment_id: $appointment_id";

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Appointment submitted successfully with ID: $appointment_id.", 'appointment_id' => $appointment_id]);
                session_write_close();
                exit();
            }

            header("Location: ../dashboard/dashboard.php");
            exit();
        } catch (Exception $e) {
            $connection->rollback();
            $errors['general'] = 'Failed to submit appointment: ' . $e->getMessage();
            $debug_log[] = "Database error: " . $e->getMessage();
            logError("Failed to submit appointment: " . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errors['general'], 'debug' => $debug_log]);
                exit();
            }
        } finally {
            if (isset($userStmt)) $userStmt->close();
            if (isset($apptStmt)) $apptStmt->close();
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Validation failed', 'errors' => $errors, 'debug' => $debug_log]);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" media="all" defer>
    <link rel="icon" type="image/x-icon" href="../image/icons/logo1.ico">
    <title>Registration Form</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap');

        :root {
            --primary-color: #1a3c6e;
            --primary-hover: #0f2452;
            --accent-color: #e6f0fa;
            --text-color: #333333;
            --border-color: #d4d4d4;
            --error-color: #a51c1c;
            --success-color: #2e7d32;
            --bg-color: #f8fafc;
            --white: #ffffff;
            --shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            --input-bg: #fafafa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Open Sans', sans-serif;
        }

        body {
            background: var(--bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            max-width: 800px;
            width: 100%;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        /* Header */
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 5rem;
            height: auto;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-color);
        }

        /* Navigation */
        .navbar {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 0.75rem;
            background: var(--accent-color);
            border-radius: 8px;
        }

        .navbar a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s, color 0.3s;
        }

        .navbar a:hover,
        .navbar a.active {
            background: var(--primary-color);
            color: var(--white);
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Labels and Inputs */
        .label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .label.required::after {
            content: '*';
            color: var(--error-color);
            font-size: 0.75rem;
            margin-left: 0.25rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="time"],
        input[type="number"],
        input[type="date"],
        select,
        input[type="text"].flatpickr-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            color: var(--text-color);
            background: var(--input-bg);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus,
        select:focus,
        input[type="text"].flatpickr-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 60, 110, 0.1);
        }

        select {
            appearance: none;
            background: var(--input-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M8 12L2 6h12l-6 6z'/%3E%3C/svg%3E") no-repeat right 0.75rem center;
            padding-right: 2rem;
        }

        input[disabled] {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        /* Contact Group */
        .contact-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .contact-group input[type="text"]:first-child {
            width: 4rem;
            background: #f5f5f5;
            pointer-events: none;
            border-radius: 6px;
        }

        /* Photo Upload Section */
        .photo-upload-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .photo-placeholder {
            position: relative;
            width: 12rem;
            height: 12rem;
            background: var(--accent-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.875rem;
            color: #666;
        }

        .photo-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
            display: none;
        }

        .photo-preview.active {
            display: block;
        }

        .photo-upload-note {
            font-size: 0.75rem;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-danger {
            background: var(--error-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-danger:hover {
            background: #8b1a1a;
            transform: translateY(-1px);
        }

        /* Messages and Feedback */
        .error-message,
        .success-message {
            padding: 0.75rem;
            border-radius: 6px;
            margin: 0.75rem 0;
            font-size: 0.875rem;
            text-align: center;
            display: none;
        }

        .error-message {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .success-message {
            background: #f0fdf4;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .error {
            color: var(--error-color);
            font-size: 0.75rem;
            margin-top: 0.375rem;
            display: none;
        }

        /* Loading Spinner and Progress */
        .loading-spinner {
            display: none;
            border: 3px solid #e5e7eb;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            animation: spin 1s linear infinite;
            margin: 0.75rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-bar {
            display: none;
            width: 100%;
            height: 0.25rem;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar div {
            height: 100%;
            background: var(--primary-color);
            width: 0;
            transition: width 0.3s ease;
        }

        /* Flatpickr Customization */
        .flatpickr-day.thursday {
            color: var(--text-color) !important;
            font-weight: 600;
        }

        .flatpickr-day:not(.thursday) {
            color: #ccc !important;
            pointer-events: none;
        }

        .flatpickr-day.selected {
            background: var(--primary-color) !important;
            color: var(--white) !important;
            border-color: var(--primary-color) !important;
        }

        .flatpickr-calendar {
            font-size: 0.875rem;
        }

        /* Debug Log */
        .debug-log {
            font-size: 0.75rem;
            color: #555;
            background: #f0f0f0;
            padding: 0.75rem;
            border-radius: 6px;
            margin: 0.75rem 0;
            display: none;
        }

        /* Accessibility */
        button:focus,
        a:focus,
        input:focus,
        select:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
                max-width: 100%;
            }

            .logo {
                width: 4rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .photo-placeholder,
            .photo-preview {
                width: 10rem;
                height: 10rem;
            }

            .navbar {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.5rem;
            }

            .navbar a {
                padding: 0.5rem;
                text-align: center;
            }

            .btn {
                width: 100%;
            }

            .contact-group {
                flex-direction: column;
            }

            .contact-group input[type="text"]:first-child {
                width: 100%;
            }

            .flatpickr-calendar {
                width: 100% !important;
                max-width: 300px;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 1rem;
            }

            h1 {
                font-size: 1.25rem;
            }

            .form-section h2 {
                font-size: 1rem;
            }

            .photo-placeholder,
            .photo-preview {
                width: 8rem;
                height: 8rem;
            }
        }
    </style>
</head>
<body>
    <div class="form-container" role="main" aria-labelledby="form-title">
        <div class="form-header">
            <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
            <h1 id="form-title">Registration Form</h1>
        </div>
        <nav class="navbar" aria-label="Navigation">
            <a href="fillupform.php" class="active" aria-current="page">Registration Form</a>
            <a href="../logout.php">Logout</a>
        </nav>

        <form id="registrationForm" action="" method="POST" enctype="multipart/form-data" aria-label="Registration Form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Photo Upload Section -->
            <div class="form-section">
                <h2>Photo Identification</h2>
                <div class="form-group">
                    <label for="myFile" class="label required">Profile Photo (2x2, JPG/JPEG)</label>
                    <div class="photo-upload-group">
                        <div class="photo-placeholder" id="profilePhotoPreview" aria-live="polite">
                            <img id="profilePhotoImg" class="photo-preview <?php echo !empty($_SESSION['profilePhoto']) && file_exists(str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $_SESSION['profilePhoto'])) ? 'active' : ''; ?>" src="<?php echo htmlspecialchars($_SESSION['profilePhoto'] ?? ''); ?>" alt="Profile Photo Preview" aria-hidden="<?php echo empty($_SESSION['profilePhoto']) || !file_exists(str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $_SESSION['profilePhoto'])) ? 'true' : 'false'; ?>">
                            <?php if (empty($_SESSION['profilePhoto']) || !file_exists(str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $_SESSION['profilePhoto']))): ?>
                                <span>No Photo Uploaded</span>
                            <?php endif; ?>
                        </div>
                        <a href="../photo_config_upload/photo_upload.php" class="btn btn-primary" aria-label="Upload profile photo">Upload Profile Photo</a>
                        <div class="photo-upload-note">JPG/JPEG, Max 2MB, 192x192 pixels</div>
                    </div>
                    <span class="error" id="myFile-error"><?php echo htmlspecialchars($errors['myFile'] ?? ''); ?></span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="idType" class="label required">Valid ID Type</label>
                        <select id="idType" name="idType" required aria-required="true">
                            <option value="" <?php echo empty($_SESSION['idType']) ? 'selected' : ''; ?>>Select ID Type</option>
                            <?php
                            $idTypes = [
                                "Professional Regulation Commission", "Government Service Insurance System", "Passport", "SSS ID", "Drivers License",
                                "Overseas Workers Welfare Administration", "Senior Citizen ID", "NBI Clearance", "Unified Multi-purpose Identification (UMID) Card",
                                "Voters ID", "TIN ID", "PhilHealth ID", "Postal ID", "Seamans Book", "Philippine Identification Card",
                                "Philippine Passport", "Philippine Postal ID", "Police Clearance", "Barangay Clearance", "Integrated Bar of the Philippines",
                                "National ID", "Philippine Identification (PhilID)/ePhilID", "School ID", "Alien Certification"
                            ];
                            foreach ($idTypes as $type) {
                                $selected = (isset($_SESSION['idType']) && $_SESSION['idType'] === $type) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($type) . "\" $selected>" . htmlspecialchars($type) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="error" id="idType-error"><?php echo htmlspecialchars($errors['idType'] ?? ''); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="idNumber" class="label required">ID Number</label>
                        <input type="text" id="idNumber" name="idNumber" required autocomplete="off" aria-required="true" value="<?php echo htmlspecialchars($_SESSION['idNumber'] ?? ''); ?>" placeholder="Example: xxxx-xxxx-xxxx-xxxx" maxlength="50">
                        <span class="error" id="idNumber-error"><?php echo htmlspecialchars($errors['idNumber'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="idPhoto" class="label required">ID Photo (2x2, JPG/JPEG)</label>
                    <div class="photo-upload-group">
                        <div class="photo-placeholder" id="idPhotoPreview" aria-live="polite">
                            <img id="idPhotoImg" class="photo-preview <?php echo !empty($_SESSION['idPhoto']) && file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto'])) ? 'active' : ''; ?>" src="<?php echo !empty($_SESSION['idPhoto']) ? htmlspecialchars($_SESSION['idPhoto']) : ''; ?>" alt="ID Photo Preview" aria-hidden="<?php echo empty($_SESSION['idPhoto']) || !file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto'])) ? 'true' : 'false'; ?>">
                            <?php if (empty($_SESSION['idPhoto']) || !file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto']))): ?>
                                <span>No ID Photo Uploaded</span>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons" id="actionButtons">
                            <button type="button" class="btn btn-primary" id="chooseIdPhotoBtn" aria-label="Choose ID photo file">Select ID Photo</button>
                            <?php if (!empty($_SESSION['idPhoto']) && file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto']))): ?>
                                <button type="button" class="btn btn-danger" id="removeIdPhotoBtn" aria-label="Remove uploaded ID photo">Remove ID Photo</button>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="idPhotoInput" name="idPhoto" accept="image/jpeg,image/jpg" style="display: none;" aria-label="Select ID photo">
                        <input type="hidden" id="compressedImage" name="compressedImage">
                        <div class="photo-upload-note">JPG/JPEG, Max 2MB, 192x192 pixels. Photo uploads automatically after selection.</div>
                        <div class="progress-bar" id="idPhotoProgress"><div></div></div>
                        <div class="loading-spinner" id="idPhotoSpinner" aria-label="Processing"></div>
                        <div class="success-message" id="idPhotoSuccess" role="alert"><?php echo !empty($_SESSION['idPhoto']) && file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto'])) ? 'ID uploaded successfully.' : ''; ?></div>
                    </div>
                    <span class="error" id="idPhoto-error"><?php echo htmlspecialchars($errors['idPhoto'] ?? ''); ?></span>
                </div>
            </div>

            <!-- Personal Information Section -->
            <div class="form-section">
                <h2>Personal Information</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastName" class="label required">Last Name</label>
                        <input type="text" id="lastName" name="lastName" required autocomplete="off" aria-required="true" placeholder="Enter your last name">
                        <span class="error" id="lastName-error"><?php echo htmlspecialchars($errors['lastName'] ?? ''); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="firstName" class="label required">First Name</label>
                        <input type="text" id="firstName" name="firstName" required autocomplete="off" aria-required="true" placeholder="Enter your first name">
                        <span class="error" id="firstName-error"><?php echo htmlspecialchars($errors['firstName'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="middleName" class="label">Middle Name (Optional)</label>
                    <input type="text" id="middleName" name="middleName" autocomplete="off" placeholder="Enter your middle name">
                    <span class="error" id="middleName-error"><?php echo htmlspecialchars($errors['middleName'] ?? ''); ?></span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="gender" class="label required">Gender</label>
                        <select id="gender" name="gender" required aria-required="true">
                            <option value="" selected disabled>Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="error" id="gender-error"><?php echo htmlspecialchars($errors['gender'] ?? ''); ?></span>
                    </div>
                    <div class="form-group" id="othergenderGroup" style="display: none;">
                        <label for="othergender" class="label required">Specify Gender</label>
                        <input type="text" id="othergender" name="othergender" autocomplete="off" placeholder="Specify your gender">
                        <span class="error" id="othergender-error"><?php echo htmlspecialchars($errors['othergender'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthdate" class="label required">Date of Birth</label>
                        <input type="date" id="birthdate" name="birthdate" required autocomplete="off" max="<?php echo date('Y-m-d'); ?>" aria-required="true">
                        <span class="error" id="birthdate-error"><?php echo htmlspecialchars($errors['birthdate'] ?? ''); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="age" class="label required">Age</label>
                        <input type="number" id="age" name="age" required autocomplete="off" min="1" max="120" aria-required="true" placeholder="Auto-calculated">
                        <span class="error" id="age-error"><?php echo htmlspecialchars($errors['age'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="occupation" class="label required">Occupation</label>
                    <input type="text" id="occupation" name="occupation" required autocomplete="off" aria-required="true" placeholder="Enter your occupation">
                    <span class="error" id="occupation-error"><?php echo htmlspecialchars($errors['occupation'] ?? ''); ?></span>
                </div>
            </div>

            <!-- Contact and Address Section -->
            <div class="form-section">
                <h2>Contact and Address</h2>
                <div class="form-group">
                    <label for="address" class="label required">Complete Address</label>
                    <input type="text" id="address" name="address" required autocomplete="off" aria-required="true" placeholder="Enter your full address">
                    <span class="error" id="address-error"><?php echo htmlspecialchars($errors['address'] ?? ''); ?></span>
                </div>
                <div class="form-group">
                    <label for="region" class="label required">Region</label>
                    <input type="text" id="region" name="region" required autocomplete="off" aria-required="true" placeholder="Enter your region">
                    <span class="error" id="region-error"><?php echo htmlspecialchars($errors['region'] ?? ''); ?></span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="label required">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="off" aria-required="true" placeholder="Enter your email">
                        <span class="error" id="email-error"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="contact" class="label required">Contact Number</label>
                        <div class="contact-group">
                            <input type="text" value="+63" readonly aria-label="Country code">
                            <input type="text" id="contact" name="contact" required autocomplete="off" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" placeholder="9123456789" aria-required="true">
                        </div>
                        <span class="error" id="contact-error"><?php echo htmlspecialchars($errors['contact'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <!-- Appointment Details Section -->
            <div class="form-section">
                <h2>Appointment Details</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointmentDate" class="label required">Appointment Date (Thursdays)</label>
                        <input type="text" id="appointmentDate" name="appointmentDate" required autocomplete="off" aria-required="true" placeholder="Select a Thursday">
                        <div class="photo-upload-note">Only Thursdays are selectable</div>
                        <span class="error" id="appointmentDate-error"><?php echo htmlspecialchars($errors['appointmentDate'] ?? ''); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="appointmentTime" class="label required">Appointment Time (5:00 AM - 10:00 PM)</label>
                        <input type="time" id="appointmentTime" name="appointmentTime" required autocomplete="off" min="05:00" max="22:00" aria-required="true">
                        <div class="photo-upload-note">Available from 5:00 AM to 10:00 PM</div>
                        <span class="error" id="appointmentTime-error"><?php echo htmlspecialchars($errors['appointmentTime'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="purpose" class="label required">Purpose of Appointment</label>
                    <input type="text" id="purpose" name="purpose" required autocomplete="off" aria-required="true" placeholder="Enter the purpose of your appointment">
                    <span class="error" id="purpose-error"><?php echo htmlspecialchars($errors['purpose'] ?? ''); ?></span>
                </div>
            </div>

            <!-- Feedback and Submit -->
            <div class="form-group">
                <div class="success-message" id="successMessage" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
                <div class="error-message" id="errorMessage" role="alert"><?php echo htmlspecialchars($errors['general'] ?? ''); ?></div>
                <div class="loading-spinner" id="loadingSpinner" aria-label="Loading"></div>
            </div>
            <?php if (!empty($debug_log)): ?>
                <div class="debug-log">
                    <h3>Debug Log:</h3>
                    <?php foreach ($debug_log as $log): ?>
                        <p><?php echo htmlspecialchars($log); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-group" style="text-align: center;">
                <button type="submit" name="submit_appointment" value="1" class="btn btn-primary" aria-label="Submit appointment form">Submit Appointment</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script>
        // Utility Functions
        const debounce = (func, wait) => {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        };

        const showError = (id, message) => {
            const errorEl = document.getElementById(`${id}-error`);
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = message ? 'block' : 'none';
                errorEl.setAttribute('aria-live', 'assertive');
            }
        };

        const showSuccess = (id, message) => {
            const successEl = document.getElementById(id);
            if (successEl) {
                successEl.textContent = message;
                successEl.style.display = message ? 'block' : 'none';
                successEl.setAttribute('aria-live', 'assertive');
            }
        };

        // Main Logic
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('registrationForm');
            const elements = {
                gender: document.getElementById('gender'),
                othergenderGroup: document.getElementById('othergenderGroup'),
                birthdate: document.getElementById('birthdate'),
                age: document.getElementById('age'),
                contact: document.getElementById('contact'),
                appointmentDate: document.getElementById('appointmentDate'),
                appointmentTime: document.getElementById('appointmentTime'),
                successMessage: document.getElementById('successMessage'),
                errorMessage: document.getElementById('errorMessage'),
                loadingSpinner: document.getElementById('loadingSpinner'),
                idType: document.getElementById('idType'),
                idNumber: document.getElementById('idNumber'),
                idPhotoInput: document.getElementById('idPhotoInput'),
                idPhotoPreview: document.getElementById('idPhotoImg'),
                chooseIdPhotoBtn: document.getElementById('chooseIdPhotoBtn'),
                removeIdPhotoBtn: document.getElementById('removeIdPhotoBtn'),
                idPhotoSpinner: document.getElementById('idPhotoSpinner'),
                idPhotoSuccess: document.getElementById('idPhotoSuccess'),
                idPhotoProgress: document.getElementById('idPhotoProgress'),
                compressedImage: document.getElementById('compressedImage'),
                actionButtons: document.getElementById('actionButtons')
            };

            // Initialize debug log visibility
            const debugLog = document.querySelector('.debug-log');
            if (debugLog?.textContent.trim()) {
                debugLog.style.display = 'block';
            }

            // Initialize Flatpickr
            flatpickr('#appointmentDate', {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                disable: [date => date.getDay() !== 4],
                onDayCreate: (dObj, dStr, fp, dayElem) => {
                    if (new Date(dayElem.dateObj).getDay() === 4) {
                        dayElem.classList.add('thursday');
                    }
                }
            });

            // Toggle Other Gender
            const toggleOtherGender = () => {
                elements.othergenderGroup.style.display = elements.gender.value === 'Other' ? 'block' : 'none';
            };
            elements.gender.addEventListener('change', toggleOtherGender);
            toggleOtherGender();

            // Auto-calculate Age
            elements.birthdate.addEventListener('change', () => {
                const birthdate = new Date(elements.birthdate.value);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }
                elements.age.value = age >= 0 ? age : '';
                validateField('age', elements.age.value);
            });

            // Restrict Contact Input
            elements.contact.addEventListener('input', e => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 10);
                validateField('contact', e.target.value);
            });

            // Restrict Appointment Time
            elements.appointmentTime.addEventListener('input', () => {
                validateField('appointmentTime', elements.appointmentTime.value);
            });

            // Image Compression
            const compressImage = (file, maxSizeMB, targetSize, callback) => {
                const maxSizeBytes = maxSizeMB * 1024 * 1024;
                elements.idPhotoSpinner.style.display = 'block';
                elements.idPhotoProgress.style.display = 'block';
                elements.idPhotoProgress.querySelector('div').style.width = '10%';

                const img = new Image();
                const reader = new FileReader();
                reader.onload = e => {
                    img.src = e.target.result;
                    img.onload = () => {
                        try {
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            const size = Math.min(img.width, img.height);
                            canvas.width = targetSize;
                            canvas.height = targetSize;
                            const offsetX = (img.width - size) / 2;
                            const offsetY = (img.height - size) / 2;
                            ctx.drawImage(img, offsetX, offsetY, size, size, 0, 0, targetSize, targetSize);

                            let quality = 0.85;
                            let compressedDataUrl;
                            do {
                                compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                                quality -= 0.05;
                                elements.idPhotoProgress.querySelector('div').style.width = `${100 - (quality * 100)}%`;
                            } while (compressedDataUrl.length / 4 * 3 > maxSizeBytes && quality > 0.1);

                            fetch(compressedDataUrl)
                                .then(res => res.blob())
                                .then(blob => {
                                    const compressedFile = new File([blob], file.name, { type: 'image/jpeg' });
                                    callback(compressedFile, compressedDataUrl);
                                    elements.idPhotoSpinner.style.display = 'none';
                                    elements.idPhotoProgress.style.display = 'none';
                                })
                                .catch(() => {
                                    showError('idPhoto', 'Error compressing image.');
                                    elements.idPhotoSpinner.style.display = 'none';
                                    elements.idPhotoProgress.style.display = 'none';
                                });
                        } catch {
                            showError('idPhoto', 'Error processing image.');
                            elements.idPhotoSpinner.style.display = 'none';
                            elements.idPhotoProgress.style.display = 'none';
                        }
                    };
                    img.onerror = () => {
                        showError('idPhoto', 'Invalid image file. Please upload a valid JPEG.');
                        elements.idPhotoSpinner.style.display = 'none';
                        elements.idPhotoProgress.style.display = 'none';
                    };
                };
                reader.onerror = () => {
                    showError('idPhoto', 'Error reading file.');
                    elements.idPhotoSpinner.style.display = 'none';
                    elements.idPhotoProgress.style.display = 'none';
                };
                reader.readAsDataURL(file);
            };

            // Add Remove ID Photo Button
            const addRemoveButton = () => {
                const existingBtn = document.getElementById('removeIdPhotoBtn');
                if (existingBtn) {
                    existingBtn.remove();
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-danger';
                btn.id = 'removeIdPhotoBtn';
                btn.textContent = 'Remove ID Photo';
                btn.setAttribute('aria-label', 'Remove uploaded ID photo');
                elements.actionButtons.appendChild(btn);
                btn.addEventListener('click', handleRemoveIdPhoto);
                elements.removeIdPhotoBtn = btn;
            };

            // ID Photo Selection and Auto-Upload
            elements.chooseIdPhotoBtn.addEventListener('click', () => elements.idPhotoInput.click());

            elements.idPhotoInput.addEventListener('change', e => {
                const file = e.target.files[0];
                showError('idPhoto', '');
                showSuccess('idPhotoSuccess', '');

                if (file) {
                    if (!['image/jpeg', 'image/jpg'].includes(file.type)) {
                        showError('idPhoto', 'Please upload a .jpg or .jpeg file.');
                        return;
                    }

                    if (!elements.idType.value) {
                        showError('idType', 'Please select a valid ID type.');
                        return;
                    }
                    if (!elements.idNumber.value || !/^[A-Za-z0-9-]{1,50}$/.test(elements.idNumber.value)) {
                        showError('idNumber', 'ID number must be alphanumeric (up to 50 characters).');
                        return;
                    }

                    compressImage(file, 0.3, 192, (compressedFile, dataUrl) => {
                        elements.idPhotoPreview.src = dataUrl;
                        elements.idPhotoPreview.classList.add('active');
                        elements.idPhotoPreview.setAttribute('aria-hidden', 'false');

                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        elements.idPhotoInput.files = dataTransfer.files;
                        elements.compressedImage.value = dataUrl;

                        // Auto-upload
                        const formData = new FormData(form);
                        formData.append('upload_id_photo', '1');

                        elements.idPhotoSpinner.style.display = 'block';
                        fetch('', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(response => response.json())
                            .then(data => {
                                elements.idPhotoSpinner.style.display = 'none';
                                if (data.success) {
                                    showSuccess('idPhotoSuccess', data.message);
                                    elements.idPhotoPreview.src = data.idPhoto;
                                    elements.idPhotoPreview.classList.add('active');
                                    elements.idPhotoPreview.setAttribute('aria-hidden', 'false');
                                    addRemoveButton();
                                    // Auto-refresh after successful upload
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                } else {
                                    showError('idPhoto', data.errors?.idPhoto || 'Failed to upload ID.');
                                    if (data.errors?.idType) showError('idType', data.errors.idType);
                                    if (data.errors?.idNumber) showError('idNumber', data.errors.idNumber);
                                    elements.idPhotoPreview.src = '';
                                    elements.idPhotoPreview.classList.remove('active');
                                    elements.idPhotoPreview.setAttribute('aria-hidden', 'true');
                                    elements.idPhotoInput.value = '';
                                    elements.compressedImage.value = '';
                                }
                            })
                            .catch(() => {
                                elements.idPhotoSpinner.style.display = 'none';
                                showError('idPhoto', 'Network error.');
                                elements.idPhotoPreview.src = '';
                                elements.idPhotoPreview.classList.remove('active');
                                elements.idPhotoPreview.setAttribute('aria-hidden', 'true');
                                elements.idPhotoInput.value = '';
                                elements.compressedImage.value = '';
                            });
                    });
                }
            });

            // Remove ID Photo
            const handleRemoveIdPhoto = () => {
                const formData = new FormData();
                formData.append('removeIdPhoto', 'true');
                formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
                elements.idPhotoSpinner.style.display = 'block';
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        elements.idPhotoSpinner.style.display = 'none';
                        if (data.success) {
                            showSuccess('idPhotoSuccess', data.message);
                            elements.idPhotoPreview.src = '';
                            elements.idPhotoPreview.classList.remove('active');
                            elements.idPhotoPreview.setAttribute('aria-hidden', 'true');
                            elements.idPhotoInput.value = '';
                            elements.compressedImage.value = '';
                            elements.idType.value = '';
                            elements.idNumber.value = '';
                            const removeBtn = document.getElementById('removeIdPhotoBtn');
                            if (removeBtn) removeBtn.remove();
                            elements.removeIdPhotoBtn = null;
                        } else {
                            showError('idPhoto', 'Error removing ID photo.');
                        }
                    })
                    .catch(() => {
                        elements.idPhotoSpinner.style.display = 'none';
                        showError('idPhoto', 'Network error.');
                    });
            };

            if (elements.removeIdPhotoBtn) {
                elements.removeIdPhotoBtn.addEventListener('click', handleRemoveIdPhoto);
            }

            // Real-time Validation
            const validateField = (id, value) => {
                switch (id) {
                    case 'lastName':
                    case 'firstName':
                    case 'occupation':
                    case 'address':
                    case 'region':
                    case 'purpose':
                        showError(id, value.trim() ? '' : `${id.charAt(0).toUpperCase() + id.slice(1)} is required.`);
                        break;
                    case 'email':
                        showError(id, /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email.');
                        break;
                    case 'contact':
                        showError(id, /^[0-9]{10}$/.test(value) ? '' : 'Please enter a valid 10-digit number.');
                        break;
                    case 'age':
                        showError(id, (value >= 1 && value <= 120) ? '' : 'Age must be between 1 and 120.');
                        break;
                    case 'idNumber':
                        showError(id, /^[A-Za-z0-9-]{1,50}$/.test(value) ? '' : 'ID number must be alphanumeric (up to 50 characters).');
                        break;
                    case 'gender':
                        showError(id, value ? '' : 'Please select a gender.');
                        if (value === 'Other') {
                            const otherValue = document.getElementById('othergender').value;
                            showError('othergender', otherValue.trim() ? '' : 'Please specify your gender.');
                        }
                        break;
                    case 'idType':
                        showError(id, value ? '' : 'Please select a valid ID type.');
                        break;
                    case 'appointmentTime':
                        showError(id, value && value >= '05:00' && value <= '22:00' ? '' : 'Time must be between 5:00 AM and 10:00 PM.');
                        break;
                }
            };

            ['lastName', 'firstName', 'occupation', 'address', 'region', 'purpose', 'email', 'contact', 'age', 'idNumber', 'gender', 'idType', 'appointmentTime'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', debounce(e => validateField(id, e.target.value), 300));
                }
            });

            // Form Validation
            const validateForm = () => {
                let isValid = true;
                const fields = [
                    { id: 'lastName', message: 'Last name is required.' },
                    { id: 'firstName', message: 'First name is required.' },
                    { id: 'gender', message: 'Please select a gender.' },
                    { id: 'birthdate', message: 'Date of birth is required.' },
                    { id: 'age', message: 'Age is required.' },
                    { id: 'occupation', message: 'Occupation is required.' },
                    { id: 'address', message: 'Address is required.' },
                    { id: 'region', message: 'Region is required.' },
                    { id: 'email', message: 'Email is required.' },
                    { id: 'contact', message: 'Contact number is required.' },
                    { id: 'appointmentDate', message: 'Appointment date is required.' },
                    { id: 'appointmentTime', message: 'Appointment time is required.' },
                    { id: 'purpose', message: 'Purpose is required.' },
                    { id: 'idType', message: 'ID type is required.' },
                    { id: 'idNumber', message: 'ID number is required.' }
                ];

                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    if (!input || !input.value.trim()) {
                        showError(field.id, field.message);
                        isValid = false;
                    } else {
                        validateField(field.id, input.value);
                        if (document.getElementById(`${field.id}-error`).style.display === 'block') {
                            isValid = false;
                        }
                    }
                });

                if (elements.gender.value === 'Other') {
                    const othergenderInput = document.getElementById('othergender');
                    if (!othergenderInput.value.trim()) {
                        showError('othergender', 'Please specify your gender.');
                        isValid = false;
                    }
                }

                <?php if (empty($_SESSION['profilePhoto']) || !file_exists(str_replace(PROFILE_RELATIVE_UPLOAD_DIR, PROFILE_PHYSICAL_UPLOAD_DIR, $_SESSION['profilePhoto']))): ?>
                    showError('myFile', 'Please upload a profile photo.');
                    isValid = false;
                <?php else: ?>
                    showError('myFile', '');
                <?php endif; ?>

                <?php if (empty($_SESSION['idPhoto']) || !file_exists(str_replace(RELATIVE_UPLOAD_DIR, PHYSICAL_UPLOAD_DIR, $_SESSION['idPhoto']))): ?>
                    showError('idPhoto', 'Please upload an ID photo.');
                    isValid = false;
                <?php else: ?>
                    showError('idPhoto', '');
                <?php endif; ?>

                return isValid;
            };

            // Form Submission
            form.addEventListener('submit', e => {
                if (!e.submitter || e.submitter.name !== 'submit_appointment') {
                    return;
                }
                e.preventDefault();

                if (!validateForm()) {
                    elements.errorMessage.textContent = 'Please correct the errors in the form.';
                    elements.errorMessage.style.display = 'block';
                    return;
                }

                if (!confirm('Are you sure you want to submit this appointment?')) {
                    return;
                }

                elements.loadingSpinner.style.display = 'block';
                elements.successMessage.style.display = 'none';
                elements.errorMessage.style.display = 'none';

                const formData = new FormData(form);
                formData.append('submit_appointment', '1');

                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        elements.loadingSpinner.style.display = 'none';
                        if (data.success) {
                            showSuccess('successMessage', data.message);
                            setTimeout(() => {
                                window.location.href = '../dashboard/dashboard.php';
                            }, 2000);
                        } else {
                            showError('errorMessage', data.error || 'Failed to submit appointment.');
                            if (data.errors) {
                                Object.keys(data.errors).forEach(key => showError(key, data.errors[key]));
                            }
                            if (data.debug) {
                                console.log('Debug:', data.debug);
                            }
                        }
                    })
                    .catch(error => {
                        elements.loadingSpinner.style.display = 'none';
                        showError('errorMessage', 'An error occurred during submission.');
                        console.error('Error:', error);
                    });
            });

            // Initialize Feedback
            if (elements.successMessage.textContent) {
                elements.successMessage.style.display = 'block';
            }
            if (elements.errorMessage.textContent) {
                elements.errorMessage.style.display = 'block';
            }
        });
    </script>
</body>
</html>