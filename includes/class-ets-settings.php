<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings_page(): void {
        add_menu_page(
            'Ticket Seller Settings',
            'Ticket Seller',
            'manage_options',
            'ets-ticket-settings',
            [ $this, 'render_settings_page' ],
            'dashicons-admin-generic',
            26
        );

        // Explicit first submenu so the top-level Ticket Seller item always opens Settings.
        add_submenu_page(
            'ets-ticket-settings',
            'Settings',
            'Settings',
            'manage_options',
            'ets-ticket-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            ETS_OPTION_GROUP,
            ETS_OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => [],
            ]
        );

        add_settings_section( 'ets_stripe_section', 'Stripe Settings', '__return_false', 'ets-ticket-settings' );

        $stripe_fields = [
            'stripe_publishable_key' => 'Stripe Publishable Key (pk_...)',
            'stripe_secret_key'      => 'Stripe Secret Key (sk_...)',
            'stripe_success_url'     => 'Success URL',
            'stripe_cancel_url'      => 'Cancel URL',
        ];

        foreach ( $stripe_fields as $key => $label ) {
            add_settings_field( $key, $label, [ $this, 'render_text_field' ], 'ets-ticket-settings', 'ets_stripe_section', [ 'option_key' => $key ] );
        }

        add_settings_section( 'ets_event_integration_section', 'Event Integration', '__return_false', 'ets-ticket-settings' );

        add_settings_field(
            'event_post_type',
            'Existing Events CPT Slug',
            [ $this, 'render_text_field' ],
            'ets-ticket-settings',
            'ets_event_integration_section',
            [
                'option_key'  => 'event_post_type',
                'description' => 'Enter the post type slug for your existing Events CPT. Common examples: event, events, tribe_events.',
            ]
        );


        add_settings_section( 'ets_checkout_section', 'Checkout Experience', '__return_false', 'ets-ticket-settings' );

        add_settings_field(
            'checkout_experience',
            'Checkout Style',
            [ $this, 'render_select_field' ],
            'ets-ticket-settings',
            'ets_checkout_section',
            [
                'option_key'  => 'checkout_experience',
                'description' => 'Choose how buyers move through the ticket purchase form.',
                'choices'     => [
                    'single'     => 'Single page checkout',
                    'multi_step' => 'Multi-step checkout',
                ],
                'default'     => 'single',
            ]
        );

        add_settings_section( 'ets_policy_section', 'Purchase Policy / T&Cs', '__return_false', 'ets-ticket-settings' );
        add_settings_field(
            'purchase_policy_text',
            'Policy Text (shown below purchase button)',
            [ $this, 'render_textarea_field' ],
            'ets-ticket-settings',
            'ets_policy_section',
            [
                'option_key'  => 'purchase_policy_text',
                'description' => 'Displayed beside the required terms checkbox. Safe HTML is allowed.',
            ]
        );



        add_settings_section( 'ets_customer_section', 'Customer Accounts', '__return_false', 'ets-ticket-settings' );

        add_settings_field(
            'customer_dashboard_url',
            'Customer Dashboard URL',
            [ $this, 'render_text_field' ],
            'ets-ticket-settings',
            'ets_customer_section',
            [
                'option_key'  => 'customer_dashboard_url',
                'description' => 'Page URL containing [ets_customer_dashboard] or [ets_my_tickets]. Example: https://example.com/profile/',
            ]
        );

        add_settings_section( 'ets_email_section', 'Email Settings', '__return_false', 'ets-ticket-settings' );

        $email_fields = [
            'email_from_name'          => 'From Name',
            'email_from_address'       => 'From Email',
            'admin_notification_email' => 'Admin Notification Email',
        ];

        foreach ( $email_fields as $key => $label ) {
            add_settings_field( $key, $label, [ $this, 'render_text_field' ], 'ets-ticket-settings', 'ets_email_section', [ 'option_key' => $key ] );
        }


        add_settings_section( 'ets_digital_ticket_section', 'Digital Ticket Delivery', '__return_false', 'ets-ticket-settings' );

        add_settings_field(
            'email_ticket_pdf_attachments',
            'Attach Ticket PDFs to Customer Email',
            [ $this, 'render_checkbox_field' ],
            'ets-ticket-settings',
            'ets_digital_ticket_section',
            [
                'option_key'  => 'email_ticket_pdf_attachments',
                'description' => 'When enabled, each generated ticket PDF is attached to the customer confirmation email. Customers can still download tickets from the profile/thank-you pages.',
            ]
        );

        add_settings_field(
            'ticket_pdf_layout',
            'Default Ticket PDF Design',
            [ $this, 'render_select_field' ],
            'ets-ticket-settings',
            'ets_digital_ticket_section',
            [
                'option_key'   => 'ticket_pdf_layout',
                'description'  => 'Choose the default PDF ticket design. Events can override this from their Event Ticket Settings.',
                'choices'      => [
                    'classic_landscape' => 'Classic landscape ticket',
                    'digital_wallet'    => 'Digital wallet ticket',
                    'modern_card'       => 'Modern card ticket',
                ],
                'default'      => 'classic_landscape',
            ]
        );

        add_settings_section( 'ets_email_template_section', 'Email Templates', '__return_false', 'ets-ticket-settings' );

        add_settings_field(
            'email_download_button_template',
            'Download Button Template',
            [ $this, 'render_textarea_field' ],
            'ets-ticket-settings',
            'ets_email_template_section',
            [
                'option_key'  => 'email_download_button_template',
                'description' => 'Use {download_url} for the ticket download/profile link.',
            ]
        );

        add_settings_field(
            'email_template_customer',
            'Customer Confirmation Email',
            [ $this, 'render_textarea_field' ],
            'ets-ticket-settings',
            'ets_email_template_section',
            [
                'option_key'  => 'email_template_customer',
                'description' => 'Available placeholders: {customer_name}, {event_title}, {event_date}, {event_time}, {event_location}, {tickets}, {download_button}',
            ]
        );
    }

    public function sanitize_settings( $input ): array {
        $output = [];

        if ( ! is_array( $input ) ) {
            return $output;
        }

        $html_fields = [
            'purchase_policy_text',
            'email_template_customer',
            'email_download_button_template',
        ];

        $checkbox_fields = [
            'email_ticket_pdf_attachments',
        ];

        foreach ( $input as $key => $value ) {
            if ( in_array( $key, $html_fields, true ) ) {
                $output[ $key ] = wp_kses_post( $value );
            } elseif ( in_array( $key, $checkbox_fields, true ) ) {
                $output[ $key ] = ! empty( $value ) ? '1' : '0';
            } else {
                $output[ $key ] = sanitize_text_field( $value );
            }
        }

        return $output;
    }

    public function render_text_field( array $args ): void {
        $options     = get_settings();
        $key         = $args['option_key'];
        $value       = $options[ $key ] ?? '';
        $description = $args['description'] ?? '';
        ?>
        <input type="text"
               id="<?php echo esc_attr( $key ); ?>"
               name="<?php echo esc_attr( ETS_OPTION_NAME . '[' . $key . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" />
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_textarea_field( array $args ): void {
        $options     = get_settings();
        $key         = $args['option_key'];
        $value       = $options[ $key ] ?? '';
        $description = $args['description'] ?? '';
        ?>
        <textarea id="<?php echo esc_attr( $key ); ?>"
                  name="<?php echo esc_attr( ETS_OPTION_NAME . '[' . $key . ']' ); ?>"
                  rows="5"
                  class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }


    public function render_select_field( array $args ): void {
        $options     = get_settings();
        $key         = $args['option_key'];
        $value       = $options[ $key ] ?? ( $args['default'] ?? '' );
        $description = $args['description'] ?? '';
        $choices     = $args['choices'] ?? [];
        ?>
        <select id="<?php echo esc_attr( $key ); ?>"
                name="<?php echo esc_attr( ETS_OPTION_NAME . '[' . $key . ']' ); ?>">
            <?php foreach ( $choices as $choice_value => $choice_label ) : ?>
                <option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, $choice_value ); ?>>
                    <?php echo esc_html( $choice_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_checkbox_field( array $args ): void {
        $options     = get_settings();
        $key         = $args['option_key'];
        $value       = ! empty( $options[ $key ] );
        $description = $args['description'] ?? '';
        ?>
        <label for="<?php echo esc_attr( $key ); ?>">
            <input type="checkbox"
                   id="<?php echo esc_attr( $key ); ?>"
                   name="<?php echo esc_attr( ETS_OPTION_NAME . '[' . $key . ']' ); ?>"
                   value="1"
                   <?php checked( $value ); ?> />
            Enable digital ticket PDF attachments
        </label>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1>Event Ticket Seller Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( ETS_OPTION_GROUP );
                do_settings_sections( 'ets-ticket-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
