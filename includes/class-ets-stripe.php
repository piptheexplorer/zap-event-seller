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

        register_rest_route( 'ets/v1', '/validate-discount', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'validate_discount' ],
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
        $ticket_pdf_layout = sanitize_key( (string) $request->get_param( 'ets_ticket_pdf_layout' ) );
        $accepted_terms = $request->get_param( 'ets_accept_terms' );
        $create_account = (bool) $request->get_param( 'ets_create_account' );
        $discount_code  = normalise_discount_code( $request->get_param( 'ets_discount_code' ) );
        $tickets        = (array) $request->get_param( 'ets_tickets' );
        $addons         = (array) $request->get_param( 'ets_addons' );
        $attendees      = (array) $request->get_param( 'ets_attendees' );

        if ( empty( $name ) || empty( $email ) ) {
            return new WP_REST_Response( [ 'error' => 'Missing required fields.' ], 400 );
        }

        if ( empty( $accepted_terms ) ) {
            return new WP_REST_Response( [ 'error' => 'You must agree to the terms and conditions.' ], 400 );
        }

        $event_ticket_types = $event_id ? $this->get_event_ticket_types( $event_id ) : [];
        $event_addons       = $event_id ? $this->get_event_addons( $event_id ) : [];

        $line_items    = [];
        $clean_tickets = [];
        $clean_addons  = [];
        $total_amount  = 0;

        foreach ( $tickets as $raw_index => $ticket ) {
            $qty        = isset( $ticket['qty'] ) ? max( 0, (int) $ticket['qty'] ) : 0;
            $ticket_key = isset( $ticket['ticket_key'] ) ? (int) $ticket['ticket_key'] : (int) $raw_index;

            if ( $event_id && isset( $event_ticket_types[ $ticket_key ] ) ) {
                // Trust the event CPT ticket definition, not the browser-submitted label/price/image.
                $event_ticket = $event_ticket_types[ $ticket_key ];
                $label = sanitize_text_field( $event_ticket['label'] ?? '' );
                $price = isset( $event_ticket['price'] ) ? (float) $event_ticket['price'] : 0;
                $image = normalise_image_url( $event_ticket['image'] ?? '' );
                $stock = $event_ticket['stock'] ?? null;
                $sold  = get_event_ticket_sold_count( $event_id, $ticket_key, $label );
                $remaining = get_ticket_remaining_stock( $stock, $sold );
                if ( $qty > 0 && $remaining !== null && $qty > $remaining ) {
                    return new WP_REST_Response(
                        [ 'error' => sprintf( 'Only %d %s ticket%s remaining.', $remaining, $label, $remaining === 1 ? '' : 's' ) ],
                        400
                    );
                }
            } else {
                $label = isset( $ticket['label'] ) ? sanitize_text_field( $ticket['label'] ) : '';
                $price = isset( $ticket['price'] ) ? (float) $ticket['price'] : 0;
                $image = isset( $ticket['image'] ) ? esc_url_raw( $ticket['image'] ) : '';
                $stock = null;
                $remaining = null;
            }

            $clean_tickets[] = [
                'ticket_key' => $ticket_key,
                'qty'        => $qty,
                'label'      => $label,
                'price'      => $price,
                'image'      => $image,
                'stock'      => normalise_ticket_stock_value( $stock ),
                'remaining_at_purchase' => $remaining,
                'attendees'  => $this->normalise_attendees_for_ticket( $attendees, $ticket_key, $qty ),
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


        foreach ( $addons as $raw_index => $addon ) {
            $qty       = isset( $addon['qty'] ) ? max( 0, (int) $addon['qty'] ) : 0;
            $addon_key = isset( $addon['addon_key'] ) ? sanitize_key( (string) $addon['addon_key'] ) : sanitize_key( (string) $raw_index );

            if ( $event_id && isset( $event_addons[ $addon_key ] ) ) {
                // Trust the Event CPT add-on definition rather than the browser-submitted values.
                $event_addon = $event_addons[ $addon_key ];
                $addon_name  = sanitize_text_field( $event_addon['name'] ?? '' );
                $price       = isset( $event_addon['price'] ) ? (float) $event_addon['price'] : 0;
                $image       = normalise_image_url( $event_addon['image'] ?? '' );
                $stock       = $event_addon['stock'] ?? null;
                $sold        = get_event_addon_sold_count( $event_id, $addon_key, $addon_name );
                $remaining   = get_ticket_remaining_stock( $stock, $sold );
                $applies_to  = sanitize_text_field( (string) ( $event_addon['applies_to'] ?? '' ) );

                if ( $qty > 0 && $applies_to && ! $this->order_has_ticket_label( $clean_tickets, $applies_to ) ) {
                    return new WP_REST_Response(
                        [ 'error' => sprintf( '%s is only available with a %s ticket.', $addon_name, $applies_to ) ],
                        400
                    );
                }

                if ( $qty > 0 && $remaining !== null && $qty > $remaining ) {
                    return new WP_REST_Response(
                        [ 'error' => sprintf( 'Only %d %s add-on%s remaining.', $remaining, $addon_name, $remaining === 1 ? '' : 's' ) ],
                        400
                    );
                }
            } else {
                $addon_name  = isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : '';
                $price       = isset( $addon['price'] ) ? (float) $addon['price'] : 0;
                $image       = isset( $addon['image'] ) ? esc_url_raw( $addon['image'] ) : '';
                $stock       = null;
                $remaining   = null;
                $applies_to  = '';
                $event_addon = [];
            }

            $clean_addons[] = [
                'addon_key' => $addon_key,
                'qty'       => $qty,
                'name'      => $addon_name,
                'price'     => $price,
                'image'     => $image,
                'stock'     => normalise_ticket_stock_value( $stock ),
                'remaining_at_purchase' => $remaining,
                'applies_to' => $applies_to ?? '',
                'addon_id'   => isset( $event_addon['addon_id'] ) ? (int) $event_addon['addon_id'] : (int) ( $addon['addon_id'] ?? 0 ),
                'scope'      => isset( $event_addon['scope'] ) ? sanitize_key( (string) $event_addon['scope'] ) : '',
                'context'    => isset( $event_addon['context'] ) ? sanitize_key( (string) $event_addon['context'] ) : '',
            ];

            if ( $qty > 0 && $price > 0 && $addon_name ) {
                $amount_cents  = (int) round( $price * 100 );
                $total_amount += $amount_cents * $qty;

                $line_items[] = [
                    'quantity'   => $qty,
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => $amount_cents,
                        'product_data' => [ 'name' => 'Add-on: ' . $addon_name ],
                    ],
                ];
            }
        }

        if ( empty( $line_items ) ) {
            return new WP_REST_Response( [ 'error' => 'Please select at least one ticket.' ], 400 );
        }

        $discount = null;
        if ( $discount_code ) {
            $discount = calculate_discount_for_order( $discount_code, $total_amount, $event_id );
            if ( empty( $discount['valid'] ) ) {
                return new WP_REST_Response( [ 'error' => $discount['error'] ?? 'Discount code is not valid.' ], 400 );
            }
        }

        $discount_amount_cents = $discount['discount_cents'] ?? 0;
        $final_amount = max( 0, $total_amount - (int) $discount_amount_cents );

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
        Customer::maybe_create_or_link_user_for_order( $order_id, $name, $email, $create_account );
        update_post_meta( $order_id, '_ets_tickets', $clean_tickets );
        update_post_meta( $order_id, '_ets_addons', $clean_addons );
        update_post_meta( $order_id, '_ets_subtotal_amount_cents', $total_amount );
        update_post_meta( $order_id, '_ets_discount_amount_cents', (int) $discount_amount_cents );
        update_post_meta( $order_id, '_ets_total_amount_cents', $final_amount );
        update_post_meta( $order_id, '_ets_status', 'pending' );

        if ( ! empty( $discount['valid'] ) ) {
            update_post_meta( $order_id, '_ets_discount_id', (int) $discount['discount_id'] );
            update_post_meta( $order_id, '_ets_discount_code', sanitize_text_field( $discount['code'] ) );
            update_post_meta( $order_id, '_ets_discount_type', sanitize_text_field( $discount['type'] ) );
        }
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
        if ( $ticket_pdf_layout ) {
            update_post_meta( $order_id, '_ets_ticket_pdf_layout', $ticket_pdf_layout );
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

        if ( ! empty( $discount['valid'] ) && ! empty( $discount_amount_cents ) ) {
            $coupon_id = $this->create_stripe_coupon( $secret_key, (int) $discount_amount_cents, sanitize_text_field( $discount['code'] ) );
            if ( is_wp_error( $coupon_id ) ) {
                return new WP_REST_Response( [ 'error' => 'Could not apply discount: ' . $coupon_id->get_error_message() ], 500 );
            }
            $body['discounts[0][coupon]'] = $coupon_id;
            update_post_meta( $order_id, '_ets_stripe_coupon_id', sanitize_text_field( $coupon_id ) );
        }

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


    public function validate_discount( WP_REST_Request $request ): WP_REST_Response {
        $event_id = (int) $request->get_param( 'ets_event_id' );
        $code     = normalise_discount_code( $request->get_param( 'ets_discount_code' ) );
        $tickets  = (array) $request->get_param( 'ets_tickets' );
        $addons   = (array) $request->get_param( 'ets_addons' );

        $subtotal = $this->calculate_request_subtotal_cents( $tickets, $event_id, $addons );

        if ( $subtotal <= 0 ) {
            return new WP_REST_Response( [ 'error' => 'Please select at least one ticket before applying a discount.' ], 400 );
        }

        $discount = calculate_discount_for_order( $code, $subtotal, $event_id );

        if ( empty( $discount['valid'] ) ) {
            return new WP_REST_Response( [ 'error' => $discount['error'] ?? 'Discount code is not valid.' ], 400 );
        }

        return new WP_REST_Response( [
            'valid'    => true,
            'code'     => $discount['code'],
            'message'  => $discount['message'],
            'subtotal' => esc_money_gbp( $subtotal / 100 ),
            'discount' => esc_money_gbp( $discount['discount_cents'] / 100 ),
            'total'    => esc_money_gbp( $discount['total_cents'] / 100 ),
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount['discount_cents'],
            'total_cents'    => $discount['total_cents'],
        ], 200 );
    }

    private function calculate_request_subtotal_cents( array $tickets, int $event_id = 0, array $addons = [] ): int {
        $event_ticket_types = $event_id ? $this->get_event_ticket_types( $event_id ) : [];
        $event_addons       = $event_id ? $this->get_event_addons( $event_id ) : [];
        $total = 0;

        foreach ( $tickets as $raw_index => $ticket ) {
            $qty = isset( $ticket['qty'] ) ? max( 0, (int) $ticket['qty'] ) : 0;
            if ( $qty <= 0 ) {
                continue;
            }

            $ticket_key = isset( $ticket['ticket_key'] ) ? (int) $ticket['ticket_key'] : (int) $raw_index;

            if ( $event_id && isset( $event_ticket_types[ $ticket_key ] ) ) {
                $price = isset( $event_ticket_types[ $ticket_key ]['price'] ) ? (float) $event_ticket_types[ $ticket_key ]['price'] : 0;
            } else {
                $price = isset( $ticket['price'] ) ? (float) $ticket['price'] : 0;
            }

            if ( $price > 0 ) {
                $total += (int) round( $price * 100 ) * $qty;
            }
        }


        foreach ( $addons as $raw_index => $addon ) {
            $qty = isset( $addon['qty'] ) ? max( 0, (int) $addon['qty'] ) : 0;
            if ( $qty <= 0 ) {
                continue;
            }

            $addon_key = isset( $addon['addon_key'] ) ? sanitize_key( (string) $addon['addon_key'] ) : sanitize_key( (string) $raw_index );

            if ( $event_id && isset( $event_addons[ $addon_key ] ) ) {
                $price = isset( $event_addons[ $addon_key ]['price'] ) ? (float) $event_addons[ $addon_key ]['price'] : 0;
            } else {
                $price = isset( $addon['price'] ) ? (float) $addon['price'] : 0;
            }

            if ( $price > 0 ) {
                $total += (int) round( $price * 100 ) * $qty;
            }
        }

        return $total;
    }

    private function create_stripe_coupon( string $secret_key, int $amount_off_cents, string $code ) {
        $response = wp_remote_post( 'https://api.stripe.com/v1/coupons', [
            'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
            'body'    => [
                'amount_off' => $amount_off_cents,
                'currency'   => 'gbp',
                'duration'   => 'once',
                'name'       => 'ETS ' . $code . ' ' . current_time( 'mysql' ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['id'] ) ) {
            return new \WP_Error( 'stripe_coupon_error', $body['error']['message'] ?? 'Stripe did not return a coupon ID.' );
        }

        return sanitize_text_field( $body['id'] );
    }


    private function order_has_ticket_label( array $tickets, string $label ): bool {
        $target = sanitize_title( $label );

        foreach ( $tickets as $ticket ) {
            if ( (int) ( $ticket['qty'] ?? 0 ) <= 0 ) {
                continue;
            }

            if ( sanitize_title( (string) ( $ticket['label'] ?? '' ) ) === $target ) {
                return true;
            }
        }

        return false;
    }

    private function normalise_attendees_for_ticket( array $attendees, int $ticket_key, int $qty ): array {
        if ( $qty <= 0 ) {
            return [];
        }

        $raw_attendees = $attendees[ $ticket_key ] ?? [];
        if ( ! is_array( $raw_attendees ) ) {
            $raw_attendees = [];
        }

        $clean = [];

        for ( $i = 0; $i < $qty; $i++ ) {
            $row = isset( $raw_attendees[ $i ] ) && is_array( $raw_attendees[ $i ] ) ? $raw_attendees[ $i ] : [];

            $clean[] = [
                'name'  => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
                'email' => sanitize_email( (string) ( $row['email'] ?? '' ) ),
            ];
        }

        return $clean;
    }

    private function get_event_ticket_types( int $event_id ): array {
        if ( ! $event_id ) {
            return [];
        }

        $ticket_types = function_exists( 'get_field' ) ? get_field( 'ets_ticket_types', $event_id ) : get_post_meta( $event_id, 'ets_ticket_types', true );

        if ( ! is_array( $ticket_types ) ) {
            return [];
        }

        return $ticket_types;
    }


    private function get_event_addons( int $event_id ): array {
        if ( ! $event_id ) {
            return [];
        }

        $ticket_types = $this->get_event_ticket_types( $event_id );
        return get_event_available_addons( $event_id, $ticket_types );
    }

    private function reduce_event_stock_for_order( int $order_id ): void {
        if ( get_post_meta( $order_id, '_ets_stock_reduced', true ) === 'yes' ) {
            return;
        }

        $event_id = (int) get_post_meta( $order_id, '_ets_event_id', true );
        if ( ! $event_id ) {
            return;
        }

        $tickets = get_post_meta( $order_id, '_ets_tickets', true );
        if ( ! is_array( $tickets ) ) {
            return;
        }

        $sold = get_event_ticket_stock_sold( $event_id );

        foreach ( $tickets as $ticket ) {
            $qty = (int) ( $ticket['qty'] ?? 0 );
            if ( $qty <= 0 ) {
                continue;
            }

            $label = sanitize_text_field( $ticket['label'] ?? '' );
            $ticket_key = isset( $ticket['ticket_key'] ) ? (int) $ticket['ticket_key'] : 0;
            $stock = normalise_ticket_stock_value( $ticket['stock'] ?? null );

            // Blank/non-numeric stock means unlimited, so there is nothing to reduce.
            if ( $stock === null || ! $label ) {
                continue;
            }

            $key = ticket_stock_key( $ticket_key, $label );
            $sold[ $key ] = isset( $sold[ $key ] ) ? (int) $sold[ $key ] + $qty : $qty;
        }


        $addons = get_post_meta( $order_id, '_ets_addons', true );
        if ( is_array( $addons ) ) {
            $addon_sold = get_event_addon_stock_sold( $event_id );

            foreach ( $addons as $addon ) {
                $qty = (int) ( $addon['qty'] ?? 0 );
                if ( $qty <= 0 ) {
                    continue;
                }

                $name = sanitize_text_field( $addon['name'] ?? '' );
                $addon_key = isset( $addon['addon_key'] ) ? sanitize_key( (string) $addon['addon_key'] ) : '0';
                $stock = normalise_ticket_stock_value( $addon['stock'] ?? null );

                if ( $stock === null || ! $name ) {
                    continue;
                }

                $key = addon_stock_key( $addon_key, $name );
                $addon_sold[ $key ] = isset( $addon_sold[ $key ] ) ? (int) $addon_sold[ $key ] + $qty : $qty;
            }

            update_post_meta( $event_id, '_ets_addon_stock_sold', $addon_sold );
        }

        update_post_meta( $event_id, '_ets_ticket_stock_sold', $sold );
        update_post_meta( $order_id, '_ets_stock_reduced', 'yes' );
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

        $this->reduce_event_stock_for_order( $order_id );
        increment_discount_usage_for_order( $order_id );

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

        $addons      = get_post_meta( $order_id, '_ets_addons', true );
        $addons      = is_array( $addons ) ? $addons : [];
        $allocations = $this->build_ticket_addon_allocations( $addons );
        $generated   = [];

        foreach ( $tickets as $ticket ) {
            $qty   = (int) ( $ticket['qty'] ?? 0 );
            $label = sanitize_text_field( $ticket['label'] ?? '' );
            $price = (float) ( $ticket['price'] ?? 0 );
            $image = esc_url_raw( (string) ( $ticket['image'] ?? '' ) );

            if ( $qty <= 0 || ! $label ) {
                continue;
            }

            $attendees = isset( $ticket['attendees'] ) && is_array( $ticket['attendees'] ) ? $ticket['attendees'] : [];

            for ( $i = 1; $i <= $qty; $i++ ) {
                $attendee       = $attendees[ $i - 1 ] ?? [];
                $ticket_addons  = $this->allocate_addons_for_ticket( $allocations, $label );

                $generated[] = [
                    'ticket_id'      => strtoupper( wp_generate_password( 10, false, false ) ),
                    'ticket_key'     => (int) ( $ticket['ticket_key'] ?? 0 ),
                    'ticket_kind'    => 'ticket',
                    'type'           => $label,
                    'price'          => $price,
                    'image'          => $image,
                    'addons'         => $ticket_addons,
                    'attendee_name'  => sanitize_text_field( (string) ( $attendee['name'] ?? '' ) ),
                    'attendee_email' => sanitize_email( (string) ( $attendee['email'] ?? '' ) ),
                ];
            }
        }

        // Event-wide add-ons become their own digital tickets/passes.
        foreach ( $addons as $addon ) {
            if ( ! $this->is_event_wide_addon( $addon ) ) {
                continue;
            }

            $qty = (int) ( $addon['qty'] ?? 0 );
            if ( $qty <= 0 ) {
                continue;
            }

            $summary = $this->normalise_addon_summary( $addon );
            if ( empty( $summary['name'] ) ) {
                continue;
            }

            for ( $i = 1; $i <= $qty; $i++ ) {
                $generated[] = [
                    'ticket_id'      => strtoupper( wp_generate_password( 10, false, false ) ),
                    'ticket_key'     => -1,
                    'ticket_kind'    => 'event_addon',
                    'type'           => $summary['name'],
                    'price'          => $summary['price'],
                    'image'          => $summary['image'],
                    'addons'         => [],
                    'attendee_name'  => '',
                    'attendee_email' => '',
                    'addon_key'      => $summary['addon_key'],
                    'addon_id'       => $summary['addon_id'],
                ];
            }
        }

        update_post_meta( $order_id, '_ets_generated_tickets', $generated );
    }

    private function build_ticket_addon_allocations( array $addons ): array {
        $allocations = [
            'by_label' => [],
            'any'      => [],
        ];

        foreach ( $addons as $addon ) {
            if ( ! $this->is_per_ticket_addon( $addon ) ) {
                continue;
            }

            $qty = (int) ( $addon['qty'] ?? 0 );
            if ( $qty <= 0 ) {
                continue;
            }

            $summary = $this->normalise_addon_summary( $addon );
            if ( empty( $summary['name'] ) ) {
                continue;
            }

            $target_label = sanitize_title( (string) ( $addon['applies_to'] ?? '' ) );

            for ( $i = 0; $i < $qty; $i++ ) {
                if ( $target_label ) {
                    $allocations['by_label'][ $target_label ][] = $summary;
                } else {
                    $allocations['any'][] = $summary;
                }
            }
        }

        return $allocations;
    }

    private function allocate_addons_for_ticket( array &$allocations, string $ticket_label ): array {
        $addons = [];
        $key    = sanitize_title( $ticket_label );

        if ( $key && ! empty( $allocations['by_label'][ $key ] ) ) {
            $addons[] = array_shift( $allocations['by_label'][ $key ] );
        }

        if ( ! empty( $allocations['any'] ) ) {
            $addons[] = array_shift( $allocations['any'] );
        }

        return array_values( array_filter( $addons ) );
    }

    private function is_event_wide_addon( array $addon ): bool {
        $context = sanitize_key( (string) ( $addon['context'] ?? '' ) );
        $scope   = sanitize_key( (string) ( $addon['scope'] ?? '' ) );

        if ( $context === 'event' ) {
            return true;
        }

        if ( $scope === 'event' && $context !== 'per_ticket' ) {
            return true;
        }

        // Legacy add-ons without a target label behave like event-wide add-ons.
        return $scope === 'legacy' && empty( $addon['applies_to'] );
    }

    private function is_per_ticket_addon( array $addon ): bool {
        $context = sanitize_key( (string) ( $addon['context'] ?? '' ) );
        $scope   = sanitize_key( (string) ( $addon['scope'] ?? '' ) );

        if ( $context === 'per_ticket' ) {
            return true;
        }

        if ( $scope === 'per_ticket' ) {
            return true;
        }

        return $scope === 'legacy' && ! empty( $addon['applies_to'] );
    }

    private function normalise_addon_summary( array $addon ): array {
        return [
            'addon_key' => sanitize_key( (string) ( $addon['addon_key'] ?? '' ) ),
            'addon_id'  => (int) ( $addon['addon_id'] ?? 0 ),
            'name'      => sanitize_text_field( (string) ( $addon['name'] ?? '' ) ),
            'price'     => (float) ( $addon['price'] ?? 0 ),
            'image'     => esc_url_raw( (string) ( $addon['image'] ?? '' ) ),
        ];
    }}
