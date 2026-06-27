<?php
namespace ETS;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Stripe {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'template_redirect', [ $this, 'maybe_mark_paid_and_email' ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'ets/v1', '/create-checkout-session', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_checkout_session' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function create_checkout_session( WP_REST_Request $request ): WP_REST_Response {
        $name           = sanitize_text_field( $request->get_param( 'ets_name' ) );
        $email          = sanitize_email( $request->get_param( 'ets_email' ) );
        $phone          = sanitize_text_field( $request->get_param( 'ets_phone' ) );
        $event_id       = (int) $request->get_param( 'ets_event_id' );
        $event_title    = sanitize_text_field( $request->get_param( 'ets_event_title' ) );
        $event_date     = sanitize_text_field( $request->get_param( 'ets_event_date' ) );
        $event_time     = sanitize_text_field( $request->get_param( 'ets_event_time' ) );
        $event_location = sanitize_text_field( $request->get_param( 'ets_event_location' ) );
        $ticket_design  = esc_url_raw( (string) $request->get_param( 'ets_ticket_design' ) );
        $accepted_terms = $request->get_param( 'ets_accept_terms' );
        $tickets        = (array) $request->get_param( 'ets_tickets' );

        if ( empty( $name ) || empty( $email ) ) {
            return new WP_REST_Response( [ 'error' => 'Missing required fields.' ], 400 );
        }

        if ( empty( $accepted_terms ) ) {
            return new WP_REST_Response( [ 'error' => 'You must agree to the terms and conditions.' ], 400 );
        }

        $line_items   = [];
        $clean_tickets = [];
        $total_amount = 0;

        foreach ( $tickets as $ticket ) {
            $qty   = isset( $ticket['qty'] ) ? (int) $ticket['qty'] : 0;
            $label = isset( $ticket['label'] ) ? sanitize_text_field( $ticket['label'] ) : '';
            $price = isset( $ticket['price'] ) ? (float) $ticket['price'] : 0;
            $image = isset( $ticket['image'] ) ? esc_url_raw( $ticket['image'] ) : '';

            $clean_tickets[] = [
                'qty'   => $qty,
                'label' => $label,
                'price' => $price,
                'image' => $image,
            ];

            if ( $qty > 0 && $price > 0 && $label ) {
                $amount_cents  = (int) round( $price * 100 );
                $total_amount += $amount_cents * $qty;

                $line_items[] = [
                    'quantity'   => $qty,
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => $amount_cents,
                        'product_data' => [ 'name' => $label ],
                    ],
                ];
            }
        }

        if ( empty( $line_items ) ) {
            return new WP_REST_Response( [ 'error' => 'Please select at least one ticket.' ], 400 );
        }

        $secret_key  = get_setting( 'stripe_secret_key', '' );
        $success_url = get_setting( 'stripe_success_url', home_url( '/' ) );
        $cancel_url  = get_setting( 'stripe_cancel_url', home_url( '/' ) );

        if ( empty( $secret_key ) ) {
            return new WP_REST_Response( [ 'error' => 'Stripe is not configured.' ], 500 );
        }

        $order_id = wp_insert_post( [
            'post_type'   => 'ticket_order',
            'post_status' => 'publish',
            'post_title'  => 'Order - ' . $name . ' - ' . current_time( 'mysql' ),
        ] );

        if ( ! $order_id || is_wp_error( $order_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Could not create ticket order.' ], 500 );
        }

        update_post_meta( $order_id, '_ets_customer_name', $name );
        update_post_meta( $order_id, '_ets_customer_email', $email );
        update_post_meta( $order_id, '_ets_customer_phone', $phone );
        update_post_meta( $order_id, '_ets_tickets', $clean_tickets );
        update_post_meta( $order_id, '_ets_total_amount_cents', $total_amount );
        update_post_meta( $order_id, '_ets_status', 'pending' );
        update_post_meta( $order_id, '_ets_terms_accepted', 'yes' );
        update_post_meta( $order_id, '_ets_terms_text', wp_strip_all_tags( get_setting( 'purchase_policy_text', '' ) ) );

        if ( $event_id ) {
            update_post_meta( $order_id, '_ets_event_id', $event_id );
        }
        if ( $event_title ) {
            update_post_meta( $order_id, '_ets_event_title', $event_title );
        }
        if ( $event_date ) {
            update_post_meta( $order_id, '_ets_event_date', $event_date );
        }
        if ( $event_time ) {
            update_post_meta( $order_id, '_ets_event_time', $event_time );
        }
        if ( $event_location ) {
            update_post_meta( $order_id, '_ets_event_location', $event_location );
        }
        if ( $ticket_design ) {
            update_post_meta( $order_id, '_ets_ticket_design', $ticket_design );
        }

        $success_url = add_query_arg( [
            'ets_success'  => '1',
            'ets_order_id' => $order_id,
        ], $success_url );

        $body = [
            'mode'                   => 'payment',
            'success_url'            => esc_url_raw( $success_url ),
            'cancel_url'             => esc_url_raw( $cancel_url ),
            'payment_method_types[]' => 'card',
            'customer_email'         => $email,
        ] + $this->flatten_line_items_for_stripe( $line_items );

        $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response( [ 'error' => 'Error communicating with Stripe: ' . $response->get_error_message() ], 500 );
        }

        $stripe_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $stripe_body['id'] ) ) {
            error_log( 'Stripe Checkout error: ' . print_r( $stripe_body, true ) );
            return new WP_REST_Response( [ 'error' => 'Unable to create checkout session.' ], 500 );
        }

        update_post_meta( $order_id, '_ets_stripe_session_id', sanitize_text_field( $stripe_body['id'] ) );

        return new WP_REST_Response( [
            'id'  => $stripe_body['id'],
            'url' => $stripe_body['url'] ?? '',
        ], 200 );
    }

    private function flatten_line_items_for_stripe( array $line_items ): array {
        $body = [];

        foreach ( $line_items as $i => $item ) {
            $body[ "line_items[$i][quantity]" ]                       = $item['quantity'];
            $body[ "line_items[$i][price_data][currency]" ]           = $item['price_data']['currency'];
            $body[ "line_items[$i][price_data][unit_amount]" ]        = $item['price_data']['unit_amount'];
            $body[ "line_items[$i][price_data][product_data][name]" ] = $item['price_data']['product_data']['name'];
        }

        return $body;
    }

    public function maybe_mark_paid_and_email(): void {
        if ( ! isset( $_GET['ets_success'], $_GET['ets_order_id'] ) ) {
            return;
        }

        $order_id = (int) $_GET['ets_order_id'];
        if ( ! $order_id || get_post_type( $order_id ) !== 'ticket_order' ) {
            return;
        }

        if ( get_post_meta( $order_id, '_ets_email_sent', true ) === 'yes' ) {
            return;
        }

        update_post_meta( $order_id, '_ets_status', 'paid' );

        if ( ! get_post_meta( $order_id, '_ets_generated_tickets', true ) ) {
            $this->generate_ticket_ids( $order_id );
        }

        Plugin::instance()->shortcodes->send_ticket_emails( $order_id );
    }

    private function generate_ticket_ids( int $order_id ): void {
        $tickets = get_post_meta( $order_id, '_ets_tickets', true );
        if ( ! is_array( $tickets ) ) {
            return;
        }

        $generated = [];

        foreach ( $tickets as $ticket ) {
            $qty   = (int) ( $ticket['qty'] ?? 0 );
            $label = sanitize_text_field( $ticket['label'] ?? '' );
            $price = (float) ( $ticket['price'] ?? 0 );
            $image = esc_url_raw( (string) ( $ticket['image'] ?? '' ) );

            if ( $qty <= 0 || ! $label ) {
                continue;
            }

            for ( $i = 1; $i <= $qty; $i++ ) {
                $generated[] = [
                    'ticket_id' => strtoupper( wp_generate_password( 10, false, false ) ),
                    'type'      => $label,
                    'price'     => $price,
                    'image'     => $image,
                ];
            }
        }

        update_post_meta( $order_id, '_ets_generated_tickets', $generated );
    }
}
