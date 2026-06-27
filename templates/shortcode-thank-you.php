<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-thank-you">
    <?php if ( $event_title ) : ?>
        <h1 class="text-2xl font-bold mb-4"><?php echo esc_html( $event_title ); ?></h1>
    <?php endif; ?>

    <h2 class="text-xl font-bold mb-4">Thank you for your purchase <?php echo esc_html( $name ); ?>!</h2>
    <p class="text-lg mb-4">Your tickets have been emailed to you.</p>

    <?php if ( $ticket_info ) : ?>
        <h3 class="text-base mb-2 uppercase font-bold tracking-wide">Your Tickets</h3>
        <ul class="ets-ticket-download-list"><?php echo wp_kses_post( $ticket_info ); ?></ul>
    <?php endif; ?>

    <p><strong>Total Paid:</strong> <?php echo esc_html( \ETS\esc_money_gbp( $total_cents / 100 ) ); ?></p>
    <p class="text-base">We look forward to seeing you at the event!</p>
</div>
