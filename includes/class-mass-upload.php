<?php
/**
 * Gestor de Subida Masiva - FASE 3
 * Maneja la subida de múltiples PDFs simultáneamente
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-visor-core.php';

class Visor_PDF_Mass_Upload extends Visor_PDF_Core {
    
    private $max_files = 20;
    private $max_file_size = 10485760; // 10MB por archivo
    private $allowed_types = array('application/pdf');
    
    public function __construct() {
        parent::__construct();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks y acciones
     */
    private function init_hooks() {
        // Menú admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX endpoints
        add_action('wp_ajax_mass_upload_files', array($this, 'ajax_mass_upload_files'));
        add_action('wp_ajax_process_single_file', array($this, 'ajax_process_single_file'));
        add_action('wp_ajax_get_upload_progress', array($this, 'ajax_get_upload_progress'));
        
        // Scripts admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Aumentar límites para subida masiva
        add_filter('wp_max_upload_size', array($this, 'increase_upload_limits'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'visor-pdf-crisman',
            'Subida Masiva',
            'Subida Masiva',
            'manage_options',
            'visor-pdf-crisman-mass-upload',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página.');
        }
        
        // Obtener carpetas para selector
        $folders_hierarchy = $this->get_folders_hierarchy();
        
        // Obtener estadísticas del sistema
        $upload_stats = $this->get_upload_statistics();
        
        // Cargar template
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-mass-upload.php';
    }
    
    /**
     * Cargar scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'visor-pdf-crisman-mass-upload') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Cargar JavaScript modular
        wp_enqueue_script(
            'mass-upload-js',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/js/mass-upload.js',
            array('jquery', 'jquery-ui-progressbar'),
            VISOR_PDF_CRISMAN_VERSION,
            true
        );
        
        wp_localize_script('mass-upload-js', 'massUploadAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mass_upload_nonce'),
            'maxFiles' => $this->max_files,
            'maxFileSize' => $this->max_file_size,
            'allowedTypes' => $this->allowed_types,
            'texts' => array(
                'selectFiles' => 'Seleccionar Archivos',
                'dragHere' => 'Arrastra archivos PDF aquí',
                'processing' => 'Procesando...',
                'completed' => 'Completado',
                'failed' => 'Error',
                'maxFilesError' => 'Máximo ' . $this->max_files . ' archivos permitidos',
                'fileSizeError' => 'Archivo demasiado grande (máximo 10MB)',
                'fileTypeError' => 'Solo se permiten archivos PDF'
            )
        ));
    }
    
    /**
     * Obtener jerarquía de carpetas para selector
     */
    public function get_folders_hierarchy($parent_id = null) {
        global $wpdb;
        
        $where = ($parent_id === null) ? 'parent_id IS NULL' : $wpdb->prepare('parent_id = %d', $parent_id);
        
        $folders = $wpdb->get_results(
            "SELECT * FROM {$this->table_folders} 
             WHERE {$where} AND visible_frontend = 1
             ORDER BY order_index ASC, name ASC"
        );
        
        foreach ($folders as &$folder) {
            $folder->children = $this->get_folders_hierarchy($folder->id);
        }
        
        return $folders;
    }
    
    /**
     * Obtener estadísticas de subida
     */
    private function get_upload_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total de actas
        $stats['total_actas'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_metadata} WHERE status = 'active'"
        );
        
        // Actas subidas hoy
        $stats['today_uploads'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_metadata} 
             WHERE status = 'active' AND DATE(upload_date) = CURDATE()"
        );
        
        // Actas subidas esta semana
        $stats['week_uploads'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_metadata} 
             WHERE status = 'active' AND YEARWEEK(upload_date, 1) = YEARWEEK(CURDATE(), 1)"
        );
        
        // Tamaño total ocupado
        $stats['total_size'] = $wpdb->get_var(
            "SELECT SUM(file_size) FROM {$this->table_metadata} WHERE status = 'active'"
        ) ?: 0;
        
        return $stats;
    }
    
    /**
     * MÉTODOS AJAX PARA SUBIDA MASIVA
     */
    
    /**
     * AJAX: Iniciar subida masiva
     */
    public function ajax_mass_upload_files() {
        // Verificar permisos y nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'mass_upload_nonce')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $folder_id = intval($_POST['folder_id']);
        $files_count = intval($_POST['files_count']);
        
        // Validar parámetros
        if ($files_count > $this->max_files) {
            wp_send_json_error('Demasiados archivos. Máximo ' . $this->max_files . ' permitidos.');
        }
        
        if (empty($folder_id)) {
            wp_send_json_error('Debe seleccionar una carpeta destino.');
        }
        
        // Verificar que la carpeta existe
        global $wpdb;
        $folder_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} WHERE id = %d",
                $folder_id
            )
        );
        
        if (!$folder_exists) {
            wp_send_json_error('Carpeta destino no válida.');
        }
        
        // Crear sesión de subida
        $upload_session = array(
            'session_id' => uniqid('mass_upload_'),
            'folder_id' => $folder_id,
            'files_count' => $files_count,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'started_at' => current_time('mysql'),
            'status' => 'processing'
        );
        
        // Guardar sesión en transient (30 minutos)
        set_transient('mass_upload_' . $upload_session['session_id'], $upload_session, 1800);
        
        wp_send_json_success(array(
            'session_id' => $upload_session['session_id'],
            'message' => 'Sesión de subida iniciada'
        ));
    }
    
    /**
     * AJAX: Procesar archivo individual
     */
    public function ajax_process_single_file() {
        // Verificar permisos y nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'mass_upload_nonce')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $file_index = intval($_POST['file_index']);
        
        // Obtener sesión
        $session = get_transient('mass_upload_' . $session_id);
        if (!$session) {
            wp_send_json_error('Sesión expirada');
        }
        
        // Verificar que hay archivo
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->update_session_progress($session_id, false, 'Error al recibir el archivo');
            wp_send_json_error('Error al recibir el archivo');
        }
        
        $file = $_FILES['file'];
        $title = sanitize_text_field($_POST['title']) ?: pathinfo($file['name'], PATHINFO_FILENAME);
        $description = sanitize_textarea_field($_POST['description']) ?: '';
        
        // Validar archivo
        $validation_result = $this->validate_uploaded_file($file);
        if (!$validation_result['valid']) {
            $this->update_session_progress($session_id, false, $validation_result['error']);
            wp_send_json_error($validation_result['error']);
        }
        
        // Procesar archivo
        $result = $this->process_uploaded_file($file, $title, $description, $session['folder_id']);
        
        // Actualizar progreso de sesión
        $this->update_session_progress($session_id, $result['success'], $result['message']);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'acta_id' => $result['acta_id'],
                'file_index' => $file_index
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Obtener progreso de subida
     */
    public function ajax_get_upload_progress() {
        // Verificar permisos y nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'mass_upload_nonce')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $session_id = sanitize_text_field($_POST['session_id']);
        
        // Obtener sesión
        $session = get_transient('mass_upload_' . $session_id);
        if (!$session) {
            wp_send_json_error('Sesión no encontrada');
        }
        
        wp_send_json_success($session);
    }
    
    /**
     * MÉTODOS DE UTILIDAD
     */
    
    /**
     * Validar archivo subido
     */
    private function validate_uploaded_file($file) {
        // Verificar tipo
        if (!in_array($file['type'], $this->allowed_types)) {
            return array('valid' => false, 'error' => 'Solo se permiten archivos PDF');
        }
        
        // Verificar tamaño
        if ($file['size'] > $this->max_file_size) {
            return array('valid' => false, 'error' => 'Archivo demasiado grande (máximo 10MB)');
        }
        
        // Verificar que no está corrupto
        if ($file['size'] === 0) {
            return array('valid' => false, 'error' => 'Archivo vacío o corrupto');
        }
        
        return array('valid' => true);
    }
    
    /**
     * Procesar archivo individual
     */
    private function process_uploaded_file($file, $title, $description, $folder_id) {
        try {
            // Generar nombre único
            $filename = uniqid('mass_') . '_' . sanitize_file_name($file['name']);
            $filepath = $this->upload_dir . $filename;
            
            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return array('success' => false, 'message' => 'Error al guardar el archivo');
            }
            
            // Obtener número de páginas
            $total_pages = $this->get_pdf_page_count($filepath);
            
            // Guardar en base de datos
            global $wpdb;
            $result = $wpdb->insert(
                $this->table_metadata,
                array(
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'title' => $title,
                    'description' => $description,
                    'folder_id' => $folder_id,
                    'uploaded_by' => get_current_user_id(),
                    'total_pages' => $total_pages,
                    'file_size' => $file['size'],
                    'status' => 'active'
                ),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s')
            );
            
            if ($result) {
                return array(
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'acta_id' => $wpdb->insert_id
                );
            } else {
                // Limpiar archivo si falla la BD
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                return array('success' => false, 'message' => 'Error al guardar en base de datos');
            }
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Error interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Actualizar progreso de sesión
     */
    private function update_session_progress($session_id, $success, $message) {
        $session = get_transient('mass_upload_' . $session_id);
        if (!$session) return;
        
        $session['processed']++;
        
        if ($success) {
            $session['successful']++;
        } else {
            $session['failed']++;
            // Agregar mensaje de error
            if (!isset($session['errors'])) {
                $session['errors'] = array();
            }
            $session['errors'][] = $message;
        }
        
        // Verificar si terminó
        if ($session['processed'] >= $session['files_count']) {
            $session['status'] = 'completed';
            $session['completed_at'] = current_time('mysql');
        }
        
        // Actualizar transient
        set_transient('mass_upload_' . $session_id, $session, 1800);
    }
    
    /**
     * Aumentar límites para subida masiva
     */
    public function increase_upload_limits($size) {
        // Solo en página de subida masiva
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'visor-pdf-crisman-mass-upload') !== false) {
            return $this->max_file_size * $this->max_files; // 200MB total
        }
        return $size;
    }
}
