<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes de límites de recursos alineadas estrictamente al ROADMAP
global $mxwm_resource_limits;
$mxwm_resource_limits = [
    'pdf' => 3,
    'podcast' => 3,
    'infographic' => 5,
    'link' => 10
];

function mxwm_lesson_resources_enqueue_scripts($hook) {
    if ( function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'mxwm_lesson' ) {
            wp_enqueue_media();
            wp_enqueue_script('mxwm-admin-lesson', plugin_dir_url(__DIR__) . 'assets/admin-lesson.js', array('jquery'), filemtime(plugin_dir_path(__DIR__) . 'assets/admin-lesson.js'), true);
        }
    } else {
        // Fallback for edge cases
        global $post;
        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            if ( $post && 'mxwm_lesson' === $post->post_type ) {
                wp_enqueue_media();
                wp_enqueue_script('mxwm-admin-lesson', plugin_dir_url(__DIR__) . 'assets/admin-lesson.js', array('jquery'), filemtime(plugin_dir_path(__DIR__) . 'assets/admin-lesson.js'), true);
            }
        }
    }
}
add_action( 'admin_enqueue_scripts', 'mxwm_lesson_resources_enqueue_scripts' );

function mxwm_add_lesson_resources_metabox() {
    add_meta_box(
        'mxwm_lesson_resources_mb',
        '📦 Recursos Múltiples de la Lección (Librería Asíncrona Inteligente)',
        'mxwm_render_lesson_resources_metabox',
        'mxwm_lesson',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'mxwm_add_lesson_resources_metabox' );

function mxwm_render_lesson_resources_metabox( $post ) {
    global $wpdb, $mxwm_resource_limits;
    $table = $wpdb->prefix . 'mxwm_lesson_resources';
    
    $resources = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE lesson_id = %d ORDER BY id ASC", $post->ID ) );
    
    $counts = [
        'pdf' => 0,
        'podcast' => 0,
        'infographic' => 0,
        'link' => 0
    ];
    
    wp_nonce_field( 'mxwm_save_lesson_resources', 'mxwm_lesson_resources_nonce' );
    
    echo '<div id="mxwm-resources-container" style="background:#f9f9f9; padding: 15px; border-radius: 6px;">';
    
    if ( $resources ) {
        echo '<h4>Archivos y Enlaces adjuntos:</h4>';
        echo '<ul style="margin: 0; padding: 0; list-style: none;">';
        foreach ( $resources as $res ) {
            $type = strtolower($res->type);
            if(isset($counts[$type])) {
                $counts[$type]++;
            }
            // Mapeo seguro para vista inversa
            $display_type = strtoupper($type);
            if($type === 'pdf') $display_type = 'PRESENTACIÓN';
            if($type === 'podcast') $display_type = 'AUDIO';
            if($type === 'infographic') $display_type = 'IMAGEN';
            
            echo '<li id="existing-res-'.esc_attr($res->id).'" style="margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #ccc; border-left: 4px solid #0073aa; display:flex; justify-content:space-between; align-items:center;">';
            echo '<span><strong>' . esc_html($res->title) . '</strong> <em style="color:#666; font-size:12px;">(' . esc_html($display_type) . ')</em></span>';
            echo '<div>';
            echo '<a href="' . esc_url($res->url) . '" target="_blank" class="button button-small" style="margin-right:10px;">🔗 Revisar</a>';
            // Se usa JS dinámico para eliminar y reflejar en DOM inmediatamente
            echo '<button type="button" class="button button-small button-link-delete" style="color: #a00;" onclick="window.mxwmMarkForDeletion(event, '.esc_attr($res->id).', \''.esc_js($type).'\')">🗑️ Eliminar</button>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<hr style="margin:20px 0;"/>';
    } 
    
    echo '<h4>Estado de Límites del Roadmap:</h4>';
    echo '<div style="display:flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">';
    
    $ui_labels = [
        'pdf' => 'PRESENTACIÓN',
        'podcast' => 'AUDIO',
        'infographic' => 'IMAGEN',
        'link' => 'LINK'
    ];
    
    foreach($mxwm_resource_limits as $type => $limit) {
        $c = $counts[$type];
        $color = ($c >= $limit) ? 'red' : 'green';
        echo "<span style='padding:5px 15px; background:#fff; border:1px solid #ddd; border-radius:4px;'>";
        echo esc_html($ui_labels[$type]) . ": <span id='limit-count-$type'><b style='color:$color;'>$c / $limit</b></span>";
        echo "</span>";
    }
    echo '</div>';


    echo '<div style="display:flex; justify-content:space-between; gap:20px;">';
    
    // BLOQUE ARCHIVOS FÍSICOS MASIVOS
    echo '<div style="flex:1; background:#fff; padding:15px; border:1px solid #ddd; border-radius:4px;">';
    echo '<h4 style="margin-top:0;">Añadir Archivos(s) desde tu computadora:</h4>';
    echo '<button type="button" class="button button-secondary" id="mxwm_upload_media_btn" style="background:#0073aa; color:#fff; border:none; border-radius:4px; padding: 10px 20px; font-weight:bold; cursor:pointer;">📂 Abrir Librería / Subir</button>';
    echo '<p style="font-size:12px; color:#666; margin-top:10px;">Selecciona múltiples archivos (con CRTL/SHIFT). El sistema <b>detectará automáticamente</b> si es PDF, Audio o Imagen y lo asignará al límite correspondiente.</p>';
    echo '<div id="mxwm_selected_files_preview" style="margin-top:15px; display:flex; flex-direction:column; gap:8px;"></div>';
    echo '<input type="hidden" name="mxwm_bulk_media_ids" id="mxwm_bulk_media_ids" value="">';

    echo '<script>
        window.mxwm_limits = ' . wp_json_encode($mxwm_resource_limits) . ';
        window.mxwm_current = ' . wp_json_encode($counts) . ';
        window.mxwm_pending = { pdf:0, podcast:0, infographic:0, link:0 };
    </script>';

    echo '</div>';

    // BLOQUE LINKS
    echo '<div style="flex:1; background:#fff; padding:15px; border:1px solid #ddd; border-radius:4px;">';
    echo '<h4 style="margin-top:0;">O añadir Enlaces Externos (Web):</h4>';
    echo '<div id="mxwm-dynamic-links-container">';
    echo '<div class="mxwm-link-item" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">';
    echo '<input type="text" name="mxwm_new_res_link_title[]" placeholder="Título (ej: Wikipedia)" style="flex:1;">';
    echo '<input type="url" name="mxwm_new_res_url[]" placeholder="https://..." style="flex:2;">';
    echo '<button type="button" class="button" onclick="this.parentElement.remove()" style="color:#d63638;" title="Quitar">✖</button>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" class="button" onclick="window.mxwmAddLinkRow()" style="margin-top:5px; background:#f0f0f1;">+ Nuevo link</button>';
    echo '</div>';

    echo '</div>'; // Fin flex

    echo '</div>';
}

function mxwm_save_lesson_resources_metabox( $post_id ) {
    if ( ! isset( $_POST['mxwm_lesson_resources_nonce'] ) || ! wp_verify_nonce( $_POST['mxwm_lesson_resources_nonce'], 'mxwm_save_lesson_resources' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    global $wpdb, $mxwm_resource_limits;
    $table = $wpdb->prefix . 'mxwm_lesson_resources';

    // 1. Borrados tienen precedencia
    if ( ! empty( $_POST['mxwm_delete_resource'] ) && is_array( $_POST['mxwm_delete_resource'] ) ) {
        foreach ( $_POST['mxwm_delete_resource'] as $res_id ) {
            $wpdb->delete( $table, [ 'id' => (int)$res_id, 'lesson_id' => $post_id ] );
        }
    }

    // Calcular contadores actuales antes de inyectar nuevos
    $current_counts = [];
    foreach($mxwm_resource_limits as $t => $lim) {
        $current_counts[$t] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE lesson_id = %d AND type = %s", $post_id, $t ) );
    }

    // 2. Procesar Link (Array Dinámico)
    if ( isset($_POST['mxwm_new_res_url']) && is_array($_POST['mxwm_new_res_url']) ) {
        $urls = $_POST['mxwm_new_res_url'];
        $titles = isset($_POST['mxwm_new_res_link_title']) ? $_POST['mxwm_new_res_link_title'] : [];

        for ($i=0; $i < count($urls); $i++) {
            $url_link = esc_url_raw($urls[$i]);
            if ( !empty($url_link) && $current_counts['link'] < $mxwm_resource_limits['link'] ) {
                $title = isset($titles[$i]) ? sanitize_text_field( $titles[$i] ) : '';
                if ( empty($title) ) $title = 'Enlace Externo';
                $wpdb->insert( $table, [
                    'lesson_id' => $post_id,
                    'type' => 'link',
                    'title' => $title,
                    'attachment_id' => null,
                    'url' => $url_link,
                    'file_size_bytes' => 0,
                    'mime_type' => 'text/html'
                ] );
                $current_counts['link']++;
            }
        }
    }

    // 3. Procesar Archivos Masivos (Auto-Enrutamiento)
    if ( !empty($_POST['mxwm_bulk_media_ids']) ) {
        $ids = explode(',', $_POST['mxwm_bulk_media_ids']);
        foreach($ids as $att_id) {
            $att_id = (int) trim($att_id);
            if($att_id <= 0) continue;

            $url = wp_get_attachment_url( $att_id );
            if(!$url) continue;

            $mime_type = get_post_mime_type( $att_id );
            
            // Detección Estricta tipo IA
            $inferred_type = null;
            if (strpos($mime_type, 'pdf') !== false) {
                $inferred_type = 'pdf';
            } elseif (strpos($mime_type, 'audio') !== false || strpos($mime_type, 'mpeg') !== false) {
                $inferred_type = 'podcast';
            } elseif (strpos($mime_type, 'image') !== false) {
                $inferred_type = 'infographic';
            }

            // Ignorar archivos que no entren en el Syllabus (ej. zips u hojas de excel)
            if ( !$inferred_type ) continue;

            // Cortar inyección de ese tipo específico si llega al tope del roadmap
            if ( $current_counts[$inferred_type] >= $mxwm_resource_limits[$inferred_type] ) continue;

            $file_path = get_attached_file( $att_id );
            $file_size = ($file_path && file_exists( $file_path )) ? filesize( $file_path ) : 0;
            $title = get_the_title( $att_id );
            if ( empty($title) ) $title = basename($url);

            $wpdb->insert( $table, [
                'lesson_id' => $post_id,
                'type' => $inferred_type,
                'title' => $title,
                'attachment_id' => $att_id,
                'url' => $url,
                'file_size_bytes' => $file_size,
                'mime_type' => $mime_type
            ] );

            $current_counts[$inferred_type]++;
        }
    }
}
add_action( 'save_post_mxwm_lesson', 'mxwm_save_lesson_resources_metabox' );

function mxwm_edit_form_enctype() {
    global $post;
    if ( $post && $post->post_type === 'mxwm_lesson' ) {
        echo ' enctype="multipart/form-data"';
    }
}
add_action( 'post_edit_form_tag', 'mxwm_edit_form_enctype' );
