<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php if ( $event_title ) : ?>
    <h2 class="ets-title text-2xl mb-8"><?php echo esc_html( $event_title ); ?></h2>
<?php endif; ?>

<?php if ( $event_desc ) : ?>
    <p class="ets-description text-lg mb-5"><?php echo nl2br( esc_html( $event_desc ) ); ?></p>
<?php endif; ?>

<?php if ( $event_location ) : ?>
    <p class="ets-location text-base mb-5"><strong>Location:</strong> <?php echo esc_html( $event_location ); ?></p>
<?php endif; ?>

<?php if ( $event_time ) : ?>
    <p class="ets-time text-base mb-5"><strong>Start Time:</strong> <?php echo esc_html( $event_time ); ?></p>
<?php endif; ?>

<?php if ( $event_date ) : ?>
    <p class="ets-date text-base mb-5"><strong>Date:</strong> <?php echo esc_html( $event_date ); ?></p>
<?php endif; ?>

<form class="ets-ticket-form">
    <h3 class="text-lg mb-5">Tickets</h3>

    <table class="ets-ticket-table border-separate border-spacing-2 table-auto w-full rounded-t-xl border-collapse border border-gray-400 mb-8">
        <thead>
            <tr>
                <th class="border border-gray-300">Type</th>
                <th class="border border-gray-300">Description</th>
                <th class="border border-gray-300">Price</th>
                <th class="border border-gray-300">Image</th>
                <th class="border border-gray-300">Availability</th>
                <th class="border border-gray-300">Quantity</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $ticket_types as $index => $ticket ) :
                $label       = $ticket['label'] ?? '';
                $description = $ticket['description'] ?? '';
                $price       = isset( $ticket['price'] ) ? (float) $ticket['price'] : 0;
                $image       = $ticket['image'] ?? [];
                $image_url   = \ETS\normalise_image_url( $image );
                $stock       = $ticket['_ets_stock'] ?? null;
                $sold        = (int) ( $ticket['_ets_sold'] ?? 0 );
                $remaining   = $ticket['_ets_remaining'] ?? null;
                $sold_out    = ! empty( $ticket['_ets_sold_out'] );
            ?>
                <tr>
                    <td><?php echo esc_html( $label ); ?></td>
                    <td><?php echo nl2br( esc_html( $description ) ); ?></td>
                    <td><?php echo esc_html( \ETS\esc_money_gbp( $price ) ); ?></td>
                    <td>
                        <?php if ( $image_url ) : ?>
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:60px;height:auto;">
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $remaining === null ) : ?>
                            <span class="ets-ticket-stock ets-ticket-stock-unlimited">Available</span>
                        <?php elseif ( $sold_out ) : ?>
                            <span class="ets-ticket-stock ets-ticket-stock-sold-out">Sold out</span>
                            <?php include ETS_PLUGIN_DIR . 'templates/waiting-list-form.php'; ?>
                        <?php else : ?>
                            <span class="ets-ticket-stock ets-ticket-stock-remaining"><?php echo esc_html( $remaining ); ?> left</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number" name="ets_tickets[<?php echo esc_attr( $index ); ?>][qty]" min="0" <?php echo $remaining !== null ? 'max="' . esc_attr( $remaining ) . '"' : ''; ?> value="0" data-price="<?php echo esc_attr( $price ); ?>" class="ets-qty-input" style="width:60px;" <?php disabled( $sold_out ); ?>>
                        <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][ticket_key]" value="<?php echo esc_attr( $index ); ?>">
                        <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
                        <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $price ); ?>">
                        <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][image]" value="<?php echo esc_url( $image_url ); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php include ETS_PLUGIN_DIR . 'templates/ticket-addons.php'; ?>

    <div class="ets-attendee-details mt-6 mb-8" data-ets-attendee-wrapper>
        <h3 class="text-lg mb-2">Attendee Details</h3>
        <p class="text-sm opacity-80 mb-4">Add the name and email for each person attending. Leave blank to use the buyer details.</p>
        <div class="ets-attendee-groups"></div>
    </div>

    <?php include ETS_PLUGIN_DIR . 'templates/ticket-form-footer.php'; ?>
</form>
