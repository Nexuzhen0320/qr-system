<?php
session_start();
include 'db.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (empty($_SESSION['status_Account']) || empty($_SESSION['email'])) {
    header("Location: index.php");
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
    header("Location: fillupform.php");
    exit();
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
    <title>Dashboard</title>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');

        :root {
            --primary-color: #005f99;
            --primary-hover: #004775;
            --success-color: #16a34a;
            --error-color: #dc3545;
            --pending-color: #f59e0b;
            --border-color: #d1d5db;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e8ecef, #f5f7fa);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            background: #fff;
            max-width: 800px;
            width: 100%;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .logo {
            width: 100px;
            height: auto;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 20px;
        }

        .navbar {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            background-color: #f3f3f3;
        }

        .navbar a {
            text-decoration: none;
            color: #444;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .navbar a:hover,
        .navbar a.active {
            background: var(--primary-color);
            color: #fff;
        }

        .appointment-details {
            text-align: left;
            margin-bottom: 20px;
        }

        .appointment-details h2 {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 15px;
            color: #333;
        }

        .appointment-details p {
            font-size: 14px;
            margin: 8px 0;
            color: #444;
        }

        .status-message {
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }

        .status-pending {
            background: #fef3c7;
            color: var(--pending-color);
        }

        .status-accepted {
            background: #e7f7ec;
            color: var(--success-color);
        }

        .status-declined {
            background: #fdeded;
            color: var(--error-color);
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            object-fit: cover;
            margin: 10px auto;
            display: block;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
                max-width: 500px;
            }

            h1 {
                font-size: 20px;
            }

            .appointment-details h2 {
                font-size: 18px;
            }

            .appointment-details p {
                font-size: 13px;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .profile-photo {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container" role="main">
        <img src="./image/icons/logo1.png" alt="Organization Logo" class="logo">
        <h1>Welcome to Your Dashboard</h1>
        <nav class="navbar">
            <a href="dashboard.php" class="active" aria-current="page">Dashboard</a>
            <a href="fillupform.php">New Appointment</a>
            <a href="logout.php">Logout</a>
        </nav>

        <div class="appointment-details">
            <h2>Your Appointment Details</h2>
            <?php if ($appointment['profile_photo']): ?>
                <img src="<?= htmlspecialchars($appointment['profile_photo']) ?>" alt="Profile Photo" class="profile-photo">
            <?php endif; ?>
            <p><strong>Name:</strong> <?= htmlspecialchars($appointment['first_name'] . ' ' . ($appointment['middle_name'] ? $appointment['middle_name'] . ' ' : '') . $appointment['last_name']) ?></p>
            <p><strong>Gender:</strong> <?= htmlspecialchars($appointment['sex'] === 'Other' ? $appointment['other_sex'] : $appointment['sex']) ?></p>
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
</body>
</html>