document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-category-color]').forEach(element => {
        const color = element.getAttribute('data-category-color');
        if (!color) {
            return;
        }

        element.style.setProperty('--category-color', color);
    });
});
