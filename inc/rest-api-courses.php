<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase que maneja toda la lógica REST API del módulo de Cursos
 */
class MXWM_Courses_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'mxwm/v1';

        /* -------------------------------------------------------------
         * RUTAS PÚBLICAS (Catálogo y Detalle)
         * ------------------------------------------------------------- */
         
        // Lista paginada de todos los cursos
        register_rest_route( $namespace, '/courses', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_courses' ),
            'permission_callback' => '__return_true', // Público
            'args'                => array(
                'page' => array(
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page' => array(
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Detalle de un curso
        register_rest_route( $namespace, '/courses/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_course_detail' ),
            'permission_callback' => '__return_true', // Público
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric( $param );
                    }
                ),
            ),
        ) );

        // Obtener la retícula del curso (Módulos y lecciones 'preview')
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/curriculum', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_course_curriculum' ),
            'permission_callback' => '__return_true', // Público
        ) );

        /* -------------------------------------------------------------
         * RUTAS PROTEGIDAS (Instructores - Dashboard Web o App)
         * ------------------------------------------------------------- */

        // Mis Cursos Impartidos (Teaching Courses)
        register_rest_route( $namespace, '/courses/teaching', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_teaching_courses' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Mis Cursos Inscritos (Enrolled Courses)
        register_rest_route( $namespace, '/courses/enrolled', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_enrolled_courses' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // Crear Curso
        register_rest_route( $namespace, '/courses', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_course' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );
        
        // Crear Módulo
        register_rest_route( $namespace, '/modules', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_module' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );
        
        // Crear Lección
        register_rest_route( $namespace, '/lessons', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_lesson' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Editar Curso
        register_rest_route( $namespace, '/courses/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_course' ),
                'permission_callback' => array( $this, 'check_instructor_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_course' ),
                'permission_callback' => array( $this, 'check_instructor_permission' ),
            ),
        ) );

        // Editar Módulo
        register_rest_route( $namespace, '/modules/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_module' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Editar Lección
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_lesson' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Recursos de lección (POST subir, DELETE borrar)
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/resources', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_lesson_resources' ),
                'permission_callback' => array( $this, 'check_student_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_lesson_resource' ),
                'permission_callback' => array( $this, 'check_instructor_permission' ),
            ),
        ) );

        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/resources/(?P<rid>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_lesson_resource' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Dashboard del instructor
        register_rest_route( $namespace, '/instructor/dashboard', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_instructor_dashboard' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        // Perfil público del instructor
        register_rest_route( $namespace, '/instructors/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_instructor_profile' ),
            'permission_callback' => '__return_true',
        ) );

        /* -------------------------------------------------------------
         * RUTAS PÚBLICAS — SHORTS (Feed del Marketplace)
         * ------------------------------------------------------------- */

        // Feed público de shorts (solo is_public=1)
        register_rest_route( $namespace, '/shorts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_public_shorts' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
                'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
            ),
        ) );

        /* -------------------------------------------------------------
         * RUTAS PROTEGIDAS — SHORTS (Instructor CRUD)
         * ------------------------------------------------------------- */

        // Shorts de una lección (GET lista, POST crear)
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/shorts', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_lesson_shorts' ),
                'permission_callback' => array( $this, 'check_student_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_lesson_short' ),
                'permission_callback' => array( $this, 'check_instructor_permission' ),
            ),
        ) );

        // Eliminar un short específico
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/shorts/(?P<sid>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_lesson_short' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        /* -------------------------------------------------------------
         * RUTAS ESTUDIANTE (requiere JWT)
         * ------------------------------------------------------------- */

        // Detalle completo de lección (verifica enrollment)
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_lesson_detail' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // Inscribirse a un curso
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/enroll', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'enroll_course' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // Progreso del curso
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/progress', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_progress' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // Actualizar progreso de lección — acepta POST y PATCH
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/progress', array(
            'methods'             => 'POST, PATCH',
            'callback'            => array( $this, 'update_progress' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // WP-0: Marcar lección como completada (app llama POST /lessons/{id}/complete)
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/complete', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'complete_lesson' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // WP-0: Actualizar watch-time (app llama POST /lessons/{id}/watch-time)
        register_rest_route( $namespace, '/lessons/(?P<id>\d+)/watch-time', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'update_watch_time' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        // WP-0: Alias GET /courses/my → mismo handler que /courses/enrolled (fix URL mismatch con app)
        register_rest_route( $namespace, '/courses/my', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_enrolled_courses' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        /* -------------------------------------------------------------
         * RESEÑAS DEL CURSO
         * ------------------------------------------------------------- */
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/reviews', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_course_reviews' ),
                'permission_callback' => '__return_true', // Público
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_course_review' ),
                'permission_callback' => array( $this, 'check_student_permission' ),
            ),
        ) );

        /* -------------------------------------------------------------
         * DISCUSIONES DEL CURSO (foro bbPress vinculado)
         * ------------------------------------------------------------- */
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/discussions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_course_discussions' ),
            'permission_callback' => '__return_true',
        ) );

        /* -------------------------------------------------------------
         * QUALITY CHECK DEL CURSO
         * ------------------------------------------------------------- */
        register_rest_route( $namespace, '/courses/(?P<id>\d+)/quality-check', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_quality_check' ),
            'permission_callback' => array( $this, 'check_instructor_permission' ),
        ) );

        /* -------------------------------------------------------------
         * GAMIFICACIÓN DEL USUARIO
         * ------------------------------------------------------------- */
        register_rest_route( $namespace, '/me/gamification', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_gamification' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        /* -------------------------------------------------------------
         * REGISTRO DE DEVICE TOKENS (Push Notifications)
         * ------------------------------------------------------------- */
        register_rest_route( $namespace, '/devices/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'register_device_token' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );

        register_rest_route( $namespace, '/devices/unregister', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'unregister_device_token' ),
            'permission_callback' => array( $this, 'check_student_permission' ),
        ) );
    }

    /**
     * Valida permisos para operaciones sensibles
     */
    public function check_instructor_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 
                'rest_forbidden', 
                esc_html__( 'Sesión requerida. Envía un token JWT o cookie.', 'mxwm-courses' ), 
                array( 'status' => 401 ) 
            );
        }
        // Nota: Al futuro verificaremos explícitamente el role "mxwm_instructor"
        return true; 
    }

    /**
     * Valida que el usuario esté autenticado (cualquier rol)
     */
    public function check_student_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_forbidden', 'Sesión requerida.', array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * LÓGICA DE RUTAS 
     */

    public function get_courses( \WP_REST_Request $request ) {
        global $wpdb;

        $paged      = max( 1, (int) $request->get_param('page') );
        $per_page   = max( 1, min( 50, (int) ( $request->get_param('per_page') ?: 10 ) ) );
        $search     = sanitize_text_field( $request->get_param('search') ?: '' );
        $category   = sanitize_text_field( $request->get_param('category') ?: '' );
        $difficulty = sanitize_text_field( $request->get_param('difficulty') ?: '' );
        $orderby    = sanitize_text_field( $request->get_param('orderby') ?: 'date' );
        $order      = strtoupper( sanitize_text_field( $request->get_param('order') ?: 'DESC' ) );
        $offset     = ( $paged - 1 ) * $per_page;

        $allowed_orderby = array( 'date' => 'p.post_date', 'title' => 'p.post_title', 'rand' => 'RAND()' );
        $order_sql       = in_array( $order, array( 'ASC', 'DESC' ) ) ? $order : 'DESC';
        $orderby_sql     = isset( $allowed_orderby[ $orderby ] ) ? $allowed_orderby[ $orderby ] : 'p.post_date';

        // ── Base WHERE: solo publicados ──────────────────────────────
        $where  = "p.post_type = 'mxwm_course' AND p.post_status = 'publish'";
        $params = array();

        if ( ! empty( $search ) ) {
            $where   .= " AND (p.post_title LIKE %s OR p.post_excerpt LIKE %s)";
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        if ( ! empty( $difficulty ) ) {
            $where   .= " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_mxwm_difficulty' AND pm.meta_value = %s)";
            $params[] = $difficulty;
        }

        // ── JOIN de taxonomía para categoría ────────────────────────
        $join = '';
        if ( ! empty( $category ) ) {
            $join     = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'course_category'
                         INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id";
            $where   .= " AND t.name = %s";
            $params[] = $category;
        }

        // ── Count total ─────────────────────────────────────────────
        $count_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p $join WHERE $where";
        $total     = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

        // ── Paged query ─────────────────────────────────────────────
        $params[] = $per_page;
        $params[] = $offset;
        $ids_sql   = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p $join WHERE $where ORDER BY $orderby_sql $order_sql LIMIT %d OFFSET %d";
        $post_ids  = $wpdb->get_col( $wpdb->prepare( $ids_sql, $params ) );

        $courses = array();
        foreach ( $post_ids as $id ) {
            $post = get_post( (int) $id );
            if ( $post && $post->post_status === 'publish' ) {
                $courses[] = $this->prepare_course_data( $post );
            }
        }

        return rest_ensure_response( array(
            'courses' => $courses,
            'total'   => $total,
            'pages'   => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
            'page'    => $paged,
        ) );
    }

    public function get_course_detail( \WP_REST_Request $request ) {
        $id = $request->get_param('id');
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'mxwm_course' ) {
            return new \WP_Error( 'not_found', 'Curso no encontrado', array( 'status' => 404 ) );
        }

        $course_data = $this->prepare_course_data( $post );
        
        // Agregar módulos y lecciones
        $request->set_param('id', $post->ID);
        $curriculum_response = $this->get_course_curriculum( $request );
        if ( ! is_wp_error( $curriculum_response ) ) {
            $course_data['modules'] = $curriculum_response->get_data();
        }

        return rest_ensure_response( $course_data );
    }

    public function get_course_curriculum( \WP_REST_Request $request ) {
        $course_id = $request->get_param('id');
        
        $modules = get_posts( array(
            'post_type'      => 'mxwm_module',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'module_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'   => 'course_id',
                    'value' => $course_id,
                )
            )
        ) );

        $curriculum = array();

        foreach ( $modules as $mod ) {
            $lessons = get_posts( array(
                'post_type'      => 'mxwm_lesson',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => 'lesson_order',
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'   => 'module_id',
                        'value' => $mod->ID,
                    )
                )
            ) );

            $formatted_lessons = array();
            foreach ( $lessons as $les ) {
                $is_preview = get_field('is_preview', $les->ID);

                // Fetch lesson resources directly
                global $wpdb;
                $table = $wpdb->prefix . 'mxwm_lesson_resources';
                $rows  = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM $table WHERE lesson_id = %d ORDER BY resource_order ASC", $les->ID
                ) );
                $resources = array();
                foreach ( $rows as $r ) {
                    $resources[] = array(
                        'id'         => (int) $r->id,
                        'type'       => $r->type,
                        'title'      => $r->title,
                        'url'        => $r->attachment_id ? wp_get_attachment_url( $r->attachment_id ) : $r->url,
                        'file_size'  => (int) $r->file_size_bytes,
                        'mime_type'  => $r->mime_type
                    );
                }

                $formatted_lessons[] = array(
                    'id'               => $les->ID,
                    'title'            => $les->post_title,
                    'order'            => (int) get_field('lesson_order', $les->ID),
                    'duration_minutes' => (int) get_field('duration_minutes', $les->ID),
                    'is_preview'       => $is_preview,
                    'resources'        => $resources,
                    // Omite la URL y el Provider deliberadamente aquí por seguridad de copyright
                );
            }

            $curriculum[] = array(
                'id'       => $mod->ID,
                'title'    => $mod->post_title,
                'order'    => (int) get_field('module_order', $mod->ID),
                'lessons'  => $formatted_lessons
            );
        }

        return rest_ensure_response( $curriculum );
    }

    /**
     * ENDPOINTS DE CREACIÓN (Para forms desde web o app)
     */
    public function create_course( \WP_REST_Request $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $price = (float) $request->get_param( 'price' );
        $short_desc = sanitize_textarea_field( $request->get_param( 'short_description' ) );
        $desc = wp_kses_post( $request->get_param( 'description' ) );
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $difficulty = sanitize_text_field( $request->get_param( 'difficulty' ) );
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        
        $post_status = ($status === 'pending_review') ? 'pending' : 'draft';

        if ( empty( $title ) ) {
            return new \WP_Error( 'missing_title', 'El título es obligatorio', array( 'status' => 400 ) );
        }

        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_content' => $desc,
            'post_type'    => 'mxwm_course',
            'post_status'  => $post_status, 
            'post_author'  => get_current_user_id()
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Categoría (Taxonomy)
        if ( !empty($category) ) {
            wp_set_object_terms( $post_id, $category, 'course_category', false );
        }

        // Vincular Metadata en ACF
        update_field( 'price', $price, $post_id );
        update_field( 'currency', sanitize_text_field( $request->get_param( 'currency' ) ) ?: 'MXN', $post_id );
        update_field( 'short_description', $short_desc, $post_id );
        update_field( 'difficulty', $difficulty, $post_id );
        update_field( 'commission_rate', 25, $post_id ); // Base por defecto

        // Crear módulos y lecciones si existen en el payload
        $modules = $request->get_param( 'modules' );
        if ( is_array($modules) ) {
            foreach ( $modules as $m_index => $mod ) {
                $mod_id = wp_insert_post( array(
                    'post_title'   => sanitize_text_field( $mod['title'] ),
                    'post_type'    => 'mxwm_module',
                    'post_status'  => 'publish',
                    'post_author'  => get_current_user_id()
                ) );
                if ( !is_wp_error($mod_id) ) {
                    update_field( 'course_id', $post_id, $mod_id );
                    update_field( 'module_order', $m_index + 1, $mod_id );

                    if ( isset($mod['lessons']) && is_array($mod['lessons']) ) {
                        foreach ( $mod['lessons'] as $l_index => $les ) {
                            $les_id = wp_insert_post( array(
                                'post_title'   => sanitize_text_field( $les['title'] ),
                                'post_type'    => 'mxwm_lesson',
                                'post_status'  => 'publish',
                                'post_author'  => get_current_user_id()
                            ) );
                            if ( !is_wp_error($les_id) ) {
                                update_field( 'course_id', $post_id, $les_id );
                                update_field( 'module_id', $mod_id, $les_id );
                                update_field( 'lesson_order', $l_index + 1, $les_id );
                                update_field( 'video_url', esc_url_raw( $les['video_url'] ?? '' ), $les_id );
                                update_field( 'is_preview', !empty($les['is_preview']) ? 1 : 0, $les_id );
                            }
                        }
                    }
                }
            }
        }

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $post_id,
            'status'  => $post_status,
            'message' => 'Curso creado exitosamente en el sistema.'
        ) );
    }

    public function create_module( \WP_REST_Request $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $course_id = (int) $request->get_param( 'course_id' );
        $order = (int) $request->get_param( 'order' );

        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_type'    => 'mxwm_module',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        ) );

        if( ! is_wp_error( $post_id ) ) {
            update_field( 'course_id', $course_id, $post_id );
            update_field( 'module_order', $order ? $order : 1, $post_id );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $post_id ) );
    }

    public function create_lesson( \WP_REST_Request $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $course_id = (int) $request->get_param( 'course_id' );
        $module_id = (int) $request->get_param( 'module_id' );
        $order = (int) $request->get_param( 'order' );
        $video_provider = sanitize_text_field( $request->get_param( 'video_provider' ) );
        $video_url = esc_url_raw( $request->get_param( 'video_url' ) );
        $duration = (int) $request->get_param( 'duration_minutes' );
        
        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_type'    => 'mxwm_lesson',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        ) );

        if( ! is_wp_error( $post_id ) ) {
            update_field( 'course_id', $course_id, $post_id );
            update_field( 'module_id', $module_id, $post_id );
            update_field( 'lesson_order', $order ? $order : 1, $post_id );
            update_field( 'video_provider', $video_provider ? $video_provider : 'vimeo', $post_id );
            update_field( 'video_url', $video_url, $post_id );
            update_field( 'duration_minutes', $duration, $post_id );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $post_id ) );
    }

    /* =============================================================
     * ENDPOINTS DE EDICIÓN (PUT)
     * ============================================================= */

    public function update_course( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'mxwm_course' ) {
            return new \WP_Error( 'not_found', 'Curso no encontrado', array( 'status' => 404 ) );
        }
        if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can('manage_options') ) {
            return new \WP_Error( 'forbidden', 'Solo el propietario puede editar este curso.', array( 'status' => 403 ) );
        }
        $updates = array( 'ID' => $id );
        if ( $request->get_param('title') )  $updates['post_title'] = sanitize_text_field( $request->get_param('title') );
        if ( $request->get_param('content') ) $updates['post_content'] = wp_kses_post( $request->get_param('content') );

        // Cambio de estado del curso
        $new_status = sanitize_text_field( $request->get_param('status') ?: '' );
        if ( ! empty( $new_status ) ) {
            $allowed_statuses = array( 'draft', 'pending', 'publish' );
            $wp_status = $new_status === 'pending_review' ? 'pending' : $new_status;
            if ( in_array( $wp_status, $allowed_statuses ) ) {
                $updates['post_status'] = $wp_status;
            }
        }

        wp_update_post( $updates );

        $acf_fields = array( 'price', 'currency', 'short_description', 'commission_rate', 'bp_group_id', 'bp_forum_id' );
        foreach ( $acf_fields as $f ) {
            if ( $request->get_param( $f ) !== null ) {
                update_field( $f, $request->get_param( $f ), $id );
            }
        }

        // Crear Grupo BuddyPress si se solicitó
        if ( $request->get_param('create_group') && function_exists('groups_create_group') ) {
            $group_id = groups_create_group( array(
                'creator_id'   => get_current_user_id(),
                'name'         => get_the_title( $id ),
                'description'  => get_post_field( 'post_excerpt', $id ),
                'status'       => 'private',
                'enable_forum' => (bool) $request->get_param('create_forum'),
            ) );
            if ( $group_id && ! is_wp_error( $group_id ) ) {
                update_field( 'bp_group_id', $group_id, $id );
            }
        }

        // Crear Foro BuddyPress si se solicitó (sin grupo)
        if ( $request->get_param('create_forum') && ! $request->get_param('create_group') && function_exists('bbp_insert_forum') ) {
            $forum_id = bbp_insert_forum( array(
                'post_title'   => get_the_title( $id ) . ' — Foro',
                'post_author'  => get_current_user_id(),
            ) );
            if ( $forum_id && ! is_wp_error( $forum_id ) ) {
                update_field( 'bp_forum_id', $forum_id, $id );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $id, 'status' => get_post_status( $id ) ) );
    }

    public function update_module( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'mxwm_module' ) {
            return new \WP_Error( 'not_found', 'Módulo no encontrado', array( 'status' => 404 ) );
        }
        if ( $request->get_param('title') ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $request->get_param('title') ) ) );
        }
        if ( $request->get_param('order') !== null )  update_field( 'module_order', (int) $request->get_param('order'), $id );
        if ( $request->get_param('is_preview') !== null ) update_field( 'is_preview', $request->get_param('is_preview'), $id );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function update_lesson( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'mxwm_lesson' ) {
            return new \WP_Error( 'not_found', 'Lección no encontrada', array( 'status' => 404 ) );
        }
        if ( $request->get_param('title') ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $request->get_param('title') ) ) );
        }
        $acf = array('video_url','video_provider','duration_minutes','lesson_order','is_preview','is_downloadable','module_id');
        foreach ( $acf as $f ) {
            if ( $request->get_param( $f ) !== null ) update_field( $f, $request->get_param( $f ), $id );
        }
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    /* =============================================================
     * ENDPOINTS ESTUDIANTE
     * ============================================================= */

    public function get_lesson_detail( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'mxwm_lesson' ) {
            return new \WP_Error( 'not_found', 'Lección no encontrada', array( 'status' => 404 ) );
        }
        $course_id  = (int) get_field( 'course_id', $id );
        $is_preview = get_field( 'is_preview', $id );
        $user_id    = get_current_user_id();

        // Verificar acceso: preview abierto, o inscripción activa
        if ( ! $is_preview && ! mxwm_is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error( 'not_enrolled', 'Debes estar inscrito para ver esta lección.', array( 'status' => 403 ) );
        }

        $progress = mxwm_get_lesson_progress( $user_id, $id );
        // Obtener shorts de la lección
        $shorts_data = $this->fetch_lesson_shorts( $id );

        return rest_ensure_response( array(
            'id'               => $id,
            'title'            => $post->post_title,
            'content'          => apply_filters( 'the_content', $post->post_content ),
            'course_id'        => $course_id,
            'module_id'        => (int) get_field( 'module_id', $id ),
            'video_url'        => get_field( 'video_url', $id ),
            'video_provider'   => get_field( 'video_provider', $id ),
            'duration_minutes' => (int) get_field( 'duration_minutes', $id ),
            'is_preview'       => $is_preview,
            'is_downloadable'  => get_field( 'is_downloadable', $id ),
            'shorts'           => $shorts_data,
            'progress'         => $progress ? array(
                'percentage'             => (int) $progress->percentage,
                'video_position_seconds' => (int) $progress->video_position_seconds,
                'completed_at'           => $progress->completed_at,
            ) : null,
        ) );
    }

    public function get_lesson_resources( \WP_REST_Request $request ) {
        global $wpdb;
        $lesson_id = (int) $request->get_param('id');
        $course_id = (int) get_field( 'course_id', $lesson_id );
        $user_id   = get_current_user_id();

        if ( ! mxwm_is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error( 'not_enrolled', 'Inscripción requerida.', array( 'status' => 403 ) );
        }

        $table = $wpdb->prefix . 'mxwm_lesson_resources';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE lesson_id = %d ORDER BY resource_order ASC", $lesson_id
        ) );

        $resources = array();
        foreach ( $rows as $r ) {
            $resources[] = array(
                'id'         => (int) $r->id,
                'type'       => $r->type,
                'title'      => $r->title,
                'url'        => $r->attachment_id ? wp_get_attachment_url( $r->attachment_id ) : $r->url,
                'file_size'  => (int) $r->file_size_bytes,
                'mime_type'  => $r->mime_type,
            );
        }
        return rest_ensure_response( $resources );
    }

    public function upload_lesson_resource( \WP_REST_Request $request ) {
        global $wpdb;
        $lesson_id = (int) $request->get_param('id');
        $type      = sanitize_text_field( $request->get_param('type') );
        $title     = sanitize_text_field( $request->get_param('title') );
        $url       = esc_url_raw( $request->get_param('url') ?: '' );

        if ( empty($type) ) {
            return new \WP_Error( 'missing_type', 'El campo type es requerido.', array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'mxwm_lesson_resources';
        $limits = array( 'pdf' => 3, 'podcast' => 3, 'infographic' => 5, 'link' => 10, 'external_link' => 10 );
        if ( isset( $limits[ $type ] ) ) {
            $current = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE lesson_id = %d AND type = %s", $lesson_id, $type
            ) );
            if ( $current >= $limits[ $type ] ) {
                return new \WP_Error( 'limit_reached', "Límite de {$limits[$type]} recursos tipo '$type' alcanzado.", array( 'status' => 422 ) );
            }
        }

        $attachment_id = 0;
        $file_size     = 0;
        $mime_type     = '';
        $final_url     = $url ?: null;

        // ── Procesar archivo binario si viene en $_FILES ──
        if ( isset( $_FILES['file'] ) && ! empty( $_FILES['file']['tmp_name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $overrides = array( 'test_form' => false );
            $upload    = wp_handle_upload( $_FILES['file'], $overrides );

            if ( isset( $upload['error'] ) ) {
                return new \WP_Error( 'upload_error', $upload['error'], array( 'status' => 500 ) );
            }

            $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );
            $attachment  = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => sanitize_file_name( $title ?: basename( $upload['file'] ) ),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => get_current_user_id(),
            );
            $attachment_id = wp_insert_attachment( $attachment, $upload['file'], 0 );
            if ( ! is_wp_error( $attachment_id ) ) {
                wp_update_attachment_metadata(
                    $attachment_id,
                    wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
                );
            } else {
                $attachment_id = 0;
            }
            $file_size = file_exists( $upload['file'] ) ? filesize( $upload['file'] ) : 0;
            $mime_type = $wp_filetype['type'];
            $final_url = $upload['url'];
        }

        $wpdb->insert( $table, array(
            'lesson_id'       => $lesson_id,
            'type'            => $type,
            'title'           => $title ?: 'Recurso',
            'attachment_id'   => $attachment_id ?: null,
            'url'             => $final_url,
            'file_size_bytes' => $file_size,
            'mime_type'       => $mime_type,
            'created_at'      => current_time('mysql'),
        ) );

        $new_id = $wpdb->insert_id;
        $resolved_url = $final_url ?: ( $attachment_id ? wp_get_attachment_url( $attachment_id ) : null );

        return rest_ensure_response( array(
            'id'        => $new_id,
            'type'      => $type,
            'title'     => $title ?: 'Recurso',
            'url'       => $resolved_url,
            'file_size' => $file_size,
            'mime_type' => $mime_type,
        ) );
    }

    public function delete_course( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'mxwm_course' ) {
            return new \WP_Error( 'not_found', 'Curso no encontrado.', array( 'status' => 404 ) );
        }
        if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can('manage_options') ) {
            return new \WP_Error( 'forbidden', 'Solo el propietario puede eliminar este curso.', array( 'status' => 403 ) );
        }
        if ( $post->post_status === 'publish' ) {
            return new \WP_Error( 'published', 'Los cursos publicados no se pueden eliminar desde la app. Usa el panel web.', array( 'status' => 403 ) );
        }
        $result = wp_delete_post( $id, true );
        if ( ! $result ) {
            return new \WP_Error( 'delete_failed', 'No se pudo eliminar el borrador.', array( 'status' => 500 ) );
        }
        return rest_ensure_response( array( 'success' => true, 'deleted_id' => $id ) );
    }

    public function delete_lesson_resource( \WP_REST_Request $request ) {
        global $wpdb;
        $rid   = (int) $request->get_param('rid');
        $table = $wpdb->prefix . 'mxwm_lesson_resources';
        $deleted = $wpdb->delete( $table, array( 'id' => $rid ) );
        if ( ! $deleted ) {
            return new \WP_Error( 'not_found', 'Recurso no encontrado.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function enroll_course( \WP_REST_Request $request ) {
        $course_id      = (int) $request->get_param('id');
        $user_id        = get_current_user_id();
        $payment_method = sanitize_text_field( $request->get_param('payment_method') ) ?: 'free';
        $transaction_id = sanitize_text_field( $request->get_param('transaction_id') );

        $post = get_post( $course_id );
        if ( ! $post || $post->post_type !== 'mxwm_course' ) {
            return new \WP_Error( 'not_found', 'Curso no encontrado', array( 'status' => 404 ) );
        }

        // Cursos de pago requieren transaction_id (excepto gratis)
        $price = (float) get_field( 'price', $course_id );
        if ( $price > 0 && $payment_method === 'free' ) {
            return new \WP_Error( 'payment_required', 'Este curso requiere pago.', array( 'status' => 402 ) );
        }

        $result = mxwm_enroll_user( $user_id, $course_id, $payment_method, $transaction_id );
        if ( is_wp_error( $result ) ) return $result;

        return rest_ensure_response( array( 'success' => true, 'enrollment_id' => $result ) );
    }

    public function get_enrolled_courses( \WP_REST_Request $request ) {
        $user_id     = get_current_user_id();
        $enrollments = mxwm_get_user_enrollments( $user_id );
        $courses     = array();

        foreach ( $enrollments as $enrollment ) {
            $post = get_post( (int) $enrollment->course_id );
            if ( ! $post || $post->post_status !== 'publish' ) continue;

            $course_data               = $this->prepare_course_data( $post, $user_id );
            $course_data['enrolled_at'] = $enrollment->enrolled_at;
            $courses[]                 = $course_data;
        }

        return rest_ensure_response( array(
            'courses' => $courses,
            'total'   => count( $courses ),
        ) );
    }

    public function get_progress( \WP_REST_Request $request ) {
        $course_id = (int) $request->get_param('id');
        $user_id   = get_current_user_id();
        if ( ! mxwm_is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error( 'not_enrolled', 'No estás inscrito en este curso.', array( 'status' => 403 ) );
        }
        return rest_ensure_response( mxwm_get_course_progress( $user_id, $course_id ) );
    }

    public function update_progress( \WP_REST_Request $request ) {
        $lesson_id = (int) $request->get_param('id');
        $user_id   = get_current_user_id();
        $course_id = (int) get_field( 'course_id', $lesson_id );

        if ( ! mxwm_is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error( 'not_enrolled', 'No estás inscrito.', array( 'status' => 403 ) );
        }

        $progress_id = mxwm_update_lesson_progress( $user_id, $lesson_id, $course_id, array(
            'percentage'             => $request->get_param('percentage'),
            'video_position_seconds' => $request->get_param('video_position_seconds'),
        ) );

        return rest_ensure_response( array(
            'success'     => true,
            'progress_id' => $progress_id,
            'course'      => mxwm_get_course_progress( $user_id, $course_id ),
        ) );
    }

    /* =============================================================
     * INSTRUCTOR: PERFIL Y DASHBOARD
     * ============================================================= */

    public function get_instructor_profile( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $user = get_user_by( 'ID', $id );
        if ( ! $user ) {
            return new \WP_Error( 'not_found', 'Instructor no encontrado.', array( 'status' => 404 ) );
        }
        $courses = get_posts( array(
            'post_type' => 'mxwm_course', 'post_status' => 'publish',
            'author' => $id, 'posts_per_page' => -1, 'fields' => 'ids',
        ) );
        $total_students = 0;
        foreach ( $courses as $cid ) {
            $total_students += mxwm_get_enrollment_count( $cid );
        }
        return rest_ensure_response( array(
            'id'             => $id,
            'name'           => $user->display_name,
            'avatar'         => get_avatar_url( $id, array( 'size' => 256 ) ),
            'bio'            => get_the_author_meta( 'description', $id ),
            'total_courses'  => count( $courses ),
            'total_students' => $total_students,
        ) );
    }

    public function get_instructor_dashboard( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $courses = get_posts( array(
            'post_type' => 'mxwm_course', 'post_status' => array('publish', 'draft', 'pending'),
            'author' => $user_id, 'posts_per_page' => -1,
        ) );

        $enrollments_table = $wpdb->prefix . 'mxwm_enrollments';
        $stats = array();
        $total_students = 0;
        foreach ( $courses as $c ) {
            $count = mxwm_get_enrollment_count( $c->ID );
            $total_students += $count;
            $stats[] = array(
                'course_id'    => $c->ID,
                'title'        => $c->post_title,
                'status'       => $c->post_status,
                'students'     => $count,
                'price'        => (float) get_field( 'price', $c->ID ),
            );
        }
        return rest_ensure_response( array(
            'total_courses'  => count( $courses ),
            'total_students' => $total_students,
            'courses'        => $stats,
        ) );
    }

    public function get_teaching_courses( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'post_type'      => 'mxwm_course',
            'post_status'    => array('publish', 'draft', 'pending'),
            'author'         => $user_id,
            'posts_per_page' => -1,
            'orderby'        => 'post_modified',
            'order'          => 'DESC'
        );
        $query = new \WP_Query( $args );
        $courses = array();
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $courses[] = $this->prepare_course_data( $post );
            }
        }
        return rest_ensure_response( $courses );
    }

    /* =============================================================
     * RESEÑAS DEL CURSO
     * ============================================================= */

    /**
     * GET /mxwm/v1/courses/{id}/reviews
     * Lista paginada de reseñas de un curso (público)
     */
    public function get_course_reviews( \WP_REST_Request $request ) {
        global $wpdb;
        $course_id = (int) $request->get_param('id');
        $table     = $wpdb->prefix . 'mxwm_reviews';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email
             FROM $table r
             INNER JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.course_id = %d
             ORDER BY r.created_at DESC",
            $course_id
        ) );

        $reviews    = array();
        $rating_sum = 0;
        foreach ( $rows as $row ) {
            $rating_sum += (int) $row->rating;
            $reviews[] = array(
                'id'           => (int) $row->id,
                'rating'       => (int) $row->rating,
                'content'      => $row->content,
                'created_at'   => $row->created_at,
                'author'       => array(
                    'id'         => (int) $row->user_id,
                    'name'       => $row->display_name,
                    'avatar_url' => get_avatar_url( (int) $row->user_id, array( 'size' => 96 ) ),
                ),
            );
        }

        $total      = count( $reviews );
        $rating_avg = $total > 0 ? round( $rating_sum / $total, 1 ) : 0;

        // Actualizar el meta del curso para que prepare_course_data lo sirva rápido
        update_post_meta( $course_id, '_mxwm_rating_avg', $rating_avg );

        return rest_ensure_response( array(
            'reviews'    => $reviews,
            'total'      => $total,
            'rating_avg' => $rating_avg,
        ) );
    }

    /**
     * POST /mxwm/v1/courses/{id}/reviews
     * El usuario crea o actualiza su reseña.
     * Reglas: solo con ≥50% de progreso en el curso.
     */
    public function create_course_review( \WP_REST_Request $request ) {
        global $wpdb;
        $course_id = (int) $request->get_param('id');
        $user_id   = get_current_user_id();
        $rating    = min( 5, max( 1, (int) $request->get_param('rating') ) );
        $content   = sanitize_textarea_field( $request->get_param('content') ?: '' );
        $table     = $wpdb->prefix . 'mxwm_reviews';

        // Verificar inscripción
        if ( ! mxwm_is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error( 'not_enrolled', 'Debes estar inscrito para dejar una reseña.', array( 'status' => 403 ) );
        }

        // Verificar ≥50% de progreso
        $progress = mxwm_get_course_progress( $user_id, $course_id );
        if ( $progress['percentage'] < 50 ) {
            return new \WP_Error(
                'insufficient_progress',
                'Necesitas completar al menos el 50% del curso para dejar una reseña. Tu progreso actual: ' . $progress['percentage'] . '%',
                array( 'status' => 403 )
            );
        }

        // Insertar o actualizar (UNIQUE KEY user_course lo garantiza)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND course_id = %d", $user_id, $course_id
        ) );

        if ( $existing ) {
            $wpdb->update( $table,
                array( 'rating' => $rating, 'content' => $content, 'updated_at' => current_time('mysql') ),
                array( 'id' => $existing )
            );
            $review_id = $existing;
            $message   = 'Reseña actualizada correctamente.';
        } else {
            $wpdb->insert( $table, array(
                'user_id'    => $user_id,
                'course_id'  => $course_id,
                'rating'     => $rating,
                'content'    => $content,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ) );
            $review_id = $wpdb->insert_id;
            $message   = 'Reseña publicada correctamente.';
        }

        // Recalcular y guardar rating promedio
        $avg = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM $table WHERE course_id = %d", $course_id
        ) );
        update_post_meta( $course_id, '_mxwm_rating_avg', round( $avg, 1 ) );

        return rest_ensure_response( array(
            'success'    => true,
            'review_id'  => (int) $review_id,
            'rating_avg' => round( $avg, 1 ),
            'message'    => $message,
        ) );
    }

    /* =============================================================
     * DISCUSIONES DEL CURSO
     * ============================================================= */

    /**
     * GET /mxwm/v1/courses/{id}/discussions
     * Devuelve los topics del foro bbPress vinculado al curso (bp_forum_id).
     */
    public function get_course_discussions( \WP_REST_Request $request ) {
        $course_id = (int) $request->get_param('id');
        $forum_id  = (int) get_field( 'bp_forum_id', $course_id );

        if ( ! $forum_id ) {
            return rest_ensure_response( array(
                'forum_id' => null,
                'topics'   => array(),
                'total'    => 0,
                'message'  => 'Este curso aún no tiene un foro vinculado.',
            ) );
        }

        // Obtener topics del foro (bbPress)
        $topics = get_posts( array(
            'post_type'      => 'topic',
            'post_status'    => 'publish',
            'post_parent'    => $forum_id,
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $formatted = array();
        foreach ( $topics as $t ) {
            $author_id   = (int) $t->post_author;
            $reply_count = function_exists('bbp_get_topic_reply_count') ? (int) bbp_get_topic_reply_count( $t->ID ) : 0;
            $formatted[] = array(
                'id'           => $t->ID,
                'title'        => $t->post_title,
                'excerpt'      => wp_trim_words( $t->post_content, 20 ),
                'date'         => $t->post_date,
                'reply_count'  => $reply_count,
                'author'       => array(
                    'id'         => $author_id,
                    'name'       => get_the_author_meta( 'display_name', $author_id ),
                    'avatar_url' => get_avatar_url( $author_id, array( 'size' => 64 ) ),
                ),
            );
        }

        return rest_ensure_response( array(
            'forum_id' => $forum_id,
            'topics'   => $formatted,
            'total'    => count( $formatted ),
        ) );
    }

    /* =============================================================
     * QUALITY CHECK DEL CURSO
     * ============================================================= */

    /**
     * GET /mxwm/v1/courses/{id}/quality-check
     * Verifica server-side si el curso cumple los 6 criterios de publicación.
     */
    public function get_quality_check( \WP_REST_Request $request ) {
        $course_id = (int) $request->get_param('id');
        $post      = get_post( $course_id );

        if ( ! $post || $post->post_type !== 'mxwm_course' ) {
            return new \WP_Error( 'not_found', 'Curso no encontrado.', array( 'status' => 404 ) );
        }
        if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can('manage_options') ) {
            return new \WP_Error( 'forbidden', 'No tienes permiso para ver este quality check.', array( 'status' => 403 ) );
        }

        // Obtener todas las lecciones del curso
        $lessons = get_posts( array(
            'post_type'      => 'mxwm_lesson',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array( 'key' => 'course_id', 'value' => $course_id ),
            ),
        ) );
        $lesson_count = count( $lessons );

        // Contar lecciones con video
        $lessons_with_video = 0;
        foreach ( $lessons as $lid ) {
            if ( get_field( 'video_url', $lid ) ) $lessons_with_video++;
        }

        // Descripción (post_content o excerpt)
        $description = $post->post_content ?: $post->post_excerpt;
        $desc_length = mb_strlen( strip_tags( $description ) );

        // Campos ACF
        $price     = (float) get_field( 'price', $course_id );
        $thumbnail = get_the_post_thumbnail_url( $course_id, 'large' );
        $currency  = get_field( 'currency', $course_id );

        // Criterios
        $criteria = array(
            array(
                'key'     => 'min_lessons',
                'label'   => 'Mínimo 5 lecciones publicadas',
                'passed'  => $lesson_count >= 5,
                'detail'  => "{$lesson_count} lección(es) publicada(s)",
            ),
            array(
                'key'     => 'title',
                'label'   => 'Título del curso completo',
                'passed'  => mb_strlen( trim( $post->post_title ) ) >= 10,
                'detail'  => mb_strlen( trim( $post->post_title ) ) . ' caracteres',
            ),
            array(
                'key'     => 'description',
                'label'   => 'Descripción de al menos 200 caracteres',
                'passed'  => $desc_length >= 200,
                'detail'  => "{$desc_length} caracteres",
            ),
            array(
                'key'     => 'video',
                'label'   => 'Al menos 1 lección con video',
                'passed'  => $lessons_with_video >= 1,
                'detail'  => "{$lessons_with_video} lección(es) con video",
            ),
            array(
                'key'     => 'price',
                'label'   => 'Precio configurado (o marcado como gratuito)',
                'passed'  => $price >= 0 && ! empty( $currency ),
                'detail'  => $price > 0 ? "{$price} {$currency}" : 'Gratuito',
            ),
            array(
                'key'     => 'thumbnail',
                'label'   => 'Imagen de portada asignada',
                'passed'  => ! empty( $thumbnail ),
                'detail'  => $thumbnail ? 'OK' : 'Falta imagen',
            ),
        );

        $passed_count = count( array_filter( $criteria, fn( $c ) => $c['passed'] ) );
        $all_passed   = $passed_count === count( $criteria );

        return rest_ensure_response( array(
            'course_id'   => $course_id,
            'ready'       => $all_passed,
            'score'       => $passed_count . '/' . count( $criteria ),
            'criteria'    => $criteria,
        ) );
    }

    /* =============================================================
     * ENDPOINTS SHORTS
     * ============================================================= */

    /**
     * Feed público de shorts (solo is_public=1)
     * GET /mxwm/v1/shorts?page=1&per_page=20
     */
    public function get_public_shorts( \WP_REST_Request $request ) {
        global $wpdb;
        $page     = max( 1, (int) $request->get_param('page') );
        $per_page = min( 50, max( 1, (int) $request->get_param('per_page') ) );
        $offset   = ( $page - 1 ) * $per_page;

        $table   = $wpdb->prefix . 'mxwm_lesson_shorts';
        $lessons = $wpdb->posts;

        // Total de shorts públicos
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE is_public = 1 AND video_provider = 'bunny'" );

        // Shorts con datos de lección, curso e instructor
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, 
                    l.post_title AS lesson_title,
                    l.post_author AS instructor_id
             FROM $table s
             LEFT JOIN $lessons l ON s.lesson_id = l.ID
             WHERE s.is_public = 1 AND s.video_provider = 'bunny'
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $shorts = array();
        foreach ( $rows as $row ) {
            // Obtener course_id de la lección
            $course_id    = (int) get_field( 'course_id', $row->lesson_id );
            $course_post  = $course_id ? get_post( $course_id ) : null;
            $instructor_id = (int) $row->instructor_id;

            $shorts[] = array(
                'id'              => (int) $row->id,
                'video_url'       => $row->video_url,
                'video_provider'  => $row->video_provider,
                'bunny_video_id'  => $row->bunny_video_id ?? null,
                'hls_url'         => mxwm_get_bunny_hls_url( $row->bunny_video_id ?? '' ),
                'thumbnail_url'   => mxwm_get_bunny_thumbnail_url( $row->bunny_video_id ?? '' ),
                'duration_seconds'=> (int) $row->duration_seconds,
                'title'           => $row->title,
                'description'     => $row->description,
                'created_at'      => $row->created_at,
                'lesson'          => array(
                    'id'    => (int) $row->lesson_id,
                    'title' => $row->lesson_title,
                ),
                'course'          => $course_post ? array(
                    'id'    => $course_post->ID,
                    'title' => $course_post->post_title,
                ) : null,
                'instructor'      => array(
                    'id'     => $instructor_id,
                    'name'   => get_the_author_meta( 'display_name', $instructor_id ),
                    'avatar' => get_avatar_url( $instructor_id, array( 'size' => 96 ) ),
                ),
            );
        }

        return rest_ensure_response( array(
            'data'  => $shorts,
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
            'page'  => $page,
        ) );
    }

    /**
     * Shorts de una lección específica
     * GET /mxwm/v1/lessons/{id}/shorts
     */
    public function get_lesson_shorts( \WP_REST_Request $request ) {
        $lesson_id = (int) $request->get_param('id');
        return rest_ensure_response( $this->fetch_lesson_shorts( $lesson_id ) );
    }

    /**
     * Crear un short en una lección
     * POST /mxwm/v1/lessons/{id}/shorts
     */
    public function create_lesson_short( \WP_REST_Request $request ) {
        global $wpdb;
        $lesson_id = (int) $request->get_param('id');
        $table     = $wpdb->prefix . 'mxwm_lesson_shorts';
        $max       = defined('MXWM_MAX_SHORTS_PER_LESSON') ? MXWM_MAX_SHORTS_PER_LESSON : 10;

        // Verificar límite
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE lesson_id = %d", $lesson_id
        ) );
        if ( $current >= $max ) {
            return new \WP_Error( 'limit_reached', "Límite de $max shorts por lección alcanzado.", array( 'status' => 422 ) );
        }

        $video_url  = esc_url_raw( $request->get_param('video_url') );
        if ( empty( $video_url ) ) {
            return new \WP_Error( 'missing_url', 'La URL del video es obligatoria.', array( 'status' => 400 ) );
        }

        $provider = sanitize_text_field( $request->get_param('video_provider') ) ?: 'youtube';
        // Auto-detect
        if ( strpos( $video_url, 'vimeo.com' ) !== false ) $provider = 'vimeo';
        elseif ( strpos( $video_url, 'youtu' ) !== false ) $provider = 'youtube';

        $wpdb->insert( $table, array(
            'lesson_id'        => $lesson_id,
            'video_url'        => $video_url,
            'video_provider'   => $provider,
            'duration_seconds' => min( 180, absint( $request->get_param('duration_seconds') ) ),
            'title'            => sanitize_text_field( $request->get_param('title') ) ?: 'Short #' . ( $current + 1 ),
            'description'      => sanitize_textarea_field( $request->get_param('description') ) ?: '',
            'is_public'        => (int) $request->get_param('is_public'),
            'display_order'    => $current + 1,
            'created_at'       => current_time('mysql'),
        ) );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    /**
     * Eliminar un short
     * DELETE /mxwm/v1/lessons/{id}/shorts/{sid}
     */
    public function delete_lesson_short( \WP_REST_Request $request ) {
        global $wpdb;
        $sid   = (int) $request->get_param('sid');
        $table = $wpdb->prefix . 'mxwm_lesson_shorts';
        $deleted = $wpdb->delete( $table, array( 'id' => $sid ) );
        if ( ! $deleted ) {
            return new \WP_Error( 'not_found', 'Short no encontrado.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Helpers Internos
     */

    /**
     * Obtiene los shorts de una lección como array formateado
     */
    private function fetch_lesson_shorts( $lesson_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mxwm_lesson_shorts';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE lesson_id = %d ORDER BY display_order ASC",
            $lesson_id
        ) );

        $shorts = array();
        foreach ( $rows as $r ) {
            $shorts[] = array(
                'id'               => (int) $r->id,
                'video_url'        => $r->video_url,
                'video_provider'   => $r->video_provider,
                'bunny_video_id'   => $r->bunny_video_id ?? null,
                'hls_url'          => $r->video_provider === 'bunny' ? mxwm_get_bunny_hls_url( $r->bunny_video_id ?? '' ) : null,
                'thumbnail_url'    => $r->video_provider === 'bunny' ? mxwm_get_bunny_thumbnail_url( $r->bunny_video_id ?? '' ) : null,
                'duration_seconds' => (int) $r->duration_seconds,
                'title'            => $r->title,
                'description'      => $r->description,
                'is_public'        => (bool) $r->is_public,
                'display_order'    => (int) $r->display_order,
            );
        }
        return $shorts;
    }

    private function prepare_course_data( $post, $user_id = null ) {
        $instructor_id = (int) $post->post_author;
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        // Taxonomías
        $categories  = wp_get_post_terms( $post->ID, 'mxwm_course_category', array( 'fields' => 'names' ) );
        $difficulties = wp_get_post_terms( $post->ID, 'mxwm_course_difficulty', array( 'fields' => 'names' ) );
        $category   = ( ! is_wp_error( $categories ) && ! empty( $categories ) ) ? $categories[0] : '';
        $diff_raw   = ( ! is_wp_error( $difficulties ) && ! empty( $difficulties ) ) ? $difficulties[0] : '';
        $difficulty = $diff_raw ?: ( get_field( 'difficulty', $post->ID ) ?: '' );

        // Enrollment del usuario actual
        $enrollment_data = null;
        if ( $user_id ) {
            $enrollment = mxwm_get_enrollment( $user_id, $post->ID );
            if ( $enrollment ) {
                $progress        = mxwm_get_course_progress( $user_id, $post->ID );
                $completed_ids   = array();
                global $wpdb;
                $prog_table = $wpdb->prefix . 'mxwm_lesson_progress';
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT lesson_id FROM $prog_table WHERE user_id = %d AND course_id = %d AND completed_at IS NOT NULL",
                    $user_id, $post->ID
                ) );
                foreach ( $rows as $r ) $completed_ids[] = (int) $r->lesson_id;

                $enrollment_data = array(
                    'enrolled'          => true,
                    'enrolled_at'       => $enrollment->enrolled_at,
                    'progress_pct'      => $progress['percentage'],
                    'completed_lessons' => $completed_ids,
                );
            }
        }

        // Rating promedio (si existe tabla de reviews, de lo contrario meta)
        $rating_avg = (float) get_post_meta( $post->ID, '_mxwm_rating_avg', true );

        return array(
            'id'                => $post->ID,
            'title'             => $post->post_title,
            'excerpt'           => $post->post_excerpt ?: get_field( 'short_description', $post->ID ),
            'status'            => $post->post_status,
            'date_created'      => $post->post_date,
            'thumbnail'         => get_the_post_thumbnail_url( $post->ID, 'large' ) ?: null,
            'price'             => (float) get_field( 'price', $post->ID ),
            'currency'          => get_field( 'currency', $post->ID ) ?: 'MXN',
            'is_free'           => (float) get_field( 'price', $post->ID ) == 0,
            'short_description' => get_field( 'short_description', $post->ID ),
            'difficulty'        => $difficulty,
            'category'          => $category,
            'rating_avg'        => $rating_avg ?: 0.0,
            'enrollment_count'  => mxwm_get_enrollment_count( $post->ID ),
            'instructor'        => array(
                'id'         => $instructor_id,
                'display_name' => get_the_author_meta( 'display_name', $instructor_id ),
                'avatar_url' => get_avatar_url( $instructor_id, array( 'size' => 128 ) ),
            ),
            'enrollment'        => $enrollment_data,
        );
    }

    /* =============================================================
     * GAMIFICACIÓN DEL USUARIO
     * ============================================================= */

    /**
     * GET /mxwm/v1/me/gamification
     *
     * Devuelve el perfil de gamificación del usuario autenticado:
     * puntos, nivel, badges desbloqueados y posición en ranking.
     *
     * Los puntos se calculan dinámicamente a partir de las acciones
     * reales del usuario (lecciones, cursos, reseñas, posts).
     * Se guarda en usermeta para cachear entre peticiones.
     */
    public function get_my_gamification( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();

        // ── Calcular puntos base ─────────────────────────────────────
        // 10 pts por lección completada
        $completed_lessons = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_lesson_progress
             WHERE user_id = %d AND completed_at IS NOT NULL",
            $user_id
        ) );

        // 100 pts por curso completado (100% progreso)
        $completed_courses = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT e.course_id)
             FROM {$wpdb->prefix}mxwm_enrollments e
             WHERE e.user_id = %d AND e.status = 'active'
             AND (
                SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_lesson_progress lp
                WHERE lp.user_id = %d AND lp.course_id = e.course_id AND lp.completed_at IS NOT NULL
             ) >= (
                SELECT COUNT(*) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'course_id' AND pm.meta_value = e.course_id
                WHERE p.post_type = 'mxwm_lesson' AND p.post_status = 'publish'
             )",
            $user_id, $user_id
        ) );

        // 25 pts por reseña publicada
        $reviews_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_reviews WHERE user_id = %d",
            $user_id
        ) );

        // 5 pts por cursos inscritos
        $enrollments_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mxwm_enrollments WHERE user_id = %d AND status = 'active'",
            $user_id
        ) );

        $total_points = ( $completed_lessons * 10 )
                      + ( $completed_courses * 100 )
                      + ( $reviews_count * 25 )
                      + ( $enrollments_count * 5 );

        // ── Calcular nivel (cada 200 pts = 1 nivel) ──────────────────
        $level      = max( 1, floor( $total_points / 200 ) + 1 );
        $next_level = $level * 200;
        $progress_to_next = $next_level > 0 ? min( 100, round( ( $total_points % 200 ) / 200 * 100 ) ) : 100;

        // ── Badges ───────────────────────────────────────────────────
        $badges = array();
        if ( $completed_lessons >= 1 )  $badges[] = array( 'id' => 'first_lesson',  'label' => 'Primera lección', 'icon' => '📖' );
        if ( $completed_lessons >= 10 ) $badges[] = array( 'id' => 'ten_lessons',   'label' => '10 lecciones',    'icon' => '🎯' );
        if ( $completed_lessons >= 50 ) $badges[] = array( 'id' => 'fifty_lessons', 'label' => '50 lecciones',    'icon' => '🏆' );
        if ( $completed_courses >= 1 )  $badges[] = array( 'id' => 'first_course',  'label' => 'Primer curso',    'icon' => '🎓' );
        if ( $completed_courses >= 3 )  $badges[] = array( 'id' => 'three_courses', 'label' => '3 cursos',        'icon' => '🌟' );
        if ( $reviews_count >= 1 )      $badges[] = array( 'id' => 'first_review',  'label' => 'Primera reseña',  'icon' => '⭐' );
        if ( $enrollments_count >= 5 )  $badges[] = array( 'id' => 'five_courses',  'label' => '5 inscritos',     'icon' => '📚' );

        // ── Guardar en usermeta para cache rápido ────────────────────
        update_user_meta( $user_id, '_mxwm_gamification_points', $total_points );
        update_user_meta( $user_id, '_mxwm_gamification_level',  $level );

        return rest_ensure_response( array(
            'user_id'           => $user_id,
            'points'            => $total_points,
            'level'             => (int) $level,
            'next_level_points' => $next_level,
            'progress_to_next'  => $progress_to_next,
            'badges'            => $badges,
            'stats'             => array(
                'completed_lessons' => $completed_lessons,
                'completed_courses' => $completed_courses,
                'reviews'           => $reviews_count,
                'enrollments'       => $enrollments_count,
            ),
        ) );
    }

    /* =============================================================
     * DEVICE TOKENS — Push Notifications
     * ============================================================= */

    /**
     * POST /mxwm/v1/devices/register
     * Registra o actualiza el Expo push token del dispositivo del usuario.
     *
     * Body: { token: "ExponentPushToken[xxx]", platform: "android|ios" }
     */
    public function register_device_token( \WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $token    = sanitize_text_field( $request->get_param('token') );
        $platform = sanitize_text_field( $request->get_param('platform') ?: 'android' );

        if ( empty( $token ) ) {
            return new \WP_Error( 'missing_token', 'El campo token es requerido.', array( 'status' => 400 ) );
        }

        // Validación básica de formato Expo push token
        if ( ! preg_match( '/^ExponentPushToken\[.+\]$/', $token ) && ! preg_match( '/^[a-zA-Z0-9_-]{20,}$/', $token ) ) {
            return new \WP_Error( 'invalid_token', 'Formato de token inválido.', array( 'status' => 422 ) );
        }

        // Recuperar tokens existentes del usuario
        $existing = get_user_meta( $user_id, '_mxwm_push_tokens', true );
        $tokens   = is_array( $existing ) ? $existing : array();

        // Evitar duplicados — upsert por token
        $found = false;
        foreach ( $tokens as &$t ) {
            if ( $t['token'] === $token ) {
                $t['platform']    = $platform;
                $t['last_seen']   = current_time( 'mysql' );
                $found = true;
                break;
            }
        }
        unset( $t );

        if ( ! $found ) {
            $tokens[] = array(
                'token'      => $token,
                'platform'   => $platform,
                'registered' => current_time( 'mysql' ),
                'last_seen'  => current_time( 'mysql' ),
            );
        }

        // Máximo 5 dispositivos por usuario (rotar el más antiguo)
        if ( count( $tokens ) > 5 ) {
            array_shift( $tokens );
        }

        update_user_meta( $user_id, '_mxwm_push_tokens', $tokens );

        return rest_ensure_response( array(
            'success'         => true,
            'registered'      => ! $found,
            'updated'         => $found,
            'device_count'    => count( $tokens ),
        ) );
    }

    /**
     * POST /mxwm/v1/devices/unregister
     * Elimina un token de push del usuario (ej. al hacer logout).
     *
     * Body: { token: "ExponentPushToken[xxx]" }
     */
    public function unregister_device_token( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $token   = sanitize_text_field( $request->get_param('token') );

        if ( empty( $token ) ) {
            return new \WP_Error( 'missing_token', 'El campo token es requerido.', array( 'status' => 400 ) );
        }

        $existing = get_user_meta( $user_id, '_mxwm_push_tokens', true );
        $tokens   = is_array( $existing ) ? $existing : array();
        $initial  = count( $tokens );

        $tokens = array_values( array_filter( $tokens, fn( $t ) => $t['token'] !== $token ) );
        update_user_meta( $user_id, '_mxwm_push_tokens', $tokens );

        return rest_ensure_response( array(
            'success'      => true,
            'removed'      => $initial > count( $tokens ),
            'device_count' => count( $tokens ),
        ) );
    }

    /**
     * WP-0: POST /mxwm/v1/lessons/{id}/complete
     * Marca una lección como 100% completada. Registra en lesson_progress
     * y recalcula el progreso total del curso.
     * La app llama este endpoint desde CourseVideoPlayer al terminar un video.
     */
    public function complete_lesson( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id   = get_current_user_id();
        $lesson_id = (int) $request->get_param('id');

        // Verificar que la lección existe
        $lesson = get_post( $lesson_id );
        if ( ! $lesson || $lesson->post_type !== 'mxwm_lesson' ) {
            return new \WP_Error( 'not_found', 'Lección no encontrada.', array( 'status' => 404 ) );
        }

        $course_id = (int) get_post_meta( $lesson_id, 'course_id', true );

        // Verificar inscripción
        $enrolled = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mxwm_enrollments WHERE user_id=%d AND course_id=%d AND status='active'",
            $user_id, $course_id
        ) );
        if ( ! $enrolled ) {
            return new \WP_Error( 'not_enrolled', 'Debes estar inscrito para completar lecciones.', array( 'status' => 403 ) );
        }

        // Upsert en lesson_progress al 100%
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mxwm_lesson_progress WHERE user_id=%d AND lesson_id=%d",
            $user_id, $lesson_id
        ) );

        if ( $existing ) {
            $wpdb->update(
                $wpdb->prefix . 'mxwm_lesson_progress',
                array( 'percentage' => 100, 'completed_at' => current_time('mysql'), 'video_position_seconds' => 0 ),
                array( 'user_id' => $user_id, 'lesson_id' => $lesson_id )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'mxwm_lesson_progress',
                array(
                    'user_id'                => $user_id,
                    'lesson_id'              => $lesson_id,
                    'course_id'              => $course_id,
                    'percentage'             => 100,
                    'video_position_seconds' => 0,
                    'completed_at'           => current_time('mysql'),
                    'updated_at'             => current_time('mysql'),
                )
            );
        }

        // Recalcular progreso del curso
        $progress_pct = $this->calculate_course_progress( $user_id, $course_id );

        return rest_ensure_response( array(
            'success'      => true,
            'lesson_id'    => $lesson_id,
            'progress_pct' => $progress_pct,
        ) );
    }

    /**
     * WP-0: POST /mxwm/v1/lessons/{id}/watch-time
     * Actualiza el tiempo de visualización de una lección (heartbeat cada ~10s).
     * Falla silenciosamente en la app si no existe — ahora responde correctamente.
     *
     * Body: { watch_time: <segundos> }
     */
    public function update_watch_time( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id    = get_current_user_id();
        $lesson_id  = (int) $request->get_param('id');
        $watch_time = (int) $request->get_param('watch_time');

        if ( $watch_time <= 0 ) {
            return rest_ensure_response( array( 'success' => true ) ); // Ignorar valores inválidos
        }

        $lesson    = get_post( $lesson_id );
        if ( ! $lesson || $lesson->post_type !== 'mxwm_lesson' ) {
            return new \WP_Error( 'not_found', 'Lección no encontrada.', array( 'status' => 404 ) );
        }

        $course_id = (int) get_post_meta( $lesson_id, 'course_id', true );

        // Upsert: solo actualiza video_position_seconds, no toca percentage ni completed_at
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mxwm_lesson_progress WHERE user_id=%d AND lesson_id=%d",
            $user_id, $lesson_id
        ) );

        if ( $existing ) {
            $wpdb->update(
                $wpdb->prefix . 'mxwm_lesson_progress',
                array( 'video_position_seconds' => $watch_time, 'updated_at' => current_time('mysql') ),
                array( 'user_id' => $user_id, 'lesson_id' => $lesson_id )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'mxwm_lesson_progress',
                array(
                    'user_id'                => $user_id,
                    'lesson_id'              => $lesson_id,
                    'course_id'              => $course_id,
                    'percentage'             => 0,
                    'video_position_seconds' => $watch_time,
                    'updated_at'             => current_time('mysql'),
                )
            );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }
}

// Arrancar el controlador
new MXWM_Courses_REST_Controller();
