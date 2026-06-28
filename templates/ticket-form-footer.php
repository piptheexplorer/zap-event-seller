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
<input type="hidden" name="ets_ticket_pdf_layout" value="<?php echo esc_attr( (string) ( $ticket_pdf_layout ?? \ETS\get_setting( 'ticket_pdf_layout', 'classic_landscape' ) ) ); ?>">

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

<?php if ( is_user_logged_in() ) : ?>
    <div class="ets-account-link-note mt-4 mb-4 text-sm opacity-80">
        This order will be saved to your account.
    </div>
<?php else : ?>
    <label class="ets-create-account-label flex items-start gap-2 text-sm mt-4 mb-4">
        <input type="checkbox" class="ets-create-account-checkbox mt-1" name="ets_create_account" value="1">
        <span>Create an account so I can view and download my tickets later.</span>
    </label>
<?php endif; ?>

<div class="ets-total-wrapper mt-4 mb-5 text-lg">
    <div><strong>Subtotal:</strong> <span class="ets-subtotal-amount">£0.00</span></div>
    <div class="ets-discount-row" style="display:none;"><strong>Discount:</strong> <span class="ets-discount-amount">-£0.00</span></div>
    <div><strong>Total:</strong> <span class="ets-total-amount">£0.00</span></div>
</div>

<div class="ets-discount-wrapper mt-4 mb-5">
    <label for="<?php echo esc_attr( $block_id ); ?>-discount" class="block text-sm/6 font-medium mb-1 text-white">Discount code</label>
    <div class="flex gap-2">
        <input type="text" name="ets_discount_code" id="<?php echo esc_attr( $block_id ); ?>-discount" class="ets-discount-code-input block w-full rounded-md bg-white/5 px-3 py-1.5 text-base text-white uppercase outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" placeholder="Enter code">
        <button type="button" class="ets-apply-discount-button py-2 px-4 border border-white rounded-lg text-sm">Apply</button>
    </div>
    <p class="ets-discount-message text-sm mt-2" aria-live="polite"></p>
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
