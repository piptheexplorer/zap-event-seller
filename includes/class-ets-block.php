<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;

}

class Block{

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_acf_block' ] );
    }

    public function register_acf_block(): void {
        if ( ! function_exists( 'acf_register_block_type' ) ) {
            return;
        }

        acf_register_block_type( [
            'name'            => 'ets_ticket_block',
            'title'           => __( 'Ticket Sales Block', 'ets' ),
            'description'     => __( 'Sell event tickets via Stripe Checkout.', 'ets' ),
            'render_callback' => [ $this, 'render' ],
            'category'        => 'widgets',
            'icon'            => 'tickets-alt',
            'keywords'        => [ 'ticket', 'event', 'checkout', 'stripe' ],
            'mode'            => 'edit',
            'supports'        => [ 'align' => false ],
        ] );

        $this->register_event_selector_field_group();
    }

    private function register_event_selector_field_group(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $event_post_type = get_setting( 'event_post_type', 'events' );
        $event_post_type = $event_post_type ?: 'events';

        acf_add_local_field_group( [
            'key'    => 'group_ets_ticket_event_source',
            'title'  => 'Ticket Event Source',
            'fields' => [
                [
                    'key'           => 'field_ets_event_id',
                    'label'         => 'Use Existing Event',
                    'name'          => 'ets_event_id',
                    'type'          => 'post_object',
                    'instructions'  => 'Optional. Select an existing Event CPT post to use as the source for this ticket block. If left empty, the block will use its own fields.',
                    'post_type'     => [ $event_post_type ],
                    'return_format' => 'id',
                    'ui'            => 1,
                    'allow_null'    => 1,
                    'multiple'      => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/ets_ticket_block',
                    ],
                ],
            ],
            'position' => 'side',
        ] );
    }

    public function render( $block, $content = '', $is_preview = false, $post_id = 0 ): void {
        $event_id       = $this->normalise_event_id( get_field( 'ets_event_id' ) );
        $event_title    = get_field( 'ets_event_title' );
        $event_heading  = get_field( 'ets_event_heading' );
        $event_desc     = get_field( 'ets_event_description' );
        $event_date     = get_field( 'ets_event_date' );
        $event_time     = get_field( 'ets_event_time' );
        $event_location = get_field( 'ets_event_location' );
        $event_map      = get_field( 'ets_event_map' );
        $ticket_types   = get_field( 'ets_ticket_types' );
        $addons         = get_field( 'ets_event_addons' );
        $ticket_style   = get_field( 'ets_ticket_style' ) ?: 'table';
        $ticket_design  = get_field( 'ets_ticket_design' );
        $ticket_pdf_layout = get_field( 'ets_ticket_pdf_layout' ) ?: get_setting( 'ticket_pdf_layout', 'classic_landscape' );
        $policy_text    = get_setting( 'purchase_policy_text', '' );

        if ( $event_id ) {
            $event_data     = $this->get_event_data( $event_id );
            $event_title    = $event_data['title'] ?: $event_title;
            $event_heading  = $event_data['heading'] ?: $event_heading;
            $event_desc     = $event_data['description'] ?: $event_desc;
            $event_date     = $event_data['date'] ?: $event_date;
            $event_time     = $event_data['time'] ?: $event_time;
            $event_location = $event_data['location'] ?: $event_location;
            $event_map      = $event_data['map'] ?: $event_map;
            $ticket_types   = ! empty( $event_data['ticket_types'] ) ? $event_data['ticket_types'] : $ticket_types;
            $addons         = isset( $event_data['addons'] ) && is_array( $event_data['addons'] ) ? $event_data['addons'] : $addons;
            $ticket_design  = $event_data['ticket_design'] ?: $ticket_design;
            $ticket_pdf_layout = $event_data['ticket_pdf_layout'] ?: $ticket_pdf_layout;
        }

        $ticket_types = $this->normalise_ticket_types( $ticket_types, $event_id );
        $addons       = $this->normalise_addons( $addons, $event_id );

        if ( empty( $ticket_types ) || ! is_array( $ticket_types ) ) {
            echo '<p>Please add at least one ticket type in the block settings.</p>';
            return;
        }

        $publishable_key = get_setting( 'stripe_publishable_key', '' );

        if ( empty( $publishable_key ) ) {
            echo '<p><strong>Stripe is not configured.</strong> Please add your publishable key in Ticket Seller settings.</p>';
            return;
        }

        $block_id = 'ets-ticket-block-' . esc_attr( $block['id'] );
        $rest_url = esc_url( rest_url( 'ets/v1/create-checkout-session' ) );

        include ETS_PLUGIN_DIR . 'templates/block-ticket.php';
    }

    private function normalise_event_id( $value ): int {
        if ( is_numeric( $value ) ) {
            return (int) $value;
        }

        if ( $value instanceof \WP_Post ) {
            return (int) $value->ID;
        }

        if ( is_array( $value ) && isset( $value['ID'] ) ) {
            return (int) $value['ID'];
        }

        return 0;
    }

    private function get_event_data( int $event_id ): array {
        $post = get_post( $event_id );

        if ( ! $post ) {
            return [];
        }

        return [
            'id'            => $event_id,
            'title'         => get_the_title( $event_id ),
            'heading'       => $this->get_event_field( $event_id, [ 'ets_event_heading', 'event_heading' ], '' ),
            'description'   => $this->get_event_description( $event_id ),
            'date'          => $this->get_event_field( $event_id, [ 'ets_event_date', 'event_date', 'date', 'start_date', 'event_start_date' ], '' ),
            'time'          => $this->get_event_field( $event_id, [ 'ets_event_time', 'event_time', 'time', 'start_time', 'event_start_time' ], '' ),
            'location'      => $this->get_event_field( $event_id, [ 'ets_event_location', 'event_location', 'location', 'venue' ], '' ),
            'map'           => $this->get_event_field( $event_id, [ 'ets_event_map', 'event_map', 'map' ], '' ),
            'addons'        => $this->normalise_addons( $this->get_event_field( $event_id, [ 'ets_event_addons', 'event_addons', 'addons' ], [] ), $event_id ),
            'ticket_types'  => $this->normalise_ticket_types( $this->get_event_field( $event_id, [ 'ets_ticket_types', 'ticket_types', 'tickets' ], [] ), $event_id ),
            'ticket_design' => $this->get_event_field( $event_id, [ 'ets_ticket_design', 'ticket_design' ], '' ),
            'ticket_pdf_layout' => $this->get_event_field( $event_id, [ 'ets_ticket_pdf_layout', 'ticket_pdf_layout' ], '' ),
        ];
    }

    private function get_event_field( int $event_id, array $keys, $default = '' ) {
        foreach ( $keys as $key ) {
            $value = function_exists( 'get_field' ) ? get_field( $key, $event_id ) : get_post_meta( $event_id, $key, true );

            if ( $value !== null && $value !== false && $value !== '' && $value !== [] ) {
                return $value;
            }
        }

        return $default;
    }

    private function get_event_description( int $event_id ): string {
        $acf_description = $this->get_event_field( $event_id, [ 'ets_event_description', 'event_description', 'description' ], '' );

        if ( $acf_description ) {
            return (string) $acf_description;
        }

        $excerpt = get_the_excerpt( $event_id );
        if ( $excerpt ) {
            return (string) $excerpt;
        }

        $post = get_post( $event_id );
        return $post ? wp_strip_all_tags( $post->post_content ) : '';
    }

    private function normalise_ticket_types( $ticket_types, int $event_id = 0 ): array {
        if ( ! is_array( $ticket_types ) ) {
            return [];
        }

        $normalised = [];

        foreach ( $ticket_types as $index => $ticket ) {
            if ( ! is_array( $ticket ) ) {
                continue;
            }

            $label = isset( $ticket['label'] ) ? sanitize_text_field( $ticket['label'] ) : '';
            $stock = $ticket['stock'] ?? null;
            $sold  = $event_id ? get_event_ticket_sold_count( $event_id, (int) $index, $label ) : 0;
            $remaining = get_ticket_remaining_stock( $stock, $sold );

            $ticket['_ets_index']     = (int) $index;
            $ticket['_ets_stock']     = normalise_ticket_stock_value( $stock );
            $ticket['_ets_sold']      = $sold;
            $ticket['_ets_remaining'] = $remaining;
            $ticket['_ets_sold_out']  = ( $remaining !== null && $remaining <= 0 );

            $normalised[ $index ] = $ticket;
        }

        return $normalised;
    }


    private function normalise_addons( $addons, int $event_id = 0 ): array {
        if ( ! is_array( $addons ) ) {
            return [];
        }

        $normalised = [];

        foreach ( $addons as $index => $addon ) {
            if ( ! is_array( $addon ) ) {
                continue;
            }

            $name = isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : '';
            if ( ! $name ) {
                continue;
            }

            $stock = $addon['stock'] ?? null;
            $sold  = $event_id ? get_event_addon_sold_count( $event_id, (int) $index, $name ) : 0;
            $remaining = get_ticket_remaining_stock( $stock, $sold );

            $addon['_ets_index']     = (int) $index;
            $addon['_ets_stock']     = normalise_ticket_stock_value( $stock );
            $addon['_ets_sold']      = $sold;
            $addon['_ets_remaining'] = $remaining;
            $addon['_ets_sold_out']  = ( $remaining !== null && $remaining <= 0 );

            $normalised[ $index ] = $addon;
        }

        return $normalised;
    }



}
