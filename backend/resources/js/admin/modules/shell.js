/**
 * Sidebar collapse (desktop), off-canvas drawer (mobile), nav groups and dropdown menus.
 */
const STORAGE_KEY = 'admin.sidebar';

function readPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function writePreference(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Storage can be unavailable (private mode); the toggle still works for this page.
    }
}

export function initShell() {
    const root = document.documentElement;
    const drawer = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');

    if (readPreference() === 'collapsed') {
        root.dataset.sidebar = 'collapsed';
    }

    document.querySelectorAll('[data-sidebar-collapse]').forEach((button) => {
        button.addEventListener('click', () => {
            const collapsed = root.dataset.sidebar === 'collapsed';
            root.dataset.sidebar = collapsed ? 'expanded' : 'collapsed';
            button.setAttribute('aria-expanded', String(collapsed));
            writePreference(root.dataset.sidebar);
        });
    });

    const setDrawer = (open) => {
        drawer?.classList.toggle('-translate-x-full', !open);
        backdrop?.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
    };

    document.querySelectorAll('[data-drawer-open]').forEach((button) => button.addEventListener('click', () => setDrawer(true)));
    document.querySelectorAll('[data-drawer-close]').forEach((button) => button.addEventListener('click', () => setDrawer(false)));
    backdrop?.addEventListener('click', () => setDrawer(false));

    document.querySelectorAll('[data-nav-group]').forEach((button) => {
        button.addEventListener('click', () => {
            const list = document.getElementById(button.getAttribute('aria-controls'));
            const open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(open));
            list?.classList.toggle('hidden', !open);
        });
    });

    document.querySelectorAll('[data-dropdown]').forEach((trigger) => {
        const menu = document.getElementById(trigger.getAttribute('aria-controls'));

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = trigger.getAttribute('aria-expanded') !== 'true';
            trigger.setAttribute('aria-expanded', String(open));
            menu?.classList.toggle('hidden', !open);
        });

        document.addEventListener('click', (event) => {
            if (menu && !menu.contains(event.target)) {
                trigger.setAttribute('aria-expanded', 'false');
                menu.classList.add('hidden');
            }
        });
    });
}
