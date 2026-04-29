const DEFAULT_SHORTCODE_VALUES = Object.freeze({
    width: '250',
    height: '40',
    backgroundColor: '#4caf50',
    textColor: '#ffffff',
    redirectBack: false,
});

const MIN_DIMENSIONS = Object.freeze({
    width: 120,
    height: 40,
});

document.addEventListener('DOMContentLoaded', initializeLiveShortcodeEditor);

function initializeLiveShortcodeEditor() {
    const elements = getLiveShortcodeElements();

    if (!hasRequiredElements(elements)) {
        return;
    }

    const buttonTextLabels = getButtonTextLabels();
    const state = {
        loginButtonText: buttonTextLabels.loginButtonText,
        logoutButtonText: buttonTextLabels.logoutButtonText,
        lastValidWidth: getInitialDimensionValue(elements.widthInput.value, MIN_DIMENSIONS.width, DEFAULT_SHORTCODE_VALUES.width),
        lastValidHeight: getInitialDimensionValue(elements.heightInput.value, MIN_DIMENSIONS.height, DEFAULT_SHORTCODE_VALUES.height),
        redirectBack: false,
        loginImgBackup: null,
    };

    initializeEditorInputs(elements, state);
    registerEventListeners(elements, state);

    updateShortcodeText(elements, state);
    updateDemoLogoutPreview(elements, state);
    updateLogoVisibility(elements, state);

    elements.loginLink.removeAttribute('href');
}

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
        button: previewContainer ? previewContainer.querySelector('.scouting-oidc-login-div, #scouting-oidc-login-div') : null,
        loginLink: previewContainer ? previewContainer.querySelector('.scouting-oidc-login-link, #scouting-oidc-login-link') : null,
        loginText: previewContainer ? previewContainer.querySelector('.scouting-oidc-login-text, #scouting-oidc-login-text') : null,
    };
}

function hasRequiredElements(elements) {
    return Boolean(
        elements.widthInput
            && elements.heightInput
            && elements.backgroundColorInput
            && elements.textColorInput
            && elements.hideLogoInput
            && elements.demoLogoutInput
            && elements.redirectBackInput
            && elements.shortcodeText
            && elements.linkShortcodeText
            && elements.button
            && elements.loginLink
            && elements.loginText
    );
}

function getButtonTextLabels() {
    const localizedText = window.scoutingOIDCLiveShortcodeL10n || {};

    return {
        loginButtonText: localizedText.loginText || 'Login with Scouts Online',
        logoutButtonText: localizedText.logoutText || 'Logout',
    };
}

function initializeEditorInputs(elements, state) {
    elements.hideLogoInput.checked = /hide_logo="true"/.test(elements.shortcodeText.textContent);
    elements.demoLogoutInput.checked = false;
    elements.redirectBackInput.checked = false;
    state.redirectBack = elements.redirectBackInput.checked;
}

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

function handleHideLogoChange(elements, state) {
    updateShortcodeText(elements, state);
    updateLogoVisibility(elements, state);
}

function updateDemoLogoutPreview(elements, state) {
    elements.loginText.textContent = elements.demoLogoutInput.checked
        ? state.logoutButtonText
        : state.loginButtonText;
}

function updateShortcodeText(elements, state) {
    elements.shortcodeText.textContent = buildShortcodeText(elements, state);
    updateLinkShortcodeText(elements, state);
}

function updateLinkShortcodeText(elements, state) {
    if (!elements.linkShortcodeText) {
        return;
    }

    elements.linkShortcodeText.textContent = buildLinkShortcodeText(state);
}

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
    const effectiveBackgroundColor = getEffectiveColorValue(elements.backgroundColorInput.value, DEFAULT_SHORTCODE_VALUES.backgroundColor);
    const effectiveTextColor = getEffectiveColorValue(elements.textColorInput.value, DEFAULT_SHORTCODE_VALUES.textColor);

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

function buildLinkShortcodeText(state) {
    return state.redirectBack ? '[scouting_oidc_link redirect_back="true"]' : '[scouting_oidc_link]';
}

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
    const currentLogo = elements.loginLink.querySelector('.scouting-oidc-login-img, #scouting-oidc-login-img, [id^="scouting-oidc-login-img-"]');

    if (shouldHideLogo) {
        if (currentLogo) {
            // Keep a reference so the image can be restored when conditions allow it.
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

function getValidDimensionValue(rawValue, minValue) {
    const parsedValue = parseInt(rawValue, 10);

    if (rawValue === '' || Number.isNaN(parsedValue) || parsedValue < minValue) {
        return null;
    }

    return String(parsedValue);
}

function getInitialDimensionValue(rawValue, minValue, defaultValue) {
    const validValue = getValidDimensionValue(rawValue, minValue);

    return validValue === null ? defaultValue : validValue;
}

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

function getEffectiveColorValue(rawValue, defaultValue) {
    const validColor = getValidHexColor(rawValue);

    return validColor === null ? defaultValue : validColor;
}

function getValidHexColor(rawValue) {
    const normalizedColor = String(rawValue || '').trim().toLowerCase();

    if (!/^#[0-9a-f]{6}$/.test(normalizedColor)) {
        return null;
    }

    return normalizedColor;
}

function setInputValidationState(inputElement, isValid) {
    inputElement.style.border = isValid ? '' : '2px solid red';
}