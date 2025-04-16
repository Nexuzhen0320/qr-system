<?php
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if the user is logged in
if (empty($_SESSION['status_Account'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent caching in HTML -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <title>Registration Form</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: #e8ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .form-container {
            background: #ffffff;
            width: 100%;
            max-width: 720px;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin: 20px;
        }

        h5 {
            font-size: 26px;
            font-weight: 500;
            color: #1a1a1a;
            text-align: center;
            margin-bottom: 30px;
            letter-spacing: 0.5px;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 12px;
            border-bottom: 1px solid #d8d8d8;
        }

        .navbar ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .navbar li {
            margin-left: 15px;
        }

        .navbar a {
            text-decoration: none;
            color: #444;
            font-size: 15px;
            font-weight: 400;
            padding: 8px 14px;
            border-radius: 6px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .navbar a:hover,
        .navbar a.active {
            background-color: #f0f2f5;
            color: #1a1a1a;
        }

        .navbar img {
            width: 50px;
            height: 50px;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
        }

        /* Form Sections */
        .input-group-container1,
        .input-group-container2 {
            margin-bottom: 25px;
        }

        .input-group {
            margin-bottom: 18px;
            position: relative;
        }

        .label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .label.required::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
            font-size: 12px;
        }

        input[type="text"],
        input[type="date"],
        select,
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #c8c8c8;
            border-radius: 6px;
            font-size: 15px;
            color: #333;
            background: #fff;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="date"]:focus,
        select:focus {
            outline: none;
            border-color: #005f99;
            box-shadow: 0 0 5px rgba(0, 95, 153, 0.2);
        }

        input[disabled] {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23444' viewBox='0 0 16 16'%3E%3Cpath d='M8 12L2 6h12l-6 6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
        }

        input[type="file"] {
            padding: 10px;
            background: #fafafa;
        }

        /* Phone Number */
        .input-group-2-phone {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .input-group-2-phone input[disabled] {
            width: 60px;
            text-align: center;
            padding: 12px 8px;
        }

        .input-group-2-phone input:not([disabled]) {
            flex: 1;
        }

        /* Other Sex Input */
        #otherSexGroup {
            margin-top: 12px;
        }

        /* Error Messages */
        .error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }

        /* Submit Button */
        .button-submit {
            text-align: center;
            margin-top: 30px;
        }

        button {
            background: #005f99;
            color: #fff;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background: #004775;
            transform: translateY(-1px);
        }

        button:focus {
            outline: none;
            box-shadow: 0 0 6px rgba(0, 95, 153, 0.3);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .input-group-container1,
            .input-group-container2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
            }

            .input-group-container1 .input-group:nth-child(1) {
                grid-column: span 2; /* Photo upload spans both columns */
            }

            .input-group-container1 .input-group:nth-child(2),
            .input-group-container1 .input-group:nth-child(3),
            .input-group-container1 .input-group:nth-child(4),
            .input-group-container2 .input-group {
                grid-column: span 1;
            }
        }

        @media (max-width: 767px) {
            .form-container {
                padding: 25px;
                max-width: 100%;
            }

            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .navbar ul {
                margin-top: 10px;
                width: 100%;
            }

            .navbar li {
                margin: 5px 0;
                width: 100%;
            }

            .navbar a {
                display: block;
                width: 100%;
            }

            .navbar img {
                align-self: center;
                margin-bottom: 10px;
            }

            .input-group-container1,
            .input-group-container2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <img src="./image/icons/logo1.ico" alt="Logo" style="display: block; margin: 0 auto 20px; width: 60px; filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.2));">
        <h5>Registration Form</h5>
        <form action="" method="POST" id="registerForm" enctype="multipart/form-data">
            <div class="navbar">
                <ul>
                    <li><a href="dashboard.php" class="active">Registration Form</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="input-group-container1">
                <div class="input-group">
                    <label for="myFile" class="label">Upload Photo</label>
                    <input type="file" id="myFile" name="filename" accept=".jpeg, .jpg">
                    <span class="error" id="myFile-error"></span>
                </div>
            </div>
            <div class="input-group-container1">
                <div class="input-group">
                    <label for="lastname" class="label required">Last Name</label>
                    <input type="text" name="lastname" id="lastname" autocomplete="off" required>
                    <span class="error" id="lastname-error"></span>
                </div>
                <div class="input-group">
                    <label for="firstname" class="label required">First Name</label>
                    <input type="text" name="firstname" id="firstname" autocomplete="off" required>
                    <span class="error" id="firstname-error"></span>
                </div>
                <div class="input-group">
                    <label for="middlename" class="label">Middle Name (Optional)</label>
                    <input type="text" name="middlename" id="middlename" autocomplete="off">
                    <span class="error" id="middlename-error"></span>
                </div>
                <div class="input-group">
                    <label for="sex" class="label required">Sex</label>
                    <select size="1" id="sex" name="sex" required>
                        <option value="" selected disabled hidden>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <span class="error" id="sex-error"></span>
                    <div class="input-group" id="otherSexGroup" style="display: none;">
                        <label for="otherSex" class="label">Please Specify</label>
                        <input type="text" id="otherSex" name="otherSex" placeholder="Specify your gender" />
                        <span class="error" id="otherSex-error"></span>
                    </div>
                </div>
            </div>
            <div class="input-group-container2">
                <div class="input-group">
                    <label for="Region" class="label required">Region</label>
                    <input type="text" name="Region" id="Region" autocomplete="off" required>
                    <span class="error" id="Region-error"></span>
                </div>
                <div class="input-group">
                    <label for="Address" class="label required">Complete Address</label>
                    <input type="text" name="Address" id="Address" autocomplete="off" required>
                    <span class="error" id="Address-error"></span>
                </div>
                <div class="input-group">
                    <div class="input-group-2-phone">
                        <label for="phonenumber" class="label required">Phone Number</label>
                        <input name="phonenumber_prefix" id="phonenumber_prefix" value="+63" disabled autocomplete="off">
                        <input type="text" name="phonenumber" id="phonenumber" autocomplete="off" required>
                        <span class="error" id="phonenumber-error"></span>
                    </div>
                </div>
                <div class="input-group">
                    <label for="appointmentDate" class="label required">Appointment Date</label>
                    <input type="date" id="appointmentDate" name="appointment-date" required />
                    <span class="error" id="appointmentDate-error"></span>
                </div>
            </div>
            <div class="button-submit">
                <button type="submit">Submit</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sexSelect = document.getElementById("sex");
            const otherSexGroup = document.getElementById("otherSexGroup");

            function toggleOtherInput() {
                if (sexSelect.value === "Other") {
                    otherSexGroup.style.display = "block";
                } else {
                    otherSexGroup.style.display = "none";
                }
            }

            toggleOtherInput();
            sexSelect.addEventListener("change", toggleOtherInput);

            // Prevent back button from showing cached page
            window.addEventListener("pageshow", function (event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    fetch('check_session.php', {
                        method: 'GET',
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.isAuthenticated) {
                            window.location.replace('index.php');
                        }
                    })
                    .catch(error => {
                        console.error('Session check failed:', error);
                        window.location.replace('index.php');
                    });
                }
            });

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Phone number validation
            document.getElementById("phonenumber").addEventListener("input", function (e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, "");
                if (e.target.value.length > 10) {
                    e.target.value = e.target.value.slice(0, 10);
                }
            });

            // Client-side form validation
            const form = document.getElementById("registerForm");
            form.addEventListener("submit", function (e) {
                let isValid = true;
                const fields = [
                    { id: "lastname", errorId: "lastname-error", message: "Last name is required" },
                    { id: "firstname", errorId: "firstname-error", message: "First name is required" },
                    { id: "sex", errorId: "sex-error", message: "Please select a gender" },
                    { id: "Region", errorId: "Region-error", message: "Region is required" },
                    { id: "Address", errorId: "Address-error", message: "Address is required" },
                    { id: "phonenumber", errorId: "phonenumber-error", message: "Phone number is required" },
                    { id: "appointmentDate", errorId: "appointmentDate-error", message: "Appointment date is required" }
                ];

                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const error = document.getElementById(field.errorId);
                    if (!input.value.trim()) {
                        error.textContent = field.message;
                        error.style.display = "block";
                        isValid = false;
                    } else {
                        error.style.display = "none";
                    }
                });

                // Special validation for phone number
                const phoneInput = document.getElementById("phonenumber");
                const phoneError = document.getElementById("phonenumber-error");
                if (phoneInput.value.length !== 10) {
                    phoneError.textContent = "Phone number must be 10 digits";
                    phoneError.style.display = "block";
                    isValid = false;
                }

                // Validate otherSex if selected
                if (sexSelect.value === "Other") {
                    const otherSexInput = document.getElementById("otherSex");
                    const otherSexError = document.getElementById("otherSex-error");
                    if (!otherSexInput.value.trim()) {
                        otherSexError.textContent = "Please specify your gender";
                        otherSexError.style.display = "block";
                        isValid = false;
                    } else {
                        otherSexError.style.display = "none";
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>