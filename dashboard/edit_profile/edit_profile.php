<?php
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['status_Account']) || !isset($_SESSION['email']) || $_SESSION['status_Account'] !== 'logged_in') {
    header("Location: ../../index.php");
    exit();
}

include '../../database/db.php';

try {
    // Fetch user data
    $email = $_SESSION['email'];
    $stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'] ?? null;
    $stmt->close();

    if (!$user_id) {
        header("Location: ../../index.php");
        exit();
    }

    // Fetch appointment data
    $stmt = $connection->prepare("SELECT * FROM appointments WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        header("Location: ../../fillupform/fillupform.php");
        exit();
    }

    $errors = [];
    $success_message = '';
    $debug_log = [];

    // Define base paths for file uploads and display
    $base_server_path = realpath(__DIR__ . '/../../ProfileImage/image/') . '/';
    $base_relative_url = '../ProfileImage/image/'; // Used for database storage
    $base_display_url = '/ProfileImage/image/'; // Absolute URL for image display
    $profile_photo_dir = $base_server_path . 'Profile_Photo/';
    $id_photo_dir = $base_server_path . 'IdPhoto/';

    // Ensure directories exist
    if (!is_dir($profile_photo_dir)) {
        if (!mkdir($profile_photo_dir, 0755, true)) {
            $errors[] = "Failed to create profile photo directory.";
            $debug_log[] = "Failed to create directory: $profile_photo_dir";
        } else {
            $debug_log[] = "Created profile photo directory: $profile_photo_dir";
        }
    }
    if (!is_dir($id_photo_dir)) {
        if (!mkdir($id_photo_dir, 0755, true)) {
            $errors[] = "Failed to create ID photo directory.";
            $debug_log[] = "Failed to create directory: $id_photo_dir";
        } else {
            $debug_log[] = "Created ID photo directory: $id_photo_dir";
        }
    }

    // Function to get next increment for photo filename
    function getNextIncrement($connection, $user_id, $type, $prefix) {
        $stmt = $connection->prepare("SELECT profile_photo, id_photo FROM appointments WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $max_increment = 0;
        while ($row = $result->fetch_assoc()) {
            $path = $type === 'profile' ? $row['profile_photo'] : $row['id_photo'];
            if ($path) {
                $filename = basename($path);
                if (preg_match("/^{$prefix}_{$user_id}_(\d+)\./", $filename, $matches)) {
                    $increment = (int)$matches[1];
                    $max_increment = max($max_increment, $increment);
                }
            }
        }
        $stmt->close();
        return $max_increment + 1;
    }

    // Validate and set current photo URLs from database paths
    $profile_photo_url = '';
    $id_photo_url = '';
    if ($appointment['profile_photo']) {
        $db_profile_path = $appointment['profile_photo'];
        // Handle legacy or incorrect paths
        if (strpos($db_profile_path, '../ProfileImage/image/') !== 0 && strpos($db_profile_path, '/ProfileImage/image/') !== 0) {
            $debug_log[] = "Non-standard profile photo path detected: '$db_profile_path'";
            $filename = basename($db_profile_path);
            $db_profile_path = "../ProfileImage/image/Profile_Photo/$filename";
        }
        // Convert database path to display URL
        $filename = basename($db_profile_path);
        $profile_photo_path = $base_server_path . 'Profile_Photo/' . $filename;
        if (file_exists($profile_photo_path)) {
            $profile_photo_url = $base_display_url . 'Profile_Photo/' . $filename;
            $debug_log[] = "Profile photo found at: '$profile_photo_path', URL: '$profile_photo_url'";
        } else {
            $debug_log[] = "Profile photo not found at: '$profile_photo_path'";
        }
    }
    if ($appointment['id_photo']) {
        $db_id_path = $appointment['id_photo'];
        // Handle legacy or incorrect paths
        if (strpos($db_id_path, '../ProfileImage/image/') !== 0 && strpos($db_id_path, '/ProfileImage/image/') !== 0) {
            $debug_log[] = "Non-standard ID photo path detected: '$db_id_path'";
            $filename = basename($db_id_path);
            $db_id_path = "../ProfileImage/image/IdPhoto/$filename";
        }
        // Convert database path to display URL
        $filename = basename($db_id_path);
        $id_photo_path = $base_server_path . 'IdPhoto/' . $filename;
        if (file_exists($id_photo_path)) {
            $id_photo_url = $base_display_url . 'IdPhoto/' . $filename;
            $debug_log[] = "ID photo found at: '$id_photo_path', URL: '$id_photo_url'";
        } else {
            $debug_log[] = "ID photo not found at: '$id_photo_path'";
        }
    }

    // List of valid regions
    $valid_regions = [
        'Region I – Ilocos Region',
        'Region II – Cagayan Valley',
        'Region III – Central Luzon',
        'Region IV-A – CALABARZON',
        'MIMAROPA Region',
        'Region V – Bicol Region',
        'Region VI – Western Visayas',
        'Region VII – Central Visayas',
        'Region VIII – Eastern Visayas',
        'Region IX – Zamboanga Peninsula',
        'Region X – Northern Mindanao',
        'Region XI – Davao Region',
        'Region XII – SOCCSKSARGEN',
        'Region XIII – Caraga',
        'NCR – National Capital Region',
        'CAR – Cordillera Administrative Region',
        'BARMM – Bangsamoro Autonomous Region in Muslim Mindanao',
        'NIR – Negros Island Region'
    ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and validate input
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $other_gender = filter_input(INPUT_POST, 'other_gender', FILTER_SANITIZE_STRING);
        $birthdate = filter_input(INPUT_POST, 'birthdate', FILTER_SANITIZE_STRING);
        $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $region = filter_input(INPUT_POST, 'region', FILTER_SANITIZE_STRING);
        $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
        $id_number = filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($first_name)) $errors[] = "First name is required.";
        if (empty($last_name)) $errors[] = "Last name is required.";
        if (empty($gender)) $errors[] = "Gender is required.";
        if ($gender === 'Other' && empty($other_gender)) $errors[] = "Please specify gender.";
        if (empty($birthdate)) $errors[] = "Date of birth is required.";
        if (empty($occupation)) $errors[] = "Occupation is required.";
        if (empty($address)) $errors[] = "Address is required.";
        if (empty($region) || !in_array($region, $valid_regions)) $errors[] = "Please select a valid region.";
        if (empty($contact) || !preg_match("/^[0-9]{10}$/", $contact)) $errors[] = "Valid 10-digit contact number is required.";

        // Calculate age
        $birthdate_obj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthdate_obj)->y;

        // Handle profile photo upload
        $profile_photo = $appointment['profile_photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file_type = mime_content_type($_FILES['profile_photo']['tmp_name']);
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Profile photo must be a JPEG, PNG, or GIF image.";
                } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                    $errors[] = "Profile photo must be less than 5MB.";
                } else {
                    $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $increment = getNextIncrement($connection, $user_id, 'profile', 'profile');
                    $profile_filename = "profile_{$user_id}_{$increment}.{$ext}";
                    $profile_destination = $profile_photo_dir . $profile_filename;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $profile_destination)) {
                        $profile_photo = $base_relative_url . "Profile_Photo/{$profile_filename}";
                        $_SESSION['profilePhoto'] = $profile_photo;
                        $debug_log[] = "Profile photo uploaded to: '$profile_destination', stored as: '$profile_photo'";
                    } else {
                        $errors[] = "Failed to upload profile photo.";
                        $debug_log[] = "Failed to move profile photo to: '$profile_destination'";
                    }
                }
            } else {
                $errors[] = "Profile photo upload error: " . $_FILES['profile_photo']['error'];
            }
        }

        // Handle ID photo upload
        $id_photo = $appointment['id_photo'];
        if (isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
                $file_type = mime_content_type($_FILES['id_photo']['tmp_name']);
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "ID photo must be a JPEG, PNG, or GIF image.";
                } elseif ($_FILES['id_photo']['size'] > 5 * 1024 * 1024) {
                    $errors[] = "ID photo must be less than 5MB.";
                } else {
                    $ext = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
                    $increment = getNextIncrement($connection, $user_id, 'id', 'id');
                    $id_filename = "id_{$user_id}_{$increment}.{$ext}";
                    $id_destination = $id_photo_dir . $id_filename;
                    if (move_uploaded_file($_FILES['id_photo']['tmp_name'], $id_destination)) {
                        $id_photo = $base_relative_url . "IdPhoto/{$id_filename}";
                        $debug_log[] = "ID photo uploaded to: '$id_destination', stored as: '$id_photo'";
                    } else {
                        $errors[] = "Failed to upload ID photo.";
                        $debug_log[] = "Failed to move ID photo to: '$id_destination'";
                    }
                }
            } else {
                $errors[] = "ID photo upload error: " . $_FILES['id_photo']['error'];
            }
        }

        // Update database if no errors
        if (empty($errors)) {
            $stmt = $connection->prepare("
                UPDATE appointments SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    gender = ?,
                    other_gender = ?,
                    birthdate = ?,
                    age = ?,
                    occupation = ?,
                    address = ?,
                    region = ?,
                    contact = ?,
                    id_number = ?,
                    profile_photo = ?,
                    id_photo = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param(
                "ssssssisssssssi",
                $first_name,
                $middle_name,
                $last_name,
                $gender,
                $other_gender,
                $birthdate,
                $age,
                $occupation,
                $address,
                $region,
                $contact,
                $id_number,
                $profile_photo,
                $id_photo,
                $user_id
            );
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully.";
                $debug_log[] = "Profile updated for user_id: $user_id";
            } else {
                $errors[] = "Failed to update profile: " . $stmt->error;
                $debug_log[] = "Database update error: " . $stmt->error;
            }
            $stmt->close();
        }

        // Update photo URLs after successful upload
        if (empty($errors) && $profile_photo !== $appointment['profile_photo']) {
            $profile_photo_path = $base_server_path . 'Profile_Photo/' . $profile_filename;
            if (file_exists($profile_photo_path)) {
                $profile_photo_url = $base_display_url . 'Profile_Photo/' . $profile_filename;
                $debug_log[] = "Profile photo URL updated to: '$profile_photo_url'";
            } else {
                $debug_log[] = "Profile photo not found after upload at: '$profile_photo_path'";
            }
        }
        if (empty($errors) && $id_photo !== $appointment['id_photo']) {
            $id_photo_path = $base_server_path . 'IdPhoto/' . $id_filename;
            if (file_exists($id_photo_path)) {
                $id_photo_url = $base_display_url . 'IdPhoto/' . $id_filename;
                $debug_log[] = "ID photo URL updated to: '$id_photo_url'";
            } else {
                $debug_log[] = "ID photo not found after upload at: '$id_photo_path'";
            }
        }
    }

    // Re-fetch appointment data to display updated values
    $stmt = $connection->prepare("SELECT * FROM appointments WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

} catch (Exception $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $debug_log[] = "Exception: " . $e->getMessage();
} finally {
    $connection->close();
}
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
    <link rel="icon" type="image/x-icon" href="/image/icons/logo1.ico">
    <title>Edit Profile</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        :root {
            --primary-color: #003366;
            --primary-hover: #002244;
            --background-color: #f7f7f7;
            --card-background: #ffffff;
            --text-color: #333333;
            --border-color: #cccccc;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --success-color: #2e7d32;
            --error-color: #d32f2f;
            --transition-speed: 0.2s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: var(--background-color);
            min-height: 100vh;
            display: flex;
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        .sidebar {
            width: 250px;
            background: var(--primary-color);
            color: #ffffff;
            padding: 20px;
            height: 100vh;
            position: fixed;
            box-shadow: var(--shadow);
            transform: translateX(0);
            transition: transform var(--transition-speed) ease;
            z-index: 1000;
        }

        .sidebar .logo {
            width: 80px;
            margin: 0 auto 20px;
            display: block;
            transition: transform var(--transition-speed);
        }

        .sidebar .logo:hover {
            transform: scale(1.1);
        }

        .sidebar h2 {
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 30px;
            opacity: 0;
            animation: slideIn 0.5s ease forwards 0.2s;
        }

        .sidebar a {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: background var(--transition-speed), transform var(--transition-speed);
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary-hover);
            transform: translateX(5px);
        }

        .sidebar .close-sidebar {
            display: none;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 24px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 10px;
            transition: color var(--transition-speed);
        }

        .sidebar .close-sidebar:hover {
            color: #f5f6fa;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            transition: margin-left var(--transition-speed), width var(--transition-speed);
        }

        .menu-toggle {
            background: var(--primary-color);
            color: #ffffff;
            border: none;
            padding: 5px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 4px;
            transition: background var(--transition-speed);
            display: none;
        }

        .menu-toggle:hover {
            background: var(--primary-hover);
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 30px;
            opacity: 0;
            animation: slideIn 0.5s ease forwards 0.3s;
        }

        .dashboard-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            flex-grow: 1;
        }

        .card {
            background: var(--card-background);
            border-radius: 6px;
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
            opacity: 0;
            transform: translateY(20px);
            animation: cardAppear 0.5s ease forwards;
            animation-delay: 0.4s;
        }

        @keyframes cardAppear {
            to { opacity: 1; transform: translateY(0); }
        }

        .card h2 {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            color: var(--text-color);
            background: #ffffff;
            transition: border-color var(--transition-speed);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .form-group select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%23333333" d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input[type="file"] {
            padding: 3px;
        }

        .photo-preview-container {
            margin-bottom: 10px;
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            display: block;
        }

        .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 4px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            color: var(--text-color);
            text-align: center;
        }

        .error {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        .success {
            background: #e8f5e9;
            color: var(--success-color);
            padding: 15px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .submit-btn {
            background: var(--primary-color);
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition-speed);
        }

        .submit-btn:hover {
            background: var(--primary-hover);
        }

        .submit-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .debug-log {
            font-size: 12px;
            color: #555;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            display: none;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                width: calc(100% - 200px);
                padding: 20px;
            }

            .dashboard-header h1 {
                font-size: 20px;
            }

            .card {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                position: fixed;
                width: 250px;
                height: 100vh;
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar .close-sidebar {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 10px;
            }

            .main-content.sidebar-active {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            .menu-toggle {
                display: inline-block;
                padding: 5px;
                font-size: 16px;
            }

            .dashboard-header {
                padding: 10px;
                gap: 8px;
            }

            .dashboard-header h1 {
                font-size: 14px;
                line-height: 1.2;
            }

            .card {
                padding: 10px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 13px;
                padding: 8px;
            }

            .form-group select {
                padding-right: 25px;
                background-position: right 8px center;
            }

            .form-group label {
                font-size: 13px;
            }

            .photo-preview,
            .photo-placeholder {
                width: 100px;
                height: 100px;
                font-size: 10px;
            }

            .submit-btn {
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <button class="close-sidebar" aria-label="Close Sidebar">
            <i class='bx bx-x'></i>
        </button>
        <img src="/image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h2>Dashboard</h2>
        <a href="../dashboard.php">Dashboard</a>
        <a href="#" class="active" aria-current="page">Edit Profile</a>
        <a href="../../logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="dashboard-header">
            <button class="menu-toggle" aria-label="Toggle Sidebar" aria-expanded="false">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Edit Profile</h1>
        </div>
        <div class="card">
            <h2>Profile Information</h2>
            <?php if ($success_message): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($appointment['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($appointment['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($appointment['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="Male" <?php echo ($appointment['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($appointment['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($appointment['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="other_gender_group" style="display: <?php echo ($appointment['gender'] ?? '') === 'Other' ? 'block' : 'none'; ?>;">
                        <label for="other_gender">Specify Gender *</label>
                        <input type="text" id="other_gender" name="other_gender" value="<?php echo htmlspecialchars($appointment['other_gender'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="birthdate">Date of Birth *</label>
                        <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($appointment['birthdate'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="occupation">Occupation *</label>
                        <input type="text" id="occupation" name="occupation" value="<?php echo htmlspecialchars($appointment['occupation'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($appointment['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="region">Region *</label>
                        <select id="region" name="region" required aria-describedby="region-help">
                            <option value="" disabled <?php echo empty($appointment['region']) ? 'selected' : ''; ?>>Select a Region</option>
                            <?php foreach ($valid_regions as $region_option): ?>
                                <option value="<?php echo htmlspecialchars($region_option); ?>" <?php echo ($appointment['region'] ?? '') === $region_option ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="region-help" class="form-text">Please select your region from the list.</small>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number * (+63)</label>
                        <input type="number" id="contact" name="contact" value="<?php echo htmlspecialchars($appointment['contact'] ?? ''); ?>" pattern="[0-9]{10}" maxlength="10" required>
                    </div>
                    <div class="form-group">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($appointment['id_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile_photo">Profile Photo (JPEG, PNG, GIF, max 5MB)</label>
                        <div class="photo-preview-container">
                            <?php if ($profile_photo_url): ?>
                                <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile Photo Preview" class="photo-preview" id="profile_preview">
                            <?php else: ?>
                                <div class="photo-placeholder" id="profile_preview">No Photo Available</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <div class="form-group">
                        <label for="id_photo">ID Photo (JPEG, PNG, GIF, max 5MB)</label>
                        <div class="photo-preview-container">
                            <?php if ($id_photo_url): ?>
                                <img src="<?php echo htmlspecialchars($id_photo_url); ?>" alt="ID Photo Preview" class="photo-preview" id="id_preview">
                            <?php else: ?>
                                <div class="photo-placeholder" id="id_preview">No Photo Available</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="id_photo" name="id_photo" accept="image/jpeg,image/png,image/gif">
                    </div>
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize debug log visibility
            const debugLog = document.querySelector('.debug-log');
            if (debugLog?.textContent.trim()) {
                debugLog.style.display = 'block';
            }

            // Sidebar toggle functionality
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            const closeSidebar = document.querySelector('.close-sidebar');
            const mainContent = document.querySelector('.main-content');
            const isMobile = window.matchMedia('(max-width: 576px)').matches;

            function toggleSidebar() {
                const isActive = sidebar.classList.toggle('active');
                mainContent.classList.toggle('sidebar-active', isActive);
                menuToggle.setAttribute('aria-expanded', isActive);
            }

            function closeSidebarOnMobile() {
                if (window.matchMedia('(max-width: 576px)').matches) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('sidebar-active');
                    menuToggle.setAttribute('aria-expanded', 'false');
                }
            }

            menuToggle.addEventListener('click', toggleSidebar);
            closeSidebar.addEventListener('click', closeSidebarOnMobile);

            // Auto-close sidebar on navigation link click for mobile
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('click', closeSidebarOnMobile);
            });

            // Show/hide other gender field
            const genderSelect = document.querySelector('#gender');
            const otherGenderGroup = document.querySelector('#other_gender_group');
            const otherGenderInput = document.querySelector('#other_gender');

            genderSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherGenderGroup.style.display = 'block';
                    otherGenderInput.setAttribute('required', 'required');
                } else {
                    otherGenderGroup.style.display = 'none';
                    otherGenderInput.removeAttribute('required');
                }
            });

            // Photo preview functionality
            const profilePhotoInput = document.querySelector('#profile_photo');
            const profilePreview = document.querySelector('#profile_preview');
            const idPhotoInput = document.querySelector('#id_photo');
            const idPreview = document.querySelector('#id_preview');

            profilePhotoInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                        profilePreview.classList.remove('photo-placeholder');
                        profilePreview.classList.add('photo-preview');
                    };
                    reader.readAsDataURL(file);
                }
            });

            idPhotoInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        idPreview.src = e.target.result;
                        idPreview.classList.remove('photo-placeholder');
                        idPreview.classList.add('photo-preview');
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Contact number formatting
            const contactInput = document.querySelector('#contact');
            contactInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
            });

            // Close sidebar with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMobile && sidebar.classList.contains('active')) {
                    closeSidebarOnMobile();
                }
            });

            // Prevent back button issues
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    window.location.reload();
                }
            });

            // Close sidebar on initial load for mobile
            if (isMobile) {
                closeSidebarOnMobile();
            }
        });
    </script>
</body>
</html>