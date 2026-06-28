<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-ticket-block" id="<?php echo esc_attr( $block_id ); ?>"
     data-ets-rest-url="<?php echo esc_attr( $rest_url ); ?>"
     data-ets-stripe-pk="<?php echo esc_attr( $publishable_key ); ?>"
     data-ets-waiting-list-url="<?php echo esc_url( rest_url( 'ets/v1/join-waiting-list' ) ); ?>">
    <?php
    if ( $ticket_style === 'cards' ) {
        include ETS_PLUGIN_DIR . 'templates/ticket-layout-cards.php';
    } else {
        include ETS_PLUGIN_DIR . 'templates/ticket-layout-table.php';
    }
    ?>
</div>
<script src="https://js.stripe.com/v3/"></script>
