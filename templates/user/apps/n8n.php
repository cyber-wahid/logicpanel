<?php
$page_title = 'n8n Workflow Automation';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>n8n Workflow Automation</h1>
        <p>Deploy a self-hosted n8n instance with one click. Includes a dedicated PostgreSQL database.</p>
    </div>

    <!-- Create App Section -->
    <div class="db-section">
        <h2>Create New n8n Instance</h2>

        <!-- Hidden Type Field -->
        <input type="hidden" id="appType" value="n8n">

        <div class="db-form-group">
            <label for="appName">Instance Name:</label>
            <div class="input-group">
                <input type="text" id="appName" class="form-control"
                    placeholder="my-workflows">
            </div>
            <small style="color:var(--text-secondary);font-size:12px;">Used for identification and to generate the subdomain.</small>
        </div>

        <div class="db-form-group">
            <label for="appDomain">Custom Domain (Optional):</label>
            <input type="text" id="appDomain" class="form-control" placeholder="e.g. n8n.yourdomain.com">
            <small style="color:var(--text-secondary);font-size:11px;">Leave empty to use the default subdomain
                (appname.<?= $app_base_domain ?>).</small>
        </div>

        <div style="background:rgba(60,135,58,0.08);border:1px solid rgba(60,135,58,0.2);border-radius:6px;padding:12px;margin-bottom:16px;">
            <div style="display:flex;gap:8px;align-items:flex-start;">
                <i data-lucide="info" style="width:16px;height:16px;color:#3C873A;margin-top:2px;flex-shrink:0;"></i>
                <div style="font-size:12px;color:var(--text-secondary);line-height:1.5;">
                    <strong style="color:var(--text-primary);">What's included:</strong> A dedicated n8n container with automatic SSL,
                    a PostgreSQL database for workflow storage, and a unique subdomain. You can access the n8n editor immediately after deployment.
                </div>
            </div>
        </div>

        <div class="db-form-group" style="margin-top:20px;">
            <button class="btn btn-primary btn-block-mobile" onclick="createApp()">Deploy n8n</button>
        </div>

        <!-- Current n8n Instances -->
        <div class="db-section" style="margin-top:20px;">
            <h2>Current n8n Instances</h2>
            <div class="table-responsive">
                <table class="db-table" id="appTable">
                    <thead>
                        <tr>
                            <th>Instance</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="appList">
                        <tr>
                            <td colspan="4" class="text-center" style="padding:20px;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .db-container { padding: 10px; width: 100%; max-width: 100%; }
    .db-page-header h1 { font-size: 20px; font-weight: 500; margin: 0 0 5px 0; color: var(--text-primary); }
    .db-page-header p { color: var(--text-secondary); font-size: 13px; margin-bottom: 20px; }
    .db-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; padding: 15px; margin-bottom: 20px; }
    .db-section h2 { font-size: 14px; font-weight: 600; margin: 0 0 12px 0; color: var(--text-primary); }
    .db-form-group { margin-bottom: 14px; }
    .db-form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: var(--text-primary); }
    .form-control { padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-primary); height: 38px; font-size: 14px; width: 100%; }
    .btn { padding: 8px 14px; border-radius: 4px; cursor: pointer; border: 1px solid transparent; color: #fff; height: 36px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; }
    .btn-primary { background-color: #3C873A; border-color: #3C873A; }
    .btn-primary:hover { background-color: #2D6A2E; }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .btn-secondary { background: var(--bg-input); color: var(--text-primary); border-color: var(--border-color); }
    .btn-secondary:hover { background: var(--bg-hover); }
    .btn-warning { background: #F59E0B; border-color: #F59E0B; }
    .btn-success { background: #10B981; border-color: #10B981; }
    .btn-danger { background: #EF4444; border-color: #EF4444; }
    .btn-sm { height: 28px; padding: 4px 8px; font-size: 11px; }
    .btn-sm svg { width: 14px; height: 14px; }
    .db-table { width: 100%; border-collapse: collapse; }
    .db-table th { text-align: left; padding: 8px; font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
    .db-table td { padding: 8px; font-size: 12px; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }
    .status-running { background: rgba(16,185,129,0.1); color: #10B981; }
    .status-stopped { background: rgba(239,68,68,0.1); color: #EF4444; }
    .status-deploying { background: rgba(245,158,11,0.1); color: #F59E0B; }
    .status-error { background: rgba(239,68,68,0.1); color: #EF4444; }
    .spinner { width: 16px; height: 16px; border: 2px solid transparent; border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
    const apiUrl = (window.base_url || '') + '/api';
    const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
    const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };

    document.addEventListener('DOMContentLoaded', () => { loadApps(); });

    async function loadApps() {
        const tbody = document.getElementById('appList');
        try {
            const res = await fetch(`${apiUrl}/services`, { headers });
            const data = await res.json();
            const apps = (data.services || []).filter(app => app.type === 'n8n');

            if (apps.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:20px;color:var(--text-muted)">No n8n instances found. Deploy your first one above!</td></tr>';
                return;
            }

            tbody.innerHTML = apps.map(app => `
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px">${app.name}</div>
                        <div style="font-size:10px;color:var(--text-secondary);opacity:0.8">n8n</div>
                    </td>
                    <td>
                        <a href="${app.url || ('https://' + app.domain)}" target="_blank" style="font-family:monospace;background:var(--bg-input);padding:2px 6px;border-radius:3px;color:var(--primary-color);text-decoration:none;">
                            ${app.domain}
                        </a>
                    </td>
                    <td>
                        <span class="status-badge ${app.status === 'deploying' ? 'status-deploying' : 'status-' + app.status}">
                            ${app.status === 'deploying' ? 'Deploying...' : app.status}
                        </span>
                    </td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-secondary" onclick="window.open('${app.url || ('https://' + app.domain)}', '_blank')" title="Open n8n"><i data-lucide="external-link"></i></button>
                        ${app.status === 'running'
                            ? `<button class="btn btn-sm btn-warning" onclick="manageApp(${app.id}, 'stop')" title="Stop"><i data-lucide="square"></i></button>`
                            : (app.status === 'deploying'
                                ? `<button class="btn btn-sm btn-secondary" disabled><span class="spinner" style="width:12px;height:12px;border-width:2px;border-color:#666;"></span></button>`
                                : `<button class="btn btn-sm btn-success" onclick="manageApp(${app.id}, 'start')" title="Start"><i data-lucide="play"></i></button>`
                            )
                        }
                        <button class="btn btn-sm btn-danger" onclick="manageApp(${app.id}, 'delete')" title="Delete"><i data-lucide="trash-2"></i></button>
                    </td>
                </tr>
            `).join('');
            lucide.createIcons();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading instances</td></tr>';
        }
    }

    async function createApp() {
        const name = document.getElementById('appName').value.trim();
        const domain = document.getElementById('appDomain').value.trim();
        const btn = document.querySelector('button[onclick="createApp()"]');

        if (!name) { showNotification('Enter an instance name', 'error'); return; }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="margin-right:8px;"></span>Deploying n8n...';

        try {
            const payload = {
                type: 'n8n',
                name: name,
                domain: domain || ''
            };

            const res = await fetch(`${apiUrl}/services`, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showNotification('n8n deployment started! It will be ready in a moment.', 'success');
                document.getElementById('appName').value = '';
                document.getElementById('appDomain').value = '';
                loadApps();
            } else {
                showNotification(data.details || data.message || data.error || 'Deployment failed', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Deploy n8n';
        }
    }

    async function manageApp(id, action) {
        if (action === 'delete') {
            if (!confirm('This will permanently delete the n8n instance and its database. Continue?')) return;
            try {
                const res = await fetch(`${apiUrl}/services/${id}`, { method: 'DELETE', headers });
                if (res.ok) { loadApps(); showNotification('n8n instance deleted', 'success'); }
            } catch (e) { }
            return;
        }

        const btn = event.currentTarget;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;"></span>';

        try {
            const res = await fetch(`${apiUrl}/services/${id}/${action}`, { method: 'POST', headers });
            if (res.ok) { loadApps(); showNotification(`Instance ${action}ed`, 'success'); }
        } catch (e) { }

        btn.disabled = false;
        lucide.createIcons();
    }

    function showNotification(msg, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:6px;color:#fff;font-size:13px;z-index:10000;animation:fadeIn 0.3s;` +
            (type === 'error' ? 'background:#EF4444;' : 'background:#10B981;');
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../shared/layouts/main.php';
?>
