<?php
$page_title = 'Databases';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Manage Databases</h1>
        <p>Manage large amounts of information over the web easily. Databases are necessary to run many web-based
            applications.</p>
    </div>

    <!-- Create Database Section -->
    <div class="db-section">
        <h2>Create New Database</h2>
        <div class="db-form-group">
            <label for="serviceSelect">Select Application (Service):</label>
            <select id="serviceSelect" class="form-control"
                style="width: auto; min-width: 250px; display: inline-block;">
                <option value="">Loading services...</option>
            </select>
        </div>
        <div class="db-form-group">
            <label for="dbType">Database Type:</label>
            <select id="dbType" class="form-control" style="width: auto; display: inline-block;">
                <option value="mysql">MySQL</option>
                <option value="postgresql">PostgreSQL</option>
                <option value="mongodb">MongoDB</option>
            </select>
        </div>
        <div class="db-form-group">
            <label for="newDbName">New Database:</label>
            <div class="input-group">
                <span class="input-group-addon"><?= htmlspecialchars($_SESSION['user_name'] ?? 'user') ?>_</span>
                <input type="text" id="newDbName" class="form-control" placeholder="dbname" style="max-width: 300px;">
            </div>
            <button class="btn btn-primary" onclick="createDatabase()">Create Database</button>
        </div>
    </div>

    <!-- Current Databases Section -->
    <div class="db-section">
        <h2>Current Databases</h2>
        <div class="db-toolbar">
            <input type="text" id="searchDb" class="form-control" placeholder="Search" style="width: 200px;">
            <button class="btn btn-default">Go</button>
        </div>

        <table class="db-table" id="dbTable">
            <thead>
                <tr>
                    <th>Database</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Privileged Users</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody id="dbList">
                <!-- DBs will be loaded here -->
                <tr>
                    <td colspan="5" class="text-center">Loading databases...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .db-container {
        padding: 0 15px;
    }

    .db-page-header {
        margin-bottom: 30px;
    }

    .db-page-header h1 {
        font-size: 28px;
        font-weight: 400;
        color: var(--text-primary);
        margin: 0 0 10px 0;
    }

    .db-page-header p {
        color: var(--text-secondary);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .db-section {
        margin-bottom: 40px;
    }

    .db-section h2 {
        font-size: 22px;
        font-weight: 300;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
        margin-bottom: 20px;
        color: var(--text-primary);
    }

    .db-form-group {
        margin-bottom: 15px;
    }

    .db-form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }

    .form-control {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        color: #333;
    }

    .input-group {
        display: inline-flex;
        align-items: center;
        margin-right: 10px;
    }

    .input-group-addon {
        padding: 8px 12px;
        font-size: 14px;
        font-weight: normal;
        line-height: 1;
        color: #555;
        text-align: center;
        background-color: #eee;
        border: 1px solid #ccc;
        border-right: 0;
        border-radius: 4px 0 0 4px;
    }

    .input-group input {
        border-radius: 0 4px 4px 0;
    }

    .btn {
        display: inline-block;
        padding: 8px 16px;
        margin-bottom: 0;
        font-size: 14px;
        font-weight: normal;
        line-height: 1.42857143;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        cursor: pointer;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .btn-primary {
        color: #fff;
        background-color: #3C873A;
        /* LogicPanel Green */
        border-color: #3C873A;
    }

    .btn-primary:hover {
        background-color: #2D6A2E;
        border-color: #2D6A2E;
    }

    .btn-default {
        color: #333;
        background-color: #fff;
        border-color: #ccc;
    }

    .db-toolbar {
        margin-bottom: 15px;
        display: flex;
        gap: 5px;
        align-items: center;
    }

    /* Table Styles */
    .db-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
        background-color: #fff;
        white-space: nowrap;
    }

    .db-table th {
        background-color: #f7f7f7;
        color: #333;
        font-weight: bold;
        text-align: left;
        padding: 10px;
        border-bottom: 2px solid #ddd;
    }

    .db-table td {
        padding: 10px;
        border-top: 1px solid #eee;
        color: #333;
        vertical-align: middle;
    }

    /* Actions Column */
    .actions-col {
        text-align: right;
        min-width: 150px;
    }

    .action-link {
        color: #337ab7;
        text-decoration: none;
        margin-left: 10px;
        font-size: 13px;
    }

    .action-link:hover {
        text-decoration: underline;
    }

    .action-icon {
        margin-right: 3px;
    }

    .text-danger {
        color: #d9534f;
    }

    /* Table Responsive Wrapper */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 10px;
    }

    /* Icons */
    .fa-trash {
        color: #d9534f;
    }

    .fa-edit {
        color: #337ab7;
    }

    /* Mobile Responsive */
    @media (max-width: 600px) {
        .db-container {
            padding: 10px;
        }

        .db-page-header h1 {
            font-size: 22px;
        }

        .db-section {
            margin-bottom: 20px;
            padding: 15px;
        }

        .db-toolbar {
            flex-wrap: wrap;
        }

        #searchDb {
            width: 100% !important;
            margin-bottom: 5px;
        }

        .db-toolbar button {
            width: 100%;
        }

        .input-group {
            display: flex;
            width: 100%;
        }

        .input-group input {
            flex: 1;
            min-width: 0;
        }

        select.form-control {
            width: 100% !important;
            display: block !important;
        }

        .db-table th,
        .db-table td {
            padding: 8px;
            font-size: 13px;
        }
    }
</style>

<script>
    // Constants from Layout - Fix API base URL
    const apiUrl = (window.base_url || '') + '/api';
    const apiToken = window.apiToken || document.querySelector('meta[name="api-token"]')?.getAttribute('content');

    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiToken}`
    };

    document.addEventListener('DOMContentLoaded', () => {
        loadServices(); // Load services for dropdown
        loadDatabases(); // Load existing databases
    });

    // 1. Load Services for Dropdown
    async function loadServices() {
        try {
            const res = await fetch(`${apiUrl}/services`, { headers });
            const data = await res.json();

            const select = document.getElementById('serviceSelect');
            let options = '<option value="">-- Standalone (No App Linked) --</option>';

            if (res.ok && data.services) {
                options += data.services.map(s =>
                    `<option value="${s.id}">${s.name} (${s.domain || 'no domain'})</option>`
                ).join('');
            }
            select.innerHTML = options;
        } catch (e) {
            console.error('Failed to load services', e);
        }
    }

    // 3. Create Database
    async function createDatabase() {
        const serviceId = document.getElementById('serviceSelect').value;
        const type = document.getElementById('dbType').value;
        const namePart = document.getElementById('newDbName').value.trim();
        const btn = document.querySelector('button[onclick="createDatabase()"]');

        // Disable button
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';

        try {
            // Determine API URL based on service selection
            let url = `${apiUrl}/databases`;
            if (serviceId) {
                url = `${apiUrl}/services/${serviceId}/databases`;
            }

            const res = await fetch(url, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    type: type,
                    name: namePart
                })
            });

            const data = await res.json();

            if (res.ok) {
                // Success Modal with Password
                await showCustomAlert({
                    title: 'Database Created!',
                    html: `
                        <div style="text-align:left; background:#f5f5f5; padding:15px; border-radius:5px; border: 1px solid #ddd;">
                            <p><strong>Database:</strong> <span style="color:#2563eb">${data.database.name}</span></p>
                            <p><strong>User:</strong> <span style="color:#059669">${data.database.user}</span></p>
                            <p><strong>Password:</strong> <code style="background:#fff;padding:2px 5px;font-size:1.1em;color:#d00;border:1px solid #ccc;border-radius:4px">${data.database.password}</code></p>
                            <p><strong>Host:</strong> ${data.database.host}:${data.database.port}</p>
                        </div>
                        <p style="color:red; font-size:11px; margin-top:10px;"><i data-lucide="alert-triangle"></i> NOTIFICATION: Please save these credentials. We don't store plain text passwords.</p>
                    `
                });

                document.getElementById('newDbName').value = '';
                loadDatabases();
            } else {
                showCustomAlert({ title: 'Failed', message: data.message || 'Could not create database.' });
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error occurred.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // 4. Delete Database
    async function deleteDb(id) {
        if (await showCustomConfirm('Delete Database?', "You won't be able to revert this! All data will be lost.", true)) {
            try {
                const res = await fetch(`${apiUrl}/databases/${id}`, {
                    method: 'DELETE',
                    headers
                });

                if (res.ok) {
                    showNotification('Database has been deleted.', 'success');
                    loadDatabases();
                } else {
                    const data = await res.json();
                    showNotification(data.message || 'Failed to delete database.', 'error');
                }
            } catch (e) {
                showNotification('Network error.', 'error');
            }
        }
    }

    function checkDb(id) {
        Swal.fire('Connection Check', 'Feature coming soon.', 'info');
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>