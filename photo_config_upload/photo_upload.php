<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if the user is logged in
if (empty($_SESSION['status_Account'])) {
    header("Location: index.php");
    exit();
}

// Handle photo upload
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePhoto'])) {
    $file = $_FILES['profilePhoto'];
    $validTypes = ['image/jpeg'];
    $uploadDir = '../ProfileImage/image/Profile_Photo/';
    $fileName = uniqid('profile_{id}', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $fileName;

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Validate file type and upload errors
    if (!in_array($file['type'], $validTypes)) {
        $error = "Please upload a valid .jpg or .jpeg file.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed: " . [
            UPLOAD_ERR_INI_SIZE => "File exceeds server size limit.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form size limit.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Temporary folder missing.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
        ][$file['error']] ?? "Unknown error.";
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $error = "Security error: Invalid file upload.";
    } else {
        // Validate image dimensions (2x2 inch at 96 DPI ~ 192x192 pixels)
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $error = "Invalid image file. Please upload a valid JPEG.";
        } elseif ($imageInfo[0] < 180 || $imageInfo[1] < 180 || abs($imageInfo[0] - $imageInfo[1]) > 20) {
            $error = "Image must be approximately 2x2 inches (~192x192 pixels at 96 DPI).";
        } else {
            // Attempt server-side compression and save to folder
            if (extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($file['tmp_name']);
                if ($image === false) {
                    $error = "Failed to process image. Try another file.";
                } else {
                    // Save compressed image to folder
                    if (imagejpeg($image, $uploadPath, 75)) {
                        imagedestroy($image);
                        $_SESSION['profilePhoto'] = $uploadPath;
                        header("Location: ../photo_config_upload/photo_upload.php");
                        exit();
                    } else {
                        imagedestroy($image);
                        $error = "Failed to save image to server.";
                    }
                }
            } else {
                // Fallback: Save client-side compressed image to folder
                if (!empty($_POST['compressedImage'])) {
                    $data = $_POST['compressedImage'];
                    // Remove data URI scheme prefix
                    $data = str_replace('data:image/jpeg;base64,', '', $data);
                    $data = base64_decode($data);
                    if (file_put_contents($uploadPath, $data)) {
                        $_SESSION['profilePhoto'] = $uploadPath;
                        header("Location: ../photo_config_upload/photo_upload.php");
                        exit();
                    } else {
                        $error = "Failed to save compressed image to server.";
                    }
                } else {
                    $error = "Server image processing unavailable. Ensure client-side compression is enabled.";
                }
            }
        }
    }
}

// Handle photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['removePhoto'])) {
    if (!empty($_SESSION['profilePhoto']) && file_exists($_SESSION['profilePhoto'])) {
        unlink($_SESSION['profilePhoto']); // Delete the file from the server
    }
    unset($_SESSION['profilePhoto']);
    header("Location: ../photo_config_upload/photo_upload.php");
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
    <title>Upload Profile Photo</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        /* CSS Variables */
        :root {
            --primary-color: #003087; /* Deep blue for a formal look */
            --primary-hover: #00205b;
            --secondary-color: #6b7280; /* Muted gray for secondary elements */
            --error-color: #b91c1c; /* Muted red for errors */
            --success-color: #15803d; /* Muted green for success */
            --border-color: #d1d5db;
            --bg-light: #f9fafb;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --text-color: #1f2937;
        }

        /* General Layout */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .form-container {
            background: #fff;
            max-width: 500px;
            width: 100%;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        h1 {
            font-size: 22px;
            font-weight: 500;
            color: var(--text-color);
            text-align: center;
            margin-bottom: 20px;
        }

        /* Logo */
        .logo {
            width: 80px;
            height: auto;
            display: block;
            margin: 0 auto 20px;
        }

        /* Photo Preview */
        .photo-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }

        .photo-placeholder {
            position: relative;
            width: 192px;
            height: 192px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: var(--secondary-color);
            text-align: center;
        }

        .photo-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 4px;
            object-fit: cover;
            display: none;
        }

        .photo-preview.active {
            display: block;
        }

        .photo-note {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 8px;
            text-align: center;
        }

        /* Buttons */
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .choose-file-button,
        .remove-photo-button,
        .back-button {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
            width: 200px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .remove-photo-button {
            background: var(--error-color);
        }

        .back-button {
            background: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .choose-file-button:hover,
        .remove-photo-button:hover {
            background: var(--primary-hover);
        }

        .remove-photo-button:hover {
            background: #991b1b;
        }

        .back-button:hover {
            background: var(--primary-color);
            color: #fff;
        }

        /* Feedback Messages */
        .error-message,
        .success-message {
            font-size: 13px;
            padding: 8px;
            border-radius: 4px;
            margin: 10px 0;
            text-align: center;
            display: none;
        }

        .error-message {
            color: var(--error-color);
            background: #fef2f2;
        }

        .success-message {
            color: var(--success-color);
            background: #f0fdf4;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: none;
            border: 3px solid #e5e7eb;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                max-width: 400px;
            }

            h1 {
                font-size: 20px;
            }

            .logo {
                width: 60px;
            }

            .photo-placeholder,
            .photo-preview {
                width: 150px;
                height: 150px;
            }

            .photo-note {
                font-size: 11px;
            }

            .choose-file-button,
            .remove-photo-button,
            .back-button {
                width: 180px;
                font-size: 13px;
                padding: 8px 16px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
        }

        /* Accessibility */
        .choose-file-button:focus,
        .remove-photo-button:focus,
        .back-button:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="form-container" role="main">
        <img src="../image/icons/logo1.ico" alt="Organization Logo" class="logo">
        <h1>Upload Profile Photo</h1>
        <form id="photoUploadForm" action="../photo_config_upload/photo_upload.php" method="POST" enctype="multipart/form-data" aria-label="Profile Photo Upload Form">
            <input type="hidden" id="compressedImage" name="compressedImage">
            <div class="photo-preview-container">
                <div class="photo-placeholder" id="photoPlaceholder">
                    <img id="photoPreview" class="photo-preview <?php echo !empty($_SESSION['profilePhoto']) ? 'active' : ''; ?>" alt="Profile Photo Preview" src="<?php echo !empty($_SESSION['profilePhoto']) ? htmlspecialchars($_SESSION['profilePhoto']) : ''; ?>" aria-hidden="<?php echo empty($_SESSION['profilePhoto']) ? 'true' : 'false'; ?>">
                    <?php if (empty($_SESSION['profilePhoto'])): ?>
                        <span>No Photo Uploaded</span>
                    <?php endif; ?>
                </div>
                <div class="photo-note">Requirements: 2x2 inch JPG/JPEG (~192x192 pixels)</div>
            </div>
            <div class="button-group">
                <input type="file" id="fileInput" name="profilePhoto" accept="image/jpeg" style="display: none;" aria-label="Select profile photo">
                <button type="button" class="choose-file-button" id="chooseFileBtn" aria-label="Choose photo file">Select Photo</button>
                <div class="loading-spinner" id="loadingSpinner" aria-label="Processing"></div>
                <?php if (!empty($_SESSION['profilePhoto'])): ?>
                    <div class="action-buttons">
                        <button type="button" class="remove-photo-button" id="removePhotoBtn" aria-label="Remove uploaded photo">Remove Photo</button>
                    </div>
                <?php endif; ?>
                <div class="error-message" id="uploadError" role="alert"><?php echo isset($error) ? htmlspecialchars($error) : ''; ?></div>
                <div class="success-message" id="uploadSuccess" role="alert">Photo uploaded successfully!</div>
                <a href="../fillupform/fillupform.php" class="back-button" aria-label="Back to registration form">Back to Form</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const fileInput = document.getElementById("fileInput");
            const photoPreview = document.getElementById("photoPreview");
            const photoPlaceholder = document.getElementById("photoPlaceholder");
            const chooseFileBtn = document.getElementById("chooseFileBtn");
            const removePhotoBtn = document.getElementById("removePhotoBtn");
            const uploadError = document.getElementById("uploadError");
            const uploadSuccess = document.getElementById("uploadSuccess");
            const loadingSpinner = document.getElementById("loadingSpinner");
            const form = document.getElementById("photoUploadForm");
            const compressedImageInput = document.getElementById("compressedImage");

            // Choose file button handler
            chooseFileBtn.addEventListener("click", function() {
                fileInput.click();
            });

            // Image compression and resizing function
            function compressAndResizeImage(file, maxSizeMB, targetSize, callback) {
                const maxSizeBytes = maxSizeMB * 1024 * 1024;
                loadingSpinner.style.display = "block";

                const img = new Image();
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.onload = function() {
                        try {
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');

                            // Calculate dimensions
                            let width = img.width;
                            let height = img.height;
                            const targetDimension = targetSize;

                            // Resize to 1:1 aspect ratio
                            const size = Math.min(width, height);
                            canvas.width = targetDimension;
                            canvas.height = targetDimension;

                            // Center and crop
                            const offsetX = (width - size) / 2;
                            const offsetY = (height - size) / 2;
                            ctx.drawImage(img, offsetX, offsetY, size, size, 0, 0, targetDimension, targetDimension);

                            // Progressive compression
                            let quality = 0.85;
                            let compressedDataUrl;
                            do {
                                compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                                quality -= 0.05;
                            } while (compressedDataUrl.length / 4 * 3 > maxSizeBytes && quality > 0.1);

                            fetch(compressedDataUrl)
                                .then(res => res.blob())
                                .then(blob => {
                                    const compressedFile = new File([blob], file.name, { type: 'image/jpeg' });
                                    callback(compressedFile, compressedDataUrl);
                                    loadingSpinner.style.display = "none";
                                })
                                .catch(err => {
                                    uploadError.textContent = "Error compressing image: " + err.message;
                                    uploadError.style.display = "block";
                                    loadingSpinner.style.display = "none";
                                });
                        } catch (err) {
                            uploadError.textContent = "Error processing image: " + err.message;
                            uploadError.style.display = "block";
                            loadingSpinner.style.display = "none";
                        }
                    };
                    img.onerror = function() {
                        uploadError.textContent = "Invalid image file. Please upload a valid JPEG.";
                        uploadError.style.display = "block";
                        loadingSpinner.style.display = "none";
                    };
                };
                reader.onerror = function() {
                    uploadError.textContent = "Error reading file. Please try again.";
                    uploadError.style.display = "block";
                    loadingSpinner.style.display = "none";
                };
                reader.readAsDataURL(file);
            }

            // File input change handler
            fileInput.addEventListener("change", function(e) {
                const file = e.target.files[0];
                uploadError.style.display = "none";
                uploadSuccess.style.display = "none";

                if (file) {
                    // Validate file type
                    const validTypes = ['image/jpeg'];
                    if (!validTypes.includes(file.type)) {
                        uploadError.textContent = "Please upload a .jpg or .jpeg file.";
                        uploadError.style.display = "block";
                        return;
                    }

                    // Compress and resize image to 192x192 (~300KB)
                    compressAndResizeImage(file, 0.3, 192, function(compressedFile, dataUrl) {
                        photoPreview.src = dataUrl;
                        photoPreview.classList.add('active');
                        photoPreview.setAttribute('aria-hidden', 'false');

                        // Update file input with compressed file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        fileInput.files = dataTransfer.files;

                        // Store compressed image for fallback
                        compressedImageInput.value = dataUrl;

                        // Show success and submit
                        uploadSuccess.style.display = "block";
                        setTimeout(() => {
                            form.submit();
                        }, 1000);
                    });
                }
            });

            // Remove photo button handler
            if (removePhotoBtn) {
                removePhotoBtn.addEventListener("click", function() {
                    const removeForm = new FormData();
                    removeForm.append('removePhoto', 'true');
                    fetch('photo_upload.php', {
                        method: 'POST',
                        body: removeForm
                    })
                        .then(response => {
                            if (response.ok) {
                                window.location.reload();
                            } else {
                                uploadError.textContent = "Error removing photo. Please try again.";
                                uploadError.style.display = "block";
                            }
                        })
                        .catch(err => {
                            uploadError.textContent = "Network error: " + err.message;
                            uploadError.style.display = "block";
                        });
                });

                removePhotoBtn.addEventListener("keydown", function(e) {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        removePhotoBtn.click();
                    }
                });
            }

            // Keyboard accessibility for choose file button
            chooseFileBtn.addEventListener("keydown", function(e) {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    fileInput.click();
                }
            });

            // Initialize error and success message visibility
            if (uploadError.textContent) {
                uploadError.style.display = "block";
            }
            if (uploadSuccess.textContent && <?php echo !empty($_SESSION['profilePhoto']) ? 'true' : 'false'; ?>) {
                uploadSuccess.style.display = "block";
            }
        });
    </script>
</body>
</html>