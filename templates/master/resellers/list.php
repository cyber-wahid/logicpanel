<?php
$page_title = 'Reseller Management';
$current_page = 'resellers';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Reseller Accounts</div>
        <div class="card-actions">
            <a href="<?= $base_url ?? '' ?>/resellers/create" class="btn btn-primary">
                <i data-lucide="user-plus"></i> Create Reseller
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="resellersTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Created</th>
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
    const API = '/public/api/master/accounts';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

    async function loadResellers() {
        try {
            const res = await fetch(API + '?role=reseller', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok && data.accounts) {
                renderTable(data.accounts);
            } else {
                showNotification('Failed to load resellers', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        }
    }

    function renderTable(accounts) {
        const tbody = document.querySelector('#resellersTable tbody');
        
        if (accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No resellers found</td></tr>';
            return;
        }

        tbody.innerHTML = accounts.map(account => `
            <tr>
                <td><strong>${account.username}</strong></td>
                <td>${account.email}</td>
                <td>${account.package || 'N/A'}</td>
                <td>
                    <span class="badge badge-${account.status === 'active' ? 'success' : 'danger'}">
                        ${account.status}
                    </span>
                </td>
                <td>${new Date(account.created_at).toLocaleDateString()}</td>
                <td>
                    <button onclick="loginAs(${account.id})" class="btn btn-sm btn-primary" title="Login as Reseller">
                        <i data-lucide="log-in"></i>
                    </button>
                    <button onclick="editAccount(${account.id})" class="btn btn-sm btn-secondary" title="Edit">
                        <i data-lucide="edit"></i>
                    </button>
                    <button onclick="deleteReseller(${account.id}, '${account.username}')" class="btn btn-sm btn-danger" title="Delete">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    async function loginAs(id) {
        try {
            const res = await fetch(`${API}/${id}/login`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok && data.token) {
                window.open(`${window.base_url}/?token=${data.token}`, '_blank');
            } else {
                showNotification(data.error || 'Failed to login', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    function editAccount(id) {
        window.location.href = `${window.base_url}/accounts/edit?id=${id}`;
    }

    async function deleteReseller(id, username) {
        showConfirm(
            'Delete Reseller',
            `Are you sure you want to delete reseller "<strong>${username}</strong>"?<br><br>This action cannot be undone. The reseller must have no users before deletion.`,
            async () => {
                try {
                    const res = await fetch(`${API}/../resellers/${id}`, {
                        method: 'DELETE',
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    const data = await res.json();

                    if (res.ok) {
                        showNotification(data.message || 'Reseller deleted successfully', 'success');
                        loadResellers(); // Reload the table
                    } else {
                        showNotification(data.error || 'Failed to delete reseller', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    showNotification('Network error', 'error');
                }
            }
        );
    }

    // Load on page load
    loadResellers();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>
