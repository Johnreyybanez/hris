function initThemeManager() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    // Apply theme based on local storage or system preference
    function applyTheme(isDark) {
        if (isDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
            if (themeIcon) themeIcon.className = 'fas fa-moon theme-icon';
            if (themeText) themeText.textContent = 'Dark';
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            document.body.classList.remove('dark-mode');
            if (themeIcon) themeIcon.className = 'fas fa-sun theme-icon';
            if (themeText) themeText.textContent = 'Light';
        }
        localStorage.setItem('darkMode', isDark);

        // Dispatch event for other scripts
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isDark } }));
    }

    // Check for saved theme or system preference
    const savedTheme = localStorage.getItem('darkMode');
    if (savedTheme !== null) {
        applyTheme(savedTheme === 'true');
    } else {
        // Check system preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark);
    }

    // Theme toggle click handler
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isDark = !document.body.classList.contains('dark-mode');
            applyTheme(isDark);
        });
    }

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (localStorage.getItem('darkMode') === null) {
            applyTheme(e.matches);
        }
    });

    // Fix DataTables dark mode
    function updateDataTablesTheme(isDark) {
        const tables = document.querySelectorAll('.dataTable');
        tables.forEach(table => {
            if ($.fn.DataTable.isDataTable(table)) {
                const dt = $(table).DataTable();
                if (isDark) {
                    $(table).addClass('table-dark');
                } else {
                    $(table).removeClass('table-dark');
                }
                dt.draw();
            }
        });
    }

    // Listen for theme changes to update DataTables
    window.addEventListener('themeChanged', (e) => {
        updateDataTablesTheme(e.detail.isDark);
    });
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeManager);
} else {
    initThemeManager();
}
