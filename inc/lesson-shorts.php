<?php
/**
 * Metabox de Shorts (Píldoras de Conocimiento) para Lecciones
 * Permite adjuntar hasta MXWM_MAX_SHORTS_PER_LESSON videos verticales por lección.
 * Usa formularios dinámicos (agregar N shorts sin guardar entre cada uno).
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function mxwm_add_lesson_shorts_metabox() {
    add_meta_box(
        'mxwm_lesson_shorts_mb',
        '📱 Shorts / Píldoras de Conocimiento (Vertical)',
        'mxwm_render_lesson_shorts_metabox',
        'mxwm_lesson',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'mxwm_add_lesson_shorts_metabox' );

function mxwm_render_lesson_shorts_metabox( $post ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_lesson_shorts';
    $max   = defined('MXWM_MAX_SHORTS_PER_LESSON') ? MXWM_MAX_SHORTS_PER_LESSON : 10;

    $shorts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE lesson_id = %d ORDER BY display_order ASC",
        $post->ID
    ) );

    $count = count( $shorts );

    wp_nonce_field( 'mxwm_save_lesson_shorts', 'mxwm_lesson_shorts_nonce' );

    echo '<div id="mxwm-shorts-container" style="background:#f9f9f9; padding:15px; border-radius:6px;">';

    // Info header
    $color = ( $count >= $max ) ? '#d63638' : '#00a32a';
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">';
    echo '<p style="margin:0; font-size:13px; color:#666;">Videos cortos verticales (≤3 min) que complementan la lección. Si marcas "Público", también aparecen en la galería de Shorts.</p>';
    echo '<span id="mxwm-shorts-counter" style="padding:5px 15px; background:#fff; border:1px solid #ddd; border-radius:4px; white-space:nowrap;">SHORTS: <b style="color:' . $color . ';">' . $count . ' / ' . $max . '</b></span>';
    echo '</div>';

    // ── Shorts existentes ──
    echo '<div id="mxwm-shorts-list">';
    if ( $shorts ) {
        foreach ( $shorts as $i => $short ) {
            mxwm_render_short_row( $short, $i + 1 );
        }
    }
    echo '</div>';

    // ── Shorts nuevos (dinámicos) ──
    echo '<div id="mxwm-new-shorts-list"></div>';

    // ── Botón agregar ──
    echo '<div id="mxwm-add-short-btn-wrap" style="margin-top:10px;">';
    echo '<button type="button" id="mxwm-add-short-btn" class="button button-secondary" style="display:flex; align-items:center; gap:6px;">';
    echo '<span style="font-size:16px;">➕</span> Agregar nuevo Short</button>';
    echo '</div>';

    echo '</div>'; // container

    // ── JavaScript dinámico ──
    $max_js            = (int) $max;
    $existing_count_js = (int) $count;
    $bunny_nonce       = wp_create_nonce( 'mxwm_bunny_verify' );
    ?>
    <script>
    (function(){
        var MAX      = <?php echo $max_js; ?>;
        var newIndex = 0;
        var BUNNY_NONCE = '<?php echo esc_js( $bunny_nonce ); ?>';

        function getTotal() {
            var saved = document.querySelectorAll('#mxwm-shorts-list .mxwm-short-item:not([style*="display: none"])').length;
            var added = document.querySelectorAll('#mxwm-new-shorts-list .mxwm-new-short-row').length;
            return saved + added;
        }

        function updateCounter() {
            var total = getTotal();
            var el    = document.getElementById('mxwm-shorts-counter');
            var color = total >= MAX ? '#d63638' : '#00a32a';
            el.innerHTML = 'SHORTS: <b style="color:' + color + ';">' + total + ' / ' + MAX + '</b>';
            document.getElementById('mxwm-add-short-btn-wrap').style.display = total >= MAX ? 'none' : 'block';
        }

        function buildNewRow(idx) {
            var n  = 'mxwm_new_shorts[' + idx + ']';
            var h  = '<div class="mxwm-new-short-row" data-idx="' + idx + '" style="padding:12px;background:#fff;border:1px solid #c3e6cb;border-left:4px solid #00a32a;border-radius:4px;margin-bottom:10px;">';
            h += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
            h += '<span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">🆕 Nuevo Short</span>';
            h += '<button type="button" class="button button-small button-link-delete mxwm-remove-new-short" style="color:#a00;">✕ Quitar</button>';
            h += '</div>';
            // Título + Proveedor
            h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px;">';
            h += '<div><label style="font-weight:600;font-size:12px;">Título</label>';
            h += '<input type="text" name="' + n + '[title]" placeholder="Ej: Resumen en 90s" style="width:100%;" /></div>';
            h += '<div><label style="font-weight:600;font-size:12px;">Proveedor</label>';
            h += '<select name="' + n + '[provider]" class="mxwm-provider-select" style="width:100%;">';
            h += '<option value="youtube">YouTube</option><option value="vimeo">Vimeo</option><option value="bunny">🐰 Bunny Stream</option>';
            h += '</select></div></div>';
            // Sección URL (YouTube / Vimeo)
            h += '<div class="mxwm-url-section" style="margin-bottom:8px;">';
            h += '<label style="font-weight:600;font-size:12px;">URL del Video <span style="color:#999;font-weight:400;">(formato vertical/shorts)</span></label>';
            h += '<input type="url" name="' + n + '[url]" class="mxwm-short-url-input" placeholder="https://youtube.com/shorts/..." style="width:100%;" />';
            h += '<div class="mxwm-url-status" style="font-size:11px;margin-top:3px;min-height:16px;"></div></div>';
            // Sección Bunny (oculta por defecto)
            h += '<div class="mxwm-bunny-section" style="display:none;margin-bottom:8px;">';
            h += '<label style="font-weight:600;font-size:12px;">Bunny Video ID <span style="color:#999;font-weight:400;">(cópialo desde tu librería Bunny)</span></label>';
            h += '<div style="display:flex;gap:8px;align-items:center;">';
            h += '<input type="text" name="' + n + '[bunny_video_id]" class="mxwm-bunny-id-input" placeholder="abc123-def456-ghi789" style="flex:1;" />';
            h += '<button type="button" class="button mxwm-verify-bunny">✔ Verificar</button></div>';
            h += '<div class="mxwm-bunny-status" style="font-size:11px;margin-top:3px;min-height:16px;"></div></div>';
            // Duración + Público
            h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px;">';
            h += '<div><label style="font-weight:600;font-size:12px;">Duración (seg, máx 180)</label>';
            h += '<input type="number" name="' + n + '[duration]" min="1" max="180" placeholder="90" style="width:100%;" /></div>';
            h += '<div><label style="font-weight:600;font-size:12px;">¿Publicar?</label>';
            h += '<div style="margin-top:5px;">';
            h += '<label><input type="checkbox" name="' + n + '[public]" class="mxwm-public-check" value="1" disabled /> Sí, público</label>';
            h += '<div style="font-size:10px;color:#999;margin-top:2px;" class="mxwm-public-note">Solo disponible para Bunny Stream</div>';
            h += '</div></div></div>';
            // Descripción
            h += '<div><label style="font-weight:600;font-size:12px;">Descripción (opcional)</label>';
            h += '<textarea name="' + n + '[description]" rows="2" placeholder="Breve descripción..." style="width:100%;"></textarea></div>';
            h += '</div>';
            return h;
        }

        document.getElementById('mxwm-add-short-btn').addEventListener('click', function(){
            if (getTotal() >= MAX) { alert('Límite de ' + MAX + ' shorts alcanzado.'); return; }
            newIndex++;
            document.getElementById('mxwm-new-shorts-list').insertAdjacentHTML('beforeend', buildNewRow(newIndex));
            updateCounter();
        });

        // ── Cambio de proveedor ──
        document.getElementById('mxwm-new-shorts-list').addEventListener('change', function(e){
            if (!e.target.classList.contains('mxwm-provider-select')) return;
            var row    = e.target.closest('.mxwm-new-short-row');
            var isBunny = e.target.value === 'bunny';
            row.querySelector('.mxwm-url-section').style.display    = isBunny ? 'none' : 'block';
            row.querySelector('.mxwm-bunny-section').style.display   = isBunny ? 'block' : 'none';
            var chk = row.querySelector('.mxwm-public-check');
            chk.disabled = !isBunny;
            if (!isBunny) chk.checked = false;
            row.querySelector('.mxwm-public-note').textContent = isBunny ? '' : 'Solo disponible para Bunny Stream';
        });

        // ── Validación URL vertical ──
        function isVerticalUrl(url) {
            if (!url) return { valid: false, msg: '' };
            if (url.match(/youtu/i)) {
                return url.match(/\/shorts\//i)
                    ? { valid: true,  msg: '✅ YouTube Short detectado' }
                    : { valid: false, msg: '⚠️ Usa una URL con /shorts/ (ej: youtube.com/shorts/ID)' };
            }
            if (url.match(/vimeo\.com/i)) return { valid: true, msg: '✅ Vimeo detectado (verifica que sea vertical)' };
            return { valid: false, msg: '⚠️ URL no reconocida. Solo YouTube Shorts o Vimeo.' };
        }

        document.getElementById('mxwm-new-shorts-list').addEventListener('input', function(e){
            if (!e.target.classList.contains('mxwm-short-url-input')) return;
            var row      = e.target.closest('.mxwm-new-short-row');
            var provider = row.querySelector('.mxwm-provider-select').value;
            if (provider === 'bunny') return;
            var statusEl = row.querySelector('.mxwm-url-status');
            var val      = e.target.value.trim();
            var result   = isVerticalUrl(val);
            if (!val) { statusEl.innerHTML = ''; return; }
            statusEl.innerHTML = result.valid
                ? '<span style="color:#00a32a;">' + result.msg + '</span>'
                : '<span style="color:#d63638;">' + result.msg + '</span>';
            e.target.style.borderColor = result.valid ? '#00a32a' : '#d63638';
            var sel = row.querySelector('.mxwm-provider-select');
            if (val.match(/vimeo/i)) sel.value = 'vimeo';
            else if (val.match(/youtu/i)) sel.value = 'youtube';
        });

        // ── Verificar Bunny Video ──
        document.getElementById('mxwm-new-shorts-list').addEventListener('click', function(e){
            if (!e.target.classList.contains('mxwm-verify-bunny')) return;
            var row      = e.target.closest('.mxwm-new-short-row');
            var videoId  = row.querySelector('.mxwm-bunny-id-input').value.trim();
            var statusEl = row.querySelector('.mxwm-bunny-status');
            var durInput = row.querySelector('input[name*="[duration]"]');
            if (!videoId) { statusEl.innerHTML = '<span style="color:#d63638;">⚠️ Ingresa el Video ID primero.</span>'; return; }
            statusEl.innerHTML = '<span style="color:#666;">⏳ Verificando...</span>';
            e.target.disabled = true;
            var fd = new FormData();
            fd.append('action', 'mxwm_verify_bunny_video');
            fd.append('nonce', BUNNY_NONCE);
            fd.append('video_id', videoId);
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    e.target.disabled = false;
                    if (data.success) {
                        var dur = data.data.duration_seconds;
                        if (durInput && dur > 0) durInput.value = Math.min(180, dur);
                        statusEl.innerHTML = data.data.status === 'ready'
                            ? '<span style="color:#00a32a;">✅ Listo · ' + dur + 's</span>'
                            : '<span style="color:#f0ad4e;">⏳ Procesando (' + data.data.status + ')...</span>';
                    } else {
                        statusEl.innerHTML = '<span style="color:#d63638;">❌ ' + (data.data || 'Error') + '</span>';
                    }
                })
                .catch(function(){ e.target.disabled = false; statusEl.innerHTML = '<span style="color:#d63638;">❌ Error de conexión</span>'; });
        });

        // ── Quitar nuevo short ──
        document.getElementById('mxwm-new-shorts-list').addEventListener('click', function(e){
            if (e.target.classList.contains('mxwm-remove-new-short')) {
                e.target.closest('.mxwm-new-short-row').remove();
                updateCounter();
            }
        });

        // ── Eliminar short existente ──
        document.getElementById('mxwm-shorts-list').addEventListener('click', function(e){
            if (e.target.classList.contains('mxwm-delete-existing')) {
                var row = e.target.closest('.mxwm-short-item');
                row.style.display = 'none';
                var input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'mxwm_delete_short[]';
                input.value = e.target.getAttribute('data-id');
                document.getElementById('mxwm-shorts-container').appendChild(input);
                updateCounter();
            }
        });
    })();
    </script>
    <?php

}

function mxwm_render_short_row( $short, $order ) {
    $provider_labels  = array( 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'bunny' => '🐰 Bunny Stream', 'self' => 'Self-hosted' );
    $provider_label   = isset( $provider_labels[ $short->video_provider ] ) ? $provider_labels[ $short->video_provider ] : $short->video_provider;
    $public_label     = $short->is_public ? '🌐 Público' : '🔒 Privado';
    $public_color     = $short->is_public ? '#00a32a' : '#666';
    $duration_display = $short->duration_seconds ? gmdate( 'i:s', $short->duration_seconds ) : '—';
    $is_bunny         = ( $short->video_provider === 'bunny' );

    echo '<div class="mxwm-short-item" style="padding:12px; background:#fff; border:1px solid #ddd; border-left:4px solid #E53935; border-radius:4px; margin-bottom:10px;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:flex-start;">';
    echo '<div style="flex:1;">';
    echo '<div style="display:flex; align-items:center; gap:10px; margin-bottom:5px;">';
    echo '<span style="background:#E53935; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600;">📱 Short #' . esc_html( $order ) . '</span>';
    echo '<strong>' . esc_html( $short->title ?: 'Sin título' ) . '</strong>';
    echo '</div>';
    echo '<div style="font-size:12px; color:#666; display:flex; gap:15px;">';
    echo '<span>🎬 ' . esc_html( $provider_label ) . '</span>';
    echo '<span>⏱️ ' . esc_html( $duration_display ) . '</span>';
    echo '<span style="color:' . $public_color . ';">' . $public_label . '</span>';
    echo '</div>';
    if ( $is_bunny ) {
        $bunny_id = isset( $short->bunny_video_id ) ? $short->bunny_video_id : '';
        echo '<div style="font-size:12px; color:#999; margin-top:4px;">🐰 ID: ' . esc_html( $bunny_id ?: '(sin ID)' ) . '</div>';
    } else {
        echo '<div style="font-size:12px; color:#999; margin-top:4px;">' . esc_html( $short->video_url ) . '</div>';
    }
    echo '</div>';

    echo '<div style="display:flex; gap:8px; align-items:center;">';
    $toggle_checked  = $short->is_public ? 'checked' : '';
    $toggle_disabled = $is_bunny ? '' : 'disabled';
    $toggle_opacity  = $is_bunny ? '1' : '0.4';
    echo '<label style="font-size:12px; display:flex; align-items:center; gap:4px; opacity:' . $toggle_opacity . ';" title="' . ( $is_bunny ? 'Compartir en galería pública' : 'Solo Bunny Stream puede ser público' ) . '">';
    echo '<input type="checkbox" name="mxwm_short_public[' . esc_attr( $short->id ) . ']" value="1" ' . $toggle_checked . ' ' . $toggle_disabled . ' />';
    echo '🌐</label>';
    echo '<input type="hidden" name="mxwm_existing_short_ids[]" value="' . esc_attr( $short->id ) . '" />';
    echo '<button type="button" class="button button-small button-link-delete mxwm-delete-existing" data-id="' . esc_attr( $short->id ) . '" style="color:#a00;">🗑️</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function mxwm_save_lesson_shorts_metabox( $post_id ) {
    if ( ! isset( $_POST['mxwm_lesson_shorts_nonce'] ) || ! wp_verify_nonce( $_POST['mxwm_lesson_shorts_nonce'], 'mxwm_save_lesson_shorts' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_lesson_shorts';
    $max   = defined('MXWM_MAX_SHORTS_PER_LESSON') ? MXWM_MAX_SHORTS_PER_LESSON : 10;

    // 1. Procesar eliminaciones
    if ( ! empty( $_POST['mxwm_delete_short'] ) && is_array( $_POST['mxwm_delete_short'] ) ) {
        foreach ( $_POST['mxwm_delete_short'] as $short_id ) {
            $wpdb->delete( $table, array( 'id' => (int) $short_id, 'lesson_id' => $post_id ) );
        }
    }

    // 2. Actualizar flags is_public de shorts existentes
    if ( ! empty( $_POST['mxwm_existing_short_ids'] ) && is_array( $_POST['mxwm_existing_short_ids'] ) ) {
        foreach ( $_POST['mxwm_existing_short_ids'] as $existing_id ) {
            $existing_id = (int) $existing_id;
            $is_public = isset( $_POST['mxwm_short_public'][ $existing_id ] ) ? 1 : 0;
            $wpdb->update(
                $table,
                array( 'is_public' => $is_public ),
                array( 'id' => $existing_id, 'lesson_id' => $post_id )
            );
        }
    }

    // 3. Insertar TODOS los nuevos shorts del array dinámico
    if ( ! empty( $_POST['mxwm_new_shorts'] ) && is_array( $_POST['mxwm_new_shorts'] ) ) {
        $current_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE lesson_id = %d", $post_id
        ) );

        // Get max display_order
        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(MAX(display_order), 0) FROM $table WHERE lesson_id = %d", $post_id
        ) );

        foreach ( $_POST['mxwm_new_shorts'] as $new_short ) {
            if ( $current_count >= $max ) break;

            $provider       = sanitize_text_field( $new_short['provider'] ?? 'youtube' );
            $title          = sanitize_text_field( $new_short['title'] ?? '' );
            $duration       = min( 180, absint( $new_short['duration'] ?? 0 ) );
            $description    = sanitize_textarea_field( $new_short['description'] ?? '' );
            $bunny_video_id = null;
            $url            = '';

            if ( $provider === 'bunny' ) {
                $bunny_video_id = sanitize_text_field( $new_short['bunny_video_id'] ?? '' );
                if ( empty( $bunny_video_id ) ) continue;
                $is_public = isset( $new_short['public'] ) ? 1 : 0;
            } else {
                $url = isset( $new_short['url'] ) ? esc_url_raw( $new_short['url'] ) : '';
                if ( empty( $url ) ) continue;
                if ( strpos( $url, 'youtu' ) !== false && strpos( $url, '/shorts/' ) === false ) continue;
                if ( strpos( $url, 'vimeo.com' ) !== false ) $provider = 'vimeo';
                elseif ( strpos( $url, 'youtu' ) !== false ) $provider = 'youtube';
                $is_public = 0; // YouTube/Vimeo nunca públicos
            }

            $max_order++;
            $current_count++;

            $wpdb->insert( $table, array(
                'lesson_id'        => $post_id,
                'video_url'        => $url,
                'video_provider'   => $provider,
                'bunny_video_id'   => $bunny_video_id,
                'duration_seconds' => $duration,
                'title'            => $title ?: 'Short #' . $current_count,
                'description'      => $description,
                'is_public'        => $is_public,
                'display_order'    => $max_order,
                'created_at'       => current_time( 'mysql' ),
            ) );
        }
    }
}
add_action( 'save_post_mxwm_lesson', 'mxwm_save_lesson_shorts_metabox' );
