<?php
$title = "Application Backups";
$current_page = 'backups';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Application Backups</h1>
        <button class="btn btn-primary" onclick="openBackupModal()">
            <i data-lucide="folder-archive"></i> New App Backup
        </button>
    </div>

    <div class="db-card">
        <div class="table-wrapper">
            <table class="db-table" id="backupsTable">
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>TYPE</th>
                        <th>SIZE</th>
                        <th>DATE</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 20px;">Loading backups...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Backup Modal -->
<div id="backupModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeBackupModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Application Backup</h3>
            <button class="modal-close" onclick="closeBackupModal()">×</button>
        </div>
        <div class="modal-body">
            <p>Select the application to backup:</p>
            <div id="appSelectList" class="app-select-list">
                <div class="text-muted">Loading applications...</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const apiUrl = (window.base_url || '') + '/api';
    const backupToken = '<?= $_SESSION['lp_session_token'] ?? '' ?>';
    const headers = { 'Authorization': `Bearer ${backupToken}` };

    let userApps = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadApps();
        loadBackups();
    });

    async function loadApps() {
        try {
            const res = await fetch(`${apiUrl}/services`, { headers });
            const data = await res.json();
            userApps = data.services || [];
        } catch (e) {
            console.error('Failed to load apps:', e);
        }
    }

    function loadBackups() {
        fetch(`${apiUrl}/backups`, { headers })
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#backupsTable tbody');
                tbody.innerHTML = '';

                const backups = data.backups || [];

                if (backups.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding: 20px; color: var(--text-secondary);">No backups found. Create your first backup!</td></tr>';
                    return;
                }

                backups.forEach(backup => {
                    const isPending = backup.status === 'creating';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <div class="file-icon">
                                <i data-lucide="archive" style="color: ${isPending ? '#f59e0b' : 'var(--text-secondary)'};"></i>
                                <div>
                                    <span style="font-weight: 500; ${isPending ? 'color:#f59e0b;' : ''}">${backup.name}</span>
                                    ${backup.app_name ? `<div style="font-size:11px; color: var(--text-secondary);">${backup.app_name}</div>` : ''}
                                    ${isPending ? '<span style="font-size:10px; margin-left:5px; color:#f59e0b;">(Creating...)</span>' : ''}
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-success">Application</span></td>
                        <td>${backup.size || '-'}</td>
                        <td>${backup.date}</td>
                        <td>
                            <div class="action-buttons" style="justify-content: flex-end;">
                                ${isPending ?
                            `<span class="spinner" style="width:16px;height:16px;"></span>` :
                            `<button class="btn-icon" title="Download" onclick="downloadBackup('${backup.name}')">
                                        <i data-lucide="download"></i>
                                    </button>
                                    <button class="btn-icon" title="Restore" onclick="restoreBackup('${backup.name}', '${backup.service_id || ''}')">
                                        <i data-lucide="rotate-ccw"></i>
                                    </button>
                                    <button class="btn-icon danger" title="Delete" onclick="deleteBackup('${backup.name}')">
                                        <i data-lucide="trash-2"></i>
                                    </button>`
                        }
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                lucide.createIcons();
            })
            .catch(err => {
                console.error('Failed to load backups:', err);
                const tbody = document.querySelector('#backupsTable tbody');
                tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="color: var(--danger);">Failed to load backups</td></tr>';
            });
    }

    function openBackupModal() {
        document.getElementById('backupModal').style.display = 'flex';
        renderAppList();
    }

    function closeBackupModal() {
        document.getElementById('backupModal').style.display = 'none';
    }

    function renderAppList() {
        const container = document.getElementById('appSelectList');

        if (userApps.length === 0) {
            container.innerHTML = '<div class="text-muted">No applications found. Create an app first.</div>';
            return;
        }

        container.innerHTML = userApps.map(app => `
            <div class="app-select-item" onclick="createBackup(${app.id}, '${app.name}')">
                <div class="app-select-icon">
                    <i data-lucide="${app.type === 'nodejs' ? 'hexagon' : 'codepen'}"></i>
                </div>
                <div class="app-select-info">
                    <div class="app-select-name">${app.name}</div>
                    <div class="app-select-domain">${app.domain || app.type}</div>
                </div>
                <span class="badge ${app.status === 'running' ? 'badge-success' : 'badge-danger'}">${app.status}</span>
            </div>
        `).join('');
        lucide.createIcons();
    }

    async function createBackup(serviceId, appName) {
        closeBackupModal();

        showNotification(`Creating backup for ${appName}...`, 'info');

        try {
            const res = await fetch(`${apiUrl}/backups/app`, {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ service_id: serviceId })
            });
            const data = await res.json();

            if (data.error) {
                showNotification(data.error, 'error');
            } else {
                showNotification('Backup created successfully!', 'success');
                loadBackups();
            }
        } catch (e) {
            showNotification('Backup failed: ' + e.message, 'error');
        }
    }

    async function deleteBackup(filename) {
        if (!(await showCustomConfirm('Delete Backup?', `Are you sure you want to delete ${filename}?`, true))) return;

        try {
            const res = await fetch(`${apiUrl}/backups/${encodeURIComponent(filename)}`, {
                method: 'DELETE',
                headers
            });
            const data = await res.json();

            if (data.error) {
                showNotification(data.error, 'error');
            } else {
                showNotification('Backup deleted', 'success');
                loadBackups();
            }
        } catch (e) {
            showNotification('Delete failed', 'error');
        }
    }

    async function restoreBackup(filename, serviceId) {
        if (!(await showCustomConfirm('Restore Backup?',
            'WARNING: This will overwrite existing files in the application. This cannot be undone. Continue?', true))) {
            return;
        }

        showNotification('Restoring backup...', 'info');

        try {
            const res = await fetch(`${apiUrl}/backups/restore`, {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ filename, service_id: serviceId })
            });
            const data = await res.json();

            if (data.error) {
                showNotification('Restore failed: ' + data.error, 'error');
            } else {
                showNotification('Backup restored successfully!', 'success');
            }
        } catch (e) {
            showNotification('Restore failed: ' + e.message, 'error');
        }
    }

    function downloadBackup(filename) {
        window.location.href = `${apiUrl}/backups/download/${encodeURIComponent(filename)}?token=${backupToken}`;
    }
</script>

<style>
    .db-container {
        padding: 0;
        width: 100%;
        max-width: 100%;
    }

    .db-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .db-page-header h1 {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .db-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .db-table th,
    .db-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .db-table th {
        background: var(--bg-input);
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-secondary);
    }

    .db-table tr:last-child td {
        border-bottom: none;
    }

    .file-icon {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .file-icon svg {
        width: 18px;
        height: 18px;
    }

    .badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-success {
        background: rgba(60, 135, 58, 0.15);
        color: var(--primary);
    }

    .badge-danger {
        background: rgba(244, 67, 54, 0.15);
        color: var(--danger);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        background: none;
        border: 1px solid transparent;
        cursor: pointer;
        padding: 6px;
        color: var(--text-secondary);
        border-radius: 4px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon:hover {
        background: var(--bg-input);
        color: var(--primary);
        border-color: var(--border-color);
    }

    .btn-icon.danger:hover {
        color: var(--danger);
        background: rgba(244, 67, 54, 0.1);
        border-color: rgba(244, 67, 54, 0.2);
    }

    .btn-icon svg {
        width: 16px;
        height: 16px;
    }

    .text-center {
        text-align: center;
    }

    /* Modal */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        position: relative;
        background: var(--bg-card);
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0;
        line-height: 1;
    }

    .modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
        color: var(--text-primary);
    }

    /* App Select */
    .app-select-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 15px;
    }

    .app-select-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .app-select-item:hover {
        border-color: var(--primary);
        background: rgba(60, 135, 58, 0.05);
    }

    .app-select-icon {
        width: 40px;
        height: 40px;
        background: var(--bg-card);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
    }

    .app-select-icon svg {
        width: 20px;
        height: 20px;
    }

    .app-select-info {
        flex: 1;
    }

    .app-select-name {
        font-weight: 500;
        color: var(--text-primary);
    }

    .app-select-domain {
        font-size: 12px;
        color: var(--text-secondary);
    }

    @media (max-width: 600px) {
        .db-page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .db-actions {
            width: 100%;
        }

        .db-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>