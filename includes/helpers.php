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
