<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Customer {

    public function __construct() {
        add_shortcode( 'ets_customer_login', [ $this, 'login_form' ] );
        add_shortcode( 'ets_customer_dashboard', [ $this, 'dashboard' ] );
    }

    public function login_form(): string {
        if ( is_user_logged_in() ) {
            $dashboard_url = get_setting( 'customer_dashboard_url', home_url( '/profile/' ) );
            return '<p>You are logged in. <a href="' . esc_url( $dashboard_url ) . '">View your tickets</a>.</p>';
        }

        ob_start();
        echo '<div class="ets-customer-login">';
        wp_login_form( [
            'echo'     => true,
            'redirect' => esc_url( get_setting( 'customer_dashboard_url', home_url( '/profile/' ) ) ),
        ] );
        echo '<p><a href="' . esc_url( wp_lostpassword_url() ) . '">Forgotten your password?</a></p>';
        echo '</div>';
        return (string) ob_get_clean();
    }

    public function dashboard(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to view your tickets.</p>' . $this->login_form();
        }

        $user = wp_get_current_user();
        $orders = get_posts( [
            'post_type'      => 'ticket_order',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_ets_customer_user_id',
                    'value' => get_current_user_id(),
                ],
                [
                    'key'   => '_ets_customer_email',
                    'value' => $user->user_email,
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        ob_start();
        include ETS_PLUGIN_DIR . 'templates/shortcode-customer-dashboard.php';
        return (string) ob_get_clean();
    }

    public static function maybe_create_or_link_user_for_order( int $order_id, string $name, string $email, bool $create_account ): int {
        $email = sanitize_email( $email );
        if ( ! $email || ! $order_id ) {
            return 0;
        }

        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            update_post_meta( $order_id, '_ets_customer_user_id', $user_id );
            update_post_meta( $order_id, '_ets_customer_account_status', 'logged_in' );
            Roles::assign_customer_role( $user_id );
            return $user_id;
        }

        if ( ! $create_account ) {
            return 0;
        }

        $existing_user_id = email_exists( $email );
        if ( $existing_user_id ) {
            update_post_meta( $order_id, '_ets_customer_user_id', (int) $existing_user_id );
            update_post_meta( $order_id, '_ets_customer_account_status', 'existing_user' );
            Roles::assign_customer_role( (int) $existing_user_id );
            self::send_existing_account_email( (int) $existing_user_id, $order_id );
            return (int) $existing_user_id;
        }

        $base_username = sanitize_user( current( explode( '@', $email ) ), true );
        if ( ! $base_username ) {
            $base_username = 'ticketbuyer';
        }

        $username = $base_username;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $base_username . $i;
            $i++;
        }

        $password = wp_generate_password( 24, true, true );
        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name ?: $email,
            'role'         => Roles::CUSTOMER_ROLE,
        ] );

        if ( is_wp_error( $user_id ) ) {
            update_post_meta( $order_id, '_ets_customer_account_status', 'create_failed' );
            update_post_meta( $order_id, '_ets_customer_account_error', $user_id->get_error_message() );
            return 0;
        }

        update_post_meta( $order_id, '_ets_customer_user_id', (int) $user_id );
        update_post_meta( $order_id, '_ets_customer_account_status', 'created' );
        Roles::assign_customer_role( (int) $user_id );
        update_user_meta( (int) $user_id, '_ets_created_from_order', $order_id );

        self::send_new_account_email( (int) $user_id, $order_id );

        return (int) $user_id;
    }

    private static function send_new_account_email( int $user_id, int $order_id ): void {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return;
        }

        $reset_url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
        $dashboard_url = get_setting( 'customer_dashboard_url', home_url( '/profile/' ) );

        $subject = 'Your ticket account has been created';
        $message = '<p>An account has been created for your ticket order.</p>' .
            '<p><a href="' . esc_url( $reset_url ) . '">Set your password</a></p>' .
            '<p>After setting your password, you can view your tickets here: <a href="' . esc_url( $dashboard_url ) . '">' . esc_html( $dashboard_url ) . '</a></p>';

        wp_mail( $user->user_email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    private static function send_existing_account_email( int $user_id, int $order_id ): void {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $dashboard_url = get_setting( 'customer_dashboard_url', home_url( '/profile/' ) );
        $subject = 'Your tickets have been linked to your account';
        $message = '<p>Your latest ticket order has been linked to your existing account.</p>' .
            '<p><a href="' . esc_url( $dashboard_url ) . '">View your tickets</a></p>';

        wp_mail( $user->user_email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }
}
