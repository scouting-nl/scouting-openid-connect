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

    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const submitBtn = document.getElementById('post-query-submit');

    if (!dateFrom || !dateTo || !submitBtn) { return; }

    // On page load, set the min/max attributes to enforce valid date ranges
    if (dateFrom.value) dateTo.min = dateFrom.value;
    if (dateTo.value) dateFrom.max = dateTo.value;

    function clampInput(input) {
        if (!input.value) return;
        if (input.min && input.value < input.min) input.value = input.min;
        if (input.max && input.value > input.max) input.value = input.max;
    }

    // When the "from" date changes, update the min of the "to" date and clamp if necessary
    dateFrom.addEventListener('change', () => {
        if (dateFrom.value) dateTo.min = dateFrom.value;
        else dateTo.removeAttribute('min');
        clampInput(dateTo);
    });

    // When the "to" date changes, update the max of the "from" date and clamp if necessary
    dateTo.addEventListener('change', () => {
        if (dateTo.value) dateFrom.max = dateTo.value;
        else dateFrom.removeAttribute('max');
        clampInput(dateFrom);
    });

    // On form submit, ensure the max attributes are set to the current date/time to prevent future dates
    submitBtn.form.addEventListener('submit', function () {
        // Set the max attributes to the current date/time to prevent future dates from being submitted
        const now = nowLocalISO();
        dateFrom.max = now;
        dateTo.max = now;

        // Clamp the input values to ensure they are within the valid range before submission
        clampInput(dateFrom);
        clampInput(dateTo);
    });
});