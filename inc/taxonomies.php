<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mxwm_courses_register_taxonomies() {
    // Taxonomy: mxwm_course_category
    $cat_labels = array(
        'name'              => _x( 'Categorías de Curso', 'taxonomy general name', 'mxwm-courses' ),
        'singular_name'     => _x( 'Categoría', 'taxonomy singular name', 'mxwm-courses' ),
        'search_items'      => __( 'Buscar Categorías', 'mxwm-courses' ),
        'all_items'         => __( 'Todas las Categorías', 'mxwm-courses' ),
        'parent_item'       => __( 'Categoría Padre', 'mxwm-courses' ),
        'parent_item_colon' => __( 'Categoría Padre:', 'mxwm-courses' ),
        'edit_item'         => __( 'Editar Categoría', 'mxwm-courses' ),
        'update_item'       => __( 'Actualizar Categoría', 'mxwm-courses' ),
        'add_new_item'      => __( 'Añadir Nueva Categoría', 'mxwm-courses' ),
        'new_item_name'     => __( 'Nuevo Nombre de Categoría', 'mxwm-courses' ),
        'menu_name'         => __( 'Categorías', 'mxwm-courses' ),
    );
    $cat_args = array(
        'hierarchical'      => true,
        'labels'            => $cat_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'course-cat' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'mxwm_course_category', array( 'mxwm_course' ), $cat_args );

    // Taxonomy: mxwm_course_difficulty
    $diff_labels = array(
        'name'              => _x( 'Dificultad', 'taxonomy general name', 'mxwm-courses' ),
        'singular_name'     => _x( 'Dificultad', 'taxonomy singular name', 'mxwm-courses' ),
        'search_items'      => __( 'Buscar Dificultad', 'mxwm-courses' ),
        'all_items'         => __( 'Dificultades', 'mxwm-courses' ),
        'edit_item'         => __( 'Editar Dificultad', 'mxwm-courses' ),
        'update_item'       => __( 'Actualizar Dificultad', 'mxwm-courses' ),
        'add_new_item'      => __( 'Añadir Nueva Dificultad', 'mxwm-courses' ),
        'new_item_name'     => __( 'Nueva Dificultad', 'mxwm-courses' ),
        'menu_name'         => __( 'Dificultad', 'mxwm-courses' ),
    );
    $diff_args = array(
        'hierarchical'      => false, // Estilo etiquetas (chips en lugar de checkbox)
        'labels'            => $diff_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'course-difficulty' ),
        'show_in_rest'      => true, // Accesible por REST API
    );
    register_taxonomy( 'mxwm_course_difficulty', array( 'mxwm_course' ), $diff_args );
}
add_action( 'init', 'mxwm_courses_register_taxonomies', 0 );
