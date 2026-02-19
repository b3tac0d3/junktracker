window.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('jobsScheduleCalendar');
    const eventsUrl = document.getElementById('schedule_events_url')?.value || '';
    const updateUrl = document.getElementById('schedule_update_url')?.value || '';
    const jobBaseUrl = document.getElementById('schedule_job_base_url')?.value || '/jobs/';
    const statusScope = document.getElementById('schedule_status_scope')?.value || 'dispatch';
    const csrfToken = document.getElementById('schedule_csrf_token')?.value || '';
    const unscheduledList = document.getElementById('schedule_unscheduled_list');

    if (!calendarEl || !eventsUrl || !updateUrl || !window.FullCalendar) {
        return;
    }

    const pad = (value) => String(value).padStart(2, '0');
    const toSqlDateTime = (date) => {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        return [
            date.getFullYear(),
            '-',
            pad(date.getMonth() + 1),
            '-',
            pad(date.getDate()),
            ' ',
            pad(date.getHours()),
            ':',
            pad(date.getMinutes()),
            ':00',
        ].join('');
    };

    const updateCount = () => {
        const badge = document.querySelector('.schedule-unscheduled-count');
        if (!badge || !unscheduledList) {
            return;
        }

        badge.textContent = String(unscheduledList.querySelectorAll('.schedule-unscheduled-item').length);
    };

    const showToast = (message, tone = 'success') => {
        const cleanMessage = (message || '').toString().trim();
        if (cleanMessage === '') {
            return;
        }

        const id = 'scheduleBoardToast';
        let toast = document.getElementById(id);
        if (!toast) {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '2000';
            container.innerHTML = `
                <div id="${id}" class="toast align-items-center border-0 text-white" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body"></div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(container);
            toast = document.getElementById(id);
        }

        if (!toast) {
            return;
        }

        toast.classList.remove('bg-success', 'bg-danger', 'bg-warning');
        toast.classList.add(tone === 'error' ? 'bg-danger' : 'bg-success');
        const body = toast.querySelector('.toast-body');
        if (body) {
            body.textContent = cleanMessage;
        }

        const toastInstance = window.bootstrap?.Toast?.getOrCreateInstance(toast);
        toastInstance?.show();
    };

    const persistSchedule = async (jobId, startDate) => {
        const payload = new URLSearchParams();
        payload.set('csrf_token', csrfToken);
        payload.set('job_id', String(jobId));
        payload.set('scheduled_date', toSqlDateTime(startDate));

        const response = await fetch(updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                Accept: 'application/json',
            },
            body: payload.toString(),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error((data.message || 'Unable to update schedule.').toString());
        }

        return data;
    };

    if (unscheduledList && window.FullCalendar.Draggable) {
        new window.FullCalendar.Draggable(unscheduledList, {
            itemSelector: '.schedule-unscheduled-item',
            eventData: (eventEl) => {
                const jobId = parseInt(eventEl.dataset.jobId || '0', 10) || 0;
                const name = (eventEl.dataset.jobName || '').trim() || `Job #${jobId}`;
                return {
                    id: String(jobId),
                    title: `#${jobId} - ${name}`,
                    duration: '01:00',
                    allDay: false,
                    extendedProps: {
                        job_id: jobId,
                    },
                };
            },
        });
    }

    const calendar = new window.FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        height: 'auto',
        editable: true,
        eventDurationEditable: false,
        eventStartEditable: true,
        droppable: true,
        nowIndicator: true,
        eventSources: [
            async (fetchInfo, successCallback, failureCallback) => {
                const query = new URLSearchParams({
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr,
                    status_scope: statusScope,
                });

                try {
                    const response = await fetch(`${eventsUrl}?${query.toString()}`, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) {
                        throw new Error('Unable to load schedule.');
                    }

                    const data = await response.json();
                    successCallback(Array.isArray(data.events) ? data.events : []);
                } catch (error) {
                    failureCallback(error);
                    showToast('Could not load calendar events.', 'error');
                }
            },
        ],
        eventClick: (info) => {
            const jobId = parseInt((info.event.extendedProps?.job_id || info.event.id || '0').toString(), 10) || 0;
            if (jobId > 0) {
                info.jsEvent.preventDefault();
                window.location.href = `${jobBaseUrl}${jobId}`;
            }
        },
        eventDrop: async (info) => {
            const jobId = parseInt((info.event.extendedProps?.job_id || info.event.id || '0').toString(), 10) || 0;
            if (jobId <= 0 || !info.event.start) {
                info.revert();
                return;
            }

            try {
                await persistSchedule(jobId, info.event.start);
                showToast('Schedule updated.');
            } catch (error) {
                info.revert();
                showToast(error instanceof Error ? error.message : 'Unable to update schedule.', 'error');
            }
        },
        eventReceive: async (info) => {
            const jobId = parseInt((info.event.extendedProps?.job_id || info.event.id || '0').toString(), 10) || 0;
            const dragged = info.draggedEl;

            if (jobId <= 0 || !info.event.start) {
                info.event.remove();
                return;
            }

            try {
                await persistSchedule(jobId, info.event.start);
                if (dragged && dragged.parentElement) {
                    dragged.parentElement.removeChild(dragged);
                    updateCount();
                }
                showToast('Job added to calendar.');
            } catch (error) {
                info.event.remove();
                showToast(error instanceof Error ? error.message : 'Unable to schedule this job.', 'error');
            }
        },
    });

    calendar.render();
});
