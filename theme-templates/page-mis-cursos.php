<?php
/* Template Name: Mis Cursos */
get_header();

if ( ! is_user_logged_in() ) {
    echo '<div style="max-width:900px;margin:60px auto;text-align:center;"><p>Debes <a href="' . wp_login_url( get_permalink() ) . '">iniciar sesión</a> para ver tus cursos.</p></div>';
    get_footer();
    exit;
}

global $wpdb;
$user_id    = get_current_user_id();
$msg_get    = isset( $_GET['mxwm_msg'] ) ? sanitize_key( $_GET['mxwm_msg'] ) : '';
// Puede crear cursos: admin, editor, o nivel de membresía Empresa (ID 2, 3 o 4)
$can_create = current_user_can('publish_posts')
           || current_user_can('manage_options')
           || current_user_can('edit_posts')
           || ( function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel( array(2,3,4), $user_id ) );
$tab        = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'ver';
$base_url   = get_permalink();
?>
<style>
.mxwm-cursos-wrap{max-width:960px;margin:40px auto;padding:0 20px}
.mxwm-cursos-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.mxwm-cursos-header h1{font-size:1.8rem;margin:0}
.mxwm-btn{display:inline-block;padding:10px 22px;border-radius:8px;font-weight:600;text-decoration:none;font-size:.95rem;transition:all .2s;cursor:pointer;border:none}
.mxwm-btn-primary{background:#0073aa;color:#fff}.mxwm-btn-primary:hover{background:#005f8d;color:#fff}
.mxwm-btn-sm{padding:6px 14px;font-size:.82rem}
.mxwm-btn-secondary{background:#f0f0f0;color:#333;border:1px solid #ddd}.mxwm-btn-secondary:hover{background:#e0e0e0;color:#333}
.mxwm-tabs{display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin-bottom:24px}
.mxwm-tab{padding:10px 20px;font-weight:600;color:#64748b;border-bottom:3px solid transparent;margin-bottom:-2px;text-decoration:none;font-size:.93rem;transition:all .15s}
.mxwm-tab.active{color:#0073aa;border-bottom-color:#0073aa}
.mxwm-cursos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px}
.mxwm-curso-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:box-shadow .2s}
.mxwm-curso-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.12)}
.mxwm-curso-thumb{width:100%;height:158px;object-fit:cover;display:block}
.mxwm-thumb-placeholder{width:100%;height:158px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;font-size:2.8rem}
.mxwm-curso-body{padding:16px}
.mxwm-curso-title{font-size:1rem;font-weight:700;margin:0 0 6px;line-height:1.35;color:#1a202c}
.mxwm-curso-meta{font-size:.8rem;color:#64748b;margin-bottom:8px}
.mxwm-curso-status{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.75rem;font-weight:600;margin-bottom:6px}
.status-publish{background:#d1fae5;color:#065f46}.status-draft{background:#fef9c3;color:#713f12}.status-pending{background:#dbeafe;color:#1e3a8a}
.mxwm-progress-bar{height:6px;background:#e2e8f0;border-radius:3px;margin:8px 0 4px;overflow:hidden}
.mxwm-progress-fill{height:100%;background:linear-gradient(90deg,#0073aa,#00b4d8);border-radius:3px;transition:width .4s}
.mxwm-curso-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.mxwm-empty{text-align:center;padding:60px 20px;color:#64748b}
.mxwm-empty-icon{font-size:3.5rem;margin-bottom:12px}
.mxwm-notice{padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:.95rem}
.mxwm-notice.success{background:#d1fae5;color:#065f46;border-left:4px solid #10b981}
.mxwm-notice.info{background:#dbeafe;color:#1e3a8a;border-left:4px solid #3b82f6}
@media(max-width:640px){.mxwm-cursos-grid{grid-template-columns:1fr}}
</style>

<div class="mxwm-cursos-wrap">

<?php
$mensajes = array(
    'curso_publicado' => array( 'success', '✅ Curso publicado correctamente.' ),
    'curso_guardado'  => array( 'success', '✅ Borrador guardado correctamente.' ),
    'curso_eliminado' => array( 'info',    '🗑️ Curso eliminado correctamente.' ),
);
if ( $msg_get && isset( $mensajes[$msg_get] ) ) {
    list( $tipo, $texto ) = $mensajes[$msg_get];
    echo '<div class="mxwm-notice ' . $tipo . '">' . $texto . '</div>';
}
?>

<div class="mxwm-cursos-header">
    <h1>📚 Cursos</h1>
    <?php if ( $can_create ) : ?>
        <a href="<?php echo esc_url( admin_url('post-new.php?post_type=mxwm_course') ); ?>" class="mxwm-btn mxwm-btn-primary">
            + Crear Curso
        </a>
    <?php endif; ?>
</div>

<div class="mxwm-tabs">
    <a href="<?php echo esc_url( add_query_arg('tab', 'ver', $base_url) ); ?>" class="mxwm-tab <?php echo $tab === 'ver' ? 'active' : ''; ?>">Mis Inscripciones</a>
    <?php if ( $can_create ) : ?>
    <a href="<?php echo esc_url( add_query_arg('tab', 'instructor', $base_url) ); ?>" class="mxwm-tab <?php echo $tab === 'instructor' ? 'active' : ''; ?>">Mis Cursos Creados</a>
    <?php endif; ?>
</div>

<?php if ( $tab === 'instructor' && $can_create ) :
    // ── Pestaña: Cursos creados por este instructor ──────────────────
    $cursos = get_posts( array(
        'post_type'      => 'mxwm_course',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'author'         => $user_id,
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ) );

    if ( empty( $cursos ) ) : ?>
        <div class="mxwm-empty">
            <div class="mxwm-empty-icon">🎙</div>
            <p><strong>Aún no has creado ningún curso.</strong></p>
            <p>Comparte tu conocimiento con la comunidad MXWM.</p>
            <a href="<?php echo esc_url( admin_url('post-new.php?post_type=mxwm_course') ); ?>" class="mxwm-btn mxwm-btn-primary" style="margin-top:16px;">
                + Crear mi primer curso
            </a>
        </div>
    <?php else : ?>
        <div class="mxwm-cursos-grid">
        <?php foreach ( $cursos as $c ) :
            $thumb        = get_the_post_thumbnail_url( $c->ID, 'medium' );
            $status       = $c->post_status;
            $precio       = (float) get_field( 'price', $c->ID );
            $total_enroll = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_enrollments WHERE course_id=%d AND status='active'", $c->ID
            ) );
            $rating_avg = (float) get_post_meta( $c->ID, '_mxwm_rating_avg', true );
        ?>
            <div class="mxwm-curso-card">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="" class="mxwm-curso-thumb">
                <?php else : ?>
                    <div class="mxwm-thumb-placeholder">🎓</div>
                <?php endif; ?>
                <div class="mxwm-curso-body">
                    <div class="mxwm-curso-title"><?php echo esc_html( $c->post_title ); ?></div>
                    <div class="mxwm-curso-meta">
                        👥 <?php echo $total_enroll; ?> inscritos
                        <?php if ( $rating_avg > 0 ) echo ' &nbsp;⭐ ' . number_format($rating_avg, 1); ?>
                        &nbsp;|&nbsp; <?php echo $precio > 0 ? '$'.number_format($precio,0).' MXN' : 'Gratuito'; ?>
                    </div>
                    <span class="mxwm-curso-status status-<?php echo $status; ?>">
                        <?php echo $status==='publish' ? '✅ Publicado' : ($status==='draft' ? '📝 Borrador' : '⏳ En revisión'); ?>
                    </span>
                    <div class="mxwm-curso-actions">
                        <a href="<?php echo esc_url( admin_url('post.php?post='.$c->ID.'&action=edit') ); ?>" class="mxwm-btn mxwm-btn-secondary mxwm-btn-sm">✏️ Editar</a>
                        <a href="<?php echo esc_url( get_permalink($c->ID) ); ?>" class="mxwm-btn mxwm-btn-secondary mxwm-btn-sm" target="_blank">👁 Ver</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif;

else :
    // ── Pestaña: Cursos en los que está inscrito (tab=ver, default) ─
    $inscripciones = $wpdb->get_results( $wpdb->prepare(
        "SELECT e.course_id, e.enrolled_at
         FROM {$wpdb->prefix}mxwm_enrollments e
         WHERE e.user_id=%d AND e.status='active'
         ORDER BY e.enrolled_at DESC",
        $user_id
    ) );

    if ( empty( $inscripciones ) ) : ?>
        <div class="mxwm-empty">
            <div class="mxwm-empty-icon">🎓</div>
            <p><strong>Aún no estás inscrito en ningún curso.</strong></p>
            <p>Explora el catálogo y comienza a aprender.</p>
            <a href="<?php echo esc_url( home_url('/cursos/') ); ?>" class="mxwm-btn mxwm-btn-primary" style="margin-top:16px;">
                🔍 Ver catálogo de cursos
            </a>
        </div>
    <?php else : ?>
        <div class="mxwm-cursos-grid">
        <?php foreach ( $inscripciones as $ins ) :
            $c_id  = (int) $ins->course_id;
            $post  = get_post( $c_id );
            if ( ! $post ) continue;
            $thumb = get_the_post_thumbnail_url( $c_id, 'medium' );
            $total_lessons = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='course_id' AND CAST(pm.meta_value AS UNSIGNED)=%d
                 WHERE p.post_type='mxwm_lesson' AND p.post_status='publish'", $c_id
            ) );
            $completed = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_lesson_progress
                 WHERE user_id=%d AND course_id=%d AND completed_at IS NOT NULL",
                $user_id, $c_id
            ) );
            $pct = $total_lessons > 0 ? round( $completed / $total_lessons * 100 ) : 0;
        ?>
            <div class="mxwm-curso-card">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="" class="mxwm-curso-thumb">
                <?php else : ?>
                    <div class="mxwm-thumb-placeholder">📖</div>
                <?php endif; ?>
                <div class="mxwm-curso-body">
                    <div class="mxwm-curso-title"><?php echo esc_html( $post->post_title ); ?></div>
                    <div class="mxwm-curso-meta">Inscrito: <?php echo date_i18n('d M Y', strtotime($ins->enrolled_at)); ?></div>
                    <div class="mxwm-progress-bar">
                        <div class="mxwm-progress-fill" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <div style="font-size:.8rem;color:#64748b;margin-bottom:4px;">
                        <?php echo $pct; ?>% completado · <?php echo $completed; ?>/<?php echo $total_lessons; ?> lecciones
                    </div>
                    <div class="mxwm-curso-actions">
                        <a href="<?php echo esc_url( get_permalink($c_id) ); ?>" class="mxwm-btn mxwm-btn-primary mxwm-btn-sm">
                            <?php echo $pct > 0 ? '▶ Continuar' : '▶ Comenzar'; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif;
endif; ?>

</div><!-- .mxwm-cursos-wrap -->
<?php get_footer(); ?>
