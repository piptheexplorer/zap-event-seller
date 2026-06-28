<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Discounts {

    public function __construct() {
        add_action( 'init', [ $this, 'register_discount_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        add_action( 'save_post_ets_discount', [ $this, 'save_discount_meta' ] );
        add_filter( 'manage_ets_discount_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_ets_discount_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'admin_menu', [ $this, 'register_discount_submenu' ], 20 );
    }

    public function register_discount_cpt(): void {
        register_post_type( 'ets_discount', [
            'labels' => [
                'name'               => 'Discount Codes',
                'singular_name'      => 'Discount Code',
                'menu_name'          => 'Discount Codes',
                'add_new_item'       => 'Add New Discount Code',
                'edit_item'          => 'Edit Discount Code',
                'search_items'       => 'Search Discount Codes',
                'not_found'          => 'No discount codes found',
                'not_found_in_trash' => 'No discount codes found in Trash',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title' ],
            'menu_icon'    => 'dashicons-tag',
        ] );
    }


    public function register_discount_submenu(): void {
        add_submenu_page(
            'ets-ticket-settings',
            'Discount Codes',
            'Discount Codes',
            'manage_options',
            'edit.php?post_type=ets_discount'
        );
    }

    public function register_metaboxes(): void {
        add_meta_box(
            'ets_discount_details',
            'Discount Details',
            [ $this, 'render_discount_metabox' ],
            'ets_discount',
            'normal',
            'high'
        );
    }

    public function render_discount_metabox( \WP_Post $post ): void {
        wp_nonce_field( 'ets_save_discount', 'ets_discount_nonce' );

        $code       = get_post_meta( $post->ID, '_ets_discount_code', true ) ?: $post->post_title;
        $type       = get_post_meta( $post->ID, '_ets_discount_type', true ) ?: 'percent';
        $amount     = get_post_meta( $post->ID, '_ets_discount_amount', true );
        $expiry     = get_post_meta( $post->ID, '_ets_discount_expiry', true );
        $usage      = get_post_meta( $post->ID, '_ets_discount_usage_limit', true );
        $used       = (int) get_post_meta( $post->ID, '_ets_discount_used_count', true );
        $min_total  = get_post_meta( $post->ID, '_ets_discount_min_total', true );
        $event_id   = (int) get_post_meta( $post->ID, '_ets_discount_event_id', true );
        $enabled    = get_post_meta( $post->ID, '_ets_discount_enabled', true );
        $enabled    = $enabled === '' ? 'yes' : $enabled;
        $event_type = get_setting( 'event_post_type', 'events' );
        $events     = get_posts( [
            'post_type'      => $event_type,
            'posts_per_page' => 200,
            'post_status'    => [ 'publish', 'future', 'draft', 'private' ],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="ets_discount_code">Code</label></th>
                <td>
                    <input type="text" id="ets_discount_code" name="ets_discount_code" value="<?php echo esc_attr( strtoupper( (string) $code ) ); ?>" class="regular-text" style="text-transform:uppercase;">
                    <p class="description">Example: EARLYBIRD, SAVE10, VIP25.</p>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_enabled">Enabled</label></th>
                <td>
                    <select id="ets_discount_enabled" name="ets_discount_enabled">
                        <option value="yes" <?php selected( $enabled, 'yes' ); ?>>Yes</option>
                        <option value="no" <?php selected( $enabled, 'no' ); ?>>No</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_type">Discount Type</label></th>
                <td>
                    <select id="ets_discount_type" name="ets_discount_type">
                        <option value="percent" <?php selected( $type, 'percent' ); ?>>Percentage</option>
                        <option value="fixed" <?php selected( $type, 'fixed' ); ?>>Fixed amount</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_amount">Amount</label></th>
                <td>
                    <input type="number" id="ets_discount_amount" name="ets_discount_amount" value="<?php echo esc_attr( $amount ); ?>" step="0.01" min="0" class="small-text">
                    <p class="description">For percentage, enter 10 for 10%. For fixed amount, enter pounds, e.g. 5 for £5 off.</p>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_expiry">Expiry Date</label></th>
                <td>
                    <input type="date" id="ets_discount_expiry" name="ets_discount_expiry" value="<?php echo esc_attr( $expiry ); ?>">
                    <p class="description">Optional. Leave blank for no expiry.</p>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_usage_limit">Usage Limit</label></th>
                <td>
                    <input type="number" id="ets_discount_usage_limit" name="ets_discount_usage_limit" value="<?php echo esc_attr( $usage ); ?>" min="0" class="small-text">
                    <p class="description">Optional. Leave blank for unlimited. Used: <?php echo esc_html( (string) $used ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_min_total">Minimum Order Total</label></th>
                <td>
                    <input type="number" id="ets_discount_min_total" name="ets_discount_min_total" value="<?php echo esc_attr( $min_total ); ?>" min="0" step="0.01" class="small-text">
                    <p class="description">Optional amount in pounds.</p>
                </td>
            </tr>
            <tr>
                <th><label for="ets_discount_event_id">Limit to Event</label></th>
                <td>
                    <select id="ets_discount_event_id" name="ets_discount_event_id">
                        <option value="0">All events</option>
                        <?php foreach ( $events as $event ) : ?>
                            <option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>>
                                <?php echo esc_html( get_the_title( $event ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_discount_meta( int $post_id ): void {
        if ( ! isset( $_POST['ets_discount_nonce'] ) || ! wp_verify_nonce( $_POST['ets_discount_nonce'], 'ets_save_discount' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $code = isset( $_POST['ets_discount_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['ets_discount_code'] ) ) ) : '';
        $type = isset( $_POST['ets_discount_type'] ) && $_POST['ets_discount_type'] === 'fixed' ? 'fixed' : 'percent';
        $amount = isset( $_POST['ets_discount_amount'] ) ? max( 0, (float) wp_unslash( $_POST['ets_discount_amount'] ) ) : 0;
        $expiry = isset( $_POST['ets_discount_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['ets_discount_expiry'] ) ) : '';
        $usage = isset( $_POST['ets_discount_usage_limit'] ) && $_POST['ets_discount_usage_limit'] !== '' ? max( 0, (int) wp_unslash( $_POST['ets_discount_usage_limit'] ) ) : '';
        $min_total = isset( $_POST['ets_discount_min_total'] ) && $_POST['ets_discount_min_total'] !== '' ? max( 0, (float) wp_unslash( $_POST['ets_discount_min_total'] ) ) : '';
        $event_id = isset( $_POST['ets_discount_event_id'] ) ? max( 0, (int) wp_unslash( $_POST['ets_discount_event_id'] ) ) : 0;
        $enabled = isset( $_POST['ets_discount_enabled'] ) && $_POST['ets_discount_enabled'] === 'no' ? 'no' : 'yes';

        update_post_meta( $post_id, '_ets_discount_code', $code );
        update_post_meta( $post_id, '_ets_discount_type', $type );
        update_post_meta( $post_id, '_ets_discount_amount', $amount );
        update_post_meta( $post_id, '_ets_discount_expiry', $expiry );
        update_post_meta( $post_id, '_ets_discount_usage_limit', $usage );
        update_post_meta( $post_id, '_ets_discount_min_total', $min_total );
        update_post_meta( $post_id, '_ets_discount_event_id', $event_id );
        update_post_meta( $post_id, '_ets_discount_enabled', $enabled );
    }

    public function add_columns( array $columns ): array {
        $columns['ets_code'] = 'Code';
        $columns['ets_discount'] = 'Discount';
        $columns['ets_usage'] = 'Usage';
        $columns['ets_expiry'] = 'Expiry';
        return $columns;
    }

    public function render_column( string $column, int $post_id ): void {
        if ( $column === 'ets_code' ) {
            echo '<code>' . esc_html( get_post_meta( $post_id, '_ets_discount_code', true ) ) . '</code>';
        }

        if ( $column === 'ets_discount' ) {
            $type = get_post_meta( $post_id, '_ets_discount_type', true );
            $amount = (float) get_post_meta( $post_id, '_ets_discount_amount', true );
            echo esc_html( $type === 'fixed' ? esc_money_gbp( $amount ) : number_format( $amount, 2 ) . '%' );
        }

        if ( $column === 'ets_usage' ) {
            $used = (int) get_post_meta( $post_id, '_ets_discount_used_count', true );
            $limit = get_post_meta( $post_id, '_ets_discount_usage_limit', true );
            echo esc_html( $used . ' / ' . ( $limit === '' ? '∞' : $limit ) );
        }

        if ( $column === 'ets_expiry' ) {
            $expiry = get_post_meta( $post_id, '_ets_discount_expiry', true );
            echo esc_html( $expiry ?: '—' );
        }
    }
}
