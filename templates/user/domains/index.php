<?php
$page_title = 'Addon Domains';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Manage Domains</h1>
        <p>Manage custom domains for your applications. Connect your own domains to replace the default subdomains.</p>
    </div>

    <div class="db-section">
        <div class="db-toolbar" style="justify-content: flex-end;">
            <div class="search-box">
                <input type="text" id="searchApp" class="form-control" placeholder="Search applications..."
                    oninput="filterApps()">
            </div>
        </div>

        <div class="table-responsive">
            <table class="db-table" id="domainTable">
                <thead>
                    <tr>
                        <th style="width: 30%;">Application</th>
                        <th style="width: 40%;">Domains</th>
                        <th style="width: 15%;">Status</th>
                        <th class="actions-col" style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="appList">
                    <tr>
                        <td colspan="4" class="text-center" style="padding: 20px;">Loading applications...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Domains Modal -->
<div id="domainModal" class="domain-modal-overlay">
    <div class="domain-modal-box">
        <div class="domain-modal-header">
            <h3 style="margin:0; font-size:16px; font-weight:600;">Manage Addon Domains</h3>
            <button type="button" class="domain-modal-close" onclick="closeModal('domainModal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="domain-modal-body">
            <div style="margin-bottom: 20px;">
                <h4 id="modalAppName" class="modal-app-name">MyApp</h4>
                <p class="modal-app-desc">Add custom domains to your application.</p>
            </div>

            <!-- Default Subdomain (Read-only) -->
            <div class="default-domain-section">
                <label class="domain-label">Default Subdomain</label>
                <div class="default-domain-display">
                    <span id="defaultDomainDisplay">-</span>
                    <span class="domain-badge">System Assigned</span>
                </div>
                <small class="domain-hint">This subdomain is automatically assigned and cannot be changed.</small>
            </div>

            <!-- Addon Domains (Editable) -->
            <div style="margin-top:20px;">
                <label for="addonDomainInput" class="domain-label">Addon Domains (Comma Separated)</label>
                <textarea id="addonDomainInput" class="domain-input" rows="3"
                    placeholder="mysite.com, www.mysite.com"></textarea>
                <small class="domain-hint">
                    Enter your custom domains separated by commas. Make sure DNS is pointed to this server.<br>
                    <span class="domain-warning">⚠️ Changes will restart the application.</span>
                </small>
            </div>
        </div>
        <div class="domain-modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('domainModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveDomains()">Save Changes</button>
        </div>
    </div>
</div>

<style>
    /* Domain Modal - Explicit Styles */
    .domain-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99999;
    }

    .domain-modal-overlay.active {
        display: flex !important;
    }

    .domain-modal-box {
        background: #ffffff;
        width: 500px;
        max-width: 90%;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        overflow: hidden;
    }

    [data-theme="dark"] .domain-modal-box {
        background: #1a1a2e;
        border: 1px solid #333;
    }

    .domain-modal-header {
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
    }

    [data-theme="dark"] .domain-modal-header {
        background: #10161bff;
        border-bottom-color: #333;
        color: #fff;
    }

    .domain-modal-close {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #666;
        border-radius: 4px;
    }

    .domain-modal-close:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    [data-theme="dark"] .domain-modal-close {
        color: #aaa;
    }

    .domain-modal-body {
        padding: 20px;
        background: #fff;
        color: #333;
    }

    [data-theme="dark"] .domain-modal-body {
        background: #10161bff;
        color: #eee;
    }

    .domain-modal-footer {
        padding: 15px 20px;
        background: #f5f5f5;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    [data-theme="dark"] .domain-modal-footer {
        background: #10161bff;
        border-top-color: #333;
    }

    .domain-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        background: #fff;
        color: #333;
        box-sizing: border-box;
    }

    [data-theme="dark"] .domain-input {
        background: #10161bff;
        border-color: #444;
        color: #eee;
    }

    .subdomain-section {
        margin-top: 15px;
        padding: 12px;
        background: rgba(60, 135, 58, 0.1);
        border: 1px dashed #3C873A;
        border-radius: 6px;
    }

    /* Modal content styles with dark mode */
    .modal-app-name {
        margin: 0 0 5px 0;
        color: #3C873A;
    }

    .modal-app-desc {
        font-size: 12px;
        color: #666;
        margin: 0;
    }

    [data-theme="dark"] .modal-app-desc {
        color: #aaa;
    }

    .domain-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }

    [data-theme="dark"] .domain-label {
        color: #eee;
    }

    .domain-hint {
        display: block;
        margin-top: 8px;
        color: #666;
    }

    [data-theme="dark"] .domain-hint {
        color: #aaa;
    }

    .domain-warning {
        color: #f59e0b;
    }

    /* Default domain section styles */
    .default-domain-section {
        padding: 15px;
        background: rgba(60, 135, 58, 0.08);
        border: 1px solid rgba(60, 135, 58, 0.2);
        border-radius: 8px;
    }

    .default-domain-display {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        padding: 10px 12px;
        background: rgba(60, 135, 58, 0.1);
        border-radius: 6px;
        font-family: monospace;
        font-size: 14px;
        color: #3C873A;
    }

    [data-theme="dark"] .default-domain-display {
        background: rgba(60, 135, 58, 0.15);
    }

    .domain-badge {
        font-size: 10px;
        padding: 2px 8px;
        background: #3C873A;
        color: #fff;
        border-radius: 4px;
        font-family: sans-serif;
        font-weight: 600;
        text-transform: uppercase;
    }
</style>

<style>
    .db-container {
        padding: 0 15px;
    }

    .db-page-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 5px 0;
    }

    .db-page-header p {
        color: var(--text-secondary);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .db-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 15px;
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .db-table th {
        background: var(--bg-input);
        padding: 10px 15px;
        text-align: left;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
    }

    .db-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: top;
    }

    .domain-tag {
        display: inline-block;
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        margin: 2px;
        font-family: monospace;
        color: var(--text-primary);
    }

    .domain-tag.primary {
        background: rgba(60, 135, 58, 0.1);
        border-color: rgba(60, 135, 58, 0.2);
        color: var(--primary);
        font-weight: 500;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: var(--bg-card, #ffffff);
        width: 500px;
        max-width: 90%;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        border: 1px solid var(--border-color);
    }

    [data-theme="dark"] .modal {
        background: #1e1e2e;
        border-color: #3a3a4a;
    }

    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-input);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 15px 20px;
        background: var(--bg-input);
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: var(--text-secondary);
        border-radius: 4px;
    }

    .btn-icon:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--text-primary);
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const API_BASE = '<?= $base_url ?? '' ?>/api';
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;

    let allApps = [];
    let currentEditingApp = null;
    let sharedDomain = '';

    document.addEventListener('DOMContentLoaded', () => {
        loadApps();
        fetchSettings();
        lucide.createIcons();
    });

    async function fetchSettings() {
        try {
            const res = await fetch(`${API_BASE}/auth/settings`, {
                headers: { 'Authorization': `Bearer ${TOKEN}` }
            });
            const data = await res.json();
            sharedDomain = data.shared_domain || data.hostname || 'cyberit.cloud';
            if (sharedDomain) {
                document.getElementById('baseDomainLabel').innerText = sharedDomain;
                document.getElementById('sharedDomainSection').style.display = 'block';
            }
        } catch (e) {
            console.error('Failed to fetch settings:', e);
        }
    }

    function applySubdomain() {
        const prefix = document.getElementById('subdomainPrefix').value.trim();
        if (!prefix) return;

        const full = `${prefix}.${sharedDomain}`;
        const input = document.getElementById('domainInput');
        const current = input.value.trim();

        if (current) {
            if (!current.includes(full)) {
                input.value = current + ', ' + full;
            }
        } else {
            input.value = full;
        }

        document.getElementById('subdomainPrefix').value = '';
        showNotification('Subdomain added to list', 'success');
    }

    async function loadApps() {
        try {
            const res = await fetch(`${API_BASE}/services`, {
                headers: { 'Authorization': `Bearer ${TOKEN}` }
            });
            const data = await res.json();

            if (data.services) {
                allApps = data.services;
                renderApps();
            }
        } catch (e) {
            console.error(e);
            document.getElementById('appList').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading applications.</td></tr>';
        }
    }

    function renderApps() {
        const tbody = document.getElementById('appList');
        const search = document.getElementById('searchApp').value.toLowerCase();

        const filtered = allApps.filter(app => app.name.toLowerCase().includes(search));

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:20px; color:var(--text-muted)">No applications found.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(app => {
            const domains = app.domain ? app.domain.split(',').map(d => d.trim()) : [];
            const domainTags = domains.map((d, i) =>
                `<span class="domain-tag ${i === 0 ? 'primary' : ''}">${d}</span>`
            ).join(' ');

            return `
                <tr>
                    <td>
                        <div style="font-weight:600;">${app.name}</div>
                        <div style="font-size:12px; color:var(--text-secondary); text-transform:capitalize;">${app.type} App</div>
                    </td>
                    <td>${domainTags || '<span class="text-muted">No domains</span>'}</td>
                    <td>
                        <span class="badge ${app.status === 'running' ? 'badge-success' : 'badge-secondary'}">
                            ${app.status}
                        </span>
                    </td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-primary" onclick="openManageModal(${app.id})">
                            <i data-lucide="globe"></i> Manage
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        lucide.createIcons();
    }

    function filterApps() {
        renderApps();
    }

    function openManageModal(appId) {
        const app = allApps.find(a => a.id === appId);
        if (!app) return;

        currentEditingApp = app;
        document.getElementById('modalAppName').innerText = app.name;

        // Parse domains - first one is always the default subdomain (system assigned)
        const allDomains = app.domain ? app.domain.split(',').map(d => d.trim()).filter(d => d) : [];
        const defaultDomain = allDomains[0] || '-';
        const addonDomains = allDomains.slice(1); // Everything after the first is addon

        // Display default subdomain (read-only)
        document.getElementById('defaultDomainDisplay').innerText = defaultDomain;

        // Fill addon domains textarea
        document.getElementById('addonDomainInput').value = addonDomains.join(', ');

        document.getElementById('domainModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        currentEditingApp = null;
    }

    async function saveDomains() {
        if (!currentEditingApp) return;

        // Get the default subdomain (preserved, not editable)
        const defaultDomain = document.getElementById('defaultDomainDisplay').innerText;

        // Get addon domains from textarea
        const addonDomainsRaw = document.getElementById('addonDomainInput').value;
        const addonDomains = addonDomainsRaw
            .split(',')
            .map(d => d.trim())
            .filter(d => d && d !== defaultDomain); // Remove empty and duplicates of default

        // Combine: default subdomain first, then addon domains
        const allDomains = [defaultDomain, ...addonDomains].filter(d => d && d !== '-');
        const newDomainString = allDomains.join(',');

        const btn = event.currentTarget;
        const originalText = btn.innerHTML;

        btn.innerHTML = '<span class="spin-anim" style="display:inline-block; border:2px solid #fff; border-top-color:transparent; border-radius:50%; width:14px; height:14px;"></span> Saving...';
        btn.disabled = true;

        try {
            const res = await fetch(`${API_BASE}/services/${currentEditingApp.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN}`
                },
                body: JSON.stringify({
                    domain: newDomainString
                })
            });

            const data = await res.json();

            if (res.ok) {
                showNotification('Addon domains updated successfully. Application restarting...', 'success');
                closeModal('domainModal');
                loadApps(); // Refresh list
            } else {
                showNotification(data.message || data.error || 'Failed to update domains', 'error');
            }
        } catch (e) {
            showNotification('Network error occurred', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Fallback confirmation if needed
    if (typeof showNotification === 'undefined') {
        window.showNotification = (msg, type) => alert(msg);
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>