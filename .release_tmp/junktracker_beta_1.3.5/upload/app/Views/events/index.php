<?php
$pageTitle = 'Events';
?>

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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="jt-calendar" class="jt-calendar"></div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" />
<link rel="stylesheet" href="<?= e(asset('css/jt-calendar.css')) ?>" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
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
    return sources.join(',');
  };

  const getTypes = () => {
    const types = [];
    if (document.getElementById('jt-type-appt')?.checked) types.push('appointment');
    if (document.getElementById('jt-type-cancel')?.checked) types.push('cancellation');
    return types.join(',');
  };

  const getQuery = () => (document.getElementById('jt-event-q')?.value || '').trim();

  const mobileMedia = window.matchMedia('(max-width: 767.98px)');
  const isMobile = mobileMedia.matches;

  const eventDatesInRange = (event) => {
    const dates = [];
    if (!event.start) {
      return dates;
    }

    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : new Date(event.start);

    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);

    if (event.allDay && event.end) {
      end.setDate(end.getDate() - 1);
    }

    for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
      dates.push(cursor.toISOString().slice(0, 10));
    }

    return dates;
  };

  const refreshEmptyDayPlaceholders = (calendar) => {
    const viewType = calendar.view?.type || '';
    const placeholders = el.querySelectorAll('.jt-calendar-empty-day');
    placeholders.forEach((node) => node.remove());

    if (!viewType.startsWith('dayGrid') || !mobileMedia.matches) {
      return;
    }

    const eventDays = new Set();
    calendar.getEvents().forEach((event) => {
      eventDatesInRange(event).forEach((dateKey) => eventDays.add(dateKey));
    });

    el.querySelectorAll('.fc-daygrid-day[data-date]').forEach((cell) => {
      const dateKey = cell.getAttribute('data-date');
      const isOtherMonth = cell.classList.contains('fc-day-other');
      const eventsWrap = cell.querySelector('.fc-daygrid-day-events');

      if (!dateKey || isOtherMonth || !eventsWrap || eventDays.has(dateKey)) {
        return;
      }

      const placeholder = document.createElement('div');
      placeholder.className = 'jt-calendar-empty-day';
      placeholder.textContent = 'No events';
      eventsWrap.appendChild(placeholder);
    });
  };

  const calendar = new FullCalendar.Calendar(el, {
    initialView: isMobile ? 'dayGridWeek' : 'dayGridMonth',
    height: 'auto',
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
    editable: false,
    eventStartEditable: false,
    eventDurationEditable: false,
    datesSet: () => refreshEmptyDayPlaceholders(calendar),
    eventsSet: () => refreshEmptyDayPlaceholders(calendar),
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

  const refetch = () => calendar.refetchEvents();
  document.getElementById('jt-src-events')?.addEventListener('change', refetch);
  document.getElementById('jt-type-appt')?.addEventListener('change', refetch);
  document.getElementById('jt-type-cancel')?.addEventListener('change', refetch);
  document.getElementById('jt-src-tasks')?.addEventListener('change', refetch);
  document.getElementById('jt-src-jobs')?.addEventListener('change', refetch);
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
