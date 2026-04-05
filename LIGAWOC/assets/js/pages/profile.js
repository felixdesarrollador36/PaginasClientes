document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.profile-page');
    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrfToken || '';
    const equipMarcoUrl = page.dataset.equipMarcoUrl || '';
    const equipPortadaUrl = page.dataset.equipPortadaUrl || '';
    const resendEmailUrl = page.dataset.resendEmailUrl || '';
    const pendingEmail = page.dataset.emailPending || '';
    const isEmailSuccess = page.dataset.emailSuccess === '1';

    const emailModal = document.getElementById('emailModal');
    const emailStep1 = document.getElementById('emailStep1');
    const emailStep2 = document.getElementById('emailStep2');
    const emailSuccess = document.getElementById('emailSuccess');
    const pendingEmailNode = document.getElementById('pendingEmail');
    const verifyEmailInput = document.getElementById('verifyEmailInput');

    function setModalVisible(modal, show) {
        if (!modal) {
            return;
        }

        modal.style.display = show ? 'flex' : 'none';
        document.body.classList.toggle('has-modal-open', show);
    }

    function openModalById(modalId) {
        const modal = document.getElementById(modalId);
        setModalVisible(modal, true);
    }

    function closeModalFromElement(element) {
        const modal = element.closest('.modal');
        setModalVisible(modal, false);
    }

    function closeEmailModal() {
        setModalVisible(emailModal, false);
        if (emailStep1) {
            emailStep1.style.display = 'block';
        }
        if (emailStep2) {
            emailStep2.style.display = 'none';
        }
        if (emailSuccess) {
            emailSuccess.style.display = 'none';
        }
    }

    function redirectEquip(baseUrl, itemId) {
        if (!baseUrl) {
            return;
        }
        const target = `${baseUrl}?item_id=${encodeURIComponent(itemId)}&csrf_token=${encodeURIComponent(csrfToken)}`;
        window.location.href = target;
    }

    function submitResendCode() {
        if (!resendEmailUrl) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = resendEmailUrl;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
    }

    document.querySelectorAll('[data-open-modal]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.openModal;
            if (targetId) {
                openModalById(targetId);
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => closeModalFromElement(button));
    });

    document.querySelectorAll('[data-close-email-modal]').forEach(button => {
        button.addEventListener('click', closeEmailModal);
    });

    document.querySelectorAll('[data-auto-submit-profile]').forEach(input => {
        input.addEventListener('change', () => {
            document.getElementById('profile-form')?.submit();
        });
    });

    document.querySelectorAll('[data-equip-marco-id]').forEach(card => {
        const activate = () => redirectEquip(equipMarcoUrl, card.dataset.equipMarcoId || 0);
        card.addEventListener('click', activate);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    document.querySelectorAll('[data-equip-portada-id]').forEach(card => {
        const activate = () => redirectEquip(equipPortadaUrl, card.dataset.equipPortadaId || 0);
        card.addEventListener('click', activate);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    document.querySelectorAll('[data-resend-email]').forEach(button => {
        button.addEventListener('click', submitResendCode);
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target !== modal) {
                return;
            }

            if (modal.id === 'emailModal') {
                closeEmailModal();
            } else {
                setModalVisible(modal, false);
            }
        });
    });

    if (pendingEmail && emailModal) {
        setModalVisible(emailModal, true);
        if (emailStep1) {
            emailStep1.style.display = 'none';
        }
        if (emailStep2) {
            emailStep2.style.display = 'block';
        }
        if (pendingEmailNode) {
            pendingEmailNode.textContent = pendingEmail;
        }
        if (verifyEmailInput) {
            verifyEmailInput.value = pendingEmail;
        }
    }

    if (isEmailSuccess && emailModal) {
        setModalVisible(emailModal, true);
        if (emailStep1) {
            emailStep1.style.display = 'none';
        }
        if (emailStep2) {
            emailStep2.style.display = 'none';
        }
        if (emailSuccess) {
            emailSuccess.style.display = 'block';
        }
    }
});