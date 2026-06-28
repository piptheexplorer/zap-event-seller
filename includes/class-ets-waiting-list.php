<?php
namespace ETS;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Waiting_List {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'admin_menu', [ $this, 'register_submenu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        add_action( 'save_post_ets_waiting_list', [ $this, 'save_waiting_list_meta' ] );
        add_filter( 'manage_ets_waiting_list_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_ets_waiting_list_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'admin_post_ets_notify_waiting_list', [ $this, 'handle_notify_waiting_list' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
    }

    public function register_cpt(): void {
        register_post_type( 'ets_waiting_list', [
            'labels' => [
                'name'               => 'Waiting List',
                'singular_name'      => 'Waiting List Entry',
                'menu_name'          => 'Waiting List',
                'add_new_item'       => 'Add Waiting List Entry',
                'edit_item'          => 'Edit Waiting List Entry',
                'search_items'       => 'Search Waiting List',
                'not_found'          => 'No waiting list entries found',
                'not_found_in_trash' => 'No waiting list entries found in Trash',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title' ],
            'menu_icon'    => 'dashicons-list-view',
        ] );
    }

    public function register_submenu(): void {
        add_submenu_page(
            'ets-ticket-settings',
            'Waiting List',
            'Waiting List',
            'manage_options',
            'edit.php?post_type=ets_waiting_list'
        );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'ets/v1', '/join-waiting-list', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'join_waiting_list' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function join_waiting_list( WP_REST_Request $request ): WP_REST_Response {
        $name         = sanitize_text_field( (string) $request->get_param( 'ets_waiting_name' ) );
        $email        = sanitize_email( (string) $request->get_param( 'ets_waiting_email' ) );
        $phone        = sanitize_text_field( (string) $request->get_param( 'ets_waiting_phone' ) );
        $event_id     = (int) $request->get_param( 'ets_event_id' );
        $event_title  = sanitize_text_field( (string) $request->get_param( 'ets_event_title' ) );
        $ticket_key   = (int) $request->get_param( 'ets_ticket_key' );
        $ticket_label = sanitize_text_field( (string) $request->get_param( 'ets_ticket_label' ) );
        $qty          = max( 1, (int) $request->get_param( 'ets_waiting_qty' ) );

        if ( empty( $name ) || empty( $email ) || empty( $ticket_label ) ) {
            return new WP_REST_Response( [ 'error' => 'Please enter your name and email.' ], 400 );
        }

        if ( ! $event_title && $event_id ) {
            $event_title = get_the_title( $event_id );
        }

        $existing_id = $this->find_existing_waiting_entry( $email, $event_id, $ticket_key, $ticket_label );

        if ( $existing_id ) {
            update_post_meta( $existing_id, '_ets_waiting_name', $name );
            update_post_meta( $existing_id, '_ets_waiting_phone', $phone );
            update_post_meta( $existing_id, '_ets_waiting_qty', $qty );
            update_post_meta( $existing_id, '_ets_waiting_updated_at', current_time( 'mysql' ) );

            return new WP_REST_Response( [
                'success' => true,
                'message' => 'You are already on the waiting list. We have updated your details.',
                'entry_id' => $existing_id,
            ], 200 );
        }

        $entry_id = wp_insert_post( [
            'post_type'   => 'ets_waiting_list',
            'post_status' => 'publish',
            'post_title'  => trim( $name . ' - ' . $ticket_label . ' - ' . current_time( 'mysql' ) ),
        ] );

        if ( ! $entry_id || is_wp_error( $entry_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Could not add you to the waiting list. Please try again.' ], 500 );
        }

        update_post_meta( $entry_id, '_ets_waiting_name', $name );
        update_post_meta( $entry_id, '_ets_waiting_email', $email );
        update_post_meta( $entry_id, '_ets_waiting_phone', $phone );
        update_post_meta( $entry_id, '_ets_waiting_event_id', $event_id );
        update_post_meta( $entry_id, '_ets_waiting_event_title', $event_title );
        update_post_meta( $entry_id, '_ets_waiting_ticket_key', $ticket_key );
        update_post_meta( $entry_id, '_ets_waiting_ticket_label', $ticket_label );
        update_post_meta( $entry_id, '_ets_waiting_qty', $qty );
        update_post_meta( $entry_id, '_ets_waiting_status', 'waiting' );
        update_post_meta( $entry_id, '_ets_waiting_created_at', current_time( 'mysql' ) );

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'You have joined the waiting list. We will contact you if tickets become available.',
            'entry_id' => $entry_id,
        ], 200 );
    }

    private function find_existing_waiting_entry( string $email, int $event_id, int $ticket_key, string $ticket_label ): int {
        $query = new \WP_Query( [
            'post_type'      => 'ets_waiting_list',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_ets_waiting_email',
                    'value' => $email,
                ],
                [
                    'key'   => '_ets_waiting_event_id',
                    'value' => $event_id,
                ],
                [
                    'key'   => '_ets_waiting_ticket_key',
                    'value' => $ticket_key,
                ],
                [
                    'key'   => '_ets_waiting_ticket_label',
                    'value' => $ticket_label,
                ],
                [
                    'key'     => '_ets_waiting_status',
                    'value'   => [ 'waiting', 'notified' ],
                    'compare' => 'IN',
                ],
            ],
        ] );

        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }

    public function register_metaboxes(): void {
        add_meta_box(
            'ets_waiting_list_details',
            'Waiting List Details',
            [ $this, 'render_waiting_list_metabox' ],
            'ets_waiting_list',
            'normal',
            'high'
        );
    }

    public function render_waiting_list_metabox( \WP_Post $post ): void {
        wp_nonce_field( 'ets_save_waiting_list', 'ets_waiting_list_nonce' );

        $name         = get_post_meta( $post->ID, '_ets_waiting_name', true );
        $email        = get_post_meta( $post->ID, '_ets_waiting_email', true );
        $phone        = get_post_meta( $post->ID, '_ets_waiting_phone', true );
        $event_id     = (int) get_post_meta( $post->ID, '_ets_waiting_event_id', true );
        $event_title  = get_post_meta( $post->ID, '_ets_waiting_event_title', true );
        $ticket_label = get_post_meta( $post->ID, '_ets_waiting_ticket_label', true );
        $qty          = (int) get_post_meta( $post->ID, '_ets_waiting_qty', true );
        $status       = get_post_meta( $post->ID, '_ets_waiting_status', true ) ?: 'waiting';
        $notified_at  = get_post_meta( $post->ID, '_ets_waiting_notified_at', true );
        ?>
        <table class="form-table">
            <tr><th><label for="ets_waiting_name">Name</label></th><td><input type="text" class="regular-text" id="ets_waiting_name" name="ets_waiting_name" value="<?php echo esc_attr( $name ); ?>"></td></tr>
            <tr><th><label for="ets_waiting_email">Email</label></th><td><input type="email" class="regular-text" id="ets_waiting_email" name="ets_waiting_email" value="<?php echo esc_attr( $email ); ?>"></td></tr>
            <tr><th><label for="ets_waiting_phone">Phone</label></th><td><input type="text" class="regular-text" id="ets_waiting_phone" name="ets_waiting_phone" value="<?php echo esc_attr( $phone ); ?>"></td></tr>
            <tr><th>Event</th><td><?php echo $event_id && get_post( $event_id ) ? '<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a>' : esc_html( $event_title ?: '—' ); ?></td></tr>
            <tr><th><label for="ets_waiting_ticket_label">Ticket Type</label></th><td><input type="text" class="regular-text" id="ets_waiting_ticket_label" name="ets_waiting_ticket_label" value="<?php echo esc_attr( $ticket_label ); ?>"></td></tr>
            <tr><th><label for="ets_waiting_qty">Quantity Requested</label></th><td><input type="number" class="small-text" id="ets_waiting_qty" name="ets_waiting_qty" value="<?php echo esc_attr( (string) max( 1, $qty ) ); ?>" min="1"></td></tr>
            <tr>
                <th><label for="ets_waiting_status">Status</label></th>
                <td>
                    <select id="ets_waiting_status" name="ets_waiting_status">
                        <option value="waiting" <?php selected( $status, 'waiting' ); ?>>Waiting</option>
                        <option value="notified" <?php selected( $status, 'notified' ); ?>>Notified</option>
                        <option value="converted" <?php selected( $status, 'converted' ); ?>>Converted</option>
                        <option value="removed" <?php selected( $status, 'removed' ); ?>>Removed</option>
                    </select>
                    <?php if ( $notified_at ) : ?><p class="description">Last notified: <?php echo esc_html( $notified_at ); ?></p><?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ( $email ) : ?>
            <hr>
            <p><strong>Notify customer</strong></p>
            <p class="description">Sends an email telling this customer that tickets may be available again.</p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ets_notify_waiting_list&entry_id=' . $post->ID ), 'ets_notify_waiting_list_' . $post->ID ) ); ?>">Send availability email</a>
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_waiting_list_meta( int $post_id ): void {
        if ( ! isset( $_POST['ets_waiting_list_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ets_waiting_list_nonce'] ) ), 'ets_save_waiting_list' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, '_ets_waiting_name', sanitize_text_field( wp_unslash( $_POST['ets_waiting_name'] ?? '' ) ) );
        update_post_meta( $post_id, '_ets_waiting_email', sanitize_email( wp_unslash( $_POST['ets_waiting_email'] ?? '' ) ) );
        update_post_meta( $post_id, '_ets_waiting_phone', sanitize_text_field( wp_unslash( $_POST['ets_waiting_phone'] ?? '' ) ) );
        update_post_meta( $post_id, '_ets_waiting_ticket_label', sanitize_text_field( wp_unslash( $_POST['ets_waiting_ticket_label'] ?? '' ) ) );
        update_post_meta( $post_id, '_ets_waiting_qty', max( 1, (int) wp_unslash( $_POST['ets_waiting_qty'] ?? 1 ) ) );

        $allowed_statuses = [ 'waiting', 'notified', 'converted', 'removed' ];
        $status = sanitize_key( (string) wp_unslash( $_POST['ets_waiting_status'] ?? 'waiting' ) );
        update_post_meta( $post_id, '_ets_waiting_status', in_array( $status, $allowed_statuses, true ) ? $status : 'waiting' );
    }

    public function add_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['ets_event'] = 'Event';
                $new['ets_ticket'] = 'Ticket';
                $new['ets_customer'] = 'Customer';
                $new['ets_qty'] = 'Qty';
                $new['ets_status'] = 'Status';
            }
        }
        return $new;
    }

    public function render_column( string $column, int $post_id ): void {
        if ( $column === 'ets_event' ) {
            $event_id = (int) get_post_meta( $post_id, '_ets_waiting_event_id', true );
            $title = get_post_meta( $post_id, '_ets_waiting_event_title', true );
            echo $event_id && get_post( $event_id ) ? '<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a>' : esc_html( $title ?: '—' );
        }
        if ( $column === 'ets_ticket' ) {
            echo esc_html( get_post_meta( $post_id, '_ets_waiting_ticket_label', true ) ?: '—' );
        }
        if ( $column === 'ets_customer' ) {
            echo esc_html( get_post_meta( $post_id, '_ets_waiting_name', true ) ?: '—' );
            $email = get_post_meta( $post_id, '_ets_waiting_email', true );
            if ( $email ) {
                echo '<br><small>' . esc_html( $email ) . '</small>';
            }
        }
        if ( $column === 'ets_qty' ) {
            echo esc_html( (string) (int) get_post_meta( $post_id, '_ets_waiting_qty', true ) );
        }
        if ( $column === 'ets_status' ) {
            echo esc_html( ucfirst( get_post_meta( $post_id, '_ets_waiting_status', true ) ?: 'waiting' ) );
        }
    }

    public function handle_notify_waiting_list(): void {
        $entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0;

        if ( ! $entry_id || get_post_type( $entry_id ) !== 'ets_waiting_list' ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=ets_waiting_list&ets_waiting_notice=invalid' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ets_notify_waiting_list_' . $entry_id ) ) {
            wp_die( 'You are not allowed to notify this waiting list entry.' );
        }

        $sent = $this->send_notification_email( $entry_id );

        wp_safe_redirect( add_query_arg( 'ets_waiting_notice', $sent ? 'sent' : 'failed', get_edit_post_link( $entry_id, 'raw' ) ) );
        exit;
    }

    private function send_notification_email( int $entry_id ): bool {
        $email = get_post_meta( $entry_id, '_ets_waiting_email', true );
        if ( ! $email ) {
            return false;
        }

        $name = get_post_meta( $entry_id, '_ets_waiting_name', true );
        $event_title = get_post_meta( $entry_id, '_ets_waiting_event_title', true );
        $ticket_label = get_post_meta( $entry_id, '_ets_waiting_ticket_label', true );
        $dashboard_url = get_setting( 'customer_dashboard_url', home_url( '/' ) );

        $from_name = get_setting( 'email_from_name', get_bloginfo( 'name' ) );
        $from_email = get_setting( 'email_from_address', get_bloginfo( 'admin_email' ) );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $subject = 'Tickets may be available for ' . ( $event_title ?: 'your event' );
        $message = '<h2>Tickets may be available</h2>';
        $message .= '<p>Hi ' . esc_html( $name ?: 'there' ) . ',</p>';
        $message .= '<p>You joined the waiting list for <strong>' . esc_html( $ticket_label ) . '</strong>';
        if ( $event_title ) {
            $message .= ' at <strong>' . esc_html( $event_title ) . '</strong>';
        }
        $message .= '.</p>';
        $message .= '<p>Please visit the event page to check current availability and complete your booking.</p>';
        $message .= '<p><a href="' . esc_url( $dashboard_url ?: home_url( '/' ) ) . '" style="display:inline-block;padding:12px 18px;background:#000;color:#fff;text-decoration:none;border-radius:6px;">View tickets</a></p>';

        $sent = wp_mail( $email, $subject, $message, $headers );

        if ( $sent ) {
            update_post_meta( $entry_id, '_ets_waiting_status', 'notified' );
            update_post_meta( $entry_id, '_ets_waiting_notified_at', current_time( 'mysql' ) );
        }

        return $sent;
    }

    public function admin_notices(): void {
        if ( empty( $_GET['ets_waiting_notice'] ) ) {
            return;
        }

        $notice = sanitize_key( (string) $_GET['ets_waiting_notice'] );
        if ( $notice === 'sent' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Waiting list notification sent.</p></div>';
        } elseif ( $notice === 'failed' ) {
            echo '<div class="notice notice-error is-dismissible"><p>Could not send waiting list notification.</p></div>';
        }
    }
}
