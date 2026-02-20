<!-- cPanel Style Header -->
<header class="header">
    <div class="header-left">
        <button class="mobile-menu-btn header-btn" onclick="toggleSidebar()">
            <i data-lucide="menu"></i>
        </button>
        <!-- Desktop: Page Title, Mobile: LogicPanel brand -->
        <h1 class="header-title desktop-only"><?= htmlspecialchars($page_title ?? 'Tools') ?></h1>
        <a href="<?= $base_url ?? '' ?>/" class="header-brand mobile-only">LogicPanel</a>
    </div>

    <div class="header-center">
        <div class="header-search">
            <i data-lucide="search"></i>
            <input type="text" placeholder="Search Tools (/)" id="toolSearch" onkeyup="filterTools()">
        </div>
    </div>

    <div class="header-actions">
        <button class="header-btn" onclick="toggleTheme()" title="Toggle Theme">
            <i data-lucide="sun" class="theme-light-icon"></i>
            <i data-lucide="moon" class="theme-dark-icon"></i>
        </button>
        <a href="<?= $base_url ?? '' ?>/settings/profile" class="header-btn" title="Settings">
            <i data-lucide="settings"></i>
        </a>
    </div>
</header>

<style>
    .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header-brand {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        text-decoration: none;
    }

    .header-brand:hover {
        text-decoration: none;
        color: var(--primary);
    }

    .header-center {
        flex: 1;
        max-width: 400px;
        margin: 0 20px;
    }

    .header-search {
        position: relative;
        display: flex;
        align-items: center;
    }

    .header-search svg {
        position: absolute;
        left: 12px;
        width: 16px;
        height: 16px;
        color: var(--text-muted);
        pointer-events: none;
    }

    .header-search input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        font-size: 13px;
        color: var(--text-primary);
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.15s ease;
    }

    .header-search input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(60, 135, 58, 0.1);
    }

    .header-search input::placeholder {
        color: var(--text-muted);
    }

    /* Theme icons visibility */
    [data-theme="light"] .theme-dark-icon {
        display: none;
    }

    [data-theme="dark"] .theme-light-icon {
        display: none;
    }

    /* Mobile/Desktop visibility */
    .mobile-only {
        display: none;
    }

    .desktop-only {
        display: block;
    }

    @media (max-width: 991px) {
        .mobile-only {
            display: block;
        }

        .desktop-only {
            display: none;
        }

        .header-center {
            display: none;
        }

        .header-title {
            font-size: 14px;
        }
    }
</style>

<script>
    function filterTools() {
        const query = document.getElementById('toolSearch').value.toLowerCase();
        const toolItems = document.querySelectorAll('.tool-item');

        toolItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    }
</script>