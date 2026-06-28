<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Addons {

    public function __construct() {
        add_action( 'init', [ $this, 'register_addon_cpt' ] );
        add_action( 'acf/init', [ $this, 'register_acf_fields' ] );
        add_action( 'admin_menu', [ $this, 'register_addon_submenu' ], 21 );
        add_filter( 'manage_ets_addon_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_ets_addon_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function register_addon_cpt(): void {
        register_post_type( 'ets_addon', [
            'labels' => [
                'name'               => 'Add-ons',
                'singular_name'      => 'Add-on',
                'menu_name'          => 'Add-ons',
                'add_new_item'       => 'Add New Add-on',
                'edit_item'          => 'Edit Add-on',
                'search_items'       => 'Search Add-ons',
                'not_found'          => 'No add-ons found',
                'not_found_in_trash' => 'No add-ons found in Trash',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title', 'thumbnail' ],
            'menu_icon'    => 'dashicons-plus-alt2',
        ] );
    }

    public function register_addon_submenu(): void {
        add_submenu_page(
            'ets-ticket-settings',
            'Add-ons',
            'Add-ons',
            'manage_options',
            'edit.php?post_type=ets_addon'
        );
    }

    public function register_acf_fields(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'    => 'group_ets_addon_fields',
            'title'  => 'Add-on Details',
            'fields' => [
                [
                    'key'   => 'field_ets_addon_description_cpt',
                    'label' => 'Description',
                    'name'  => 'ets_addon_description',
                    'type'  => 'textarea',
                    'rows'  => 3,
                ],
                [
                    'key'     => 'field_ets_addon_price_cpt',
                    'label'   => 'Price',
                    'name'    => 'ets_addon_price',
                    'type'    => 'number',
                    'prepend' => '£',
                    'step'    => '0.01',
                    'min'     => 0,
                ],
                [
                    'key'   => 'field_ets_addon_stock_cpt',
                    'label' => 'Stock',
                    'name'  => 'ets_addon_stock',
                    'type'  => 'number',
                    'min'   => 0,
                    'instructions' => 'Optional. Leave blank for unlimited.',
                ],
                [
                    'key'           => 'field_ets_addon_image_cpt',
                    'label'         => 'Add-on Image',
                    'name'          => 'ets_addon_image',
                    'type'          => 'image',
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                    'instructions'  => 'Optional. Featured image is used as a fallback.',
                ],
                [
                    'key'           => 'field_ets_addon_scope_cpt',
                    'label'         => 'Add-on Type',
                    'name'          => 'ets_addon_scope',
                    'type'          => 'select',
                    'choices'       => [
                        'event'      => 'Event-wide add-on',
                        'per_ticket' => 'Per-ticket add-on',
                        'both'       => 'Both',
                    ],
                    'default_value' => 'event',
                    'ui'            => 1,
                    'instructions'  => 'Event-wide add-ons are shown once per order. Per-ticket add-ons can be attached to specific ticket types.',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'ets_addon',
                    ],
                ],
            ],
        ] );
    }

    public function add_columns( array $columns ): array {
        $columns['ets_price'] = 'Price';
        $columns['ets_stock'] = 'Stock';
        $columns['ets_scope'] = 'Type';
        return $columns;
    }

    public function render_column( string $column, int $post_id ): void {
        if ( $column === 'ets_price' ) {
            echo esc_html( esc_money_gbp( get_addon_price( $post_id ) ) );
        }

        if ( $column === 'ets_stock' ) {
            $stock = get_addon_stock( $post_id );
            echo $stock === null ? 'Unlimited' : esc_html( (string) $stock );
        }

        if ( $column === 'ets_scope' ) {
            $scope = get_addon_scope( $post_id );
            $labels = [
                'event'      => 'Event-wide',
                'per_ticket' => 'Per-ticket',
                'both'       => 'Both',
            ];
            echo esc_html( $labels[ $scope ] ?? ucfirst( $scope ) );
        }
    }
}
