<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filtro para procesar subidas de archivos en WordPress (GhostScript y FFmpeg)
 */
function mxwm_courses_process_uploads( $upload ) {
    // Si hubo un error en la subida, ignorar
    if ( isset( $upload['error'] ) && $upload['error'] ) {
        return $upload;
    }

    $file_path = $upload['file'];
    $mime_type = $upload['type'];

    // Procesador de PDF con Ghostscript
    if ( $mime_type === 'application/pdf' ) {
        $temp_file = $file_path . '.tmp.pdf';
        
        // Ejecutar Ghostscript (preset /ebook -> 150 DPI)
        $cmd = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
            escapeshellarg( $temp_file ),
            escapeshellarg( $file_path )
        );
        
        exec( $cmd, $output, $return_var );
        
        // Si fue exitoso y el archivo existe
        if ( $return_var === 0 && file_exists( $temp_file ) ) {
            $original_size = filesize( $file_path );
            $new_size = filesize( $temp_file );
            
            // Solo reemplazar si realmente hubo mejora en peso
            if ( $new_size > 0 && $new_size < $original_size ) {
                rename( $temp_file, $file_path );
                error_log( "MXWM GS: PDF comprimido. Origen: {$original_size}B -> Destino: {$new_size}B" );
            } else {
                unlink( $temp_file );
                error_log( "MXWM GS: Archivo no se logró comprimir más o hubo error de tamaño." );
            }
        } else {
            error_log( "MXWM GS Error de compresión Ghostscript. Código de salida: {$return_var}" );
            if ( file_exists( $temp_file ) ) unlink( $temp_file );
        }
    }

    // Procesador de Audio/Video a MP3 con FFmpeg
    $audio_mimes = array( 'audio/mp4', 'video/mp4', 'audio/wav', 'audio/x-m4a', 'audio/m4a' );
    
    // Si la subida trae un flag de 'mxwm_skip_ffmpeg', nos la saltamos. 
    // Útil si en el futuro necesitas subir un mp4 real.
    $skip_ffmpeg = isset($_POST['mxwm_skip_ffmpeg']) && $_POST['mxwm_skip_ffmpeg'] == '1';

    if ( ! $skip_ffmpeg && in_array( $mime_type, $audio_mimes ) ) {
        $path_parts = pathinfo( $file_path );
        $new_file_path = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.mp3';
        
        // Si está sobreescribiendo el mismo nombre (ej. era wav), generamos la ruta
        // Ejecutar FFmpeg (extrae audio y lo convierte a mp3, calidad q:a 2)
        $cmd = sprintf(
            'ffmpeg -y -i %s -vn -acodec libmp3lame -q:a 2 %s',
            escapeshellarg( $file_path ),
            escapeshellarg( $new_file_path )
        );
        
        exec( $cmd, $output, $return_var );
        
        if ( $return_var === 0 && file_exists( $new_file_path ) ) {
            // Eliminar el archivo viejo (ej. mp4 o wav gigantes)
            if ( $file_path !== $new_file_path ) {
                unlink( $file_path );
            }
            
            error_log( "MXWM FFmpeg: Conversión exitosa a MP3 de " . basename($file_path) );
            
            // Actualizar manualmente los paths para WordPress
            $upload['file'] = $new_file_path;
            // Corregir la URL apuntando al nuevo .mp3
            $url_parts = pathinfo( $upload['url'] );
            $upload['url'] = $url_parts['dirname'] . '/' . $url_parts['filename'] . '.mp3';
            $upload['type'] = 'audio/mpeg';
        } else {
            error_log( "MXWM FFmpeg: Error en conversión. Código de salida: {$return_var}" );
            if ( file_exists( $new_file_path ) && $file_path !== $new_file_path ) {
                unlink( $new_file_path );
            }
        }
    }

    return $upload;
}
add_filter( 'wp_handle_upload', 'mxwm_courses_process_uploads' );
