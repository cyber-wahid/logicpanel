<?php
$page_title = 'MongoDB Databases';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>MongoDB® Databases</h1>
        <p>A source-available cross-platform document-oriented database program.</p>
    </div>

    <!-- Create Database Section -->
    <div class="db-section">
        <h2>Create New Database</h2>
        <div class="db-form-group">
            <label for="serviceSelect">Select Application (Service):</label>
            <select id="serviceSelect" class="form-control" style="width: 100%;">
                <option value="">Loading services...</option>
            </select>
        </div>

        <div class="db-form-group">
            <div
                style="background:var(--bg-input); border:1px dashed var(--border-color); padding:10px; border-radius:4px; font-size:13px; color:var(--text-secondary);">
                <i data-lucide="info" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;"></i>
                Database name, user, and password will be auto-generated for security.
            </div>
        </div>

        <div class="db-form-group">
            <button class="btn btn-primary btn-block-mobile" onclick="createDatabase()">Create Database</button>
        </div>
    </div>

    <!-- Current Databases Section -->
    <div class="db-section">
        <h2>Current Databases</h2>
        <div class="db-toolbar">
            <input type="text" id="searchDb" class="form-control" placeholder="Search">
            <button class="btn btn-default" style="margin-left: 5px;">Go</button>
        </div>

        <div
            style="background:#fff3cd; color:#856404; padding:12px; border:1px solid #ffeeba; border-radius:4px; font-size:13px; margin-bottom:15px; display:flex; gap:10px; align-items:center;">
            <i data-lucide="alert-triangle" style="width:18px;height:18px;min-width:18px;"></i>
            <div>
                <strong>Note:</strong> MongoDB cannot be managed via the web interface (Adminer). Please use <a
                    href="https://www.mongodb.com/products/tools/compass" target="_blank"
                    style="color:#533f03;text-decoration:underline;">MongoDB Compass</a> or your application to connect.
                Use the "Copy Connection String" button below to get the connection details.
            </div>
        </div>

        <div class="table-responsive">
            <table class="db-table" id="dbTable">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th>Size</th>
                        <th>User</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="dbList">
                    <tr>
                        <td colspan="4" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Force box-sizing everywhere in this container */
    .db-container,
    .db-container * {
        box-sizing: border-box;
    }

    .db-container {
        padding: 10px;
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    /* Headers */
    .db-page-header h1 {
        font-size: 20px;
        font-weight: 500;
        margin: 0 0 5px 0;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .db-page-header p {
        color: var(--text-secondary);
        font-size: 13px;
        margin-bottom: 20px;
        line-height: 1.4;
    }

    /* Sections */
    .db-section {
        margin-bottom: 20px;
        background: var(--bg-card);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        width: 100%;
        max-width: 100%;
    }

    .db-section h2 {
        font-size: 16px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 15px;
        color: var(--text-primary);
    }

    /* Forms */
    .db-form-group {
        margin-bottom: 15px;
        width: 100%;
    }

    .db-form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 5px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .form-control {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
        height: 36px;
        font-size: 14px;
    }

    /* Input Group - Responsive Fix */
    .input-group {
        display: flex;
        width: 100%;
        position: relative;
    }

    .input-group-addon {
        padding: 8px 10px;
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-right: 0;
        border-radius: 4px 0 0 4px;
        color: var(--text-secondary);
        font-size: 14px;
        height: 36px;
        display: flex;
        align-items: center;
        white-space: nowrap;
        background-color: #f3f4f6;
        max-width: 40%;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .input-group input {
        border-radius: 0 4px 4px 0;
        flex: 1;
        min-width: 0;
    }

    /* Buttons */
    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        border: 1px solid transparent;
        color: #fff;
        height: 36px;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .btn-primary {
        background-color: #3C873A;
        border-color: #3C873A;
    }

    .btn-default,
    .btn-secondary {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    .btn-danger {
        background: #d9534f;
        border-color: #d9534f;
        color: #fff;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
        height: 28px;
    }

    /* Toolbar */
    .db-toolbar {
        display: flex;
        margin-bottom: 15px;
        gap: 5px;
    }

    #searchDb {
        flex: 1;
        min-width: 0;
    }

    /* Table Responsive Wrapper */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-card);
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        white-space: nowrap;
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
        padding: 10px;
        border-top: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .user-badge {
        background: var(--bg-input);
        padding: 3px 6px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        font-family: monospace;
        font-size: 12px;
    }

    /* Mobile Specific Overrides */
    @media (max-width: 600px) {
        .db-container {
            padding: 10px;
        }

        .db-section {
            padding: 12px;
            margin-bottom: 15px;
        }

        .db-page-header h1 {
            font-size: 18px;
        }

        .db-page-header p {
            font-size: 12px;
        }

        .btn-block-mobile {
            width: 100%;
        }

        .input-group-addon {
            padding: 0 8px;
            font-size: 12px;
        }

        .form-control {
            font-size: 13px;
            height: 34px;
        }

        .input-group-addon {
            height: 34px;
        }

        .btn {
            height: 34px;
            font-size: 13px;
        }

        .db-table th,
        .db-table td {
            padding: 8px;
            font-size: 12px;
        }
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const apiUrl = (window.base_url || '') + '/api';
    const apiToken = window.apiToken || document.querySelector('meta[name="api-token"]')?.getAttribute('content');
    const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };

    document.addEventListener('DOMContentLoaded', () => {
        loadServices();
        loadDatabases();
    });

    async function loadServices() {
        try {
            const res = await fetch(`${apiUrl}/services`, { headers });
            const data = await res.json();
            const select = document.getElementById('serviceSelect');

            let options = '<option value="">-- Standalone (No App Linked) --</option>';

            if (res.ok && data.services) {
                options += data.services.map(s => `<option value="${s.id}">${s.name} (${s.domain || 'no domain'})</option>`).join('');
            }
            select.innerHTML = options;
        } catch (e) { console.error('Load Services Error:', e); }
    }

    async function loadDatabases() {
        try {
            const res = await fetch(`${apiUrl}/databases`, { headers });
            const data = await res.json();
            const list = document.getElementById('dbList');
            const type = 'mongodb';

            if (res.ok && data.databases) {
                const filtered = data.databases.filter(db => db.type === type);
                if (filtered.length === 0) {
                    list.innerHTML = '<tr><td colspan="4" class="text-center">No databases found</td></tr>';
                    return;
                }

                list.innerHTML = filtered.map(db => `
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-primary);">${db.name}</div>
                            <div style="font-size:11px;color:var(--text-secondary);">${(db.host === 'lp-mongo-mother' || db.host === 'lp-mysql-mother' || db.host === 'lp-postgres-mother') ? window.location.hostname : db.host}:${db.port}</div>
                        </td>
                        <td><span style="color:var(--text-secondary);">-</span></td>
                        <td><span class="user-badge">${db.user}</span></td>
                        <td class="actions-col">
                            <div style="display:flex;gap:5px;justify-content:flex-end;">
                                <button class="btn btn-sm btn-secondary" title="Copy Connection String" onclick="copyConnString('${db.connection_string}')">
                                    <i data-lucide="copy" style="width:14px;height:14px;"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteDb(${db.id})">
                                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                if (window.lucide) lucide.createIcons();
            } else {
                list.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${data.error || 'Failed to load databases'}</td></tr>`;
            }
        } catch (e) {
            console.error('Load DBs Error:', e);
            document.getElementById('dbList').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Network error</td></tr>';
        }
    }

    function copyConnString(connStr) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(connStr).then(() => {
                    showNotification('Connection string copied!', 'success');
                }).catch(() => {
                    fallbackCopy(connStr);
                });
            } else {
                fallbackCopy(connStr);
            }
        } catch (e) {
            console.error('Copy Error:', e);
            showNotification('Failed to process connection string', 'error');
        }
    }

    function fallbackCopy(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showNotification('Connection string copied!', 'success');
        } catch (err) {
            console.error('Fallback copy failed', err);
            showNotification('Failed to copy', 'error');
        }
        document.body.removeChild(textArea);
    }

    async function createDatabase() {
        const serviceId = document.getElementById('serviceSelect').value;
        const btn = document.querySelector('button[onclick="createDatabase()"]');

        /* Remove mandatory service check
        if (!serviceId) { showNotification('Please select a service', 'error'); return; }
        */

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="margin-right:8px;"></span>Generating...';

        try {
            // Determine API URL based on service selection
            let url = `${apiUrl}/databases`;
            if (serviceId) {
                url = `${apiUrl}/services/${serviceId}/databases`;
            }

            const res = await fetch(url, {
                method: 'POST',
                headers,
                body: JSON.stringify({ type: 'mongodb' }) // No name
            });
            const data = await res.json();
            if (res.ok) {
                // Stop loading immediately before showing modal
                // Use the pre-built connection string from the backend
                const connStr = data.database.connection_string;

                await showCustomAlert({
                    title: 'Database Created!',
                    html: `<div style="text-align:left;font-size:13px">
                            <b>DB:</b> ${data.database.name}<br>
                            <b>User:</b> ${data.database.user}<br>
                            <b>Pass:</b> <code style="background:#333;color:#fff;padding:2px">${data.database.password}</code>
                           </div>
                           
                           <div style="margin-top:10px;">
                                <label style="display:block; font-size:11px; color:#64748b;">Connection String</label>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" readonly value="${connStr}" id="new-conn-string" style="width:100%; padding:5px; border:1px solid #ddd; border-radius:4px; font-size:11px;">
                                    <button onclick="navigator.clipboard.writeText(document.getElementById('new-conn-string').value)" class="btn btn-sm btn-secondary">Copy</button>
                                </div>
                           </div>
                           
                           <p style="margin-top:10px;font-size:11px;color:#d00">Save this password/string now!</p>`
                });
                loadDatabases();
            } else { showCustomAlert({ title: 'Failed', message: data.message }); }
        } catch (e) { showNotification('Network error', 'error'); }
        finally { btn.disabled = false; btn.innerHTML = 'Create Database'; }
    }

    async function deleteDb(id) {
        if (await showCustomConfirm('Delete Database?', 'Are you sure you want to delete this database? This action cannot be undone.', true)) {
            await fetch(`${apiUrl}/databases/${id}`, { method: 'DELETE', headers });
            loadDatabases();
            showNotification('Database deleted successfully', 'success');
        }
    }


</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>