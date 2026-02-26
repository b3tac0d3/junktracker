(function () {
  "use strict";

  const mobileBreakpoint = window.matchMedia("(max-width: 767.98px)");
  const cards = Array.from(
    document.querySelectorAll('.card.jt-filter-card, .card[data-mobile-filter="true"]')
  ).filter((card) => String(card.getAttribute('data-mobile-filter') || '').toLowerCase() !== 'false');
  let filterIndex = 0;

  const isGetForm = (form) => {
    if (!form || form.tagName.toLowerCase() !== "form") {
      return false;
    }

    const method = String(form.getAttribute("method") || "get").toLowerCase();
    if (method !== "get") {
      return false;
    }

    const submitButtons = Array.from(
      form.querySelectorAll('button[type="submit"], input[type="submit"]')
    );
    if (submitButtons.length === 0) {
      return false;
    }

    const submitText = submitButtons
      .map((button) => String(button.textContent || button.value || "").trim().toLowerCase())
      .join(" ");

    if (/(apply|search|filter|run|go)/.test(submitText)) {
      return true;
    }

    if (submitText.includes("load")) {
      return false;
    }

    return Boolean(
      form.querySelector('input[name="q"], input[type="search"], input[type="date"], select')
    );
  };

  const getDirectChildByClass = (element, className) =>
    Array.from(element.children).find((child) => child.classList.contains(className)) || null;

  const getFilterForm = (card) => {
    const forms = [];
    if (card.matches("form")) {
      forms.push(card);
    }
    forms.push(...Array.from(card.querySelectorAll("form")));
    return forms.find(isGetForm) || null;
  };

  const ensureHeader = (card, body) => {
    let header = getDirectChildByClass(card, "card-header");
    if (header) {
      header.classList.add("mobile-filter-header");
      return header;
    }

    header = document.createElement("div");
    header.className = "card-header mobile-filter-header";
    header.innerHTML = '<span class="mobile-filter-title"><i class="fas fa-filter me-1"></i>Filters</span>';
    card.insertBefore(header, body);
    return header;
  };

  const ensureToggleButton = (header) => {
    let actionWrap = header.querySelector(".mobile-filter-header-actions");
    if (!actionWrap) {
      actionWrap = document.createElement("div");
      actionWrap.className = "mobile-filter-header-actions";
      header.appendChild(actionWrap);
    }

    let button = actionWrap.querySelector(".mobile-filter-toggle");
    if (!button) {
      button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-sm btn-outline-primary mobile-filter-toggle";
      actionWrap.appendChild(button);
    }

    return button;
  };

  const updateToggleState = (card, body, button) => {
    const isOpen = body.classList.contains("show");
    card.classList.toggle("mobile-filter-open", isOpen);
    button.setAttribute("aria-expanded", isOpen ? "true" : "false");
    button.innerHTML = isOpen
      ? '<i class="fas fa-chevron-up me-1"></i>Hide Filters'
      : '<i class="fas fa-chevron-down me-1"></i>Show Filters';
  };

  cards.forEach((card) => {
    if (card.classList.contains("mobile-filter-initialized")) {
      return;
    }

    const filterForm = getFilterForm(card);
    if (!filterForm) {
      return;
    }

    const body =
      getDirectChildByClass(card, "card-body") || card.querySelector(".card-body");
    if (!body) {
      return;
    }

    filterIndex += 1;
    const collapseId = body.id || `mobileFilterCollapse${filterIndex}`;
    body.id = collapseId;
    body.classList.add("collapse", "mobile-filter-collapse");

    const header = ensureHeader(card, body);
    const toggleButton = ensureToggleButton(header);
    const collapse = window.bootstrap?.Collapse
      ? window.bootstrap.Collapse.getOrCreateInstance(body, { toggle: false })
      : null;

    const showCollapse = () => {
      if (collapse) {
        collapse.show();
      } else {
        body.classList.add("show");
      }
      updateToggleState(card, body, toggleButton);
    };

    const hideCollapse = () => {
      if (collapse) {
        collapse.hide();
      } else {
        body.classList.remove("show");
      }
      updateToggleState(card, body, toggleButton);
    };

    toggleButton.addEventListener("click", () => {
      if (body.classList.contains("show")) {
        hideCollapse();
      } else {
        showCollapse();
      }
    });

    body.addEventListener("shown.bs.collapse", () =>
      updateToggleState(card, body, toggleButton)
    );
    body.addEventListener("hidden.bs.collapse", () =>
      updateToggleState(card, body, toggleButton)
    );

    card.classList.add("mobile-filter-card", "mobile-filter-initialized");
    if (mobileBreakpoint.matches) {
      hideCollapse();
    } else {
      showCollapse();
    }
  });

  if (mobileBreakpoint && typeof mobileBreakpoint.addEventListener === "function") {
    mobileBreakpoint.addEventListener("change", (event) => {
      document.querySelectorAll(".mobile-filter-card").forEach((card) => {
        const body = card.querySelector(".mobile-filter-collapse");
        const button = card.querySelector(".mobile-filter-toggle");
        if (!body || !button) {
          return;
        }

        if (event.matches) {
          body.classList.remove("show");
        } else {
          body.classList.add("show");
        }

        updateToggleState(card, body, button);
      });
    });
  }
})();
