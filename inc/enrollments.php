<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MXWM Enrollments & Progress
 * 
 * Funciones helper para gestión de inscripciones y progreso.
 * Operan sobre las tablas: wp_mxwm_enrollments y wp_mxwm_lesson_progress
 */

// ─────────────────────────────────────────────
// INSCRIPCIONES (Enrollments)
// ─────────────────────────────────────────────

/**
 * Verifica si un usuario está inscrito activamente en un curso
 */
function mxwm_is_enrolled( $user_id, $course_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_enrollments';
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND course_id = %d AND status = 'active'",
        $user_id, $course_id
    ) );
}

/**
 * Inscribe a un usuario en un curso
 * 
 * @return int|WP_Error  ID de la inscripción o error
 */
function mxwm_enroll_user( $user_id, $course_id, $payment_method = 'free', $transaction_id = null ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_enrollments';

    if ( mxwm_is_enrolled( $user_id, $course_id ) ) {
        return new WP_Error( 'already_enrolled', 'Ya estás inscrito en este curso.', array( 'status' => 409 ) );
    }

    $result = $wpdb->insert( $table, array(
        'user_id'        => $user_id,
        'course_id'      => $course_id,
        'payment_method' => $payment_method,
        'transaction_id' => $transaction_id,
        'status'         => 'active',
        'enrolled_at'    => current_time( 'mysql' ),
    ) );

    if ( $result === false ) {
        return new WP_Error( 'db_error', 'Error al inscribir al usuario.', array( 'status' => 500 ) );
    }

    $enrollment_id = $wpdb->insert_id;

    // Disparar hooks de post-inscripción (BP group join, push notification, etc.)
    do_action( 'mxwm_after_enrollment', $user_id, $course_id );

    return $enrollment_id;
}

/**
 * Obtiene el registro de inscripción activa de un usuario en un curso
 */
function mxwm_get_enrollment( $user_id, $course_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_enrollments';
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND course_id = %d AND status = 'active'",
        $user_id, $course_id
    ) );
}

/**
 * Lista todas las inscripciones activas de un usuario
 */
function mxwm_get_user_enrollments( $user_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_enrollments';
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND status = 'active' ORDER BY enrolled_at DESC",
        $user_id
    ) );
}

/**
 * Cuenta el total de estudiantes inscritos en un curso
 */
function mxwm_get_enrollment_count( $course_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_enrollments';
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE course_id = %d AND status = 'active'",
        $course_id
    ) );
}

// ─────────────────────────────────────────────
// PROGRESO DE LECCIONES
// ─────────────────────────────────────────────

/**
 * Obtiene el progreso de una lección para un usuario
 */
function mxwm_get_lesson_progress( $user_id, $lesson_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_lesson_progress';
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND lesson_id = %d",
        $user_id, $lesson_id
    ) );
}

/**
 * Actualiza (o crea) el progreso de una lección
 * Auto-completa si percentage >= 90
 * 
 * @return int  ID del registro de progreso
 */
function mxwm_update_lesson_progress( $user_id, $lesson_id, $course_id, $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxwm_lesson_progress';

    $existing = mxwm_get_lesson_progress( $user_id, $lesson_id );

    $record = array(
        'user_id'                => $user_id,
        'lesson_id'              => $lesson_id,
        'course_id'              => $course_id,
        'video_position_seconds' => isset( $data['video_position_seconds'] ) ? (int) $data['video_position_seconds'] : 0,
        'percentage'             => isset( $data['percentage'] ) ? min( 100, max( 0, (int) $data['percentage'] ) ) : 0,
        'updated_at'             => current_time( 'mysql' ),
    );

    if ( $existing ) {
        // Solo avanzar, no retroceder (evita trampas de "descompletar")
        if ( $record['percentage'] < $existing->percentage ) {
            $record['percentage'] = $existing->percentage;
        }
        // Auto-completar si supera 90%
        if ( $record['percentage'] >= 90 && ! $existing->completed_at ) {
            $record['completed_at'] = current_time( 'mysql' );
        }
        $wpdb->update( $table, $record, array( 'id' => $existing->id ) );
        return $existing->id;
    } else {
        // Insertar nuevo registro
        $record['started_at'] = current_time( 'mysql' );
        if ( $record['percentage'] >= 90 ) {
            $record['completed_at'] = current_time( 'mysql' );
        }
        $wpdb->insert( $table, $record );
        return $wpdb->insert_id;
    }
}

/**
 * Obtiene estadísticas de progreso global de un usuario en un curso
 */
function mxwm_get_course_progress( $user_id, $course_id ) {
    global $wpdb;
    $progress_table = $wpdb->prefix . 'mxwm_lesson_progress';

    // Total de lecciones publicadas para este curso
    $lessons = get_posts( array(
        'post_type'      => 'mxwm_lesson',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array( 'key' => 'course_id', 'value' => $course_id ),
        ),
    ) );
    $total_lessons = count( $lessons );

    if ( $total_lessons === 0 ) {
        return array(
            'total_lessons'     => 0,
            'completed_lessons' => 0,
            'started_lessons'   => 0,
            'percentage'        => 0,
            'is_complete'       => false,
        );
    }

    $completed = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $progress_table WHERE user_id = %d AND course_id = %d AND completed_at IS NOT NULL",
        $user_id, $course_id
    ) );

    $started = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $progress_table WHERE user_id = %d AND course_id = %d",
        $user_id, $course_id
    ) );

    $pct = round( ( $completed / $total_lessons ) * 100 );

    return array(
        'total_lessons'     => $total_lessons,
        'completed_lessons' => $completed,
        'started_lessons'   => $started,
        'percentage'        => $pct,
        'is_complete'       => ( $completed >= $total_lessons ),
    );
}
