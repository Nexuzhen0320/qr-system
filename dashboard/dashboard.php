<?php
include '../database/db.php';
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

// Fetch data
$email = $_SESSION['email'];
$stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$stmt->close();

// Check if user has submitted an appointment
$stmt = $connection->prepare("SELECT * FROM appointments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

// Redirect to fillupform.php if no appointment exists
if (!$appointment) {
    header("Location: ../fillupform/fillupform.php");
    exit();
}

// Validate profile photo
$is_valid_photo = false;
$profile_photo = $appointment['profile_photo'] ?? '';
$debug_log = [];

if ($profile_photo) {
    // Check if the profile photo is a valid base64-encoded image
    if (preg_match('/^data:image\/(jpeg|png);base64,/', $profile_photo)) {
        $is_valid_photo = true;
    } else {
        $debug_log[] = "Profile photo is not a valid base64 image: '$profile_photo'";
    }
} else {
    $debug_log[] = "Profile photo is empty or not set.";
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
    <title>Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        :root {
            --primary-color: #003087;
            --primary-hover: #00205b;
            --secondary-color: #f5f6fa;
            --accent-color: #007bff;
            --text-color: #333333;
            --border-color: #e0e0e0;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
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
        }

        .sidebar .logo {
            width: 80px;
            margin: 0 auto 20px;
            display: block;
            transition: transform var(--transition-speed) ease;
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
            border-radius: 5px;
            font-size: 14px;
            position: relative;
            overflow: hidden;
            transition: background var(--transition-speed), transform var(--transition-speed);
        }

        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .sidebar a:hover::before {
            left: 100%;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary-hover);
            transform: translateX(5px);
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            opacity: 0;
            animation: slideIn 0.5s ease forwards 0.3s;
        }

        .dashboard-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
            opacity: 0;
            transform: translateY(20px);
            animation: cardAppear 0.5s ease forwards;
            animation-delay: 0.4s;
        }

        @keyframes cardAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card h2 {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .appointment-details p {
            font-size: 14px;
            color: var(--text-color);
            margin: 8px 0;
            transition: color var(--transition-speed);
        }

        .status-message {
            padding: 15px;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
            margin-top: 20px;
            opacity: 0;
            transform: scale(0.95);
            animation: statusPop 0.5s ease forwards 0.5s;
        }

        @keyframes statusPop {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .status-pending {
            background: #fff3cd;
            color: var(--warning-color);
        }

        .status-accepted {
            background: #d4edda;
            color: var(--success-color);
        }

        .status-declined {
            background: #f8d7da;
            color: var(--danger-color);
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            object-fit: cover;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            transition: transform var(--transition-speed);
        }

        .profile-photo:hover {
            transform: scale(1.05);
        }

        .profile-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 15px;
            transition: transform var(--transition-speed);
        }

        .profile-placeholder:hover {
            transform: scale(1.05);
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
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

            .appointment-details {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    
</head>
<body>
    <div class="sidebar">
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h2>Dashboard</h2>
        <a href="../../dashboard/dashboard.php" class="active" aria-current="page">Dashboard</a>
        <a href="../dashboard/profile/edit_profile.php">Edit Profile</a>
        <a href="../logout/logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome to Your Dashboard</h1>
        </div>
        <div class="card">
            <h2>Your Appointment Details</h2>
            <?php if ($is_valid_photo): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile Photo" class="profile-photo">
            <?php else: ?>
                <div class="profile-placeholder">No Photo Available</div>
            <?php endif; ?>
            <div class="appointment-details">
                <p><strong>Appointment ID:</strong> <?= htmlspecialchars($appointment['appointment_id']) ?></p>
                <p><strong>Name:</strong> <?= htmlspecialchars($appointment['first_name'] . ' ' . ($appointment['middle_name'] ? $appointment['middle_name'] . ' ' : '') . $appointment['last_name']) ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($appointment['gender'] === 'Other' ? $appointment['other_gender'] : $appointment['gender']) ?></p>
                <p><strong>Date of Birth:</strong> <?= htmlspecialchars($appointment['birthdate']) ?></p>
                <p><strong>Age:</strong> <?= htmlspecialchars($appointment['age']) ?></p>
                <p><strong>Occupation:</strong> <?= htmlspecialchars($appointment['occupation']) ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($appointment['address']) ?></p>
                <p><strong>Region:</strong> <?= htmlspecialchars($appointment['region']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($appointment['email']) ?></p>
                <p><strong>Contact Number:</strong> +63<?= htmlspecialchars($appointment['contact']) ?></p>
                <p><strong>Appointment Date:</strong> <?= htmlspecialchars($appointment['appointment_date']) ?></p>
                <p><strong>Appointment Time:</strong> <?= htmlspecialchars($appointment['appointment_time']) ?></p>
                <p><strong>Purpose:</strong> <?= htmlspecialchars($appointment['purpose']) ?></p>
                <p><strong>Submitted On:</strong> <?= htmlspecialchars($appointment['created_at']) ?></p>
            </div>
            <!-- Debug Log -->
            <?php if (!empty($debug_log)): ?>
                <div class="debug-log">
                    <h3>Debug Log:</h3>
                    <?php foreach ($debug_log as $log): ?>
                        <p><?= htmlspecialchars($log) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="status-message status-<?= strtolower($appointment['status']) ?>">
                Your appointment is <strong><?= htmlspecialchars($appointment['status']) ?></strong>.
                <?php if ($appointment['status'] === 'Accepted'): ?>
                    Please arrive on time for your appointment.
                <?php elseif ($appointment['status'] === 'Declined'): ?>
                    Please submit a new appointment or contact support.
                <?php else: ?>
                    We are reviewing your appointment. You will be notified soon.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const debugLog = document.querySelector(".debug-log");
            if (debugLog && debugLog.textContent) {
                debugLog.style.display = "block";
            }
        });
    </script>
</body>
</html>