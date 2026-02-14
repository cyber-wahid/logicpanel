<?php
$page_title = 'Locked Accounts';
$current_page = 'locked_accounts';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Locked Accounts</div>
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex align-items-start gap-3 mb-4">
            <i data-lucide="info" style="min-width: 20px; margin-top: 2px;"></i>
            <div>&nbsp;
                <strong>Security Notice: </strong> Accounts are automatically locked after multiple failed login
                attempts. You can unlock them here to restore access.
            </div>
        </div><br>

        <div class="table-responsive">
            <table class="table" id="lockedAccountsTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Locked At</th>
                        <th>Failed Attempts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const API = '/public/api/master';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

    async function loadLockedAccounts() {
        try {
            const res = await fetch(`${API}/accounts?status=locked`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok && data.accounts) {
                renderTable(data.accounts);
            } else {
                showNotification('Failed to load locked accounts', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        }
    }

    function renderTable(accounts) {
        const tbody = document.querySelector('#lockedAccountsTable tbody');

        if (accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No locked accounts found.</td></tr>';
            return;
        }

        tbody.innerHTML = accounts.map(acc => `
            <tr>
                <td><strong>${acc.username}</strong></td>
                <td>${acc.email}</td>
                <td><span class="badge badge-${acc.role === 'admin' ? 'danger' : acc.role === 'reseller' ? 'warning' : 'info'}">${acc.role}</span></td>
                <td>${acc.locked_at ? new Date(acc.locked_at).toLocaleString() : 'N/A'}</td>
                <td>${acc.failed_attempts || 0}</td>
                <td>
                    <button onclick="unlockAccount(${acc.id}, '${acc.username}')" class="btn btn-sm btn-success" title="Unlock">
                        <i data-lucide="unlock"></i> Unlock
                    </button>
                </td>
            </tr>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    async function unlockAccount(id, username) {
        if (!await confirmAction(`Are you sure you want to unlock account "${username}"?`, 'Unlock Account?')) return;

        try {
            const res = await fetch(`${API}/accounts/${id}/unlock`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (res.ok) {
                showNotification('Account unlocked successfully', 'success');
                loadLockedAccounts();
            } else {
                const data = await res.json();
                showNotification(data.error || 'Failed to unlock account', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    // Load on page load
    loadLockedAccounts();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>