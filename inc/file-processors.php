<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =============================================================================
// MIME types — permitir todos los formatos de audio comunes
// =============================================================================

add_filter( 'upload_mimes', function ( $mimes ) {
    $mimes['mp3']  = 'audio/mpeg';
    $mimes['m4a']  = 'audio/mpeg';
    $mimes['m4b']  = 'audio/mpeg';
    $mimes['wav']  = 'audio/wav';
    $mimes['ogg']  = 'audio/ogg';
    $mimes['oga']  = 'audio/ogg';
    $mimes['flac'] = 'audio/flac';
    $mimes['aac']  = 'audio/aac';
    return $mimes;
} );

// WP 5.1+ detecta el MIME real con finfo. Para M4A, finfo devuelve audio/mp4
// mientras WP espera audio/mpeg (por la extensión). Esto provoca rechazo silencioso.
// Este filtro corrige el mismatch extensión↔MIME para los formatos de audio.
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {
    if ( $data['ext'] && $data['type'] ) {
        return $data; // Ya validado, no tocar
    }

    $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    $audio_map = [
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mpeg',
        'm4b'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'oga'  => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac'  => 'audio/aac',
    ];

    if ( isset( $audio_map[ $ext ] ) ) {
        $data['ext']  = $ext;
        $data['type'] = $audio_map[ $ext ];
    }

    return $data;
}, 10, 4 );

// =============================================================================
// Procesador de subidas: Ghostscript (PDF) + FFmpeg (audio → MP3)
// =============================================================================

function mxwm_courses_process_uploads( $upload ) {
    if ( isset( $upload['error'] ) && $upload['error'] ) {
        return $upload;
    }

    $file_path = $upload['file'];
    $mime_type = $upload['type'];

    // ── PDF → Ghostscript (background) ─────────────────────────────────────────
    // GS con páginas grandes puede superar el timeout de Cloudflare (100 s).
    // Ejecutamos en background: el HTTP response regresa en <1 s y el archivo
    // se reemplaza silenciosamente cuando GS termina. Si falla, el original queda.
    // Log en /tmp/mxwm-gs.log para debugging.
    if ( $mime_type === 'application/pdf' ) {
        $temp   = $file_path . '.gs_tmp.pdf';
        $orig_q = escapeshellarg( $file_path );
        $temp_q = escapeshellarg( $temp );
        $log_q  = escapeshellarg( '/tmp/mxwm-gs.log' );

        $shell = 'echo "[$(date +%FT%T)] START ' . $file_path . '" >> ' . $log_q
               . ' && timeout 300 gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
               . ' -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH'
               . ' -sOutputFile=' . $temp_q . ' ' . $orig_q . ' >>' . $log_q . ' 2>&1'
               . ' && [ -s ' . $temp_q . ' ]'
               . ' && [ $(stat -c%s ' . $temp_q . ') -lt $(stat -c%s ' . $orig_q . ') ]'
               . ' && mv ' . $temp_q . ' ' . $orig_q
               . ' && echo "[$(date +%FT%T)] OK compressed" >> ' . $log_q
               . ' || { rm -f ' . $temp_q . '; echo "[$(date +%FT%T)] SKIP/ERR" >> ' . $log_q . '; }';

        exec( 'setsid sh -c ' . escapeshellarg( $shell ) . ' >/dev/null 2>&1 &' );
    }

    // ── Audio → FFmpeg → MP3 ─────────────────────────────────────────────────
    // Incluye audio/mpeg (wav/m4a ya convertidos al llegar aquí), audio/mp4,
    // audio/x-m4a, audio/m4a, audio/wav. MP3 nativo se omite para evitar
    // recodificación innecesaria.
    $audio_mimes = [
        'audio/mp4', 'audio/x-m4a', 'audio/m4a',
        'audio/wav', 'audio/x-wav',
        'audio/ogg', 'audio/flac', 'audio/aac',
    ];

    $skip_ffmpeg = ! empty( $_POST['mxwm_skip_ffmpeg'] );

    if ( ! $skip_ffmpeg && in_array( $mime_type, $audio_mimes, true ) ) {
        $path_parts    = pathinfo( $file_path );
        $new_file_path = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.mp3';

        $cmd = sprintf(
            'ffmpeg -y -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
            escapeshellarg( $file_path ),
            escapeshellarg( $new_file_path )
        );
        exec( $cmd, $ffmpeg_out, $return_var );

        if ( $return_var === 0 && file_exists( $new_file_path ) && filesize( $new_file_path ) > 0 ) {
            if ( $file_path !== $new_file_path ) {
                @unlink( $file_path );
            }
            error_log( 'MXWM FFmpeg: Conversión exitosa → ' . basename( $new_file_path ) );

            $url_parts      = pathinfo( $upload['url'] );
            $upload['file'] = $new_file_path;
            $upload['url']  = $url_parts['dirname'] . '/' . $url_parts['filename'] . '.mp3';
            $upload['type'] = 'audio/mpeg';
        } else {
            error_log( "MXWM FFmpeg: Error (código {$return_var}): " . implode( ' | ', array_slice( $ffmpeg_out, -5 ) ) );
            if ( file_exists( $new_file_path ) && $file_path !== $new_file_path ) {
                @unlink( $new_file_path );
            }
            // El archivo original se conserva tal cual; WP lo guarda sin conversión.
        }
    }

    return $upload;
}
add_filter( 'wp_handle_upload', 'mxwm_courses_process_uploads' );
