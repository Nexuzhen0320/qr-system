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

$email = $_SESSION['email'];
$stmt = $connection->prepare("SELECT * FROM data WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['user_status'] != 1) {
    header("Location: ../dashboard/dashboard.php");
    exit;
}

// Process Accept/Reject actions (if submitted through POST directly here, fallback logic)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $action = $_POST['action'];
    $appointmentId = intval($_POST['appointment_id']);
    $status = ($action === 'accept') ? 'Accepted' : 'Rejected';

    $update = $connection->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $appointmentId);
    $update->execute();
}

// Fetch appointments
$appointmentQuery = "SELECT * FROM appointments ORDER BY created_at DESC";
$appointments = $connection->query($appointmentQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Appointments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../image/icons/logo1.ico">
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

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            box-shadow: var(--shadow);
            margin-top: 20px;
            font-size: 14px;
        }

        table th,
        table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: 500;
        }

        table tr:hover {
            background-color: #f1f1f1;
        }

        .action-btn {
            background: var(--primary-color);
            color:rgb(247, 255, 255);
            border: none;
            padding: 6px 12px;
            margin: 2px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: background var(--transition-speed), transform var(--transition-speed);
        }

        .action-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .action-btn.reject {
            background: var(--danger-color);
        }

        .action-btn.reject:hover {
            background:rgb(228, 68, 84);
        }
    </style>
</head>
<body>
<meta charset="UTF-8">
<title>Admin Dashboard - Appointments</title>
<link rel="stylesheet" href="your_stylesheet.css"> <!-- Replace with your actual stylesheet path -->

<body>

<!-- ✅ Sidebar -->
<div class="sidebar">
    <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
    <h2>Admin Dashboard</h2>
    <a href="./admin_dashboard.php" class="active">Home</a>
    <a href="./record.php">Record</a>
    <a href="./notifications.php">Notifications <span id="notification-count" style="display:none;" class="badge"></span></a>
    <a href="../logout.php">Logout</a>
</div>

<!-- ✅ Main Content -->
<div class="main-content">
    <div class="dashboard-header">
        <h1>Appointment Requests</h1>
        <?php if (isset($_SESSION['notification'])): ?>
            <p style="color: green;">
                <?= htmlspecialchars($_SESSION['notification']); unset($_SESSION['notification']); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($appointments->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Email</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars(date("F j, Y", strtotime($row['appointment_date']))) ?></td>
                        <td><?= htmlspecialchars(date("g:i A", strtotime($row['appointment_time']))) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td class="status-cell"><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars(date("F j, Y g:i A", strtotime($row['created_at']))) ?></td>
                        <td class="action-cell">
                            <?php if ($row['status'] === 'Pending'): ?>
                                <button class="action-btn accept" 
                                        data-id="<?= $row['appointment_id'] ?>" 
                                        data-email="<?= $row['email'] ?>">Accept</button>
                                <button class="action-btn reject" 
                                        data-id="<?= $row['appointment_id'] ?>" 
                                        data-email="<?= $row['email'] ?>">Reject</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No appointments found.</p>
    <?php endif; ?>
</div>

<!-- ✅ Popup Box -->
<div id="popup-box" style="display:none; position:fixed; top:20px; right:20px; background:#d4edda; color:#155724; padding:20px; border:1px solid #c3e6cb; border-radius:5px; z-index:9999;">
    <span id="popup-close" style="float:right; cursor:pointer;">&times;</span>
    <p id="popup-message"></p>
</div>

<!-- ✅ JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".action-btn").forEach(button => {
        button.addEventListener("click", async function () {
            const status = this.classList.contains("accept") ? "Approved" : "Rejected";
            const appointmentId = this.getAttribute("data-id");
            const email = this.getAttribute("data-email");

            if (!confirm(`Are you sure you want to ${status.toLowerCase()} this appointment?`)) return;

            const formData = new URLSearchParams();
            formData.append("appointment_id", appointmentId);
            formData.append("email", email);
            formData.append("status", status);

            const response = await fetch("update_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: formData.toString()
            });

            const result = await response.json();
            const popup = document.getElementById("popup-box");
            const message = document.getElementById("popup-message");

            if (result.success) {
                message.textContent = result.message;
                popup.style.display = "block";
                popup.style.background = "#d4edda";
                popup.style.color = "#155724";
                popup.style.borderColor = "#c3e6cb";

                const row = this.closest("tr");
                row.querySelector(".status-cell").textContent = status;
                row.querySelector(".action-cell").innerHTML = ""; // Remove buttons
            } else {
                message.textContent = "Error: " + result.message;
                popup.style.display = "block";
                popup.style.background = "#f8d7da";
                popup.style.color = "#721c24";
                popup.style.borderColor = "#f5c6cb";
            }
        });
    });

    document.getElementById("popup-close").onclick = () => {
        document.getElementById("popup-box").style.display = "none";
    };

    // Fetch notification count
    updateNotificationCount();
});

function updateNotificationCount() {
    fetch('./notification_fetch.php')
    .then(res => res.json())
    .then(data => {
        const countSpan = document.getElementById('notification-count');
        if(data.length > 0) {
            countSpan.style.display = 'inline-block';
            countSpan.textContent = data.length;
        } else {
            countSpan.style.display = 'none';
        }
    });
}
</script>

</body>
</html>
