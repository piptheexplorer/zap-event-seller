<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $email ) && ! empty( $_GET['ets_email'] ) ) {
    $email = sanitize_email( wp_unslash( $_GET['ets_email'] ) );
}
?>
<div class="ets-my-tickets">
    <h2 class="text-2xl font-bold mb-4">My Tickets</h2>

    <?php if ( ! is_user_logged_in() || $email ) : ?>
        <form method="post" class="mb-6">
            <label class="block mb-2 font-semibold">Enter your email address</label>
            <input type="email" name="ets_email_lookup" required value="<?php echo esc_attr( $email ); ?>" class="ets-email-lookup" placeholder="Search for tickets...">
            <button type="submit" class="ets-profile-submit">View my tickets</button>
        </form>
    <?php else : ?>
        <p class="mb-6">Logged in as <?php echo esc_html( wp_get_current_user()->user_email ); ?>. <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Log out</a></p>
    <?php endif; ?>

    <?php
    $current_user_id = get_current_user_id();
    $lookup_by_account = is_user_logged_in() && ! $email;

    if ( ! $email && ! $lookup_by_account ) {
        echo '</div>';
        return;
    }

    if ( $lookup_by_account ) {
        echo '<p class="ets-dashboard-intro">Showing tickets linked to your account.</p>';
        $orders = get_posts( [
            'post_type'      => 'ticket_order',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_ets_customer_user_id',
                    'value' => $current_user_id,
                ],
                [
                    'key'   => '_ets_customer_email',
                    'value' => wp_get_current_user()->user_email,
                ],
            ],
        ] );
    } else {
        $orders = get_posts( [
            'post_type'      => 'ticket_order',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => '_ets_customer_email',
                    'value' => $email,
                ],
            ],
        ] );
    }

    if ( empty( $orders ) ) {
        echo '<p>No tickets found for this email.</p></div>';
        return;
    }

    foreach ( $orders as $order ) :
        $event_title    = get_post_meta( $order->ID, '_ets_event_title', true );
        $event_date     = get_post_meta( $order->ID, '_ets_event_date', true );
        $event_time     = get_post_meta( $order->ID, '_ets_event_time', true );
        $event_location = get_post_meta( $order->ID, '_ets_event_location', true );
        $tickets        = get_post_meta( $order->ID, '_ets_generated_tickets', true );
    ?>
        <div class="ets-event-block">
            <?php if ( $event_title ) : ?><h3><?php echo esc_html( $event_title ); ?></h3><?php endif; ?>
            <?php if ( $event_location ) : ?><p><?php echo esc_html( $event_location ); ?></p><?php endif; ?>
            <?php if ( $event_time ) : ?><p><?php echo esc_html( $event_time ); ?></p><?php endif; ?>
            <?php if ( $event_date ) : ?><p><?php echo esc_html( $event_date ); ?></p><?php endif; ?>

            <ul class="ets-ticket-download-list">
                <?php foreach ( (array) $tickets as $ticket ) :
                    if ( empty( $ticket['ticket_id'] ) ) {
                        continue;
                    }
                    $download_link = add_query_arg( [
                        'ets_order_id' => $order->ID,
                        'ticket_pdf'   => $ticket['ticket_id'],
                    ], home_url( '/' ) );
                ?>
                    <li class="ets-ticket-download-row">
                        <div>
                            <strong><?php echo esc_html( $ticket['ticket_id'] ); ?></strong><br>
                            <span><?php echo esc_html( $ticket['type'] ?? '' ); ?></span>
                            <?php if ( ! empty( $ticket['attendee_name'] ) ) : ?>
                                <br><small>Attendee: <?php echo esc_html( $ticket['attendee_name'] ); ?></small>
                            <?php endif; ?>
                            <?php if ( ! empty( $ticket['addons'] ) && is_array( $ticket['addons'] ) ) : ?>
                                <br><small>
                                    Add-ons:
                                    <?php
                                    $addon_bits = [];
                                    foreach ( $ticket['addons'] as $ticket_addon ) {
                                        if ( empty( $ticket_addon['name'] ) ) {
                                            continue;
                                        }
                                        $addon_text = esc_html( $ticket_addon['name'] );
                                        if ( isset( $ticket_addon['price'] ) && (float) $ticket_addon['price'] > 0 ) {
                                            $addon_text .= ' (' . esc_html( \ETS\esc_money_gbp( (float) $ticket_addon['price'] ) ) . ')';
                                        }
                                        $addon_bits[] = $addon_text;
                                    }
                                    echo wp_kses_post( implode( ', ', $addon_bits ) );
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url( $download_link ); ?>" class="ets-download-btn">Download ticket</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
