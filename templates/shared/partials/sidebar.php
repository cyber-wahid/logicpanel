<!-- LogicPanel Sidebar - cPanel Style -->
<aside class="lp-sidebar">
    <a href="<?= $base_url ?? '' ?>/" class="lp-sidebar-brand">
        <img src="<?= $base_url ?? '' ?>/public/assets/logo.svg" alt="LogicPanel" class="lp-brand-logo">
    </a>

    <nav class="lp-sidebar-nav">
        <a href="<?= $base_url ?? '' ?>/"
            class="lp-nav-item <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-grid"></i>
            <span>Tools</span>
        </a>

        <a href="<?= $base_url ?? '' ?>/apps/files" target="_blank"
            class="lp-nav-item <?= ($current_page ?? '') === 'apps_files' ? 'active' : '' ?>">
            <i data-lucide="folder"></i>
            <span>File Manager</span>
        </a>

        <a href="<?= $base_url ?? '' ?>/apps/terminal"
            class="lp-nav-item <?= ($current_page ?? '') === 'terminal' ? 'active' : '' ?>">
            <i data-lucide="terminal"></i>
            <span>Terminal</span>
        </a>

        <a href="<?= $base_url ?? '' ?>/domains"
            class="lp-nav-item <?= ($current_page ?? '') === 'domains' ? 'active' : '' ?>">
            <i data-lucide="globe"></i>
            <span>Addon Domains</span>
        </a>

        <a href="<?= $base_url ?? '' ?>/backups"
            class="lp-nav-item <?= ($current_page ?? '') === 'backups' ? 'active' : '' ?>">
            <i data-lucide="archive"></i>
            <span>Backup Wizard</span>
        </a>

        <a href="<?= $base_url ?? '' ?>/cron"
            class="lp-nav-item <?= ($current_page ?? '') === 'cron' ? 'active' : '' ?>">
            <i data-lucide="clock"></i>
            <span>Cron Jobs</span>
        </a>

        <?php if (isset($service_id)): ?>
            <!-- Active Service Context -->
            <div class="lp-nav-section">Service Management</div>
            <a href="<?= $base_url ?? '' ?>/services/<?= $service_id ?>"
                class="lp-nav-item <?= ($current_page ?? '') === 'service_show' ? 'active' : '' ?>">
                <i data-lucide="monitor"></i>
                <span>Overview</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/files/<?= $service_id ?>/manager"
                class="lp-nav-item <?= ($current_page ?? '') === 'files' ? 'active' : '' ?>">
                <i data-lucide="folder"></i>
                <span>File Manager</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/databases/<?= $service_id ?>"
                class="lp-nav-item <?= ($current_page ?? '') === 'databases' ? 'active' : '' ?>">
                <i data-lucide="database"></i>
                <span>Databases</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- User Section -->
    <div class="lp-sidebar-user">
        <a href="<?= $base_url ?? '' ?>/settings/profile"
            class="lp-user-profile <?= ($current_page ?? '') === 'profile' ? 'active' : '' ?>" title="Account Settings">
            <div class="lp-user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="lp-user-info">
                <div class="lp-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                <div class="lp-user-role"><?= ucfirst(htmlspecialchars($_SESSION['user_role'] ?? 'user')) ?></div>
            </div>
        </a>
        <a href="<?= $base_url ?? '' ?>/logout" class="lp-logout" title="Logout">
            <i data-lucide="log-out"></i>
        </a>
    </div>
</aside>

<style>
    /* ========================================
   LogicPanel Sidebar - Clean Rebuild
   ======================================== */
    .lp-sidebar {
        width: 200px;
        background: #1E2127;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    [data-theme="dark"] .lp-sidebar {
        border-right: 1px solid rgba(60, 135, 58, 0.3);
    }

    /* Brand */
    .lp-sidebar-brand {
        padding: 14px 18px;
        display: flex;
        align-items: center;
        text-decoration: none;
    }

    .lp-brand-logo {
        height: 32px;
        width: auto;
    }

    /* Navigation */
    .lp-sidebar-nav {
        flex: 1;
        padding: 12px 0;
        overflow-y: auto;
    }

    .lp-nav-section {
        padding: 16px 18px 8px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6B7280;
    }

    .lp-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 18px;
        color: #9CA3AF;
        font-size: 13px;
        text-decoration: none !important;
        transition: all 0.15s ease;
        border-left: 3px solid transparent;
        margin: 2px 0;
    }

    .lp-nav-item svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .lp-nav-item:hover {
        color: #E5E7EB;
        background: rgba(255, 255, 255, 0.05);
        text-decoration: none !important;
    }

    .lp-nav-item.active {
        color: #fff;
        background: rgba(60, 135, 58, 0.2);
        border-left-color: #3C873A;
    }

    .lp-nav-item.active svg {
        color: #3C873A;
    }

    /* User Section */
    .lp-sidebar-user {
        padding: 14px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0, 0, 0, 0.1);
    }

    .lp-user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        text-decoration: none;
        padding: 4px;
        margin: -4px;
        border-radius: 6px;
        transition: background 0.15s ease;
    }

    .lp-user-profile:hover,
    .lp-user-profile.active {
        background: rgba(255, 255, 255, 0.05);
    }

    .lp-user-avatar {
        width: 34px;
        height: 34px;
        background: linear-gradient(135deg, #3C873A 0%, #2D6A2E 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 600;
        font-size: 13px;
        flex-shrink: 0;
    }

    .lp-user-info {
        flex: 1;
        min-width: 0;
    }

    .lp-user-name {
        color: #E5E7EB;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lp-user-role {
        color: #6B7280;
        font-size: 10px;
    }

    .lp-logout {
        color: #6B7280;
        padding: 6px;
        border-radius: 6px;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lp-logout:hover {
        color: #EF4444;
        background: rgba(239, 68, 68, 0.1);
    }

    .lp-logout svg {
        width: 16px;
        height: 16px;
    }

    /* Update main content margin for new sidebar width */
    .main-content {
        margin-left: 200px !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .lp-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .lp-sidebar.open {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0 !important;
        }
    }
</style>