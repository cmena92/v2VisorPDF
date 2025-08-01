<?php
/**
 * Script de Migración - Visor PDF Crisman
 * 
 * Este script ayuda a migrar de versiones anteriores al nuevo sistema de instalación
 * 
 * @package VisorPDFCrisman
 * @version 2.0.8
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Visor_PDF_Migration_Helper {
    
    /**
     * Ejecutar proceso de migración completo
     */
    public static function run_full_migration() {
        $results = array();
        
        // 1. Verificar estado actual
        $current_state = self::check_current_state();
        $results['current_state'] = $current_state;
        
        // 2. Hacer backup de datos existentes
        $backup_result = self::backup_existing_data();
        $results['backup'] = $backup_result;
        
        // 3. Ejecutar instalación nueva
        require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-plugin-installer.php';
        $install_result = Visor_PDF_Plugin_Installer::install();
        $results['installation'] = $install_result;
        
        // 4. Migrar datos existentes a nueva estructura
        $migration_result = self::migrate_existing_data();
        $results['migration'] = $migration_result;
        
        // 5. Verificar integridad final
        $verification_result = self::verify_migration();
        $results['verification'] = $verification_result;
        
        return $results;
    }
    
    /**
     * Verificar estado actual del sistema
     */
    private static function check_current_state() {
        global $wpdb;
        
        $state = array(
            'tables' => array(),
            'folders' => 0,
            'actas' => 0,
            'options' => array(),
            'directories' => array()
        );
        
        // Verificar tablas existentes
        $required_tables = array(
            'actas_logs', 'actas_metadata', 'actas_folders', 
            'actas_suspicious_logs', 'actas_analytics', 'actas_user_sessions'
        );
        
        foreach ($required_tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;
            
            $state['tables'][$table_suffix] = array(
                'exists' => $exists,
                'count' => $count
            );
        }
        
        // Contar carpetas y actas existentes
        if ($state['tables']['actas_folders']['exists']) {
            $state['folders'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
        }
        
        if ($state['tables']['actas_metadata']['exists']) {
            $state['actas'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_metadata WHERE status = 'active'");
        }
        
        // Verificar opciones importantes
        $important_options = array(
            'visor_pdf_crisman_version',
            'visor_pdf_crisman_installed',
            'visor_pdf_default_folders_created'
        );
        
        foreach ($important_options as $option) {
            $state['options'][$option] = get_option($option, 'no_existe');
        }
        
        // Verificar directorios
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/actas-pdf';
        
        $state['directories']['base'] = file_exists($base_dir);
        $state['directories']['writable'] = is_writable($base_dir);
        
        return $state;
    }
    
    /**
     * Hacer backup de datos existentes
     */
    private static function backup_existing_data() {
        global $wpdb;
        
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'tables' => array(),
            'options' => array(),
            'files_count' => 0
        );
        
        // Backup de tablas existentes
        $tables_to_backup = array('actas_metadata', 'actas_folders', 'actas_logs');
        
        foreach ($tables_to_backup as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
                $backup_data['tables'][$table_suffix] = $data;
            }
        }
        
        // Backup de opciones importantes
        $options_to_backup = array(
            'visor_pdf_crisman_version',
            'visor_pdf_default_folders_created'
        );
        
        foreach ($options_to_backup as $option) {
            $backup_data['options'][$option] = get_option($option);
        }
        
        // Contar archivos existentes
        $upload_dir = wp_upload_dir();
        $actas_dir = $upload_dir['basedir'] . '/actas-pdf';
        
        if (file_exists($actas_dir)) {
            $files = glob($actas_dir . '/*.pdf');
            $backup_data['files_count'] = count($files);
        }
        
        // Guardar backup en option
        update_option('visor_pdf_migration_backup', $backup_data);
        
        return array(
            'success' => true,
            'tables_backed_up' => count($backup_data['tables']),
            'options_backed_up' => count($backup_data['options']),
            'files_found' => $backup_data['files_count']
        );
    }
    
    /**
     * Migrar datos existentes a nueva estructura
     */
    private static function migrate_existing_data() {
        $backup = get_option('visor_pdf_migration_backup', array());
        
        if (empty($backup['tables'])) {
            return array('success' => true, 'message' => 'No hay datos para migrar');
        }
        
        global $wpdb;
        $migrated = array();
        
        // Migrar metadatos de actas con nuevos campos
        if (isset($backup['tables']['actas_metadata'])) {
            foreach ($backup['tables']['actas_metadata'] as $acta) {
                // Añadir campos nuevos con valores por defecto
                $acta_data = array_merge($acta, array(
                    'mime_type' => 'application/pdf',
                    'file_hash' => hash('sha256', $acta['filename']),
                    'tags' => null,
                    'access_level' => 'restricted'
                ));
                
                // Intentar insertar (ignorar si ya existe por el UNIQUE KEY)
                $wpdb->replace($wpdb->prefix . 'actas_metadata', $acta_data);
            }
            $migrated['actas_metadata'] = count($backup['tables']['actas_metadata']);
        }
        
        // Migrar carpetas con nuevos campos
        if (isset($backup['tables']['actas_folders'])) {
            foreach ($backup['tables']['actas_folders'] as $folder) {
                // Añadir campos nuevos
                $folder_data = array_merge($folder, array(
                    'description' => null,
                    'icon' => 'folder',
                    'color' => '#0073aa',
                    'access_level' => 'restricted',
                    'created_by' => 1
                ));
                
                $wpdb->replace($wpdb->prefix . 'actas_folders', $folder_data);
            }
            $migrated['actas_folders'] = count($backup['tables']['actas_folders']);
        }
        
        // Migrar logs con nuevos campos
        if (isset($backup['tables']['actas_logs'])) {
            foreach ($backup['tables']['actas_logs'] as $log) {
                // Añadir campos nuevos
                $log_data = array_merge($log, array(
                    'session_id' => null,
                    'viewing_duration' => 0
                ));
                
                $wpdb->replace($wpdb->prefix . 'actas_logs', $log_data);
            }
            $migrated['actas_logs'] = count($backup['tables']['actas_logs']);
        }
        
        return array(
            'success' => true,
            'migrated' => $migrated,
            'message' => 'Datos migrados correctamente'
        );
    }
    
    /**
     * Verificar integridad de la migración
     */
    private static function verify_migration() {
        global $wpdb;
        
        $verification = array(
            'tables_ok' => true,
            'data_ok' => true,
            'folders_ok' => true,
            'files_ok' => true,
            'issues' => array()
        );
        
        // Verificar que todas las tablas existen
        $required_tables = array(
            'actas_logs', 'actas_metadata', 'actas_folders', 
            'actas_suspicious_logs', 'actas_analytics', 'actas_user_sessions'
        );
        
        foreach ($required_tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            
            if (!$exists) {
                $verification['tables_ok'] = false;
                $verification['issues'][] = "Tabla faltante: $table_name";
            }
        }
        
        // Verificar integridad de datos
        $actas_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_metadata");
        $folders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
        
        if ($actas_count == 0 && $folders_count == 0) {
            $verification['issues'][] = "Sistema vacío - considera añadir datos de prueba";
        }
        
        // Verificar carpetas jerárquicas
        $parent_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders WHERE parent_id IS NULL");
        $child_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders WHERE parent_id IS NOT NULL");
        
        if ($parent_folders == 0) {
            $verification['folders_ok'] = false;
            $verification['issues'][] = "No hay carpetas padre definidas";
        }
        
        // Verificar archivos físicos
        $upload_dir = wp_upload_dir();
        $actas_dir = $upload_dir['basedir'] . '/actas-pdf';
        
        if (!file_exists($actas_dir) || !is_writable($actas_dir)) {
            $verification['files_ok'] = false;
            $verification['issues'][] = "Directorio de archivos no accesible: $actas_dir";
        }
        
        // Verificar opciones del sistema
        $version = get_option('visor_pdf_crisman_version');
        if (!$version || version_compare($version, '2.0.8', '<')) {
            $verification['issues'][] = "Versión del sistema no actualizada correctamente";
        }
        
        return $verification;
    }
    
    /**
     * Limpiar datos de migración
     */
    public static function cleanup_migration() {
        delete_option('visor_pdf_migration_backup');
        
        return array(
            'success' => true,
            'message' => 'Datos de migración limpiados'
        );
    }
    
    /**
     * Generar reporte de migración
     */
    public static function generate_migration_report() {
        $backup = get_option('visor_pdf_migration_backup', array());
        $current_state = self::check_current_state();
        
        $report = array(
            'migration_date' => isset($backup['timestamp']) ? $backup['timestamp'] : 'No disponible',
            'before' => $backup,
            'after' => $current_state,
            'summary' => array()
        );
        
        // Generar resumen comparativo
        if (isset($backup['tables']['actas_metadata'])) {
            $before_actas = count($backup['tables']['actas_metadata']);
            $after_actas = $current_state['actas'];
            $report['summary']['actas'] = "Antes: $before_actas, Después: $after_actas";
        }
        
        if (isset($backup['tables']['actas_folders'])) {
            $before_folders = count($backup['tables']['actas_folders']);
            $after_folders = $current_state['folders'];
            $report['summary']['folders'] = "Antes: $before_folders, Después: $after_folders";
        }
        
        return $report;
    }
    
    /**
     * Reparar problemas comunes encontrados en la migración
     */
    public static function repair_common_issues() {
        $issues_fixed = array();
        
        // 1. Recrear carpetas faltantes
        global $wpdb;
        $folders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
        
        if ($folders_count == 0) {
            require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-plugin-installer.php';
            Visor_PDF_Plugin_Installer::create_default_folders();
            $issues_fixed[] = 'Carpetas predeterminadas recreadas';
        }
        
        // 2. Reparar estructura de directorios
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        $required_directories = array(
            $base_dir . '/actas-pdf',
            $base_dir . '/actas-pdf/temp',
            $base_dir . '/actas-pdf/cache',
            $base_dir . '/actas-pdf/thumbnails',
            $base_dir . '/actas-pdf/watermarks',
            $base_dir . '/actas-pdf/backups'
        );
        
        foreach ($required_directories as $dir) {
            if (!file_exists($dir)) {
                if (wp_mkdir_p($dir)) {
                    $issues_fixed[] = "Directorio creado: $dir";
                    
                    // Añadir protección
                    file_put_contents($dir . '/.htaccess', "Options -Indexes\nDeny from all");
                    file_put_contents($dir . '/index.php', '<?php // Silence is golden');
                }
            }
        }
        
        // 3. Verificar y reparar opciones del sistema
        $version = get_option('visor_pdf_crisman_version');
        if (!$version) {
            update_option('visor_pdf_crisman_version', VISOR_PDF_CRISMAN_VERSION);
            $issues_fixed[] = 'Versión del sistema actualizada';
        }
        
        // 4. Reparar actas huérfanas (sin carpeta asignada)
        $orphaned_actas = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_metadata 
             WHERE (folder_id IS NULL OR folder_id = 0) AND status = 'active'"
        );
        
        if ($orphaned_actas > 0) {
            // Buscar carpeta "Sin Clasificar" o crearla
            $unclassified_folder = $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}actas_folders WHERE slug = 'sin-clasificar'"
            );
            
            if (!$unclassified_folder) {
                $wpdb->insert(
                    $wpdb->prefix . 'actas_folders',
                    array(
                        'name' => 'Sin Clasificar',
                        'slug' => 'sin-clasificar',
                        'parent_id' => null,
                        'order_index' => 999,
                        'visible_frontend' => 0,
                        'icon' => 'category',
                        'color' => '#dba617',
                        'access_level' => 'private'
                    )
                );
                $unclassified_id = $wpdb->insert_id;
            } else {
                $unclassified_id = $unclassified_folder->id;
            }
            
            // Asignar actas huérfanas
            $wpdb->update(
                $wpdb->prefix . 'actas_metadata',
                array('folder_id' => $unclassified_id),
                array('folder_id' => null, 'status' => 'active')
            );
            
            $wpdb->update(
                $wpdb->prefix . 'actas_metadata',
                array('folder_id' => $unclassified_id),
                array('folder_id' => 0, 'status' => 'active')
            );
            
            $issues_fixed[] = "$orphaned_actas actas huérfanas asignadas a 'Sin Clasificar'";
        }
        
        return array(
            'success' => true,
            'issues_fixed' => $issues_fixed,
            'total_fixes' => count($issues_fixed)
        );
    }
}

// Funciones de conveniencia para usar en templates
if (!function_exists('visor_pdf_run_migration')) {
    function visor_pdf_run_migration() {
        return Visor_PDF_Migration_Helper::run_full_migration();
    }
}

if (!function_exists('visor_pdf_repair_issues')) {
    function visor_pdf_repair_issues() {
        return Visor_PDF_Migration_Helper::repair_common_issues();
    }
}
