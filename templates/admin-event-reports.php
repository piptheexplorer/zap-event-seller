<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$checkin_percent = $stats['tickets_generated'] > 0 ? round( ( $stats['tickets_checked_in'] / $stats['tickets_generated'] ) * 100 ) : 0;
?>
<div class="wrap ets-reports-wrap">
    <h1>Event Reports</h1>

    <form method="get" style="margin: 18px 0; padding: 14px; background: #fff; border: 1px solid #dcdcde;">
        <input type="hidden" name="page" value="ets-event-reports">

        <label for="ets_event_id"><strong>Event</strong></label>
        <select id="ets_event_id" name="ets_event_id" style="min-width: 260px; margin-right: 12px;">
            <option value="0">All events</option>
            <?php foreach ( $events as $event ) : ?>
                <option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( $selected_event_id, $event->ID ); ?>>
                    <?php echo esc_html( get_the_title( $event ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="ets_date_from"><strong>From</strong></label>
        <input type="date" id="ets_date_from" name="ets_date_from" value="<?php echo esc_attr( $date_from ); ?>" style="margin-right: 12px;">

        <label for="ets_date_to"><strong>To</strong></label>
        <input type="date" id="ets_date_to" name="ets_date_to" value="<?php echo esc_attr( $date_to ); ?>" style="margin-right: 12px;">

        <button type="submit" class="button button-primary">Filter</button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ets-event-reports' ) ); ?>" class="button">Reset</a>
    </form>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin: 18px 0;">
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Revenue</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( \ETS\esc_money_gbp( $stats['revenue_cents'] / 100 ) ); ?></strong></p></div>
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Tickets Sold</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( (string) $stats['tickets_sold'] ); ?></strong></p></div>
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Paid Orders</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( (string) $stats['orders_paid'] ); ?></strong></p></div>
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Pending Orders</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( (string) $stats['orders_pending'] ); ?></strong></p></div>
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Checked In</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( $stats['tickets_checked_in'] . ' / ' . $stats['tickets_generated'] ); ?></strong></p><p style="margin:4px 0 0;"> <?php echo esc_html( (string) $checkin_percent ); ?>%</p></div>
        <div class="postbox" style="padding:16px;"><h2 style="margin:0 0 8px;">Discounts</h2><p style="font-size:26px;margin:0;"><strong><?php echo esc_html( \ETS\esc_money_gbp( $stats['discount_cents'] / 100 ) ); ?></strong></p></div>
    </div>

    <div style="display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap:18px; align-items:start;">
        <div class="postbox">
            <h2 class="hndle" style="padding:12px; margin:0;">Ticket Type Breakdown</h2>
            <div class="inside">
                <?php if ( ! empty( $stats['ticket_types'] ) ) : ?>
                    <table class="widefat striped">
                        <thead><tr><th>Ticket Type</th><th>Sold</th><th>Gross Value</th></tr></thead>
                        <tbody>
                            <?php foreach ( $stats['ticket_types'] as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row['label'] ); ?></td>
                                    <td><?php echo esc_html( (string) $row['qty'] ); ?></td>
                                    <td><?php echo esc_html( \ETS\esc_money_gbp( $row['revenue_cents'] / 100 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No ticket sales found for this filter.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle" style="padding:12px; margin:0;">Discount Usage</h2>
            <div class="inside">
                <?php if ( ! empty( $stats['discounts'] ) ) : ?>
                    <table class="widefat striped">
                        <thead><tr><th>Code</th><th>Uses</th><th>Total Discount</th></tr></thead>
                        <tbody>
                            <?php foreach ( $stats['discounts'] as $row ) : ?>
                                <tr>
                                    <td><code><?php echo esc_html( $row['code'] ); ?></code></td>
                                    <td><?php echo esc_html( (string) $row['uses'] ); ?></td>
                                    <td><?php echo esc_html( \ETS\esc_money_gbp( $row['discount_cents'] / 100 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No discount usage found for this filter.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="postbox" style="margin-top:18px;">
        <h2 class="hndle" style="padding:12px; margin:0;">Event Performance</h2>
        <div class="inside">
            <?php if ( ! empty( $stats['events'] ) ) : ?>
                <table class="widefat striped">
                    <thead><tr><th>Event</th><th>Orders</th><th>Paid Orders</th><th>Tickets Sold</th><th>Checked In</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ( $stats['events'] as $event ) : ?>
                            <tr>
                                <td>
                                    <?php if ( ! empty( $event['event_id'] ) && get_post( $event['event_id'] ) ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $event['event_id'] ) ); ?>"><?php echo esc_html( $event['event_title'] ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $event['event_title'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( (string) $event['orders'] ); ?></td>
                                <td><?php echo esc_html( (string) $event['paid_orders'] ); ?></td>
                                <td><?php echo esc_html( (string) $event['tickets_sold'] ); ?></td>
                                <td><?php echo esc_html( (string) $event['checked_in'] ); ?></td>
                                <td><?php echo esc_html( \ETS\esc_money_gbp( $event['revenue_cents'] / 100 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No event data found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="postbox" style="margin-top:18px;">
        <h2 class="hndle" style="padding:12px; margin:0;">Recent Orders</h2>
        <div class="inside">
            <?php if ( ! empty( $stats['recent_orders'] ) ) : ?>
                <table class="widefat striped">
                    <thead><tr><th>Order</th><th>Customer</th><th>Event</th><th>Status</th><th>Tickets</th><th>Total</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ( $stats['recent_orders'] as $order ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $order['edit_url'] ); ?>"><?php echo esc_html( '#' . $order['order_id'] ); ?></a></td>
                                <td><?php echo esc_html( $order['customer_name'] ); ?><br><small><?php echo esc_html( $order['customer_email'] ); ?></small></td>
                                <td><?php echo esc_html( $order['event_title'] ); ?></td>
                                <td><?php echo esc_html( ucfirst( $order['status'] ) ); ?></td>
                                <td><?php echo esc_html( (string) $order['tickets'] ); ?></td>
                                <td><?php echo esc_html( \ETS\esc_money_gbp( $order['total_cents'] / 100 ) ); ?></td>
                                <td><?php echo esc_html( $order['date'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No recent orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
