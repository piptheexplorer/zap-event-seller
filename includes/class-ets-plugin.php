<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static $instance = null;

    public Settings $settings;
    public Orders_CPT $orders;
    public Block $block;
    public Stripe $stripe;
    public PDF $pdf;
    public Shortcodes $shortcodes;
    public Checkin $checkin;
    public Discounts $discounts;
    public Reports $reports;
    public Customer $customer;
    public Roles $roles;
    public Waiting_List $waiting_list;
    public $event_fields;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-settings.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-orders-cpt.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-block.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-event-fields.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-stripe.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-pdf.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-shortcodes.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-checkin.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-discounts.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-reports.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-customer.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-roles.php';
        require_once ETS_PLUGIN_DIR . 'includes/class-ets-waiting-list.php';

        $this->settings   = new Settings();
        $this->orders     = new Orders_CPT();
        $this->stripe     = new Stripe();
        $this->pdf        = new PDF();
        $this->block      = new Block();
        $this->shortcodes = new Shortcodes();
        $this->checkin    = new Checkin();
        $this->discounts = new Discounts();
        $this->reports   = new Reports();
        $this->customer  = new Customer();
        $this->roles     = new Roles();
        $this->waiting_list = new Waiting_List();
        $this->event_fields = new Event_Fields();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        wp_enqueue_script(
            'ets-ticket-block',
            ETS_PLUGIN_URL . 'assets/js/ticket-block.js',
            [],
            '4.1.0',
            true
        );

        wp_enqueue_style(
            'ets-tailwind-output',
            ETS_PLUGIN_URL . 'assets/css/tailwind-output.css',
            [],
            '4.1.0'
        );

        wp_enqueue_style(
            'ets-ticket-block',
            ETS_PLUGIN_URL . 'assets/css/ticket-block.css',
            [ 'ets-tailwind-output' ],
            '4.1.0'
        );

        wp_register_script(
            'ets-html5-qrcode',
            'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
            [],
            '2.3.8',
            true
        );

        wp_register_script(
            'ets-checkin',
            ETS_PLUGIN_URL . 'assets/js/checkin.js',
            [ 'ets-html5-qrcode' ],
            '4.1.0',
            true
        );

        wp_register_style(
            'ets-checkin',
            ETS_PLUGIN_URL . 'assets/css/ticket-block.css',
            [],
            '4.1.0'
        );
    }
}
