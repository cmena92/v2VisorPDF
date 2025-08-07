<?php
/**
 * Gestor de Navegación Frontend - FASE 4
 * Maneja navegación avanzada, breadcrumbs y búsqueda para usuarios finales
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-visor-core.php';

class Visor_PDF_Frontend_Navigation extends Visor_PDF_Core {
    
    private $current_folder = null;
    private $breadcrumb_trail = array();
    
    public function __construct() {
        parent::__construct();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks y acciones
     */
    private function init_hooks() {
        // AJAX endpoints para frontend
        add_action('wp_ajax_get_folder_contents', array($this, 'ajax_get_folder_contents'));
        add_action('wp_ajax_nopriv_get_folder_contents', array($this, 'ajax_get_folder_contents'));
        
        add_action('wp_ajax_search_actas', array($this, 'ajax_search_actas'));
        add_action('wp_ajax_nopriv_search_actas', array($this, 'ajax_search_actas'));
        
        add_action('wp_ajax_get_breadcrumb', array($this, 'ajax_get_breadcrumb'));
        add_action('wp_ajax_nopriv_get_breadcrumb', array($this, 'ajax_get_breadcrumb'));
        
        add_action('wp_ajax_filter_actas', array($this, 'ajax_filter_actas'));
        add_action('wp_ajax_nopriv_filter_actas', array($this, 'ajax_filter_actas'));
        
        // NUEVO: Endpoint unificado para navegador visual
        add_action('wp_ajax_unified_navigator', array($this, 'ajax_unified_navigator'));
        add_action('wp_ajax_nopriv_unified_navigator', array($this, 'ajax_unified_navigator'));
        
        // Scripts frontend para navegador visual
        add_action('wp_enqueue_scripts', array($this, 'enqueue_visual_navigator_scripts'));
        
        // Scripts frontend original
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Shortcode para navegador visual
        add_shortcode('actas_navigator_visual', array($this, 'shortcode_visual_navigator'));
        
        // Shortcode extendido
        add_shortcode('actas_navigator', array($this, 'render_actas_navigator'));
        
        // NUEVO: Shortcode navegador avanzado
        add_shortcode('actas_navigator_advanced', array($this, 'render_actas_navigator_advanced'));
    }
    
    /**
     * Cargar scripts para frontend (solo cuando se usa el shortcode)
     */
    public function enqueue_frontend_scripts() {
        // NO cargar automáticamente - solo cuando se use el shortcode
        // Los scripts se cargan en render_actas_navigator()
    }
    
    /**
     * AJAX: Obtener contenido de carpeta
     */
    public function ajax_get_folder_contents() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        
        try {
            global $wpdb;
            
            // Obtener información de la carpeta
            $folder = null;
            if ($folder_id > 0) {
                $folder = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_folders} WHERE id = %d",
                    $folder_id
                ));
            }
            
            // Construir consulta de actas
            $where_clause = "WHERE a.status = 'active'";
            $params = array();
            
            if ($folder_id > 0) {
                $where_clause .= " AND a.folder_id = %d";
                $params[] = $folder_id;
            }
            
            // Paginación
            $offset = ($page - 1) * $per_page;
            
            // Obtener actas
            $query = "SELECT a.*, f.name as folder_name 
                     FROM {$this->table_metadata} a
                     LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id
                     {$where_clause}
                     ORDER BY a.upload_date DESC
                     LIMIT %d OFFSET %d";
            
            $params[] = $per_page;
            $params[] = $offset;
            
            $actas = $wpdb->get_results($wpdb->prepare($query, $params));
            
            // Obtener total para paginación
            $total_query = "SELECT COUNT(*) FROM {$this->table_metadata} a {$where_clause}";
            $total_params = array_slice($params, 0, -2); // Remover LIMIT y OFFSET
            $total = $wpdb->get_var($wpdb->prepare($total_query, $total_params));
            
            // Obtener subcarpetas si estamos en una carpeta específica
            $subfolders = array();
            if ($folder_id >= 0) {
                $subfolders = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->table_folders} 
                     WHERE parent_id = %d AND visible_frontend = 1
                     ORDER BY order_index ASC, name ASC",
                    $folder_id
                ));
            }
            
            // Generar breadcrumb
            $breadcrumb = $this->generate_breadcrumb($folder_id);
            
            wp_send_json_success(array(
                'actas' => $this->format_actas_for_frontend($actas),
                'subfolders' => $this->format_folders_for_frontend($subfolders),
                'folder' => $folder,
                'breadcrumb' => $breadcrumb,
                'pagination' => array(
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $per_page)
                )
            ));
            
        } catch (Exception $e) {
            error_log('Error en get_folder_contents: ' . $e->getMessage());
            wp_send_json_error('Error al cargar contenido');
        }
    }
    
    /**
     * AJAX: Búsqueda global de actas
     */
    public function ajax_search_actas() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        
        if (strlen($search_term) < 2) {
            wp_send_json_error('El término de búsqueda debe tener al menos 2 caracteres');
        }
        
        try {
            global $wpdb;
            
            $offset = ($page - 1) * $per_page;
            $search_like = '%' . $wpdb->esc_like($search_term) . '%';
            
            // Búsqueda en título y descripción
            $query = "SELECT a.*, f.name as folder_name 
                     FROM {$this->table_metadata} a
                     LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id
                     WHERE a.status = 'active' 
                     AND (a.title LIKE %s OR a.description LIKE %s)
                     ORDER BY 
                         CASE WHEN a.title LIKE %s THEN 1 ELSE 2 END,
                         a.upload_date DESC
                     LIMIT %d OFFSET %d";
            
            $actas = $wpdb->get_results($wpdb->prepare(
                $query, 
                $search_like, $search_like, $search_like, $per_page, $offset
            ));
            
            // Total de resultados
            $total_query = "SELECT COUNT(*) FROM {$this->table_metadata} a
                           WHERE a.status = 'active' 
                           AND (a.title LIKE %s OR a.description LIKE %s)";
            $total = $wpdb->get_var($wpdb->prepare($total_query, $search_like, $search_like));
            
            wp_send_json_success(array(
                'actas' => $this->format_actas_for_frontend($actas),
                'search_term' => $search_term,
                'pagination' => array(
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $per_page)
                )
            ));
            
        } catch (Exception $e) {
            error_log('Error en search_actas: ' . $e->getMessage());
            wp_send_json_error('Error en la búsqueda');
        }
    }
    
    /**
     * AJAX: Obtener breadcrumb
     */
    public function ajax_get_breadcrumb() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        $breadcrumb = $this->generate_breadcrumb($folder_id);
        
        wp_send_json_success(array('breadcrumb' => $breadcrumb));
    }
    
    /**
     * AJAX: Filtrar actas
     */
    public function ajax_filter_actas() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        
        try {
            global $wpdb;
            
            $where_conditions = array("a.status = 'active'");
            $params = array();
            
            // Filtro por carpeta
            if ($folder_id > 0) {
                $where_conditions[] = "a.folder_id = %d";
                $params[] = $folder_id;
            }
            
            // Filtro por fecha
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "DATE(a.upload_date) >= %s";
                $params[] = sanitize_text_field($filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "DATE(a.upload_date) <= %s";
                $params[] = sanitize_text_field($filters['date_to']);
            }
            
            // Filtro por número de páginas
            if (!empty($filters['min_pages'])) {
                $where_conditions[] = "a.total_pages >= %d";
                $params[] = intval($filters['min_pages']);
            }
            if (!empty($filters['max_pages'])) {
                $where_conditions[] = "a.total_pages <= %d";
                $params[] = intval($filters['max_pages']);
            }
            
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
            
            // Orden
            $order_by = "ORDER BY a.upload_date DESC";
            if (!empty($filters['sort_by'])) {
                switch ($filters['sort_by']) {
                    case 'title':
                        $order_by = "ORDER BY a.title ASC";
                        break;
                    case 'pages':
                        $order_by = "ORDER BY a.total_pages DESC";
                        break;
                    case 'size':
                        $order_by = "ORDER BY a.file_size DESC";
                        break;
                }
            }
            
            // Paginación
            $offset = ($page - 1) * $per_page;
            
            $query = "SELECT a.*, f.name as folder_name 
                     FROM {$this->table_metadata} a
                     LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id
                     {$where_clause}
                     {$order_by}
                     LIMIT %d OFFSET %d";
            
            $params[] = $per_page;
            $params[] = $offset;
            
            $actas = $wpdb->get_results($wpdb->prepare($query, $params));
            
            // Total
            $total_query = "SELECT COUNT(*) FROM {$this->table_metadata} a {$where_clause}";
            $total_params = array_slice($params, 0, -2);
            $total = $wpdb->get_var($wpdb->prepare($total_query, $total_params));
            
            wp_send_json_success(array(
                'actas' => $this->format_actas_for_frontend($actas),
                'filters_applied' => $filters,
                'pagination' => array(
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $per_page)
                )
            ));
            
        } catch (Exception $e) {
            error_log('Error en filter_actas: ' . $e->getMessage());
            wp_send_json_error('Error al aplicar filtros');
        }
    }
    
    /**
     * Generar breadcrumb trail jerárquico mejorado
     */
    private function generate_breadcrumb($folder_id) {
        if ($folder_id <= 0) {
            return array(
                array(
                    'name' => 'Todas las actas', 
                    'title' => 'Todas las actas',
                    'folder_id' => 0, 
                    'is_current' => true
                )
            );
        }
        
        global $wpdb;
        $breadcrumb = array();
        $current_id = $folder_id;
        
        // Construir breadcrumb desde la carpeta actual hacia arriba
        while ($current_id > 0) {
            $folder = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_folders} WHERE id = %d",
                $current_id
            ));
            
            if (!$folder) break;
            
            // Determinar si es una carpeta padre o hija
            $is_parent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} WHERE parent_id = %d",
                $folder->id
            )) > 0;
            
            array_unshift($breadcrumb, array(
                'name' => $folder->name,
                'title' => $folder->name,
                'folder_id' => $folder->id,
                'is_current' => ($current_id == $folder_id),
                'is_parent' => $is_parent,
                'parent_id' => $folder->parent_id
            ));
            
            $current_id = $folder->parent_id;
        }
        
        // Agregar "Todas las actas" al principio
        array_unshift($breadcrumb, array(
            'name' => 'Todas las actas',
            'title' => 'Todas las actas',
            'folder_id' => 0,
            'is_current' => false,
            'is_parent' => false,
            'parent_id' => null
        ));
        
        return $breadcrumb;
    }
    
    /**
     * Formatear actas para frontend (sin información sensible)
     */
    private function format_actas_for_frontend($actas) {
        $formatted = array();
        
        foreach ($actas as $acta) {
            $formatted[] = array(
                'id' => $acta->id,
                'title' => $acta->title,
                'description' => $acta->description,
                'upload_date' => date('d/m/Y', strtotime($acta->upload_date)),
                'total_pages' => intval($acta->total_pages), // Asegurar que sea entero
                'file_size_formatted' => size_format($acta->file_size),
                'folder_name' => $acta->folder_name ?: 'Sin clasificar'
            );
        }
        
        return $formatted;
    }
    
    /**
     * Formatear carpetas para frontend
     */
    private function format_folders_for_frontend($folders) {
        $formatted = array();
        
        foreach ($folders as $folder) {
            // Contar actas en la carpeta
            global $wpdb;
            $actas_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_metadata} 
                 WHERE folder_id = %d AND status = 'active'",
                $folder->id
            ));
            
            $formatted[] = array(
                'id' => $folder->id,
                'name' => $folder->name,
                'description' => $folder->description,
                'actas_count' => intval($actas_count)
            );
        }
        
        return $formatted;
    }
    
    /**
     * Shortcode para navegador de actas avanzado
     */
    public function render_actas_navigator($atts) {
        $atts = shortcode_atts(array(
            'navegacion' => 'true',
            'breadcrumb' => 'true', 
            'busqueda' => 'true',
            'filtros' => 'fecha,paginas,orden',
            'carpeta' => '',
            'limite' => '10',
            'mostrar_carpetas' => 'true'
        ), $atts);
        
        // Verificar autenticación
        if (!is_user_logged_in()) {
            return '<p>Debe iniciar sesión para ver las actas.</p>';
        }
        
        // Verificar número de colegiado usando método robusto del core
        $numero_colegiado = $this->verify_user_permissions();
        if (!$numero_colegiado) {
            return '<p>Su cuenta no tiene un número de colegiado asignado. Contacte al administrador.</p>';
        }
        
        // Cargar scripts solo para este shortcode
        $this->enqueue_navigator_scripts();
        
        ob_start();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/frontend-navigator.php';
        return ob_get_clean();
    }
    
    /**
     * Cargar scripts específicos del navegador
     */
    private function enqueue_navigator_scripts() {
        static $scripts_loaded = false;
        
        if ($scripts_loaded) {
            return;
        }
        
        // 1. jQuery (base para todo)
        wp_enqueue_script('jquery');
        
        // 2. CSS del visor PDF (primero para evitar FOUC)
        wp_enqueue_style(
            'visor-pdf-css',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/visor-pdf.css',
            array(),
            VISOR_PDF_CRISMAN_VERSION
        );
        
        // 3. CSS para navegación frontend
        wp_enqueue_style(
            'visor-frontend-nav-css',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/css/frontend-nav.css',
            array('visor-pdf-css'), // Dependencia del CSS del visor
            VISOR_PDF_CRISMAN_VERSION
        );
        
        // 4. JavaScript del visor PDF (CORE - debe cargar primero)
        wp_enqueue_script(
            'visor-pdf-js',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/visor-pdf.js',
            array('jquery'),
            VISOR_PDF_CRISMAN_VERSION,
            true // En footer para mejor rendimiento
        );
        
        // 5. Configuración para el visor PDF
        wp_localize_script('visor-pdf-js', 'actas_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('actas_nonce')
        ));
        
        // 6. JavaScript del navegador (EXTENDIDO - depende del visor)
        wp_enqueue_script(
            'visor-frontend-nav-js',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/js/frontend-nav.js',
            array('jquery', 'visor-pdf-js'), // IMPORTANTE: Dependencia estricta del visor PDF
            VISOR_PDF_CRISMAN_VERSION,
            true // En footer después del visor
        );
        
        // 7. Configuración para el navegador
        wp_localize_script('visor-frontend-nav-js', 'frontendNavAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('actas_nonce'),
            'loading_text' => __('Cargando...', 'visor-pdf-crisman'),
            'no_results_text' => __('No se encontraron resultados', 'visor-pdf-crisman'),
            'search_placeholder' => __('Buscar actas...', 'visor-pdf-crisman'),
            'debug_mode' => WP_DEBUG // Para debugging
        ));
        
        // 8. Script inline para verificar carga
        $inline_script = "
            // Verificación de integración del navegador
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof console !== 'undefined' && console.log) {
                    console.log('🔍 Navegador PDF: Scripts cargados');
                    console.log('- jQuery:', typeof window.jQuery !== 'undefined' ? '✅' : '❌');
                    console.log('- Visor PDF:', typeof window.visorPDFCrisman !== 'undefined' ? '✅' : '❌');
                    console.log('- Navigator:', typeof window.frontendNavigator !== 'undefined' ? '✅' : '❌');
                }
            });
        ";
        
        wp_add_inline_script('visor-frontend-nav-js', $inline_script, 'after');
        
        $scripts_loaded = true;
        
        error_log('Visor PDF: Scripts del navegador encolados correctamente');
    }
    
    /**
     * Obtener configuración de navegación para JavaScript
     */
    public function get_navigation_config($atts) {
        return array(
            'show_navigation' => $atts['navegacion'] === 'true',
            'show_breadcrumb' => $atts['breadcrumb'] === 'true',
            'show_search' => $atts['busqueda'] === 'true',
            'show_folders' => $atts['mostrar_carpetas'] === 'true',
            'filters' => explode(',', $atts['filtros']),
            'initial_folder' => !empty($atts['carpeta']) ? intval($atts['carpeta']) : 0,
            'per_page' => intval($atts['limite'])
        );
    }
    
    // =============================================
    // NAVEGADOR AVANZADO - NUEVOS MÉTODOS
    // =============================================
    
    /**
     * Shortcode para navegador avanzado con panel lateral
     */
    public function render_actas_navigator_advanced($atts) {
        $atts = shortcode_atts(array(
            'navegacion_lateral' => 'true',
            'breadcrumb' => 'true',
            'mostrar_contadores' => 'true',
            'vista_inicial' => 'ultimas',
            'limite_inicial' => '5',
            'responsive_breakpoint' => '768',
            'busqueda' => 'true',
            'filtros' => 'fecha,paginas,orden'
        ), $atts);
        
        // Verificar autenticación
        if (!is_user_logged_in()) {
            return '<p>Debe iniciar sesión para ver las actas.</p>';
        }
        
        // Verificar número de colegiado
        $numero_colegiado = $this->verify_user_permissions();
        if (!$numero_colegiado) {
            return '<p>Su cuenta no tiene un número de colegiado asignado. Contacte al administrador.</p>';
        }
        
        // Cargar scripts específicos del navegador avanzado
        $this->enqueue_advanced_navigator_scripts();
        
        ob_start();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/frontend-navigator-advanced.php';
        return ob_get_clean();
    }
    
    /**
     * Cargar scripts específicos del navegador avanzado
     */
    private function enqueue_advanced_navigator_scripts() {
        static $scripts_loaded = false;
        
        if ($scripts_loaded) {
            return;
        }
        
        // Cargar scripts base del navegador normal
        $this->enqueue_navigator_scripts();
        
        // JavaScript del navegador avanzado
        wp_enqueue_script(
            'visor-advanced-nav',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/js/advanced-navigator.js',
            array('jquery', 'visor-pdf-js', 'visor-frontend-nav'),
            VISOR_PDF_CRISMAN_VERSION,
            true
        );
        
        wp_localize_script('visor-advanced-nav', 'advancedNavAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('actas_nonce'),
            'loading_text' => __('Cargando...', 'visor-pdf-crisman'),
            'no_folders_text' => __('No hay carpetas disponibles', 'visor-pdf-crisman'),
            'recent_actas_text' => __('Últimas actas subidas', 'visor-pdf-crisman')
        ));
        
        // CSS del navegador avanzado
        wp_enqueue_style(
            'visor-advanced-nav',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/css/advanced-navigator.css',
            array('visor-frontend-nav'),
            VISOR_PDF_CRISMAN_VERSION
        );
        
        $scripts_loaded = true;
    }
    
    /**
     * AJAX: Obtener árbol completo de carpetas con contadores
     */
    public function ajax_get_folder_tree() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        try {
            $folder_tree = $this->get_folder_tree_with_counts();
            wp_send_json_success(array(
                'folders' => $folder_tree
            ));
        } catch (Exception $e) {
            error_log('Error en get_folder_tree: ' . $e->getMessage());
            wp_send_json_error('Error al cargar estructura de carpetas');
        }
    }
    
    /**
     * AJAX: Obtener actas recientes
     */
    public function ajax_get_recent_actas() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        
        try {
            $recent_actas = $this->get_recent_actas($limit);
            wp_send_json_success(array(
                'actas' => $this->format_actas_for_frontend($recent_actas),
                'total' => count($recent_actas)
            ));
        } catch (Exception $e) {
            error_log('Error en get_recent_actas: ' . $e->getMessage());
            wp_send_json_error('Error al cargar actas recientes');
        }
    }
    
    /**
     * Obtener árbol de carpetas con contadores de actas
     */
    private function get_folder_tree_with_counts() {
        global $wpdb;
        
        // Obtener todas las carpetas visibles en frontend
        $folders = $wpdb->get_results(
            "SELECT id, name, parent_id, order_index 
             FROM {$this->table_folders} 
             WHERE visible_frontend = 1 
             ORDER BY parent_id ASC, order_index ASC, name ASC"
        );
        
        if (!$folders) {
            return array();
        }
        
        // Construir árbol jerárquico
        $tree = array();
        $folder_map = array();
        
        // Primero crear el mapa y calcular contadores
        foreach ($folders as $folder) {
            $folder->actas_count = $this->get_folder_actas_count($folder->id);
            $folder->level = 0;
            $folder->children = array();
            $folder_map[$folder->id] = $folder;
        }
        
        // Construir jerarquía
        foreach ($folders as $folder) {
            if ($folder->parent_id === null || $folder->parent_id == 0) {
                // Carpeta raíz
                $folder->level = 0;
                $tree[] = $folder;
            } else {
                // Carpeta hija
                if (isset($folder_map[$folder->parent_id])) {
                    $folder->level = $folder_map[$folder->parent_id]->level + 1;
                    $folder_map[$folder->parent_id]->children[] = $folder;
                }
            }
        }
        
        return $tree;
    }
    
    /**
     * Obtener número de actas en una carpeta (incluye subcarpetas)
     */
    private function get_folder_actas_count($folder_id) {
        global $wpdb;
        
        // Obtener conteo directo de la carpeta
        $direct_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_metadata} 
             WHERE folder_id = %d AND status = 'active'",
            $folder_id
        ));
        
        // Obtener subcarpetas
        $subfolders = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$this->table_folders} 
             WHERE parent_id = %d AND visible_frontend = 1",
            $folder_id
        ));
        
        $total_count = intval($direct_count);
        
        // Sumar conteos de subcarpetas recursivamente
        foreach ($subfolders as $subfolder) {
            $total_count += $this->get_folder_actas_count($subfolder->id);
        }
        
        return $total_count;
    }
    
    /**
     * Obtener las actas más recientes
     */
    private function get_recent_actas($limit = 5) {
        global $wpdb;
        
        $query = "SELECT a.*, f.name as folder_name 
                 FROM {$this->table_metadata} a
                 LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id
                 WHERE a.status = 'active'
                 ORDER BY a.upload_date DESC
                 LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $limit));
    }
    
    /**
     * AJAX: Crear carpetas de ejemplo
     */
    public function ajax_create_sample_folders() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        // Solo administradores pueden crear carpetas
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            
            // Verificar si ya existen carpetas
            $existing_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_folders}");
            
            if ($existing_folders > 0) {
                wp_send_json_error('Ya existen carpetas en el sistema');
            }
            
            // Crear estructura de carpetas de ejemplo
            $sample_folders = array(
                array(
                    'name' => '2025',
                    'description' => 'Actas del año 2025',
                    'parent_id' => 0,
                    'visible_frontend' => 1,
                    'order_index' => 1
                ),
                array(
                    'name' => '2024',
                    'description' => 'Actas del año 2024',
                    'parent_id' => 0,
                    'visible_frontend' => 1,
                    'order_index' => 2
                ),
                array(
                    'name' => '2016',
                    'description' => 'Actas del año 2016',
                    'parent_id' => 0,
                    'visible_frontend' => 1,
                    'order_index' => 3
                ),
                array(
                    'name' => 'Archivo Histórico',
                    'description' => 'Actas de años anteriores',
                    'parent_id' => 0,
                    'visible_frontend' => 1,
                    'order_index' => 4
                )
            );
            
            $created_folders = array();
            
            foreach ($sample_folders as $folder_data) {
                $result = $wpdb->insert(
                    $this->table_folders,
                    $folder_data,
                    array('%s', '%s', '%d', '%d', '%d')
                );
                
                if ($result !== false) {
                    $folder_id = $wpdb->insert_id;
                    $created_folders[] = $folder_data['name'] . ' (ID: ' . $folder_id . ')';
                }
            }
            
            // Organizar actas existentes en carpetas por año
            $this->organize_existing_actas();
            
            wp_send_json_success(array(
                'message' => 'Carpetas creadas exitosamente',
                'folders_created' => $created_folders,
                'total' => count($created_folders)
            ));
            
        } catch (Exception $e) {
            error_log('Error creando carpetas: ' . $e->getMessage());
            wp_send_json_error('Error al crear carpetas: ' . $e->getMessage());
        }
    }
    
    /**
     * Organizar actas existentes en carpetas por año
     */
    private function organize_existing_actas() {
        global $wpdb;
        
        // Obtener mapeo de carpetas por nombre
        $folders_map = array();
        $folders = $wpdb->get_results("SELECT id, name FROM {$this->table_folders}");
        foreach ($folders as $folder) {
            $folders_map[$folder->name] = $folder->id;
        }
        
        // Obtener actas sin carpeta asignada o con folder_id = 0
        $actas = $wpdb->get_results(
            "SELECT id, title, upload_date FROM {$this->table_metadata} 
             WHERE (folder_id IS NULL OR folder_id = 0) AND status = 'active'"
        );
        
        foreach ($actas as $acta) {
            // Intentar determinar el año desde el título o fecha
            $year = null;
            
            // Método 1: Buscar año en el título
            if (preg_match('/\b(20\d{2})\b/', $acta->title, $matches)) {
                $year = $matches[1];
            }
            // Método 2: Usar año de la fecha de subida
            else {
                $year = date('Y', strtotime($acta->upload_date));
            }
            
            // Asignar a carpeta correspondiente
            $folder_id = null;
            if (isset($folders_map[$year])) {
                $folder_id = $folders_map[$year];
            } else if ($year < 2020) {
                // Actas antiguas van a "Archivo Histórico"
                $folder_id = isset($folders_map['Archivo Histórico']) ? $folders_map['Archivo Histórico'] : null;
            }
            
            // Actualizar acta si se encontró carpeta
            if ($folder_id) {
                $wpdb->update(
                    $this->table_metadata,
                    array('folder_id' => $folder_id),
                    array('id' => $acta->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }
    
    /**
     * NUEVO: Endpoint unificado para navegador visual
     * Maneja filtros combinados: carpeta + búsqueda + fecha + orden
     */
    public function ajax_unified_navigator() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Acceso denegado');
        }
        
        $numero_colegiado = $this->verify_user_permissions();
        if (!$numero_colegiado) {
            wp_send_json_error('Debe iniciar sesión');
        }
        
        // Obtener parámetros
        $folder_id = intval($_POST['folder_id'] ?? 0);
        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        $order_by = sanitize_text_field($_POST['order_by'] ?? 'upload_date');
        $order_direction = sanitize_text_field($_POST['order_direction'] ?? 'DESC');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 12);
        
        try {
            // Construir query base
            global $wpdb;
            
            $where_conditions = array("a.status = 'active'");
            $join_clauses = array();
            $params = array();
            
            // Filtro por carpeta
            if ($folder_id > 0) {
                $where_conditions[] = 'a.folder_id = %d';
                $params[] = $folder_id;
            }
            
            // Filtro por búsqueda
            if (!empty($search_term)) {
                $where_conditions[] = '(a.title LIKE %s OR a.description LIKE %s OR a.original_name LIKE %s)';
                $search_like = '%' . $wpdb->esc_like($search_term) . '%';
                $params[] = $search_like;
                $params[] = $search_like;
                $params[] = $search_like;
            }
            
            // Filtro por fecha
            if (!empty($date_from)) {
                $where_conditions[] = 'DATE(a.upload_date) >= %s';
                $params[] = $date_from;
            }
            if (!empty($date_to)) {
                $where_conditions[] = 'DATE(a.upload_date) <= %s';
                $params[] = $date_to;
            }
            
            // Validar orden
            $valid_orders = array('upload_date', 'title', 'total_pages');
            if (!in_array($order_by, $valid_orders)) {
                $order_by = 'upload_date';
            }
            $order_direction = $order_direction === 'ASC' ? 'ASC' : 'DESC';
            
            // Query para contar total
            $count_sql = "SELECT COUNT(*) FROM {$this->table_metadata} a";
            if (!empty($join_clauses)) {
                $count_sql .= ' ' . implode(' ', $join_clauses);
            }
            $count_sql .= ' WHERE ' . implode(' AND ', $where_conditions);
            
            $total_actas = $wpdb->get_var($wpdb->prepare($count_sql, $params));
            
            // Query principal
            $offset = ($page - 1) * $per_page;
            $sql = "SELECT a.*, f.name as folder_name 
                    FROM {$this->table_metadata} a 
                    LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id";
            
            if (!empty($join_clauses)) {
                $sql .= ' ' . implode(' ', $join_clauses);
            }
            
            $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
            $sql .= " ORDER BY a.{$order_by} {$order_direction}";
            $sql .= ' LIMIT %d OFFSET %d';
            
            $params[] = $per_page;
            $params[] = $offset;
            
            $actas = $wpdb->get_results($wpdb->prepare($sql, $params));
            
            // Procesar resultados
            $processed_actas = array();
            foreach ($actas as $acta) {
                $processed_actas[] = array(
                    'id' => $acta->id,
                    'title' => $acta->title ?: 'Sin título',
                    'description' => $acta->description,
                    'filename' => $acta->filename,
                    'original_name' => $acta->original_name,
                    'folder_name' => $acta->folder_name ?: 'Sin carpeta',
                    'folder_id' => $acta->folder_id,
                    'upload_date' => $acta->upload_date,
                    'upload_date_formatted' => date('d/m/Y', strtotime($acta->upload_date)),
                    'total_pages' => $acta->total_pages,
                    'file_size' => $acta->file_size ? size_format($acta->file_size) : 'N/A'
                );
            }
            
            // Obtener información de carpeta actual para breadcrumb
            $current_folder = null;
            if ($folder_id > 0) {
                $current_folder = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_folders} WHERE id = %d",
                    $folder_id
                ));
            }
            
            wp_send_json_success(array(
                'actas' => $processed_actas,
                'pagination' => array(
                    'total' => intval($total_actas),
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => ceil($total_actas / $per_page)
                ),
                'current_folder' => $current_folder,
                'breadcrumb' => $this->generate_breadcrumb($folder_id),
                'filters_applied' => array(
                    'folder_id' => $folder_id,
                    'search_term' => $search_term,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'order_by' => $order_by,
                    'order_direction' => $order_direction
                )
            ));
            
        } catch (Exception $e) {
            error_log('Error en navegador unificado: ' . $e->getMessage());
            wp_send_json_error('Error al cargar actas: ' . $e->getMessage());
        }
    }
    

    
    /**
     * Obtener carpetas para selector visual con jerarquía correcta
     */
    public function get_folders_for_selector() {
        global $wpdb;
        
        // Obtener todas las carpetas visibles con conteo de actas
        $folders = $wpdb->get_results(
            "SELECT id, name, slug, parent_id, order_index,
                    (SELECT COUNT(*) FROM {$this->table_metadata} WHERE folder_id = f.id AND status = 'active') as actas_count 
             FROM {$this->table_folders} f 
             WHERE visible_frontend = 1 
             ORDER BY parent_id ASC, order_index ASC, name ASC"
        );
        
        // Construir estructura jerárquica
        $hierarchical_folders = array();
        $folder_map = array();
        
        // Primero, crear un mapa de todas las carpetas
        foreach ($folders as $folder) {
            $folder->children = array();
            $folder_map[$folder->id] = $folder;
        }
        
        // Luego, construir la jerarquía
        foreach ($folders as $folder) {
            if ($folder->parent_id === null || $folder->parent_id == 0) {
                // Carpeta raíz
                $hierarchical_folders[] = $folder;
            } else {
                // Carpeta hija - agregarla a su padre
                if (isset($folder_map[$folder->parent_id])) {
                    $folder_map[$folder->parent_id]->children[] = $folder;
                }
            }
        }
        
        // Agregar opción "Todas" al inicio
        $total_actas = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_metadata} WHERE status = 'active'"
        );
        
        $all_option = (object) array(
            'id' => 0,
            'name' => 'Todas las actas',
            'slug' => 'todas',
            'actas_count' => $total_actas,
            'parent_id' => null,
            'children' => array()
        );
        
        array_unshift($hierarchical_folders, $all_option);
        
        return $hierarchical_folders;
    }
    
    /**
     * Cargar scripts específicos para navegador visual (Corregido)
     */
    public function enqueue_visual_navigator_scripts() {
        // SIEMPRE cargar cuando se use el shortcode - no verificar has_shortcode
        
        wp_enqueue_script(
            'visual-navigator-js',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/js/visual-navigator.js',
            array('jquery'),
            VISOR_PDF_CRISMAN_VERSION,
            true
        );
        
        // CSS principal del navegador visual
        wp_enqueue_style(
            'visual-navigator-css',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/css/visual-navigator-simple.css',
            array(),
            VISOR_PDF_CRISMAN_VERSION
        );
        
        // CSS específico para jerarquía de carpetas
        wp_enqueue_style(
            'visual-navigator-hierarchy-css',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/css/visual-navigator-hierarchy.css',
            array('visual-navigator-css'),
            VISOR_PDF_CRISMAN_VERSION
        );
        
        // Localizar variables para JavaScript
        wp_localize_script('visual-navigator-js', 'visualNavigator', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('actas_nonce'),
            'texts' => array(
                'loading' => 'Cargando...',
                'no_results' => 'No se encontraron actas con los filtros aplicados.',
                'error' => 'Error al cargar las actas. Inténtelo de nuevo.',
                'search_placeholder' => 'Buscar actas...',
                'clear_filters' => 'Limpiar filtros',
                'apply_filters' => 'Aplicar filtros',
                'pages' => 'páginas'
            )
        ));
        
        // Debug: Log que se cargaron los scripts
        error_log('Visor PDF: Scripts del navegador visual encolados');
    }
    
    /**
     * Shortcode para navegador visual (Simplificado y Corregido)
     */
    public function shortcode_visual_navigator($atts) {
        // Verificar permisos
        $numero_colegiado = $this->verify_user_permissions();
        if (!$numero_colegiado) {
            return '<p class="actas-error">Debe iniciar sesión con un número de colegiado válido para ver las actas.</p>';
        }
        
        // Atributos simplificados - solo configuración básica
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'default_order' => 'upload_date',
            'default_direction' => 'DESC'
        ), $atts);
        
        // IMPORTANTE: Cargar scripts directamente cuando se usa el shortcode
        $this->enqueue_visual_navigator_scripts();
        
        // Obtener carpetas para selector
        $folders = $this->get_folders_for_selector();
        
        // Renderizar template
        ob_start();
        
        // Variables para el template
        $numero_colegiado_var = $numero_colegiado;
        $folders_var = $folders;
        $atts_var = $atts;
        
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/visual-navigator.php';
        return ob_get_clean();
    }
    
    /**
     * Obtener configuración para navegador avanzado
     */
    public function get_advanced_navigation_config($atts) {
        return array(
            'sidebar_navigation' => $atts['navegacion_lateral'] === 'true',
            'show_breadcrumb' => $atts['breadcrumb'] === 'true',
            'show_counters' => $atts['mostrar_contadores'] === 'true',
            'initial_view' => $atts['vista_inicial'],
            'initial_limit' => intval($atts['limite_inicial']),
            'responsive_breakpoint' => intval($atts['responsive_breakpoint']),
            'show_search' => $atts['busqueda'] === 'true',
            'filters' => explode(',', $atts['filtros'])
        );
    }
}
