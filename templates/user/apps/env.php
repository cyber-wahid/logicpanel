<?php
$page_title = 'Environment Variables';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Environment Variables</h1>
        <p>Manage environment variables for your application. Changes will be saved to a .env file.</p>
    </div>

    <div class="db-section">
        <div class="db-toolbar" style="justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="serviceSelect" class="form-control" style="width: 250px;" onchange="loadEnvVars()">
                    <option value="">Select Application</option>
                </select>
            </div>
            <div>
                <button class="btn btn-primary" onclick="addEnvRow()">
                    <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Variable
                </button>
            </div>
        </div>

        <div id="envEditor" style="display: none;">
            <div class="env-info-box">
                <i data-lucide="info" style="width:16px;height:16px;"></i>
                <span>Variables are saved to <code>.env</code> file in your app directory. Restart the app after making
                    changes.</span>
            </div>

            <div class="env-table-container">
                <table class="db-table" id="envTable">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Variable Name</th>
                            <th style="width: 50%;">Value</th>
                            <th style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="envList">
                        <tr>
                            <td colspan="3" class="text-center">No environment variables defined</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-primary" onclick="saveEnvVars()">
                    <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
                </button>
                <button class="btn btn-secondary" onclick="restartApp()">
                    <i data-lucide="refresh-cw" style="width:16px;height:16px;"></i> Restart App
                </button>
            </div>
        </div>

        <div id="noAppSelected" class="text-center" style="padding: 40px; color: var(--text-secondary);">
            <i data-lucide="box" style="width:48px;height:48px;opacity:0.5;"></i>
            <p style="margin-top: 10px;">Select an application above to manage its environment variables</p>
        </div>
    </div>
</div>

<style>
    .db-container {
        padding: 10px;
        width: 100%;
        max-width: 100%;
    }

    .db-page-header h1 {
        font-size: 20px;
        font-weight: 500;
        margin: 0 0 5px 0;
        color: var(--text-primary);
    }

    .db-page-header p {
        color: var(--text-secondary);
        font-size: 13px;
        margin-bottom: 20px;
    }

    .db-section {
        margin-bottom: 20px;
        background: var(--bg-card);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .form-control {
        padding: 8px 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
        height: 36px;
        font-size: 14px;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        border: 1px solid transparent;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-primary {
        background-color: #3C873A;
        border-color: #3C873A;
        color: #fff;
    }

    .btn-secondary {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    .btn-danger {
        background: #d9534f;
        border-color: #d9534f;
        color: #fff;
        padding: 6px 10px;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
        height: 28px;
    }

    .env-info-box {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        color: #0369a1;
        padding: 10px 15px;
        border-radius: 6px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    [data-theme="dark"] .env-info-box {
        background: rgba(56, 189, 248, 0.1);
        border-color: rgba(56, 189, 248, 0.3);
        color: #7dd3fc;
    }

    .env-table-container {
        border: 1px solid var(--border-color);
        border-radius: 6px;
        overflow: hidden;
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .db-table th {
        background: var(--bg-input);
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-weight: 600;
    }

    .db-table td {
        padding: 8px 10px;
        border-top: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .env-input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
    }

    .env-input:focus {
        border-color: #3C873A;
        outline: none;
    }

    .env-key {
        text-transform: uppercase;
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const apiUrl = (window.base_url || '') + '/api';
    const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
    const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };

    let currentServiceId = null;
    let envVars = {};

    document.addEventListener('DOMContentLoaded', () => {
        loadServices();
        if (window.lucide) lucide.createIcons();
    });

    async function loadServices() {
        try {
            const res = await fetch(`${apiUrl}/services`, { headers });
            const data = await res.json();
            const select = document.getElementById('serviceSelect');

            if (res.ok && data.services && data.services.length > 0) {
                select.innerHTML = '<option value="">Select Application</option>' +
                    data.services.map(s => `<option value="${s.id}">${s.name} (${s.domain})</option>`).join('');
            } else {
                select.innerHTML = '<option value="">No applications found</option>';
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadEnvVars() {
        const serviceId = document.getElementById('serviceSelect').value;

        if (!serviceId) {
            document.getElementById('envEditor').style.display = 'none';
            document.getElementById('noAppSelected').style.display = 'block';
            return;
        }

        currentServiceId = serviceId;
        document.getElementById('envEditor').style.display = 'block';
        document.getElementById('noAppSelected').style.display = 'none';

        try {
            const res = await fetch(`${apiUrl}/services/${serviceId}`, { headers });
            const data = await res.json();

            if (res.ok && data.service) {
                envVars = data.service.env_vars || {};
                renderEnvTable();
            }
        } catch (e) {
            console.error(e);
            showNotification('Failed to load environment variables', 'error');
        }
    }

    function renderEnvTable() {
        const tbody = document.getElementById('envList');
        const keys = Object.keys(envVars);

        if (keys.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center" style="padding:20px;color:var(--text-secondary);">No environment variables defined. Click "Add Variable" to get started.</td></tr>';
            return;
        }

        tbody.innerHTML = keys.map(key => `
            <tr data-key="${key}">
                <td>
                    <input type="text" class="env-input env-key" value="${escapeHtml(key)}" data-original="${key}" onchange="updateKey(this, '${key}')">
                </td>
                <td>
                    <input type="text" class="env-input" value="${escapeHtml(envVars[key])}" onchange="updateValue(this, '${key}')">
                </td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteEnvRow('${key}')">
                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    function addEnvRow() {
        // Generate unique key
        let newKey = 'NEW_VARIABLE';
        let counter = 1;
        while (envVars.hasOwnProperty(newKey)) {
            newKey = `NEW_VARIABLE_${counter++}`;
        }
        envVars[newKey] = '';
        renderEnvTable();

        // Focus the new input
        const inputs = document.querySelectorAll('.env-key');
        if (inputs.length > 0) {
            inputs[inputs.length - 1].focus();
            inputs[inputs.length - 1].select();
        }
    }

    function updateKey(input, oldKey) {
        const newKey = input.value.toUpperCase().replace(/[^A-Z0-9_]/g, '_');
        input.value = newKey;

        if (newKey !== oldKey && newKey) {
            const value = envVars[oldKey];
            delete envVars[oldKey];
            envVars[newKey] = value;
        }
    }

    function updateValue(input, key) {
        // Find current key (might have changed)
        const row = input.closest('tr');
        const keyInput = row.querySelector('.env-key');
        const currentKey = keyInput.value;
        envVars[currentKey] = input.value;
    }

    function deleteEnvRow(key) {
        delete envVars[key];
        renderEnvTable();
    }

    async function saveEnvVars() {
        if (!currentServiceId) {
            showNotification('Please select an application first', 'error');
            return;
        }

        // Collect current values from inputs
        const rows = document.querySelectorAll('#envList tr[data-key]');
        const newEnvVars = {};
        rows.forEach(row => {
            const keyInput = row.querySelector('.env-key');
            const valueInput = row.querySelector('.env-input:not(.env-key)');
            if (keyInput && valueInput && keyInput.value.trim()) {
                newEnvVars[keyInput.value.trim().toUpperCase()] = valueInput.value;
            }
        });

        try {
            const res = await fetch(`${apiUrl}/services/${currentServiceId}`, {
                method: 'PUT',
                headers,
                body: JSON.stringify({ env_vars: newEnvVars })
            });

            const data = await res.json();

            if (res.ok) {
                envVars = newEnvVars;
                showNotification('Environment variables saved! .env file created.', 'success');
            } else {
                showNotification(data.error || 'Failed to save', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        }
    }

    async function restartApp() {
        if (!currentServiceId) return;

        try {
            const res = await fetch(`${apiUrl}/services/${currentServiceId}/restart`, {
                method: 'POST',
                headers
            });

            if (res.ok) {
                showNotification('Application restarting...', 'success');
            } else {
                showNotification('Failed to restart application', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>