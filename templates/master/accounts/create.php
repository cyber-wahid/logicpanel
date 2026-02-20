<?php
$page_title = 'Create Account';
$current_page = 'accounts_create';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Create a New Account</div>
    </div>
    <div class="card-body">
        <form id="createAccountForm">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="user">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control">
                </div>
            </div>
            <style>
                .form-row {
                    display: flex;
                    gap: 10px;
                }

                .form-row .form-group {
                    flex: 1;
                }

                @media (max-width: 768px) {
                    .form-row {
                        flex-direction: column;
                        gap: 0;
                    }
                }
            </style>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com">
            </div>

            <div class="form-group">
                <label class="form-label">Account Type</label>
                <select name="role" class="form-control" id="roleSelect" onchange="updatePackageFilter()">
                    <option value="user">User Account</option>
                    <?php if (($_SESSION['user_role'] ?? '') !== 'reseller'): ?>
                    <option value="reseller">Reseller Account</option>
                    <?php endif; ?>
                </select>
                <small class="text-muted">
                    <?php if (($_SESSION['user_role'] ?? '') === 'reseller'): ?>
                        You can only create user accounts
                    <?php else: ?>
                        Select whether this is a regular user or a reseller account
                    <?php endif; ?>
                </small>
            </div>

            <div class="form-group"
                style="border-top:1px solid var(--border-color); padding-top:20px; margin-top:20px;">
                <label class="form-label">Choose a Package</label>
                <select name="package" class="form-control" id="packageSelect">
                    <option value="" disabled selected>Loading packages...</option>
                </select>
            </div>


            <div class="mt-20">
                <button type="button" class="btn btn-primary" onclick="createAccount()">
                    <i data-lucide="check"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    async function createAccount() {
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;

        // Gather data
        const form = document.getElementById('createAccountForm');
        const roleSelect = document.getElementById('roleSelect');
        const data = {
            username: form.username.value,
            password: form.password.value,
            email: form.email.value,
            package_name: form.package.value,
            role: roleSelect ? roleSelect.value : 'user'
        };

        if (!data.username || !data.password || !data.email) {
            showNotification('Please fill in all required fields', 'error');
            return;
        }

        try {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner" style="vertical-align:middle;margin-right:8px"></span> Creating...`;
            if (window.lucide) lucide.createIcons();

            // Use token from global scope
            const token = window.apiToken || sessionStorage.getItem('token');
            if (!token) {
                showNotification('Authentication Error: Please login again', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            const res = await fetch('/public/api/master/accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (res.ok) {
                showNotification('Account created successfully!', 'success');
                setTimeout(() => window.location.href = 'list', 1500);
            } else {
                showNotification('Error: ' + (result.message || result.error || 'Unknown error'), 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('System Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (window.lucide) lucide.createIcons();
        }
    }

    // Store all packages globally
    let allPackages = [];

    // Update package filter based on role selection
    function updatePackageFilter() {
        const roleSelect = document.getElementById('roleSelect');
        const packageSelect = document.getElementById('packageSelect');
        const selectedRole = roleSelect.value;

        packageSelect.innerHTML = '';

        const filteredPackages = allPackages.filter(pkg => {
            if (selectedRole === 'reseller') {
                return pkg.type === 'reseller';
            } else {
                return pkg.type === 'user';
            }
        });

        if (filteredPackages.length > 0) {
            filteredPackages.forEach(pkg => {
                const option = document.createElement('option');
                option.value = pkg.name;
                option.textContent = `${pkg.name} (${pkg.storage_limit === '0' || pkg.storage_limit === 0 ? 'Unlimited' : pkg.storage_limit + 'MB'} Disk)`;
                packageSelect.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.text = `No ${selectedRole} packages found`;
            option.disabled = true;
            option.selected = true;
            packageSelect.appendChild(option);
        }
    }

    // Fetch Packages on Load
    (async function fetchPackages() {
        const select = document.getElementById('packageSelect');
        try {
            const token = window.apiToken || sessionStorage.getItem('token');
            const res = await fetch('/public/api/master/packages', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (data.packages && data.packages.length > 0) {
                allPackages = data.packages;
                updatePackageFilter(); // Load initial packages based on default role (user)
            } else {
                select.innerHTML = '<option disabled>No packages found</option>';
            }
        } catch (e) {
            console.error(e);
            select.innerHTML = '<option disabled>Failed to load packages</option>';
        }
    })();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>