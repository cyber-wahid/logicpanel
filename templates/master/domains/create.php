<?php
$page_title = 'Add Domain';
$current_page = 'domains';
$sidebar_type = 'master';
ob_start();
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <div class="card-header">
        <div class="card-title">Add New Domain</div>
    </div>
    <div class="card-body">
        <form id="createDomainForm">
            <div class="form-group">
                <label class="form-label">Domain Name</label>
                <input type="text" name="name" class="form-control" placeholder="example.com">
            </div>

            <div class="form-group">
                <label class="form-label">Owner (User)</label>
                <select name="user_id" id="userSelect" class="form-control">
                    <option value="">Loading users...</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                    <option value="primary">Primary Domain</option>
                    <option value="addon">Addon Domain</option>
                    <option value="alias">Parked Domain (Alias)</option>
                    <!-- <option value="subdomain">Subdomain</option> -->
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Document Root</label>
                <input type="text" name="path" class="form-control" placeholder="/public_html/example.com">
            </div>

            <div class="mt-20">
                <button type="button" class="btn btn-primary" onclick="createDomain()">
                    <i data-lucide="plus"></i> Create Domain
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

    // Load Users
    fetch('/public/api/master/accounts', { headers: { 'Authorization': 'Bearer ' + token } })
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('userSelect');
            if (data.users && data.users.length > 0) {
                select.innerHTML = data.users.map(u => `<option value="${u.id}">${u.username}</option>`).join('');
            } else {
                select.innerHTML = '<option value="">No users found</option>';
            }
        })
        .catch(e => {
            document.getElementById('userSelect').innerHTML = '<option value="">Error loading users</option>';
        });

    async function createDomain() {
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        const form = document.getElementById('createDomainForm');

        const data = {
            name: form.name.value,
            user_id: form.userSelect.value,
            type: form.type.value,
            path: form.path.value
        };

        if (!data.name || !data.user_id) {
            alert('Domain and User are required');
            return;
        }

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="lucide-loader-2 spin-anim"></i> Creating...`;
            lucide.createIcons();

            const res = await fetch('/public/api/master/domains', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (res.ok) {
                alert('Domain created successfully!');
                window.location.href = 'list';
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            alert('System Error: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            lucide.createIcons();
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>