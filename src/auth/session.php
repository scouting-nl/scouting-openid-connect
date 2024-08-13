<?php
// Start the session if it is not already started
function scouting_oidc_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Destroy the session
function scouting_oidc_end_session() {
    session_destroy();
}
?>