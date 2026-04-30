document.addEventListener('DOMContentLoaded', function () {
    // The logging filters live inside this form. If it is missing, nothing on this page
    // should be initialized because all behavior below depends on form controls.
    var form = document.getElementById('scouting-oidc-logs-filter');
    if (!form) { return; }

    // Ensure sorting URL params exist for a stable default sort state.
    // This only updates the browser URL and table header classes; it does not submit the form.
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

    // Build a local datetime string in the format expected by datetime-local controls.
    // Example: 2026-03-14T18:42:09
    function nowLocalISO() {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    // Collect all mirrored controls and group them by data-sync-key.
    // Each key typically has two controls: one in the top tablenav and one in the bottom tablenav.
    function getSyncGroups() {
        var controls = form.querySelectorAll('[data-sync-key]');
        var groups = {};

        controls.forEach(function (control) {
            var key = control.getAttribute('data-sync-key');
            if (!key) { return; }

            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(control);
        });

        return groups;
    }

    // Read a control value in a normalized way so we can mirror values across control types.
    // Multi-select controls return an array of selected option values.
    function getControlValue(control) {
        if (control.tagName === 'SELECT' && control.multiple) {
            return Array.from(control.options)
                .filter(function (option) { return option.selected; })
                .map(function (option) { return option.value; });
        }
        // For input controls and single selects, return the scalar string value.
        return control.value;
    }

    // Apply a normalized value back to a control.
    // For multi-select controls we update selected state option-by-option.
    function setControlValue(control, value) {
        if (control.tagName === 'SELECT' && control.multiple) {
            var selectedValues = Array.isArray(value) ? value : [];
            Array.from(control.options).forEach(function (option) {
                option.selected = selectedValues.indexOf(option.value) !== -1;
            });
            return;
        }

        // For scalar controls, ensure we always assign a string.
        control.value = typeof value === 'string' ? value : '';
    }

    // Mirror the changed value from one control to all sibling controls with the same sync key.
    function syncByKey(sourceControl) {
        var key = sourceControl.getAttribute('data-sync-key');
        if (!key || !syncGroups[key]) { return; }

        var sourceValue = getControlValue(sourceControl);
        syncGroups[key].forEach(function (control) {
            if (control === sourceControl) { return; }
            setControlValue(control, sourceValue);
        });
    }

    // Build a map like { date_from: [topInput, bottomInput], level: [topSelect, bottomSelect], ... }.
    var syncGroups = getSyncGroups();

    // Keep top and bottom controls synchronized while typing/changing.
    // Listening to both input and change covers text-like inputs and select interactions.
    Object.keys(syncGroups).forEach(function (key) {
        syncGroups[key].forEach(function (control) {
            control.addEventListener('input', function () {
                syncByKey(control);
            });
            control.addEventListener('change', function () {
                syncByKey(control);
            });
        });
    });

    var dateFromInputs = syncGroups.date_from || [];
    var dateToInputs = syncGroups.date_to || [];

    // Date constraint logic depends on both date controls being present.
    if (dateFromInputs.length === 0 || dateToInputs.length === 0) { return; }

    // Clamp a datetime-local value to the current min/max bounds if it falls outside them.
    function clampInput(input) {
        if (!input.value) return;
        if (input.min && input.value < input.min) input.value = input.min;
        if (input.max && input.value > input.max) input.value = input.max;
    }

    // Enforce cross-field constraints:
    // - date_to cannot be earlier than date_from
    // - date_from cannot be later than date_to
    // The first control in each group is used as source of truth because top/bottom are synced.
    function applyDateConstraints() {
        var dateFromValue = dateFromInputs.length > 0 ? dateFromInputs[0].value : '';
        var dateToValue = dateToInputs.length > 0 ? dateToInputs[0].value : '';

        dateToInputs.forEach(function (input) {
            if (dateFromValue) input.min = dateFromValue;
            else input.removeAttribute('min');
            clampInput(input);
        });

        dateFromInputs.forEach(function (input) {
            if (dateToValue) input.max = dateToValue;
            else input.removeAttribute('max');
            clampInput(input);
        });
    }

    // On page load, set min/max attributes to enforce valid date ranges.
    applyDateConstraints();

    // Re-apply date rules whenever either mirrored "from" control changes.
    dateFromInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            applyDateConstraints();
        });
    });

    // Re-apply date rules whenever either mirrored "to" control changes.
    dateToInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            applyDateConstraints();
        });
    });

    // On form submit, prevent future dates and clamp values on all mirrored controls.
    form.addEventListener('submit', function () {
        const now = nowLocalISO();

        // Prevent selecting/submitting future timestamps.
        dateFromInputs.forEach(function (input) {
            input.max = now;
            clampInput(input);
        });
        dateToInputs.forEach(function (input) {
            input.max = now;
            clampInput(input);
        });

        // Final consistency pass before submission.
        applyDateConstraints();
    });
});