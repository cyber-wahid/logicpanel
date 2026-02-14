<?php
$page_title = 'Create Reseller';
$current_page = 'resellers';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Create Reseller Account</div>
    </div>
    <div class="card-body">
        <form id="createResellerForm">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="package_id">Reseller Package *</label>
                <select class="form-control" id="package_id" name="package_id" required>
                    <option value="">Loading packages...</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="user-plus"></i> Create Reseller
                </button>
                <a href="<?= $base_url ?? '' ?>/resellers" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    const API = '/public/api/master';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

    // Load reseller packages
    async function loadPackages() {
        try {
            const res = await fetch(`${API}/packages?type=reseller`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok && data.packages) {
                const select = document.getElementById('package_id');
                select.innerHTML = '<option value="">Select a package</option>' +
                    data.packages.map(pkg => `<option value="${pkg.id}">${pkg.name}</option>`).join('');
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Handle form submission
    document.getElementById('createResellerForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const data = {
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password'),
            package_id: formData.get('package_id'),
            role: 'reseller'
        };

        try {
            const res = await fetch(`${API}/accounts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (res.ok) {
                showNotification('Reseller created successfully', 'success');
                setTimeout(() => {
                    window.location.href = '<?= $base_url ?? '' ?>/resellers';
                }, 1500);
            } else {
                showNotification(result.error || 'Failed to create reseller', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        }
    });

    // Load packages on page load
    loadPackages();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>
