window.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('jt-app-authenticated')) {
        document.querySelectorAll('form:not([data-allow-autocomplete])').forEach((form) => {
            form.setAttribute('autocomplete', 'off');
            form.setAttribute('data-form-type', 'other');
            form.querySelectorAll('input, textarea, select').forEach((field) => {
                const type = (field.getAttribute('type') || '').toLowerCase();
                if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'checkbox' || type === 'radio') {
                    return;
                }
                const existing = (field.getAttribute('autocomplete') || '').toLowerCase();
                if (type === 'password') {
                    if (existing === '' || existing === 'off') {
                        field.setAttribute('autocomplete', 'new-password');
                    }
                    return;
                }
                if (existing === '' || existing === 'off') {
                    field.setAttribute('autocomplete', 'off');
                }
                field.setAttribute('data-1p-ignore', 'true');
                field.setAttribute('data-lpignore', 'true');
                field.setAttribute('data-dashlane-ignore', 'true');
            });
        });
    }

    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    alerts.forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.25s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 250);
        }, 5000);
    });

    const largeViewport = window.matchMedia('(min-width: 992px)');
    const filterCards = Array.from(document.querySelectorAll('.index-card')).filter((card) => {
        const header = card.querySelector(':scope > .index-card-header');
        if (!header) {
            return false;
        }

        return header.textContent.toLowerCase().includes('filters');
    });

    const setFilterState = (entry, expanded) => {
        entry.expanded = expanded;
        entry.body.style.display = expanded ? '' : 'none';
        entry.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        entry.toggle.innerHTML = expanded
            ? '<i class="fas fa-chevron-up me-1"></i>Hide'
            : '<i class="fas fa-chevron-down me-1"></i>Show';
    };

    const responsiveEntries = filterCards.map((card, index) => {
        const header = card.querySelector(':scope > .index-card-header');
        const body = card.querySelector(':scope > .card-body');
        if (!header || !body) {
            return null;
        }

        header.classList.add('d-flex', 'align-items-center', 'justify-content-between');
        if (!header.id) {
            header.id = `responsive-filter-header-${index + 1}`;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-outline-secondary btn-sm d-lg-none';
        toggle.setAttribute('aria-controls', `responsive-filter-body-${index + 1}`);
        body.id = `responsive-filter-body-${index + 1}`;

        const entry = { card, header, body, toggle, expanded: false };
        setFilterState(entry, false);

        toggle.addEventListener('click', () => {
            setFilterState(entry, !entry.expanded);
        });

        header.appendChild(toggle);
        return entry;
    }).filter(Boolean);

    const applyResponsiveFilterMode = () => {
        responsiveEntries.forEach((entry) => {
            if (largeViewport.matches) {
                entry.body.style.display = '';
                entry.toggle.classList.add('d-none');
                entry.toggle.setAttribute('aria-expanded', 'true');
                return;
            }

            entry.toggle.classList.remove('d-none');
            setFilterState(entry, !!entry.expanded);
        });
    };

    if (responsiveEntries.length > 0) {
        applyResponsiveFilterMode();
        largeViewport.addEventListener('change', applyResponsiveFilterMode);
    }
});

window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('client-form');
    const alertEl = document.getElementById('client-duplicate-alert');
    if (!form || !alertEl || !form.dataset.checkUrl) {
        return;
    }

    const checkUrl = form.dataset.checkUrl;
    const clientsBase = form.dataset.clientsBase || '/clients';
    const excludeId = form.dataset.clientId || '0';

    const fieldNames = ['first_name', 'last_name', 'company_name', 'email', 'phone'];
    const inputs = {};
    fieldNames.forEach((name) => {
        const el = form.elements.namedItem(name);
        if (el && el instanceof HTMLElement) {
            inputs[name] = el;
        }
    });

    const reasonLabels = {
        name: 'Same first and last name',
        company: 'Same company name',
        phone: 'Same phone number',
        email: 'Same email address',
    };

    const digitsOnly = (value) => String(value || '').replace(/\D/g, '');

    const shouldQueryDuplicates = () => {
        const fn = String(inputs.first_name?.value || '').trim();
        const ln = String(inputs.last_name?.value || '').trim();
        const co = String(inputs.company_name?.value || '').trim();
        const phoneDigits = digitsOnly(inputs.phone?.value);
        const em = String(inputs.email?.value || '').trim();
        if (fn !== '' && ln !== '') {
            return true;
        }
        if (co !== '') {
            return true;
        }
        if (phoneDigits.length >= 7) {
            return true;
        }
        if (em !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            return true;
        }
        return false;
    };

    const buildQuery = () => {
        const p = new URLSearchParams();
        fieldNames.forEach((name) => {
            const el = inputs[name];
            if (el && 'value' in el) {
                p.set(name, String(el.value || ''));
            }
        });
        if (excludeId !== '0') {
            p.set('exclude_id', excludeId);
        }
        return p.toString();
    };

    let debounceTimer = null;

    const renderAlert = (matches) => {
        alertEl.classList.add('d-none');
        alertEl.innerHTML = '';
        if (!matches || matches.length === 0) {
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Possible duplicate client(s) already in the system:';
        const list = document.createElement('ul');
        list.className = 'mb-0 mt-2';

        matches.forEach((m) => {
            const li = document.createElement('li');
            const base = clientsBase.endsWith('/') ? clientsBase.slice(0, -1) : clientsBase;
            const href = `${base}/${m.id}`;
            const link = document.createElement('a');
            link.href = href;
            link.textContent = `#${m.id} ${m.display_name}`;
            li.appendChild(link);
            const reasons = Array.isArray(m.reasons) ? m.reasons : [];
            if (reasons.length > 0) {
                const note = document.createElement('span');
                note.className = 'text-muted';
                note.textContent = ` (${reasons.map((r) => reasonLabels[r] || r).join('; ')})`;
                li.appendChild(note);
            }
            list.appendChild(li);
        });

        const headerRow = document.createElement('div');
        headerRow.className = 'd-flex justify-content-between align-items-start gap-2';
        headerRow.appendChild(title);
        const dismissBtn = document.createElement('button');
        dismissBtn.type = 'button';
        dismissBtn.className = 'btn-close';
        dismissBtn.setAttribute('aria-label', 'Dismiss duplicate warning');
        dismissBtn.addEventListener('click', () => {
            alertEl.classList.add('d-none');
            alertEl.innerHTML = '';
        });
        headerRow.appendChild(dismissBtn);

        alertEl.appendChild(headerRow);
        alertEl.appendChild(list);
        alertEl.classList.remove('d-none');
    };

    const runDuplicateCheck = async () => {
        if (!shouldQueryDuplicates()) {
            alertEl.classList.add('d-none');
            alertEl.innerHTML = '';
            return;
        }
        try {
            const res = await fetch(`${checkUrl}?${buildQuery()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            renderAlert(data.matches || []);
        } catch (e) {
            console.error(e);
        }
    };

    const scheduleCheck = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            void runDuplicateCheck();
        }, 400);
    };

    ['first_name', 'last_name', 'company_name'].forEach((name) => {
        const el = inputs[name];
        if (!el) {
            return;
        }
        el.addEventListener('input', scheduleCheck);
        el.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            void runDuplicateCheck();
        });
    });

    ['phone', 'email'].forEach((name) => {
        const el = inputs[name];
        if (!el) {
            return;
        }
        el.addEventListener('input', scheduleCheck);
        el.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            void runDuplicateCheck();
        });
    });

    form.addEventListener('submit', async (e) => {
        if (form.dataset.duplicateBypass === '1') {
            form.dataset.duplicateBypass = '';
            return;
        }
        e.preventDefault();
        try {
            const res = await fetch(`${checkUrl}?${buildQuery()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                window.alert('Could not verify duplicates. Please try again.');
                return;
            }
            const data = await res.json();
            const matches = data.matches || [];
            if (matches.length === 0) {
                form.dataset.duplicateBypass = '1';
                form.requestSubmit();
                return;
            }
            const summary = matches
                .map((m) => `#${m.id} ${m.display_name}`)
                .join('; ');
            const ok = window.confirm(
                `Possible duplicate client(s) already in the system:\n${summary}\n\nSave anyway?`
            );
            if (ok) {
                form.dataset.duplicateBypass = '1';
                form.requestSubmit();
            }
        } catch (err) {
            console.error(err);
            window.alert('Could not verify duplicates. Please try again.');
        }
    });
});

window.jtSubmitLock = (function () {
    const defaultSavingHtml = (label) => (
        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
        + '<span>' + label + '</span>'
    );

    const lockButton = (button, options = {}) => {
        if (!button || button.disabled || button.dataset.jtSubmitLocked === '1') {
            return false;
        }

        const label = String(options.label || 'Saving…');
        button.dataset.jtSubmitLocked = '1';
        button.dataset.jtSubmitLockOriginalHtml = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = options.savingHtml || defaultSavingHtml(label);
        return true;
    };

    const unlockButton = (button) => {
        if (!button) {
            return;
        }
        if (button.dataset.jtSubmitLockOriginalHtml) {
            button.innerHTML = button.dataset.jtSubmitLockOriginalHtml;
            delete button.dataset.jtSubmitLockOriginalHtml;
        }
        button.disabled = false;
        delete button.dataset.jtSubmitLocked;
        button.removeAttribute('aria-busy');
    };

    const showProgress = (container) => {
        if (!container) {
            return;
        }
        container.classList.remove('d-none');
        container.setAttribute('aria-hidden', 'false');
    };

    const hideProgress = (container) => {
        if (!container) {
            return;
        }
        container.classList.add('d-none');
        container.setAttribute('aria-hidden', 'true');
    };

    const ensureFormProgress = (form) => {
        let progress = form.querySelector(':scope > .jt-submit-progress');
        if (!progress) {
            progress = document.createElement('div');
            progress.className = 'jt-submit-progress d-none';
            progress.setAttribute('role', 'progressbar');
            progress.setAttribute('aria-label', 'Saving');
            progress.setAttribute('aria-hidden', 'true');
            progress.innerHTML = '<div class="jt-submit-progress-bar"></div>';
            form.insertBefore(progress, form.firstChild);
        }
        return progress;
    };

    const lockForm = (form, options = {}) => {
        if (!form || form.dataset.jtSubmitLocked === '1') {
            return false;
        }

        form.dataset.jtSubmitLocked = '1';
        const label = String(options.label || form.getAttribute('data-jt-submit-label') || 'Saving…');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            lockButton(button, { label, savingHtml: options.savingHtml });
        });
        showProgress(ensureFormProgress(form));
        return true;
    };

    const unlockForm = (form) => {
        if (!form) {
            return;
        }
        delete form.dataset.jtSubmitLocked;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(unlockButton);
        hideProgress(form.querySelector(':scope > .jt-submit-progress'));
    };

    const lockModalSave = (saveButton, options = {}) => {
        if (!lockButton(saveButton, options)) {
            return false;
        }
        const modal = saveButton.closest('.modal');
        if (modal) {
            modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach((el) => {
                el.dataset.jtSubmitLockWasDisabled = el.disabled ? '1' : '0';
                el.disabled = true;
                el.classList.add('disabled');
                el.setAttribute('aria-disabled', 'true');
            });
            showProgress(modal.querySelector('.jt-submit-progress'));
        }
        return true;
    };

    const unlockModalSave = (saveButton) => {
        if (!saveButton) {
            return;
        }
        unlockButton(saveButton);
        const modal = saveButton.closest('.modal');
        if (modal) {
            modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach((el) => {
                el.disabled = el.dataset.jtSubmitLockWasDisabled === '1';
                delete el.dataset.jtSubmitLockWasDisabled;
                el.classList.remove('disabled');
                el.removeAttribute('aria-disabled');
            });
            hideProgress(modal.querySelector('.jt-submit-progress'));
        }
    };

    return {
        lockButton,
        unlockButton,
        showProgress,
        hideProgress,
        lockForm,
        unlockForm,
        lockModalSave,
        unlockModalSave,
    };
})();

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-jt-submit-lock]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.jtSubmitLocked === '1') {
                event.preventDefault();
                return;
            }
            window.jtSubmitLock.lockForm(form);
        });
    });
});
