<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mxwm_courses_register_cpts() {
    // CPT: mxwm_course
    $course_labels = array(
        'name'                  => _x( 'Cursos', 'Post Type General Name', 'mxwm-courses' ),
        'singular_name'         => _x( 'Curso', 'Post Type Singular Name', 'mxwm-courses' ),
        'menu_name'             => __( 'Cursos', 'mxwm-courses' ),
        'name_admin_bar'        => __( 'Curso', 'mxwm-courses' ),
        'add_new'               => __( 'Añadir Nuevo', 'mxwm-courses' ),
        'add_new_item'          => __( 'Añadir Nuevo Curso', 'mxwm-courses' ),
    );
    $course_args = array(
        'label'                 => __( 'Curso', 'mxwm-courses' ),
        'labels'                => $course_labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ),
        // Las taxonomías se registran por separado en taxonomies.php, si ponemos el nombre aquí se vinculan
        'taxonomies'            => array( 'mxwm_course_category', 'mxwm_course_difficulty' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-welcome-learn-more',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'rewrite'               => array( 'slug' => 'curso' ),
    );
    register_post_type( 'mxwm_course', $course_args );

    // CPT: mxwm_lesson
    $lesson_labels = array(
        'name'                  => _x( 'Lecciones', 'Post Type General Name', 'mxwm-courses' ),
        'singular_name'         => _x( 'Lección', 'Post Type Singular Name', 'mxwm-courses' ),
        'menu_name'             => __( 'Lecciones', 'mxwm-courses' ),
    );
    $lesson_args = array(
        'label'                 => __( 'Lección', 'mxwm-courses' ),
        'labels'                => $lesson_labels,
        'supports'              => array( 'title', 'editor', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => 'edit.php?post_type=mxwm_course', // Asignado bajo el menú de Cursos
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false, // Se accederá vía API, no tendrán página web propia
        'show_in_rest'          => true,
    );
    register_post_type( 'mxwm_lesson', $lesson_args );

    // CPT: mxwm_module
    $module_labels = array(
        'name'                  => _x( 'Módulos', 'Post Type General Name', 'mxwm-courses' ),
        'singular_name'         => _x( 'Módulo', 'Post Type Singular Name', 'mxwm-courses' ),
        'menu_name'             => __( 'Módulos', 'mxwm-courses' ),
    );
    $module_args = array(
        'label'                 => __( 'Módulo', 'mxwm-courses' ),
        'labels'                => $module_labels,
        'supports'              => array( 'title', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => 'edit.php?post_type=mxwm_course',
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'show_in_rest'          => true,
    );
    register_post_type( 'mxwm_module', $module_args );
}
add_action( 'init', 'mxwm_courses_register_cpts', 0 );
