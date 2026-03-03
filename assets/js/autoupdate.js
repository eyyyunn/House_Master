/**
 * HouseMaster Auto-Update Script
 * 
 * This script polls the server to check for updates (e.g., new payments, messages)
 * and refreshes the page if a change is detected.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if the timestamp variable is defined (set in the PHP page)
    if (typeof window.housemaster_last_update === 'undefined') {
        console.warn('Auto-update: window.housemaster_last_update is not defined. Polling disabled.');
        return;
    }

    // Polling interval in milliseconds (3000ms = 3 seconds)
    const POLL_INTERVAL = 5000;

    setInterval(function() {
        // The fetch URL is relative to the current page (in /admin/), so check_updates.php is accessible directly.
        fetch('check_updates.php?timestamp=' + window.housemaster_last_update)
            .then(response => response.json())
            .then(data => {
                if (data.refresh) {
                    console.log('Update detected. Refreshing page...');
                    // Update the local timestamp to prevent multiple reloads if the reload is delayed
                    window.housemaster_last_update = data.new_timestamp;
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Auto-update check failed:', error);
            });
    }, POLL_INTERVAL);
});