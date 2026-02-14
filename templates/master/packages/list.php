<?php
$page_title = 'Packages';
$current_page = 'packages_list';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="card-title">Packages List</div>
        <div class="flex gap-2">
            <a href="create" class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Add Package</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table" id="packagesTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Storage</th>
                        <th>Bandwidth</th>
                        <th>Apps</th>
                        <th>Domains</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-muted" style="text-align:center; padding:20px;">
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
    const token = window.apiToken || sessionStorage.getItem('token');
    const headers = { 
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    };

    fetch('/public/api/master/packages?type=user', { headers })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            const tbody = document.querySelector('#packagesTable tbody');
            if (data.packages && data.packages.length > 0) {
                // Formatting helper
                const fmt = (mb) => {
                    if (!mb) return '0 MB';
                    if (mb >= 1024 && mb % 1024 === 0) return (mb / 1024) + ' GB';
                    return mb + ' MB';
                };

                tbody.innerHTML = data.packages.map(p => `
                <tr>
                    <td>
                        <strong>${p.name}</strong>
                        ${p.creator ? `<br><small class="text-muted" title="Created by reseller"><i data-lucide="user-check" style="width:12px;height:12px;"></i> ${p.creator.username}</small>` : ''}
                    </td>
                    <td>${fmt(p.storage_limit)}</td>
                    <td>${fmt(p.bandwidth_limit)}</td>
                    <td>${p.max_services || 0}</td>
                    <td>
                        <small title="Subdomains / Addon Domains">
                            ${p.max_subdomains || 0} / ${p.max_addon_domains || 0}
                        </small>
                    </td>
                    <td>
                        <a href="edit?id=${p.id}&type=user" class="btn btn-secondary btn-sm">Edit</a>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(${p.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
                if (window.lucide) lucide.createIcons();
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px;" class="text-muted">No packages found</td></tr>';
            }
        })
        .catch(e => {
            console.error('Package load error:', e);
            document.querySelector('#packagesTable tbody').innerHTML = '<tr><td colspan="6" class="text-danger" style="text-align:center;">Failed to load data: ' + e.message + '</td></tr>';
        });

    function confirmDelete(id) {
        showConfirm(
            'Delete Package?',
            'This action cannot be undone.',
            () => deletePackage(id),
            'Yes, delete it!',
            'btn-danger'
        );
    }

    async function deletePackage(id) {
        try {
            const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
            const res = await fetch(`/public/api/master/packages/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (res.ok) {
                showToast('Package deleted', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                showToast('Failed to delete package', 'error');
            }
        } catch (e) {
            console.error(e);
            showToast('Error deleting package', 'error');
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>