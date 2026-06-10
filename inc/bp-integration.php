<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MXWM BP Integration
 *
 * Hooks que sincronizan cursos con BuddyPress y bbPress:
 *  1. Al publicar un curso: crea grupo BP + foro bbPress automáticamente.
 *  2. Al inscribirse en un curso: une al usuario al grupo BP del curso.
 *  3. Al desinscribirse (futuro): retira del grupo.
 */

/* =================================================================
 * HOOK 1: Crear grupo BP + foro bbPress al publicar un curso
 * ================================================================= */

/**
 * Se dispara cuando un mxwm_course cambia de estado a 'publish'.
 */
add_action( 'transition_post_status', 'mxwm_on_course_published', 10, 3 );

function mxwm_on_course_published( $new_status, $old_status, $post ) {
    // Solo cursos recién publicados (no re-publicaciones repetidas)
    if ( $post->post_type !== 'mxwm_course' ) return;
    if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

    $course_id = $post->ID;

    // Evitar duplicados: si ya tiene grupo, no hacer nada
    $existing_group = get_field( 'bp_group_id', $course_id );
    if ( $existing_group ) return;

    $instructor_id = (int) $post->post_author;
    $title         = $post->post_title;
    $excerpt       = $post->post_excerpt ?: get_field( 'short_description', $course_id ) ?: '';

    // ── 1a. Crear grupo BuddyPress ──────────────────────────────────
    if ( function_exists( 'groups_create_group' ) ) {
        $group_id = groups_create_group( array(
            'creator_id'   => $instructor_id,
            'name'         => $title,
            'description'  => $excerpt,
            'status'       => 'private',    // privado: solo inscritos entran
            'enable_forum' => false,        // el foro se añade via bbPress directamente
        ) );

        if ( $group_id && ! is_wp_error( $group_id ) ) {
            // Guardar thumbnail del grupo
            $thumb = get_the_post_thumbnail_url( $course_id, 'thumbnail' );
            if ( $thumb ) {
                // Intentar asignar cover image del grupo si BP Extended Profiles lo soporta
                groups_update_groupmeta( $group_id, 'mxwm_course_id', $course_id );
            }

            update_field( 'bp_group_id', $group_id, $course_id );
            error_log( "[MXWM Courses] Grupo BP creado: ID {$group_id} para curso {$course_id}" );
        }
    }

    // ── 1b. Crear foro bbPress vinculado al grupo ───────────────────
    if ( function_exists( 'bbp_insert_forum' ) ) {
        $forum_id = bbp_insert_forum( array(
            'post_title'   => $title . ' — Foro',
            'post_content' => 'Foro oficial del curso: ' . $title,
            'post_author'  => $instructor_id,
            'post_status'  => 'publish',
        ) );

        if ( $forum_id && ! is_wp_error( $forum_id ) ) {
            update_field( 'bp_forum_id', $forum_id, $course_id );
            error_log( "[MXWM Courses] Foro bbPress creado: ID {$forum_id} para curso {$course_id}" );

            // Vincular foro al grupo BP si ambos existen
            $group_id = get_field( 'bp_group_id', $course_id );
            if ( $group_id && function_exists( 'bbp_update_group_forum_ids' ) ) {
                bbp_update_group_forum_ids( $group_id, array( $forum_id ) );
            }
        }
    }
}

/* =================================================================
 * HOOK 2: Auto-unir al grupo BP al inscribirse en un curso
 * ================================================================= */

/**
 * Acción llamada desde mxwm_enroll_user() tras una inscripción exitosa.
 * @param int $user_id
 * @param int $course_id
 */
add_action( 'mxwm_after_enrollment', 'mxwm_bp_join_group_on_enroll', 10, 2 );

function mxwm_bp_join_group_on_enroll( $user_id, $course_id ) {
    if ( ! function_exists( 'groups_join_group' ) ) return;

    $group_id = (int) get_field( 'bp_group_id', $course_id );
    if ( ! $group_id ) return;

    // Verificar que no sea ya miembro
    if ( groups_is_user_member( $user_id, $group_id ) ) return;

    $result = groups_join_group( $group_id, $user_id );
    if ( $result ) {
        error_log( "[MXWM Courses] Usuario {$user_id} unido al grupo BP {$group_id} (curso {$course_id})" );
    }
}

/* =================================================================
 * HOOK 3: Retirar del grupo BP al desinscribirse (soft-delete)
 * ================================================================= */

add_action( 'mxwm_after_unenrollment', 'mxwm_bp_leave_group_on_unenroll', 10, 2 );

function mxwm_bp_leave_group_on_unenroll( $user_id, $course_id ) {
    if ( ! function_exists( 'groups_leave_group' ) ) return;

    $group_id = (int) get_field( 'bp_group_id', $course_id );
    if ( ! $group_id ) return;

    // No expulsar a instructores del grupo
    $post = get_post( $course_id );
    if ( $post && (int) $post->post_author === $user_id ) return;

    groups_leave_group( $group_id, $user_id );
    error_log( "[MXWM Courses] Usuario {$user_id} salió del grupo BP {$group_id} (curso {$course_id})" );
}

/* =================================================================
 * HELPER: disparar acción después de inscribir al usuario
 * Parche para enrollments.php (que no emite el action hook aún)
 * ================================================================= */

add_filter( 'mxwm_enrollment_complete', 'mxwm_fire_enrollment_hooks', 10, 2 );

function mxwm_fire_enrollment_hooks( $enrollment_id, $args ) {
    if ( isset( $args['user_id'] ) && isset( $args['course_id'] ) ) {
        do_action( 'mxwm_after_enrollment', $args['user_id'], $args['course_id'] );
    }
    return $enrollment_id;
}

/* =================================================================
 * NAV ITEMS DE BUDDYPRESS — "Mis Cursos" en el sidebar del perfil
 * ================================================================= */

add_action( 'bp_setup_nav', 'mxwm_bp_setup_courses_nav', 100 );

function mxwm_bp_setup_courses_nav() {
    if ( ! is_user_logged_in() ) return;
    if ( ! function_exists( 'bp_core_new_nav_item' ) ) return;

    $current_user = wp_get_current_user();
    $user_domain  = bp_core_get_user_domain( $current_user->ID );
    $page_url     = home_url( '/mis-cursos/' );

    // ── Ítem principal: "Mis Cursos" ─────────────────────────────────
    bp_core_new_nav_item( array(
        'name'                    => 'Mis Cursos',
        'slug'                    => 'mis-cursos',
        'position'                => 85,
        'screen_function'         => 'mxwm_bp_mis_cursos_screen',
        'default_subnav_slug'     => 'ver',
        'item_css_id'             => 'mis-cursos',
        'show_for_displayed_user' => false,
        'user_has_access'         => bp_is_my_profile(),
    ) );

    // ── Subnav: Ver mis cursos ───────────────────────────────────────
    bp_core_new_subnav_item( array(
        'name'            => 'Ver cursos',
        'slug'            => 'ver',
        'parent_slug'     => 'mis-cursos',
        'parent_url'      => $user_domain . 'mis-cursos/',
        'screen_function' => 'mxwm_bp_mis_cursos_screen',
        'position'        => 10,
        'user_has_access' => bp_is_my_profile(),
    ) );

    // ── Subnav: Crear Curso — cualquier usuario registrado puede crear cursos
    // Regla de negocio: no hay restricción de membresía para ser instructor
    if ( bp_is_my_profile() ) {
        bp_core_new_subnav_item( array(
            'name'            => '+ Crear Curso',
            'slug'            => 'crear-curso',
            'parent_slug'     => 'mis-cursos',
            'parent_url'      => $user_domain . 'mis-cursos/',
            'screen_function' => 'mxwm_bp_crear_curso_screen',
            'position'        => 20,
            'user_has_access' => true,
        ) );
    }
}

/**
 * Screen function para "Mis Cursos" — redirige a la página WP con el template.
 */
function mxwm_bp_mis_cursos_screen() {
    $tab = current_user_can('publish_posts') ? '' : '';
    wp_redirect( home_url( '/mis-cursos/' ) );
    exit;
}

/**
 * Screen function para "Crear Curso".
 * WP-0: Redirige a /crear-curso/ si la página existe (WP-4 la creará).
 * Fallback temporal: WP Admin mientras el wizard no esté disponible.
 */
function mxwm_bp_crear_curso_screen() {
    $wizard_page = get_page_by_path( 'crear-curso' );
    if ( $wizard_page && $wizard_page->post_status === 'publish' ) {
        wp_redirect( home_url( '/crear-curso/' ) );
    } else {
        // Fallback temporal — se elimina cuando WP-4 esté desplegado
        wp_redirect( admin_url( 'post-new.php?post_type=mxwm_course' ) );
    }
    exit;
}

/* =================================================================
 * CREAR PÁGINA "mis-cursos" AUTOMÁTICAMENTE SI NO EXISTE
 * ================================================================= */

add_action( 'init', 'mxwm_maybe_create_courses_page', 20 );

function mxwm_maybe_create_courses_page() {
    // Solo si aún no se ha creado (evitar duplicados)
    if ( get_option( 'mxwm_courses_page_created' ) ) return;

    $existing = get_page_by_path( 'mis-cursos' );
    if ( $existing ) {
        update_option( 'mxwm_courses_page_created', $existing->ID );
        return;
    }

    // Buscar el ID del template en los temas disponibles
    $page_id = wp_insert_post( array(
        'post_title'     => 'Mis Cursos',
        'post_name'      => 'mis-cursos',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_author'    => 1,
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'page_template'  => 'page-mis-cursos.php',
    ) );

    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_option( 'mxwm_courses_page_created', $page_id );
        error_log( "[MXWM Courses] Página 'Mis Cursos' creada: ID {$page_id}" );
    }
}
