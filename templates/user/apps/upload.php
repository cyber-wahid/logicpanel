<?php
// templates/apps/upload.php - Dedicated Upload Page (cPanel Style)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lp_session_token'])) {
    header('Location: /login');
    exit;
}

$serviceId = $_GET['id'] ?? null;
$path = $_GET['path'] ?? '/';

if (!$serviceId) {
    die("Service ID missing");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="auth-token" content="<?php echo htmlspecialchars($_SESSION['lp_session_token'] ?? ''); ?>">
    <title>File Upload - LogicPanel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f5f7;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .header {
            background-color: #1E2127;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .upload-info {
            margin-bottom: 20px;
        }

        .upload-info h2 {
            margin-top: 0;
            font-size: 20px;
            font-weight: 400;
        }

        .info-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }

        .drop-zone {
            border: 2px dashed #ccc;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 20px;
            background: #fafafa;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .drop-zone.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .drop-zone p {
            font-size: 16px;
            color: #666;
            margin: 10px 0;
        }

        .btn {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-link {
            background: none;
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
            padding: 0;
        }

        .btn-link:hover {
            text-decoration: underline;
        }

        .progress-list {
            margin-top: 20px;
        }

        .progress-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 10px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            font-size: 13px;
            display: block;
        }

        .file-status {
            font-size: 11px;
            color: #666;
        }

        .progress-bar-container {
            width: 50%;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: #28a745;
            width: 0%;
            transition: width 0.2s;
        }

        .progress-bar.error {
            background: #dc3545;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="header">
        <i class="fa fa-cloud-upload-alt"></i> File Upload
    </div>

    <div class="container">

        <div class="upload-info">
            <h2>Select the file you want to upload to "
                <?php echo htmlspecialchars($path); ?>".
            </h2>

            <div class="info-box">
                Maximum file size allowed for upload: 512 MB
            </div>

            <label style="font-size:13px; display:block; margin-bottom:10px;">
                <input type="checkbox" id="overwrite"> Overwrite existing files
            </label>
        </div>

        <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
            <p>Drop files here to start uploading</p>
            <p>or</p>
            <button class="btn">Select File</button>
            <input type="file" id="fileInput" multiple style="display:none">
        </div>

        <div class="progress-list" id="progressList">
            <!-- Upload items will appear here -->
        </div>

        <div class="back-link">
            <a href="#" onclick="goBack()" class="btn-link"><i class="fa fa-arrow-left"></i> Go Back to File Manager</a>
        </div>

    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const progressList = document.getElementById('progressList');
        const overwriteCheck = document.getElementById('overwrite');

        const serviceId = "<?php echo $serviceId; ?>";
        const currentPath = "<?php echo htmlspecialchars($path); ?>";
        const baseUrl = window.location.origin;
        let uploadCount = 0;

        function goBack() {
            if (window.opener && !window.opener.closed && window.opener.loadFiles) {
                // Reload the specific path if possible, or just reload
                try {
                    window.opener.loadFiles(currentPath);
                    window.opener.focus();
                } catch (e) {
                    console.log('Parent refresh failed', e);
                }
                window.close();
            } else {
                window.location.href = `/apps/files?id=${serviceId}`;
            }
        }

        // Also try to refresh parent when window is closed/unloaded if uploads happened
        window.addEventListener('beforeunload', function () {
            if (uploadCount > 0 && window.opener && !window.opener.closed) {
                try {
                    // We can't easily wait for this, but it might fire
                    window.opener.loadFiles(currentPath);
                } catch (e) { }
            }
        });

        // Auth
        function getAuthToken() {
            return document.querySelector('meta[name="auth-token"]').getAttribute('content');
        }

        // Drag & Drop events
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            Array.from(files).forEach(uploadFile);
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('path', currentPath);
            if (overwriteCheck.checked) formData.append('overwrite', '1');

            const id = Math.random().toString(36).substring(7);

            // Create UI Element
            const div = document.createElement('div');
            div.className = 'progress-item';
            div.id = `upload-${id}`;
            div.innerHTML = `
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-status" id="status-${id}">Starting...</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="bar-${id}" style="width: 0%"></div>
                </div>
                <div style="width: 40px; text-align: right; font-size: 12px;" id="percent-${id}">0%</div>
            `;
            progressList.prepend(div);

            // Upload via XHR
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${baseUrl}/api/services/${serviceId}/files/upload`, true);
            xhr.setRequestHeader('Authorization', `Bearer ${getAuthToken()}`);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    document.getElementById(`bar-${id}`).style.width = percent + '%';
                    document.getElementById(`percent-${id}`).innerText = percent + '%';
                    document.getElementById(`status-${id}`).innerText = 'Uploading...';
                }
            };

            xhr.onload = () => {
                const statusEl = document.getElementById(`status-${id}`);
                const barEl = document.getElementById(`bar-${id}`);

                try {
                    const res = JSON.parse(xhr.responseText);
                    if (xhr.status === 200 && !res.error) {
                        statusEl.innerText = 'Complete';
                        statusEl.style.color = 'green';
                        barEl.style.background = '#28a745';
                        document.getElementById(`percent-${id}`).innerText = '100%';
                        document.getElementById(`bar-${id}`).style.width = '100%';
                        uploadCount++; // Increment count
                    } else {
                        statusEl.innerText = 'Failed: ' + (res.error || 'Unknown error');
                        statusEl.style.color = '#d9534f';
                        barEl.classList.add('error');
                    }
                } catch (e) {
                    statusEl.innerText = 'Failed: Server error';
                    statusEl.style.color = '#d9534f';
                    barEl.classList.add('error');
                }
            };

            xhr.onerror = () => {
                const statusEl = document.getElementById(`status-${id}`);
                statusEl.innerText = 'Network Error';
                statusEl.style.color = '#d9534f';
                document.getElementById(`bar-${id}`).classList.add('error');
            };

            xhr.send(formData);
        }
    </script>
</body>

</html>