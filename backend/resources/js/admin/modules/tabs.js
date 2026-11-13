/**
 * Accessible tabs: <div data-tabs> with [role=tab][aria-controls] buttons and [role=tabpanel] panels.
 * The active tab is kept in the URL hash (main editor tabs only) so validation redirects reopen it.
 */
export function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((container) => {
        const tabs = [...container.querySelectorAll(':scope [role="tab"]')].filter((tab) => tab.closest('[data-tabs]') === container);
        const useHash = container.hasAttribute('data-tabs-hash');

        const activate = (tab, updateHash = false) => {
            tabs.forEach((other) => {
                const selected = other === tab;
                other.setAttribute('aria-selected', String(selected));
                other.tabIndex = selected ? 0 : -1;
                document.getElementById(other.getAttribute('aria-controls'))?.toggleAttribute('hidden', !selected);
            });

            if (useHash) {
                document.querySelectorAll('input[name="_tab"]').forEach((input) => {
                    input.value = tab.getAttribute('aria-controls');
                });

                if (updateHash) {
                    history.replaceState(null, '', `#${tab.getAttribute('aria-controls')}`);
                }
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab, true));
            tab.addEventListener('keydown', (event) => {
                const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

                if (step) {
                    const next = tabs[(index + step + tabs.length) % tabs.length];
                    next.focus();
                    activate(next, true);
                }
            });
        });

        const fromHash = useHash && location.hash ? tabs.find((tab) => `#${tab.getAttribute('aria-controls')}` === location.hash) : null;
        const withError = tabs.find((tab) => document.getElementById(tab.getAttribute('aria-controls'))?.querySelector('.form-error, .is-invalid'));
        activate(withError ?? fromHash ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0]);

        if (fromHash) {
            requestAnimationFrame(() => window.scrollTo({ top: 0 }));
        }
    });
}
