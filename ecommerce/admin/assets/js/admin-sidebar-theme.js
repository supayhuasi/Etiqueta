document.addEventListener('DOMContentLoaded', function () {
    const root = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');
    const themeToggleLabel = document.getElementById('themeToggleLabel');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
    const backdrop = document.getElementById('adminSidebarBackdrop');
    const menuSections = document.querySelectorAll('.menu-section');
    const mobileMq = window.matchMedia('(max-width: 991.98px)');

    function isMobile() {
        return mobileMq.matches;
    }

    function applySidebarState(collapsed) {
        root.setAttribute('data-admin-sidebar', collapsed ? 'collapsed' : 'expanded');
        updateToggleChrome();
    }

    function setMobileOpen(open) {
        if (open) {
            root.setAttribute('data-admin-sidebar-mobile', 'open');
            if (backdrop) {
                backdrop.hidden = false;
            }
        } else {
            root.removeAttribute('data-admin-sidebar-mobile');
            if (backdrop) {
                backdrop.hidden = true;
            }
        }
        updateToggleChrome();
    }

    function updateToggleChrome() {
        const mobile = isMobile();
        const mobileOpen = root.getAttribute('data-admin-sidebar-mobile') === 'open';
        const collapsed = root.getAttribute('data-admin-sidebar') === 'collapsed';

        if (sidebarToggleIcon) {
            if (mobile) {
                sidebarToggleIcon.className = mobileOpen ? 'bi bi-x-lg' : 'bi bi-list';
            } else {
                sidebarToggleIcon.className = collapsed ? 'bi bi-arrow-bar-right' : 'bi bi-arrow-bar-left';
            }
        }

        if (sidebarToggleBtn) {
            const label = mobile
                ? (mobileOpen ? 'Cerrar menú' : 'Abrir menú')
                : (collapsed ? 'Mostrar menú completo' : 'Colapsar menú a íconos');
            sidebarToggleBtn.setAttribute('title', label);
            sidebarToggleBtn.setAttribute('aria-label', label);
            sidebarToggleBtn.setAttribute('aria-expanded', mobile ? String(mobileOpen) : String(!collapsed));
        }
    }

    function wrapWideTables() {
        document.querySelectorAll('.main-content table.table').forEach(function (table) {
            if (table.closest('.table-responsive, .modal')) {
                return;
            }
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    }

    function syncLayoutMode() {
        if (isMobile()) {
            root.classList.add('admin-is-mobile');
            applySidebarState(false);
            setMobileOpen(false);
        } else {
            root.classList.remove('admin-is-mobile');
            setMobileOpen(false);
            applySidebarState(localStorage.getItem('admin-sidebar-collapsed') === '1');
        }
        wrapWideTables();
    }

    applySidebarState(root.getAttribute('data-admin-sidebar') === 'collapsed');
    syncLayoutMode();

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function () {
            if (isMobile()) {
                setMobileOpen(root.getAttribute('data-admin-sidebar-mobile') !== 'open');
                return;
            }
            const collapsed = root.getAttribute('data-admin-sidebar') !== 'collapsed';
            localStorage.setItem('admin-sidebar-collapsed', collapsed ? '1' : '0');
            applySidebarState(collapsed);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setMobileOpen(false);
        });
    }

    document.querySelectorAll('.sidebar a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) {
                setMobileOpen(false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && root.getAttribute('data-admin-sidebar-mobile') === 'open') {
            setMobileOpen(false);
        }
    });

    if (typeof mobileMq.addEventListener === 'function') {
        mobileMq.addEventListener('change', syncLayoutMode);
    } else if (typeof mobileMq.addListener === 'function') {
        mobileMq.addListener(syncLayoutMode);
    }

    function applyTheme(theme) {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-admin-theme', nextTheme);
        root.setAttribute('data-bs-theme', nextTheme);

        if (themeToggleIcon) {
            themeToggleIcon.className = nextTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        if (themeToggleLabel) {
            themeToggleLabel.textContent = nextTheme === 'dark' ? 'Modo claro' : 'Modo oscuro';
        }
        if (themeToggleBtn) {
            themeToggleBtn.setAttribute('aria-label', nextTheme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            themeToggleBtn.setAttribute('title', nextTheme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        }
    }

    applyTheme(root.getAttribute('data-admin-theme') || 'light');

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const currentTheme = root.getAttribute('data-admin-theme') === 'dark' ? 'dark' : 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('admin-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    menuSections.forEach(function (section) {
        const activeItem = section.querySelector('.menu-items a.active');
        if (activeItem) {
            section.classList.add('has-active');
            const collapseEl = section.querySelector('.collapse.menu-items');
            if (collapseEl) {
                collapseEl.classList.add('show');
            }
            const headerEl = section.querySelector('.menu-header[data-bs-toggle="collapse"]');
            if (headerEl) {
                headerEl.classList.remove('collapsed');
                headerEl.setAttribute('aria-expanded', 'true');
            }
        }
    });
});
