document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.rankings-page');
    if (!page) {
        return;
    }

    const baseUrl = page.dataset.rankingsBaseUrl || '';
    const seasonFilter = document.getElementById('filterSeason');
    const tournamentFilter = document.getElementById('filterTournament');

    function applyFilters() {
        if (!baseUrl) {
            return;
        }

        const params = new URLSearchParams();
        if (seasonFilter?.value) {
            params.set('season', seasonFilter.value);
        }
        if (tournamentFilter?.value) {
            params.set('tournament', tournamentFilter.value);
        }

        const query = params.toString();
        window.location.href = query ? `${baseUrl}?${query}` : baseUrl;
    }

    function showRankTab(name) {
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelectorAll('[data-rank-tab]').forEach(tab => tab.classList.remove('active'));

        const targetContent = document.getElementById(`tab-${name}`);
        const targetTab = document.querySelector(`[data-rank-tab="${name}"]`);

        targetContent?.classList.add('active');
        targetTab?.classList.add('active');
    }

    document.querySelectorAll('[data-ranking-filter]').forEach(filter => {
        filter.addEventListener('change', applyFilters);
    });

    document.querySelectorAll('[data-rank-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.rankTab || 'teams';
            showRankTab(tabName);
        });
    });
});