(function () {
    function headerText(cell) {
        return (cell && cell.textContent ? cell.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function cleanText(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
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

        const preferred = ['name', 'title', 'task', 'job', 'client', 'company', 'estate', 'prospect', 'employee', 'user', 'email', 'alert', 'subject'];
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

    function shouldSkipFieldLabel(label) {
        const normalized = (label || '').trim().toLowerCase();
        return normalized === '' || normalized === 'id' || normalized === '#';
    }

    function resolveFieldIndexes(table, headers, primaryIndex, actionIndex) {
        const explicit = (table.dataset.cardFields || '')
            .split(',')
            .map((value) => Number.parseInt(value.trim(), 10))
            .filter((value) => Number.isInteger(value) && value >= 0);
        if (explicit.length) {
            return explicit.slice(0, 4);
        }

        const indexes = [];
        for (let i = 0; i < headers.length; i += 1) {
            if (i === primaryIndex || i === actionIndex) {
                continue;
            }
            if (shouldSkipFieldLabel(headers[i])) {
                continue;
            }

            indexes.push(i);
            if (indexes.length >= 3) {
                break;
            }
        }

        return indexes;
    }

    function resolveRowHref(row, titleCell, actionCell) {
        const rowHref = row.getAttribute('data-href');
        if (rowHref && rowHref.trim() !== '') {
            return rowHref.trim();
        }

        const cellAnchor = titleCell ? titleCell.querySelector('a[href]') : null;
        if (cellAnchor) {
            return cellAnchor.getAttribute('href') || '';
        }

        const fallbackAnchors = Array.from(row.querySelectorAll('a[href]'));
        const usableAnchor = fallbackAnchors.find(function (anchor) {
            if (!actionCell) {
                return true;
            }
            return !actionCell.contains(anchor);
        });
        if (usableAnchor) {
            return usableAnchor.getAttribute('href') || '';
        }

        return '';
    }

    function renderFieldRow(label, valueHtml, isCheckboxField) {
        const wrapper = document.createElement('div');
        wrapper.className = 'card-list-field';
        if (isCheckboxField) {
            wrapper.classList.add('is-checkbox');
        }

        const valueNode = document.createElement('span');
        valueNode.className = 'card-list-field-value';
        valueNode.innerHTML = valueHtml;

        if (!isCheckboxField) {
            const labelNode = document.createElement('span');
            labelNode.className = 'card-list-field-label';
            labelNode.textContent = label;
            wrapper.appendChild(labelNode);
        }
        wrapper.appendChild(valueNode);

        return wrapper;
    }

    function cloneActions(cell) {
        if (!cell || !hasActionElements(cell)) {
            return null;
        }

        const actionGroup = document.createElement('div');
        actionGroup.className = 'card-list-actions-inline';

        const fragment = document.createElement('div');
        fragment.innerHTML = cell.innerHTML;

        Array.from(fragment.children).forEach(function (node) {
            if (node.classList && node.classList.contains('btn')) {
                node.classList.add('btn-sm');
            }

            if (node.tagName === 'FORM') {
                node.classList.add('card-list-action-form');
                const formButtons = node.querySelectorAll('.btn');
                formButtons.forEach((button) => button.classList.add('btn-sm'));
            }

            actionGroup.appendChild(node);
        });

        return actionGroup.childElementCount > 0 ? actionGroup : null;
    }

    function buildRowItem(row, headers, primaryIndex, fieldIndexes, actionIndex) {
        const cells = Array.from(row.querySelectorAll('td'));
        if (!cells.length) {
            return null;
        }

        const item = document.createElement('div');
        item.className = 'card-list-item';

        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
            item.classList.add('card-list-item-empty', 'text-muted');
            item.innerHTML = cells[0].innerHTML;
            return item;
        }

        const titleCell = cells[primaryIndex] || cells[0];
        const actionCell = actionIndex >= 0 ? cells[actionIndex] : null;
        const rowHref = resolveRowHref(row, titleCell, actionCell);
        const titleHtml = titleCell ? titleCell.innerHTML : 'Record';
        const titleText = cleanText(titleCell ? titleCell.textContent : '');

        const header = document.createElement('div');
        header.className = 'card-list-item-header';

        const title = document.createElement(rowHref !== '' && !titleCell.querySelector('a[href]') ? 'a' : 'div');
        title.className = 'card-list-item-title';
        if (title.tagName === 'A') {
            title.setAttribute('href', rowHref);
        }
        title.innerHTML = titleHtml;
        header.appendChild(title);

        const actions = cloneActions(actionCell);
        if (actions) {
            header.appendChild(actions);
        }

        item.appendChild(header);

        const meta = document.createElement('div');
        meta.className = 'card-list-item-meta';

        fieldIndexes.forEach(function (fieldIndex) {
            if (!cells[fieldIndex]) {
                return;
            }

            const label = headers[fieldIndex] || 'Field';
            const valueHtml = cells[fieldIndex].innerHTML;
            const valueText = cleanText(cells[fieldIndex].textContent || '');
            const hasCheckbox = !!cells[fieldIndex].querySelector('input[type=\"checkbox\"]');

            if (!hasCheckbox && (valueText === '' || valueText === '—')) {
                return;
            }

            meta.appendChild(renderFieldRow(label, valueHtml, hasCheckbox));
        });

        if (meta.childElementCount > 0) {
            item.appendChild(meta);
        }

        if (rowHref !== '' && titleText !== '') {
            item.classList.add('is-clickable');
            item.setAttribute('data-href', rowHref);
            item.addEventListener('click', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                if (event.target.closest('a, button, input, select, textarea, label, form, .dropdown-menu, [data-bs-toggle], [role="button"]')) {
                    return;
                }

                window.location.href = rowHref;
            });
        }

        return item;
    }

    function buildRenderer(table, host) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return null;
        }

        return function renderRows() {
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
            listWrapper.className = 'card-list-list';

            rows.forEach(function (row) {
                const item = buildRowItem(row, headers, primaryIndex, fieldIndexes, actionIndex);
                if (item) {
                    listWrapper.appendChild(item);
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

        const renderRows = buildRenderer(table, host);
        if (!renderRows) {
            return;
        }

        renderRows();
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
                    renderRows();
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
