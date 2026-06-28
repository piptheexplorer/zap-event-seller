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

            $this->output_ticket_pdf( $order_id, $ticket, $ticket_id, 'D' );
            exit;
        }
    }

    public function get_ticket_pdf_attachment_paths( int $order_id ): array {
        if ( ! $order_id || get_post_type( $order_id ) !== 'ticket_order' ) {
            return [];
        }

        $tickets = get_post_meta( $order_id, '_ets_generated_tickets', true );
        if ( ! is_array( $tickets ) || empty( $tickets ) ) {
            return [];
        }

        $upload_dir = wp_upload_dir();
        $pdf_dir    = trailingslashit( $upload_dir['basedir'] ) . 'ets-ticket-pdfs/';
        $pdf_url    = trailingslashit( $upload_dir['baseurl'] ) . 'ets-ticket-pdfs/';

        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
        }

        $attachments = [];
        $saved       = [];

        foreach ( $tickets as $ticket ) {
            $ticket_id = sanitize_text_field( (string) ( $ticket['ticket_id'] ?? '' ) );
            if ( ! $ticket_id ) {
                continue;
            }

            $filename = 'ticket-' . sanitize_file_name( $ticket_id ) . '.pdf';
            $path     = $pdf_dir . $filename;

            if ( ! file_exists( $path ) ) {
                $this->output_ticket_pdf( $order_id, $ticket, $ticket_id, 'F', $path );
            }

            if ( file_exists( $path ) ) {
                $attachments[] = $path;
                $saved[] = [
                    'ticket_id' => $ticket_id,
                    'file'      => $pdf_url . $filename,
                    'path'      => $path,
                ];
            }
        }

        if ( ! empty( $saved ) ) {
            update_post_meta( $order_id, '_ets_ticket_pdfs', $saved );
        }

        return $attachments;
    }

    private function output_ticket_pdf( int $order_id, array $ticket, string $ticket_id, string $destination = 'D', string $file_path = '' ): void {
        $fpdf_path = ETS_PLUGIN_DIR . 'lib/fpdf/fpdf.php';

        if ( ! file_exists( $fpdf_path ) ) {
            wp_die( 'FPDF not found. Please add it to: ' . esc_html( $fpdf_path ) );
        }

        require_once $fpdf_path;

        $layout = sanitize_key( (string) get_post_meta( $order_id, '_ets_ticket_pdf_layout', true ) );
        if ( ! $layout ) {
            $layout = sanitize_key( (string) get_setting( 'ticket_pdf_layout', 'classic_landscape' ) );
        }

        if ( ! in_array( $layout, [ 'classic_landscape', 'digital_wallet', 'modern_card' ], true ) ) {
            $layout = 'classic_landscape';
        }

        if ( $layout === 'digital_wallet' ) {
            $pdf = $this->build_digital_wallet_ticket_pdf( $order_id, $ticket, $ticket_id );
        } elseif ( $layout === 'modern_card' ) {
            $pdf = $this->build_modern_card_ticket_pdf( $order_id, $ticket, $ticket_id );
        } else {
            $pdf = $this->build_classic_landscape_ticket_pdf( $order_id, $ticket, $ticket_id );
        }

        if ( $destination === 'F' ) {
            $pdf->Output( 'F', $file_path );
            return;
        }

        $pdf->Output( 'D', 'ticket-' . sanitize_file_name( $ticket_id ) . '.pdf' );
    }

    private function get_ticket_pdf_data( int $order_id, array $ticket, string $ticket_id ): array {
        $ticket_design  = get_post_meta( $order_id, '_ets_ticket_design', true );

        return [
            'name'              => (string) ( $ticket['attendee_name'] ?? '' ) ?: get_post_meta( $order_id, '_ets_customer_name', true ),
            'attendee_email'    => (string) ( $ticket['attendee_email'] ?? '' ),
            'event_title'       => get_post_meta( $order_id, '_ets_event_title', true ) ?: 'Event',
            'event_date'        => get_post_meta( $order_id, '_ets_event_date', true ),
            'event_time'        => get_post_meta( $order_id, '_ets_event_time', true ),
            'event_location'    => get_post_meta( $order_id, '_ets_event_location', true ),
            'ticket_type'       => (string) ( $ticket['type'] ?? '' ),
            'ticket_price'      => (float) ( $ticket['price'] ?? 0 ),
            'ticket_addons'     => isset( $ticket['addons'] ) && is_array( $ticket['addons'] ) ? $ticket['addons'] : [],
            'ticket_id'         => $ticket_id,
            'ticket_image_path' => asset_url_to_path( (string) ( $ticket['image'] ?? '' ) ),
            'design_path'       => asset_url_to_path( (string) $ticket_design ),
            'logo_path'         => $this->get_site_logo_path(),
            'qr_path'           => $this->generate_qr_code_image( $ticket_id, $order_id ),
        ];
    }

    private function build_classic_landscape_ticket_pdf( int $order_id, array $ticket, string $ticket_id ): \FPDF {
        $data = $this->get_ticket_pdf_data( $order_id, $ticket, $ticket_id );

        $pdf = new \FPDF( 'L', 'mm', [ 150, 80 ] );
        $pdf->AddPage();
        $pdf->SetAutoPageBreak( false );

        if ( $data['design_path'] && file_exists( $data['design_path'] ) ) {
            $pdf->Image( $data['design_path'], 0, 0, 150, 80 );
        } else {
            $pdf->SetFillColor( 12, 16, 28 );
            $pdf->Rect( 0, 0, 150, 80, 'F' );
            $pdf->SetFillColor( 36, 49, 82 );
            $pdf->Rect( 102, 0, 48, 80, 'F' );
        }

        if ( $data['ticket_image_path'] && file_exists( $data['ticket_image_path'] ) ) {
            $pdf->Image( $data['ticket_image_path'], 5, 5, 14 );
        }

        if ( $data['logo_path'] && file_exists( $data['logo_path'] ) ) {
            $pdf->Image( $data['logo_path'], 5, 66, 16 );
        }

        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->SetFont( 'Arial', 'B', 15 );
        $pdf->SetXY( 10, 10 );
        $pdf->Cell( 130, 10, $data['event_title'] . ' - Ticket', 0, 1, 'C' );

        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->SetXY( 10, 24 );
        $pdf->Cell( 80, 7, trim( $data['event_date'] . ' / ' . $data['event_time'] ), 0, 1 );

        if ( $data['event_location'] ) {
            $pdf->SetX( 10 );
            $pdf->Cell( 90, 7, $data['event_location'], 0, 1 );
        }

        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, $data['ticket_type'], 0, 1 );
        if ( ! empty( $data['ticket_addons'] ) ) {
            $pdf->SetX( 10 );
            $pdf->Cell( 90, 7, 'Add-ons: ' . $this->format_ticket_addons_for_pdf( $data['ticket_addons'] ), 0, 1 );
        }
        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, 'Price: ' . esc_money_gbp( $data['ticket_price'] ), 0, 1 );
        $pdf->SetX( 10 );
        $pdf->Cell( 90, 7, 'Name: ' . $data['name'], 0, 1 );
        if ( ! empty( $data['attendee_email'] ) ) {
            $pdf->SetX( 10 );
            $pdf->Cell( 90, 7, 'Email: ' . $data['attendee_email'], 0, 1 );
        }

        if ( $data['qr_path'] && file_exists( $data['qr_path'] ) ) {
            $pdf->Image( $data['qr_path'], 124, 50, 20, 20 );
        }

        $pdf->SetFont( 'Arial', 'B', 12 );
        $pdf->SetXY( 10, 57 );
        $pdf->Cell( 105, 8, 'ID: ' . $ticket_id, 0, 1, 'C' );

        return $pdf;
    }

    private function build_digital_wallet_ticket_pdf( int $order_id, array $ticket, string $ticket_id ): \FPDF {
        $data = $this->get_ticket_pdf_data( $order_id, $ticket, $ticket_id );

        $pdf = new \FPDF( 'P', 'mm', [ 80, 140 ] );
        $pdf->AddPage();
        $pdf->SetAutoPageBreak( false );

        $pdf->SetFillColor( 8, 12, 24 );
        $pdf->Rect( 0, 0, 80, 140, 'F' );
        $pdf->SetFillColor( 29, 43, 82 );
        $pdf->Rect( 6, 6, 68, 128, 'F' );

        if ( $data['ticket_image_path'] && file_exists( $data['ticket_image_path'] ) ) {
            $pdf->Image( $data['ticket_image_path'], 6, 6, 68, 35 );
            $pdf->SetFillColor( 0, 0, 0 );
        }

        if ( $data['logo_path'] && file_exists( $data['logo_path'] ) ) {
            $pdf->Image( $data['logo_path'], 10, 10, 16 );
        }

        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->SetFont( 'Arial', 'B', 15 );
        $pdf->SetXY( 10, 44 );
        $pdf->MultiCell( 60, 7, $data['event_title'], 0, 'C' );

        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->SetXY( 10, 62 );
        $pdf->Cell( 60, 6, trim( $data['event_date'] . ' / ' . $data['event_time'] ), 0, 1, 'C' );
        $pdf->SetX( 10 );
        $pdf->Cell( 60, 6, $data['event_location'], 0, 1, 'C' );

        if ( $data['qr_path'] && file_exists( $data['qr_path'] ) ) {
            $pdf->Image( $data['qr_path'], 23, 78, 34, 34 );
        }

        $pdf->SetFont( 'Arial', 'B', 11 );
        $pdf->SetXY( 10, 116 );
        $pdf->Cell( 60, 6, $data['ticket_type'], 0, 1, 'C' );
        if ( ! empty( $data['ticket_addons'] ) ) {
            $pdf->SetFont( 'Arial', '', 8 );
            $pdf->SetX( 10 );
            $pdf->Cell( 60, 5, 'Add-ons: ' . $this->format_ticket_addons_for_pdf( $data['ticket_addons'] ), 0, 1, 'C' );
        }
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetX( 10 );
        $pdf->Cell( 60, 5, 'Name: ' . $data['name'], 0, 1, 'C' );
        if ( ! empty( $data['attendee_email'] ) ) {
            $pdf->SetX( 10 );
            $pdf->Cell( 60, 5, $data['attendee_email'], 0, 1, 'C' );
        }
        $pdf->SetX( 10 );
        $pdf->Cell( 60, 5, 'ID: ' . $ticket_id, 0, 1, 'C' );

        return $pdf;
    }

    private function build_modern_card_ticket_pdf( int $order_id, array $ticket, string $ticket_id ): \FPDF {
        $data = $this->get_ticket_pdf_data( $order_id, $ticket, $ticket_id );

        $pdf = new \FPDF( 'L', 'mm', [ 140, 70 ] );
        $pdf->AddPage();
        $pdf->SetAutoPageBreak( false );

        $pdf->SetFillColor( 245, 247, 250 );
        $pdf->Rect( 0, 0, 140, 70, 'F' );
        $pdf->SetFillColor( 17, 24, 39 );
        $pdf->Rect( 0, 0, 48, 70, 'F' );

        if ( $data['ticket_image_path'] && file_exists( $data['ticket_image_path'] ) ) {
            $pdf->Image( $data['ticket_image_path'], 6, 8, 36, 26 );
        }

        if ( $data['logo_path'] && file_exists( $data['logo_path'] ) ) {
            $pdf->Image( $data['logo_path'], 8, 52, 18 );
        }

        $pdf->SetTextColor( 17, 24, 39 );
        $pdf->SetFont( 'Arial', 'B', 15 );
        $pdf->SetXY( 54, 8 );
        $pdf->MultiCell( 58, 7, $data['event_title'], 0, 'L' );

        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetXY( 54, 26 );
        $pdf->Cell( 58, 5, trim( $data['event_date'] . ' / ' . $data['event_time'] ), 0, 1 );
        $pdf->SetX( 54 );
        $pdf->Cell( 58, 5, $data['event_location'], 0, 1 );

        $pdf->SetFont( 'Arial', 'B', 11 );
        $pdf->SetX( 54 );
        $pdf->Cell( 58, 6, $data['ticket_type'], 0, 1 );
        if ( ! empty( $data['ticket_addons'] ) ) {
            $pdf->SetFont( 'Arial', '', 8 );
            $pdf->SetX( 54 );
            $pdf->Cell( 58, 5, 'Add-ons: ' . $this->format_ticket_addons_for_pdf( $data['ticket_addons'] ), 0, 1 );
        }
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetX( 54 );
        $pdf->Cell( 58, 5, $data['name'], 0, 1 );
        if ( ! empty( $data['attendee_email'] ) ) {
            $pdf->SetX( 54 );
            $pdf->Cell( 58, 5, $data['attendee_email'], 0, 1 );
        }
        $pdf->SetX( 54 );
        $pdf->Cell( 58, 5, esc_money_gbp( $data['ticket_price'] ), 0, 1 );

        if ( $data['qr_path'] && file_exists( $data['qr_path'] ) ) {
            $pdf->Image( $data['qr_path'], 112, 11, 22, 22 );
        }

        $pdf->SetFont( 'Arial', 'B', 8 );
        $pdf->SetXY( 54, 58 );
        $pdf->Cell( 80, 5, 'Ticket ID: ' . $ticket_id, 0, 1 );

        return $pdf;
    }

    private function format_ticket_addons_for_pdf( array $addons ): string {
        $names = [];

        foreach ( $addons as $addon ) {
            if ( ! empty( $addon['name'] ) ) {
                $names[] = sanitize_text_field( (string) $addon['name'] );
            }
        }

        return implode( ', ', array_slice( $names, 0, 4 ) );
    }

    private function generate_qr_code_image( string $ticket_id, int $order_id ): string {
        $upload_dir = wp_upload_dir();
        $qr_dir     = trailingslashit( $upload_dir['basedir'] ) . 'ets-qr-codes/';

        if ( ! file_exists( $qr_dir ) ) {
            wp_mkdir_p( $qr_dir );
        }

        $filename = 'ticket-' . sanitize_file_name( $ticket_id ) . '.png';
        $path     = $qr_dir . $filename;

        if ( file_exists( $path ) ) {
            return $path;
        }

        $qr_data = wp_json_encode( [
            'ticket_id' => $ticket_id,
            'order_id'  => $order_id,
            'site'      => home_url(),
        ] );

        $api_url  = 'https://quickchart.io/qr?size=300&text=' . rawurlencode( (string) $qr_data );
        $response = wp_remote_get( $api_url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 || empty( $body ) ) {
            return '';
        }

        file_put_contents( $path, $body );

        return file_exists( $path ) ? $path : '';
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
