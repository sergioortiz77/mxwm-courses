<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mxwm_courses_register_acf_fields() {
    if( function_exists('acf_add_local_field_group') ):

        // Grupo: Curso - Configuración
        acf_add_local_field_group(array(
            'key' => 'group_mxwm_course_config',
            'title' => 'Configuración del Curso',
            'fields' => array(
                array(
                    'key' => 'field_course_price',
                    'label' => 'Precio',
                    'name' => 'price',
                    'type' => 'number',
                    'instructions' => '0 para gratis',
                    'required' => 1,
                    'default_value' => 0,
                    'min' => 0,
                ),
                array(
                    'key' => 'field_course_currency',
                    'label' => 'Moneda',
                    'name' => 'currency',
                    'type' => 'select',
                    'choices' => array(
                        'MXN' => 'Pesos Mexicanos (MXN)',
                        'USD' => 'Dólares (USD)',
                    ),
                    'default_value' => 'MXN',
                ),
                array(
                    'key' => 'field_course_short_desc',
                    'label' => 'Descripción Corta',
                    'name' => 'short_description',
                    'type' => 'textarea',
                    'rows' => 3,
                ),
                array(
                    'key' => 'field_course_bp_group',
                    'label' => 'ID del Grupo BuddyPress',
                    'name' => 'bp_group_id',
                    'type' => 'number',
                    'instructions' => 'Asignado automáticamente si la comunidad está habilitada.',
                ),
                array(
                    'key' => 'field_course_bp_forum',
                    'label' => 'ID del Foro bbPress',
                    'name' => 'bp_forum_id',
                    'type' => 'number',
                ),
                array(
                    'key' => 'field_course_commission',
                    'label' => 'Comisión de Plataforma (%)',
                    'name' => 'commission_rate',
                    'type' => 'number',
                    'instructions' => 'Visible y editable solo por administradores. Default: 25.',
                    'default_value' => 25,
                    'min' => 0,
                    'max' => 100,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mxwm_course',
                    ),
                ),
            ),
            'position' => 'normal',
            'style' => 'default',
        ));

        // Grupo: Módulo - Estructura
        acf_add_local_field_group(array(
            'key' => 'group_mxwm_module_config',
            'title' => 'Configuración del Módulo',
            'fields' => array(
                array(
                    'key' => 'field_module_course_id',
                    'label' => 'Curso al que pertenece',
                    'name' => 'course_id',
                    'type' => 'post_object',
                    'post_type' => array('mxwm_course'),
                    'return_format' => 'id',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_module_order',
                    'label' => 'Orden',
                    'name' => 'module_order',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 1,
                ),
                array(
                    'key' => 'field_module_is_preview',
                    'label' => '¿Es Preview Público?',
                    'name' => 'is_preview',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mxwm_module',
                    ),
                ),
            ),
        ));

        // Grupo: Lección - Contenido
        acf_add_local_field_group(array(
            'key' => 'group_mxwm_lesson_config',
            'title' => 'Contenido de la Lección',
            'fields' => array(
                array(
                    'key' => 'field_lesson_course_id',
                    'label' => 'Curso al que pertenece',
                    'name' => 'course_id',
                    'type' => 'post_object',
                    'post_type' => array('mxwm_course'),
                    'return_format' => 'id',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_lesson_module_id',
                    'label' => 'Módulo al que pertenece',
                    'name' => 'module_id',
                    'type' => 'post_object',
                    'post_type' => array('mxwm_module'),
                    'return_format' => 'id',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_lesson_order',
                    'label' => 'Orden en el módulo',
                    'name' => 'lesson_order',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 1,
                ),
                array(
                    'key' => 'field_lesson_video_url',
                    'label' => 'URL del Video',
                    'name' => 'video_url',
                    'type' => 'url',
                ),
                array(
                    'key' => 'field_lesson_video_provider',
                    'label' => 'Proveedor de Video',
                    'name' => 'video_provider',
                    'type' => 'select',
                    'choices' => array(
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'bunny' => 'Bunny CDN',
                        'self' => 'Self-hosted (MP4)',
                    ),
                    'default_value' => 'youtube',
                ),
                array(
                    'key' => 'field_lesson_duration',
                    'label' => 'Duración (minutos)',
                    'name' => 'duration_minutes',
                    'type' => 'text',
                ),
                array(
                    'key' => 'field_lesson_is_preview',
                    'label' => '¿Es gratuita (Preview)?',
                    'name' => 'is_preview',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 0,
                ),
                array(
                    'key' => 'field_lesson_is_downloadable',
                    'label' => '¿Permitir descarga offline?',
                    'name' => 'is_downloadable',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mxwm_lesson',
                    ),
                ),
            ),
        ));

    endif;
}
add_action('acf/init', 'mxwm_courses_register_acf_fields');
