<?php
/**
 * Instalador del Plugin - Configuración inicial completa
 * 
 * @package VisorPDFCrisman
 * @version 2.0.8
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Visor_PDF_Plugin_Installer {
    
    /**
     * Ejecutar instalación completa del plugin
     */
    public static function install() {
        self::create_database_tables();
        self::create_default_folders();
        self::create_directory_structure();
        self::set_default_options();
        self::create_default_user_roles();
        
        // Log de instalación
        error_log('[Visor PDF] Plugin instalado correctamente - ' . date('Y-m-d H:i:s'));
        
        // Actualizar versión
        update_option('visor_pdf_crisman_version', VISOR_PDF_CRISMAN_VERSION);
        update_option('visor_pdf_crisman_installed', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Crear todas las tablas necesarias
     */
    private static function create_database_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Tabla para logs de visualización
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            numero_colegiado varchar(50) NOT NULL,
            acta_filename varchar(255) NOT NULL,
            folder_id int(11) DEFAULT NULL,
            page_viewed int(11) NOT NULL,
            viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            viewing_duration int(11) DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_user_acta (user_id, acta_filename),
            INDEX idx_colegiado (numero_colegiado),
            INDEX idx_folder_id (folder_id),
            INDEX idx_viewed_at (viewed_at),
            INDEX idx_session (session_id)
        ) $charset_collate;";
        dbDelta($sql_logs);
        
        // 2. Tabla para metadatos de actas
        $sql_actas = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_metadata (
            id int(11) NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            original_name varchar(255) NOT NULL,
            title varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            folder_id int(11) DEFAULT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            uploaded_by int(11) DEFAULT NULL,
            total_pages int(11) DEFAULT 0,
            file_size bigint DEFAULT 0,
            status enum('active', 'inactive', 'archived') DEFAULT 'active',
            mime_type varchar(100) DEFAULT 'application/pdf',
            file_hash varchar(64) DEFAULT NULL,
            tags text DEFAULT NULL,
            access_level enum('public', 'restricted', 'private') DEFAULT 'restricted',
            PRIMARY KEY (id),
            UNIQUE KEY uk_filename (filename),
            INDEX idx_folder_id (folder_id),
            INDEX idx_status (status),
            INDEX idx_upload_date (upload_date),
            INDEX idx_uploaded_by (uploaded_by),
            INDEX idx_file_hash (file_hash)
        ) $charset_collate;";
        dbDelta($sql_actas);
        
        // 3. Tabla para carpetas jerárquicas
        $sql_folders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_folders (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            parent_id int(11) DEFAULT NULL,
            order_index int(11) DEFAULT 0,
            visible_frontend tinyint(1) DEFAULT 1,
            icon varchar(50) DEFAULT 'folder',
            color varchar(7) DEFAULT '#0073aa',
            access_level enum('public', 'restricted', 'private') DEFAULT 'restricted',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_slug (slug),
            INDEX idx_parent_id (parent_id),
            INDEX idx_order (order_index),
            INDEX idx_visible (visible_frontend),
            INDEX idx_access_level (access_level)
        ) $charset_collate;";
        dbDelta($sql_folders);
        
        // 4. Tabla para actividades sospechosas
        $sql_suspicious = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_suspicious_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            numero_colegiado varchar(50) NOT NULL,
            acta_id int(11) DEFAULT NULL,
            activity_type varchar(100) NOT NULL,
            page_num int(11) DEFAULT NULL,
            description text DEFAULT NULL,
            severity enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            logged_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_activity (user_id, activity_type),
            INDEX idx_acta_activity (acta_id, activity_type),
            INDEX idx_severity (severity),
            INDEX idx_logged_at (logged_at)
        ) $charset_collate;";
        dbDelta($sql_suspicious);
        
        // 5. Tabla para analytics y estadísticas
        $sql_analytics = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_analytics (
            id int(11) NOT NULL AUTO_INCREMENT,
            metric_name varchar(100) NOT NULL,
            metric_value longtext DEFAULT NULL,
            category varchar(50) DEFAULT 'general',
            period_start datetime DEFAULT NULL,
            period_end datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_metric_period (metric_name, period_start, period_end),
            INDEX idx_category (category),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_analytics);
        
        // 6. Tabla para sesiones de usuario
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_user_sessions (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            session_token varchar(100) NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            status enum('active', 'expired', 'terminated') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY uk_session_token (session_token),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_last_activity (last_activity)
        ) $charset_collate;";
        dbDelta($sql_sessions);
        
        error_log('[Visor PDF] Tablas de base de datos creadas/actualizadas');
    }
    
    /**
     * Crear estructura de carpetas predefinidas
     */
    private static function create_default_folders() {
        global $wpdb;
        
        // Verificar si ya existen carpetas
        $existing_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
        if ($existing_folders > 0) {
            error_log('[Visor PDF] Carpetas ya existen, omitiendo creación');
            return;
        }
        
        $folders_structure = array(
            // 1. Actas de Junta Directiva (Carpeta padre)
            array(
                'name' => 'Actas de Junta Directiva',
                'slug' => 'junta-directiva',
                'description' => 'Actas de las reuniones de Junta Directiva organizadas por año',
                'parent_id' => null,
                'order_index' => 1,
                'visible_frontend' => 1,
                'icon' => 'building',
                'color' => '#0073aa',
                'access_level' => 'restricted'
            ),
            // 2. Actas de Asamblea (Carpeta padre)
            array(
                'name' => 'Actas de Asamblea',
                'slug' => 'asamblea',
                'description' => 'Actas de Asambleas Generales y Extraordinarias',
                'parent_id' => null,
                'order_index' => 2,
                'visible_frontend' => 1,
                'icon' => 'groups',
                'color' => '#00a32a',
                'access_level' => 'restricted'
            ),
            // 3. Sin Clasificar (Carpeta temporal)
            array(
                'name' => 'Sin Clasificar',
                'slug' => 'sin-clasificar',
                'description' => 'Documentos pendientes de clasificación',
                'parent_id' => null,
                'order_index' => 999,
                'visible_frontend' => 0,
                'icon' => 'category',
                'color' => '#dba617',
                'access_level' => 'private'
            )
        );
        
        $created_folders = array();
        $folder_ids = array();
        
        // Crear carpetas padre
        foreach ($folders_structure as $folder_data) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'actas_folders',
                array_merge($folder_data, array(
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id() ?: 1
                )),
                array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result !== false) {
                $folder_id = $wpdb->insert_id;
                $folder_ids[$folder_data['slug']] = $folder_id;
                $created_folders[] = $folder_data['name'] . ' (ID: ' . $folder_id . ')';
                error_log("[Visor PDF] Carpeta creada: {$folder_data['name']} (ID: $folder_id)");
            }
        }
        
        // Crear subcarpetas por años para Junta Directiva
        if (isset($folder_ids['junta-directiva'])) {
            $parent_id = $folder_ids['junta-directiva'];
            $current_year = date('Y');
            
            $year_folders = array();
            // Crear carpetas para los últimos 5 años + año actual + próximo año
            for ($year = $current_year - 4; $year <= $current_year + 1; $year++) {
                $year_folders[] = array(
                    'name' => (string)$year,
                    'slug' => 'junta-directiva-' . $year,
                    'description' => "Actas de Junta Directiva del año $year",
                    'parent_id' => $parent_id,
                    'order_index' => ($current_year + 1) - $year + 1, // Orden descendente (más reciente primero)
                    'visible_frontend' => 1,
                    'icon' => 'calendar-alt',
                    'color' => '#0073aa',
                    'access_level' => 'restricted'
                );
            }
            
            foreach ($year_folders as $year_data) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'actas_folders',
                    array_merge($year_data, array(
                        'created_at' => current_time('mysql'),
                        'created_by' => get_current_user_id() ?: 1
                    )),
                    array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
                );
                
                if ($result !== false) {
                    $folder_id = $wpdb->insert_id;
                    $created_folders[] = '  └─ ' . $year_data['name'] . ' (ID: ' . $folder_id . ', Hijo de: Junta Directiva)';
                    error_log("[Visor PDF] Subcarpeta creada: {$year_data['name']} (ID: $folder_id)");
                }
            }
        }
        
        // Crear subcarpetas por años para Asamblea
        if (isset($folder_ids['asamblea'])) {
            $parent_id = $folder_ids['asamblea'];
            $current_year = date('Y');
            
            $year_folders = array();
            // Crear carpetas para los últimos 3 años + año actual
            for ($year = $current_year - 2; $year <= $current_year; $year++) {
                $year_folders[] = array(
                    'name' => "Asamblea $year",
                    'slug' => 'asamblea-' . $year,
                    'description' => "Actas de Asamblea del año $year",
                    'parent_id' => $parent_id,
                    'order_index' => $current_year - $year + 1, // Orden descendente
                    'visible_frontend' => 1,
                    'icon' => 'calendar-alt',
                    'color' => '#00a32a',
                    'access_level' => 'restricted'
                );
            }
            
            foreach ($year_folders as $year_data) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'actas_folders',
                    array_merge($year_data, array(
                        'created_at' => current_time('mysql'),
                        'created_by' => get_current_user_id() ?: 1
                    )),
                    array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
                );
                
                if ($result !== false) {
                    $folder_id = $wpdb->insert_id;
                    $created_folders[] = '  └─ ' . $year_data['name'] . ' (ID: ' . $folder_id . ', Hijo de: Asamblea)';
                    error_log("[Visor PDF] Subcarpeta creada: {$year_data['name']} (ID: $folder_id)");
                }
            }
        }
        
        // Guardar registro de carpetas creadas
        update_option('visor_pdf_default_folders_created', $created_folders);
        error_log('[Visor PDF] Estructura de carpetas creada: ' . count($created_folders) . ' carpetas');
    }
    
    /**
     * Crear estructura de directorios del sistema
     */
    private static function create_directory_structure() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        $directories = array(
            $base_dir . '/actas-pdf',
            $base_dir . '/actas-pdf/temp',
            $base_dir . '/actas-pdf/cache',
            $base_dir . '/actas-pdf/thumbnails',
            $base_dir . '/actas-pdf/watermarks',
            $base_dir . '/actas-pdf/backups'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (wp_mkdir_p($dir)) {
                    // Crear archivo .htaccess para seguridad
                    $htaccess_content = "# Protección de archivos PDF\n";
                    $htaccess_content .= "Options -Indexes\n";
                    $htaccess_content .= "<Files *.pdf>\n";
                    $htaccess_content .= "    Order Allow,Deny\n";
                    $htaccess_content .= "    Deny from all\n";
                    $htaccess_content .= "</Files>\n";
                    
                    file_put_contents($dir . '/.htaccess', $htaccess_content);
                    
                    // Crear archivo index.php vacío
                    file_put_contents($dir . '/index.php', '<?php // Silence is golden');
                    
                    error_log("[Visor PDF] Directorio creado: $dir");
                } else {
                    error_log("[Visor PDF] Error creando directorio: $dir");
                }
            }
        }
        
        error_log('[Visor PDF] Estructura de directorios verificada/creada');
    }
    
    /**
     * Establecer opciones predeterminadas del plugin
     */
    private static function set_default_options() {
        $default_options = array(
            // Configuración general
            'visor_pdf_max_file_size' => 50, // MB
            'visor_pdf_allowed_file_types' => array('pdf'),
            'visor_pdf_watermark_enabled' => true,
            'visor_pdf_watermark_text' => '[numero_colegiado] - [fecha]',
            'visor_pdf_watermark_opacity' => 0.3,
            'visor_pdf_watermark_position' => 'center',
            
            // Configuración de seguridad
            'visor_pdf_require_login' => true,
            'visor_pdf_require_colegiado' => true,
            'visor_pdf_log_all_activities' => true,
            'visor_pdf_suspicious_activity_enabled' => true,
            'visor_pdf_max_session_time' => 60, // minutos
            'visor_pdf_heartbeat_interval' => 30, // segundos
            
            // Configuración de visualización
            'visor_pdf_default_zoom' => 1.0,
            'visor_pdf_max_zoom' => 3.0,
            'visor_pdf_min_zoom' => 0.5,
            'visor_pdf_show_thumbnails' => true,
            'visor_pdf_pages_per_load' => 5,
            'visor_pdf_cache_enabled' => true,
            'visor_pdf_cache_expiry' => 24, // horas
            
            // Configuración de analytics
            'visor_pdf_analytics_enabled' => true,
            'visor_pdf_analytics_retention_days' => 365,
            'visor_pdf_analytics_real_time' => true,
            
            // Configuración de notificaciones
            'visor_pdf_email_notifications' => true,
            'visor_pdf_notification_email' => get_option('admin_email'),
            'visor_pdf_suspicious_activity_threshold' => 5,
            
            // Configuración de rendimiento
            'visor_pdf_image_quality' => 150, // DPI
            'visor_pdf_image_format' => 'png',
            'visor_pdf_compression_enabled' => true,
            'visor_pdf_lazy_loading' => true
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
                error_log("[Visor PDF] Opción creada: $option_name = " . print_r($option_value, true));
            }
        }
        
        error_log('[Visor PDF] Opciones predeterminadas configuradas');
    }
    
    /**
     * Crear roles de usuario personalizados
     */
    private static function create_default_user_roles() {
        // Rol para visualizadores de actas
        add_role(
            'actas_viewer',
            'Visualizador de Actas',
            array(
                'read' => true,
                'view_actas' => true
            )
        );
        
        // Rol para gestores de actas
        add_role(
            'actas_manager',
            'Gestor de Actas',
            array(
                'read' => true,
                'view_actas' => true,
                'upload_actas' => true,
                'edit_actas' => true,
                'manage_actas_folders' => true
            )
        );
        
        // Añadir capacidades a roles existentes
        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('view_actas');
            $administrator->add_cap('upload_actas');
            $administrator->add_cap('edit_actas');
            $administrator->add_cap('manage_actas_folders');
            $administrator->add_cap('view_actas_analytics');
            $administrator->add_cap('manage_actas_security');
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_actas');
            $editor->add_cap('upload_actas');
            $editor->add_cap('edit_actas');
        }
        
        error_log('[Visor PDF] Roles de usuario configurados');
    }
    
    /**
     * Verificar si es necesaria una actualización
     */
    public static function needs_update() {
        $installed_version = get_option('visor_pdf_crisman_version', '0.0.0');
        return version_compare($installed_version, VISOR_PDF_CRISMAN_VERSION, '<');
    }
    
    /**
     * Ejecutar actualización del plugin
     */
    public static function update() {
        $installed_version = get_option('visor_pdf_crisman_version', '0.0.0');
        
        error_log("[Visor PDF] Actualizando desde versión $installed_version a " . VISOR_PDF_CRISMAN_VERSION);
        
        // Actualizar tablas si es necesario
        self::create_database_tables();
        
        // Crear carpetas si no existen
        $existing_folders = get_option('visor_pdf_default_folders_created', array());
        if (empty($existing_folders)) {
            self::create_default_folders();
        }
        
        // Verificar directorios
        self::create_directory_structure();
        
        // Actualizar opciones si es necesario
        self::set_default_options();
        
        // Actualizar versión
        update_option('visor_pdf_crisman_version', VISOR_PDF_CRISMAN_VERSION);
        update_option('visor_pdf_crisman_last_update', current_time('mysql'));
        
        error_log('[Visor PDF] Actualización completada');
        
        return true;
    }
    
    /**
     * Desinstalación completa (opcional)
     */
    public static function uninstall() {
        global $wpdb;
        
        // Eliminar tablas
        $tables = array(
            $wpdb->prefix . 'actas_logs',
            $wpdb->prefix . 'actas_metadata',
            $wpdb->prefix . 'actas_folders', 
            $wpdb->prefix . 'actas_suspicious_logs',
            $wpdb->prefix . 'actas_analytics',
            $wpdb->prefix . 'actas_user_sessions'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Eliminar opciones
        $options = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'visor_pdf_%'"
        );
        
        foreach ($options as $option) {
            delete_option($option->option_name);
        }
        
        // Eliminar roles personalizados
        remove_role('actas_viewer');
        remove_role('actas_manager');
        
        // Eliminar archivos subidos (opcional)
        $upload_dir = wp_upload_dir();
        $actas_dir = $upload_dir['basedir'] . '/actas-pdf';
        if (file_exists($actas_dir)) {
            // Aquí se podría implementar la eliminación de archivos
            // self::recursive_rmdir($actas_dir);
        }
        
        error_log('[Visor PDF] Plugin desinstalado completamente');
    }
    
    /**
     * Función auxiliar para eliminar directorios recursivamente
     */
    private static function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        self::recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
