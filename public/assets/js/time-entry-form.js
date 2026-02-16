window.addEventListener('DOMContentLoaded', () => {
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const minutesInput = document.getElementById('minutes_worked');
    const payRateInput = document.getElementById('pay_rate');
    const totalPaidInput = document.getElementById('total_paid');

    if (!minutesInput || !payRateInput || !totalPaidInput) {
        return;
    }

    const parseTimeToMinutes = (value) => {
        if (!value || !value.includes(':')) {
            return null;
        }

        const [h, m] = value.split(':');
        const hours = Number.parseInt(h, 10);
        const minutes = Number.parseInt(m, 10);

        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return null;
        }

        return (hours * 60) + minutes;
    };

    const computeMinutesFromTimes = () => {
        if (!startInput || !endInput) {
            return null;
        }

        const start = parseTimeToMinutes(startInput.value);
        const end = parseTimeToMinutes(endInput.value);

        if (start === null || end === null) {
            return null;
        }

        let diff = end - start;
        if (diff < 0) {
            diff += 24 * 60;
        }

        return diff > 0 ? diff : null;
    };

    const computeTotalPaid = () => {
        const rate = Number.parseFloat(payRateInput.value);
        const minutes = Number.parseInt(minutesInput.value, 10);

        if (Number.isNaN(rate) || Number.isNaN(minutes) || minutes <= 0 || rate < 0) {
            return null;
        }

        return ((rate * minutes) / 60).toFixed(2);
    };

    const updateMinutesFromTimeRange = () => {
        const computedMinutes = computeMinutesFromTimes();
        if (computedMinutes !== null && minutesInput.value.trim() === '') {
            minutesInput.value = String(computedMinutes);
        }
    };

    const updateTotalWhenEmpty = () => {
        if (totalPaidInput.value.trim() !== '') {
            return;
        }

        const total = computeTotalPaid();
        if (total !== null) {
            totalPaidInput.value = total;
        }
    };

    if (startInput && endInput) {
        startInput.addEventListener('change', () => {
            updateMinutesFromTimeRange();
            updateTotalWhenEmpty();
        });

        endInput.addEventListener('change', () => {
            updateMinutesFromTimeRange();
            updateTotalWhenEmpty();
        });
    }

    minutesInput.addEventListener('input', updateTotalWhenEmpty);
    payRateInput.addEventListener('input', updateTotalWhenEmpty);

    updateMinutesFromTimeRange();
    updateTotalWhenEmpty();
});
