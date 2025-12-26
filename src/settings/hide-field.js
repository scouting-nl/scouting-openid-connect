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

    function showField(fieldTrClass, conditionFieldIds) {
        // Support single id or array of ids
        var ids = Array.isArray(conditionFieldIds) ? conditionFieldIds : [conditionFieldIds];

        // Get the field row to hide/show
        var fieldRow = document.querySelector(fieldTrClass);
        if (fieldRow === null) {
            return; // Field row not found
        }

        // Show if any condition field is checked
        var shouldShow = ids.some(function(id) {
            var field = document.getElementById(id);
            return field && field.checked;
        });

        fieldRow.style.display = shouldShow ? '' : 'none';
    }

    var select = document.getElementById('scouting_oidc_login_redirect');
    var checkBox1 = document.getElementById('scouting_oidc_user_address');
    var checkBox2 = document.getElementById('scouting_oidc_user_phone');
    if (select === null || checkBox1 === null || checkBox2 === null) {
        return; // Select or checkbox element not found
    }

    showField('.scouting-oidc-user-woocommerce-sync-tr', ['scouting_oidc_user_phone', 'scouting_oidc_user_address']);
    toggleCustomRedirect();

    select.addEventListener('change', toggleCustomRedirect);
    checkBox1.addEventListener('change', function() {
        showField('.scouting-oidc-user-woocommerce-sync-tr', ['scouting_oidc_user_phone', 'scouting_oidc_user_address']);
    });
    checkBox2.addEventListener('change', function() {
        showField('.scouting-oidc-user-woocommerce-sync-tr', ['scouting_oidc_user_phone', 'scouting_oidc_user_address']);
    });
});
