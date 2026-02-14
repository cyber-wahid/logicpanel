<?php
$page_title = 'Reseller Packages';
$current_page = 'reseller_packages';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Reseller Packages</div>
        <div class="card-actions">
            <a href="<?= $base_url ?? '' ?>/reseller-packages/create" class="btn btn-primary">
                <i data-lucide="plus"></i> Create Reseller Package
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="packagesTable">
                <thead>
                    <tr>
                        <th>Package Name</th>
                        <th>Storage</th>
                        <th>Bandwidth</th>
                        <th>Max Services</th>
                        <th>Max Databases</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const API = '/public/api/master/packages';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

    async function loadPackages() {
        try {
            const res = await fetch(API + '?type=reseller', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (res.ok && data.packages) {
                renderTable(data.packages);
            } else {
                showNotification('Failed to load packages', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        }
    }

    function renderTable(packages) {
        const tbody = document.querySelector('#packagesTable tbody');

        if (packages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No reseller packages found. <a href="<?= $base_url ?? '' ?>/reseller-packages/create">Create one now</a></td></tr>';
            return;
        }

        tbody.innerHTML = packages.map(pkg => `
            <tr>
                <td>
                    <strong>${pkg.name}</strong>
                    ${pkg.is_global ? '<span class="badge badge-info ml-2">Global</span>' : ''}
                </td>
                <td>${formatSize(pkg.storage_limit)}</td>
                <td>${formatSize(pkg.bandwidth_limit)}</td>
                <td>${pkg.max_services}</td>
                <td>${pkg.max_databases}</td>
                <td>
                    <span class="badge badge-success">Active</span>
                </td>
                <td>
                    <button onclick="editPackage(${pkg.id})" class="btn btn-sm btn-secondary" title="Edit">
                        <i data-lucide="edit"></i>
                    </button>
                    <button onclick="deletePackage(${pkg.id}, '${pkg.name}')" class="btn btn-sm btn-danger" title="Delete">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    function formatSize(mb) {
        if (mb >= 1024) {
            return (mb / 1024).toFixed(1) + ' GB';
        }
        return mb + ' MB';
    }

    function editPackage(id) {
        window.location.href = `${window.base_url}/reseller-packages/edit?id=${id}`;
    }

    async function deletePackage(id, name) {
        if (!await confirmAction(`Are you sure you want to delete package "${name}"?`, 'Delete Package?')) return;

        try {
            const res = await fetch(`${API}/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (res.ok) {
                showNotification('Package deleted successfully', 'success');
                loadPackages();
            } else {
                const data = await res.json();
                showNotification(data.error || 'Failed to delete package', 'error');
            }
        } catch (e) {
            showNotification('Network error', 'error');
        }
    }

    // Load on page load
    loadPackages();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>