<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-customer-dashboard">
    <h2 class="text-2xl font-bold mb-4">My Ticket Dashboard</h2>

    <p class="mb-6">Logged in as <?php echo esc_html( $user->user_email ); ?>. <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Log out</a></p>

    <?php if ( empty( $orders ) ) : ?>
        <p>No tickets are linked to your account yet.</p>
    <?php else : ?>
        <?php foreach ( $orders as $order ) :
            $event_title    = get_post_meta( $order->ID, '_ets_event_title', true );
            $event_date     = get_post_meta( $order->ID, '_ets_event_date', true );
            $event_time     = get_post_meta( $order->ID, '_ets_event_time', true );
            $event_location = get_post_meta( $order->ID, '_ets_event_location', true );
            $status         = get_post_meta( $order->ID, '_ets_status', true );
            $tickets        = get_post_meta( $order->ID, '_ets_generated_tickets', true );
        ?>
            <div class="ets-event-block">
                <?php if ( $event_title ) : ?><h3><?php echo esc_html( $event_title ); ?></h3><?php endif; ?>
                <?php if ( $event_location ) : ?><p><?php echo esc_html( $event_location ); ?></p><?php endif; ?>
                <?php if ( $event_time ) : ?><p><?php echo esc_html( $event_time ); ?></p><?php endif; ?>
                <?php if ( $event_date ) : ?><p><?php echo esc_html( $event_date ); ?></p><?php endif; ?>
                <?php if ( $status ) : ?><p>Status: <?php echo esc_html( ucfirst( $status ) ); ?></p><?php endif; ?>

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
                                <?php if ( isset( $ticket['checked_in'] ) && $ticket['checked_in'] ) : ?>
                                    <br><small>Checked in</small>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo esc_url( $download_link ); ?>" class="ets-download-btn">Download ticket</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
