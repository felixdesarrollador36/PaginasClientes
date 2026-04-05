document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.shop-page');
    const modal = document.getElementById('itemModal');
    const modalContent = document.getElementById('itemModalContent');

    if (!page || !modal || !modalContent) {
        return;
    }

    const apiBase = page.dataset.itemApiBase || '';

    function closeModal() {
        modal.classList.remove('active');
        document.body.classList.remove('has-modal-open');
    }

    function openModal(itemId) {
        if (!apiBase || !itemId) {
            return;
        }

        modal.classList.add('active');
        document.body.classList.add('has-modal-open');
        modalContent.textContent = 'Cargando...';

        fetch(`${apiBase}${itemId}`)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
            })
            .catch(() => {
                modalContent.textContent = 'No se pudo cargar el item.';
            });
    }

    document.querySelectorAll('[data-open-item-modal]').forEach(card => {
        const activate = () => openModal(card.dataset.openItemModal || '');
        card.addEventListener('click', activate);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    document.querySelectorAll('[data-close-item-modal]').forEach(button => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', event => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });
});