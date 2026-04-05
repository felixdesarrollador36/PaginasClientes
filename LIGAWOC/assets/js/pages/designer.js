document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.designer-item-form');
    if (!form) {
        return;
    }

    const categorySelect = form.querySelector('[name="category_id"]');
    const imageInput = form.querySelector('[name="image"]');
    const portadaHint = form.querySelector('[data-portada-hint]');

    if (!categorySelect || !imageInput) {
        return;
    }

    const REQUIRED_WIDTH = 1920;
    const REQUIRED_HEIGHT = 500;

    function normalize(value) {
        return String(value || '').toLowerCase();
    }

    function notifyError(message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'error');
            return;
        }

        window.alert(message);
    }

    function getSelectedCategoryOption() {
        return categorySelect.options[categorySelect.selectedIndex] || null;
    }

    function isPortadaCategorySelected() {
        const option = getSelectedCategoryOption();
        if (!option) {
            return false;
        }

        const name = normalize(option.dataset.categoryName || option.textContent);
        const slug = normalize(option.dataset.categorySlug);
        const type = normalize(option.dataset.categoryType);

        return name.includes('portada') || slug.includes('portada') || type.includes('portada');
    }

    function updatePortadaHint() {
        if (!portadaHint) {
            return;
        }

        portadaHint.hidden = !isPortadaCategorySelected();
    }

    function readImageSize(file) {
        return new Promise((resolve, reject) => {
            const tempUrl = URL.createObjectURL(file);
            const image = new Image();

            image.onload = () => {
                const width = image.naturalWidth;
                const height = image.naturalHeight;
                URL.revokeObjectURL(tempUrl);
                resolve({ width, height });
            };

            image.onerror = () => {
                URL.revokeObjectURL(tempUrl);
                reject(new Error('No se pudo leer la imagen seleccionada.'));
            };

            image.src = tempUrl;
        });
    }

    categorySelect.addEventListener('change', () => {
        imageInput.value = '';
        updatePortadaHint();
    });

    imageInput.addEventListener('change', async () => {
        const file = imageInput.files && imageInput.files[0];
        if (!file) {
            return;
        }

        if (!isPortadaCategorySelected()) {
            return;
        }

        try {
            const imageSize = await readImageSize(file);
            if (imageSize.width !== REQUIRED_WIDTH || imageSize.height !== REQUIRED_HEIGHT) {
                notifyError(`Para categoria Portada, la imagen debe medir exactamente ${REQUIRED_WIDTH} x ${REQUIRED_HEIGHT} px. Imagen seleccionada: ${imageSize.width} x ${imageSize.height} px.`);
                imageInput.value = '';
            }
        } catch (_error) {
            notifyError('No se pudo validar la portada seleccionada.');
            imageInput.value = '';
        }
    });

    updatePortadaHint();
});
