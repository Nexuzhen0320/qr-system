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

// Initialize debug log
$debug_log = [];

// Check if database connection is successful
if (!$connection) {
    $debug_log[] = "Database connection failed: " . mysqli_connect_error();
    die("Database connection failed. Please try again later.");
}

try {
    // Fetch user data
    $email = $_SESSION['email'];
    $stmt = $connection->prepare("SELECT user_id FROM data WHERE email = ?");
    if ($stmt === false) {
        $debug_log[] = "User query preparation failed: " . $connection->error;
        throw new Exception("Failed to prepare user query.");
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'] ?? null;
    $stmt->close();

    if (!$user_id) {
        $debug_log[] = "No user found for email: $email";
        header("Location: ../index.php");
        exit();
    }

    // Fetch appointment details
    $query = "SELECT appointment_id, first_name, middle_name, last_name, gender, other_gender, birthdate, age, 
                     occupation, address, region, email, contact, appointment_date, appointment_time, purpose, 
                     profile_photo, status, created_at, reference_id 
              FROM appointments 
              WHERE user_id = ?";
    $stmt = $connection->prepare($query);
    if ($stmt === false) {
        $debug_log[] = "Appointment query preparation failed: " . $connection->error;
        throw new Exception("Failed to prepare appointment query: " . $connection->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        $debug_log[] = "Appointment query execution failed: " . $stmt->error;
        throw new Exception("Failed to execute appointment query.");
    }
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        $debug_log[] = "No appointment found for user_id: $user_id";
        header("Location: ../fillupform/fillupform.php");
        exit();
    }

    // Fetch ID details from user_information
    $id_type = '';
    $id_number = '';
    $id_photo = '';
    $is_valid_id_photo = false;
    $id_photo_url = '';
    $stmt = $connection->prepare("SELECT id_type, id_number, id_photo FROM user_information WHERE user_id = ?");
    if ($stmt === false) {
        $debug_log[] = "User information query preparation failed: " . $connection->error;
    } else {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $user_info = $result->fetch_assoc();
            $id_type = $user_info['id_type'] ?? '';
            $id_number = $user_info['id_number'] ?? '';
            $id_photo = $user_info['id_photo'] ?? '';
        } else {
            $debug_log[] = "User information query execution failed: " . $stmt->error;
        }
        $stmt->close();
    }

    // Validate profile photo
    $is_valid_photo = false;
    $profile_photo = $appointment['profile_photo'] ?? $_SESSION['profilePhoto'] ?? '';
    $base_server_path = '../ProfileImage/image/';
    $base_relative_url = './image/';

    if ($profile_photo) {
        $profile_photo = ltrim($profile_photo, '/\\');
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
    if ($id_photo) {
        $id_photo = ltrim($id_photo, '/\\');
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
    $debug_log[] = "Error: " . $e->getMessage();
} finally {
    if (isset($connection) && $connection) {
        $connection->close();
    }
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
            z-index: 1000;
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
            margin-bottom: 20px;
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
            color: var(--secondary-color);
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            transition: margin-left var(--transition-speed), width var(--transition-speed);
        }

        .menu-toggle {
            background: var(--primary-color);
            color: #ffffff;
            border: none;
            padding: 6px;
            font-size: 18px;
            cursor: pointer;
            border-radius: 5px;
            transition: background var(--transition-speed);
        }

        .menu-toggle:hover {
            background: var(--primary-hover);
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
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
            background: #ffffff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
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

        .status-approved {
            background: #d4edda;
            color: var(--success-color);
        }

        .status-rejected {
            background: #f8d7da;
            color: var(--danger-color);
        }

        .status-cancelled {
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
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            transition: transform var(--transition-speed);
        }

        .profile-photo:hover {
            transform: scale(1.05);
        }

        .profile-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            color: var(--text-color);
            transition: transform var(--transition-speed);
            text-align: center;
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

        .modal .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-color);
            cursor: pointer;
            transition: color var(--transition-speed);
        }

        .modal .close-btn:hover {
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
                padding: 15px;
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
                max-width: 95%;
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

            .profile-photo,
            .profile-placeholder {
                width: 80px;
                height: 80px;
                font-size: 10px;
            }

            .id-photo,
            .id-placeholder {
                width: 150px;
                height: 150px;
                font-size: 12px;
            }

            .appointment-details p {
                font-size: 13px;
            }

            .status-message {
                font-size: 13px;
                padding: 10px;
            }

            .view-id-btn {
                padding: 6px 12px;
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
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h2>Dashboard</h2>
        <a href="../dashboard.php" class="active" aria-current="page">Dashboard</a>
        <a href="../dashboard/edit_profile/edit_profile.php">Edit Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="dashboard-header">
            <button class="menu-toggle" aria-label="Toggle Sidebar" aria-expanded="false">
                <i class='bx bx-menu'></i>
            </button>
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
                <?php if ($appointment['status'] === 'Approved' && !empty($appointment['reference_id'])): ?>
                    <p><strong>Reference ID:</strong> <?php echo htmlspecialchars($appointment['reference_id']); ?></p>
                <?php endif; ?>
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
                    case 'Approved':
                        echo 'Please arrive on time for your appointment.';
                        break;
                    case 'Rejected':
                    case 'Cancelled':
                        echo 'Please submit a new appointment or contact support.';
                        break;
                    default:
                        echo 'We are reviewing your appointment. You will be notified soon.';
                }
                ?>
            </div>
        </div>
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

            // Modal functionality
            const viewIdBtn = document.querySelector('.view-id-btn');
            const idModal = document.querySelector('#idModal');
            const modalCloseBtn = document.querySelector('.modal .close-btn');

            viewIdBtn.addEventListener('click', function() {
                idModal.classList.add('show');
            });

            modalCloseBtn.addEventListener('click', function() {
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
                if (e.key === 'Escape') {
                    if (idModal.classList.contains('show')) {
                        idModal.classList.remove('show');
                    }
                    if (isMobile && sidebar.classList.contains('active')) {
                        closeSidebarOnMobile();
                    }
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