<?php
namespace ETS;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Checkin {

    public function __construct() {
        add_shortcode( 'ets_check_in', [ $this, 'render_checkin_page' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'ets/v1', '/ticket-validate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'validate_ticket' ],
            'permission_callback' => [ $this, 'can_checkin' ],
        ] );

        register_rest_route( 'ets/v1', '/ticket-check-in', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'check_in_ticket' ],
            'permission_callback' => [ $this, 'can_checkin' ],
        ] );
    }

    public function can_checkin(): bool {
        return is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    public function render_checkin_page(): string {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            return '<p>You must be logged in with staff access to check in tickets.</p>';
        }

        wp_enqueue_script( 'ets-html5-qrcode' );
        wp_enqueue_script( 'ets-checkin' );
        wp_enqueue_style( 'ets-checkin' );

        $rest_validate = esc_url( rest_url( 'ets/v1/ticket-validate' ) );
        $rest_checkin  = esc_url( rest_url( 'ets/v1/ticket-check-in' ) );
        $nonce         = wp_create_nonce( 'wp_rest' );

        ob_start();
        include ETS_PLUGIN_DIR . 'templates/shortcode-check-in.php';
        return (string) ob_get_clean();
    }

    public function validate_ticket( WP_REST_Request $request ): WP_REST_Response {
        $ticket_id = $this->normalise_ticket_input( (string) $request->get_param( 'ticket_id' ) );

        if ( empty( $ticket_id ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Please enter or scan a ticket ID.' ], 400 );
        }

        $match = $this->find_ticket( $ticket_id );

        if ( ! $match ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Ticket not found.' ], 404 );
        }

        return new WP_REST_Response( $this->format_ticket_response( $match ), 200 );
    }

    public function check_in_ticket( WP_REST_Request $request ): WP_REST_Response {
        $ticket_id = $this->normalise_ticket_input( (string) $request->get_param( 'ticket_id' ) );

        if ( empty( $ticket_id ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Please enter or scan a ticket ID.' ], 400 );
        }

        $match = $this->find_ticket( $ticket_id );

        if ( ! $match ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Ticket not found.' ], 404 );
        }

        $order_id = (int) $match['order_id'];
        $tickets  = $match['tickets'];
        $index    = (int) $match['index'];
        $status   = get_post_meta( $order_id, '_ets_status', true );

        if ( $status !== 'paid' ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'This order has not been marked as paid.',
                'ticket'  => $this->format_ticket_response( $match ),
            ], 400 );
        }

        if ( ! empty( $tickets[ $index ]['checked_in'] ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Ticket already checked in.',
                'ticket'  => $this->format_ticket_response( $match ),
            ], 409 );
        }

        $tickets[ $index ]['checked_in']    = 'yes';
        $tickets[ $index ]['checked_in_at'] = current_time( 'mysql' );
        $tickets[ $index ]['checked_in_by'] = get_current_user_id();

        update_post_meta( $order_id, '_ets_generated_tickets', $tickets );

        $log = get_post_meta( $order_id, '_ets_ticket_checkin_log', true );
        $log = is_array( $log ) ? $log : [];
        $log[] = [
            'ticket_id' => $ticket_id,
            'time'      => current_time( 'mysql' ),
            'user_id'   => get_current_user_id(),
        ];
        update_post_meta( $order_id, '_ets_ticket_checkin_log', $log );

        $match = $this->find_ticket( $ticket_id );

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Ticket checked in successfully.',
            'ticket'  => $this->format_ticket_response( $match ),
        ], 200 );
    }

    private function find_ticket( string $ticket_id ) {
        $orders = get_posts( [
            'post_type'      => 'ticket_order',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_ets_generated_tickets',
                    'value'   => $ticket_id,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        if ( empty( $orders ) ) {
            return false;
        }

        foreach ( $orders as $order_id ) {
            $tickets = get_post_meta( (int) $order_id, '_ets_generated_tickets', true );
            if ( ! is_array( $tickets ) ) {
                continue;
            }

            foreach ( $tickets as $index => $ticket ) {
                if ( (string) ( $ticket['ticket_id'] ?? '' ) === $ticket_id ) {
                    return [
                        'order_id' => (int) $order_id,
                        'tickets'  => $tickets,
                        'index'    => (int) $index,
                        'ticket'   => $ticket,
                    ];
                }
            }
        }

        return false;
    }

    private function format_ticket_response( $match ): array {
        if ( ! $match ) {
            return [];
        }

        $order_id = (int) $match['order_id'];
        $ticket   = (array) $match['ticket'];
        $user_id  = (int) ( $ticket['checked_in_by'] ?? 0 );
        $user     = $user_id ? get_userdata( $user_id ) : false;

        $addons = [];
        if ( ! empty( $ticket['addons'] ) && is_array( $ticket['addons'] ) ) {
            foreach ( $ticket['addons'] as $addon ) {
                if ( empty( $addon['name'] ) ) {
                    continue;
                }

                $addons[] = [
                    'name'  => (string) $addon['name'],
                    'price' => esc_money_gbp( (float) ( $addon['price'] ?? 0 ) ),
                ];
            }
        }

        return [
            'success'          => true,
            'order_id'         => $order_id,
            'ticket_id'        => (string) ( $ticket['ticket_id'] ?? '' ),
            'ticket_type'      => (string) ( $ticket['type'] ?? '' ),
            'ticket_kind'      => (string) ( $ticket['ticket_kind'] ?? 'ticket' ),
            'attendee_name'    => (string) ( $ticket['attendee_name'] ?? '' ),
            'attendee_email'   => (string) ( $ticket['attendee_email'] ?? '' ),
            'price'            => esc_money_gbp( (float) ( $ticket['price'] ?? 0 ) ),
            'addons'           => $addons,
            'checked_in'       => ! empty( $ticket['checked_in'] ),
            'checked_in_at'    => (string) ( $ticket['checked_in_at'] ?? '' ),
            'checked_in_by'    => $user ? $user->display_name : '',
            'order_status'     => (string) get_post_meta( $order_id, '_ets_status', true ),
            'customer_name'    => (string) get_post_meta( $order_id, '_ets_customer_name', true ),
            'customer_email'   => (string) get_post_meta( $order_id, '_ets_customer_email', true ),
            'event_title'      => (string) get_post_meta( $order_id, '_ets_event_title', true ),
            'event_date'       => (string) get_post_meta( $order_id, '_ets_event_date', true ),
            'event_time'       => (string) get_post_meta( $order_id, '_ets_event_time', true ),
            'event_location'   => (string) get_post_meta( $order_id, '_ets_event_location', true ),
        ];
    }

    private function normalise_ticket_input( string $input ): string {
        $input = trim( wp_unslash( $input ) );

        if ( empty( $input ) ) {
            return '';
        }

        $decoded = json_decode( $input, true );
        if ( is_array( $decoded ) && ! empty( $decoded['ticket_id'] ) ) {
            return sanitize_text_field( (string) $decoded['ticket_id'] );
        }

        $parts = wp_parse_url( $input );
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
            if ( ! empty( $query['ticket_id'] ) ) {
                return sanitize_text_field( (string) $query['ticket_id'] );
            }
            if ( ! empty( $query['ticket_pdf'] ) ) {
                return sanitize_text_field( (string) $query['ticket_pdf'] );
            }
        }

        return sanitize_text_field( $input );
    }
}
