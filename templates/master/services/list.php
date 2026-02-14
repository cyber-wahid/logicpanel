<?php
$page_title = 'Service Manager';
$current_page = 'services';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="card-title">Container Management</div>
        <div class="d-flex gap-10">
            <select id="bulkActionSelect" class="form-control" style="width: 150px; display: inline-block;">
                <option value="">Bulk Actions</option>
                <option value="start">Start Selected</option>
                <option value="stop">Stop Selected</option>
                <option value="restart">Restart Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button class="btn btn-primary btn-sm" onclick="applyBulkAction()">Apply</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table" id="svcTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        </th>
                        <th>Service Name</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Container ID</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-muted" style="text-align:center; padding:20px;">
                            <i class="lucide-loader-2 spin-anim" style="vertical-align:middle; margin-right:8px;"></i>
                            Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const API = '/public/api/master/services';
    const token = window.apiToken || sessionStorage.getItem('token');
    const headers = { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token };

    async function loadServices() {
        if (!token) return;
        try {
            const res = await fetch(API, { headers });
            const data = await res.json();
            const tbody = document.querySelector('#svcTable tbody');

            if (res.ok && data.services && data.services.length > 0) {
                tbody.innerHTML = data.services.map(s => {
                    const statusClass = s.status === 'running' ? 'badge-success' : (s.status === 'deploying' ? 'badge-warning' : 'badge-danger');
                    let actionButtons = '';

                    if (s.status === 'running') {
                        actionButtons += `
                            <button class="btn btn-warning btn-icon btn-sm" title="Stop" onclick="performAction(${s.id}, 'stop')"><i data-lucide="square" style="fill:currentColor;"></i></button>
                            <button class="btn btn-secondary btn-icon btn-sm" title="Restart" onclick="performAction(${s.id}, 'restart')"><i data-lucide="refresh-cw"></i></button>
                        `;
                    } else if (s.status === 'stopped' || s.status === 'error') {
                        actionButtons += `
                            <button class="btn btn-success btn-icon btn-sm" title="Start" onclick="performAction(${s.id}, 'start')"><i data-lucide="play" style="fill:currentColor;"></i></button>
                        `;
                    } else if (s.status === 'deploying') {
                        actionButtons += `<span class="badge badge-warning">Deploying...</span>`;
                    } else {
                        actionButtons += `<span class="text-muted">...</span>`;
                    }

                    actionButtons += `
                        <button class="btn btn-danger btn-icon btn-sm" title="Delete" onclick="performAction(${s.id}, 'delete')"><i data-lucide="trash-2"></i></button>
                    `;

                    return `
                    <tr>
                        <td><input type="checkbox" class="svc-checkbox" value="${s.id}"></td>
                        <td><strong>${s.name}</strong></td>
                        <td>
                            ${s.user || s.user_id}
                            ${s.user_owner ? `<br><small class="text-muted" title="User created by reseller"><i data-lucide="user-check" style="width:12px;height:12px;"></i> ${s.user_owner.username}</small>` : ''}
                        </td>
                        <td><span class="badge badge-secondary">${s.type}</span></td>
                        <td><span class="badge ${statusClass}">${s.status}</span></td>
                        <td><code style="background:var(--bg-input); padding:2px 4px; border-radius:3px;">${(s.container_id || 'N/A').substring(0, 12)}</code></td>
                        <td style="text-align: right;">
                            <div class="d-flex gap-5 justify-content-end">
                                ${actionButtons}
                            </div>
                        </td>
                    </tr>
                `}).join('');
                if (window.lucide) lucide.createIcons();
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;" class="text-muted">No services found</td></tr>';
            }
        } catch (e) {
            console.error(e);
            document.querySelector('#svcTable tbody').innerHTML = '<tr><td colspan="7" class="text-danger" style="text-align:center;">Failed to load services</td></tr>';
        }
    }

    function toggleSelectAll(source) {
        document.querySelectorAll('.svc-checkbox').forEach(cb => cb.checked = source.checked);
    }

    async function performAction(id, action) {
        let confirmMsg = `Are you sure you want to ${action} this service?`;
        if (action === 'delete') confirmMsg = 'Are you sure you want to DELETE this service? This cannot be undone.';

        const confirmed = await showCustomConfirm(
            action.charAt(0).toUpperCase() + action.slice(1) + ' Service?',
            confirmMsg,
            action === 'delete' // isDanger
        );

        if (!confirmed) return;

        try {
            let url = `${API}/${id}/${action}`;
            if (action === 'delete') url = `${API}/${id}`;

            const method = action === 'delete' ? 'DELETE' : 'POST';

            const res = await fetch(url, { method, headers });
            const data = await res.json();

            if (res.ok) {
                showNotification(data.message, 'success');
                loadServices();
            } else {
                showNotification(data.error || 'Action failed', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    async function applyBulkAction() {
        const action = document.getElementById('bulkActionSelect').value;
        if (!action) {
            showNotification('Please select an action', 'warning');
            return;
        }

        const selected = Array.from(document.querySelectorAll('.svc-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            showNotification('No services selected', 'warning');
            return;
        }

        const confirmed = await showCustomConfirm(
            'Bulk Action',
            `Are you sure you want to ${action} ${selected.length} services?`,
            action === 'delete'
        );

        if (!confirmed) return;

        try {
            const res = await fetch(`${API}/bulk-action`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ ids: selected, action })
            });
            const data = await res.json();

            if (res.ok) {
                showNotification(data.message, 'success');
                loadServices();
                document.getElementById('selectAll').checked = false;
            } else {
                showNotification(data.error || 'Bulk action failed', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    loadServices();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>