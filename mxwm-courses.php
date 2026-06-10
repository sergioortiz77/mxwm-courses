<?php
/**
 * Plugin Name: MX with ME Courses LMS
 * Plugin URI: https://mxwithme.com
 * Description: Sistema de gestión de cursos (Marketplace) para MX with ME.
 * Version: 1.3.0
 * Author: MXWM Team
 * Text Domain: mxwm-courses
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'MXWM_COURSES_VERSION', '1.3.0' );
define( 'MXWM_COURSES_DIR', plugin_dir_path( __FILE__ ) );
define( 'MXWM_COURSES_URL', plugin_dir_url( __FILE__ ) );
define( 'MXWM_MAX_SHORTS_PER_LESSON', 10 );

// Incluir dependencias
require_once MXWM_COURSES_DIR . 'inc/cpt-courses.php';
require_once MXWM_COURSES_DIR . 'inc/taxonomies.php';
require_once MXWM_COURSES_DIR . 'inc/acf-fields.php';
require_once MXWM_COURSES_DIR . 'inc/db-schema.php';
require_once MXWM_COURSES_DIR . 'inc/lesson-resources.php';
require_once MXWM_COURSES_DIR . 'inc/bunny-integration.php';
require_once MXWM_COURSES_DIR . 'inc/lesson-shorts.php';
require_once MXWM_COURSES_DIR . 'inc/file-processors.php';
require_once MXWM_COURSES_DIR . 'inc/enrollments.php';
require_once MXWM_COURSES_DIR . 'inc/bp-integration.php'; // Hooks BuddyPress + bbPress
require_once MXWM_COURSES_DIR . 'inc/rest-api-courses.php';

/**
 * Activación del plugin
 */
function mxwm_courses_activate() {
    mxwm_courses_register_cpts();
    mxwm_courses_register_taxonomies();
    mxwm_courses_install_db_schema();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mxwm_courses_activate' );

/**
 * Desactivación del plugin
 */
function mxwm_courses_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mxwm_courses_deactivate' );
