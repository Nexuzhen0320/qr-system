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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./image/icons/logo1.ico">
    <title>New Registration Form</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e8ecef, #f5f7fa);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .form-container {
            background: #ffffff;
            width: 100%;
            max-width: 760px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            margin: 20px;
            transition: transform 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-2px);
        }

        h5 {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            text-align: center;
            margin-bottom: 30px;
            letter-spacing: 0.5px;
            position: relative;
        }

        h5::after {
            content: '';
            width: 50px;
            height: 3px;
            background: #005f99;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
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
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .navbar a:hover,
        .navbar a.active {
            background: #005f99;
            color: #fff;
        }

        .navbar img {
            width: 50px;
            height: 50px;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.1));
        }

        .input-group-container1,
        .input-group-container2 {
            margin-bottom: 25px;
        }

        .input-group {
            margin-bottom: 20px;
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
        select,
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #d0d4d8;
            border-radius: 6px;
            font-size: 15px;
            color: #333;
            background: #fff;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #005f99;
            box-shadow: 0 0 8px rgba(0, 95, 153, 0.2);
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

        .upload-button {
            background: #005f99;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .upload-button:hover {
            background: #004775;
        }

        .photo-upload-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .profile-photo-preview {
            margin-top: -5em;
            margin-left: 10em;
            width: 150px; /* 2 inches at 96 DPI */
            height: 150px; /* 2 inches at 96 DPI */
            border: 2px solid #d0d4d8;
            overflow: hidden;
            display: absolute;
            justify-content: center;
            align-items: center;
            background: #f5f5f5;
            
        }

        .profile-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

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

        #otherSexGroup {
            margin-top: 12px;
        }

        .error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }

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
            transition: all 0.3s ease;
        }

        button:hover {
            background: #004775;
            transform: scale(1.02);
        }

        button:focus {
            outline: none;
            box-shadow: 0 0 8px rgba(0, 95, 153, 0.3);
        }

        /* Popup Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .popup-content {
            background: #fff;
            width: 90%;
            max-width: 400px;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            position: relative;
            text-align: center;
        }

        .popup-content .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .popup-content .close-button:hover {
            color: #333;
        }

        .photo-preview-container {
            border: 2px dashed #d0d4d8;
            border-radius: 4px;
            padding: 20px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .photo-preview-container i {
            font-size: 30px;
            color: #666;
            margin-bottom: 10px;
        }

        .photo-preview-container p {
            margin: 5px 0;
            font-size: 16px;
            color: #333;
        }

        .photo-preview-container .sub-text {
            font-size: 14px;
            color: #666;
        }

        .photo-preview {
            max-width: 100%;
            max-height: 160px;
            display: none;
            object-fit: contain;
        }

        .popup-file-input {
            margin-top: 15px;
        }

        .popup-file-input input[type="file"] {
            display: none;
        }

        .choose-file-button {
            background: #000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .choose-file-button:hover {
            background: #333;
        }

        /* Custom Date Picker Styles */
        .custom-date-picker {
            position: relative;
            width: 100%;
        }

        #appointmentDateDisplay {
            width: 100%;
            padding: 12px;
            border: 1px solid #d0d4d8;
            border-radius: 6px;
            font-size: 15px;
            color: #333;
            background: #fff;
            cursor: pointer;
        }

        #appointmentDateDisplay:focus {
            outline: none;
            border-color: #005f99;
            box-shadow: 0 0 8px rgba(0, 95, 153, 0.2);
        }

        .date-picker-container {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #fff;
            border: 1px solid #d0d4d8;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 10px;
            z-index: 1000;
            width: 280px;
        }

        .date-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .date-picker-header button {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #005f99;
        }

        .date-picker-header button:hover {
            color: #004775;
        }

        .date-picker-header span {
            font-size: 16px;
            font-weight: 500;
        }

        .date-picker-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            text-align: center;
        }

        .date-picker-days span {
            font-size: 12px;
            color: #666;
            padding: 5px;
        }

        .date-picker-day {
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .date-picker-day.thursday {
            color: #000;
        }

        .date-picker-day:not(.thursday) {
            color: #999;
            cursor: not-allowed;
        }

        .date-picker-day:hover:not(.date-picker-day:not(.thursday)) {
            background: #e8ecef;
        }

        .date-picker-day.selected {
            background: #005f99;
            color: #fff;
        }

        .date-picker-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media (min-width: 768px) {
            .input-group-container1,
            .input-group-container2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
            }

            .input-group-container1 .input-group:nth-child(1) {
                grid-column: span 2;
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
        <img src="./image/icons/logo1.ico" alt="Logo" style="display: block; margin: 0 auto 20px; width: 60px; filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));">
        <h5>New Registration Form</h5>
        <form action="" method="POST" id="newRegisterForm" enctype="multipart/form-data">
            <div class="navbar">
                <ul>
                    <li><a href="new_registration_form.php" class="active">Registration Form</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="input-group-container1">
                <div class="input-group">
                    <label for="myFile" class="label required">2x2 Photo</label>
                    <button type="button" class="upload-button" onclick="openPopup()">Upload Photo</button>
                    <div class="photo-upload-note">Supports: JPG, JPEG</div>
                    <input type="file" id="myFile" name="filename" accept="image/jpeg,image/jpg" style="display: none;">
                    <div class="profile-photo-preview" id="profilePhotoPreview">
                        <img id="profilePhotoImg" src="" alt="Profile Photo Preview" style="display: none;">
                    </div>
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
                    <label for="region" class="label required">Region</label>
                    <input type="text" name="region" id="region" autocomplete="off" required>
                    <span class="error" id="region-error"></span>
                </div>
                <div class="input-group">
                    <label for="address" class="label required">Complete Address</label>
                    <input type="text" name="address" id="address" autocomplete="off" required>
                    <span class="error" id="address-error"></span>
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
                    <label for="appointmentDate" class="label required">Appointment Date (Thursdays Only)</label>
                    <div class="custom-date-picker">
                        <input type="hidden" id="appointmentDate" name="appointment-date" required>
                        <input type="text" id="appointmentDateDisplay" readonly placeholder="Select a date">
                        <div class="date-picker-container" id="datePickerContainer">
                            <div class="date-picker-header">
                                <button type="button" id="prevMonthBtn"><</button>
                                <span id="monthYear"></span>
                                <button type="button" id="nextMonthBtn">></button>
                            </div>
                            <div class="date-picker-days" id="datePickerDays">
                                <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                            </div>
                        </div>
                    </div>
                    <div class="date-picker-note">Only Thursdays are selectable</div>
                    <span class="error" id="appointmentDate-error"></span>
                </div>
            </div>
            <div class="button-submit">
                <button type="submit">Submit</button>
            </div>
        </form>

        <!-- Popup for Photo Upload -->
        <div class="popup-overlay" id="photoUploadPopup">
            <div class="popup-content">
                <i class="bx bx-x close-button" onclick="closePopup()"></i>
                <div class="photo-preview-container" id="photoPreviewContainer">
                    <i class="bx bx-upload"></i>
                    <p>Upload picture</p>
                    <p class="sub-text">Supports: PNG, JPG, GIF format</p>
                    <img id="popupPhotoPreview" class="photo-preview" alt="1x1 Photo Preview">
                </div>
                <div class="popup-file-input">
                    <input type="file" id="popupFileInput" accept="image/jpeg,image/jpg,image/png,image/gif">
                    <button type="button" class="choose-file-button" id="chooseFileBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sexSelect = document.getElementById("sex");
            const otherSexGroup = document.getElementById("otherSexGroup");
            const fileInput = document.getElementById("myFile");
            const popupFileInput = document.getElementById("popupFileInput");
            const popupPhotoPreview = document.getElementById("popupPhotoPreview");
            const profilePhotoImg = document.getElementById("profilePhotoImg");
            const photoPreviewContainer = document.getElementById("photoPreviewContainer");
            const popupOverlay = document.getElementById("photoUploadPopup");
            const chooseFileBtn = document.getElementById("chooseFileBtn");
            const appointmentDate = document.getElementById("appointmentDate");
            const appointmentDateDisplay = document.getElementById("appointmentDateDisplay");
            const datePickerContainer = document.getElementById("datePickerContainer");
            const datePickerDays = document.getElementById("datePickerDays");
            const monthYear = document.getElementById("monthYear");
            const prevMonthBtn = document.getElementById("prevMonthBtn");
            const nextMonthBtn = document.getElementById("nextMonthBtn");

            let currentDate = new Date();
            let selectedDate = null;

            // Toggle Other Sex input
            function toggleOtherInput() {
                otherSexGroup.style.display = sexSelect.value === "Other" ? "block" : "none";
            }
            toggleOtherInput();
            sexSelect.addEventListener("change", toggleOtherInput);

            // Popup handling
            window.openPopup = function() {
                popupOverlay.style.display = "flex";
                popupPhotoPreview.style.display = "none";
                photoPreviewContainer.querySelector('i').style.display = "block";
                photoPreviewContainer.querySelectorAll('p').forEach(p => p.style.display = "block");
                popupFileInput.value = "";
            };

            window.closePopup = function() {
                popupOverlay.style.display = "none";
            };

            chooseFileBtn.addEventListener("click", function() {
                popupFileInput.click();
            });

            // Image compression function
            function compressImage(file, maxSizeKB, callback) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const maxDimension = 300; // Limit the max dimension for profile pics
                        let width = img.width;
                        let height = img.height;

                        // Resize the image while maintaining aspect ratio
                        if (width > height) {
                            if (width > maxDimension) {
                                height = Math.round((height * maxDimension) / width);
                                width = maxDimension;
                            }
                        } else {
                            if (height > maxDimension) {
                                width = Math.round((width * maxDimension) / height);
                                height = maxDimension;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);

                        // Convert to JPEG with quality compression
                        let quality = 0.7;
                        let compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                        let sizeKB = (compressedDataUrl.length * 0.75) / 1024; // Approximate size in KB

                        // Reduce quality until the size is below maxSizeKB
                        while (sizeKB > maxSizeKB && quality > 0.1) {
                            quality -= 0.1;
                            compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                            sizeKB = (compressedDataUrl.length * 0.75) / 1024;
                        }

                        // Convert base64 to blob
                        fetch(compressedDataUrl)
                            .then(res => res.blob())
                            .then(blob => {
                                const compressedFile = new File([blob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                callback(compressedFile, compressedDataUrl);
                            });
                    };
                };
                reader.onerror = function() {
                    alert("Error reading the file. Please try again.");
                };
                reader.readAsDataURL(file);
            }

            popupFileInput.addEventListener("change", function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Compress the image regardless of size
                    compressImage(file, 100, function(compressedFile, dataUrl) {
                        // Update popup preview
                        popupPhotoPreview.src = dataUrl;
                        popupPhotoPreview.style.display = "block";
                        photoPreviewContainer.querySelector('i').style.display = "none";
                        photoPreviewContainer.querySelectorAll('p').forEach(p => p.style.display = "none");

                        // Update profile preview
                        profilePhotoImg.src = dataUrl;
                        profilePhotoImg.style.display = "block";

                        // Transfer the compressed file to the hidden input for form submission
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        fileInput.files = dataTransfer.files;

                        closePopup();
                    });
                }
            });

            // Custom Date Picker
            function renderDatePicker() {
                datePickerDays.innerHTML = '<span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>';
                const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
                const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();

                monthYear.textContent = `${currentDate.toLocaleString('default', { month: 'long' })} ${currentDate.getFullYear()}`;

                for (let i = 0; i < firstDay; i++) {
                    const emptyDay = document.createElement('div');
                    datePickerDays.appendChild(emptyDay);
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
                    const dayElement = document.createElement('div');
                    dayElement.classList.add('date-picker-day');
                    dayElement.textContent = day;

                    if (date.getDay() === 4) {
                        dayElement.classList.add('thursday');
                    }

                    if (selectedDate && date.toDateString() === selectedDate.toDateString()) {
                        dayElement.classList.add('selected');
                    }

                    dayElement.addEventListener('click', () => {
                        if (date.getDay() !== 4) return;
                        selectedDate = date;
                        appointmentDate.value = date.toISOString().split('T')[0];
                        appointmentDateDisplay.value = date.toISOString().split('T')[0];
                        datePickerContainer.style.display = 'none';
                        renderDatePicker();
                    });

                    datePickerDays.appendChild(dayElement);
                }
            }

            // Attach event listeners for month navigation
            prevMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderDatePicker();
            });

            nextMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderDatePicker();
            });

            appointmentDateDisplay.addEventListener('click', () => {
                datePickerContainer.style.display = 'block';
                renderDatePicker();
            });

            document.addEventListener('click', (e) => {
                if (!datePickerContainer.contains(e.target) && e.target !== appointmentDateDisplay) {
                    datePickerContainer.style.display = 'none';
                }
            });

            // Prevent back button caching
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

            // Prevent form resubmission
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

            // Form validation
            const form = document.getElementById("newRegisterForm");
            form.addEventListener("submit", function (e) {
                let isValid = true;
                const fields = [
                    { id: "lastname", errorId: "lastname-error", message: "Last name is required" },
                    { id: "firstname", errorId: "firstname-error", message: "First name is required" },
                    { id: "sex", errorId: "sex-error", message: "Please select a gender" },
                    { id: "region", errorId: "region-error", message: "Region is required" },
                    { id: "address", errorId: "address-error", message: "Address is required" },
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

                const phoneInput = document.getElementById("phonenumber");
                const phoneError = document.getElementById("phonenumber-error");
                if (phoneInput.value.length !== 10) {
                    phoneError.textContent = "Phone number must be 10 digits";
                    phoneError.style.display = "block";
                    isValid = false;
                }

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

                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        document.getElementById("myFile-error").textContent = "Please upload a JPEG, PNG, or GIF image";
                        document.getElementById("myFile-error").style.display = "block";
                        isValid = false;
                    }
                } else {
                    document.getElementById("myFile-error").textContent = "Please upload a photo";
                    document.getElementById("myFile-error").style.display = "block";
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>