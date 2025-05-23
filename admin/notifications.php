<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['status_Account']) || $_SESSION['status_Account'] !== 'logged_in') {
    header("Location: ../index.php");
    exit;
}

include '../database/db.php';

// Fetch all new (pending) appointments
$query = $connection->prepare("SELECT appointment_id, first_name, middle_name, last_name, purpose, created_at FROM appointments WHERE status = 'Pending' ORDER BY created_at DESC");
$query->execute();
$result = $query->get_result();
$newAppointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="../image/icons/logo1.ico" />
    <title>Notifications - New Appointments</title>
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
        }
        .sidebar .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: block;
            border-radius: 50%;
            object-fit: cover;
        }
        .sidebar h2 {
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar a {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 5px;
            font-size: 14px;
            transition: background var(--transition-speed), transform var(--transition-speed);
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
        .notification {
            background-color: #fff;
            border-left: 5px solid var(--accent-color);
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: var(--shadow);
            transition: transform var(--transition-speed);
        }
        .notification:hover {
            transform: translateY(-2px);
        }
        .no-notifications {
            padding: 20px;
            font-style: italic;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo" />
        <h2>Admin Dashboard</h2>
        <a href="./admin_dashboard.php">Home</a>
        <a href="./record.php">Record</a>
        <a href="./notifications.php" class="active">Notifications</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="main-content">
        <h1>New Appointment Requests</h1>
        <div id="notification-list">
            <?php if (count($newAppointments) > 0): ?>
                <?php foreach ($newAppointments as $appt): ?>
                    <?php
                        $fullName = htmlspecialchars($appt['first_name'] . ' ' . $appt['middle_name'] . ' ' . $appt['last_name']);
                        $purpose = htmlspecialchars($appt['purpose']);
                        $dateTime = date("M d, Y H:i", strtotime($appt['created_at']));
                    ?>
                    <div class="notification">
                        <strong><?= $fullName ?></strong> requested <em><?= $purpose ?></em><br />
                        <small><?= $dateTime ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-notifications">No new appointment requests.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Optional: auto-refresh notifications every 30 seconds (AJAX)
        setInterval(() => {
            fetch('./notification_fetch.php')
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('notification-list');
                    if(data.length > 0) {
                        container.innerHTML = '';
                        data.forEach(appt => {
                            const div = document.createElement('div');
                            div.classList.add('notification');
                            div.innerHTML = `<strong>${appt.client_name}</strong> requested <em>${appt.purpose}</em><br /><small>${appt.created_at}</small>`;
                            container.appendChild(div);
                        });
                    } else {
                        container.innerHTML = '<p class="no-notifications">No new appointment requests.</p>';
                    }
                });
        }, 30000);
    </script>
</body>
</html>
