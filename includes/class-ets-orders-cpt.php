<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Orders_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_ticket_order_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_order_metabox' ] );
        add_filter( 'manage_ticket_order_posts_columns', [ $this, 'add_order_columns' ] );
        add_action( 'manage_ticket_order_posts_custom_column', [ $this, 'render_order_column' ], 10, 2 );
    }

    public function register_ticket_order_cpt(): void {
        $labels = [
            'name'               => 'Ticket Orders',
            'singular_name'      => 'Ticket Order',
            'menu_name'          => 'Ticket Orders',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Ticket Order',
            'edit_item'          => 'Edit Ticket Order',
            'new_item'           => 'New Ticket Order',
            'view_item'          => 'View Ticket Order',
            'search_items'       => 'Search Ticket Orders',
            'not_found'          => 'No ticket orders found',
            'not_found_in_trash' => 'No ticket orders found in Trash',
        ];

        register_post_type( 'ticket_order', [
            'label'        => 'Ticket Orders',
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'supports'     => [ 'title', 'custom-fields' ],
            'menu_icon'    => 'dashicons-tickets-alt',
        ] );
    }

    public function register_order_metabox(): void {
        add_meta_box(
            'ets_order_details',
            'Order Details',
            [ $this, 'render_order_details_metabox' ],
            'ticket_order',
            'normal',
            'high'
        );
    }


    public function add_order_columns( array $columns ): array {
        $new = [];

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;

            if ( $key === 'title' ) {
                $new['ets_event'] = 'Event';
                $new['ets_status'] = 'Status';
            }
        }

        return $new;
    }

    public function render_order_column( string $column, int $post_id ): void {
        if ( $column === 'ets_event' ) {
            $event_id = (int) get_post_meta( $post_id, '_ets_event_id', true );
            $title    = get_post_meta( $post_id, '_ets_event_title', true );

            if ( $event_id && get_post( $event_id ) ) {
                echo '<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a>';
            } elseif ( $title ) {
                echo esc_html( $title );
            } else {
                echo '—';
            }
        }

        if ( $column === 'ets_status' ) {
            $status = get_post_meta( $post_id, '_ets_status', true );
            echo esc_html( $status ? ucfirst( (string) $status ) : '—' );
        }
    }

    public function render_order_details_metabox( \WP_Post $post ): void {
        $name           = get_post_meta( $post->ID, '_ets_customer_name', true );
        $email          = get_post_meta( $post->ID, '_ets_customer_email', true );
        $phone          = get_post_meta( $post->ID, '_ets_customer_phone', true );
        $tickets        = get_post_meta( $post->ID, '_ets_tickets', true );
        $total          = (int) get_post_meta( $post->ID, '_ets_total_amount_cents', true );
        $event_id       = (int) get_post_meta( $post->ID, '_ets_event_id', true );
        $event_title    = get_post_meta( $post->ID, '_ets_event_title', true );
        $event_date     = get_post_meta( $post->ID, '_ets_event_date', true );
        $event_time     = get_post_meta( $post->ID, '_ets_event_time', true );
        $event_location = get_post_meta( $post->ID, '_ets_event_location', true );
        $status         = get_post_meta( $post->ID, '_ets_status', true );
        $stripe_id      = get_post_meta( $post->ID, '_ets_stripe_session_id', true );
        $generated      = get_post_meta( $post->ID, '_ets_generated_tickets', true );
        $ticket_design  = get_post_meta( $post->ID, '_ets_ticket_design', true );

        include ETS_PLUGIN_DIR . 'templates/admin-order-metabox.php';
    }
}
