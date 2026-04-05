document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.notifications-page');
    const markAllButton = document.querySelector('[data-mark-all-read]');

    if (!page || !markAllButton) {
        return;
    }

    const endpoint = page.dataset.markAllReadUrl || '';
    const csrfToken = page.dataset.csrfToken || '';

    markAllButton.addEventListener('click', async () => {
        if (!endpoint) {
            return;
        }

        markAllButton.disabled = true;
        try {
            await fetch(endpoint, {
                method: 'POST',
                headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
            });
            window.location.reload();
        } catch {
            markAllButton.disabled = false;
            window.alert('No se pudieron marcar las notificaciones.');
        }
    });
});