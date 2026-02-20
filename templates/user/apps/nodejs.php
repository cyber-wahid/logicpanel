<?php
$page_title = 'Node.js Applications';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Node.js Applications</h1>
        <p>Deploy and manage scalable network applications.</p>
    </div>

    <!-- Create App Section -->
    <div class="db-section">
        <h2>Create New Application</h2>

        <!-- Hidden Type Field -->
        <input type="hidden" id="appType" value="nodejs">

        <div class="db-form-group">
            <label for="appName">Application Name:</label>
            <div class="input-group">
                <input type="text" id="appName" class="form-control"
                    placeholder="My Node.js App">
            </div>
            <small style="color:var(--text-secondary);font-size:12px;">Any name. Used for identification only.</small>
        </div>

        <div class="db-form-group">
            <label for="appDomain">Custom Domain (Optional):</label>
            <input type="text" id="appDomain" class="form-control" placeholder="e.g. logicpanel.com">
            <small style="color:var(--text-secondary);font-size:11px;">Leave empty to use the default subdomain
                (appname.<?= $app_base_domain ?>).</small>
        </div>


        <div class="db-form-group">
            <label for="appVersion">Node.js Version:</label>
            <select id="appVersion" class="form-control">
                <option value="Node.js 22 LTS">22.x LTS (Latest)</option>
                <option value="Node.js 20 LTS" selected>20.x LTS (Recommended)</option>
                <option value="Node.js 18 LTS">18.x LTS</option>
                <option value="Node.js 16 LTS">16.x LTS</option>
            </select>
        </div>

        <div class="db-form-group">
            <label for="startupFile">Startup File:</label>
            <input type="text" id="startupFile" class="form-control" placeholder="server.js" value="server.js">
        </div>

        <!-- GitHub Deployment (Optional) - Collapsible -->
        <div class="db-form-group">
            <button type="button" class="toggle-github-btn" onclick="toggleGithubSection()">
                <i data-lucide="github" style="width:16px;height:16px;"></i>
                <span>Deploy from GitHub (Optional)</span>
                <i data-lucide="chevron-down" id="github-chevron" style="width:16px;height:16px;margin-left:auto;"></i>
            </button>
        </div>

        <div id="github-section" class="github-deploy-section" style="display:none;">
            <div class="db-form-group">
                <label for="githubRepo">GitHub Repository URL:</label>
                <input type="text" id="githubRepo" class="form-control"
                    placeholder="https://github.com/username/repo.git">
                <small style="color:var(--text-secondary);font-size:11px;">LogicPanel will <b>only clone</b> the repository. You must manually configure and start the app via terminal/files thereafter.</small>
            </div>

            <div class="db-form-group">
                <label for="githubBranch">Branch:</label>
                <input type="text" id="githubBranch" class="form-control" placeholder="main" value="main">
            </div>

            <!-- Advanced Options -->
            <div class="db-form-group">
                <button type="button" class="toggle-github-btn" onclick="toggleAdvancedSection()">
                    <i data-lucide="settings" style="width:16px;height:16px;"></i>
                    <span>Advanced Options (Optional)</span>
                    <i data-lucide="chevron-down" id="advanced-chevron"
                        style="width:16px;height:16px;margin-left:auto;"></i>
                </button>
            </div>

            <div id="advanced-section" class="github-deploy-section" style="display:none;">
                <!-- Root Directory for Monorepos -->
                <div class="db-form-group">
                    <label for="rootDir">Root Directory:</label>
                    <input type="text" id="rootDir" class="form-control" placeholder="./">
                    <small style="color:var(--text-secondary);font-size:11px;">For monorepos, specify subdirectory
                        (e.g., packages/api)</small>
                </div>

                <!-- Commands -->
                <div class="db-form-group">
                    <label for="installCmd">Install Command:</label>
                    <input type="text" id="installCmd" class="form-control" placeholder="npm install">
                    <small style="color:var(--text-secondary);font-size:11px;">Default: npm install</small>
                </div>

                <div class="db-form-group">
                    <label for="postInstallCmd">Post-Install Command:</label>
                    <input type="text" id="postInstallCmd" class="form-control" placeholder="npx prisma generate">
                    <small style="color:var(--text-secondary);font-size:11px;">Run after install (e.g., prisma generate,
                        migrations)</small>
                </div>

                <div class="db-form-group">
                    <label for="buildCmd">Build Command:</label>
                    <input type="text" id="buildCmd" class="form-control" placeholder="npm run build">
                    <small style="color:var(--text-secondary);font-size:11px;">For compiled/transpiled apps (TypeScript,
                        Next.js, etc.)</small>
                </div>

                <div class="db-form-group">
                    <label for="startCmd">Start Command:</label>
                    <input type="text" id="startCmd" class="form-control" placeholder="npm start">
                    <small style="color:var(--text-secondary);font-size:11px;">Default: npm start</small>
                </div>
            </div>
        </div>

        <!-- Environment Variables - Always visible outside collapsibles -->
        <div style="margin-top:15px; border-top:1px solid var(--border-color); padding-top:15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <label style="margin:0;font-weight:600;">Environment Variables</label>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addEnvVar()">
                    <i data-lucide="plus" style="width:14px;height:14px;"></i> Add
                </button>
            </div>
            <div id="envVarsContainer"></div>
            <small style="color:var(--text-secondary);font-size:11px;">Add environment variables that will be saved to
                .env file</small>
        </div>

        <div class="db-form-group" style="margin-top:20px;">
            <button class="btn btn-primary btn-block-mobile" onclick="createApp()">Create Application</button>
        </div>

        <!-- Current Apps Section -->
        <div class="db-section">
            <h2>Current Applications</h2>
            <div class="db-toolbar">
                <input type="text" id="searchApp" class="form-control" placeholder="Search">
                <button class="btn btn-default" style="margin-left: 5px;">Go</button>
            </div>

            <div class="table-responsive">
                <table class="db-table" id="appTable">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="appList">
                        <tr>
                            <td colspan="4" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        /* Consistent Styles from DB pages */
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

        .input-group {
            display: flex;
            width: 100%;
            position: relative;
        }

        .input-group input {
            border-radius: 4px;
            flex: 1;
            min-width: 0;
        }

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
            transition: all 0.2s;
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
            color: #fff;
        }

        .btn-danger:hover {
            background: #c9302c;
        }

        .btn-success {
            background: #15803d;
            border-color: #15803d;
            color: #fff;
        }

        .btn-warning {
            background: #d97706;
            border-color: #d97706;
            color: #fff;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            height: 28px;
        }

        .db-toolbar {
            display: flex;
            margin-bottom: 15px;
            gap: 5px;
        }

        #searchApp {
            flex: 1;
            min-width: 0;
        }

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

        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        .status-running {
            background: #dcfce7;
            color: #15803d;
        }

        .status-stopped {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-error {
            background: #fef3c7;
            color: #d97706;
        }

        .status-deploying {
            background: #e0f2fe;
            color: #0369a1;
        }

        /* GitHub Toggle Button */
        .toggle-github-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: var(--bg-input);
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-github-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .github-deploy-section {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }

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

            .btn-block-mobile {
                width: 100%;
            }

            .form-control,
            .btn {
                font-size: 13px;
                height: 34px;
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
        const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
        const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };

        document.addEventListener('DOMContentLoaded', () => { loadApps(); });

        async function loadApps() {
            const tbody = document.getElementById('appList');
            try {
                const res = await fetch(`${apiUrl}/services`, { headers });
                const data = await res.json();

                // Filter only Node.js apps
                const apps = (data.services || []).filter(app => app.type === 'nodejs');

                if (apps.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:20px;color:var(--text-muted)">No Node.js applications found.</td></tr>';
                    return;
                }

                tbody.innerHTML = apps.map(app => `
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px">${app.name}</div>
                        <div style="font-size:10px;color:var(--text-secondary);opacity:0.8">${app.version || 'Node.js'}</div>
                    </td>
                    <td>
                        <a href="${app.url || ('https://' + app.domain)}" target="_blank" style="font-family:monospace;background:var(--bg-input);padding:2px 6px;border-radius:3px;color:var(--primary-color);text-decoration:none;">
                            ${app.domain}
                        </a>
                    </td>
                    <td>
                        <span class="status-badge ${app.status === 'deploying' ? 'status-deploying' : `status-${app.status}`}">
                            ${app.status === 'deploying' ? 'Pending' : app.status}
                        </span>
                        ${app.status === 'deploying' ? '<span class="status-detail">&nbsp; Installing...</span>' : ''}
                    </td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-secondary" onclick="window.open('${app.url || ('https://' + app.domain)}', '_blank')" title="Open App"><i data-lucide="external-link"></i></button>
                        <button class="btn btn-sm btn-secondary" onclick="window.open((window.base_url || '') + '/apps/files?id=${app.id}', '_blank')" title="Files"><i data-lucide="folder"></i></button>
                        ${app.status === 'running'
                        ? `<button class="btn btn-sm btn-warning" onclick="manageApp(${app.id}, 'stop')" title="Stop"><i data-lucide="square"></i></button>`
                        : (app.status === 'deploying'
                            ? `<button class="btn btn-sm btn-secondary" disabled title="Deploying..."><i class="spinner" style="width:12px;height:12px;border-width:2px;border-color:#666;"></i></button>`
                            : `<button class="btn btn-sm btn-success" onclick="manageApp(${app.id}, 'start')" title="Start"><i data-lucide="play"></i></button>`
                        )
                    }
                        <button class="btn btn-sm btn-danger" onclick="manageApp(${app.id}, 'delete')" title="Delete"><i data-lucide="trash-2"></i></button>
                    </td>
                </tr>
            `).join('');
                lucide.createIcons();
            } catch (e) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading applications</td></tr>'; }
        }

        // ... (toggle headers) ...

        function toggleGithubSection() {
            const section = document.getElementById('github-section');
            const chevron = document.getElementById('github-chevron');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';

                // Show tip if not already shown
                if (!document.getElementById('github-env-tip')) {
                    const tip = document.createElement('div');
                    tip.id = 'github-env-tip';
                    tip.style.cssText = 'background:#eff6ff;color:#1e40af;padding:10px;border-radius:4px;font-size:12px;margin-bottom:15px;border:1px solid #bfdbfe;display:flex;gap:8px;align-items:center;';
                    tip.innerHTML = '<i data-lucide="info" style="width:16px;height:16px;min-width:16px"></i> <span><strong>Pro Tip:</strong> Ensure you add necessary Environment Variables (like API Keys, DB URL) below before creating the app to prevent build failures.</span>';
                    section.insertBefore(tip, section.firstChild);
                    lucide.createIcons();
                }
            } else {
                section.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function toggleAdvancedSection() {
            const section = document.getElementById('advanced-section');
            const chevron = document.getElementById('advanced-chevron');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                section.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function addEnvVar(key = '', value = '') {
            const container = document.getElementById('envVarsContainer');
            const row = document.createElement('div');
            row.className = 'env-var-row';
            row.style.cssText = 'display:flex; gap:8px; margin-bottom:8px;';
            row.innerHTML = `
                <input type="text" class="form-control env-key" placeholder="KEY" value="${key}" style="flex:1">
                <input type="text" class="form-control env-value" placeholder="VALUE" value="${value}" style="flex:1">
                <button class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding:0 10px">&times;</button>
            `;
            container.appendChild(row);
        }

        function getEnvVars() {
            const keys = Array.from(document.querySelectorAll('.env-key')).map(i => i.value.trim());
            const values = Array.from(document.querySelectorAll('.env-value')).map(i => i.value.trim());
            const env_keys = [];
            const env_values = [];
            keys.forEach((key, idx) => {
                if (key) {
                    env_keys.push(key);
                    env_values.push(values[idx]);
                }
            });
            return { env_keys, env_values };
        }

        async function createApp() {
            const name = document.getElementById('appName').value.trim();
            const domain = document.getElementById('appDomain').value.trim();
            const version = document.getElementById('appVersion').value;
            const startup = document.getElementById('startupFile').value.trim() || 'server.js';
            const githubRepo = document.getElementById('githubRepo')?.value.trim() || '';
            const githubBranch = document.getElementById('githubBranch')?.value.trim() || 'main';
            const rootDir = document.getElementById('rootDir')?.value.trim() || '';
            const installCmd = document.getElementById('installCmd')?.value.trim() || '';
            const postInstallCmd = document.getElementById('postInstallCmd')?.value.trim() || '';
            const buildCmd = document.getElementById('buildCmd')?.value.trim() || '';
            const startCmd = document.getElementById('startCmd')?.value.trim() || '';
            const btn = document.querySelector('button[onclick="createApp()"]');

            if (!name) { showNotification('Enter application name', 'error'); return; }

            btn.disabled = true;
            const loadingText = githubRepo ? 'Cloning & Creating...' : 'Creating...';
            btn.innerHTML = `<span class="spinner" style="margin-right:8px;"></span>${loadingText}`;

            try {
                const payload = {
                    type: 'nodejs',
                    name: name,
                    domain: domain,
                    version: version,
                    startup_file: startup
                };
                // Add GitHub params if provided
                if (githubRepo) {
                    payload.github_repo = githubRepo;
                    payload.github_branch = githubBranch;
                }
                // Add root directory if provided (for monorepos)
                if (rootDir) payload.root_directory = rootDir;
                // Add commands if provided
                if (installCmd) payload.install_command = installCmd;
                if (postInstallCmd) payload.post_install_command = postInstallCmd;
                if (buildCmd) payload.build_command = buildCmd;
                if (startCmd) payload.start_command = startCmd;
                // Add env vars
                const envData = getEnvVars();
                if (envData.env_keys.length > 0) {
                    payload.env_keys = envData.env_keys;
                    payload.env_values = envData.env_values;
                }

                const res = await fetch(`${apiUrl}/services`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok) {
                    if (githubRepo) {
                        showNotification('Deployment started! Showing live logs...', 'info', 3000);
                        // Show deployment logs modal
                        showDeploymentLogs(data.service.id, data.service.name);
                    } else {
                        showNotification('Application created successfully', 'success');
                    }

                    document.getElementById('appName').value = '';
                    if (document.getElementById('githubRepo')) document.getElementById('githubRepo').value = '';

                    // Reload list to see changes (will show Pending status for github apps)
                    loadApps();
                } else {
                    showCustomAlert({ title: 'Failed', message: data.details || data.message || data.error });
                }
            } catch (e) { showNotification('Network error', 'error'); }
            finally {
                btn.disabled = false;
                btn.innerHTML = 'Create Application';
            }
        }

        async function manageApp(id, action) {
            if (action === 'delete') {
                if (!(await showCustomConfirm('Delete App?', 'This cannot be undone.', true))) return;
                try {
                    const res = await fetch(`${apiUrl}/services/${id}`, { method: 'DELETE', headers });
                    if (res.ok) { loadApps(); showNotification('App deleted', 'success'); }
                } catch (e) { }
                return;
            }

            // Start/Stop
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;"></span>';
            btn.disabled = true;

            try {
                const res = await fetch(`${apiUrl}/services/${id}/${action}`, { method: 'POST', headers });
                if (res.ok) {
                    loadApps();
                    showNotification(action === 'start' ? 'App started' : 'App stopped', 'success');
                }
                else {
                    showNotification('Action failed', 'error');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    lucide.createIcons();
                }
            } catch (e) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                lucide.createIcons();
            }
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
                .replace(/(pip|npm|node|git|python|yarn|docker)/gi, '<span style="color: #dcdcaa;">$&</span>');
        }

        async function fetchDeploymentLogs(serviceId) {
            try {
                const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
                const res = await fetch(`${apiUrl}/services/${serviceId}/logs`, {
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
                            if (logLower.includes('=== app failed ===') || logLower.includes('process failed') || logLower.includes('failed to configure') || logLower.includes('error') || logLower.includes('permission denied')) {
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
            loadApps();
        }
    </script>

    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../../shared/layouts/main.php';
    ?>