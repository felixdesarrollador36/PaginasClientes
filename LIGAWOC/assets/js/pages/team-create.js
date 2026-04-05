document.addEventListener('DOMContentLoaded', () => {
    const roleInput = document.getElementById('roleInput');
    const rankInput = document.getElementById('rankInput');
    const heroInput = document.getElementById('heroInput');
    const heroModal = document.getElementById('heroModal');
    const heroSearch = document.getElementById('heroSearch');
    const heroSelectedDisplay = document.getElementById('heroSelectedDisplay');
    const heroSelectedImg = document.getElementById('heroSelectedImg');
    const heroSelectedName = document.getElementById('heroSelectedName');
    const heroPickerBtn = document.getElementById('heroPickerBtn');

    let currentHeroRole = 'all';

    function activateCard(elements, activeElement) {
        elements.forEach(element => element.classList.remove('selected'));
        activeElement?.classList.add('selected');
    }

    function handleKeyboardActivation(selector, callback) {
        document.querySelectorAll(selector).forEach(element => {
            element.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                callback(element);
            });
        });
    }

    function selectRole(card) {
        if (!roleInput || !card) {
            return;
        }

        roleInput.value = card.dataset.role || '';
        activateCard(document.querySelectorAll('.role-card'), card);
    }

    function selectRank(card) {
        if (!rankInput || !card) {
            return;
        }

        rankInput.value = card.dataset.rank || '';
        activateCard(document.querySelectorAll('.rank-card'), card);
    }

    function selectLane(card) {
        const laneGroup = card?.dataset.laneGroup;
        const laneValue = card?.dataset.lane ?? '';
        const input = document.getElementById(`lane${laneGroup}Input`);
        const grid = document.getElementById(`lane${laneGroup}Grid`);

        if (!laneGroup || !input || !grid || !card) {
            return;
        }

        input.value = laneValue;
        activateCard(grid.querySelectorAll('.lane-card'), card);
    }

    function openHeroPicker() {
        if (!heroModal) {
            return;
        }

        heroModal.classList.add('active');
        document.body.classList.add('has-modal-open');
        window.setTimeout(() => heroSearch?.focus(), 100);
    }

    function closeHeroPicker() {
        heroModal?.classList.remove('active');
        document.body.classList.remove('has-modal-open');
    }

    function pickHero(card) {
        if (!card || !heroInput || !heroSelectedImg || !heroSelectedName || !heroSelectedDisplay || !heroPickerBtn) {
            return;
        }

        heroInput.value = card.dataset.heroName || '';
        heroSelectedImg.src = card.dataset.heroImg || '';
        heroSelectedName.textContent = card.dataset.heroName || '';
        heroSelectedDisplay.classList.remove('is-hidden');
        heroPickerBtn.classList.add('is-hidden');
        activateCard(document.querySelectorAll('.hero-pick-card'), card);
        closeHeroPicker();
    }

    function applyHeroFilters() {
        const query = heroSearch?.value.toLowerCase() || '';
        document.querySelectorAll('.hero-pick-card').forEach(card => {
            const matchesRole = currentHeroRole === 'all' || card.dataset.role === currentHeroRole;
            const matchesQuery = (card.dataset.name || '').includes(query);
            card.hidden = !(matchesRole && matchesQuery);
        });
    }

    function setRoleFilter(button) {
        currentHeroRole = button?.dataset.filterRole || 'all';
        document.querySelectorAll('.hero-tab').forEach(tab => tab.classList.remove('active'));
        button?.classList.add('active');
        applyHeroFilters();
    }

    document.querySelectorAll('[data-select-role]').forEach(card => {
        card.addEventListener('click', () => selectRole(card));
    });
    handleKeyboardActivation('[data-select-role]', selectRole);

    document.querySelectorAll('[data-select-rank]').forEach(card => {
        card.addEventListener('click', () => selectRank(card));
    });
    handleKeyboardActivation('[data-select-rank]', selectRank);

    document.querySelectorAll('[data-select-lane]').forEach(card => {
        card.addEventListener('click', () => selectLane(card));
    });
    handleKeyboardActivation('[data-select-lane]', selectLane);

    document.querySelectorAll('[data-open-hero-picker]').forEach(button => {
        button.addEventListener('click', openHeroPicker);
    });

    document.querySelectorAll('[data-close-hero-picker]').forEach(button => {
        button.addEventListener('click', closeHeroPicker);
    });

    if (heroModal) {
        heroModal.addEventListener('click', event => {
            if (event.target === heroModal) {
                closeHeroPicker();
            }
        });
    }

    if (heroSearch) {
        heroSearch.addEventListener('input', applyHeroFilters);
        heroSearch.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeHeroPicker();
            }
        });
    }

    document.querySelectorAll('[data-filter-role]').forEach(button => {
        button.addEventListener('click', () => setRoleFilter(button));
    });

    document.querySelectorAll('.hero-pick-card').forEach(card => {
        const image = card.querySelector('img[data-fallback-src]');
        if (image) {
            image.addEventListener('error', () => {
                image.src = image.dataset.fallbackSrc || image.src;
            }, { once: true });
        }

        card.addEventListener('click', () => pickHero(card));
    });
    handleKeyboardActivation('.hero-pick-card', pickHero);
});