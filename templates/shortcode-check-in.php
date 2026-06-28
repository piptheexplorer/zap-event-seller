<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-checkin" data-validate-url="<?php echo esc_attr( $rest_validate ); ?>" data-checkin-url="<?php echo esc_attr( $rest_checkin ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
    <h2>Ticket Check-in</h2>
    <p>Scan a ticket QR code or enter a ticket ID manually.</p>

    <div class="ets-checkin-grid">
        <div class="ets-checkin-scanner-panel">
            <div id="ets-qr-reader"></div>
            <button type="button" class="button ets-start-scanner">Start Scanner</button>
            <button type="button" class="button ets-stop-scanner" disabled>Stop Scanner</button>
        </div>

        <div class="ets-checkin-manual-panel">
            <label for="ets-ticket-id-input"><strong>Ticket ID</strong></label>
            <input type="text" id="ets-ticket-id-input" class="regular-text" placeholder="e.g. ABC123XYZ0">
            <div class="ets-checkin-actions">
                <button type="button" class="button button-secondary ets-validate-ticket">Validate</button>
                <button type="button" class="button button-primary ets-checkin-ticket" disabled>Check In</button>
            </div>
        </div>
    </div>

    <div class="ets-checkin-result" aria-live="polite"></div>
</div>
