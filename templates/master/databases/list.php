<?php
$page_title = 'Databases';
$current_page = 'databases';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="card-title">Database Management</div>
        <div class="d-flex gap-2">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search databases..."
                style="width: 200px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table" id="dbTable">
                <thead>
                    <tr>
                        <th>Database Name</th>
                        <th>User</th>
                        <th>Container ID</th>
                        <th>Type</th>
                        <th>Created At</th>
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
    let searchTimeout;

    function fetchDatabases(query = '') {
        const url = '/public/api/master/databases' + (query ? '?q=' + encodeURIComponent(query) : '');
        fetch(url, { headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content) } })
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#dbTable tbody');
                if (data.databases && data.databases.length > 0) {
                    tbody.innerHTML = data.databases.map(db => `
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i data-lucide="database" style="width:16px; height:16px; color:var(--primary);"></i>
                                <strong>${db.name || 'N/A'}</strong>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary" style="font-weight:normal;">
                                <i data-lucide="user" style="width:12px; height:12px; margin-right:4px;"></i>
                                ${db.user}
                            </span>
                            ${db.user_owner ? `<br><small class="text-muted" title="User created by reseller"><i data-lucide="user-check" style="width:12px;height:12px;"></i> ${db.user_owner.username}</small>` : ''}
                        </td>
                        <td class="font-mono text-xs">${db.container_id ? db.container_id.substring(0, 12) : '<span class="text-muted">-</span>'}</td>
                        <td><span class="badge badge-info">${db.type || 'Unknown'}</span></td>
                        <td>${db.created_at ? new Date(db.created_at).toLocaleDateString() : '-'}</td>
                        <td>
                            <div class="d-flex gap-5">
                                <button class="btn btn-secondary btn-sm" onclick="showDbInfo('${db.name}', '${db.user}', '${db.type}')">Info</button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(${db.id})">Delete</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                    if (window.lucide) lucide.createIcons();
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px;" class="text-muted">No databases found</td></tr>';
                }
            })
            .catch(e => {
                console.error(e);
                document.querySelector('#dbTable tbody').innerHTML = '<tr><td colspan="6" class="text-danger" style="text-align:center;">Failed to load databases</td></tr>';
            });
    }

    // Initial load
    fetchDatabases();

    // Search Handler
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchDatabases(e.target.value);
        }, 300);
    });

    function showDbInfo(name, user, type) {
        // Simple info display using Toast
        if (window.showToast) {
            showToast(`Database: ${name} | User: ${user} | Type: ${type}`, 'info');
        } else {
            alert(`Database: ${name}\nUser: ${user}\nType: ${type}`);
        }
    }

    function confirmDelete(id) {
        showConfirm(
            'Delete Database?',
            'This action cannot be undone. It will permanently delete the database and all its data.',
            () => deleteDatabase(id),
            'Yes, delete it!',
            'btn-danger'
        );
    }

    async function deleteDatabase(id) {
        try {
            const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
            const res = await fetch(`/public/api/master/databases/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (res.ok) {
                if (window.showToast) showToast('Database deleted successfully', 'success');
                setTimeout(() => fetchDatabases(), 500);
            } else {
                const data = await res.json();
                if (window.showToast) showToast(data.message || 'Failed to delete database', 'error');
            }
        } catch (e) {
            console.error(e);
            if (window.showToast) showToast('Error processing request', 'error');
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>