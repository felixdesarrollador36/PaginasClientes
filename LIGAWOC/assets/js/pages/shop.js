document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.shop-page');
    const modal = document.getElementById('itemModal');
    const modalContent = document.getElementById('itemModalContent');

    if (!page || !modal || !modalContent) {
        return;
    }

    const apiBase = page.dataset.itemApiBase || '';

    // --- SKELETON LOADERS ---
    function showSkeletons(count = 8) {
        const grid = document.querySelector('.shop-items-grid');
        if (!grid) return;
        grid.innerHTML = '';
        for (let i = 0; i < count; i++) {
            const card = document.createElement('div');
            card.className = 'product-card skeleton-card';
            card.innerHTML = `
                <div class="skeleton-img"></div>
                <div class="skeleton-line" style="width:70%;height:22px;"></div>
                <div class="skeleton-line" style="width:50%;height:18px;"></div>
                <div class="skeleton-line" style="width:60%;height:18px;"></div>
                <div class="skeleton-btn"></div>
            `;
            grid.appendChild(card);
        }
    }

    // --- IMAGE PLACEHOLDER ---
    function setImageFallbacks() {
        document.querySelectorAll('.shop-item-image-media').forEach(img => {
            img.onerror = function() {
                this.src = '/assets/img/placeholder.png';
                this.classList.add('img-placeholder');
            };
        });
    }

    // --- HOVER & BUTTON FEEDBACK ---
    function setupCardInteractions() {
        document.querySelectorAll('.shop-item-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.classList.add('is-hovered');
            });
            card.addEventListener('mouseleave', () => {
                card.classList.remove('is-hovered');
            });
        });
        document.querySelectorAll('.product-action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                btn.classList.add('clicked');
                setTimeout(() => btn.classList.remove('clicked'), 350);
            });
        });
    }

    // --- LABELS (Nuevo, Popular) ---
    function addDynamicLabels() {
        document.querySelectorAll('.shop-item-card').forEach(card => {
            const isNew = card.dataset.isNew === '1';
            const isPopular = card.dataset.isPopular === '1';
            if (isNew) {
                const label = document.createElement('div');
                label.className = 'product-label';
                label.textContent = 'Nuevo';
                card.appendChild(label);
            } else if (isPopular) {
                const label = document.createElement('div');
                label.className = 'product-label';
                label.textContent = 'Popular';
                card.appendChild(label);
            }
        });
    }

    // --- MODAL LOGIC (EXISTENTE) ---
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

    // --- NO RESULTS MESSAGE ---
    function showNoResults() {
        const grid = document.querySelector('.shop-items-grid');
        if (grid) {
            grid.innerHTML = '<div class="no-results">No se encontraron productos en esta categoría.</div>';
        }
    }

    // --- INIT ---
    setImageFallbacks();
    setupCardInteractions();
    addDynamicLabels();

    // Si quieres mostrar skeletons al cargar, llama showSkeletons() antes de cargar los productos.
    // Si no hay resultados, llama showNoResults().
});