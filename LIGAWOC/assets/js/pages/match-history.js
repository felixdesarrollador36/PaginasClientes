document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.match-history-page');
    if (!page) {
        return;
    }

    const filterView = page.dataset.filterView || 'all';
    const tournamentFilter = document.getElementById('mhTournamentFilter');

    function toggleMatchDetail(matchId) {
        const detail = document.getElementById(`detail-${matchId}`);
        if (!detail) {
            return;
        }

        detail.classList.toggle('mh-detail-hidden');
    }

    document.querySelectorAll('[data-toggle-match-detail]').forEach(element => {
        const matchId = element.dataset.toggleMatchDetail;
        if (!matchId) {
            return;
        }

        element.addEventListener('click', () => toggleMatchDetail(matchId));
        element.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            toggleMatchDetail(matchId);
        });
    });

    if (tournamentFilter) {
        tournamentFilter.addEventListener('change', () => {
            const params = new URLSearchParams();
            params.set('view', filterView);

            if (tournamentFilter.value) {
                params.set('tournament', tournamentFilter.value);
            }

            window.location.href = `?${params.toString()}`;
        });
    }

    document.querySelectorAll('[data-confirm-delete]').forEach(button => {
        button.addEventListener('click', event => {
            const message = button.dataset.confirmDelete || 'Seguro?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});