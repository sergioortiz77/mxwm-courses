<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mxwm_courses_install_db_schema() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // Tabla de Recursos de la Lección
    $table_resources = $wpdb->prefix . 'mxwm_lesson_resources';
    $sql_resources = "CREATE TABLE $table_resources (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lesson_id bigint(20) NOT NULL,
        type varchar(32) NOT NULL,
        title varchar(255) NOT NULL,
        attachment_id bigint(20) DEFAULT NULL,
        url varchar(500) DEFAULT NULL,
        file_size_bytes bigint(20) DEFAULT 0,
        mime_type varchar(100) DEFAULT '',
        resource_order int(11) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY lesson_id (lesson_id)
    ) $charset_collate;";
    dbDelta( $sql_resources );

    // Tabla de Inscripciones (Enrollments)
    $table_enrollments = $wpdb->prefix . 'mxwm_enrollments';
    $sql_enrollments = "CREATE TABLE $table_enrollments (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
        payment_method varchar(50) DEFAULT 'free',
        transaction_id varchar(150) DEFAULT NULL,
        status varchar(50) DEFAULT 'active',
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY course_id (course_id)
    ) $charset_collate;";
    dbDelta( $sql_enrollments );

    // Tabla de Progreso de Lecciones
    $table_progress = $wpdb->prefix . 'mxwm_lesson_progress';
    $sql_progress = "CREATE TABLE $table_progress (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        lesson_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        started_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        video_position_seconds int(11) DEFAULT 0,
        percentage int(11) DEFAULT 0,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_lesson (user_id, lesson_id),
        KEY course_id (course_id)
    ) $charset_collate;";
    dbDelta( $sql_progress );

    // Tabla de Shorts de la Lección (Píldoras de Conocimiento)
    $table_shorts = $wpdb->prefix . 'mxwm_lesson_shorts';
    $sql_shorts = "CREATE TABLE $table_shorts (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lesson_id bigint(20) NOT NULL,
        video_url varchar(500) NOT NULL DEFAULT '',
        video_provider varchar(20) DEFAULT 'youtube',
        bunny_video_id varchar(150) DEFAULT NULL,
        duration_seconds int(11) DEFAULT 0,
        title varchar(255) DEFAULT '',
        description text,
        is_public tinyint(1) DEFAULT 0,
        display_order int(11) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_lesson (lesson_id),
        KEY idx_public (is_public, created_at)
    ) $charset_collate;";
    dbDelta( $sql_shorts );

    // Tabla de Reseñas de Cursos
    $table_reviews = $wpdb->prefix . 'mxwm_reviews';
    $sql_reviews = "CREATE TABLE $table_reviews (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        rating tinyint(1) NOT NULL DEFAULT 5,
        content text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_course (user_id, course_id),
        KEY course_id (course_id)
    ) $charset_collate;";
    dbDelta( $sql_reviews );
}

/**
 * Migración para instalaciones existentes:
 * Agrega la columna bunny_video_id si no existe.
 * Se llama en 'plugins_loaded' para ejecutarse en cada carga.
 */
function mxwm_courses_maybe_upgrade_db() {
    global $wpdb;

    // Migración 1: columna bunny_video_id en shorts
    $table = $wpdb->prefix . 'mxwm_lesson_shorts';
    $col = $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE 'bunny_video_id'" );
    if ( empty( $col ) ) {
        $wpdb->query( "ALTER TABLE $table ADD COLUMN bunny_video_id VARCHAR(150) DEFAULT NULL AFTER video_provider" );
    }

    // Migración 2: tabla de reseñas (para instalaciones previas)
    $reviews_table = $wpdb->prefix . 'mxwm_reviews';
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '$reviews_table'" );
    if ( ! $exists ) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $sql = "CREATE TABLE $reviews_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            rating tinyint(1) NOT NULL DEFAULT 5,
            content text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_course (user_id, course_id),
            KEY course_id (course_id)
        ) $charset_collate;";
        dbDelta( $sql );
    }
}
add_action( 'plugins_loaded', 'mxwm_courses_maybe_upgrade_db' );
