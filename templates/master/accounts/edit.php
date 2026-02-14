<?php
$page_title = 'Edit Account';
$current_page = 'accounts';
$sidebar_type = 'master';
ob_start();
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">Edit Account</div>
    </div>
    <div class="card-body">
        <form id="editAccountForm">
            <input type="hidden" name="id" id="accountId">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Package</label>
                <select name="package_id" class="form-control" required>
                    <option value="">Loading...</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>

            <div class="form-group">
                <label>New Password (leave empty to keep current)</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i> Save Changes
                </button>
                <a href="<?= $base_url ?? '' ?>/accounts" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    const API = '/public/api/master/accounts';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
    const accountId = new URLSearchParams(window.location.search).get('id');

    async function loadAccount() {
        try {
            const res = await fetch(`${API}/${accountId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok) {
                document.getElementById('accountId').value = data.id;
                document.querySelector('[name="username"]').value = data.username;
                document.querySelector('[name="email"]').value = data.email;
                document.querySelector('[name="package_id"]').value = data.package_id;
                document.querySelector('[name="status"]').value = data.status;
            }
        } catch (e) {
            showNotification('Failed to load account', 'error');
        }
    }

    async function loadPackages() {
        try {
            const res = await fetch('/public/api/master/packages', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok) {
                const select = document.querySelector('[name="package_id"]');
                select.innerHTML = data.packages.map(pkg => 
                    `<option value="${pkg.id}">${pkg.name}</option>`
                ).join('');
            }
        } catch (e) {
            console.error(e);
        }
    }

    document.getElementById('editAccountForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        // Remove password if empty
        if (!data.password) delete data.password;

        try {
            const res = await fetch(`${API}/${accountId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });
            
            const result = await res.json();

            if (res.ok) {
                showNotification('Account updated successfully', 'success');
                setTimeout(() => window.location.href = '<?= $base_url ?? '' ?>/accounts', 1500);
            } else {
                showNotification(result.error || 'Failed to update account', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    });

    // Load data
    loadPackages();
    loadAccount();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>
