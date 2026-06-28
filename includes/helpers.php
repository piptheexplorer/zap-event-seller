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


function addon_stock_key( $index, string $name ): string {
    return sanitize_key( (string) $index ) . '|' . sanitize_title( $name );
}

function get_event_addon_stock_sold( int $event_id ): array {
    $sold = get_post_meta( $event_id, '_ets_addon_stock_sold', true );
    return is_array( $sold ) ? $sold : [];
}

function get_event_addon_sold_count( int $event_id, $index, string $name ): int {
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

function get_addon_description( int $addon_id ): string {
    $description = function_exists( 'get_field' ) ? get_field( 'ets_addon_description', $addon_id ) : get_post_meta( $addon_id, 'ets_addon_description', true );
    return (string) ( $description ?: '' );
}

function get_addon_price( int $addon_id ): float {
    $price = function_exists( 'get_field' ) ? get_field( 'ets_addon_price', $addon_id ) : get_post_meta( $addon_id, 'ets_addon_price', true );
    return is_numeric( $price ) ? max( 0, (float) $price ) : 0.0;
}

function get_addon_stock( int $addon_id ) {
    $stock = function_exists( 'get_field' ) ? get_field( 'ets_addon_stock', $addon_id ) : get_post_meta( $addon_id, 'ets_addon_stock', true );
    return normalise_ticket_stock_value( $stock );
}

function get_addon_scope( int $addon_id ): string {
    $scope = function_exists( 'get_field' ) ? get_field( 'ets_addon_scope', $addon_id ) : get_post_meta( $addon_id, 'ets_addon_scope', true );
    $scope = sanitize_key( (string) ( $scope ?: 'event' ) );
    return in_array( $scope, [ 'event', 'per_ticket', 'both' ], true ) ? $scope : 'event';
}

function get_addon_image_url( int $addon_id ): string {
    $image = function_exists( 'get_field' ) ? get_field( 'ets_addon_image', $addon_id ) : get_post_meta( $addon_id, 'ets_addon_image', true );
    $url = normalise_image_url( $image );

    if ( ! $url && has_post_thumbnail( $addon_id ) ) {
        $url = (string) get_the_post_thumbnail_url( $addon_id, 'full' );
    }

    return $url;
}

function get_addon_cpt_data( int $addon_id, string $applies_to = '', string $context = 'event' ): array {
    $post = get_post( $addon_id );
    if ( ! $post || $post->post_type !== 'ets_addon' ) {
        return [];
    }

    return [
        'addon_id'    => $addon_id,
        'name'        => get_the_title( $addon_id ),
        'description' => get_addon_description( $addon_id ),
        'price'       => get_addon_price( $addon_id ),
        'stock'       => get_addon_stock( $addon_id ),
        'image'       => get_addon_image_url( $addon_id ),
        'scope'       => get_addon_scope( $addon_id ),
        'context'     => sanitize_key( $context ),
        'applies_to'  => sanitize_text_field( $applies_to ),
    ];
}

function normalise_post_id_list( $value ): array {
    if ( empty( $value ) ) {
        return [];
    }

    if ( is_numeric( $value ) ) {
        return [ (int) $value ];
    }

    if ( $value instanceof \WP_Post ) {
        return [ (int) $value->ID ];
    }

    if ( ! is_array( $value ) ) {
        return [];
    }

    $ids = [];
    foreach ( $value as $item ) {
        if ( is_numeric( $item ) ) {
            $ids[] = (int) $item;
        } elseif ( $item instanceof \WP_Post ) {
            $ids[] = (int) $item->ID;
        } elseif ( is_array( $item ) && isset( $item['ID'] ) ) {
            $ids[] = (int) $item['ID'];
        }
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

function get_event_available_addons( int $event_id, array $ticket_types = [] ): array {
    if ( ! $event_id ) {
        return [];
    }

    $addons = [];

    $event_addon_ids = function_exists( 'get_field' ) ? get_field( 'ets_event_addon_ids', $event_id ) : get_post_meta( $event_id, 'ets_event_addon_ids', true );
    foreach ( normalise_post_id_list( $event_addon_ids ) as $addon_id ) {
        $scope = get_addon_scope( $addon_id );
        if ( ! in_array( $scope, [ 'event', 'both' ], true ) ) {
            continue;
        }

        $data = get_addon_cpt_data( $addon_id, '', 'event' );
        if ( $data ) {
            $addons[ 'addon_' . $addon_id . '_event' ] = $data;
        }
    }

    foreach ( $ticket_types as $ticket_index => $ticket ) {
        if ( ! is_array( $ticket ) ) {
            continue;
        }

        $label = sanitize_text_field( (string) ( $ticket['label'] ?? '' ) );
        if ( ! $label ) {
            continue;
        }

        $allowed_addons = $ticket['allowed_addons'] ?? [];
        foreach ( normalise_post_id_list( $allowed_addons ) as $addon_id ) {
            $scope = get_addon_scope( $addon_id );
            if ( ! in_array( $scope, [ 'per_ticket', 'both' ], true ) ) {
                continue;
            }

            $data = get_addon_cpt_data( $addon_id, $label, 'per_ticket' );
            if ( $data ) {
                $addons[ 'addon_' . $addon_id . '_ticket_' . sanitize_key( (string) $ticket_index ) ] = $data;
            }
        }
    }

    // Backwards compatibility: keep old event repeater add-ons working while users migrate.
    $legacy_addons = function_exists( 'get_field' ) ? get_field( 'ets_event_addons', $event_id ) : get_post_meta( $event_id, 'ets_event_addons', true );
    if ( is_array( $legacy_addons ) ) {
        foreach ( $legacy_addons as $legacy_index => $addon ) {
            if ( ! is_array( $addon ) || empty( $addon['name'] ) ) {
                continue;
            }

            $addons[ 'legacy_' . sanitize_key( (string) $legacy_index ) ] = [
                'addon_id'    => 0,
                'name'        => sanitize_text_field( (string) ( $addon['name'] ?? '' ) ),
                'description' => sanitize_textarea_field( (string) ( $addon['description'] ?? '' ) ),
                'price'       => isset( $addon['price'] ) ? (float) $addon['price'] : 0,
                'stock'       => $addon['stock'] ?? null,
                'image'       => normalise_image_url( $addon['image'] ?? '' ),
                'scope'       => 'legacy',
                'context'     => 'legacy',
                'applies_to'  => sanitize_text_field( (string) ( $addon['applies_to'] ?? '' ) ),
            ];
        }
    }

    return normalise_event_addon_rows( $addons, $event_id );
}

function normalise_event_addon_rows( array $addons, int $event_id = 0 ): array {
    $normalised = [];

    foreach ( $addons as $index => $addon ) {
        if ( ! is_array( $addon ) ) {
            continue;
        }

        $name = sanitize_text_field( (string) ( $addon['name'] ?? '' ) );
        if ( ! $name ) {
            continue;
        }

        $stock     = $addon['stock'] ?? null;
        $sold      = $event_id ? get_event_addon_sold_count( $event_id, $index, $name ) : 0;
        $remaining = get_ticket_remaining_stock( $stock, $sold );

        $addon['_ets_index']     = (string) $index;
        $addon['_ets_stock']     = normalise_ticket_stock_value( $stock );
        $addon['_ets_sold']      = $sold;
        $addon['_ets_remaining'] = $remaining;
        $addon['_ets_sold_out']  = ( $remaining !== null && $remaining <= 0 );

        $normalised[ (string) $index ] = $addon;
    }

    return $normalised;
}
