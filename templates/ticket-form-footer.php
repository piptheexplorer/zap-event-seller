<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<input type="hidden" name="ets_event_id" value="<?php echo esc_attr( (string) ( $event_id ?? '' ) ); ?>">
<input type="hidden" name="ets_event_title" value="<?php echo esc_attr( (string) $event_title ); ?>">
<input type="hidden" name="ets_event_date" value="<?php echo esc_attr( (string) $event_date ); ?>">
<input type="hidden" name="ets_event_time" value="<?php echo esc_attr( (string) $event_time ); ?>">
<input type="hidden" name="ets_event_location" value="<?php echo esc_attr( (string) $event_location ); ?>">
<input type="hidden" name="ets_ticket_design" value="<?php echo esc_url( \ETS\normalise_image_url( $ticket_design ) ); ?>">

<h3 class="text-base/7 font-semibold text-white">Your Details</h3>

<p>
    <label for="<?php echo esc_attr( $block_id ); ?>-name" class="block text-sm/6 font-medium mb-1 text-white">Full Name *</label>
    <input type="text" name="ets_name" class="block w-full mb-1 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" id="<?php echo esc_attr( $block_id ); ?>-name" required>
</p>

<p>
    <label for="<?php echo esc_attr( $block_id ); ?>-email" class="block text-sm/6 font-medium mb-1 text-white">Email *</label>
    <input type="email" name="ets_email" class="block w-full mb-1 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" id="<?php echo esc_attr( $block_id ); ?>-email" required>
</p>

<p>
    <label for="<?php echo esc_attr( $block_id ); ?>-phone" class="block text-sm/6 font-medium mb-1 text-white">Phone</label>
    <input type="text" name="ets_phone" class="block w-full mb-1 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" id="<?php echo esc_attr( $block_id ); ?>-phone">
</p>

<div class="ets-total-wrapper mt-4 mb-5 text-lg">
    <strong>Total:</strong>
    <span class="ets-total-amount">£0.00</span>
</div>

<?php if ( ! empty( $policy_text ) ) : ?>
    <label class="ets-terms-label flex items-start gap-2 text-sm mt-4">
        <input type="checkbox" class="ets-terms-checkbox mt-1" name="ets_accept_terms" value="1">
        <span><?php echo wp_kses_post( $policy_text ); ?></span>
    </label>
<?php endif; ?>

<p>
    <button type="button" class="ets-pay-button py-2 px-4 mt-4 mb-4 border border-white rounded-lg">Proceed to Payment</button>
</p>
