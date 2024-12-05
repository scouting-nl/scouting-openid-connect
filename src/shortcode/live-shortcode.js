// Get the input elements
const scoutingOIDCWidthInput = document.getElementById('scoutingOIDCWidthInput');
const scoutingOIDCHeightInput = document.getElementById('scoutingOIDCHeightInput');
const scoutingOIDCBackgroundColorInput = document.getElementById('scoutingOIDCBackgroundColorInput');
const scoutingOIDCTextColorInput = document.getElementById('scoutingOIDCTextColorInput');

// Get the shortcode text element
const scoutingOIDCButtonShortCode = document.getElementById('scoutingOIDCButtonShortCode');

// Get the button element and the image element
const scoutingOIDCButton = document.getElementById('scouting-oidc-login-div');
const scoutingOIDCLoginImg = document.getElementById('scouting-oidc-login-img');
const scoutingOIDCLoginLink = document.getElementById('scouting-oidc-login-link');

// Variable to hold a backup of the image element
let scoutingOIDCLoginImgBackup = null;

const updateValueWidth = (event) => {
    // Get the current shortcode text
    let currentText = scoutingOIDCButtonShortCode.textContent;

    // Check if width is a number above 120 and not empty
    let newWidth = event.target.value;
    if (newWidth === '' || isNaN(newWidth) || newWidth < 120) {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Use a regular expression to replace the width attribute value
    let updatedText = currentText.replace(/width="\d+"/, `width="${newWidth}"`);

    // Update the content of the element
    scoutingOIDCButtonShortCode.textContent = updatedText;

    // Update button width
    scoutingOIDCButton.style.width = `${newWidth}px`;

    // Check if the new width is less than 225
    if (newWidth < 225) {
        // Remove the image element if it exists
        if (scoutingOIDCLoginImg) {
            scoutingOIDCLoginImgBackup = scoutingOIDCLoginImg; // Backup the image
            scoutingOIDCLoginImg.remove();
        }
    }
    else {
        // Add the image element if it exists
        if (scoutingOIDCLoginImgBackup) {
            // Add the image element as the first child if it exists
            scoutingOIDCLoginLink.insertBefore(scoutingOIDCLoginImgBackup, scoutingOIDCLoginLink.firstChild);
            scoutingOIDCLoginImgBackup = null; // Clear the backup after restoring
        }
    }
};

const updateValueHeight = (event) => {
    // Get the current shortcode text
    let currentText = scoutingOIDCButtonShortCode.textContent;

    // check if width is a number above 40 and not empty
    let newHeight = event.target.value;
    if (newHeight === '' || isNaN(newHeight) || newHeight < 40) {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Use a regular expression to replace the height attribute value
    let updatedText = currentText.replace(/height="\d+"/, `height="${newHeight}"`);
    
    // Update the content of the element
    scoutingOIDCButtonShortCode.textContent = updatedText;

    // Update button height
    scoutingOIDCButton.style.height = `${newHeight}px`;
};

const updateValueBackgroundColor = (event) => {
    // Get the current shortcode text
    let currentText = scoutingOIDCButtonShortCode.textContent;

    // Check if background color is not empty
    let newBackgroundColor = event.target.value;
    if (newBackgroundColor === '') {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Use a regular expression to replace the background_color attribute value
    let updatedText = currentText.replace(/background_color="#\w+"/, `background_color="${newBackgroundColor}"`);
    
    // Update the content of the element
    scoutingOIDCButtonShortCode.textContent = updatedText;

    // Update button background color
    scoutingOIDCLoginLink.style.backgroundColor = newBackgroundColor;
}

const updateValueTextColor = (event) => {
    // Get the current shortcode text
    let currentText = scoutingOIDCButtonShortCode.textContent;

    // Check if text color is not empty
    let newTextColor = event.target.value;
    if (newTextColor === '') {
        // Change border color to red
        event.target.style.border = '2px solid red';
        return;
    }
    // Change border color to default
    event.target.style.border = '';

    // Use a regular expression to replace the text_color attribute value
    let updatedText = currentText.replace(/text_color="#\w+"/, `text_color="${newTextColor}"`);
    
    // Update the content of the element
    scoutingOIDCButtonShortCode.textContent = updatedText;

    // Update button text color
    scoutingOIDCLoginLink.style.color = newTextColor;
}

scoutingOIDCWidthInput.addEventListener('input', updateValueWidth);
scoutingOIDCHeightInput.addEventListener('input', updateValueHeight);
scoutingOIDCBackgroundColorInput.addEventListener('input', updateValueBackgroundColor);
scoutingOIDCTextColorInput.addEventListener('input', updateValueTextColor);

// Remove href attribute from the link
scoutingOIDCLoginLink.removeAttribute('href');