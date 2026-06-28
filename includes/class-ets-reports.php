<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Reports {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_reports_page' ] );
    }

    public function register_reports_page(): void {
        add_submenu_page(
            'ets-ticket-settings',
            'Event Reports',
            'Event Reports',
            'manage_options',
            'ets-event-reports',
            [ $this, 'render_reports_page' ]
        );
    }

    public function render_reports_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ets' ) );
        }

        $event_post_type  = get_setting( 'event_post_type', 'events' );
        $selected_event_id = isset( $_GET['ets_event_id'] ) ? max( 0, (int) $_GET['ets_event_id'] ) : 0;
        $date_from        = isset( $_GET['ets_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['ets_date_from'] ) ) : '';
        $date_to          = isset( $_GET['ets_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['ets_date_to'] ) ) : '';

        $events = $this->get_events_for_filter( $event_post_type );
        $orders = $this->get_orders( $selected_event_id, $date_from, $date_to );
        $stats  = $this->build_stats( $orders );

        include ETS_PLUGIN_DIR . 'templates/admin-event-reports.php';
    }

    private function get_events_for_filter( string $event_post_type ): array {
        if ( ! post_type_exists( $event_post_type ) ) {
            return [];
        }

        return get_posts( [
            'post_type'      => $event_post_type,
            'posts_per_page' => 500,
            'post_status'    => [ 'publish', 'future', 'draft', 'private' ],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    private function get_orders( int $event_id = 0, string $date_from = '', string $date_to = '' ): array {
        $meta_query = [];

        if ( $event_id ) {
            $meta_query[] = [
                'key'     => '_ets_event_id',
                'value'   => $event_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        $date_query = [];
        if ( $date_from ) {
            $date_query['after'] = $date_from;
        }
        if ( $date_to ) {
            $date_query['before'] = $date_to;
            $date_query['inclusive'] = true;
        }

        $args = [
            'post_type'      => 'ticket_order',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        if ( ! empty( $date_query ) ) {
            $args['date_query'] = [ $date_query ];
        }

        return get_posts( $args );
    }

    private function build_stats( array $orders ): array {
        $stats = [
            'orders_total'            => 0,
            'orders_paid'             => 0,
            'orders_pending'          => 0,
            'revenue_cents'           => 0,
            'subtotal_cents'          => 0,
            'discount_cents'          => 0,
            'tickets_sold'            => 0,
            'tickets_generated'       => 0,
            'tickets_checked_in'      => 0,
            'ticket_types'            => [],
            'events'                  => [],
            'discounts'               => [],
            'recent_orders'           => [],
        ];

        foreach ( $orders as $order ) {
            $order_id = (int) $order->ID;
            $stats['orders_total']++;

            $status = (string) get_post_meta( $order_id, '_ets_status', true );
            if ( $status === 'paid' ) {
                $stats['orders_paid']++;
            } else {
                $stats['orders_pending']++;
            }

            $event_id    = (int) get_post_meta( $order_id, '_ets_event_id', true );
            $event_title = (string) get_post_meta( $order_id, '_ets_event_title', true );
            if ( ! $event_title && $event_id ) {
                $event_title = get_the_title( $event_id );
            }
            if ( ! $event_title ) {
                $event_title = 'Unknown event';
            }

            if ( ! isset( $stats['events'][ $event_id ] ) ) {
                $stats['events'][ $event_id ] = [
                    'event_id'      => $event_id,
                    'event_title'   => $event_title,
                    'orders'        => 0,
                    'paid_orders'   => 0,
                    'revenue_cents' => 0,
                    'tickets_sold'  => 0,
                    'checked_in'    => 0,
                ];
            }

            $stats['events'][ $event_id ]['orders']++;

            $subtotal = (int) get_post_meta( $order_id, '_ets_subtotal_amount_cents', true );
            $discount = (int) get_post_meta( $order_id, '_ets_discount_amount_cents', true );
            $total    = (int) get_post_meta( $order_id, '_ets_total_amount_cents', true );

            $tickets = get_post_meta( $order_id, '_ets_tickets', true );
            $generated = get_post_meta( $order_id, '_ets_generated_tickets', true );

            $order_ticket_count = 0;
            if ( is_array( $tickets ) ) {
                foreach ( $tickets as $ticket ) {
                    $qty   = max( 0, (int) ( $ticket['qty'] ?? 0 ) );
                    $label = sanitize_text_field( (string) ( $ticket['label'] ?? 'Ticket' ) );
                    $price = (float) ( $ticket['price'] ?? 0 );

                    if ( $qty <= 0 ) {
                        continue;
                    }

                    $order_ticket_count += $qty;

                    if ( ! isset( $stats['ticket_types'][ $label ] ) ) {
                        $stats['ticket_types'][ $label ] = [
                            'label'         => $label,
                            'qty'           => 0,
                            'revenue_cents' => 0,
                        ];
                    }

                    $line_revenue = (int) round( $price * 100 ) * $qty;
                    $stats['ticket_types'][ $label ]['qty'] += $qty;
                    $stats['ticket_types'][ $label ]['revenue_cents'] += $line_revenue;
                }
            }

            $generated_count = 0;
            $checked_in_count = 0;
            if ( is_array( $generated ) ) {
                $generated_count = count( $generated );
                foreach ( $generated as $ticket ) {
                    if ( ! empty( $ticket['checked_in'] ) ) {
                        $checked_in_count++;
                    }
                }
            }

            if ( $status === 'paid' ) {
                $stats['revenue_cents'] += $total;
                $stats['subtotal_cents'] += $subtotal;
                $stats['discount_cents'] += $discount;
                $stats['tickets_sold'] += $order_ticket_count;
                $stats['events'][ $event_id ]['paid_orders']++;
                $stats['events'][ $event_id ]['revenue_cents'] += $total;
                $stats['events'][ $event_id ]['tickets_sold'] += $order_ticket_count;
            }

            $stats['tickets_generated'] += $generated_count;
            $stats['tickets_checked_in'] += $checked_in_count;
            $stats['events'][ $event_id ]['checked_in'] += $checked_in_count;

            $discount_code = (string) get_post_meta( $order_id, '_ets_discount_code', true );
            if ( $discount_code ) {
                if ( ! isset( $stats['discounts'][ $discount_code ] ) ) {
                    $stats['discounts'][ $discount_code ] = [
                        'code'          => $discount_code,
                        'uses'          => 0,
                        'discount_cents'=> 0,
                    ];
                }
                $stats['discounts'][ $discount_code ]['uses']++;
                $stats['discounts'][ $discount_code ]['discount_cents'] += $discount;
            }

            if ( count( $stats['recent_orders'] ) < 12 ) {
                $stats['recent_orders'][] = [
                    'order_id'      => $order_id,
                    'title'         => get_the_title( $order_id ),
                    'customer_name' => (string) get_post_meta( $order_id, '_ets_customer_name', true ),
                    'customer_email'=> (string) get_post_meta( $order_id, '_ets_customer_email', true ),
                    'event_title'   => $event_title,
                    'status'        => $status ?: 'unknown',
                    'total_cents'   => $total,
                    'tickets'       => $order_ticket_count,
                    'date'          => get_the_date( 'Y-m-d H:i', $order_id ),
                    'edit_url'      => get_edit_post_link( $order_id ),
                ];
            }
        }

        uasort( $stats['ticket_types'], function ( $a, $b ) {
            return $b['qty'] <=> $a['qty'];
        } );

        uasort( $stats['events'], function ( $a, $b ) {
            return $b['revenue_cents'] <=> $a['revenue_cents'];
        } );

        uasort( $stats['discounts'], function ( $a, $b ) {
            return $b['uses'] <=> $a['uses'];
        } );

        return $stats;
    }
}
