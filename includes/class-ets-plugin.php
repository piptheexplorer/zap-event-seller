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

        $this->settings   = new Settings();
        $this->orders     = new Orders_CPT();
        $this->stripe     = new Stripe();
        $this->pdf        = new PDF();
        $this->block      = new Block();
        $this->shortcodes = new Shortcodes();
        $this->event_fields = new Event_Fields();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        wp_enqueue_script(
            'ets-ticket-block',
            ETS_PLUGIN_URL . 'assets/js/ticket-block.js',
            [],
            '3.3.0',
            true
        );

        wp_enqueue_style(
            'ets-tailwind-output',
            ETS_PLUGIN_URL . 'assets/css/tailwind-output.css',
            [],
            '3.3.0'
        );

        wp_enqueue_style(
            'ets-ticket-block',
            ETS_PLUGIN_URL . 'assets/css/ticket-block.css',
            [ 'ets-tailwind-output' ],
            '3.3.0'
        );
    }
}
