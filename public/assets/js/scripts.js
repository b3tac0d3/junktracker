/*!
    * Start Bootstrap - SB Admin v7.0.7 (https://startbootstrap.com/template/sb-admin)
    * Copyright 2013-2023 Start Bootstrap
    * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
    */
    // 
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    const SIDEBAR_DESKTOP_MIN = 1200;
    let sidebarDesktopViewport = window.innerWidth >= SIDEBAR_DESKTOP_MIN;

    const isSidebarDesktopViewport = () => window.innerWidth >= SIDEBAR_DESKTOP_MIN;

    const resetSidebarToDefault = () => {
        document.body.classList.remove('sb-sidenav-toggled');
    };

    const syncSidebarForViewport = () => {
        const isDesktop = isSidebarDesktopViewport();
        if (isDesktop === sidebarDesktopViewport) {
            return;
        }
        sidebarDesktopViewport = isDesktop;
        resetSidebarToDefault();
    };

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

    if (!isSidebarDesktopViewport()) {
        resetSidebarToDefault();
    }

    window.addEventListener('resize', syncSidebarForViewport);

    document.addEventListener('click', (event) => {
        if (isSidebarDesktopViewport()) {
            return;
        }
        if (!document.body.classList.contains('sb-sidenav-toggled')) {
            return;
        }

        const nav = document.querySelector('#layoutSidenav_nav');
        const toggle = document.querySelector('#sidebarToggle');
        if (nav?.contains(event.target) || toggle?.contains(event.target)) {
            return;
        }

        resetSidebarToDefault();
    });

    // Mark current sidenav link active (keeps UI consistent)
    const normalizePath = (value) => {
        try {
            const url = new URL(value, window.location.origin);
            let path = url.pathname || '/';
            if (path.length > 1 && path.endsWith('/')) {
                path = path.slice(0, -1);
            }
            return path;
        } catch {
            return '';
        }
    };

    const currentPath = normalizePath(window.location.href);
    const navLinks = Array.from(document.querySelectorAll('#sidenavAccordion .sb-sidenav-menu .nav-link[href]'));
    if (currentPath && navLinks.length > 0) {
        let best = null;
        let bestLen = -1;

        navLinks.forEach((link) => {
            const href = link.getAttribute('href') || '';
            if (href.startsWith('#') || href.startsWith('javascript:') || href.trim() === '') {
                return;
            }

            const linkPath = normalizePath(href);
            if (!linkPath) {
                return;
            }

            if (currentPath === linkPath || (linkPath !== '/' && currentPath.startsWith(linkPath + '/'))) {
                if (linkPath.length > bestLen) {
                    best = link;
                    bestLen = linkPath.length;
                }
            }
        });

        if (best) {
            navLinks.forEach((l) => {
                l.classList.remove('active');
                l.removeAttribute('aria-current');
            });
            best.classList.add('active');
            best.setAttribute('aria-current', 'page');

            const collapse = best.closest('.collapse');
            if (collapse && collapse.id) {
                collapse.classList.add('show');
                const toggle = document.querySelector(`#sidenavAccordion [data-bs-target="#${collapse.id}"]`);
                if (toggle) {
                    toggle.classList.remove('collapsed');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
        }
    }

    document.querySelectorAll('.index-card-tabs[data-detail-tabs]').forEach((tabList) => {
        const root = tabList.closest('.index-card') || tabList.closest('section') || document.body;
        root.querySelectorAll('form[method="post"]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.querySelector('input[name="return_tab"]')) {
                    return;
                }
                const tab = new URLSearchParams(window.location.search).get('tab');
                if (!tab) {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'return_tab';
                input.value = tab;
                form.appendChild(input);
            });
        });
    });

});
