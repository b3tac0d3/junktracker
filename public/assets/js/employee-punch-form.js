window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('employeeQuickPunchForm');
    const input = document.getElementById('employee_punch_job_search');
    const hidden = document.getElementById('employee_punch_job_id');
    const box = document.getElementById('employee_punch_job_suggestions');
    const lookupUrl = document.getElementById('employee_punch_job_lookup_url')?.value || '';

    if (!form || !input || !hidden || !box || !lookupUrl) {
        return;
    }

    const debounce = (fn, delay = 180) => {
        let timer = null;
        return (...args) => {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(() => fn(...args), delay);
        };
    };

    const hide = () => {
        box.classList.add('d-none');
        box.innerHTML = '';
    };

    const show = (items) => {
        box.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hide();
            return;
        }

        items.forEach((item) => {
            const id = String(item.id || '');
            if (!id) {
                return;
            }

            const name = (item.name || `Job #${id}`).toString();
            const title = `#${id} - ${name}`;
            const city = (item.city || '').toString().trim();
            const state = (item.state || '').toString().trim();
            const status = (item.job_status || '').toString().trim();
            const location = city && state ? `${city}, ${state}` : (city || state);
            const meta = [location, status].filter(Boolean).join(' • ');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const titleNode = document.createElement('div');
            titleNode.className = 'fw-semibold';
            titleNode.textContent = title;
            button.appendChild(titleNode);

            if (meta !== '') {
                const metaNode = document.createElement('small');
                metaNode.className = 'text-muted';
                metaNode.textContent = meta;
                button.appendChild(metaNode);
            }

            button.addEventListener('click', () => {
                input.value = title;
                hidden.value = id;
                input.setCustomValidity('');
                hide();
            });

            box.appendChild(button);
        });

        if (box.children.length === 0) {
            hide();
            return;
        }

        box.classList.remove('d-none');
    };

    const search = debounce(async () => {
        const q = input.value.trim();
        if (q.length < 2) {
            hide();
            return;
        }

        try {
            const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                hide();
                return;
            }

            const items = await response.json();
            show(items);
        } catch (_error) {
            hide();
        }
    }, 180);

    input.addEventListener('input', () => {
        hidden.value = '';
        input.setCustomValidity('');
        search();
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            // Keep the field valid when blank (non-job punch-in allowed).
            if (input.value.trim() === '') {
                input.setCustomValidity('');
            }
        }, 120);
    });

    form.addEventListener('submit', (event) => {
        input.setCustomValidity('');
        const hasTypedJob = input.value.trim() !== '';
        if (!hasTypedJob) {
            hidden.value = '';
            return;
        }

        if (hasTypedJob && hidden.value === '') {
            event.preventDefault();
            input.setCustomValidity('Select a job from the suggestion list.');
            input.reportValidity();
            return;
        }

        input.setCustomValidity('');
    });

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target) && event.target !== input) {
            hide();
        }
    });
});
