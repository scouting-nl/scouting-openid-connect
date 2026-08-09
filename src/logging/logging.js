/**
 * Synchronizes filters and date constraints on the logging page.
 *
 * @file  Defines filter synchronization on the logging page.
 * @since 2.4.0
 */

/**
 * Initializes logging filter controls after the DOM is ready.
 *
 * @since 2.4.0
 */
document.addEventListener('DOMContentLoaded', function () {
	// Stops initialization when the logging filter form is unavailable.
	var form = document.getElementById('scouting-oidc-logs-filter');
	if (!form) {
		return;
	}

	// Sets a stable default sorting state without submitting the filter form.
	var params = new URLSearchParams(window.location.search);
	var hasExplicitSort = params.has('orderby') || params.has('order');
	if (!hasExplicitSort) {
		var createdAtHeader = document.querySelector('th#created_at');
		if (createdAtHeader) {
			// Sets the default sorting parameters without reloading the page.
			params.set('orderby', 'id');
			params.set('order', 'desc');
			var newUrl = window.location.pathname + '?' + params.toString();
			window.history.replaceState({}, '', newUrl);

			// Marks the default sorting state for assistive technology.
			createdAtHeader.setAttribute('aria-sort', 'descending');

			// Updates the table-header classes to show descending sorting.
			createdAtHeader.classList.remove('sortable', 'asc');
			createdAtHeader.classList.add('sorted', 'desc');
		}
	}

	/**
	 * Builds a local datetime string for datetime-local controls.
	 *
	 * @since  2.4.0
	 * @return {string} Local datetime string.
	 */
	function nowLocalISO() {
		var date = new Date();

		/**
		 * Pads a number to two digits.
		 *
		 * @since  2.4.0
		 * @param  {number} number Number to pad.
		 * @return {string} Padded number.
		 */
		var pad = function (number) {
			return String(number).padStart(2, '0');
		};

		return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' +
			pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' +
			pad(date.getMinutes()) + ':' + pad(date.getSeconds());
	}

	/**
	 * Groups mirrored filter controls by their synchronization key.
	 *
	 * @since  2.4.0
	 * @return {Object} Controls grouped by synchronization key.
	 */
	function getSyncGroups() {
		var controls = form.querySelectorAll('[data-sync-key]');
		var groups = {};

		controls.forEach(function (control) {
			var key = control.getAttribute('data-sync-key');
			if (!key) {
				return;
			}

			if (!groups[key]) {
				groups[key] = [];
			}
			groups[key].push(control);
		});

		return groups;
	}

	/**
	 * Gets a normalized value from a filter control.
	 *
	 * @since  2.4.0
	 * @param  {HTMLElement} control Filter control.
	 * @return {string|string[]} Normalized control value.
	 */
	function getControlValue(control) {
		if (control.tagName === 'SELECT' && control.multiple) {
			return Array.from(control.options)
				.filter(function (option) {
					return option.selected;
				})
				.map(function (option) {
					return option.value;
				});
		}

		return control.value;
	}

	/**
	 * Applies a normalized value to a filter control.
	 *
	 * @since  2.4.0
	 * @param  {HTMLElement}     control Filter control.
	 * @param  {string|string[]} value   Normalized control value.
	 * @return {void} Does not return a value.
	 */
	function setControlValue(control, value) {
		if (control.tagName === 'SELECT' && control.multiple) {
			var selectedValues = Array.isArray(value) ? value : [];
			Array.from(control.options).forEach(function (option) {
				option.selected = selectedValues.indexOf(option.value) !== -1;
			});
			return;
		}

		control.value = typeof value === 'string' ? value : '';
	}

	/**
	 * Synchronizes controls that share a synchronization key.
	 *
	 * @since  2.4.0
	 * @param  {HTMLElement} sourceControl Changed filter control.
	 * @return {void} Does not return a value.
	 */
	function syncByKey(sourceControl) {
		var key = sourceControl.getAttribute('data-sync-key');
		if (!key || !syncGroups[key]) {
			return;
		}

		var sourceValue = getControlValue(sourceControl);
		syncGroups[key].forEach(function (control) {
			if (control === sourceControl) {
				return;
			}
			setControlValue(control, sourceValue);
		});
	}

	// Groups controls such as date_from and date_to by their synchronization key.
	var syncGroups = getSyncGroups();

	// Synchronizes matching controls while users type or change values.
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

	if (dateFromInputs.length === 0 || dateToInputs.length === 0) {
		return;
	}

	/**
	 * Clamps a datetime-local control to its current bounds.
	 *
	 * @since  2.4.0
	 * @param  {HTMLInputElement} input Datetime-local input control.
	 * @return {void} Does not return a value.
	 */
	function clampInput(input) {
		if (!input.value) {
			return;
		}
		if (input.min && input.value < input.min) {
			input.value = input.min;
		}
		if (input.max && input.value > input.max) {
			input.value = input.max;
		}
	}

	/**
	 * Applies matching minimum and maximum values to date filters.
	 *
	 * @since  2.4.0
	 * @return {void} Does not return a value.
	 */
	function applyDateConstraints() {
		var dateFromValue = dateFromInputs.length > 0 ? dateFromInputs[0].value : '';
		var dateToValue = dateToInputs.length > 0 ? dateToInputs[0].value : '';

		dateToInputs.forEach(function (input) {
			if (dateFromValue) {
				input.min = dateFromValue;
			} else {
				input.removeAttribute('min');
			}
			clampInput(input);
		});

		dateFromInputs.forEach(function (input) {
			if (dateToValue) {
				input.max = dateToValue;
			} else {
				input.removeAttribute('max');
			}
			clampInput(input);
		});
	}

	applyDateConstraints();

	// Re-applies date rules when a mirrored start-date control changes.
	dateFromInputs.forEach(function (input) {
		input.addEventListener('change', function () {
			applyDateConstraints();
		});
	});

	// Re-applies date rules when a mirrored end-date control changes.
	dateToInputs.forEach(function (input) {
		input.addEventListener('change', function () {
			applyDateConstraints();
		});
	});

	form.addEventListener('submit', function () {
		const now = nowLocalISO();

		// Prevents future timestamps in both mirrored date filters.
		dateFromInputs.forEach(function (input) {
			input.max = now;
			clampInput(input);
		});
		dateToInputs.forEach(function (input) {
			input.max = now;
			clampInput(input);
		});

		applyDateConstraints();
	});
});