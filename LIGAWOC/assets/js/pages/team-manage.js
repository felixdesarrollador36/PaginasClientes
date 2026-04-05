document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.team-manage-page');
    const roleModal = document.getElementById('roleModal');
    const roleMemberName = document.getElementById('roleMemberName');
    const roleSelect = document.getElementById('roleSelect');

    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrfToken || '';
    const apiBase = page.dataset.apiBase || '';
    const kickMemberBase = page.dataset.kickMemberBase || '';
    const changeRoleBase = page.dataset.changeRoleBase || '';

    let roleTargetId = null;

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
            body,
        });

        return response.json();
    }

    function openRoleModal(button) {
        if (!roleModal || !roleMemberName || !roleSelect || !button) {
            return;
        }

        roleTargetId = button.dataset.userId || null;
        roleMemberName.textContent = button.dataset.memberName || '';
        roleSelect.value = button.dataset.currentRole || '';
        roleModal.classList.add('active');
        document.body.classList.add('has-modal-open');
    }

    function closeRoleModal() {
        roleModal?.classList.remove('active');
        document.body.classList.remove('has-modal-open');
        roleTargetId = null;
    }

    async function handleRequest(button) {
        const requestId = button.dataset.requestId || '';
        const action = button.dataset.requestAction || '';

        if (!requestId || !action) {
            return;
        }

        try {
            const data = await postJson(`${apiBase}${action}-request/${requestId}`);
            if (!data.success) {
                window.alert(data.error || 'Error');
                return;
            }

            const requestCard = document.getElementById(`request-${requestId}`);
            if (requestCard) {
                requestCard.hidden = true;
            }

            window.alert(data.message);
        } catch {
            window.alert('No se pudo procesar la solicitud.');
        }
    }

    async function kickMember(button) {
        const userId = button.dataset.userId || '';
        const memberName = button.dataset.memberName || 'este miembro';

        if (!userId) {
            return;
        }

        if (!window.confirm(`¿Estás seguro de que quieres expulsar a ${memberName} del equipo?`)) {
            return;
        }

        try {
            const data = await postJson(`${kickMemberBase}${userId}`);
            if (!data.success) {
                window.alert(data.error || 'Error');
                return;
            }

            const memberRow = document.getElementById(`member-${userId}`);
            if (memberRow) {
                memberRow.hidden = true;
            }

            window.location.reload();
        } catch {
            window.alert('No se pudo expulsar al miembro.');
        }
    }

    async function submitRoleChange() {
        if (!roleTargetId || !roleSelect) {
            return;
        }

        const body = new FormData();
        body.append('role', roleSelect.value);

        try {
            const data = await postJson(`${changeRoleBase}${roleTargetId}`, body);
            if (!data.success) {
                window.alert(data.error || 'Error al cambiar rol');
                return;
            }

            const roleBadge = document.getElementById(`role-badge-${roleTargetId}`);
            if (roleBadge) {
                roleBadge.textContent = data.newRoleLabel;
            }

            closeRoleModal();
        } catch {
            window.alert('No se pudo cambiar el rol.');
        }
    }

    document.querySelectorAll('[data-request-action]').forEach(button => {
        button.addEventListener('click', () => handleRequest(button));
    });

    document.querySelectorAll('[data-kick-member]').forEach(button => {
        button.addEventListener('click', () => kickMember(button));
    });

    document.querySelectorAll('[data-open-role-modal]').forEach(button => {
        button.addEventListener('click', () => openRoleModal(button));
    });

    document.querySelectorAll('[data-close-role-modal]').forEach(button => {
        button.addEventListener('click', closeRoleModal);
    });

    document.querySelector('[data-submit-role-change]')?.addEventListener('click', submitRoleChange);

    roleModal?.addEventListener('click', event => {
        if (event.target === roleModal) {
            closeRoleModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && roleModal?.classList.contains('active')) {
            closeRoleModal();
        }
    });
});