<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PDF {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'handle_ticket_pdf_download' ], 1 );
    }

    public function handle_ticket_pdf_download(): void {
        if ( ! isset( $_GET['ticket_pdf'], $_GET['ets_order_id'] ) ) {
            return;
        }

        $order_id  = (int) $_GET['ets_order_id'];
        $ticket_id = sanitize_text_field( (string) $_GET['ticket_pdf'] );

        if ( ! $order_id || ! $ticket_id || get_post_type( $order_id ) !== 'ticket_order' ) {
            return;
        }

        $tickets = get_post_meta( $order_id, '_ets_generated_tickets', true );
        if ( ! is_array( $tickets ) ) {
            return;
        }

        foreach ( $tickets as $ticket ) {
            if ( ( $ticket['ticket_id'] ?? '' ) !== $ticket_id ) {
                continue;
            }

            $this->render_pdf( $order_id, $ticket, $ticket_id );
            exit;
        }
    }

    private function render_pdf( int $order_id, array $ticket, string $ticket_id ): void {
        $fpdf_path = ETS_PLUGIN_DIR . 'lib/fpdf/fpdf.php';

        if ( ! file_exists( $fpdf_path ) ) {
            wp_die( 'FPDF not found. Please add it to: ' . esc_html( $fpdf_path ) );
        }

        require_once $fpdf_path;

        $name           = get_post_meta( $order_id, '_ets_customer_name', true );
        $event_title    = get_post_meta( $order_id, '_ets_event_title', true );
        $event_date     = get_post_meta( $order_id, '_ets_event_date', true );
        $event_time     = get_post_meta( $order_id, '_ets_event_time', true );
        $event_location = get_post_meta( $order_id, '_ets_event_location', true );
        $ticket_design  = get_post_meta( $order_id, '_ets_ticket_design', true );

        $ticket_image_path = asset_url_to_path( (string) ( $ticket['image'] ?? '' ) );
        $design_path       = asset_url_to_path( (string) $ticket_design );
        $logo_path         = $this->get_site_logo_path();

        $pdf = new \FPDF( 'L', 'mm', [ 150, 80 ] );
        $pdf->AddPage();
        $pdf->SetAutoPageBreak( false );

        if ( $design_path && file_exists( $design_path ) ) {
            $pdf->Image( $design_path, 0, 0, 150, 80 );
        }

        if ( $ticket_image_path && file_exists( $ticket_image_path ) ) {
            $pdf->Image( $ticket_image_path, 5, 5, 14 );
        }

        if ( $logo_path && file_exists( $logo_path ) ) {
            $pdf->Image( $logo_path, 5, 66, 16 );
        }
    
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->SetFont( 'Arial', 'B', 15 );
        $pdf->SetXY( 10, 10 );
        $pdf->Cell( 130, 10, ( $event_title ?: 'Event' ) . ' - Ticket', 0, 1, 'C' );

        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->SetXY( 10, 24 );
        $pdf->Cell( 80, 7, trim( $event_date . ' / ' . $event_time ), 0, 1 );

        if ( $event_location ) {
            $pdf->SetX( 10 );
            $pdf->Cell( 90, 7, $event_location, 0, 1 );
        }

        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, (string) ( $ticket['type'] ?? '' ), 0, 1 );

        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, 'Price: ' . esc_money_gbp( (float) ( $ticket['price'] ?? 0 ) ), 0, 1 );

        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, 'Name: ' . $name, 0, 1 );
        $pdf->SetFillColor( 0, 220, 10 );
        $pdf->SetFont( 'Arial', 'B', 12 );
        $pdf->SetXY( 10, 57 );
        $pdf->Cell( 130, 8, 'ID: ' . $ticket_id, 0, 1, 'C' );

        $pdf->Output( 'D', 'ticket-' . sanitize_file_name( $ticket_id ) . '.pdf' );
    }

    private function get_site_logo_path(): string {
        $logo_id = (int) get_theme_mod( 'custom_logo' );
        if ( ! $logo_id ) {
            return '';
        }

        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
        return $logo_url ? asset_url_to_path( $logo_url ) : '';
    }
}
