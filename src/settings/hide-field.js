document.addEventListener('DOMContentLoaded', function() {
    function toggleCustomRedirect() {
        var select = document.getElementById('scouting_oidc_login_redirect');
        var customRow = document.querySelector('.scouting-oidc-custom-redirect-tr');
        if (customRow === null) {
            return; // Custom row not found
        }

        if (select.value === 'custom') {
            customRow.style.display = ''; // show
        } else {
            customRow.style.display = 'none'; // hide
        }
    }

    function showField(fieldTrClass, conditionFieldId) {
        // Get the condition value input checkbox
        var conditionField = document.getElementById(conditionFieldId);
        
        // Get the field row to hide/show
        var fieldRow = document.querySelector(fieldTrClass);
        if (fieldRow === null) {
            return; // Field row not found
        }

        // Show or hide based on the condition field's checked status
        if (conditionField.checked) {
            fieldRow.style.display = ''; // show
        } else {
            fieldRow.style.display = 'none'; // hide
        }
    }

    var select = document.getElementById('scouting_oidc_login_redirect');
    var checkBox = document.getElementById('scouting_oidc_user_address');
    if (select === null || checkBox === null) {
        return; // Select or checkbox element not found
    }

    showField('.scouting-oidc-user-woocommerce-sync-tr', 'scouting_oidc_user_address');
    toggleCustomRedirect();

    select.addEventListener('change', toggleCustomRedirect);
    checkBox.addEventListener('change', function() {
        showField('.scouting-oidc-user-woocommerce-sync-tr', 'scouting_oidc_user_address');
    });
});
