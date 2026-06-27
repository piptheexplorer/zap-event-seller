<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-order-details" style="font-size:14px; line-height:1.6;">
    <h3>Customer Details</h3>
    <p><strong>Name:</strong> <?php echo esc_html( $name ); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html( $email ); ?></p>
    <p><strong>Phone:</strong> <?php echo esc_html( $phone ); ?></p>

    <hr>

    <h3>Event</h3>
    <?php if ( ! empty( $event_id ) ) : ?>
        <p><strong>Linked Event:</strong>
            <a href="<?php echo esc_url( get_edit_post_link( $event_id ) ); ?>">
                #<?php echo esc_html( $event_id ); ?> <?php echo esc_html( get_the_title( $event_id ) ); ?>
            </a>
        </p>
    <?php endif; ?>
    <?php if ( $event_title ) : ?><p><strong>Event Title:</strong> <?php echo esc_html( $event_title ); ?></p><?php endif; ?>
    <?php if ( $event_location ) : ?><p><strong>Location:</strong> <?php echo esc_html( $event_location ); ?></p><?php endif; ?>
    <?php if ( $event_time ) : ?><p><strong>Time:</strong> <?php echo esc_html( $event_time ); ?></p><?php endif; ?>
    <?php if ( $event_date ) : ?><p><strong>Date:</strong> <?php echo esc_html( $event_date ); ?></p><?php endif; ?>

    <hr>

    <h3>Order Summary</h3>
    <?php if ( is_array( $tickets ) && ! empty( $tickets ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Image</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $tickets as $ticket ) :
                    if ( empty( $ticket['qty'] ) ) {
                        continue;
                    }
                    $qty   = (int) $ticket['qty'];
                    $price = (float) ( $ticket['price'] ?? 0 );
                    $image = $ticket['image'] ?? '';
                ?>
                    <tr>
                        <td><?php echo esc_html( $ticket['label'] ?? '' ); ?></td>
                        <td>
                            <?php if ( $image ) : ?>
                                <img src="<?php echo esc_url( $image ); ?>" style="max-width:60px;height:auto;border:1px solid #ddd;">
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $qty ); ?></td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( $price ) ); ?></td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( $qty * $price ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No ticket data found.</p>
    <?php endif; ?>

    <hr>

    <h3>Payment Details</h3>
    <p><strong>Status:</strong> <?php echo esc_html( ucfirst( (string) $status ) ); ?></p>
    <?php if ( $stripe_id ) : ?><p><strong>Stripe Session ID:</strong><br><code><?php echo esc_html( $stripe_id ); ?></code></p><?php endif; ?>
    <p><strong>Total Paid:</strong> <?php echo esc_html( \ETS\esc_money_gbp( $total / 100 ) ); ?></p>

    <h3>Generated Tickets</h3>
    <?php if ( is_array( $generated ) && ! empty( $generated ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Image</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $generated as $ticket ) :
                    $download_link = add_query_arg( [
                        'ets_order_id' => $post->ID,
                        'ticket_pdf'   => $ticket['ticket_id'] ?? '',
                    ], home_url( '/' ) );
                ?>
                    <tr>
                        <td><code><?php echo esc_html( $ticket['ticket_id'] ?? '' ); ?></code></td>
                        <td><?php echo esc_html( $ticket['type'] ?? '' ); ?></td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( (float) ( $ticket['price'] ?? 0 ) ) ); ?></td>
                        <td>
                            <?php if ( ! empty( $ticket['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $ticket['image'] ); ?>" style="max-width:60px;height:auto;border:1px solid #ddd;">
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><a class="button" href="<?php echo esc_url( $download_link ); ?>" target="_blank">Download PDF</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No tickets generated yet.</p>
    <?php endif; ?>

    <?php if ( $ticket_design ) : ?>
        <hr>
        <h3>Ticket Design</h3>
        <img src="<?php echo esc_url( $ticket_design ); ?>" style="max-width:120px;height:auto;border:1px solid #ddd;">
    <?php endif; ?>

    <?php if ( $status === 'paid' ) : ?>
        <hr>
        <form method="post">
            <?php wp_nonce_field( 'ets_resend_email', 'ets_resend_nonce' ); ?>
            <input type="hidden" name="ets_resend_order_id" value="<?php echo esc_attr( $post->ID ); ?>">
            <button type="submit" class="button button-secondary">Resend Customer Email</button>
        </form>
    <?php endif; ?>
</div>
