document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.tournament-view-page');
    if (!page) {
        return;
    }

    const bracketFileSlug = page.dataset.bracketFileSlug || 'tournament';
    const rosterModal = document.getElementById('rosterModal');
    const rosterForm = document.getElementById('rosterForm');
    const rosterCount = document.getElementById('rosterCount');
    const mainRosterCount = document.getElementById('mainRosterCount');
    const subRosterCount = document.getElementById('subRosterCount');
    const rosterSubmitButton = document.getElementById('btnSubmitRoster');
    const minRosterSize = Number(rosterModal?.dataset.minRoster ?? 5);
    const maxRosterSize = Number(rosterModal?.dataset.maxRoster ?? 8);

    function showTab(name) {
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelectorAll('[data-tournament-tab]').forEach(tab => tab.classList.remove('active'));

        document.getElementById(`tab-${name}`)?.classList.add('active');
        document.querySelector(`[data-tournament-tab="${name}"]`)?.classList.add('active');
    }

    function openRosterModal() {
        if (!rosterModal) {
            return;
        }
        rosterModal.classList.add('active');
        document.body.classList.add('has-modal-open');
    }

    function closeRosterModal() {
        if (!rosterModal) {
            return;
        }
        rosterModal.classList.remove('active');
        document.body.classList.remove('has-modal-open');
    }

    function updateRosterSelection() {
        if (!rosterModal) {
            return;
        }
        
        const mainCheckboxes = rosterModal.querySelectorAll('.main-roster-checkbox');
        const subCheckboxes = rosterModal.querySelectorAll('.substitute-checkbox');
        const substituteOptions = rosterModal.querySelectorAll('.substitute-option');
        const mainRosterSection = rosterModal.querySelector('.main-roster-section');
        
        const selectedMain = rosterModal.querySelectorAll('.main-roster-checkbox:checked').length;
        const selectedSub = rosterModal.querySelectorAll('.substitute-checkbox:checked').length;
        const selectedMainIds = Array.from(mainCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        
        if (mainRosterCount) {
            mainRosterCount.textContent = selectedMain;
        }
        if (subRosterCount) {
            subRosterCount.textContent = selectedSub;
        }
        
        mainCheckboxes.forEach(cb => {
            const option = cb.closest('.roster-player-option');
            if (selectedMain >= minRosterSize) {
                if (!cb.checked) {
                    cb.disabled = true;
                    cb.style.opacity = '0.5';
                    cb.style.pointerEvents = 'none';
                    if (option) {
                        option.style.opacity = '0.5';
                        option.style.pointerEvents = 'none';
                    }
                }
            } else {
                cb.disabled = false;
                cb.style.opacity = '1';
                cb.style.pointerEvents = 'auto';
                if (option) {
                    option.style.opacity = '1';
                    option.style.pointerEvents = 'auto';
                }
            }
        });
        
        substituteOptions.forEach(option => {
            const cb = option.querySelector('.substitute-checkbox');
            if (selectedMain >= minRosterSize) {
                if (selectedMainIds.includes(cb.value)) {
                    option.style.display = 'flex';
                    option.style.opacity = '0.4';
                    option.style.pointerEvents = 'none';
                    cb.disabled = true;
                    cb.checked = false;
                } else {
                    option.style.display = 'flex';
                    option.style.opacity = '1';
                    option.style.pointerEvents = 'auto';
                    cb.disabled = false;
                }
            } else {
                option.style.display = 'none';
                cb.disabled = true;
                cb.checked = false;
            }
        });

        const totalSelected = selectedMain + selectedSub;
        if (rosterSubmitButton) {
            rosterSubmitButton.disabled = selectedMain < minRosterSize;
        }
    }

    function submitRoster() {
        if (!rosterModal || !rosterForm) {
            return;
        }

        const selectedMain = rosterModal.querySelectorAll('.main-roster-checkbox:checked').length;
        if (selectedMain < minRosterSize) {
            window.alert(`Debes seleccionar al menos ${minRosterSize} jugadores principales.`);
            return;
        }

        rosterForm.submit();
    }

    function exportBracketImage() {
        const bracketEl = document.getElementById('bracket-card-wrapper');
        if (!bracketEl || !window.html2canvas) {
            return;
        }

        bracketEl.classList.add('exporting');
        window.html2canvas(bracketEl, {
            backgroundColor: '#1a0a2e',
            scale: 2,
            useCORS: true,
        }).then(canvas => {
            bracketEl.classList.remove('exporting');
            const link = document.createElement('a');
            link.download = `bracket-${bracketFileSlug}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }

    document.querySelectorAll('[data-tournament-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            showTab(tab.dataset.tournamentTab || 'info');
        });
    });

    document.querySelectorAll('[data-export-bracket]').forEach(button => {
        button.addEventListener('click', exportBracketImage);
    });

    document.querySelectorAll('[data-open-roster-modal]').forEach(button => {
        button.addEventListener('click', openRosterModal);
    });

    document.querySelectorAll('[data-close-roster-modal]').forEach(button => {
        button.addEventListener('click', closeRosterModal);
    });

    rosterModal?.addEventListener('click', event => {
        if (event.target === rosterModal) {
            closeRosterModal();
        }
    });

    rosterModal?.querySelectorAll('.main-roster-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateRosterSelection);
    });

    rosterModal?.querySelectorAll('.substitute-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateRosterSelection);
    });

    if (rosterSubmitButton) {
        rosterSubmitButton.addEventListener('click', submitRoster);
    }
});