window.addEventListener('DOMContentLoaded', () => {
    const isGeoAvailable = Boolean(window.isSecureContext && navigator.geolocation);

    const assignValues = (form, payload) => {
        const latInput = form.querySelector('input[name="geo_lat"]');
        const lngInput = form.querySelector('input[name="geo_lng"]');
        const accuracyInput = form.querySelector('input[name="geo_accuracy"]');
        const capturedAtInput = form.querySelector('input[name="geo_captured_at"]');

        if (!latInput || !lngInput || !accuracyInput || !capturedAtInput) {
            return;
        }

        latInput.value = payload.lat ?? '';
        lngInput.value = payload.lng ?? '';
        accuracyInput.value = payload.accuracy ?? '';
        capturedAtInput.value = payload.capturedAt ?? '';
    };

    const continueSubmit = (form, submitter) => {
        form.dataset.geoResolved = '1';
        delete form.dataset.geoInFlight;

        if (typeof form.requestSubmit === 'function') {
            if (submitter instanceof HTMLElement) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
            return;
        }

        form.submit();
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.classList.contains('js-punch-geo-form')) {
            return;
        }

        const requiredSubmitValue = (form.getAttribute('data-punch-geo-submit-value') || '').trim();
        if (requiredSubmitValue !== '') {
            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            const submitValue = submitter && 'value' in submitter ? String(submitter.value || '').trim() : '';
            if (submitValue !== requiredSubmitValue) {
                return;
            }
        }

        if (form.dataset.geoResolved === '1') {
            delete form.dataset.geoResolved;
            return;
        }

        const latInput = form.querySelector('input[name="geo_lat"]');
        const lngInput = form.querySelector('input[name="geo_lng"]');
        const accuracyInput = form.querySelector('input[name="geo_accuracy"]');
        const capturedAtInput = form.querySelector('input[name="geo_captured_at"]');
        if (!latInput || !lngInput || !accuracyInput || !capturedAtInput) {
            return;
        }

        if (!isGeoAvailable) {
            capturedAtInput.value = new Date().toISOString();
            return;
        }

        if (form.dataset.geoInFlight === '1') {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        form.dataset.geoInFlight = '1';
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                assignValues(form, {
                    lat: Number(position.coords.latitude).toFixed(7),
                    lng: Number(position.coords.longitude).toFixed(7),
                    accuracy: Number(position.coords.accuracy).toFixed(2),
                    capturedAt: new Date().toISOString(),
                });
                continueSubmit(form, submitter);
            },
            () => {
                assignValues(form, {
                    lat: '',
                    lng: '',
                    accuracy: '',
                    capturedAt: new Date().toISOString(),
                });
                continueSubmit(form, submitter);
            },
            {
                enableHighAccuracy: true,
                timeout: 2500,
                maximumAge: 60000,
            }
        );
    }, true);
});
