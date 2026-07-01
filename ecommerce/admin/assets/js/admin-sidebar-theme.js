document.addEventListener('DOMContentLoaded', function () {
    const root = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');
    const themeToggleLabel = document.getElementById('themeToggleLabel');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
    const menuSections = document.querySelectorAll('.menu-section');

    function applySidebarState(collapsed) {
        root.setAttribute('data-admin-sidebar', collapsed ? 'collapsed' : 'expanded');
        if (sidebarToggleIcon) {
            sidebarToggleIcon.className = collapsed ? 'bi bi-arrow-bar-right' : 'bi bi-arrow-bar-left';
        }
        if (sidebarToggleBtn) {
            const label = collapsed ? 'Mostrar menú completo' : 'Colapsar menú a íconos';
            sidebarToggleBtn.setAttribute('title', label);
            sidebarToggleBtn.setAttribute('aria-label', label);
        }
    }

    applySidebarState(root.getAttribute('data-admin-sidebar') === 'collapsed');

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function () {
            const collapsed = root.getAttribute('data-admin-sidebar') !== 'collapsed';
            localStorage.setItem('admin-sidebar-collapsed', collapsed ? '1' : '0');
            applySidebarState(collapsed);
        });
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
