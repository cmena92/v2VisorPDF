<?php
/**
 * Clase para gestión de analytics y estadísticas avanzadas
 * FASE 5A: Base de Analytics
 * 
 * @package VisorPDFCrisman
 * @version 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Visor_PDF_Analytics extends Visor_PDF_Core {
    
    private $cache_expiry = 300; // 5 minutos de cache
    private $cache_prefix = 'visor_analytics_';
    
    public function __construct() {
        parent::__construct();
        $this->init_analytics_hooks();
    }
    
    /**
     * Inicializar hooks específicos de analytics
     */
    private function init_analytics_hooks() {
        // Hook para tracking extendido de visualizaciones
        add_action('actas_page_loaded', array($this, 'track_extended_view'), 10, 3);
        
        // Hook para limpiar cache cuando hay nuevos datos
        add_action('actas_log_created', array($this, 'clear_analytics_cache'));
        
        // AJAX endpoints para analytics
        add_action('wp_ajax_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_get_realtime_stats', array($this, 'ajax_get_realtime_stats'));
    }
    
    /**
     * Tracking extendido de visualizaciones con métricas adicionales
     */
    public function track_extended_view($acta_id, $page_num, $user_id) {
        global $wpdb;
        
        $user = get_user_by('ID', $user_id);
        $numero_colegiado = get_user_meta($user_id, 'numero_colegiado', true);
        
        if (!$numero_colegiado || !$user) {
            return;
        }
        
        // Obtener información adicional para analytics
        $device_info = $this->get_device_info();
        $session_info = $this->get_session_info($user_id);
        
        // Obtener acta info
        $acta = $wpdb->get_row($wpdb->prepare(
            "SELECT filename, folder_id FROM {$wpdb->prefix}actas_metadata WHERE id = %d",
            $acta_id
        ));
        
        if (!$acta) {
            return;
        }
        
        // Insertar log extendido
        $wpdb->insert(
            $wpdb->prefix . 'actas_logs',
            array(
                'user_id' => $user_id,
                'numero_colegiado' => $numero_colegiado,
                'acta_filename' => $acta->filename,
                'acta_id' => $acta_id,
                'folder_id' => $acta->folder_id,
                'page_viewed' => $page_num,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'device_type' => $device_info['type'],
                'browser' => $device_info['browser'],
                'session_duration' => $session_info['duration'],
                'referrer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
                'viewed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        // Trigger hook para otros plugins
        do_action('actas_analytics_tracked', $acta_id, $user_id, $page_num);
        
        // Limpiar cache de estadísticas
        $this->clear_analytics_cache();
    }
    
    /**
     * Obtener información del dispositivo y navegador
     */
    private function get_device_info() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $device_type = 'desktop';
        $browser = 'unknown';
        
        // Detectar tipo de dispositivo
        if (preg_match('/mobile|android|iphone|ipad/i', $user_agent)) {
            $device_type = 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
            $device_type = 'tablet';
        }
        
        // Detectar navegador
        if (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $browser = 'edge';
        }
        
        return array(
            'type' => $device_type,
            'browser' => $browser
        );
    }
    
    /**
     * Obtener información de la sesión
     */
    private function get_session_info($user_id) {
        $last_activity = get_user_meta($user_id, 'last_acta_activity', true);
        $session_start = get_user_meta($user_id, 'acta_session_start', true);
        
        if (!$session_start) {
            update_user_meta($user_id, 'acta_session_start', current_time('mysql'));
            $duration = 0;
        } else {
            $start_time = strtotime($session_start);
            $duration = time() - $start_time;
        }
        
        return array(
            'duration' => $duration,
            'last_activity' => $last_activity
        );
    }
    
    /**
     * Obtener IP del cliente de forma segura
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Obtener estadísticas generales del sistema
     */
    public function get_general_stats() {
        $cache_key = $this->cache_prefix . 'general_stats';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $stats = array();
        
        // Total de actas
        $stats['total_actas'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_metadata WHERE status = 'active'"
        );
        
        // Total de visualizaciones
        $stats['total_views'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_logs"
        );
        
        // Usuarios únicos
        $stats['unique_users'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}actas_logs"
        );
        
        // Visualizaciones hoy
        $stats['views_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_logs 
             WHERE DATE(viewed_at) = CURDATE()"
        );
        
        // Visualizaciones esta semana
        $stats['views_this_week'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_logs 
             WHERE YEARWEEK(viewed_at) = YEARWEEK(NOW())"
        );
        
        // Visualizaciones este mes
        $stats['views_this_month'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_logs 
             WHERE YEAR(viewed_at) = YEAR(NOW()) AND MONTH(viewed_at) = MONTH(NOW())"
        );
        
        // Actas más vistas (top 5)
        $stats['top_actas'] = $wpdb->get_results(
            "SELECT am.title, am.filename, COUNT(al.id) as view_count
             FROM {$wpdb->prefix}actas_logs al
             JOIN {$wpdb->prefix}actas_metadata am ON al.acta_filename = am.filename
             WHERE am.status = 'active'
             GROUP BY al.acta_filename
             ORDER BY view_count DESC
             LIMIT 5"
        );
        
        // Usuarios más activos (top 5)
        $stats['top_users'] = $wpdb->get_results(
            "SELECT u.display_name, al.numero_colegiado, COUNT(al.id) as view_count
             FROM {$wpdb->prefix}actas_logs al
             JOIN {$wpdb->prefix}users u ON al.user_id = u.ID
             GROUP BY al.user_id
             ORDER BY view_count DESC
             LIMIT 5"
        );
        
        // Distribución por dispositivos (verificar si la columna existe primero)
        $device_column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$wpdb->prefix}actas_logs LIKE 'device_type'"
        );
        
        if (!empty($device_column_exists)) {
            $stats['device_distribution'] = $wpdb->get_results(
                "SELECT device_type, COUNT(*) as count
                 FROM {$wpdb->prefix}actas_logs 
                 WHERE device_type IS NOT NULL
                 GROUP BY device_type"
            );
        } else {
            // Si no existe la columna, devolver array vacío
            $stats['device_distribution'] = array();
            
            // Intentar actualizar la tabla
            $this->upgrade_tables_for_analytics();
        }
        
        // Cache por 5 minutos
        set_transient($cache_key, $stats, $this->cache_expiry);
        
        return $stats;
    }
    
    /**
     * Obtener estadísticas en tiempo real
     */
    public function get_realtime_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Usuarios activos en los últimos 30 minutos
        $stats['active_users'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}actas_logs 
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
        
        // Visualizaciones en la última hora
        $stats['views_last_hour'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_logs 
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        // Actividades sospechosas hoy
        $stats['suspicious_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}actas_suspicious_logs 
             WHERE DATE(logged_at) = CURDATE()"
        );
        
        // Última actividad
        $stats['last_activity'] = $wpdb->get_row(
            "SELECT al.viewed_at, u.display_name, am.title
             FROM {$wpdb->prefix}actas_logs al
             JOIN {$wpdb->prefix}users u ON al.user_id = u.ID
             JOIN {$wpdb->prefix}actas_metadata am ON al.acta_filename = am.filename
             ORDER BY al.viewed_at DESC
             LIMIT 1"
        );
        
        return $stats;
    }
    
    /**
     * Obtener estadísticas por rango de fechas
     */
    public function get_stats_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $cache_key = $this->cache_prefix . 'date_range_' . md5($start_date . $end_date);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $stats = array();
        
        // Visualizaciones por día en el rango
        $stats['daily_views'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(viewed_at) as date, COUNT(*) as views
             FROM {$wpdb->prefix}actas_logs
             WHERE DATE(viewed_at) BETWEEN %s AND %s
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        // Usuarios únicos por día
        $stats['daily_unique_users'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(viewed_at) as date, COUNT(DISTINCT user_id) as unique_users
             FROM {$wpdb->prefix}actas_logs
             WHERE DATE(viewed_at) BETWEEN %s AND %s
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        // Cache por 10 minutos para rangos de fechas
        set_transient($cache_key, $stats, 600);
        
        return $stats;
    }
    
    /**
     * Limpiar cache de analytics
     */
    public function clear_analytics_cache() {
        global $wpdb;
        
        // Obtener todas las transients de analytics
        $transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->prefix}options 
             WHERE option_name LIKE '_transient_{$this->cache_prefix}%'"
        );
        
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient->option_name);
            delete_transient($key);
        }
    }
    
    /**
     * Endpoint AJAX para obtener datos de analytics
     */
    public function ajax_get_analytics_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'analytics_nonce')) {
            wp_die('Acceso denegado');
        }
        
        $type = sanitize_text_field($_POST['type']);
        $data = array();
        
        switch ($type) {
            case 'general':
                $data = $this->get_general_stats();
                break;
                
            case 'realtime':
                $data = $this->get_realtime_stats();
                break;
                
            case 'date_range':
                $start_date = sanitize_text_field($_POST['start_date']);
                $end_date = sanitize_text_field($_POST['end_date']);
                $data = $this->get_stats_by_date_range($start_date, $end_date);
                break;
                
            default:
                wp_die('Tipo de datos no válido');
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Endpoint AJAX para estadísticas en tiempo real
     */
    public function ajax_get_realtime_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        $stats = $this->get_realtime_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * Verificar y crear campos adicionales en tablas
     */
    public function upgrade_tables_for_analytics() {
        global $wpdb;
        
        // Verificar si las columnas ya existen
        $columns = $wpdb->get_results(
            "SHOW COLUMNS FROM {$wpdb->prefix}actas_logs LIKE 'device_type'"
        );
        
        if (empty($columns)) {
            // Agregar nuevas columnas para analytics extendidos
            $result1 = $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}actas_logs 
                 ADD COLUMN acta_id INT(11) AFTER acta_filename,
                 ADD COLUMN device_type VARCHAR(20) AFTER user_agent,
                 ADD COLUMN browser VARCHAR(50) AFTER device_type,
                 ADD COLUMN session_duration INT(11) AFTER browser,
                 ADD COLUMN referrer VARCHAR(255) AFTER session_duration"
            );
            
            if ($result1 !== false) {
                // Agregar índices para mejor performance (solo si se agregaron las columnas)
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}actas_logs 
                     ADD INDEX idx_acta_id (acta_id),
                     ADD INDEX idx_device_type (device_type),
                     ADD INDEX idx_viewed_at (viewed_at)"
                );
            }
            
            // Log para debug
            if (defined('WP_DEBUG') && WP_DEBUG && $result1 === false) {
                error_log('Visor PDF Analytics - Error al agregar columnas: ' . $wpdb->last_error);
            }
        }
        
        return true;
    }
}
