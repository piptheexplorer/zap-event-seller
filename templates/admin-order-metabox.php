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
    <?php if ( ! empty( $customer_user_id ) ) : ?>
        <p><strong>Customer Account:</strong> #<?php echo esc_html( (string) $customer_user_id ); ?><?php if ( $account_status ) : ?> (<?php echo esc_html( $account_status ); ?>)<?php endif; ?></p>
    <?php endif; ?>

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
                    <th>Stock</th>
                    <th>Attendees</th>
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
                    $stock = $ticket['stock'] ?? null;
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
                        <td><?php echo $stock === null || $stock === '' ? 'Unlimited' : esc_html( (string) $stock ); ?></td>
                        <td>
                            <?php if ( ! empty( $ticket['attendees'] ) && is_array( $ticket['attendees'] ) ) : ?>
                                <ol style="margin:0 0 0 18px;">
                                    <?php foreach ( $ticket['attendees'] as $attendee ) : ?>
                                        <li>
                                            <?php echo esc_html( $attendee['name'] ?? 'Buyer' ); ?>
                                            <?php if ( ! empty( $attendee['email'] ) ) : ?>
                                                <br><small><?php echo esc_html( $attendee['email'] ); ?></small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No ticket data found.</p>
    <?php endif; ?>


    <?php if ( is_array( $addons ) && ! empty( $addons ) ) : ?>
        <h3>Add-ons / Upsells</h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Add-on</th>
                    <th>Image</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Line Total</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $addons as $addon ) :
                    if ( empty( $addon['qty'] ) ) {
                        continue;
                    }
                    $addon_qty   = (int) $addon['qty'];
                    $addon_price = (float) ( $addon['price'] ?? 0 );
                    $addon_image = $addon['image'] ?? '';
                    $addon_stock = $addon['stock'] ?? null;
                ?>
                    <tr>
                        <td><?php echo esc_html( $addon['name'] ?? '' ); ?></td>
                        <td>
                            <?php if ( $addon_image ) : ?>
                                <img src="<?php echo esc_url( $addon_image ); ?>" style="max-width:60px;height:auto;border:1px solid #ddd;">
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( (string) $addon_qty ); ?></td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( $addon_price ) ); ?></td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( $addon_qty * $addon_price ) ); ?></td>
                        <td><?php echo $addon_stock === null || $addon_stock === '' ? 'Unlimited' : esc_html( (string) $addon_stock ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>

    <h3>Payment Details</h3>
    <p><strong>Status:</strong> <?php echo esc_html( ucfirst( (string) $status ) ); ?></p>
    <?php if ( $stripe_id ) : ?><p><strong>Stripe Session ID:</strong><br><code><?php echo esc_html( $stripe_id ); ?></code></p><?php endif; ?>
    <?php if ( ! empty( $subtotal ) && ! empty( $discount_total ) ) : ?>
        <p><strong>Subtotal:</strong> <?php echo esc_html( \ETS\esc_money_gbp( $subtotal / 100 ) ); ?></p>
        <p><strong>Discount:</strong> <?php echo esc_html( $discount_code ? strtoupper( (string) $discount_code ) : 'Discount' ); ?> – <?php echo esc_html( \ETS\esc_money_gbp( $discount_total / 100 ) ); ?></p>
    <?php endif; ?>
    <p><strong>Total Paid:</strong> <?php echo esc_html( \ETS\esc_money_gbp( $total / 100 ) ); ?></p>

    <h3>Generated Tickets</h3>
    <?php if ( is_array( $generated ) && ! empty( $generated ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Type</th>
                    <th>Attendee</th>
                    <th>Ticket Add-ons</th>
                    <th>Price</th>
                    <th>Image</th>
                    <th>Check-in</th>
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
                        <td>
                            <?php echo esc_html( $ticket['attendee_name'] ?? '' ); ?>
                            <?php if ( ! empty( $ticket['attendee_email'] ) ) : ?>
                                <br><small><?php echo esc_html( $ticket['attendee_email'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $ticket['addons'] ) && is_array( $ticket['addons'] ) ) : ?>
                                <ul style="margin:0 0 0 18px;">
                                    <?php foreach ( $ticket['addons'] as $ticket_addon ) : ?>
                                        <li><?php echo esc_html( $ticket_addon['name'] ?? '' ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ( ( $ticket['ticket_kind'] ?? '' ) === 'event_addon' ) : ?>
                                <small>Event-wide add-on pass</small>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( \ETS\esc_money_gbp( (float) ( $ticket['price'] ?? 0 ) ) ); ?></td>
                        <td>
                            <?php if ( ! empty( $ticket['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $ticket['image'] ); ?>" style="max-width:60px;height:auto;border:1px solid #ddd;">
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $ticket['checked_in'] ) ) : ?>
                                <strong>Checked in</strong><br>
                                <small><?php echo esc_html( $ticket['checked_in_at'] ?? '' ); ?></small>
                            <?php else : ?>
                                Not checked in
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


    <hr>
    <h3>Staff Check-in</h3>
    <p>Create a front-end page containing <code>[ets_check_in]</code> for staff QR scanning and manual ticket check-in.</p>

    <?php if ( $status === 'paid' ) : ?>
        <hr>
        <form method="post">
            <?php wp_nonce_field( 'ets_resend_email', 'ets_resend_nonce' ); ?>
            <input type="hidden" name="ets_resend_order_id" value="<?php echo esc_attr( $post->ID ); ?>">
            <button type="submit" class="button button-secondary">Resend Customer Email</button>
        </form>
    <?php endif; ?>
</div>
