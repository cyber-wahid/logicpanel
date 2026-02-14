<?php
$page_title = 'My Profile';
$current_page = 'profile';
$sidebar_type = 'master';
ob_start();
?>

<div class="card mb-20">
    <div class="card-header">
        <div class="card-title">
            <i data-lucide="user"></i> Profile Information
        </div>
    </div>
    <div class="card-body">
        <div class="form-group mb-15">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>"
                disabled>
            <small class="text-muted">Username cannot be changed.</small>
        </div>
        <div class="form-group mb-15">
            <label class="form-label">Email Address</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>"
                disabled>
            <small class="text-muted">Primary administrator email.</small>
        </div>
        <div class="form-group">
            <label class="form-label">Role</label>
            <input type="text" class="form-control"
                value="<?= ucfirst(htmlspecialchars($_SESSION['user_role'] ?? 'admin')) ?>" disabled>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i data-lucide="lock"></i> Change Password
        </div>
    </div>
    <div class="card-body">
        <form id="passwordForm" onsubmit="event.preventDefault(); updatePassword();">
            <div class="form-group mb-15">
                <label class="form-label">Current Password</label>
                <input type="password" id="currentPassword" class="form-control" required
                    placeholder="Enter current password">
            </div>

            <div class="form-row mb-15">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" id="newPassword" class="form-control" required minlength="8"
                        placeholder="At least 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" id="confirmPassword" class="form-control" required minlength="8"
                        placeholder="Repeat new password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i> Update Password
            </button>
        </form>
    </div>
</div>

<style>
    .form-row {
        display: flex;
        gap: 15px;
    }

    .form-row .form-group {
        flex: 1;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>

<script>
    async function updatePassword() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const btn = document.querySelector('#passwordForm button[type="submit"]');

        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'error');
            return;
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spin-anim" style="display:inline-block; border:2px solid #fff; border-top-color:transparent; border-radius:50%; width:14px; height:14px; margin-right:5px; vertical-align:middle;"></span> Updating...';
        btn.disabled = true;

        try {
            const token = window.apiToken || sessionStorage.getItem('token');
            const res = await fetch('/public/api/auth/password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    new_password_confirmation: confirmPassword
                })
            });

            const data = await res.json();

            if (res.ok) {
                showNotification('Password updated successfully', 'success');
                document.getElementById('passwordForm').reset();
            } else {
                showNotification(data.message || data.error || 'Failed to update password', 'error');
            }
        } catch (e) {
            showNotification('Network error occurred', 'error');
            console.error(e);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (window.lucide) lucide.createIcons();
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>