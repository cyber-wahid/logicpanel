<?php
$page_title = 'Domains';
$current_page = 'domains';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="card-title">Domain Management</div>
        <div class="d-flex gap-2">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search domains..."
                style="width: 200px;">
            <a href="create" class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Add Domain</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="domainTable">
                <thead>
                    <tr>
                        <th>Domain Name</th>
                        <th>User</th>
                        <th>Container ID</th>
                        <th>Type</th>
                        <th>Path</th>
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

    function fetchDomains(query = '') {
        const url = '/public/api/master/domains' + (query ? '?q=' + encodeURIComponent(query) : '');
        fetch(url, { headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content) } })
            .then(async r => {
                if (!r.ok) {
                    const text = await r.text();
                    throw new Error(`Server Error ${r.status}: ${text.substring(0, 100)}...`);
                }
                return r.json();
            })
            .then(data => {
                const tbody = document.querySelector('#domainTable tbody');
                if (data.domains && data.domains.length > 0) {
                    tbody.innerHTML = data.domains.map(d => `
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i data-lucide="globe" style="width:16px; height:16px; color:var(--primary);"></i>
                            <strong>${d.name}</strong>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-weight:normal;">
                            <i data-lucide="user" style="width:12px; height:12px; margin-right:4px;"></i>
                            ${d.user}
                        </span>
                        ${d.user_owner ? `<br><small class="text-muted" title="User created by reseller"><i data-lucide="user-check" style="width:12px;height:12px;"></i> ${d.user_owner.username}</small>` : ''}
                    </td>
                    <td class="font-mono text-xs">${d.container_id ? d.container_id.substring(0, 12) : '<span class="text-muted">-</span>'}</td>
                    <td><span class="badge ${d.type === 'primary' ? 'badge-primary' : 'badge-info'}">${d.type}</span></td>
                    <td>${d.path}</td>
                    <td>
                        <div class="d-flex gap-5">
                            <button class="btn btn-secondary btn-sm" onclick="showDomainInfo('${d.name}', '${d.user}', '${d.path}', '${d.type}', '${d.container_id || ''}')">Info</button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(${d.id})">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
                    if (window.lucide) lucide.createIcons();
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px;" class="text-muted">No domains found</td></tr>';
                }
            })
            .catch(e => {
                console.error(e);
                document.querySelector('#domainTable tbody').innerHTML = `<tr><td colspan="6" class="text-danger" style="text-align:center;">Failed to load domains: ${e.message}</td></tr>`;
            });
    }

    // Initial load
    fetchDomains();

    // Search Handler
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchDomains(e.target.value);
        }, 300);
    });

    function showDomainInfo(name, user, path, type, containerId) {
        if (window.showToast) {
            showToast(`Domain: ${name} | User: ${user} | Container: ${containerId ? containerId.substring(0, 12) : 'N/A'}`, 'info');
        } else {
            alert(`Domain: ${name}\nUser: ${user}\nContainer: ${containerId}\nPath: ${path}`);
        }
    }

    function confirmDelete(id) {
        showConfirm(
            'Delete Domain?',
            'This action cannot be undone. It will remove the domain configuration from the web server.',
            () => deleteDomain(id),
            'Yes, delete it!',
            'btn-danger'
        );
    }

    async function deleteDomain(id) {
        try {
            const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
            const res = await fetch(`/public/api/master/domains/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (res.ok) {
                if (window.showToast) showToast('Domain deleted successfully', 'success');
                setTimeout(() => fetchDomains(), 500); // Reload list instead of page
            } else {
                const data = await res.json();
                if (window.showToast) showToast(data.message || 'Failed to delete domain', 'error');
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