<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function get_settings(): array {
    $options = get_option( ETS_OPTION_NAME, [] );
    return is_array( $options ) ? $options : [];
}

function get_setting( string $key, $default = '' ) {
    $options = get_settings();
    return $options[ $key ] ?? $default;
}

function esc_money_gbp( $amount ): string {
    $amount = is_numeric( $amount ) ? (float) $amount : 0.0;
    return '£' . number_format( $amount, 2 );
}

function parse_email_template( string $template, array $data = [] ): string {
    foreach ( $data as $key => $value ) {
        $template = str_replace( '{' . $key . '}', (string) $value, $template );
    }

    return $template;
}

function asset_url_to_path( string $url ): string {
    if ( empty( $url ) ) {
        return '';
    }

    $upload = wp_upload_dir();

    if ( strpos( $url, $upload['baseurl'] ) !== false ) {
        return str_replace( $upload['baseurl'], $upload['basedir'], $url );
    }

    $site_url = site_url();
    if ( strpos( $url, $site_url ) !== false ) {
        return str_replace( $site_url, ABSPATH, $url );
    }

    return '';
}


function normalise_image_url( $image ): string {
    if ( empty( $image ) ) {
        return '';
    }

    if ( is_array( $image ) ) {
        if ( ! empty( $image['url'] ) ) {
            return esc_url_raw( $image['url'] );
        }

        if ( ! empty( $image['ID'] ) ) {
            return (string) wp_get_attachment_image_url( (int) $image['ID'], 'full' );
        }

        if ( ! empty( $image['id'] ) ) {
            return (string) wp_get_attachment_image_url( (int) $image['id'], 'full' );
        }
    }

    if ( is_numeric( $image ) ) {
        return (string) wp_get_attachment_image_url( (int) $image, 'full' );
    }

    return esc_url_raw( (string) $image );
}


function ticket_stock_key( int $index, string $label ): string {
    return $index . '|' . sanitize_title( $label );
}

function normalise_ticket_stock_value( $stock ) {
    if ( $stock === '' || $stock === null ) {
        return null;
    }

    if ( ! is_numeric( $stock ) ) {
        return null;
    }

    return max( 0, (int) $stock );
}

function get_event_ticket_stock_sold( int $event_id ): array {
    $sold = get_post_meta( $event_id, '_ets_ticket_stock_sold', true );
    return is_array( $sold ) ? $sold : [];
}

function get_event_ticket_sold_count( int $event_id, int $index, string $label ): int {
    if ( ! $event_id ) {
        return 0;
    }

    $sold = get_event_ticket_stock_sold( $event_id );
    $key  = ticket_stock_key( $index, $label );

    return isset( $sold[ $key ] ) ? max( 0, (int) $sold[ $key ] ) : 0;
}

function get_ticket_remaining_stock( $stock, int $sold = 0 ) {
    $stock = normalise_ticket_stock_value( $stock );

    if ( $stock === null ) {
        return null;
    }

    return max( 0, $stock - max( 0, $sold ) );
}


function addon_stock_key( int $index, string $name ): string {
    return $index . '|' . sanitize_title( $name );
}

function get_event_addon_stock_sold( int $event_id ): array {
    $sold = get_post_meta( $event_id, '_ets_addon_stock_sold', true );
    return is_array( $sold ) ? $sold : [];
}

function get_event_addon_sold_count( int $event_id, int $index, string $name ): int {
    if ( ! $event_id ) {
        return 0;
    }

    $sold = get_event_addon_stock_sold( $event_id );
    $key  = addon_stock_key( $index, $name );

    return isset( $sold[ $key ] ) ? max( 0, (int) $sold[ $key ] ) : 0;
}

function normalise_discount_code( $code ): string {
    return strtoupper( trim( sanitize_text_field( (string) $code ) ) );
}

function find_discount_by_code( string $code ): int {
    $code = normalise_discount_code( $code );
    if ( ! $code ) {
        return 0;
    }

    $discounts = get_posts( [
        'post_type'      => 'ets_discount',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => '_ets_discount_code',
                'value' => $code,
            ],
        ],
        'fields'         => 'ids',
    ] );

    if ( ! empty( $discounts ) ) {
        return (int) $discounts[0];
    }

    // Fallback: allow the post title itself to be the code.
    $discounts = get_posts( [
        'post_type'      => 'ets_discount',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'title'          => $code,
        'fields'         => 'ids',
    ] );

    return ! empty( $discounts ) ? (int) $discounts[0] : 0;
}

function calculate_discount_for_order( string $code, int $subtotal_cents, int $event_id = 0 ): array {
    $code = normalise_discount_code( $code );

    if ( ! $code ) {
        return [
            'valid' => false,
            'error' => 'Please enter a discount code.',
        ];
    }

    $discount_id = find_discount_by_code( $code );
    if ( ! $discount_id ) {
        return [
            'valid' => false,
            'error' => 'Discount code not found.',
        ];
    }

    if ( get_post_meta( $discount_id, '_ets_discount_enabled', true ) === 'no' ) {
        return [
            'valid' => false,
            'error' => 'This discount code is not active.',
        ];
    }

    $expiry = get_post_meta( $discount_id, '_ets_discount_expiry', true );
    if ( $expiry && strtotime( $expiry . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
        return [
            'valid' => false,
            'error' => 'This discount code has expired.',
        ];
    }

    $limit = get_post_meta( $discount_id, '_ets_discount_usage_limit', true );
    $used  = (int) get_post_meta( $discount_id, '_ets_discount_used_count', true );
    if ( $limit !== '' && is_numeric( $limit ) && $used >= (int) $limit ) {
        return [
            'valid' => false,
            'error' => 'This discount code has reached its usage limit.',
        ];
    }

    $limit_event_id = (int) get_post_meta( $discount_id, '_ets_discount_event_id', true );
    if ( $limit_event_id && $event_id && $limit_event_id !== $event_id ) {
        return [
            'valid' => false,
            'error' => 'This discount code is not valid for this event.',
        ];
    }

    if ( $limit_event_id && ! $event_id ) {
        return [
            'valid' => false,
            'error' => 'This discount code is not valid for this ticket block.',
        ];
    }

    $minimum_total = get_post_meta( $discount_id, '_ets_discount_min_total', true );
    $minimum_cents = $minimum_total !== '' && is_numeric( $minimum_total ) ? (int) round( (float) $minimum_total * 100 ) : 0;
    if ( $minimum_cents > 0 && $subtotal_cents < $minimum_cents ) {
        return [
            'valid' => false,
            'error' => sprintf( 'This code requires a minimum order of %s.', esc_money_gbp( $minimum_cents / 100 ) ),
        ];
    }

    $type   = get_post_meta( $discount_id, '_ets_discount_type', true ) ?: 'percent';
    $amount = (float) get_post_meta( $discount_id, '_ets_discount_amount', true );

    if ( $amount <= 0 || $subtotal_cents <= 0 ) {
        return [
            'valid' => false,
            'error' => 'This discount code is not valid.',
        ];
    }

    if ( $type === 'fixed' ) {
        $discount_cents = (int) round( $amount * 100 );
    } else {
        $discount_cents = (int) round( $subtotal_cents * min( 100, $amount ) / 100 );
    }

    $discount_cents = max( 0, min( $discount_cents, $subtotal_cents ) );
    $total_cents    = max( 0, $subtotal_cents - $discount_cents );

    return [
        'valid'          => true,
        'discount_id'    => $discount_id,
        'code'           => $code,
        'type'           => $type,
        'amount'         => $amount,
        'subtotal_cents' => $subtotal_cents,
        'discount_cents' => $discount_cents,
        'total_cents'    => $total_cents,
        'message'        => sprintf( '%s applied. You saved %s.', $code, esc_money_gbp( $discount_cents / 100 ) ),
    ];
}

function increment_discount_usage_for_order( int $order_id ): void {
    if ( get_post_meta( $order_id, '_ets_discount_usage_incremented', true ) === 'yes' ) {
        return;
    }

    $discount_id = (int) get_post_meta( $order_id, '_ets_discount_id', true );
    if ( ! $discount_id ) {
        return;
    }

    $used = (int) get_post_meta( $discount_id, '_ets_discount_used_count', true );
    update_post_meta( $discount_id, '_ets_discount_used_count', $used + 1 );
    update_post_meta( $order_id, '_ets_discount_usage_incremented', 'yes' );
}
