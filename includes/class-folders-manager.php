<?php
/**
 * Gestor de Carpetas - FASE 2
 * Maneja toda la funcionalidad de gestión de carpetas
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-visor-core.php';

class Visor_PDF_Folders_Manager extends Visor_PDF_Core {
    
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
        add_action('wp_ajax_create_folder', array($this, 'ajax_create_folder'));
        add_action('wp_ajax_update_folder', array($this, 'ajax_update_folder'));
        add_action('wp_ajax_delete_folder', array($this, 'ajax_delete_folder'));
        add_action('wp_ajax_reorder_folders', array($this, 'ajax_reorder_folders'));
        add_action('wp_ajax_reassign_actas', array($this, 'ajax_reassign_actas'));
        add_action('wp_ajax_get_folder_actas', array($this, 'ajax_get_folder_actas'));
        
        // AJAX endpoints para gestión de actas
        add_action('wp_ajax_delete_acta', array($this, 'ajax_delete_acta'));
        add_action('wp_ajax_rename_acta', array($this, 'ajax_rename_acta'));
        
        // Scripts admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'visor-pdf-crisman',
            'Gestionar Carpetas',
            'Gestionar Carpetas',
            'manage_options',
            'visor-pdf-crisman-folders',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        $message = '';
        $error = '';
        
        // Manejar formularios POST
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'folders_management')) {
            switch ($_POST['action']) {
                case 'create_folder':
                    $result = $this->handle_create_folder($_POST);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                    break;
                    
                case 'update_folder':
                    $result = $this->handle_update_folder($_POST);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                    break;
            }
        }
        
        // Obtener datos para la vista
        $folders_hierarchy = $this->get_folders_hierarchy();
        $all_actas = $this->get_all_actas_with_folders();
        
        // Cargar template
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-folders.php';
    }
    
    /**
     * Cargar scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'visor-pdf-crisman-folders') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Cargar JavaScript modular
        wp_enqueue_script(
            'folders-admin-js',
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/js/folders-admin.js',
            array('jquery'),
            VISOR_PDF_CRISMAN_VERSION,
            true
        );
        
        wp_localize_script('folders-admin-js', 'foldersAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folders_management')
        ));
    }
    
    /**
     * MÉTODOS DE GESTIÓN DE CARPETAS
     */
    
    /**
     * Obtener jerarquía de carpetas
     */
    public function get_folders_hierarchy($parent_id = null) {
        global $wpdb;
        
        $where = ($parent_id === null) ? 'parent_id IS NULL' : $wpdb->prepare('parent_id = %d', $parent_id);
        
        $folders = $wpdb->get_results(
            "SELECT * FROM {$this->table_folders} 
             WHERE {$where} 
             ORDER BY order_index ASC, name ASC"
        );
        
        foreach ($folders as &$folder) {
            $folder->children = $this->get_folders_hierarchy($folder->id);
            $folder->actas_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_metadata} 
                     WHERE folder_id = %d AND status = 'active'",
                    $folder->id
                )
            );
        }
        
        return $folders;
    }
    
    /**
     * Crear nueva carpeta
     */
    private function handle_create_folder($data) {
        global $wpdb;
        
        $name = sanitize_text_field($data['folder_name']);
        $parent_id = !empty($data['parent_id']) ? intval($data['parent_id']) : null;
        
        if (empty($name)) {
            return array('success' => false, 'message' => 'El nombre de la carpeta es requerido.');
        }
        
        // Generar slug único
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        // Validar jerarquía
        if ($parent_id && $this->get_folder_depth($parent_id) >= 1) {
            return array('success' => false, 'message' => 'No se permiten más de 2 niveles de carpetas.');
        }
        
        // Verificar nombre único en el nivel
        if ($this->name_exists_in_level($name, $parent_id)) {
            return array('success' => false, 'message' => 'Ya existe una carpeta con ese nombre en este nivel.');
        }
        
        $order_index = $this->get_next_order_index($parent_id);
        
        $result = $wpdb->insert(
            $this->table_folders,
            array(
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parent_id,
                'order_index' => $order_index,
                'visible_frontend' => 1
            ),
            array('%s', '%s', '%d', '%d', '%d')
        );
        
        if ($result) {
            return array('success' => true, 'message' => 'Carpeta creada exitosamente.');
        } else {
            return array('success' => false, 'message' => 'Error al crear la carpeta.');
        }
    }
    
    /**
     * Actualizar carpeta
     */
    private function handle_update_folder($data) {
        global $wpdb;
        
        $folder_id = intval($data['folder_id']);
        $name = sanitize_text_field($data['folder_name']);
        
        if (empty($name)) {
            return array('success' => false, 'message' => 'El nombre de la carpeta es requerido.');
        }
        
        $folder = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_folders} WHERE id = %d",
                $folder_id
            )
        );
        
        if (!$folder) {
            return array('success' => false, 'message' => 'Carpeta no encontrada.');
        }
        
        if ($this->name_exists_in_level($name, $folder->parent_id, $folder_id)) {
            return array('success' => false, 'message' => 'Ya existe una carpeta con ese nombre en este nivel.');
        }
        
        $result = $wpdb->update(
            $this->table_folders,
            array('name' => $name),
            array('id' => $folder_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            return array('success' => true, 'message' => 'Carpeta actualizada exitosamente.');
        } else {
            return array('success' => false, 'message' => 'Error al actualizar la carpeta.');
        }
    }
    
    /**
     * Obtener todas las actas con información de carpetas
     */
    private function get_all_actas_with_folders() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT a.*, 
                    COALESCE(f.name, 'Sin Clasificar') as folder_name,
                    COALESCE(f.id, 0) as folder_id_safe
             FROM {$this->table_metadata} a 
             LEFT JOIN {$this->table_folders} f ON a.folder_id = f.id 
             WHERE a.status = 'active' 
             ORDER BY COALESCE(f.name, 'ZZZ_Sin_Clasificar') ASC, a.title ASC"
        );
    }
    
    /**
     * MÉTODOS AJAX
     */
    
    public function ajax_create_folder() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $result = $this->handle_create_folder($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_update_folder() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $result = $this->handle_update_folder($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_delete_folder() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        global $wpdb;
        $folder_id = intval($_POST['folder_id']);
        
        $folder = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_folders} WHERE id = %d",
                $folder_id
            )
        );
        
        if (!$folder) {
            wp_send_json_error('Carpeta no encontrada');
        }
        
        if (in_array($folder->slug, array('junta-directiva', 'asamblea', 'sin-clasificar'))) {
            wp_send_json_error('No se pueden eliminar las carpetas predefinidas del sistema');
        }
        
        $has_children = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} WHERE parent_id = %d",
                $folder_id
            )
        ) > 0;
        
        if ($has_children) {
            wp_send_json_error('No se puede eliminar una carpeta que contiene subcarpetas');
        }
        
        $sin_clasificar_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_folders} WHERE slug = %s",
                'sin-clasificar'
            )
        );
        
        $moved_actas = $wpdb->update(
            $this->table_metadata,
            array('folder_id' => $sin_clasificar_id),
            array('folder_id' => $folder_id),
            array('%d'),
            array('%d')
        );
        
        $result = $wpdb->delete(
            $this->table_folders,
            array('id' => $folder_id),
            array('%d')
        );
        
        if ($result) {
            $message = "Carpeta eliminada exitosamente.";
            if ($moved_actas > 0) {
                $message .= " Se movieron {$moved_actas} actas a 'Sin Clasificar'.";
            }
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error('Error al eliminar la carpeta');
        }
    }
    
    public function ajax_reorder_folders() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        global $wpdb;
        $folder_orders = json_decode(stripslashes($_POST['folder_orders']), true);
        
        if (!is_array($folder_orders)) {
            wp_send_json_error('Datos de orden inválidos');
        }
        
        foreach ($folder_orders as $folder_data) {
            $folder_id = intval($folder_data['id']);
            $order_index = intval($folder_data['order']);
            
            $wpdb->update(
                $this->table_folders,
                array('order_index' => $order_index),
                array('id' => $folder_id),
                array('%d'),
                array('%d')
            );
        }
        
        wp_send_json_success(array('message' => 'Orden actualizado exitosamente'));
    }
    
    public function ajax_reassign_actas() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        global $wpdb;
        $acta_ids = array_map('intval', $_POST['acta_ids']);
        $new_folder_id = intval($_POST['new_folder_id']);
        
        if (empty($acta_ids)) {
            wp_send_json_error('No se seleccionaron actas');
        }
        
        $folder_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} WHERE id = %d",
                $new_folder_id
            )
        ) > 0;
        
        if (!$folder_exists) {
            wp_send_json_error('Carpeta destino no válida');
        }
        
        $placeholders = implode(',', array_fill(0, count($acta_ids), '%d'));
        $query = $wpdb->prepare(
            "UPDATE {$this->table_metadata} 
             SET folder_id = %d 
             WHERE id IN ({$placeholders})",
            array_merge(array($new_folder_id), $acta_ids)
        );
        
        $result = $wpdb->query($query);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => "Se reasignaron {$result} actas exitosamente",
                'moved_count' => $result
            ));
        } else {
            wp_send_json_error('Error al reasignar las actas');
        }
    }
    
    public function ajax_get_folder_actas() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'folders_management')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $folder_id = intval($_POST['folder_id']);
        $actas = $this->get_actas_by_folder($folder_id);
        
        wp_send_json_success(array('actas' => $actas));
    }
    
    /**
     * MÉTODOS DE UTILIDAD
     */
    
    private function slug_exists($slug) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} WHERE slug = %s",
                $slug
            )
        ) > 0;
    }
    
    private function name_exists_in_level($name, $parent_id, $exclude_id = null) {
        global $wpdb;
        
        $where_parent = ($parent_id === null) ? 'parent_id IS NULL' : $wpdb->prepare('parent_id = %d', $parent_id);
        $where_exclude = $exclude_id ? $wpdb->prepare(' AND id != %d', $exclude_id) : '';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_folders} 
                 WHERE name = %s AND {$where_parent}{$where_exclude}",
                $name
            )
        ) > 0;
    }
    
    private function get_folder_depth($folder_id) {
        global $wpdb;
        
        $depth = 0;
        $current_id = $folder_id;
        
        while ($current_id) {
            $parent_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT parent_id FROM {$this->table_folders} WHERE id = %d",
                    $current_id
                )
            );
            
            if ($parent_id) {
                $depth++;
                $current_id = $parent_id;
            } else {
                break;
            }
        }
        
        return $depth;
    }
    
    private function get_next_order_index($parent_id) {
        global $wpdb;
        
        $where_parent = ($parent_id === null) ? 'parent_id IS NULL' : $wpdb->prepare('parent_id = %d', $parent_id);
        
        $max_order = $wpdb->get_var(
            "SELECT MAX(order_index) FROM {$this->table_folders} WHERE {$where_parent}"
        );
        
        return ($max_order !== null) ? $max_order + 1 : 1;
    }
    
    private function get_actas_by_folder($folder_id) {
        global $wpdb;
        
        // Manejar el caso donde folder_id puede ser NULL
        if ($folder_id === null || $folder_id === 0) {
            return $wpdb->get_results(
                "SELECT * FROM {$this->table_metadata} 
                 WHERE (folder_id IS NULL OR folder_id = 0) AND status = 'active' 
                 ORDER BY upload_date DESC"
            );
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_metadata} 
                 WHERE folder_id = %d AND status = 'active' 
                 ORDER BY upload_date DESC",
                $folder_id
            )
        );
    }
    
    /**
     * AJAX: Eliminar acta
     */
    public function ajax_delete_acta() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'folders_manager_nonce')) {
            wp_send_json_error('Error de seguridad');
        }
        
        $acta_id = isset($_POST['acta_id']) ? intval($_POST['acta_id']) : 0;
        
        if (!$acta_id) {
            wp_send_json_error('ID de acta inválido');
        }
        
        global $wpdb;
        
        // Obtener información del archivo antes de eliminar
        $acta = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_metadata} WHERE id = %d",
                $acta_id
            )
        );
        
        if (!$acta) {
            wp_send_json_error('Acta no encontrada');
        }
        
        // Eliminar archivo físico
        if ($acta->file_path && file_exists($acta->file_path)) {
            unlink($acta->file_path);
        }
        
        // Marcar como eliminado en la base de datos (soft delete)
        $result = $wpdb->update(
            $this->table_metadata,
            array(
                'status' => 'deleted',
                'upload_date' => current_time('mysql') // Actualizar fecha de modificación
            ),
            array('id' => $acta_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Registrar en log (usando tabla de suspicious_logs)
            global $wpdb;
            $user_id = get_current_user_id();
            $numero_colegiado = get_user_meta($user_id, 'numero_colegiado', true);
            
            if ($numero_colegiado) {
                $wpdb->insert(
                    $this->table_suspicious_logs,
                    array(
                        'user_id' => $user_id,
                        'numero_colegiado' => $numero_colegiado,
                        'acta_id' => $acta_id,
                        'activity_type' => 'acta_deleted',
                        'activity_data' => json_encode(array(
                            'deleted_title' => $acta->title,
                            'file_path' => $acta->file_path
                        )),
                        'timestamp' => current_time('mysql'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    )
                );
            }
            
            wp_send_json_success(array(
                'message' => 'Acta eliminada correctamente',
                'acta_id' => $acta_id
            ));
        } else {
            wp_send_json_error('Error al eliminar el acta');
        }
    }
    
    /**
     * AJAX: Renombrar acta
     */
    public function ajax_rename_acta() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'folders_manager_nonce')) {
            wp_send_json_error('Error de seguridad');
        }
        
        $acta_id = isset($_POST['acta_id']) ? intval($_POST['acta_id']) : 0;
        $new_title = isset($_POST['new_title']) ? sanitize_text_field($_POST['new_title']) : '';
        
        if (!$acta_id || empty($new_title)) {
            wp_send_json_error('Datos inválidos');
        }
        
        global $wpdb;
        
        // Verificar que el acta existe
        $acta = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_metadata} WHERE id = %d AND status = 'active'",
                $acta_id
            )
        );
        
        if (!$acta) {
            wp_send_json_error('Acta no encontrada');
        }
        
        // Actualizar título
        $result = $wpdb->update(
            $this->table_metadata,
            array(
                'title' => $new_title,
                'upload_date' => current_time('mysql') // Actualizar fecha de modificación
            ),
            array('id' => $acta_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Registrar en log (usando log_suspicious_activity adaptado)
            global $wpdb;
            $user_id = get_current_user_id();
            $numero_colegiado = get_user_meta($user_id, 'numero_colegiado', true);
            
            if ($numero_colegiado) {
                $wpdb->insert(
                    $this->table_suspicious_logs,
                    array(
                        'user_id' => $user_id,
                        'numero_colegiado' => $numero_colegiado,
                        'acta_id' => $acta_id,
                        'activity_type' => 'acta_renamed',
                        'activity_data' => json_encode(array(
                            'old_title' => $acta->title,
                            'new_title' => $new_title
                        )),
                        'timestamp' => current_time('mysql'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    )
                );
            }
            
            wp_send_json_success(array(
                'message' => 'Acta renombrada correctamente',
                'acta_id' => $acta_id,
                'new_title' => $new_title
            ));
        } else {
            wp_send_json_error('Error al renombrar el acta');
        }
    }
}
