<?php
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['status_Account']) || !isset($_SESSION['email']) || $_SESSION['status_Account'] !== 'logged_in') {
    header("Location: ../index.php");
    exit();
}

include '../database/db.php';

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
        header("Location: ../index.php");
        exit();
    }

    // Check appointment
    $stmt = $connection->prepare("SELECT * FROM appointments WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        header("Location: ../fillupform/fillupform.php");
        exit();
    }

    // Validate profile photo
    $is_valid_photo = false;
    $profile_photo = $appointment['profile_photo'] ?? $_SESSION['profilePhoto'] ?? '';
    $debug_log = [];

    // Define the base path for profile photos
    $base_photo_path = '../dashboard/image/Profile_Photo/';
    $relative_photo_url = './image/Profile_Photo/';

    if ($profile_photo) {
        // Normalize the path
        $profile_photo = ltrim($profile_photo, '/\\');
        $full_photo_path = $base_photo_path . basename($profile_photo);

        // Check if the file exists
        if (file_exists($full_photo_path) && is_file($full_photo_path)) {
            $is_valid_photo = true;
            $profile_photo_url = $relative_photo_url . basename($profile_photo);
        } else {
            $debug_log[] = "Profile photo file not found or invalid: '$full_photo_path'";
        }
    } else {
        $debug_log[] = "Profile photo is empty or not set.";
    }

} catch (Exception $e) {
    $debug_log[] = "Database error: " . $e->getMessage();
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
        <a href="../dashboard.php" class="active" aria-current="page">Dashboard</a>
        <a href="../dashboard/edit_profile/edit_profile.php">Edit Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome to Your Dashboard</h1>
        </div>
        <div class="card">
            <h2>Your Appointment Details</h2>
            <?php if ($is_valid_photo && !empty($profile_photo_url)): ?>
                <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile Photo" class="profile-photo">
            <?php else: ?>
                <div class="profile-placeholder">No Photo Available</div>
            <?php endif; ?>
            <div class="appointment-details">
                <p><strong>Appointment ID:</strong> <?php echo htmlspecialchars($appointment['appointment_id']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment['first_name'] . ' ' . ($appointment['middle_name'] ? $appointment['middle_name'] . ' ' : '') . $appointment['last_name']); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($appointment['gender'] === 'Other' ? $appointment['other_gender'] : $appointment['gender']); ?></p>
                <p><strong>Date of Birth:</strong> 
                    <?php 
                        $birthdate = $appointment['birthdate'];
                        $formatted_birthdate = date("F j, Y", strtotime($birthdate));
                        echo htmlspecialchars($formatted_birthdate); 
                    ?>
                </p>
                <p><strong>Age:</strong> <?php echo htmlspecialchars($appointment['age']); ?></p>
                <p><strong>Occupation:</strong> <?php echo htmlspecialchars($appointment['occupation']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($appointment['address']); ?></p>
                <p><strong>Region:</strong> <?php echo htmlspecialchars($appointment['region']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['email']); ?></p>
                <p><strong>Contact Number:</strong> +63<?php echo htmlspecialchars($appointment['contact']); ?></p>
                <p><strong>Appointment Date:</strong> 
                    <?php 
                        $appointment_date = $appointment['appointment_date'];
                        $formatted_date = date("F j, Y", strtotime($appointment_date));
                        echo htmlspecialchars($formatted_date); 
                    ?>
                </p>
                <p><strong>Appointment Time:</strong> 
                    <?php 
                        $time_24hr = $appointment['appointment_time'];
                        $time_12hr = date("g:i A", strtotime($time_24hr));
                        echo htmlspecialchars($time_12hr); 
                    ?>
                </p>
                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($appointment['purpose']); ?></p>
                <p><strong>Submitted On:</strong> 
                    <?php 
                        $submitted_on = $appointment['created_at'];
                        $formatted_submitted_on = date("F j, Y", strtotime($submitted_on));
                        echo htmlspecialchars($formatted_submitted_on); 
                    ?>
                    <strong>Time:</strong>
                    <?php 
                        $time_on = $appointment['created_at'];
                        $formatted_time_on = date('g:i A', strtotime($time_on));
                        echo htmlspecialchars($formatted_time_on);
                        ?>
                </p>
            </div>
            <div class="status-message status-<?php echo strtolower($appointment['status']); ?>">
                Your appointment is <strong><?php echo htmlspecialchars($appointment['status']); ?></strong>.
                <?php
                switch ($appointment['status']) {
                    case 'Accepted':
                        echo 'Please arrive on time for your appointment.';
                        break;
                    case 'Declined':
                        echo 'Please submit a new appointment or contact support.';
                        break;
                    default:
                        echo 'We are reviewing your appointment. You will be notified soon.';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Prevent back button from showing cached page  ================= incase the user clicks back button
        // window.addEventListener('pageshow', function(event) {
        //     if (event.persisted || performance.getEntriesByType('navigation')[4].type === 'back_forward') {
        //         fetch('./check_session.php', {
        //             method: 'POST',
        //             headers: {
        //                 'Content-Type': 'application/json',
        //                 'Cache-Control': 'no-cache'
        //             }
        //         })
        //         .then(response => response.json())
        //         .then(data => {
        //             if (!data.logged_in) {
        //                 window.location.replace('../index.php');
        //             }
        //         })
        //         .catch(error => {
        //             console.error('Session check failed:', error);
        //             window.location.replace('../index.php');
        //         });
        //     }
        // });

        // Periodically check session to ensure user is still logged in
        document.addEventListener('DOMContentLoaded', function() {
            const debugLog = document.querySelector('.debug-log');
            if (debugLog?.textContent.trim()) {
            debugLog.style.display = 'block';
            }
        });

        

        document.addEventListener('DOMContentLoaded', function() {
    const debugLog = document.querySelector('.debug-log');
    if (debugLog?.textContent.trim()) {
        debugLog.style.display = 'block';
    }

    // Prevent back button issues by ensuring session is valid
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
});
    </script>
</body>
</html>