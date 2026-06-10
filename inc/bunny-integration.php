<?php
/**
 * Integración con Bunny Stream CDN
 * Requiere constantes en wp-config.php:
 *   MXWM_BUNNY_LIBRARY_ID, MXWM_BUNNY_API_KEY, MXWM_BUNNY_CDN_HOST
 *
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Construye la URL de reproducción HLS (.m3u8) para un video de Bunny Stream.
 */
function mxwm_get_bunny_hls_url( $video_id ) {
    if ( ! $video_id ) return '';
    $host = defined( 'MXWM_BUNNY_CDN_HOST' ) ? MXWM_BUNNY_CDN_HOST : '';
    return $host ? "https://{$host}/{$video_id}/playlist.m3u8" : '';
}

/**
 * Construye la URL del thumbnail automático de Bunny Stream.
 */
function mxwm_get_bunny_thumbnail_url( $video_id ) {
    if ( ! $video_id ) return '';
    $host = defined( 'MXWM_BUNNY_CDN_HOST' ) ? MXWM_BUNNY_CDN_HOST : '';
    return $host ? "https://{$host}/{$video_id}/thumbnail.jpg" : '';
}

/**
 * Verifica el estado de un video en Bunny Stream via API.
 *
 * @return array { status: 'ready'|'encoding'|'error', duration_seconds: int }
 */
function mxwm_verify_bunny_video( $video_id ) {
    $lib_id  = defined( 'MXWM_BUNNY_LIBRARY_ID' ) ? MXWM_BUNNY_LIBRARY_ID : '';
    $api_key = defined( 'MXWM_BUNNY_API_KEY' ) ? MXWM_BUNNY_API_KEY : '';

    if ( ! $video_id || ! $lib_id || ! $api_key ) {
        return array( 'status' => 'error', 'message' => 'Bunny Stream no configurado en wp-config.php.' );
    }

    $response = wp_remote_get(
        "https://video.bunnycdn.com/library/{$lib_id}/videos/{$video_id}",
        array( 'headers' => array( 'AccessKey' => $api_key ), 'timeout' => 15 )
    );

    if ( is_wp_error( $response ) ) {
        return array( 'status' => 'error', 'message' => $response->get_error_message() );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Bunny status codes: 0=queued, 1=processing, 2=encoding, 3=finished, 4=resolution_finished, 5=error
    $status_code = $body['status'] ?? 5;
    $status_map  = array( 0 => 'queued', 1 => 'processing', 2 => 'encoding', 3 => 'ready', 4 => 'ready', 5 => 'error' );
    $status      = $status_map[ $status_code ] ?? 'error';

    return array(
        'status'           => $status,
        'duration_seconds' => (int) ( $body['length'] ?? 0 ),
        'title'            => sanitize_text_field( $body['title'] ?? '' ),
    );
}

/**
 * AJAX handler: verifica un video de Bunny Stream desde el admin de WordPress.
 * Llamado por el JS del metabox al hacer clic en "Verificar".
 */
add_action( 'wp_ajax_mxwm_verify_bunny_video', function () {
    check_ajax_referer( 'mxwm_bunny_verify', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Permisos insuficientes.' );
    }

    $video_id = sanitize_text_field( $_POST['video_id'] ?? '' );
    if ( ! $video_id ) {
        wp_send_json_error( 'Video ID requerido.' );
    }

    $result = mxwm_verify_bunny_video( $video_id );

    if ( $result['status'] === 'error' ) {
        wp_send_json_error( $result['message'] ?? 'Error al verificar el video.' );
    }

    wp_send_json_success( $result );
} );
