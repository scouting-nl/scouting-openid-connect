document.addEventListener('DOMContentLoaded', () => {
    // Get the input elements
    const scoutingOIDCWidthInput = document.getElementById('scoutingOIDCWidthInput');
    const scoutingOIDCHeightInput = document.getElementById('scoutingOIDCHeightInput');
    const scoutingOIDCBackgroundColorInput = document.getElementById('scoutingOIDCBackgroundColorInput');
    const scoutingOIDCTextColorInput = document.getElementById('scoutingOIDCTextColorInput');
    const scoutingOIDCHideLogoInput = document.getElementById('scoutingOIDCHideLogoInput');
    const scoutingOIDCDemoLogoutInput = document.getElementById('scoutingOIDCDemoLogoutInput');

    // Get the shortcode text element
    const scoutingOIDCButtonShortCode = document.getElementById('scoutingOIDCButtonShortCode');

    // Get the button element and the image element
    const scoutingOIDCButton = document.getElementById('scouting-oidc-login-div');
    const scoutingOIDCLoginImg = document.getElementById('scouting-oidc-login-img');
    const scoutingOIDCLoginLink = document.getElementById('scouting-oidc-login-link');
    const scoutingOIDCLoginText = document.getElementById('scouting-oidc-login-text');

    // Check if all required elements are present
    if (!scoutingOIDCWidthInput || !scoutingOIDCHeightInput || !scoutingOIDCBackgroundColorInput || !scoutingOIDCTextColorInput || !scoutingOIDCHideLogoInput || !scoutingOIDCDemoLogoutInput || !scoutingOIDCButtonShortCode || !scoutingOIDCButton || !scoutingOIDCLoginLink || !scoutingOIDCLoginText) {
        // Required elements are missing, exit the script
        return;
    }

    const loginButtonText =
        (window.scoutingOIDCLiveShortcodeL10n && window.scoutingOIDCLiveShortcodeL10n.loginText)
            ? window.scoutingOIDCLiveShortcodeL10n.loginText
            : 'Login with Scouts Online';
    const logoutButtonText =
        (window.scoutingOIDCLiveShortcodeL10n && window.scoutingOIDCLiveShortcodeL10n.logoutText)
            ? window.scoutingOIDCLiveShortcodeL10n.logoutText
            : 'Logout';

    const defaultShortcodeValues = {
        width: '250',
        height: '40',
        backgroundColor: '#4caf50',
        textColor: '#ffffff',
    };

    const buildShortcodeText = () => {
    const shortcodeAttributes = [];

    if (scoutingOIDCWidthInput.value !== defaultShortcodeValues.width) {
        shortcodeAttributes.push(`width="${scoutingOIDCWidthInput.value}"`);
    }

    if (scoutingOIDCHeightInput.value !== defaultShortcodeValues.height) {
        shortcodeAttributes.push(`height="${scoutingOIDCHeightInput.value}"`);
    }

    if (scoutingOIDCBackgroundColorInput.value.toLowerCase() !== defaultShortcodeValues.backgroundColor) {
        shortcodeAttributes.push(`background_color="${scoutingOIDCBackgroundColorInput.value}"`);
    }

    if (scoutingOIDCTextColorInput.value.toLowerCase() !== defaultShortcodeValues.textColor) {
        shortcodeAttributes.push(`text_color="${scoutingOIDCTextColorInput.value}"`);
    }

    if (scoutingOIDCHideLogoInput.checked) {
        shortcodeAttributes.push('hide_logo="true"');
    }

    if (shortcodeAttributes.length === 0) {
        return '[scouting_oidc_button]';
    }

    return `[scouting_oidc_button ${shortcodeAttributes.join(' ')}]`;
    };

    const updateShortcodeText = () => {
    scoutingOIDCButtonShortCode.textContent = buildShortcodeText();
    };

    // Variable to hold a backup of the image element
    let scoutingOIDCLoginImgBackup = null;

    const updateLogoVisibility = () => {
    const currentWidth = parseInt(scoutingOIDCWidthInput.value, 10);
    const hideLogo = scoutingOIDCHideLogoInput.checked || (!isNaN(currentWidth) && currentWidth < 225);

    if (hideLogo) {
        // Keep a reference so the image can be restored when conditions allow it.
        if (scoutingOIDCLoginImg && scoutingOIDCLoginImg.parentElement) {
            scoutingOIDCLoginImgBackup = scoutingOIDCLoginImg;
            scoutingOIDCLoginImg.remove();
        }
        return;
    }

    if (scoutingOIDCLoginImgBackup && !scoutingOIDCLoginLink.contains(scoutingOIDCLoginImgBackup)) {
        scoutingOIDCLoginLink.insertBefore(scoutingOIDCLoginImgBackup, scoutingOIDCLoginLink.firstChild);
        scoutingOIDCLoginImgBackup = null;
    }
    };

    const updateValueWidth = (event) => {
    // Check if width is a number above 120 and not empty
    let newWidth = event.target.value;
    if (newWidth === '' || isNaN(newWidth) || newWidth < 120) {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Update button width
    scoutingOIDCButton.style.width = `${newWidth}px`;

    // Rebuild shortcode and hide attributes that are still defaults.
    updateShortcodeText();

    // Logo visibility depends on both width and hide-logo checkbox.
    updateLogoVisibility();
    };

    const updateValueHeight = (event) => {
    // check if width is a number above 40 and not empty
    let newHeight = event.target.value;
    if (newHeight === '' || isNaN(newHeight) || newHeight < 40) {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Update button height
    scoutingOIDCButton.style.height = `${newHeight}px`;

    // Rebuild shortcode and hide attributes that are still defaults.
    updateShortcodeText();
    };

    const updateValueBackgroundColor = (event) => {
    // Check if background color is not empty
    let newBackgroundColor = event.target.value;
    if (newBackgroundColor === '') {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Update button background color
    scoutingOIDCLoginLink.style.backgroundColor = newBackgroundColor;

    // Rebuild shortcode and hide attributes that are still defaults.
    updateShortcodeText();
    }

    const updateValueTextColor = (event) => {
    // Check if text color is not empty
    let newTextColor = event.target.value;
    if (newTextColor === '') {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Update button text color
    scoutingOIDCLoginLink.style.color = newTextColor;

    // Rebuild shortcode and hide attributes that are still defaults.
    updateShortcodeText();
    }

    const updateValueHideLogo = (event) => {
    // Rebuild shortcode and hide attributes that are still defaults.
    updateShortcodeText();

    // Update preview logo state immediately.
    updateLogoVisibility();
    }

    const updateDemoLogoutPreview = (event) => {
    scoutingOIDCLoginText.textContent = event.target.checked ? logoutButtonText : loginButtonText;
    }

    scoutingOIDCWidthInput.addEventListener('input', updateValueWidth);
    scoutingOIDCHeightInput.addEventListener('input', updateValueHeight);
    scoutingOIDCBackgroundColorInput.addEventListener('input', updateValueBackgroundColor);
    scoutingOIDCTextColorInput.addEventListener('input', updateValueTextColor);
    scoutingOIDCHideLogoInput.addEventListener('change', updateValueHideLogo);
    scoutingOIDCDemoLogoutInput.addEventListener('change', updateDemoLogoutPreview);

    // Keep checkbox state aligned with the initial shortcode text.
    scoutingOIDCHideLogoInput.checked = /hide_logo="true"/.test(scoutingOIDCButtonShortCode.textContent);
    scoutingOIDCDemoLogoutInput.checked = false;
    updateShortcodeText();
    updateDemoLogoutPreview({ target: scoutingOIDCDemoLogoutInput });
    updateLogoVisibility();

    // Remove href attribute from the link
    scoutingOIDCLoginLink.removeAttribute('href');
});