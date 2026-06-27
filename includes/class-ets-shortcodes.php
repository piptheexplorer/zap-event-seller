<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shortcodes {

    public function __construct() {
        add_shortcode( 'ets_thank_you', [ $this, 'thank_you' ] );
        add_shortcode( 'ets_my_tickets', [ $this, 'my_tickets' ] );
        add_action( 'admin_init', [ $this, 'handle_resend_email' ] );
    }

    public function thank_you(): string {
        if ( ! isset( $_GET['ets_success'], $_GET['ets_order_id'] ) ) {
            return '<p>No recent order found.</p>';
        }

        $order_id       = (int) $_GET['ets_order_id'];
        $name           = get_post_meta( $order_id, '_ets_customer_name', true );
        $tickets        = get_post_meta( $order_id, '_ets_tickets', true );
        $event_title    = get_post_meta( $order_id, '_ets_event_title', true );
        $event_date     = get_post_meta( $order_id, '_ets_event_date', true );
        $event_time     = get_post_meta( $order_id, '_ets_event_time', true );
        $event_location = get_post_meta( $order_id, '_ets_event_location', true );
        $total_cents    = (int) get_post_meta( $order_id, '_ets_total_amount_cents', true );
        $ticket_ids     = get_post_meta( $order_id, '_ets_generated_tickets', true );

        if ( ! $order_id || get_post_type( $order_id ) !== 'ticket_order' || ! is_array( $tickets ) ) {
            return '<p>Order not found.</p>';
        }

        $lines = $this->build_ticket_lines( $tickets );
        $ticket_info = $this->build_download_buttons( $order_id, (array) $ticket_ids );

        ob_start();
        include ETS_PLUGIN_DIR . 'templates/shortcode-thank-you.php';
        return (string) ob_get_clean();
    }

    public function my_tickets(): string {
        $email = isset( $_POST['ets_email_lookup'] ) ? sanitize_email( $_POST['ets_email_lookup'] ) : '';

        ob_start();
        include ETS_PLUGIN_DIR . 'templates/shortcode-my-tickets.php';
        return (string) ob_get_clean();
    }

    public function send_ticket_emails( int $order_id ): void {
        $from_name   = get_setting( 'email_from_name', get_bloginfo( 'name' ) );
        $from_email  = get_setting( 'email_from_address', get_bloginfo( 'admin_email' ) );
        $admin_email = get_setting( 'admin_notification_email', get_bloginfo( 'admin_email' ) );

        $customer_email = get_post_meta( $order_id, '_ets_customer_email', true );
        $customer_name  = get_post_meta( $order_id, '_ets_customer_name', true );
        $event_title    = get_post_meta( $order_id, '_ets_event_title', true );
        $event_date     = get_post_meta( $order_id, '_ets_event_date', true );
        $event_time     = get_post_meta( $order_id, '_ets_event_time', true );
        $event_location = get_post_meta( $order_id, '_ets_event_location', true );
        $tickets        = get_post_meta( $order_id, '_ets_tickets', true );

        if ( empty( $customer_email ) ) {
            return;
        }

        $ticket_lines = $this->build_ticket_lines( (array) $tickets );
        $headers      = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $download_button = $this->build_email_download_button( $order_id );
        $template        = get_setting( 'email_template_customer', '' );

        if ( empty( $template ) ) {
            $template = '<h2>Thank you {customer_name}!</h2><p>Your tickets for <strong>{event_title}</strong> are confirmed.</p><p>{tickets}</p><p>{download_button}</p>';
        }

        $message = parse_email_template( $template, [
            'customer_name'   => esc_html( $customer_name ),
            'event_title'     => esc_html( $event_title ),
            'event_date'      => esc_html( $event_date ),
            'event_time'      => esc_html( $event_time ),
            'event_location'  => esc_html( $event_location ),
            'tickets'         => '<ul>' . $ticket_lines . '</ul>',
            'download_button' => $download_button,
        ] );

        wp_mail( $customer_email, 'Your Ticket Order Confirmation', $message, $headers, [] );

        $admin_message = '<h2>New Ticket Order</h2>' .
            '<p><strong>Name:</strong> ' . esc_html( $customer_name ) . '</p>' .
            '<p><strong>Email:</strong> ' . esc_html( $customer_email ) . '</p>' .
            '<p><strong>Tickets:</strong></p><ul>' . $ticket_lines . '</ul>';

        wp_mail( $admin_email, 'New Ticket Order #' . $order_id, $admin_message, $headers );

        update_post_meta( $order_id, '_ets_email_sent', 'yes' );
    }

    public function handle_resend_email(): void {
        if ( empty( $_POST['ets_resend_order_id'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! isset( $_POST['ets_resend_nonce'] ) || ! wp_verify_nonce( $_POST['ets_resend_nonce'], 'ets_resend_email' ) ) {
            return;
        }

        $order_id = (int) $_POST['ets_resend_order_id'];
        if ( ! $order_id || get_post_type( $order_id ) !== 'ticket_order' ) {
            return;
        }

        delete_post_meta( $order_id, '_ets_email_sent' );
        update_post_meta( $order_id, '_ets_email_resent_at', current_time( 'mysql' ) );
        $this->send_ticket_emails( $order_id );

        wp_safe_redirect( add_query_arg( 'ets_resent', '1', wp_get_referer() ?: admin_url( 'edit.php?post_type=ticket_order' ) ) );
        exit;
    }

    private function build_ticket_lines( array $tickets ): string {
        $lines = '';

        foreach ( $tickets as $ticket ) {
            if ( empty( $ticket['qty'] ) ) {
                continue;
            }

            $lines .= '<li>' . intval( $ticket['qty'] ) . ' × ' . esc_html( $ticket['label'] ?? '' ) . '</li>';
        }

        return $lines;
    }

    private function build_download_buttons( int $order_id, array $tickets ): string {
        $html = '';

        foreach ( $tickets as $ticket ) {
            if ( empty( $ticket['ticket_id'] ) ) {
                continue;
            }

            $download_link = add_query_arg( [
                'ets_success'  => '1',
                'ets_order_id' => $order_id,
                'ticket_pdf'   => $ticket['ticket_id'],
            ] );

            $html .= sprintf(
                '<li class="ets-ticket-download-row"><div><strong>%s</strong><br><span>%s</span> <span>%s</span></div><a href="%s" class="ets-download-btn">Download ticket</a></li>',
                esc_html( $ticket['ticket_id'] ),
                esc_html( $ticket['type'] ?? '' ),
                esc_html( esc_money_gbp( (float) ( $ticket['price'] ?? 0 ) ) ),
                esc_url( $download_link )
            );
        }

        return $html;
    }

    private function build_email_download_button( int $order_id ): string {
        $download_url = add_query_arg( [
            'ets_email' => rawurlencode( (string) get_post_meta( $order_id, '_ets_customer_email', true ) ),
        ], home_url( '/profile/' ) );

        $button_template = get_setting( 'email_download_button_template', '' );

        if ( empty( $button_template ) ) {
            $button_template = '<a href="{download_url}" style="display:inline-block;padding:12px 18px;background:#000;color:#fff;text-decoration:none;border-radius:6px;">Download Tickets</a>';
        }

        return str_replace( '{download_url}', esc_url( $download_url ), $button_template );
    }
}
