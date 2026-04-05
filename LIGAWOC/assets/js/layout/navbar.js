document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('topnavSearchForm');
    const searchInput = document.getElementById('topnavSearchInput');
    const searchToggle = document.getElementById('searchToggleBtn');
    const mobileSearchBtn = document.getElementById('mobile-search-btn');
    const searchWrap = document.getElementById('searchWrap');
    const topnavImmersive = searchWrap?.closest('.topnav-immersive') ?? null;
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileMenuPanel = document.getElementById('mobile-menu-panel');
    const mobileMenuClose = document.getElementById('mobile-menu-close');
    const topnavCaretLinks = Array.from(document.querySelectorAll('.topnav-dropdown-container > .topnav-link.has-caret'));

    function toggleSearch(forceOpen) {
        if (!searchForm || !searchInput) {
            return;
        }

        const nextState = typeof forceOpen === 'boolean' ? forceOpen : !searchForm.classList.contains('is-open');
        searchForm.classList.toggle('is-open', nextState);
        searchInput.classList.toggle('expanded', nextState);
        searchWrap?.classList.toggle('is-open', nextState);
        topnavImmersive?.classList.toggle('search-active', nextState);

        if (nextState) {
            requestAnimationFrame(() => searchInput.focus());
            return;
        }

        searchInput.value = '';
    }

    function toggleMobileMenu(forceOpen) {
        if (!mobileMenuPanel || !mobileMenuToggle) {
            return;
        }

        const nextState = typeof forceOpen === 'boolean' ? forceOpen : mobileMenuPanel.hasAttribute('hidden');
        mobileMenuPanel.toggleAttribute('hidden', !nextState);
        mobileMenuToggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');
        document.body.classList.toggle('mobile-menu-open', nextState);
    }

    if (searchToggle) {
        searchToggle.addEventListener('click', () => toggleSearch());
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    window.location.href = window.BASE_URL + 'search?q=' + encodeURIComponent(query);
                }
            }

            if (event.key === 'Escape') {
                toggleSearch(false);
            }
        });
    }

    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', () => {
            window.location.href = window.BASE_URL + 'search';
        });
    }

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', () => {
            toggleMobileMenu();
        });
    }

    if (mobileMenuClose) {
        mobileMenuClose.addEventListener('click', () => {
            toggleMobileMenu(false);
        });
    }

    if (mobileMenuPanel) {
        mobileMenuPanel.addEventListener('click', event => {
            if (event.target === mobileMenuPanel) {
                toggleMobileMenu(false);
            }
        });

        mobileMenuPanel.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                toggleMobileMenu(false);
            });
        });
    }

    function closeTopnavDropdowns(exceptContainer = null) {
        topnavCaretLinks.forEach(link => {
            const container = link.closest('.topnav-dropdown-container');
            if (!container || container === exceptContainer) {
                return;
            }
            container.classList.remove('is-open');
            link.setAttribute('aria-expanded', 'false');
        });
    }

    topnavCaretLinks.forEach(link => {
        const container = link.closest('.topnav-dropdown-container');
        if (!container) {
            return;
        }

        link.setAttribute('aria-expanded', 'false');
        link.addEventListener('click', event => {
            event.preventDefault();
            const willOpen = !container.classList.contains('is-open');
            closeTopnavDropdowns(container);
            container.classList.toggle('is-open', willOpen);
            link.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) {
            return;
        }
        if (event.target.closest('.topnav-dropdown-container')) {
            return;
        }
        closeTopnavDropdowns();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            toggleSearch(false);
            toggleMobileMenu(false);
            closeTopnavDropdowns();
        }
    });

    document.querySelectorAll('[data-dropdown-toggle]').forEach(toggle => {
        toggle.addEventListener('click', () => {
            toggle.nextElementSibling?.classList.toggle('show');
        });
    });
});