<?php
session_start();
include '../database/db.php'; // DB connection

// Fetch appointments with status Approved or Rejected
$sql = "SELECT appointment_id, first_name, last_name, appointment_date, reference_id, status 
        FROM appointments 
        WHERE status IN ('Approved', 'Rejected')
        ORDER BY appointment_date DESC";

$result = $connection->query($sql);
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
    <title>Appointment Records</title>
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

        .main-content h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            box-shadow: var(--shadow);
            font-size: 14px;
        }

        table th,
        table td {
            padding: 12px 15px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        table th {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: 500;
        }

        table tr:hover {
            background-color: #f1f1f1;
        }

        .approved {
            color: var(--success-color);
            font-weight: bold;
        }

        .rejected {
            color: var(--danger-color);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h2>Admin Dashboard</h2>
        <a href="./admin_dashboard.php">Home</a>
        <a href="./record.php" class="active">Record</a>
        <a href="./notifications.php">Notifications</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="main-content">
        <h2>Accepted and Declined Appointments</h2>
        <table>
            <thead>
                <tr>
                    <th>Reference ID</th>
                    <th>Full Name</th>
                    <th>Appointment Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reference_id'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= htmlspecialchars(date('F j, Y', strtotime($row['appointment_date']))) ?></td>
                            <td class="<?= strtolower($row['status']) ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No approved or rejected appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
