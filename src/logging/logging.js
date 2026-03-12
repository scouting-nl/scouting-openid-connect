document.addEventListener('DOMContentLoaded', function () {
    function nowLocalISO() {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    var dateFrom = document.getElementById('date_from');
    var dateTo = document.getElementById('date_to');

    if (!dateFrom || !dateTo) { return; }

    // Enforce date_to max = current time
    dateTo.max = nowLocalISO();
    // Enforce date_from max = date_to value (or now if not set)
    dateFrom.max = dateTo.value || dateTo.max;

    dateFrom.addEventListener('change', function () {
        dateTo.min = dateFrom.value;
        // Push date_to forward if it is now before date_from
        if (dateTo.value && dateTo.value < dateFrom.value) {
            dateTo.value = dateFrom.value;
        }
    });

    dateTo.addEventListener('change', function () {
        // Cap date_to to current time if user somehow exceeds it
        var now = nowLocalISO();
        if (dateTo.value > now) {
            dateTo.value = now;
        }
        dateFrom.max = dateTo.value || now;
        // Pull date_from back if it is now after date_to
        if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
            dateFrom.value = dateTo.value;
        }
    });
});
