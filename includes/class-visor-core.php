<?php
/**
 * Clase Base del Visor PDF Crisman
 * Contiene funcionalidades comunes para todas las fases
 */

if (!defined('ABSPATH')) {
    exit;
}

class Visor_PDF_Core {
    
    protected $upload_dir;
    protected $table_logs;
    protected $table_metadata;
    protected $table_folders;
    protected $table_suspicious;
    
    public function __construct() {
        global $wpdb;
        
        $this->upload_dir = wp_upload_dir()['basedir'] . '/actas-pdf/';
        $this->table_logs = $wpdb->prefix . 'actas_logs';
        $this->table_metadata = $wpdb->prefix . 'actas_metadata';
        $this->table_folders = $wpdb->prefix . 'actas_folders';
        $this->table_suspicious = $wpdb->prefix . 'actas_suspicious_logs';
    }
    
    /**
     * Inicializar directorio de uploads
     */
    public function init_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            file_put_contents($this->upload_dir . '.htaccess', "deny from all");
        }
    }
    
    /**
     * Obtener todas las actas activas
     */
    public function get_all_actas() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_metadata} 
             WHERE status = 'active' 
             ORDER BY upload_date DESC"
        );
    }
    
    /**
     * Obtener acta por ID
     */
    public function get_acta_by_id($acta_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_metadata} WHERE id = %d AND status = 'active'",
                $acta_id
            )
        );
    }
    
    /**
     * Registrar actividad sospechosa
     */
    public function log_suspicious_activity($user_id, $numero_colegiado, $acta_id, $activity_type, $page_num = null) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->table_suspicious,
            array(
                'user_id' => $user_id,
                'numero_colegiado' => $numero_colegiado,
                'acta_id' => $acta_id,
                'activity_type' => $activity_type,
                'page_num' => $page_num,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            )
        );
    }
    
    /**
     * Generar página PDF con marca de agua
     */
    public function generate_page_with_watermark($filename, $page_num, $numero_colegiado) {
        $filepath = $this->upload_dir . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        // Verificar que Imagick esté disponible
        if (!extension_loaded('imagick')) {
            error_log('Visor PDF: Imagick extension not available');
            return false;
        }
        
        try {
            $imagick = new Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($filepath . '[' . ($page_num - 1) . ']');
            $imagick->setImageCompression(Imagick::COMPRESSION_LOSSLESSJPEG);
            $imagick->setImageCompressionQuality(90);
            $imagick->setImageFormat('png');
            
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            
            $canvas = new Imagick();
            $canvas->newImage($width, $height, 'white');
            $canvas->setImageFormat('png');
            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);
            
            // Agregar marca de agua
            $draw = new ImagickDraw();
            $draw->setFillColor('rgba(255, 0, 0, 0.3)');
            $draw->setFontSize(min($width, $height) * 0.03);
            $draw->setStrokeColor('rgba(255, 0, 0, 0.1)');
            $draw->setStrokeWidth(1);
            
            $watermark_text = "Colegiado: " . $numero_colegiado . "\n" . date('Y-m-d H:i:s');
            $draw->setGravity(Imagick::GRAVITY_CENTER);
            $canvas->annotateImage($draw, 0, 0, 45, $watermark_text);
            
            // Marcas de agua repetidas
            $draw->setFontSize(min($width, $height) * 0.02);
            $draw->setGravity(Imagick::GRAVITY_NORTHWEST);
            
            $step_x = $width / 5;
            $step_y = $height / 8;
            
            for ($x = 0; $x < $width; $x += $step_x) {
                for ($y = 0; $y < $height; $y += $step_y) {
                    $canvas->annotateImage($draw, $x, $y, 45, $numero_colegiado);
                }
            }
            
            $imagick->clear();
            $imagick->destroy();
            
            return $canvas->getImageBlob();
            
        } catch (Exception $e) {
            error_log('Error generating PDF page: ' . $e->getMessage());
            return false;
        }
    }
    

    
    /**
     * Verificar permisos de usuario
     */
    public function verify_user_permissions() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $numero_colegiado = get_user_meta($user->ID, 'numero_colegiado', true);
        
        if (empty($numero_colegiado)) {
            // Compatibilidad con ACF si existe
            if (function_exists('get_field')) {
                $numero_colegiado = get_field('user_numcolegiado', 'user_' . $user->ID);
            }
        }
        
        return !empty($numero_colegiado) ? $numero_colegiado : false;
    }
    
    /**
     * Obtener logs de visualización
     */
    public function get_viewing_logs($limit = 100) {
        global $wpdb;
        
        // Verificar si la columna folder_id existe en la tabla logs
        $folder_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_logs} LIKE 'folder_id'");
        
        if (!empty($folder_column_exists)) {
            // Consulta completa con folder_id
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, u.display_name, a.title as acta_title, f.name as folder_name
                     FROM {$this->table_logs} l
                     LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                     LEFT JOIN {$this->table_metadata} a ON l.acta_filename = a.filename
                     LEFT JOIN {$this->table_folders} f ON l.folder_id = f.id
                     ORDER BY l.viewed_at DESC
                     LIMIT %d",
                    $limit
                )
            );
        } else {
            // Consulta sin folder_id para compatibilidad
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, u.display_name, a.title as acta_title
                     FROM {$this->table_logs} l
                     LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                     LEFT JOIN {$this->table_metadata} a ON l.acta_filename = a.filename
                     ORDER BY l.viewed_at DESC
                     LIMIT %d",
                    $limit
                )
            );
        }
    }
    
    /**
     * Obtener número de páginas de un PDF
     */
    public function get_pdf_page_count($filepath) {
        if (!file_exists($filepath)) {
            return 0;
        }
        
        // Método 1: Usar Imagick si está disponible
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->readImage($filepath);
                $pages = $imagick->getNumberImages();
                $imagick->destroy();
                return $pages;
            } catch (Exception $e) {
                // Continuar con método alternativo
            }
        }
        
        // Método 2: Leer contenido del PDF y buscar patrones
        $content = file_get_contents($filepath);
        if (!$content) {
            return 1;
        }
        
        // Buscar patrones de páginas
        preg_match_all('/\/Page\W/', $content, $matches);
        $count1 = count($matches[0]);
        
        preg_match_all('/\/Count\s+(\d+)/', $content, $matches2);
        $count2 = !empty($matches2[1]) ? max($matches2[1]) : 0;
        
        return max($count1, $count2, 1);
    }
    
    /**
     * Verificar requisitos del sistema
     */
    public function check_system_requirements() {
        $requirements = array(
            'imagick' => array(
                'available' => extension_loaded('imagick'),
                'required' => true,
                'description' => 'Extensión Imagick para procesar PDFs'
            ),
            'upload_dir' => array(
                'available' => is_writable($this->upload_dir) || wp_mkdir_p($this->upload_dir),
                'required' => true,
                'description' => 'Directorio de subida escribible'
            ),
            'memory_limit' => array(
                'available' => (int) ini_get('memory_limit') >= 256 || ini_get('memory_limit') == -1,
                'required' => false,
                'description' => 'Límite de memoria PHP (recomendado: 256MB+)'
            )
        );
        
        return $requirements;
    }
    
    /**
     * Obtener jerarquía de carpetas
     */
    public function get_folders_hierarchy($parent_id = null) {
        global $wpdb;
        
        $where = ($parent_id === null) ? 'parent_id IS NULL' : $wpdb->prepare('parent_id = %d', $parent_id);
        
        // Verificar si la columna 'status' existe en la tabla folders
        $status_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_folders} LIKE 'status'");
        
        if (!empty($status_column_exists)) {
            // Usar columna status si existe
            $folders = $wpdb->get_results(
                "SELECT * FROM {$this->table_folders} 
                 WHERE {$where} AND status = 'active'
                 ORDER BY order_index ASC, name ASC"
            );
        } else {
            // No usar columna status si no existe
            $folders = $wpdb->get_results(
                "SELECT * FROM {$this->table_folders} 
                 WHERE {$where}
                 ORDER BY order_index ASC, name ASC"
            );
        }
        
        foreach ($folders as &$folder) {
            $folder->children = $this->get_folders_hierarchy($folder->id);
            
            // Agregar contador de actas
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
     * Métodos extendidos para analytics (FASE 5A)
     */
    
    /**
     * Log de visualización extendido con trigger para analytics
     */
    public function log_viewing($user_id, $numero_colegiado, $filename, $page_num, $folder_id = null) {
        global $wpdb;
        
        // Verificar si la columna folder_id existe
        $folder_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_logs} LIKE 'folder_id'");
        
        $log_data = array(
            'user_id' => $user_id,
            'numero_colegiado' => $numero_colegiado,
            'acta_filename' => $filename,
            'page_viewed' => $page_num,
            'viewed_at' => current_time('mysql'),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        );
        
        // Solo agregar folder_id si la columna existe
        if (!empty($folder_column_exists) && $folder_id !== null) {
            $log_data['folder_id'] = $folder_id;
        }
        
        $log_id = $wpdb->insert($this->table_logs, $log_data);
        
        if ($log_id) {
            // Trigger hook para analytics
            do_action('actas_log_created', $log_id, $user_id, $filename, $page_num);
        }
        
        return $log_id;
    }
    
    /**
     * Obtener IP del usuario de forma segura
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Obtener estadísticas básicas rápidas (para widgets)
     */
    public function get_quick_stats() {
        global $wpdb;
        
        return array(
            'total_actas' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_metadata} WHERE status = 'active'"
            ),
            'total_views' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_logs}"
            ),
            'views_today' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_logs} WHERE DATE(viewed_at) = CURDATE()"
            )
        );
    }
}
