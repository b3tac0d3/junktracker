(function () {
    let wrapperCounter = 0;

    function ensureWrapperKey(wrapper) {
        if (wrapper.dataset.externalTopKey) {
            return wrapper.dataset.externalTopKey;
        }

        wrapperCounter += 1;
        const key = 'dt-top-' + wrapperCounter;
        wrapper.dataset.externalTopKey = key;
        return key;
    }

    function ensureHost(wrapper) {
        const key = ensureWrapperKey(wrapper);
        const parent = wrapper.parentElement;
        if (!parent) {
            return null;
        }

        let host = parent.querySelector('.datatable-external-top[data-for="' + key + '"]');
        if (!host) {
            host = document.createElement('div');
            host.className = 'datatable-external-top';
            host.dataset.for = key;
        }

        const table = wrapper.querySelector('table');
        const anchor = table ? (table.closest('.card') || wrapper) : wrapper;
        if (anchor.parentElement) {
            anchor.parentElement.insertBefore(host, anchor);
        }

        return host;
    }

    function moveTopControls(wrapper) {
        if (!wrapper) {
            return;
        }

        const top = wrapper.querySelector(':scope > .datatable-top');
        if (!top) {
            return;
        }

        const host = ensureHost(wrapper);
        if (!host) {
            return;
        }

        if (top.parentElement !== host) {
            host.appendChild(top);
        }
    }

    function scanAndMove() {
        document.querySelectorAll('.datatable-wrapper').forEach(function (wrapper) {
            moveTopControls(wrapper);
        });
    }

    function observeUpdates() {
        if (!document.body || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(function (mutations) {
            let shouldScan = false;
            for (const mutation of mutations) {
                if (mutation.type !== 'childList') {
                    continue;
                }
                if (mutation.addedNodes.length === 0) {
                    continue;
                }
                shouldScan = true;
                break;
            }

            if (shouldScan) {
                window.requestAnimationFrame(scanAndMove);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    window.addEventListener('load', function () {
        scanAndMove();
        window.setTimeout(scanAndMove, 120);
        window.setTimeout(scanAndMove, 320);
        observeUpdates();
    });
})();
