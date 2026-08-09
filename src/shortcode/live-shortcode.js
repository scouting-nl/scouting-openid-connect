/**
 * Provides a live preview and shortcode builder for login buttons.
 *
 * @file  Defines the live login-button shortcode editor.
 * @since 1.0.0
 */

/**
 * Defines default values for the button shortcode.
 *
 * @since  2.4.0
 * @type   {Object}
 */
const DEFAULT_SHORTCODE_VALUES = Object.freeze({
	width: '250',
	height: '40',
	backgroundColor: '#4caf50',
	textColor: '#ffffff',
	redirectBack: false,
});

/**
 * Defines the minimum supported button dimensions.
 *
 * @since  2.4.0
 * @type   {Object}
 */
const MIN_DIMENSIONS = Object.freeze({
	width: 120,
	height: 40,
});

document.addEventListener('DOMContentLoaded', initializeLiveShortcodeEditor);

/**
 * Initializes the live shortcode editor after the DOM is ready.
 *
 * @since  2.4.0
 * @return {void} Does not return a value.
 */
function initializeLiveShortcodeEditor() {
	const elements = getLiveShortcodeElements();

	if (!hasRequiredElements(elements)) {
		return;
	}

	const buttonTextLabels = getButtonTextLabels();
	const state = {
		loginButtonText: buttonTextLabels.loginButtonText,
		logoutButtonText: buttonTextLabels.logoutButtonText,
		lastValidWidth: getInitialDimensionValue(
			elements.widthInput.value,
			MIN_DIMENSIONS.width,
			DEFAULT_SHORTCODE_VALUES.width
		),
		lastValidHeight: getInitialDimensionValue(
			elements.heightInput.value,
			MIN_DIMENSIONS.height,
			DEFAULT_SHORTCODE_VALUES.height
		),
		redirectBack: false,
		loginImgBackup: null,
	};

	initializeEditorInputs(elements, state);
	registerEventListeners(elements, state);

	updateShortcodeText(elements, state);
	updateLinkShortcodeText(elements, state);
	updateDemoLogoutPreview(elements, state);
	updateLogoVisibility(elements, state);

	elements.loginLink.removeAttribute('href');
}

/**
 * Collects the elements used by the live shortcode editor.
 *
 * @since  2.4.0
 * @return {Object} Editor elements and preview controls.
 */
function getLiveShortcodeElements() {
	const previewContainer = document.getElementById('scoutingOIDCLivePreviewContainer');

	return {
		widthInput: document.getElementById('scoutingOIDCWidthInput'),
		heightInput: document.getElementById('scoutingOIDCHeightInput'),
		backgroundColorInput: document.getElementById('scoutingOIDCBackgroundColorInput'),
		textColorInput: document.getElementById('scoutingOIDCTextColorInput'),
		hideLogoInput: document.getElementById('scoutingOIDCHideLogoInput'),
		demoLogoutInput: document.getElementById('scoutingOIDCDemoLogoutInput'),
		redirectBackInput: document.getElementById('scoutingOIDCRedirectBackInput'),
		shortcodeText: document.getElementById('scoutingOIDCButtonShortCode'),
		linkShortcodeText: document.getElementById('scoutingOIDCLinkShortCode'),
		button: previewContainer ? previewContainer.querySelector(
			'.scouting-oidc-login-div, #scouting-oidc-login-div'
		) : null,
		loginLink: previewContainer ? previewContainer.querySelector(
			'.scouting-oidc-login-link, #scouting-oidc-login-link'
		) : null,
		loginText: previewContainer ? previewContainer.querySelector(
			'.scouting-oidc-login-text, #scouting-oidc-login-text'
		) : null,
	};
}

/**
 * Determines whether all required editor elements are available.
 *
 * @since  2.4.0
 * @param  {Object}  elements Editor elements.
 * @return {boolean} Whether all required elements are available.
 */
function hasRequiredElements(elements) {
	return Boolean(
		elements.widthInput &&
		elements.heightInput &&
		elements.backgroundColorInput &&
		elements.textColorInput &&
		elements.hideLogoInput &&
		elements.demoLogoutInput &&
		elements.redirectBackInput &&
		elements.shortcodeText &&
		elements.linkShortcodeText &&
		elements.button &&
		elements.loginLink &&
		elements.loginText
	);
}

/**
 * Gets localized button text with fallback values.
 *
 * @since  2.4.0
 * @return {Object} Login and logout button labels.
 */
function getButtonTextLabels() {
	const localizedText = window.scoutingOIDCLiveShortcodeL10n || {};

	return {
		loginButtonText: localizedText.loginText || 'Login with Scouts Online',
		logoutButtonText: localizedText.logoutText || 'Logout',
	};
}

/**
 * Initializes inputs from the current shortcode text.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function initializeEditorInputs(elements, state) {
	elements.hideLogoInput.checked = /hide_logo="true"/.test(elements.shortcodeText.textContent);
	elements.demoLogoutInput.checked = false;
	elements.redirectBackInput.checked = false;
	state.redirectBack = elements.redirectBackInput.checked;
}

/**
 * Registers live-preview input event listeners.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function registerEventListeners(elements, state) {
	elements.widthInput.addEventListener('input', (event) => {
		handleWidthInput(event, elements, state);
	});
	elements.heightInput.addEventListener('input', (event) => {
		handleHeightInput(event, elements, state);
	});
	elements.backgroundColorInput.addEventListener('input', (event) => {
		handleBackgroundColorInput(event, elements, state);
	});
	elements.textColorInput.addEventListener('input', (event) => {
		handleTextColorInput(event, elements, state);
	});
	elements.hideLogoInput.addEventListener('change', () => {
		handleHideLogoChange(elements, state);
	});
	elements.demoLogoutInput.addEventListener('change', () => {
		updateDemoLogoutPreview(elements, state);
	});
	elements.redirectBackInput.addEventListener('change', () => {
		state.redirectBack = elements.redirectBackInput.checked;
		updateShortcodeText(elements, state);
		updateLinkShortcodeText(elements, state);
	});
}

/**
 * Handles changes to the preview button width.
 *
 * @since  2.4.0
 * @param  {Event}  event    Input event.
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function handleWidthInput(event, elements, state) {
	const validWidth = getValidDimensionValue(event.target.value, MIN_DIMENSIONS.width);

	if (validWidth === null) {
		setInputValidationState(event.target, false);
		return;
	}

	setInputValidationState(event.target, true);
	state.lastValidWidth = validWidth;
	elements.button.style.width = `${validWidth}px`;

	updateShortcodeText(elements, state);
	updateLogoVisibility(elements, state);
}

/**
 * Handles changes to the preview button height.
 *
 * @since  2.4.0
 * @param  {Event}  event    Input event.
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function handleHeightInput(event, elements, state) {
	const validHeight = getValidDimensionValue(event.target.value, MIN_DIMENSIONS.height);

	if (validHeight === null) {
		setInputValidationState(event.target, false);
		return;
	}

	setInputValidationState(event.target, true);
	state.lastValidHeight = validHeight;
	elements.button.style.height = `${validHeight}px`;

	updateShortcodeText(elements, state);
}

/**
 * Handles changes to the preview background color.
 *
 * @since  2.4.0
 * @param  {Event}  event    Input event.
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function handleBackgroundColorInput(event, elements, state) {
	const validBackgroundColor = getValidHexColor(event.target.value);

	if (validBackgroundColor === null) {
		setInputValidationState(event.target, false);
		return;
	}

	setInputValidationState(event.target, true);
	elements.loginLink.style.backgroundColor = validBackgroundColor;

	updateShortcodeText(elements, state);
}

/**
 * Handles changes to the preview text color.
 *
 * @since  2.4.0
 * @param  {Event}  event    Input event.
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function handleTextColorInput(event, elements, state) {
	const validTextColor = getValidHexColor(event.target.value);

	if (validTextColor === null) {
		setInputValidationState(event.target, false);
		return;
	}

	setInputValidationState(event.target, true);
	elements.loginLink.style.color = validTextColor;

	updateShortcodeText(elements, state);
}

/**
 * Handles changes to the hide-logo input.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function handleHideLogoChange(elements, state) {
	updateShortcodeText(elements, state);
	updateLogoVisibility(elements, state);
}

/**
 * Updates the preview label for the demo logout mode.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function updateDemoLogoutPreview(elements, state) {
	elements.loginText.textContent = elements.demoLogoutInput.checked ?
		state.logoutButtonText :
		state.loginButtonText;
}

/**
 * Updates the button shortcode preview text.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function updateShortcodeText(elements, state) {
	elements.shortcodeText.textContent = buildShortcodeText(elements, state);
}

/**
 * Updates the link shortcode preview text.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function updateLinkShortcodeText(elements, state) {
	if (!elements.linkShortcodeText) {
		return;
	}

	elements.linkShortcodeText.textContent = buildLinkShortcodeText(state);
}

/**
 * Builds the login button shortcode from the current editor state.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {string} Generated shortcode text.
 */
function buildShortcodeText(elements, state) {
	const shortcodeAttributes = [];
	const effectiveWidth = getEffectiveDimensionValue(
		elements.widthInput.value,
		MIN_DIMENSIONS.width,
		state.lastValidWidth,
		DEFAULT_SHORTCODE_VALUES.width
	);
	const effectiveHeight = getEffectiveDimensionValue(
		elements.heightInput.value,
		MIN_DIMENSIONS.height,
		state.lastValidHeight,
		DEFAULT_SHORTCODE_VALUES.height
	);
	const effectiveBackgroundColor = getEffectiveColorValue(
		elements.backgroundColorInput.value,
		DEFAULT_SHORTCODE_VALUES.backgroundColor
	);
	const effectiveTextColor = getEffectiveColorValue(
		elements.textColorInput.value,
		DEFAULT_SHORTCODE_VALUES.textColor
	);

	if (effectiveWidth !== DEFAULT_SHORTCODE_VALUES.width) {
		shortcodeAttributes.push(`width="${effectiveWidth}"`);
	}

	if (effectiveHeight !== DEFAULT_SHORTCODE_VALUES.height) {
		shortcodeAttributes.push(`height="${effectiveHeight}"`);
	}

	if (effectiveBackgroundColor !== DEFAULT_SHORTCODE_VALUES.backgroundColor) {
		shortcodeAttributes.push(`background_color="${effectiveBackgroundColor}"`);
	}

	if (effectiveTextColor !== DEFAULT_SHORTCODE_VALUES.textColor) {
		shortcodeAttributes.push(`text_color="${effectiveTextColor}"`);
	}

	if (elements.hideLogoInput.checked) {
		shortcodeAttributes.push('hide_logo="true"');
	}

	if (state.redirectBack) {
		shortcodeAttributes.push('redirect_back="true"');
	}

	if (shortcodeAttributes.length === 0) {
		return '[scouting_oidc_button]';
	}

	return `[scouting_oidc_button ${shortcodeAttributes.join(' ')}]`;
}

/**
 * Builds the login link shortcode from the current editor state.
 *
 * @since  2.4.0
 * @param  {Object} state Editor state.
 * @return {string} Generated link shortcode text.
 */
function buildLinkShortcodeText(state) {
	return state.redirectBack ?
		'[scouting_oidc_link redirect_back="true"]' :
		'[scouting_oidc_link]';
}

/**
 * Shows or restores the Scouting logo in the button preview.
 *
 * @since  2.4.0
 * @param  {Object} elements Editor elements.
 * @param  {Object} state    Editor state.
 * @return {void} Does not return a value.
 */
function updateLogoVisibility(elements, state) {
	const effectiveWidth = parseInt(
		getEffectiveDimensionValue(
			elements.widthInput.value,
			MIN_DIMENSIONS.width,
			state.lastValidWidth,
			DEFAULT_SHORTCODE_VALUES.width
		),
		10
	);
	const shouldHideLogo = elements.hideLogoInput.checked || effectiveWidth < 225;
	const currentLogo = elements.loginLink.querySelector(
		'.scouting-oidc-login-img, #scouting-oidc-login-img, [id^="scouting-oidc-login-img-"]'
	);

	if (shouldHideLogo) {
		if (currentLogo) {
			// Retains the image so it can be restored when conditions allow it.
			state.loginImgBackup = currentLogo;
			currentLogo.remove();
		}
		return;
	}

	if (!currentLogo && state.loginImgBackup) {
		elements.loginLink.insertBefore(state.loginImgBackup, elements.loginLink.firstChild);
		state.loginImgBackup = null;
	}
}

/**
 * Validates a dimension value against a minimum.
 *
 * @since  2.4.0
 * @param  {string}      rawValue Raw input value.
 * @param  {number}      minValue Minimum accepted value.
 * @return {string|null} Validated dimension or null.
 */
function getValidDimensionValue(rawValue, minValue) {
	const parsedValue = parseInt(rawValue, 10);

	if (rawValue === '' || Number.isNaN(parsedValue) || parsedValue < minValue) {
		return null;
	}

	return String(parsedValue);
}

/**
 * Gets the initial dimension value using a fallback when needed.
 *
 * @since  2.4.0
 * @param  {string} rawValue     Raw input value.
 * @param  {number} minValue     Minimum accepted value.
 * @param  {string} defaultValue Default dimension value.
 * @return {string} Initial dimension value.
 */
function getInitialDimensionValue(rawValue, minValue, defaultValue) {
	const validValue = getValidDimensionValue(rawValue, minValue);

	return validValue === null ? defaultValue : validValue;
}

/**
 * Gets a valid dimension using current, fallback, and default values.
 *
 * @since  2.4.0
 * @param  {string} rawValue      Raw input value.
 * @param  {number} minValue      Minimum accepted value.
 * @param  {string} fallbackValue Most recent valid dimension value.
 * @param  {string} defaultValue  Default dimension value.
 * @return {string} Effective dimension value.
 */
function getEffectiveDimensionValue(rawValue, minValue, fallbackValue, defaultValue) {
	const validValue = getValidDimensionValue(rawValue, minValue);

	if (validValue !== null) {
		return validValue;
	}

	if (typeof fallbackValue === 'string' && fallbackValue !== '') {
		return fallbackValue;
	}

	return defaultValue;
}

/**
 * Gets a valid color using a fallback when needed.
 *
 * @since  2.4.0
 * @param  {string} rawValue     Raw input value.
 * @param  {string} defaultValue Default color value.
 * @return {string} Effective color value.
 */
function getEffectiveColorValue(rawValue, defaultValue) {
	const validColor = getValidHexColor(rawValue);

	return validColor === null ? defaultValue : validColor;
}

/**
 * Validates and normalizes a hexadecimal color value.
 *
 * @since  2.4.0
 * @param  {string}      rawValue Raw input value.
 * @return {string|null} Normalized color or null.
 */
function getValidHexColor(rawValue) {
	const normalizedColor = String(rawValue || '').trim().toLowerCase();

	if (! /^#[0-9a-f]{6}$/.test(normalizedColor)) {
		return null;
	}

	return normalizedColor;
}

/**
 * Updates the visual validation state of an input element.
 *
 * @since  2.4.0
 * @param  {HTMLElement} inputElement Input element to update.
 * @param  {boolean}     isValid       Whether the current value is valid.
 * @return {void} Does not return a value.
 */
function setInputValidationState(inputElement, isValid) {
	inputElement.style.border = isValid ? '' : '2px solid red';
}