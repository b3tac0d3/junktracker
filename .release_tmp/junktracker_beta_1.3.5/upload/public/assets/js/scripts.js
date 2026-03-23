/*!
    * Start Bootstrap - SB Admin v7.0.7 (https://startbootstrap.com/template/sb-admin)
    * Copyright 2013-2023 Start Bootstrap
    * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
    */
    // 
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Uncomment Below to persist sidebar toggle between refreshes
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sb-sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

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
            navLinks.forEach((l) => l.classList.remove('active'));
            best.classList.add('active');
            best.setAttribute('aria-current', 'page');
        }
    }

});
