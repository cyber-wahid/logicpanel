<?php
$page_title = 'Applications Overview';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Applications Overview</h1>
        <p>Manage all your applications in one place. Monitor status, access terminals, and manage files.</p>
    </div>

    <div class="db-section">
        <div class="db-toolbar">
            <div class="toolbar-left">
                <input type="text" id="searchApp" class="form-control check-dirty" placeholder="Search applications...">
                <select id="typeFilter" class="form-control check-dirty" style="width: auto;">
                    <option value="all">All Types</option>
                    <option value="nodejs">Node.js</option>
                    <option value="python">Python</option>
                </select>
            </div>

            <div class="toolbar-right">
                <button class="btn btn-default" onclick="loadApps()" title="Refresh">
                    <i data-lucide="refresh-cw" id="refresh-icon"></i>
                </button>

                <div class="dropdown-wrapper">
                    <button class="btn btn-primary" onclick="toggleDropdown('create-menu')">
                        <i data-lucide="plus"></i> Create New
                    </button>
                    <div id="create-menu" class="dropdown-menu">
                        <a href="<?= $base_url ?? '' ?>/apps/nodejs" class="dropdown-item">
                            <i data-lucide="hexagon" style="color: #3C873A;"></i> Node.js
                        </a>
                        <a href="<?= $base_url ?? '' ?>/apps/python" class="dropdown-item">
                            <i data-lucide="codepen" style="color: #306998;"></i> Python
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="db-table" id="appTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Application</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">Created</th>
                        <th class="actions-col" style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="appList">
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 20px;">Loading applications...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Reuse core panel styles to ensure consistency */
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
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .db-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .toolbar-left,
    .toolbar-right {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .toolbar-left {
        flex: 1;
        max-width: 500px;
    }

    .form-control {
        padding: 8px 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
        height: 38px;
        font-size: 14px;
        width: 100%;
    }

    #searchApp {
        flex: 1;
    }

    /* Buttons */
    .btn {
        padding: 8px 14px;
        border-radius: 4px;
        cursor: pointer;
        border: 1px solid transparent;
        color: #fff;
        height: 36px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary {
        background-color: #3C873A;
        border-color: #3C873A;
    }

    .btn-primary:hover {
        background-color: #2D6A2E;
    }

    .btn-default,
    .btn-secondary {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    .btn-default:hover,
    .btn-secondary:hover {
        background: var(--border-color);
    }

    .btn-danger {
        background: #d9534f;
        border-color: #d9534f;
    }

    .btn-danger:hover {
        background: #c9302c;
    }

    .btn-warning {
        background: #d97706;
        border-color: #d97706;
    }

    .btn-success {
        background: #15803d;
        border-color: #15803d;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 13px;
        height: 30px;
    }

    /* Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        border: 1px solid var(--border-color);
        border-radius: 4px;
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        white-space: nowrap;
    }

    .db-table th {
        background: var(--bg-input);
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-weight: 600;
    }

    .db-table td {
        padding: 10px 12px;
        border-top: 1px solid var(--border-color);
        vertical-align: middle;
        color: var(--text-primary);
    }

    /* Status Badges */
    .status-badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-running {
        background: #dcfce7;
        color: #15803d;
    }

    .status-stopped {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-deploying {
        background: #e0f2fe;
        color: #0369a1;
    }

    .status-error {
        background: #fef3c7;
        color: #d97706;
    }

    /* Dropdown */
    .dropdown-wrapper {
        position: relative;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 4px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        z-index: 100;
        min-width: 160px;
    }

    .dropdown-menu.show {
        display: block;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 13px;
        transition: background 0.1s;
    }

    .dropdown-item:hover {
        background: var(--bg-input);
    }

    .app-link {
        color: var(--primary);
        text-decoration: none;
        font-family: monospace;
    }

    .app-link:hover {
        text-decoration: underline;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    .spin-anim {
        animation: spin 1s linear infinite;
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
        .db-toolbar {
            flex-direction: column;
            gap: 15px;
        }

        .toolbar-left {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .toolbar-right {
            width: 100%;
            justify-content: space-between;
            display: flex;
            gap: 10px;
        }

        .dropdown-wrapper {
            flex: 1;
        }

        .dropdown-wrapper .btn {
            width: 100%;
            justify-content: center;
        }

        .form-control {
            width: 100%;
        }

        /* Transform Table to Cards */
        .db-table,
        .db-table tbody,
        .db-table tr,
        .db-table td {
            display: block;
            width: 100%;
        }

        .db-table thead {
            display: none;
            /* Hide headers */
        }

        .db-table tr {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .db-table td {
            padding: 0;
            border: none;
        }

        /* Application Name (Full Width) */
        .db-table td:nth-child(1) {
            width: 100%;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 5px;
        }

        /* Type & Status (50% Width each) */
        .db-table td:nth-child(2),
        .db-table td:nth-child(3) {
            width: 48%;
            display: flex;
            align-items: center;
        }

        /* Adjust Status column to align right or keep left? Keeping left is safer */

        /* Hide Created Date */
        .db-table td:nth-child(4) {
            display: none;
        }

        /* Actions (Full Width, Bottom) */
        .db-table td:last-child {
            width: 100%;
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
            margin-top: 5px;
        }

        /* Override inline styles for the button container */
        .db-table td:last-child>div {
            justify-content: space-between !important;
            width: 100%;
            gap: 8px !important;
        }

        .db-table td:last-child .btn {
            flex: 1;
            /* Distribute buttons evenly */
            justify-content: center;
            height: 38px;
            /* Larger touch target */
        }

        /* Text alignments */
        .text-right {
            text-align: left;
        }
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const API_BASE = '<?= $base_url ?? '' ?>/api';
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;
    let allApps = [];
    let refreshInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadApps();

        // Auto refresh status every 5 seconds
        refreshInterval = setInterval(() => loadApps(false), 5000);

        // Search listeners
        document.getElementById('searchApp').addEventListener('input', filterApps);
        document.getElementById('typeFilter').addEventListener('change', filterApps);
    });

    // Dropdown Toggle
    function toggleDropdown(id) {
        const menu = document.getElementById(id);
        const isVisible = menu.classList.contains('show');
        document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('show'));
        if (!isVisible) menu.classList.add('show');
    }

    window.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-wrapper')) {
            document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('show'));
        }
    });

    async function loadApps(showLoading = true) {
        const tbody = document.getElementById('appList');
        const refreshIcon = document.getElementById('refresh-icon');

        if (showLoading && refreshIcon) refreshIcon.classList.add('spin-anim');

        try {
            const res = await fetch(`${API_BASE}/services`, {
                headers: { 'Authorization': `Bearer ${TOKEN}`, 'Content-Type': 'application/json' }
            });
            const data = await res.json();

            allApps = data.services || [];
            filterApps();

        } catch (e) {
            console.error(e);
            if (showLoading) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading applications.</td></tr>';
            }
        } finally {
            if (refreshIcon) refreshIcon.classList.remove('spin-anim');
        }
    }

    function filterApps() {
        const search = document.getElementById('searchApp').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;
        const tbody = document.getElementById('appList');

        const filtered = allApps.filter(app => {
            const matchSearch = app.name.toLowerCase().includes(search) || (app.domain && app.domain.toLowerCase().includes(search));
            const matchType = type === 'all' || app.type === type;
            return matchSearch && matchType;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding:20px; color:var(--text-muted)">No applications found.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(app => `
        <tr>
            <td>
                <div style="font-weight:600; font-size:15px;">${app.name}</div>
                <div style="font-size:13px; margin-top:4px;">
                    <a href="https://${app.domain}" target="_blank" class="app-link">${app.domain}</a>
                </div>
            </td>
            <td>
                <div style="display:flex; align-items:center; gap:8px;">
                    ${getAppIcon(app.type)}
                    <span style="text-transform:capitalize; font-size:13px;">${app.type}</span>
                </div>
            </td>
            <td>
                <span class="status-badge ${app.status === 'deploying' ? 'status-deploying' : 'status-' + app.status}">
                    ${app.status === 'deploying' ? 'Pending' : app.status}
                </span>
                ${app.status === 'deploying' ? '<span style="font-size:12px; color:#0369a1; margin-left:6px;">&nbsp;Installing...</span>' : ''}
            </td>
            <td style="font-size:13px; color:var(--text-secondary);">
                ${new Date(app.created_at).toLocaleDateString()}
            </td>
            <td class="text-right">
                <div style="display:flex; justify-content:flex-end; gap:6px;">
                    <button class="btn btn-sm btn-secondary" onclick="window.location.href='<?= $base_url ?? '' ?>/apps/terminal?id=${app.id}'" title="Terminal">
                        <i data-lucide="terminal" style="width:16px; height:16px;"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="window.open('<?= $base_url ?? '' ?>/apps/files?id=${app.id}', '_blank')" title="File Manager">
                        <i data-lucide="folder" style="width:16px; height:16px;"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="showDeploymentLogs(${app.id}, '${app.name}')" title="View Logs">
                        <i data-lucide="list" style="width:16px; height:16px;"></i>
                    </button>
                    
                    <div style="width:1px; background:var(--border-color); margin:0 6px;"></div>
                    
                    ${getPowerButtons(app)}
                    
                    <div style="width:1px; background:var(--border-color); margin:0 6px;"></div>
                    
                    <button class="btn btn-sm btn-danger" onclick="manageApp(${app.id}, 'delete')" title="Delete">
                        <i data-lucide="trash-2" style="width:16px; height:16px;"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

        lucide.createIcons();
    }

    function getAppIcon(type) {
        if (type === 'nodejs') return '<i data-lucide="hexagon" style="width:16px; height:16px; color:#3C873A;"></i>';
        if (type === 'python') return '<i data-lucide="codepen" style="width:16px; height:16px; color:#306998;"></i>';
        return '<i data-lucide="box" style="width:16px; height:16px;"></i>';
    }

    function getPowerButtons(app) {
        if (app.status === 'running') {
            return `
            <button class="btn btn-sm btn-secondary" onclick="manageApp(${app.id}, 'restart')" title="Restart">
                <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i>
            </button>
            <button class="btn btn-sm btn-warning" onclick="manageApp(${app.id}, 'stop')" title="Stop">
                <i data-lucide="square" style="width:14px; height:14px;"></i>
            </button>
        `;
        } else if (app.status === 'stopped') {
            return `
            <button class="btn btn-sm btn-success" onclick="manageApp(${app.id}, 'start')" title="Start">
                <i data-lucide="play" style="width:14px; height:14px;"></i>
            </button>
        `;
        } else if (app.status === 'deploying') {
            return `
            <button class="btn btn-sm btn-secondary" disabled title="Deploying..." style="opacity:0.6;">
                <i data-lucide="loader-2" class="spin-anim" style="width:14px; height:14px;"></i>
            </button>
        `;
        }
        // Error state
        return `
        <button class="btn btn-sm btn-secondary" onclick="manageApp(${app.id}, 'restart')" title="Retry">
            <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i>
        </button>
    `;
    }

    async function manageApp(id, action) {
        if (action === 'delete') {
            if (!await showCustomConfirm('Delete Application?', 'This cannot be undone.', true)) return;

            try {
                await apiCall(`${API_BASE}/services/${id}`, 'DELETE');
                showNotification('Application deleted', 'success');
                loadApps();
            } catch (e) { showNotification(e.message, 'error'); }
            return;
        }

        // For power actions, show loading state on button
        const btn = event.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<div class="spin-anim" style="width:14px; height:14px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%;"></div>';
        btn.disabled = true;

        try {
            await apiCall(`${API_BASE}/services/${id}/${action}`, 'POST');
            showNotification(`Application ${action}ed`, 'success');
            loadApps();
        } catch (e) {
            showNotification(e.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            lucide.createIcons();
        }
    }

    async function apiCall(url, method = 'GET') {
        const res = await fetch(url, {
            method,
            headers: { 'Authorization': `Bearer ${TOKEN}`, 'Content-Type': 'application/json' }
        });

        // Handle empty or non-JSON responses
        let data = {};
        const text = await res.text();
        if (text) {
            try {
                data = JSON.parse(text);
            } catch (e) {
                // Response is not JSON, that's okay for some operations
                if (!res.ok) throw new Error('Request failed');
            }
        }

        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
    }

    // Custom Confirm Helper (if missing from dashboard.js, though it should be there)
    if (typeof showCustomConfirm === 'undefined') {
        window.showCustomConfirm = (title, msg) => confirm(msg);
    }
    
    // Deployment Logs Modal Logic (Secure read-only API access)
    function showDeploymentLogs(serviceId, serviceName) {
        const existingModal = document.getElementById('deployment-logs-modal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.id = 'deployment-logs-modal';
        modal.style.cssText = `position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 20px;`;
        
        modal.innerHTML = `
            <div style="background: #1e1e1e; border: 1px solid #333; border-radius: 8px; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.5); overflow: hidden;">
                <div id="terminal-header" style="background:#252526; color:#ccc; padding:12px 15px; font-size:13px; display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #333;">
                    <div>
                        <i data-lucide="terminal" style="width:16px; margin-right: 8px; display:inline-block; vertical-align:middle;"></i>
                        <span style="font-weight: 500;">Deployment Logs - ${serviceName}</span>
                    </div>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div id="deployment-status" style="font-size: 12px; color: #58a6ff; display: none;">
                            <i data-lucide="activity" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><span id="deployment-status-text">Deploying...</span>
                        </div>
                        <button onclick="refreshDeploymentLogs(${serviceId})" style="background:transparent; border:none; color:#ccc; cursor:pointer;" title="Refresh Logs"><i data-lucide="refresh-cw" style="width:14px;"></i></button>
                        <button onclick="closeDeploymentLogs()" style="background:transparent; border:none; color:#ccc; cursor:pointer; opacity: 0.7;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'" title="Close">&times;</button>
                    </div>
                </div>
                
                <div style="flex: 1; overflow: hidden; position: relative;">
                    <div id="deployment-logs-content" style="background: #1e1e1e; color: #cccccc; font-family: Menlo, Monaco, 'Courier New', monospace; font-size: 14px; line-height: 1.5; padding: 15px; height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
                        <div style="color: #8b949e;"><i data-lucide="loader" class="spin-anim" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 8px;"></i>Fetching logs...</div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        window.deploymentLogsInterval = setInterval(() => fetchDeploymentLogs(serviceId), 2000);
        fetchDeploymentLogs(serviceId);
        if (window.lucide) lucide.createIcons();
    }

    function formatLogs(logs) {
        return logs
            .replace(/===.*===/g, '<span style="color: #4ec9b0; font-weight: bold;">$&</span>')
            .replace(/(error|failed|fail|Permission denied|Traceback)/gi, '<span style="color: #f48771; font-weight: bold;">$&</span>')
            .replace(/(success|complete|done|installed)/gi, '<span style="color: #89d185;">$&</span>')
            .replace(/(warning|warn)/gi, '<span style="color: #ce9178;">$&</span>')
            .replace(/(\$|>|#)/g, '<span style="color: #569cd6;">$&</span>')
            .replace(/(pip|npm|node|git|python)/gi, '<span style="color: #dcdcaa;">$&</span>');
    }

    async function fetchDeploymentLogs(serviceId) {
        try {
            const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
            const res = await fetch(`${API_BASE}/services/${serviceId}/logs`, {
                headers: { 
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            const data = await res.json();
            
            if (res.ok && data.logs) {
                const logsContent = document.getElementById('deployment-logs-content');
                const statusDiv = document.getElementById('deployment-status');
                const statusText = document.getElementById('deployment-status-text');
                
                if (logsContent) {
                    const newLogs = data.logs || 'No logs available yet...';
                    
                    logsContent.innerHTML = formatLogs(newLogs);
                    logsContent.scrollTop = logsContent.scrollHeight;
                    
                    if (statusDiv && statusText) {
                        statusDiv.style.display = 'block';
                        
                        const logLower = data.logs.toLowerCase();
                        if (logLower.includes('=== app failed ===') || logLower.includes('process failed') || logLower.includes('traceback (most recent call last)')) {
                            statusText.innerHTML = '<span style="color: #f48771;">⚠ Error</span>';
                        } else if (logLower.includes('=== starting app ===') || logLower.includes('server running')) {
                            statusText.innerHTML = '<span style="color: #89d185;">✓ Running</span>';
                            if (window.deploymentLogsInterval) {
                                clearInterval(window.deploymentLogsInterval);
                                window.deploymentLogsInterval = setInterval(() => fetchDeploymentLogs(serviceId), 10000); // Slow down once running
                            }
                        } else {
                            statusText.innerHTML = '<span style="color: #569cd6;">⟳ Processing...</span>';
                        }
                    }
                }
            }
        } catch (e) {
            console.error('Failed to fetch logs:', e);
            const logsContent = document.getElementById('deployment-logs-content');
            if (logsContent) {
                logsContent.innerHTML = '<span style="color: #f48771;">Failed to fetch logs. Please try refreshing.</span>';
            }
        }
    }
    
    function refreshDeploymentLogs(serviceId) { fetchDeploymentLogs(serviceId); }

    function closeDeploymentLogs() {
        const modal = document.getElementById('deployment-logs-modal');
        if (modal) modal.remove();
        if (window.deploymentLogsInterval) { 
            clearInterval(window.deploymentLogsInterval); 
            window.deploymentLogsInterval = null; 
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>