window.addEventListener('DOMContentLoaded', () => {
    const preserveMeridiemOnKeyboardEdit = (input) => {
        if (!(input instanceof HTMLInputElement) || input.type !== 'datetime-local') {
            return;
        }

        const rememberValue = () => {
            input.dataset.previousDateTimeValue = input.value || '';
        };

        rememberValue();

        input.addEventListener('focus', rememberValue);
        input.addEventListener('keydown', (event) => {
            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            const ignoredKeys = new Set(['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown']);
            if (ignoredKeys.has(event.key)) {
                return;
            }

            input.dataset.keyboardDateTimeEdit = '1';
        });

        const maybePreserveMeridiem = () => {
            const previousValue = input.dataset.previousDateTimeValue || '';
            const currentValue = input.value || '';
            const keyboardEdit = input.dataset.keyboardDateTimeEdit === '1';

            if (!keyboardEdit || previousValue === '' || currentValue === '') {
                rememberValue();
                delete input.dataset.keyboardDateTimeEdit;
                return;
            }

            const previous = new Date(previousValue);
            const current = new Date(currentValue);
            if (Number.isNaN(previous.getTime()) || Number.isNaN(current.getTime())) {
                rememberValue();
                delete input.dataset.keyboardDateTimeEdit;
                return;
            }

            const previousHour = previous.getHours();
            const currentHour = current.getHours();
            if (previousHour >= 12 && currentHour < 12) {
                current.setHours(currentHour + 12);
                const offset = current.getTimezoneOffset();
                const localTime = new Date(current.getTime() - (offset * 60000));
                input.value = localTime.toISOString().slice(0, 16);
            }

            rememberValue();
            delete input.dataset.keyboardDateTimeEdit;
        };

        input.addEventListener('change', maybePreserveMeridiem);
        input.addEventListener('blur', maybePreserveMeridiem);
    };

    document.querySelectorAll('input[type="datetime-local"]').forEach((input) => {
        preserveMeridiemOnKeyboardEdit(input);
    });

    const initDateRangePicker = (picker) => {
        const display = picker.querySelector('.date-range-display');
        const startInput = picker.querySelector('.date-range-start');
        const endInput = picker.querySelector('.date-range-end');

        if (!(display instanceof HTMLInputElement) || !(startInput instanceof HTMLInputElement) || !(endInput instanceof HTMLInputElement)) {
            return;
        }

        const formatDate = (value) => {
            const raw = String(value || '').trim();
            if (raw === '') {
                return '';
            }

            const parts = raw.split('-');
            if (parts.length !== 3) {
                return raw;
            }

            return `${parts[1]}/${parts[2]}/${parts[0]}`;
        };

        const updateDisplay = () => {
            const startValue = String(startInput.value || '').trim();
            const endValue = String(endInput.value || '').trim();

            if (startValue === '' && endValue === '') {
                display.value = '';
                return;
            }

            if (startValue !== '' && endValue !== '') {
                display.value = `${formatDate(startValue)} - ${formatDate(endValue)}`;
                return;
            }

            display.value = formatDate(startValue || endValue);
        };

        const showNativePicker = (input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            if (typeof input.showPicker === 'function') {
                input.showPicker();
                return;
            }

            input.focus();
            input.click();
        };

        const startSelectionFlow = () => {
            picker.dataset.rangeSelectionFlow = '1';
            showNativePicker(startInput);
        };

        display.addEventListener('click', startSelectionFlow);
        display.addEventListener('focus', startSelectionFlow);

        startInput.addEventListener('change', () => {
            const startValue = String(startInput.value || '').trim();
            const endValue = String(endInput.value || '').trim();

            if (startValue !== '' && (endValue === '' || endValue < startValue)) {
                endInput.value = startValue;
            }

            updateDisplay();

            if (picker.dataset.rangeSelectionFlow === '1') {
                window.setTimeout(() => showNativePicker(endInput), 60);
            }
        });

        endInput.addEventListener('change', () => {
            const startValue = String(startInput.value || '').trim();
            const endValue = String(endInput.value || '').trim();

            if (startValue !== '' && endValue !== '' && endValue < startValue) {
                endInput.value = startValue;
            }

            picker.dataset.rangeSelectionFlow = '0';
            updateDisplay();
        });

        updateDisplay();
    };

    document.querySelectorAll('.date-range-picker').forEach((picker) => {
        initDateRangePicker(picker);
    });

    const alerts = document.querySelectorAll('.alert');
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
