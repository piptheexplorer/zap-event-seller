<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-waiting-list-box mt-3">
    <button type="button" class="ets-waiting-toggle text-xs underline">Join waiting list</button>
    <div class="ets-waiting-list-form mt-3" style="display:none;">
        <input type="hidden" name="ets_event_id" value="<?php echo esc_attr( (string) ( $event_id ?? 0 ) ); ?>">
        <input type="hidden" name="ets_event_title" value="<?php echo esc_attr( (string) ( $event_title ?? '' ) ); ?>">
        <input type="hidden" name="ets_ticket_key" value="<?php echo esc_attr( (string) $index ); ?>">
        <input type="hidden" name="ets_ticket_label" value="<?php echo esc_attr( (string) $label ); ?>">

        <label class="block text-xs mb-1">Name</label>
        <input type="text" name="ets_waiting_name" class="ets-waiting-input" required>

        <label class="block text-xs mb-1 mt-2">Email</label>
        <input type="email" name="ets_waiting_email" class="ets-waiting-input" required>

        <label class="block text-xs mb-1 mt-2">Phone</label>
        <input type="text" name="ets_waiting_phone" class="ets-waiting-input">

        <label class="block text-xs mb-1 mt-2">Quantity wanted</label>
        <input type="number" name="ets_waiting_qty" class="ets-waiting-input ets-waiting-qty" min="1" value="1">

        <button type="button" class="ets-waiting-submit mt-3">Join waiting list</button>
        <p class="ets-waiting-message text-xs mt-2" aria-live="polite"></p>
    </div>
</div>
