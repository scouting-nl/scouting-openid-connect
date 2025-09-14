document.addEventListener('DOMContentLoaded', function() {
    function toggleCustomRedirect() {
        var select = document.getElementById('scouting_oidc_login_redirect');
        var customRow = document.querySelector('.scouting-oidc-custom-redirect-tr');
        if (select.value === 'custom') {
            customRow.style.display = ''; // show
        } else {
            customRow.style.display = 'none'; // hide
        }
    }

    toggleCustomRedirect();

    var select = document.getElementById('scouting_oidc_login_redirect');
    select.addEventListener('change', toggleCustomRedirect);
});
