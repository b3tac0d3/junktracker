(function () {
    function headerText(cell) {
        return (cell && cell.textContent ? cell.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function hasActionElements(cell) {
        if (!cell) {
            return false;
        }

        return !!cell.querySelector('button, .btn, form, [data-bs-toggle="modal"], [data-action]');
    }

    function parseIndexAttribute(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return null;
        }

        const index = Number.parseInt(value, 10);
        return Number.isInteger(index) && index >= 0 ? index : null;
    }

    function detectActionIndex(table, headers, firstRowCells) {
        const explicit = parseIndexAttribute(table.dataset.cardActionsCol || '');
        if (explicit !== null) {
            return explicit;
        }

        const headerBased = headers.findIndex((label) => /action/i.test(label));
        if (headerBased >= 0) {
            return headerBased;
        }

        if (firstRowCells.length > 0) {
            const lastIndex = firstRowCells.length - 1;
            const lastHeader = headers[lastIndex] || '';
            if ((lastHeader === '' || /action/i.test(lastHeader)) && hasActionElements(firstRowCells[lastIndex])) {
                return lastIndex;
            }
        }

        return -1;
    }

    function detectPrimaryIndex(table, headers, actionIndex) {
        const explicit = parseIndexAttribute(table.dataset.cardPrimaryCol || '');
        if (explicit !== null) {
            return explicit;
        }

        const preferred = ['name', 'title', 'job', 'client', 'company', 'estate', 'prospect', 'employee', 'user', 'email'];
        for (let i = 0; i < headers.length; i += 1) {
            if (i === actionIndex) {
                continue;
            }

            const label = headers[i].toLowerCase();
            if (preferred.some((term) => label.includes(term))) {
                return i;
            }
        }

        for (let i = 0; i < headers.length; i += 1) {
            if (i !== actionIndex) {
                return i;
            }
        }

        return 0;
    }

    function resolveFieldIndexes(table, headers, primaryIndex, actionIndex) {
        const explicit = (table.dataset.cardFields || '').split(',').map((value) => Number.parseInt(value.trim(), 10)).filter((value) => Number.isInteger(value) && value >= 0);
        if (explicit.length) {
            return explicit.slice(0, 5);
        }

        const indexes = [];
        for (let i = 0; i < headers.length; i += 1) {
            if (i === primaryIndex || i === actionIndex) {
                continue;
            }

            if ((headers[i] || '').trim() === '') {
                continue;
            }

            indexes.push(i);
            if (indexes.length >= 5) {
                break;
            }
        }

        return indexes;
    }

    function renderFieldRow(label, valueHtml) {
        const wrapper = document.createElement('div');
        wrapper.className = 'card-list-field';

        const labelNode = document.createElement('div');
        labelNode.className = 'card-list-field-label';
        labelNode.textContent = label;

        const valueNode = document.createElement('div');
        valueNode.className = 'card-list-field-value';
        valueNode.innerHTML = valueHtml;

        wrapper.appendChild(labelNode);
        wrapper.appendChild(valueNode);

        return wrapper;
    }

    function buildCard(row, headers, primaryIndex, fieldIndexes, actionIndex) {
        const cells = Array.from(row.querySelectorAll('td'));
        if (!cells.length) {
            return null;
        }

        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
            const col = document.createElement('div');
            col.className = 'col-12';

            const emptyCard = document.createElement('div');
            emptyCard.className = 'card card-list-item h-100';

            const body = document.createElement('div');
            body.className = 'card-body text-muted card-list-empty';
            body.innerHTML = cells[0].innerHTML;

            emptyCard.appendChild(body);
            col.appendChild(emptyCard);
            return col;
        }

        const titleCell = cells[primaryIndex] || cells[0];
        const titleHtml = titleCell ? titleCell.innerHTML : 'Record';

        const col = document.createElement('div');
        col.className = 'col-12';

        const card = document.createElement('div');
        card.className = 'card card-list-item h-100';

        const header = document.createElement('div');
        header.className = 'card-header card-list-title';
        header.innerHTML = titleHtml;

        const body = document.createElement('div');
        body.className = 'card-body';

        fieldIndexes.forEach(function (fieldIndex) {
            if (!cells[fieldIndex]) {
                return;
            }

            const label = headers[fieldIndex] || 'Field';
            const valueHtml = cells[fieldIndex].innerHTML;
            body.appendChild(renderFieldRow(label, valueHtml));
        });

        card.appendChild(header);
        card.appendChild(body);

        const actionCell = actionIndex >= 0 ? cells[actionIndex] : null;
        if (actionCell && hasActionElements(actionCell)) {
            const footer = document.createElement('div');
            footer.className = 'card-footer bg-white';

            const actionGroup = document.createElement('div');
            actionGroup.className = 'd-flex flex-wrap gap-2 card-list-actions mobile-two-col-buttons';

            const fragment = document.createElement('div');
            fragment.innerHTML = actionCell.innerHTML;
            Array.from(fragment.children).forEach(function (node) {
                actionGroup.appendChild(node);
            });

            footer.appendChild(actionGroup);
            card.appendChild(footer);
        }

        col.appendChild(card);
        return col;
    }

    function buildRenderer(table, host) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return null;
        }

        return function renderCards() {
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(function (row) {
                return !row.hidden && row.style.display !== 'none';
            });

            const headers = Array.from(table.querySelectorAll('thead th')).map(headerText);
            const firstDataRow = rows.find(function (row) {
                const cells = Array.from(row.querySelectorAll('td'));
                return cells.length > 0 && !(cells.length === 1 && cells[0].hasAttribute('colspan'));
            });
            const firstCells = firstDataRow ? Array.from(firstDataRow.querySelectorAll('td')) : [];
            const actionIndex = detectActionIndex(table, headers, firstCells);
            const primaryIndex = detectPrimaryIndex(table, headers, actionIndex);
            const fieldIndexes = resolveFieldIndexes(table, headers, primaryIndex, actionIndex);

            host.innerHTML = '';
            const listWrapper = document.createElement('div');
            listWrapper.className = 'card-list-grid row g-3';

            rows.forEach(function (row) {
                const card = buildCard(row, headers, primaryIndex, fieldIndexes, actionIndex);
                if (card) {
                    listWrapper.appendChild(card);
                }
            });

            host.appendChild(listWrapper);
        };
    }

    function enhanceTable(table) {
        if (!table || table.dataset.cardListReady === '1') {
            return;
        }

        const parentCard = table.closest('.card');
        if (parentCard) {
            parentCard.classList.add('card-list-parent');
        }

        const host = document.createElement('div');
        host.className = 'card-list-component';
        table.insertAdjacentElement('afterend', host);

        const renderCards = buildRenderer(table, host);
        if (!renderCards) {
            return;
        }

        renderCards();
        table.classList.add('d-none');
        table.setAttribute('aria-hidden', 'true');

        const tbody = table.querySelector('tbody');
        if (tbody) {
            let pending = null;
            const observer = new MutationObserver(function () {
                if (pending !== null) {
                    window.clearTimeout(pending);
                }

                pending = window.setTimeout(function () {
                    renderCards();
                    pending = null;
                }, 30);
            });
            observer.observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        }

        table.dataset.cardListReady = '1';
    }

    function init() {
        document.querySelectorAll('table.js-card-list-source').forEach(enhanceTable);
    }

    window.addEventListener('load', init);
})();
