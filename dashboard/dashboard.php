<?php
session_start();

// Prevent caching to ensure fresh content
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

    // Fetch appointment details, including id_type
    $stmt = $connection->prepare("
        SELECT appointment_id, first_name, middle_name, last_name, gender, other_gender, birthdate, age, 
               occupation, address, region, email, contact, appointment_date, appointment_time, purpose, 
               profile_photo, id_type, id_number, id_photo, status, created_at 
        FROM appointments 
        WHERE user_id = ?
    ");
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

    // Define base paths for photo handling
    $base_server_path = '../ProfileImage/image/';
    $base_relative_url = './image/';

    if ($profile_photo) {
        // Normalize stored path
        $profile_photo = ltrim($profile_photo, '/\\');
        // Convert relative path to absolute server path
        $full_photo_path = str_replace('./image/', $base_server_path, $profile_photo);
        $debug_log[] = "Profile photo stored path: '$profile_photo', computed server path: '$full_photo_path'";
        if (file_exists($full_photo_path) && is_file($full_photo_path)) {
            $is_valid_photo = true;
            $profile_photo_url = str_replace('./image/', $base_relative_url, $profile_photo);
        } else {
            $debug_log[] = "Profile photo file not found or invalid: '$full_photo_path'";
        }
    } else {
        $debug_log[] = "Profile photo is empty or not set.";
    }

    // Validate ID photo
    $is_valid_id_photo = false;
    $id_photo = $appointment['id_photo'] ?? '';
    $id_type = $appointment['id_type'] ?? '';
    $id_number = $appointment['id_number'] ?? '';

    if ($id_photo) {
        // Normalize stored path
        $id_photo = ltrim($id_photo, '/\\');
        // Convert relative path to absolute server path
        $full_id_photo_path = str_replace('../image/', $base_server_path, $id_photo);
        $debug_log[] = "ID photo stored path: '$id_photo', computed server path: '$full_id_photo_path'";
        if (file_exists($full_id_photo_path) && is_file($full_id_photo_path)) {
            $is_valid_id_photo = true;
            $id_photo_url = str_replace(['../image/', './image/'], $base_relative_url, $id_photo);
        } else {
            $debug_log[] = "ID photo file not found or invalid: '$full_id_photo_path'";
        }
    } else {
        $debug_log[] = "ID photo is empty or not set.";
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
            to { opacity: 1; transform: translateY(0); }
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
            to { opacity: 1; transform: scale(1); }
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

        .photo-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            object-fit: cover;
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
            transition: transform var(--transition-speed);
        }

        .profile-placeholder:hover {
            transform: scale(1.05);
        }

        .view-id-btn {
            background: var(--primary-color);
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition-speed), transform var(--transition-speed);
        }

        .view-id-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .view-id-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow);
            position: relative;
            transform: scale(0.8);
            transition: transform var(--transition-speed);
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-color);
            cursor: pointer;
            transition: color var(--transition-speed);
        }

        .close-btn:hover {
            color: var(--primary-color);
        }

        .modal-body {
            text-align: center;
        }

        .id-photo {
            width: 200px;
            height: 200px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            margin: 10px auto;
            display: block;
        }

        .id-placeholder {
            width: 200px;
            height: 200px;
            border-radius: 8px;
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: var(--text-color);
            margin: 10px auto;
        }

        .id-number,
        .id-type {
            font-size: 16px;
            color: var(--text-color);
            margin: 10px 0;
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

            .appointment-details {
                grid-template-columns: 1fr;
            }

            .photo-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-content {
                max-width: 90%;
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

            .id-photo,
            .id-placeholder {
                width: 150px;
                height: 150px;
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
            <div class="photo-container">
                <?php if ($is_valid_photo && !empty($profile_photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile Photo" class="profile-photo">
                <?php else: ?>
                    <div class="profile-placeholder">No Photo Available</div>
                <?php endif; ?>
                <button class="view-id-btn" aria-label="View ID Details">View ID</button>
            </div>
            <div class="appointment-details">
                <p><strong>Appointment ID: #</strong> <?php echo htmlspecialchars($appointment['appointment_id']); ?></p>
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
        <?php if (!empty($debug_log)): ?>
            <div class="debug-log">
                <h3>Debug Log:</h3>
                <?php foreach ($debug_log as $log): ?>
                    <p><?php echo htmlspecialchars($log); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for ID Details -->
    <div class="modal" id="idModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ID Details</h3>
                <button class="close-btn" aria-label="Close Modal">×</button>
            </div>
            <div class="modal-body">
                <?php if ($is_valid_id_photo && !empty($id_photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($id_photo_url); ?>" alt="ID Photo" class="id-photo">
                <?php else: ?>
                    <div class="id-placeholder">No ID Photo Available</div>
                <?php endif; ?>
                <p class="id-type"><strong>ID Type:</strong> <?php echo htmlspecialchars($id_type ?: 'Not Provided'); ?></p>
                <p class="id-number"><strong>ID Number:</strong> <?php echo htmlspecialchars($id_number ?: 'Not Provided'); ?></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize debug log visibility
            const debugLog = document.querySelector('.debug-log');
            if (debugLog?.textContent.trim()) {
                debugLog.style.display = 'block';
            }

            // Modal functionality
            const viewIdBtn = document.querySelector('.view-id-btn');
            const idModal = document.querySelector('#idModal');
            const closeBtn = document.querySelector('.close-btn');

            viewIdBtn.addEventListener('click', function() {
                idModal.classList.add('show');
            });

            closeBtn.addEventListener('click', function() {
                idModal.classList.remove('show');
            });

            // Close modal when clicking outside
            idModal.addEventListener('click', function(e) {
                if (e.target === idModal) {
                    idModal.classList.remove('show');
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && idModal.classList.contains('show')) {
                    idModal.classList.remove('show');
                }
            });

            // Prevent back button issues
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    window.location.reload();
                }
            });
        });
    </script>
</body>
</html>