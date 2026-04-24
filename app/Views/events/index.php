<?php
$pageTitle = 'Events';
?>

<div class="jt-events-screen">
<div class="page-header">
    <h1>Events</h1>
    <p class="muted">Calendar for appointments, cancellations, tasks due dates, and scheduled jobs.</p>
</div>

<div class="card index-card">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-calendar-days me-2"></i>Calendar</strong>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge" style="background:#b91c1c;">Appts</span>
                    <span class="badge" style="background:#2563eb;">Cancels</span>
                    <span class="badge" style="background:#16a34a;">Tasks</span>
                    <span class="badge" style="background:#ea580c;">Jobs</span>
                    <span class="badge" style="background:#7c3aed;">Quotes</span>
                </div>
            </div>
    </div>
    <div class="card-body">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-12 col-md-7">
                <label class="form-label fw-semibold" for="jt-event-q">Search</label>
                <input id="jt-event-q" class="form-control" placeholder="Search titles..." autocomplete="off" />
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label fw-semibold d-none d-md-block">Filters</label>
                <div class="jt-filter-wrap d-flex justify-content-end justify-content-md-start">
                    <button class="jt-filter-select w-100 w-md-auto d-inline-flex align-items-center justify-content-between gap-2"
                            type="button" id="jt-filter-toggle">
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="fas fa-sliders"></i>
                            <span class="small fw-semibold">Filters</span>
                        </span>
                        <i class="fas fa-chevron-down small" id="jt-filter-caret"></i>
                    </button>
                    <div class="jt-filter-panel card d-none" id="jt-filter-panel">
                        <div class="card-body py-2">
                            <div class="mb-2 small text-uppercase text-muted fw-bold">Types</div>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-type-appt" checked />
                                    <span class="small">Appointments</span>
                                </label>
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-type-cancel" />
                                    <span class="small">Cancellations</span>
                                </label>
                            </div>
                            <div class="mb-2 small text-uppercase text-muted fw-bold border-top pt-2">Sources</div>
                            <div class="d-flex flex-column gap-2">
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-src-events" checked />
                                    <span class="small">Custom Events</span>
                                </label>
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-src-tasks" checked />
                                    <span class="small">Tasks</span>
                                </label>
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-src-jobs" checked />
                                    <span class="small">Jobs</span>
                                </label>
                                <label class="form-check d-flex align-items-center gap-2 m-0">
                                    <input class="form-check-input" type="checkbox" id="jt-src-deliveries" checked />
                                    <span class="small">Deliveries</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-md-none jt-calendar-mobile-views mb-3" role="tablist" aria-label="Calendar view">
            <div class="jt-cal-view-segment d-flex rounded-3 overflow-hidden border border-primary border-opacity-25" role="group">
                <button type="button" class="jt-cal-view-btn jt-cal-view-btn--active flex-fill btn btn-sm border-0 rounded-0 py-2" data-jt-cal-view="timeGridDay" aria-pressed="true">Day</button>
                <button type="button" class="jt-cal-view-btn flex-fill btn btn-sm border-0 rounded-0 py-2 border-start border-primary border-opacity-25" data-jt-cal-view="listWeek">Week</button>
                <button type="button" class="jt-cal-view-btn flex-fill btn btn-sm border-0 rounded-0 py-2 border-start border-primary border-opacity-25" data-jt-cal-view="dayGridMonth">Month</button>
                <button type="button" class="jt-cal-view-btn flex-fill btn btn-sm border-0 rounded-0 py-2 border-start border-primary border-opacity-25" data-jt-cal-view="listMonth">List</button>
            </div>
        </div>
        <div id="jt-calendar" class="jt-calendar"></div>
    </div>
</div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" />
<link rel="stylesheet" href="<?= e(asset('css/jt-calendar.css')) ?>" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
  document.body.classList.add('jt-events-page');
  const el = document.getElementById('jt-calendar');
  if (!el || !window.FullCalendar) return;

  const debounce = (fn, ms = 250) => {
    let t = null;
    return (...args) => {
      if (t) window.clearTimeout(t);
      t = window.setTimeout(() => fn(...args), ms);
    };
  };

  const getSources = () => {
    const sources = [];
    if (document.getElementById('jt-src-events')?.checked) sources.push('events');
    if (document.getElementById('jt-src-tasks')?.checked) sources.push('tasks');
    if (document.getElementById('jt-src-jobs')?.checked) sources.push('jobs');
    if (document.getElementById('jt-src-deliveries')?.checked) sources.push('deliveries');
    return sources.join(',');
  };

  const getTypes = () => {
    const types = [];
    if (document.getElementById('jt-type-appt')?.checked) types.push('appointment');
    if (document.getElementById('jt-type-cancel')?.checked) types.push('cancellation');
    return types.join(',');
  };

  const getQuery = () => (document.getElementById('jt-event-q')?.value || '').trim();

  /** Show customer name next to the title in all calendar views (uses feed extendedProps). */
  const appendCustomerToEventTitle = (info) => {
    const customer = String(info.event.extendedProps?.customerName || '').trim();
    if (!customer) {
      return;
    }
    const fullTitle = String(info.event.title || '');
    if (fullTitle.includes(customer)) {
      return;
    }
    const titleEl =
      info.el.querySelector('.fc-list-event-title a') ||
      info.el.querySelector('.fc-event-title');
    if (!titleEl) {
      return;
    }
    const suffix = document.createElement('span');
    suffix.className = 'jt-cal-event-customer';
    suffix.textContent = ' · ' + customer;
    titleEl.appendChild(suffix);
  };

  const mobileMedia = window.matchMedia('(max-width: 767.98px)');
  const isMobile = mobileMedia.matches;
  const eventsScreen = document.querySelector('.jt-events-screen');

  const syncMobileViewButtons = () => {
    const group = document.querySelector('.jt-calendar-mobile-views');
    if (!group || !mobileMedia.matches) {
      return;
    }
    const type = calendar.view?.type || '';
    group.querySelectorAll('.jt-cal-view-btn').forEach((btn) => {
      const v = btn.getAttribute('data-jt-cal-view') || '';
      const on = v === type;
      btn.classList.toggle('jt-cal-view-btn--active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  const syncMobileMonthFullscreen = () => {
    if (!eventsScreen) {
      return;
    }
    const currentView = calendar?.view?.type || '';
    const isMonthView = currentView === 'dayGridMonth';
    eventsScreen.classList.toggle('jt-events-mobile-month-fullscreen', mobileMedia.matches && isMonthView);
  };

  let calendar;
  calendar = new FullCalendar.Calendar(el, {
    // Mobile: segmented Day / Week / Month / List; default Day on phones.
    initialView: isMobile ? 'timeGridDay' : 'dayGridMonth',
    height: 'auto',
    scrollTime: '07:00:00',
    slotMinTime: '05:00:00',
    slotMaxTime: '22:00:00',
    slotDuration: '00:30:00',
    headerToolbar: isMobile
      ? {
          left: 'prev,next',
          center: 'title',
          right: 'today',
        }
      : {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
    navLinks: false,
    nowIndicator: true,
    dayMaxEvents: true,
    eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
    /* Month / week *grid*: title only (iOS-style); time + list still show times */
    views: {
      dayGridMonth: {
        displayEventTime: false,
      },
      dayGridWeek: {
        displayEventTime: false,
      },
    },
    editable: false,
    eventStartEditable: false,
    eventDurationEditable: false,
    eventDidMount: appendCustomerToEventTitle,
    datesSet: () => {
      syncMobileViewButtons();
      syncMobileMonthFullscreen();
    },
    events: (info, success, failure) => {
      const params = new URLSearchParams();
      params.set('start', info.startStr);
      params.set('end', info.endStr);
      params.set('sources', getSources());
      const types = getTypes();
      if (types) params.set('types', types);
      const q = getQuery();
      if (q) params.set('q', q);
      fetch(<?= json_encode(url('/events/feed')) ?> + '?' + params.toString(), { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((data) => success(Array.isArray(data) ? data : []))
        .catch(() => failure());
    },
    eventClick: (arg) => {
      arg.jsEvent.preventDefault();
      const modal = document.getElementById('jt-event-quickview');
      const titleEl = document.getElementById('jt-event-quickview-title');
      const whenEl = document.getElementById('jt-event-quickview-when');
      const openBtn = document.getElementById('jt-event-quickview-open');
      if (!modal || !titleEl || !whenEl || !openBtn) {
        if (arg.event.url) {
          window.open(arg.event.url, '_blank');
        }
        return;
      }

      const title = arg.event.title || 'Event';
      titleEl.textContent = title;

      const customerWrap = document.getElementById('jt-event-quickview-customer-wrap');
      const customerEl = document.getElementById('jt-event-quickview-customer');
      const typeWrap = document.getElementById('jt-event-quickview-type-wrap');
      const typeEl = document.getElementById('jt-event-quickview-type');
      const ext = arg.event.extendedProps || {};
      const customerName = String(ext.customerName || '').trim();
      let eventType = String(ext.eventType || '').trim();
      if (eventType === '') {
        const rawId = String(arg.event.id || '');
        if (rawId.startsWith('task:')) eventType = 'Task';
        else if (rawId.startsWith('job:')) eventType = 'Job';
        else if (rawId.startsWith('delivery:')) eventType = 'Delivery';
        else eventType = 'Event';
      }
      if (customerWrap && customerEl) {
        if (customerName !== '') {
          customerEl.textContent = customerName;
          customerWrap.classList.remove('d-none');
        } else {
          customerEl.textContent = '';
          customerWrap.classList.add('d-none');
        }
      }
      if (typeWrap && typeEl) {
        typeEl.textContent = eventType;
        typeWrap.classList.remove('d-none');
      }

      const start = arg.event.start;
      const end = arg.event.end;
      const fmt = (d) => {
        if (!(d instanceof Date)) return '';
        return d.toLocaleString(undefined, {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: 'numeric',
          minute: '2-digit'
        });
      };
      let whenText = '';
      if (start && end) {
        whenText = fmt(start) + ' – ' + fmt(end);
      } else if (start) {
        whenText = fmt(start);
      } else {
        whenText = '—';
      }
      whenEl.textContent = whenText;

      const url = arg.event.url || '';
      openBtn.dataset.url = url;
      openBtn.disabled = url === '';

      modal.classList.remove('d-none');
      modal.classList.add('jt-event-quickview-open');
    },
  });

  calendar.render();

  document.querySelectorAll('.jt-cal-view-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const view = btn.getAttribute('data-jt-cal-view');
      if (view) {
        calendar.changeView(view);
      }
    });
  });
  syncMobileViewButtons();
  syncMobileMonthFullscreen();
  mobileMedia.addEventListener('change', () => {
    syncMobileViewButtons();
    syncMobileMonthFullscreen();
  });

  const refetch = () => calendar.refetchEvents();
  document.getElementById('jt-src-events')?.addEventListener('change', refetch);
  document.getElementById('jt-type-appt')?.addEventListener('change', refetch);
  document.getElementById('jt-type-cancel')?.addEventListener('change', refetch);
  document.getElementById('jt-src-tasks')?.addEventListener('change', refetch);
  document.getElementById('jt-src-jobs')?.addEventListener('change', refetch);
  document.getElementById('jt-src-deliveries')?.addEventListener('change', refetch);
  document.getElementById('jt-event-q')?.addEventListener('input', debounce(refetch, 250));

  const filterToggle = document.getElementById('jt-filter-toggle');
  const filterPanel = document.getElementById('jt-filter-panel');
  if (filterPanel) {
    const togglePanel = () => {
      filterPanel.classList.toggle('d-none');
      const caret = document.getElementById('jt-filter-caret');
      if (caret) {
        caret.classList.toggle('fa-chevron-up', !filterPanel.classList.contains('d-none'));
        caret.classList.toggle('fa-chevron-down', filterPanel.classList.contains('d-none'));
      }
    };
    if (filterToggle) {
      filterToggle.addEventListener('click', togglePanel);
    }

    // Start closed on all viewports
    filterPanel.classList.add('d-none');
    const caretInit = document.getElementById('jt-filter-caret');
    if (caretInit) {
      caretInit.classList.add('fa-chevron-down');
      caretInit.classList.remove('fa-chevron-up');
    }
  }

  const quickview = document.getElementById('jt-event-quickview');
  const quickviewBackdrop = document.getElementById('jt-event-quickview-backdrop');
  const quickviewClose = document.getElementById('jt-event-quickview-close');
  const quickviewOpen = document.getElementById('jt-event-quickview-open');
  if (quickview && quickviewBackdrop && quickviewClose && quickviewOpen) {
    const closeQuickview = () => {
      quickview.classList.add('d-none');
      quickview.classList.remove('jt-event-quickview-open');
    };
    quickviewBackdrop.addEventListener('click', closeQuickview);
    quickviewClose.addEventListener('click', closeQuickview);
    quickviewOpen.addEventListener('click', () => {
      const url = quickviewOpen.dataset.url || '';
      if (url) {
        window.open(url, '_blank');
      }
      closeQuickview();
    });
  }
});
</script>

<div id="jt-event-quickview" class="jt-event-quickview d-none">
    <div id="jt-event-quickview-backdrop" class="jt-event-quickview-backdrop"></div>
    <div class="jt-event-quickview-card card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="small text-uppercase text-muted fw-bold mb-1">Event</div>
                    <h2 class="h5 m-0" id="jt-event-quickview-title">Event</h2>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="jt-event-quickview-close">Close</button>
            </div>
            <div id="jt-event-quickview-customer-wrap" class="mb-2 d-none">
                <div class="small text-uppercase text-muted fw-bold mb-1">Customer</div>
                <div id="jt-event-quickview-customer" class="small"></div>
            </div>
            <div id="jt-event-quickview-type-wrap" class="mb-2 d-none">
                <div class="small text-uppercase text-muted fw-bold mb-1">Type</div>
                <div id="jt-event-quickview-type" class="small"></div>
            </div>
            <div class="mb-2">
                <div class="small text-uppercase text-muted fw-bold mb-1">When</div>
                <div id="jt-event-quickview-when" class="small">—</div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-primary" id="jt-event-quickview-open">Open Event</button>
            </div>
        </div>
    </div>
</div>
