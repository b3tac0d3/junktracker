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

    const persistSchedule = async (jobId, startDate, endDate = null) => {
        const payload = new URLSearchParams();
        payload.set('csrf_token', csrfToken);
        payload.set('job_id', String(jobId));
        payload.set('scheduled_date', toSqlDateTime(startDate));
        payload.set('end_date', toSqlDateTime(endDate));

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

    const parseTime = (value) => {
        const match = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec((value || '').trim());
        if (!match) {
            return null;
        }

        return {
            hour: parseInt(match[1], 10),
            minute: parseInt(match[2], 10),
        };
    };

    const dateAtTime = (baseDate, timeText) => {
        const parsed = parseTime(timeText);
        if (!parsed || !(baseDate instanceof Date) || Number.isNaN(baseDate.getTime())) {
            return null;
        }

        return new Date(
            baseDate.getFullYear(),
            baseDate.getMonth(),
            baseDate.getDate(),
            parsed.hour,
            parsed.minute,
            0,
            0
        );
    };

    const formatInputTime = (date) => {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '09:00';
        }
        return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const scheduleWindowModal = () => {
        const id = 'scheduleWindowModal';
        let modalEl = document.getElementById(id);
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.id = id;
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Set Schedule Time</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted mb-3" id="scheduleWindowLabel"></p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="scheduleStartTimeInput">Start</label>
                                    <input type="time" class="form-control" id="scheduleStartTimeInput" />
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="scheduleEndTimeInput">End</label>
                                    <input type="time" class="form-control" id="scheduleEndTimeInput" />
                                </div>
                            </div>
                            <div class="small text-danger mt-2 d-none" id="scheduleWindowError">End time must be after start time.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="scheduleWindowSaveBtn">Save Time</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modalEl);
        }

        return modalEl;
    };

    const askScheduleWindow = (baseDate, titleText) => new Promise((resolve) => {
        const modalEl = scheduleWindowModal();
        const label = modalEl.querySelector('#scheduleWindowLabel');
        const startInput = modalEl.querySelector('#scheduleStartTimeInput');
        const endInput = modalEl.querySelector('#scheduleEndTimeInput');
        const saveButton = modalEl.querySelector('#scheduleWindowSaveBtn');
        const errorEl = modalEl.querySelector('#scheduleWindowError');

        if (!label || !startInput || !endInput || !saveButton || !errorEl) {
            resolve(null);
            return;
        }

        const defaultStart = new Date(baseDate);
        if (defaultStart.getHours() === 0 && defaultStart.getMinutes() === 0) {
            defaultStart.setHours(9, 0, 0, 0);
        }
        const defaultEnd = new Date(defaultStart.getTime() + (60 * 60 * 1000));

        label.textContent = `Set start and end for ${titleText || 'this job'} on ${defaultStart.toLocaleDateString()}.`;
        startInput.value = formatInputTime(defaultStart);
        endInput.value = formatInputTime(defaultEnd);
        errorEl.classList.add('d-none');

        const modal = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
        if (!modal) {
            resolve(null);
            return;
        }

        let closed = false;
        const finalize = (value) => {
            if (closed) {
                return;
            }
            closed = true;
            saveButton.removeEventListener('click', onSave);
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            resolve(value);
        };

        const onHidden = () => finalize(null);
        const onSave = () => {
            const startDate = dateAtTime(defaultStart, startInput.value);
            const endDate = dateAtTime(defaultStart, endInput.value);
            if (!startDate || !endDate || endDate <= startDate) {
                errorEl.classList.remove('d-none');
                return;
            }

            modal.hide();
            finalize({ start: startDate, end: endDate });
        };

        saveButton.addEventListener('click', onSave);
        modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
        modal.show();
    });

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
                await persistSchedule(jobId, info.event.start, info.event.end);
                calendar.refetchEvents();
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

            const scheduleWindow = await askScheduleWindow(info.event.start, info.event.title);
            if (!scheduleWindow) {
                info.event.remove();
                return;
            }

            info.event.setDates(scheduleWindow.start, scheduleWindow.end, { allDay: false });

            try {
                await persistSchedule(jobId, scheduleWindow.start, scheduleWindow.end);
                if (dragged && dragged.parentElement) {
                    dragged.parentElement.removeChild(dragged);
                    updateCount();
                }
                info.event.remove();
                calendar.refetchEvents();
                showToast('Job added to calendar.');
            } catch (error) {
                info.event.remove();
                showToast(error instanceof Error ? error.message : 'Unable to schedule this job.', 'error');
            }
        },
    });

    calendar.render();
});
