document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    var hasExplicitSort = params.has('orderby') || params.has('order');
    if (!hasExplicitSort) {
        var createdAtHeader = document.querySelector('th#created_at');
        if (createdAtHeader) {
            // Set default sorting to created_at descending by updating the URL parameters without reloading the page
            params.set('orderby', 'id');
            params.set('order', 'desc');
            var newUrl = window.location.pathname + '?' + params.toString();
            window.history.replaceState({}, '', newUrl);

            // Update the aria-sort attribute to indicate the default sorting state for accessibility
            createdAtHeader.setAttribute('aria-sort', 'descending');

            // Update class to reflect default sorting state (remove sortable and add sorted and desc)
            createdAtHeader.classList.remove('sortable', 'asc');
            createdAtHeader.classList.add('sorted', 'desc');
        }
    }

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