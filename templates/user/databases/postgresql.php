<?php
$page_title = 'PostgreSQL Databases';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>PostgreSQL® Databases</h1>
        <p>The world's most advanced open source relational database.</p>
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
        <!-- Hidden Type Field -->
        <input type="hidden" id="dbType" value="postgresql">

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
        /* Prevent horizontal scroll on body */
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
        /* Critical for keeping within viewport */
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
        flex-wrap: wrap;
        /* Allow wrapping on very small screens if needed */
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
        /* Don't let prefix take more than 40% */
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .input-group input {
        border-radius: 0 4px 4px 0;
        flex: 1;
        min-width: 50%;
        /* Input must take at least 50% */
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
        /* Smooth scroll on iOS */
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-card);
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        white-space: nowrap;
        /* Prevent wrapping inside cells */
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

        /* Adjust input group for small screens */
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

        /* Ensure table doesn't break layout */
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
            const type = document.getElementById('dbType').value;

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
                            <div style="font-size:11px;color:var(--text-secondary);">${(db.host === 'lp-postgres-mother' || db.host === 'lp-mysql-mother' || db.host === 'lp-mongo-mother') ? window.location.hostname : db.host}:${db.port}</div>
                        </td>
                        <td><span style="color:var(--text-secondary);">-</span></td>
                        <td><span class="user-badge">${db.user}</span></td>
                        <td class="actions-col">
                            <div style="display:flex;gap:5px;justify-content:flex-end;">
                                <a href="${window.location.protocol}//${window.location.hostname}:7777/public/adminer.php?pgsql=${db.host}&username=${db.user}&db=${db.name}" target="_blank" class="btn btn-sm btn-secondary" title="Login to Adminer">
                                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                                </a>
                                <button class="btn btn-sm btn-secondary" title="Copy Connection String" onclick="copyConnString('postgresql', '${db.host}', '${db.port}', '${db.user}', '${db.password}', '${db.name}')">
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

    function loginToAdminer(driver, host, user, encryptedPass, db) {
        // Construct Adminer URL with port 7777 and direct path
        const adminerUrl = `${window.location.protocol}//${window.location.hostname}:7777/public/adminer.php?pgsql=${host}&username=${user}&db=${db}`;
        window.open(adminerUrl, '_blank');
    }

    function copyConnString(type, host, port, user, encryptedPass, db) {
        const pass = atob(encryptedPass);
        // Use browser host if internal docker host is returned
        let displayHost = host;
        if (host === 'lp-mysql-mother' || host === 'lp-postgres-mother' || host === 'lp-mongo-mother') {
            displayHost = window.location.hostname;
        }

        const str = `${type}://${user}:${pass}@${displayHost}:${port}/${db}`;

        // Use a fallback for non-secure contexts (HTTP)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(str).then(() => {
                showNotification('Connection string copied!', 'success');
            }).catch(() => {
                fallbackCopy(str);
            });
        } else {
            fallbackCopy(str);
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
                body: JSON.stringify({ type: 'postgresql' })
            });
            const data = await res.json();
            if (res.ok) {
                // Stop loading immediately before showing modal
                btn.disabled = false;
                btn.innerHTML = 'Create Database';

                await showCustomAlert({
                    title: 'Database Created!',
                    html: `<div style="text-align:left;font-size:13px;background:#f3f4f6;padding:12px;border-radius:6px;border:1px solid #e5e7eb;margin-bottom:12px">
                            <div style="margin-bottom:4px"><b>DB:</b> <span style="color:#2563eb">${data.database.name}</span></div>
                            <div style="margin-bottom:4px"><b>User:</b> <span style="color:#059669">${data.database.user}</span></div>
                            <div style="margin-bottom:4px"><b>Pass:</b> <code style="background:#fff;color:#dc2626;padding:2px 6px;border:1px solid #e5e7eb;border-radius:4px">${data.database.password}</code></div>
                            <div><b>Host:</b> ${data.database.host}</div>
                           </div>
                           
                           <div style="margin-top:15px; text-align:center;">
                                 <a href="${window.location.protocol}//${window.location.hostname}:7777/public/adminer.php?pgsql=${data.database.host}&username=${data.database.user}&db=${data.database.name}" 
                                   target="_blank" 
                                   class="btn btn-primary" 
                                   style="width:100%;justify-content:center;font-weight:600;text-decoration:none;">
                                    <i data-lucide="external-link" style="width:16px;height:16px;margin-right:6px"></i> 
                                    Open Adminer (Login)
                                 </a>
                                <p style="margin-top:8px;font-size:11px;color:#d00">
                                    <i data-lucide="alert-triangle" style="width:12px;height:12px;vertical-align:text-bottom"></i>
                                    Copy the password above! You will need to paste it to login.
                                </p>
                           </div>`
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

    function checkDb() { showNotification('Connection check feature pending', 'info'); }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>