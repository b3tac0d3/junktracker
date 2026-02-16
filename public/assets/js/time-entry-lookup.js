window.addEventListener('DOMContentLoaded', () => {
    const bindLookup = ({
        input,
        hidden,
        box,
        lookupUrl,
        minChars = 2,
        render,
        onSelect,
    }) => {
        if (!input || !hidden || !box || !lookupUrl) {
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

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                const content = render(item);
                if (content.title) {
                    const title = document.createElement('div');
                    title.className = 'fw-semibold';
                    title.textContent = content.title;
                    button.appendChild(title);
                }

                if (content.meta) {
                    const meta = document.createElement('small');
                    meta.className = 'text-muted';
                    meta.textContent = content.meta;
                    button.appendChild(meta);
                }

                button.addEventListener('click', () => {
                    input.value = content.title || '';
                    hidden.value = id;
                    onSelect(item);
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
            if (q.length < minChars) {
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
            search();
        });

        document.addEventListener('click', (event) => {
            if (!box.contains(event.target) && event.target !== input) {
                hide();
            }
        });
    };

    const employeeInput = document.getElementById('employee_search');
    const employeeHidden = document.getElementById('employee_id');
    const employeeBox = document.getElementById('time_employee_suggestions');
    const employeeLookupUrl = document.getElementById('time_employee_lookup_url')?.value || '';
    const payRateInput = document.getElementById('pay_rate');

    bindLookup({
        input: employeeInput,
        hidden: employeeHidden,
        box: employeeBox,
        lookupUrl: employeeLookupUrl,
        render: (item) => {
            const name = (item.name || `Employee #${item.id}`).toString();
            const rate = item.pay_rate !== null && item.pay_rate !== undefined
                ? `$${Number(item.pay_rate).toFixed(2)}/hr`
                : '';
            return { title: name, meta: rate };
        },
        onSelect: (item) => {
            const rate = item.pay_rate !== null && item.pay_rate !== undefined
                ? Number(item.pay_rate).toFixed(2)
                : '';

            employeeHidden.dataset.payRate = rate;
            if (payRateInput && payRateInput.value.trim() === '' && rate !== '') {
                payRateInput.value = rate;
                payRateInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    });

    const jobInput = document.getElementById('job_search');
    const jobHidden = document.getElementById('job_id');
    const jobBox = document.getElementById('time_job_suggestions');
    const jobLookupUrl = document.getElementById('time_job_lookup_url')?.value || '';

    bindLookup({
        input: jobInput,
        hidden: jobHidden,
        box: jobBox,
        lookupUrl: jobLookupUrl,
        render: (item) => {
            const id = String(item.id || '');
            const name = (item.name || `Job #${id}`).toString();
            const title = `#${id} - ${name}`;

            const city = (item.city || '').toString().trim();
            const state = (item.state || '').toString().trim();
            const status = (item.job_status || '').toString().trim();
            const location = city && state ? `${city}, ${state}` : (city || state);
            const meta = [location, status].filter(Boolean).join(' • ');

            return { title, meta };
        },
        onSelect: () => {},
    });
});
