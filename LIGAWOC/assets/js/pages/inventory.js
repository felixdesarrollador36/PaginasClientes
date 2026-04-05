document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.inventory-page');
    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrfToken || '';
    const equipBase = page.dataset.equipBase || '';
    const unequipBase = page.dataset.unequipBase || '';

    function navigateToggle(itemId, isEquipped) {
        if (!itemId) {
            return;
        }

        const base = isEquipped ? unequipBase : equipBase;
        if (!base) {
            return;
        }

        const targetUrl = `${base}${itemId}?csrf_token=${encodeURIComponent(csrfToken)}`;
        window.location.href = targetUrl;
    }

    function getItemData(element) {
        const itemId = element.dataset.itemId || '';
        const isEquipped = Number(element.dataset.isEquipped || 0) === 1;
        return { itemId, isEquipped };
    }

    page.addEventListener('click', event => {
        const trigger = event.target.closest('[data-toggle-equip]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        const { itemId, isEquipped } = getItemData(trigger);
        navigateToggle(itemId, isEquipped);
    });

    page.addEventListener('keydown', event => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const trigger = event.target.closest('[data-toggle-equip]');
        if (!trigger || trigger.tagName === 'BUTTON') {
            return;
        }

        event.preventDefault();
        const { itemId, isEquipped } = getItemData(trigger);
        navigateToggle(itemId, isEquipped);
    });
});