<?php
/**
 * Plugin Name: Visor PDF Crisman
 * Plugin URI: https://github.com/cmena92/v2VisorPDF
 * Description: Sistema seguro para cargar, visualizar y controlar acceso a actas PDF con marcas de agua - CORREGIDO
 * Version: 2.0.7
 * Author: Crisman
 * Author URI: https://tu-sitio-web.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: visor-pdf-crisman
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cmena92/v2VisorPDF
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('VISOR_PDF_CRISMAN_VERSION', '2.0.7');
define('VISOR_PDF_CRISMAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VISOR_PDF_CRISMAN_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Clase principal del plugin (Corregida)
 * Actúa como bootstrap y coordinador de módulos
 */
class VisorPDFCrisman {
    
    private static $instance = null;
    private $core;
    private $folders_manager;
    private $mass_upload;
    private $frontend_navigation;
    private $analytics;
    private $updater;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Retrasar la inicialización hasta que WordPress esté completamente cargado
        add_action('init', array($this, 'delayed_init'), 20);
        
        // Hooks que deben ejecutarse temprano
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Hook para actualizaciones de plugin
        add_action('upgrader_process_complete', array($this, 'on_plugin_update'), 10, 2);
        add_action('admin_init', array($this, 'check_version_and_upgrade'));
    }
    
    /**
     * Inicialización retrasada para evitar problemas con conditional tags
     */
    public function delayed_init() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_modules();
        $this->init_upload_directory();
        
        // Verificar y actualizar tablas si es necesario (solo en admin)
        if (is_admin()) {
            $this->check_version_and_upgrade();
        }
    }
    
    /**
     * Inicializar hooks principales
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Agregar enlace de configuración en la lista de plugins
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Shortcodes
        add_shortcode('actas_viewer', array($this, 'shortcode_actas_viewer'));
        add_shortcode('actas_navigator_visual', array($this, 'shortcode_visual_navigator'));
        add_shortcode('actas_hybrid', array($this, 'shortcode_actas_hybrid'));
        
        // AJAX para funcionalidad base (visor)
        add_action('wp_ajax_load_pdf_page', array($this, 'load_pdf_page'));
        add_action('wp_ajax_nopriv_load_pdf_page', array($this, 'load_pdf_page'));
        add_action('wp_ajax_actas_heartbeat', array($this, 'actas_heartbeat'));
        add_action('wp_ajax_nopriv_actas_heartbeat', array($this, 'actas_heartbeat'));
        add_action('wp_ajax_log_suspicious_activity', array($this, 'log_suspicious_activity_endpoint'));
        add_action('wp_ajax_nopriv_log_suspicious_activity', array($this, 'log_suspicious_activity_endpoint'));
        
        // AJAX para visor híbrido
        add_action('wp_ajax_get_folder_actas', array($this, 'ajax_get_folder_actas'));
        add_action('wp_ajax_nopriv_get_folder_actas', array($this, 'ajax_get_folder_actas'));
        
        // AJAX para analytics
        add_action('wp_ajax_get_quick_analytics', array($this, 'ajax_get_quick_analytics'));
        add_action('wp_ajax_clear_analytics_cache', array($this, 'ajax_clear_analytics_cache'));
        
        // AJAX para diagnóstico
        add_action('wp_ajax_visor_diagnostico', array($this, 'ajax_visor_diagnostico'));
        add_action('wp_ajax_visor_diagnostico_navegador', array($this, 'ajax_visor_diagnostico_navegador'));
        
        // AJAX para carpetas de ejemplo
        add_action('wp_ajax_create_sample_folders', array($this, 'ajax_create_sample_folders'));
        add_action('wp_ajax_nopriv_create_sample_folders', array($this, 'ajax_create_sample_folders'));
        
        // AJAX para reorganizar actas por año
        add_action('wp_ajax_reorganizar_actas_por_año', array($this, 'ajax_reorganizar_actas_por_año'));
        add_action('wp_ajax_nopriv_reorganizar_actas_por_año', array($this, 'ajax_reorganizar_actas_por_año'));
        
        // AJAX para migración jerárquica
        add_action('wp_ajax_migrate_to_hierarchy', array($this, 'ajax_migrate_to_hierarchy'));
        add_action('wp_ajax_reset_folders', array($this, 'ajax_reset_folders'));
        
        // AJAX para sistema de actualizaciones
        add_action('wp_ajax_visor_pdf_check_update', array($this, 'ajax_check_update'));
        add_action('wp_ajax_visor_pdf_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_visor_pdf_force_update_check', array($this, 'ajax_force_update_check'));
        
        // Widget en dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        $required_files = array(
            'includes/install-utils.php',
            'includes/security-config.php',
            'includes/class-visor-core.php',
            'includes/class-folders-manager.php',
            'includes/class-mass-upload.php',
            'includes/class-analytics.php'
        );
        
        $optional_files = array(
            'includes/class-frontend-navigation.php'
        );
        
        // Cargar archivos requeridos (críticos)
        foreach ($required_files as $file) {
            $file_path = VISOR_PDF_CRISMAN_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log('Visor PDF Crisman: Archivo REQUERIDO no encontrado: ' . $file_path);
            }
        }
        
        // Cargar archivos opcionales (no críticos)
        foreach ($optional_files as $file) {
            $file_path = VISOR_PDF_CRISMAN_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log('Visor PDF Crisman: Archivo opcional no encontrado: ' . $file_path . ' (algunas funciones estarán limitadas)');
            }
        }
        
        // Cargar sistema de actualizaciones (opcional)
        if (file_exists(VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-plugin-updater.php')) {
            require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-plugin-updater.php';
        }
    }
    
    /**
     * Inicializar módulos
     */
    private function init_modules() {
        // Inicializar módulo core (esencial)
        if (class_exists('Visor_PDF_Core')) {
            $this->core = new Visor_PDF_Core();
        } else {
            error_log('Visor PDF Crisman: Clase Visor_PDF_Core no encontrada');
        }
        
        // Solo cargar módulos de admin en el backend
        if (is_admin()) {
            if (class_exists('Visor_PDF_Folders_Manager')) {
                $this->folders_manager = new Visor_PDF_Folders_Manager();
            }
            if (class_exists('Visor_PDF_Mass_Upload')) {
                $this->mass_upload = new Visor_PDF_Mass_Upload();
            }
            if (class_exists('Visor_PDF_Analytics')) {
                $this->analytics = new Visor_PDF_Analytics();
            }
        }
        
        // Navegación frontend disponible siempre
        if (class_exists('Visor_PDF_Frontend_Navigation')) {
            $this->frontend_navigation = new Visor_PDF_Frontend_Navigation();
        }
        
        // Inicializar sistema de actualizaciones (opcional)
        if (class_exists('Visor_PDF_Plugin_Updater')) {
            $this->updater = new Visor_PDF_Plugin_Updater(__FILE__);
        }
    }
    
    /**
     * Inicializar directorio de uploads
     */
    public function init_upload_directory() {
        if ($this->core) {
            $this->core->init_upload_directory();
        }
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        $this->create_tables();
        $this->setup_default_folders();
        $this->upgrade_analytics_tables();
        flush_rewrite_rules();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear tablas de base de datos
     */
    private function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabla para logs de visualización
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            numero_colegiado varchar(50) NOT NULL,
            acta_filename varchar(255) NOT NULL,
            folder_id int(11),
            page_viewed int(11) NOT NULL,
            viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            INDEX idx_user_acta (user_id, acta_filename),
            INDEX idx_colegiado (numero_colegiado),
            INDEX idx_folder_id (folder_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        dbDelta($sql);
        
        // Tabla para metadatos de actas
        $sql_actas = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_metadata (
            id int(11) NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            original_name varchar(255) NOT NULL,
            title varchar(255),
            description text,
            folder_id int(11),
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            uploaded_by int(11),
            total_pages int(11),
            file_size bigint,
            status enum('active', 'inactive') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY uk_filename (filename),
            INDEX idx_folder_id (folder_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        dbDelta($sql_actas);
        
        // Tabla para carpetas
        $sql_folders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_folders (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL UNIQUE,
            parent_id int(11),
            order_index int(11) DEFAULT 0,
            visible_frontend tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_parent_id (parent_id),
            INDEX idx_slug (slug),
            INDEX idx_order (order_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        dbDelta($sql_folders);
        
        // Tabla para actividades sospechosas
        $sql_suspicious = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}actas_suspicious_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            numero_colegiado varchar(50) NOT NULL,
            acta_id int(11),
            activity_type varchar(100) NOT NULL,
            page_num int(11),
            ip_address varchar(45),
            user_agent text,
            logged_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_activity (user_id, activity_type),
            INDEX idx_acta_activity (acta_id, activity_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        dbDelta($sql_suspicious);
    }
    
    /**
     * Configurar carpetas predefinidas
     */
    private function setup_default_folders() {
        global $wpdb;
        
        $existing_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
        if ($existing_folders > 0) {
            return;
        }
        
        $default_folders = array(
            array('name' => 'Actas de Junta Directiva', 'slug' => 'junta-directiva', 'parent_id' => null, 'order_index' => 1, 'visible_frontend' => 1),
            array('name' => 'Actas de Asamblea', 'slug' => 'asamblea', 'parent_id' => null, 'order_index' => 2, 'visible_frontend' => 1),
            array('name' => 'Sin Clasificar', 'slug' => 'sin-clasificar', 'parent_id' => null, 'order_index' => 999, 'visible_frontend' => 0)
        );
        
        foreach ($default_folders as $folder) {
            $wpdb->insert($wpdb->prefix . 'actas_folders', $folder, array('%s', '%s', '%d', '%d', '%d'));
        }
    }
    
    /**
     * Verificar versión y ejecutar upgrade si es necesario
     */
    public function check_version_and_upgrade() {
        $installed_version = get_option('visor_pdf_crisman_version', '0.0.0');
        
        if (version_compare($installed_version, VISOR_PDF_CRISMAN_VERSION, '<')) {
            // Ejecutar upgrades
            $this->upgrade_analytics_tables();
            
            // Actualizar versión instalada
            update_option('visor_pdf_crisman_version', VISOR_PDF_CRISMAN_VERSION);
        }
    }
    
    /**
     * Hook para cuando el plugin se actualiza
     */
    public function on_plugin_update($upgrader_object, $options) {
        if ($options['type'] === 'plugin' && isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === plugin_basename(__FILE__)) {
                    $this->upgrade_analytics_tables();
                    break;
                }
            }
        }
    }
    
    /**
     * Actualizar tablas para analytics
     */
    private function upgrade_analytics_tables() {
        if (class_exists('Visor_PDF_Analytics')) {
            $analytics = new Visor_PDF_Analytics();
            if (method_exists($analytics, 'upgrade_tables_for_analytics')) {
                $analytics->upgrade_tables_for_analytics();
            }
        }
    }
    
    /**
     * Menú de administración
     */
    public function admin_menu() {
        add_menu_page(
            'Visor PDF Crisman',
            'Visor PDF',
            'manage_options',
            'visor-pdf-crisman',
            array($this, 'admin_page'),
            'dashicons-media-document',
            30
        );
        
        add_submenu_page(
            'visor-pdf-crisman',
            'Subir Actas',
            'Subir Actas',
            'manage_options',
            'visor-pdf-crisman-upload',
            array($this, 'upload_page')
        );
        
        add_submenu_page(
            'visor-pdf-crisman',
            'Logs de Visualización',
            'Logs',
            'manage_options',
            'visor-pdf-crisman-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'visor-pdf-crisman',
            'Analytics',
            'Analytics',
            'manage_options',
            'visor-pdf-crisman-analytics',
            array($this, 'analytics_page')
        );
        
        add_submenu_page(
            'visor-pdf-crisman',
            'Diagnóstico del Sistema',
            'Diagnóstico',
            'manage_options',
            'visor-pdf-crisman-diagnostico',
            array($this, 'diagnostico_page')
        );
        
        add_submenu_page(
            'visor-pdf-crisman',
            'Debug Navegador Visual',
            'Debug Navegador',
            'manage_options',
            'visor-pdf-crisman-debug-navegador',
            array($this, 'debug_navegador_page')
        );
    }
    
    /**
     * Agregar enlaces de configuración y comprobar actualizaciones en la lista de plugins
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=visor-pdf-crisman') . '">' . __('Configuración', 'visor-pdf-crisman') . '</a>';
        $update_check_link = '<a href="#" id="visor-pdf-check-updates" style="color: #0073aa;">' . __('Comprobar actualizaciones', 'visor-pdf-crisman') . '</a>';
        
        array_unshift($links, $settings_link, $update_check_link);
        return $links;
    }
    
    /**
     * Páginas del administrador
     */
    public function admin_page() {
        if (isset($_POST['delete_acta']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_acta')) {
            $this->delete_acta($_POST['acta_id']);
        }
        
        $actas = $this->core->get_all_actas();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-list.php';
    }
    
    public function upload_page() {
        $message = '';
        
        if (isset($_POST['upload_acta']) && wp_verify_nonce($_POST['_wpnonce'], 'upload_acta')) {
            $result = $this->handle_upload();
            $message = $result['message'];
        }
        
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-upload.php';
    }
    
    public function logs_page() {
        $logs = $this->core->get_viewing_logs();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-logs.php';
    }
    
    public function analytics_page() {
        wp_enqueue_style(
            'visor-analytics-css', 
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/css/analytics.css', 
            array(), 
            VISOR_PDF_CRISMAN_VERSION
        );
        
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/admin-analytics.php';
    }
    
    public function diagnostico_page() {
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'diagnostico_integrado.php';
    }
    
    public function debug_navegador_page() {
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'debug-navigator.php';
    }
    
    /**
     * Scripts frontend - CORREGIDO para evitar conflictos de modales
     */
    public function enqueue_frontend_scripts() {
        // Solo cargar en páginas que contengan shortcodes del visor
        global $post;
        
        // Verificar si es necesario cargar scripts
        $load_scripts = false;
        
        if (is_singular() && $post) {
            if (has_shortcode($post->post_content, 'actas_viewer') || 
                has_shortcode($post->post_content, 'actas_hybrid') ||
                has_shortcode($post->post_content, 'actas_navigator_visual')) {
                $load_scripts = true;
            }
        }
        
        // También cargar en admin y si es AJAX
        if (is_admin() || wp_doing_ajax()) {
            $load_scripts = true;
        }
        
        if (!$load_scripts) {
            return;
        }
        
        // jQuery (requerido)
        wp_enqueue_script('jquery');
        
        // SOLO cargar el visor principal (NO los navegadores avanzados)
        wp_enqueue_script(
            'visor-pdf-crisman-js', 
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/visor-pdf.js', 
            array('jquery'), 
            VISOR_PDF_CRISMAN_VERSION, 
            true
        );
        
        // CSS principal
        wp_enqueue_style(
            'visor-pdf-crisman-css', 
            VISOR_PDF_CRISMAN_PLUGIN_URL . 'assets/visor-pdf.css', 
            array(), 
            VISOR_PDF_CRISMAN_VERSION
        );
        
        // Variables AJAX
        wp_localize_script('visor-pdf-crisman-js', 'actas_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('actas_nonce'),
            'debug' => WP_DEBUG
        ));
        
        // CSS adicional para ocultar navegadores conflictivos
        wp_add_inline_style('visor-pdf-crisman-css', '
            /* IMPORTANTE: Ocultar navegadores conflictivos */
            .visual-navigator-container,
            .frontend-navigation-container,
            .advanced-navigator,
            .actas-navigator,
            [id*="visual-navigator"],
            [class*="visual-navigator"]:not(.actas-viewer-hybrid),
            [id*="frontend-nav"],
            [class*="frontend-nav"],
            [id*="advanced-nav"],
            [class*="advanced-nav"],
            .navigator-modal,
            .nav-modal,
            [id*="nav-modal"],
            [class*="nav-modal"] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                z-index: -1 !important;
            }
            
            /* Solo permitir el modal original */
            #actas-modal {
                z-index: 99999 !important;
            }
            
            /* Ocultar otros modales */
            .modal:not(#actas-modal),
            [id*="modal"]:not(#actas-modal) {
                display: none !important;
            }
        ');
        
        // JavaScript inline para limpiar conflictos
        wp_add_inline_script('visor-pdf-crisman-js', '
            jQuery(document).ready(function($) {
                // Limpiar navegadores conflictivos inmediatamente
                setTimeout(function() {
                    $(".visual-navigator-container").remove();
                    $(".frontend-navigation-container").remove(); 
                    $(".advanced-navigator").remove();
                    $("[id*=\"visual-navigator\"]").remove();
                    $("[class*=\"visual-navigator\"]").not(".actas-viewer-hybrid").remove();
                    $(".modal").not("#actas-modal").remove();
                    console.log("🧹 Navegadores conflictivos eliminados");
                }, 100);
            });
        ', 'after');
    }
    
    /**
     * Scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        // Solo cargar en la página de plugins
        if ($hook !== 'plugins.php') {
            return;
        }
        
        // JavaScript inline para manejar el enlace de comprobar actualizaciones
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $("#visor-pdf-check-updates").on("click", function(e) {
                    e.preventDefault();
                    
                    var $link = $(this);
                    var originalText = $link.text();
                    
                    // Cambiar texto mientras procesa
                    $link.text("Comprobando...").css("color", "#999");
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "visor_pdf_force_update_check"
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                if (response.data.reload) {
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1000);
                                }
                            } else {
                                var errorMsg = "Error al verificar actualizaciones";
                                if (response.data && typeof response.data === "string") {
                                    errorMsg = response.data;
                                }
                                alert(errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error AJAX:", status, error);
                            alert("Error de conexión al verificar actualizaciones. Por favor, intente nuevamente.");
                        },
                        complete: function() {
                            // Restaurar texto original
                            $link.text(originalText).css("color", "#0073aa");
                        }
                    });
                });
            });
        ');
    }
    
    /**
     * Shortcodes
     */
    public function shortcode_actas_viewer($atts) {
        $atts = shortcode_atts(array(
            'categoria' => '',
            'limite' => 10
        ), $atts);
        
        $numero_colegiado = $this->core->verify_user_permissions();
        if (!$numero_colegiado) {
            return '<p>Debe iniciar sesión con un número de colegiado válido para ver las actas.</p>';
        }
        
        $actas = $this->core->get_all_actas();
        
        ob_start();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/viewer.php';
        return ob_get_clean();
    }
    
    public function shortcode_visual_navigator($atts) {
        if ($this->frontend_navigation) {
            return $this->frontend_navigation->shortcode_visual_navigator($atts);
        }
        
        return '<div class="actas-error">
            <p>⚠️ Navegador visual no disponible.</p>
            <p><small>Módulo de navegación frontend no se pudo cargar. Use <code>[actas_hybrid]</code> como alternativa.</small></p>
        </div>';
    }
    
    public function shortcode_actas_hybrid($atts) {
        $atts = shortcode_atts(array(
            'carpeta' => '',
            'limite' => 0,
            'mostrar_debug' => 'false'
        ), $atts);
        
        $numero_colegiado = $this->core->verify_user_permissions();
        if (!$numero_colegiado) {
            return '<div class="actas-error">
                <p>🔐 Debe iniciar sesión con un número de colegiado válido para ver las actas.</p>
                <p><a href="' . wp_login_url(get_permalink()) . '">Iniciar Sesión</a></p>
            </div>';
        }
        
        $actas = $this->get_actas_for_hybrid($atts);
        
        ob_start();
        
        $mostrar_debug = ($atts['mostrar_debug'] === 'true');
        
        $template_path = $this->get_hybrid_template_path();
        include $template_path;
        
        return ob_get_clean();
    }
    
    /**
     * AJAX endpoints - ÚNICOS (sin duplicación)
     */
    public function load_pdf_page() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Acceso denegado');
        }
        
        $numero_colegiado = $this->core->verify_user_permissions();
        if (!$numero_colegiado) {
            wp_die('Debe iniciar sesión');
        }
        
        $acta_id = intval($_POST['acta_id']);
        $page_num = intval($_POST['page_num']);
        
        $acta = $this->core->get_acta_by_id($acta_id);
        if (!$acta) {
            wp_die('Acta no encontrada');
        }
        
        $user = wp_get_current_user();
        $this->core->log_viewing($user->ID, $numero_colegiado, $acta->filename, $page_num, $acta->folder_id);
        
        $image_data = $this->core->generate_page_with_watermark($acta->filename, $page_num, $numero_colegiado);
        
        if ($image_data) {
            header('Content-Type: image/png');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $image_data;
        } else {
            wp_die('Error al cargar la página');
        }
        
        wp_die();
    }
    
    public function actas_heartbeat() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce') || !is_user_logged_in()) {
            wp_die('Acceso denegado');
        }
        
        $user = wp_get_current_user();
        update_user_meta($user->ID, 'last_acta_activity', current_time('mysql'));
        
        wp_send_json_success(array(
            'status' => 'active',
            'timestamp' => current_time('mysql')
        ));
    }
    
    public function log_suspicious_activity_endpoint() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce') || !is_user_logged_in()) {
            wp_die('Acceso denegado');
        }
        
        $user = wp_get_current_user();
        $numero_colegiado = $this->core->verify_user_permissions();
        
        if ($numero_colegiado) {
            $acta_id = intval($_POST['acta_id']);
            $activity = sanitize_text_field($_POST['activity']);
            $page_num = intval($_POST['page_num']);
            
            $this->core->log_suspicious_activity($user->ID, $numero_colegiado, $acta_id, $activity, $page_num);
        }
        
        wp_send_json_success(array('logged' => true));
    }
    
    public function ajax_get_folder_actas() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Acceso denegado');
        }
        
        $numero_colegiado = $this->core->verify_user_permissions();
        if (!$numero_colegiado) {
            wp_send_json_error('Usuario no autorizado');
        }
        
        $folder_id = intval($_POST['folder_id']);
        
        // CORRECCIÓN: Si folder_id es 0, mostrar TODAS las actas (no filtrar)
        if ($folder_id == 0) {
            $atts = array(); // Sin filtro de carpeta = todas las actas
        } else {
            $atts = array('carpeta' => $folder_id); // Filtrar por carpeta específica
        }
        
        $actas = $this->get_actas_for_hybrid($atts);
        
        $actas_html = '';
        foreach ($actas as $acta) {
            $actas_html .= $this->render_acta_card($acta);
        }
        
        wp_send_json_success(array(
            'actas_html' => $actas_html,
            'count' => count($actas),
            'folder_id' => $folder_id
        ));
    }
    
    public function ajax_get_quick_analytics() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        $stats = $this->core->get_quick_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_clear_analytics_cache() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'analytics_nonce')) {
            wp_die('Acceso denegado');
        }
        
        if (isset($this->analytics)) {
            $this->analytics->clear_analytics_cache();
            wp_send_json_success(array('message' => 'Cache limpiado exitosamente'));
        } else {
            wp_send_json_error(array('message' => 'Sistema de analytics no disponible'));
        }
    }
    
    public function ajax_visor_diagnostico() {
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'diagnostico_integrado.php';
    }
    
    public function ajax_visor_diagnostico_navegador() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado - Requiere permisos de administrador');
        }
        
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/diagnostico-navegador.php';
        wp_die();
    }
    
    public function ajax_create_sample_folders() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            $table_folders = $wpdb->prefix . 'actas_folders';
            
            $existing_folders = $wpdb->get_var("SELECT COUNT(*) FROM $table_folders");
            
            if ($existing_folders > 0) {
                wp_send_json_error('Ya existen carpetas en el sistema');
            }
            
            $sample_folders = array(
                array('name' => 'Actas de Junta Directiva', 'slug' => 'junta-directiva', 'parent_id' => null, 'order_index' => 1, 'visible_frontend' => 1),
                array('name' => 'Actas de Asamblea', 'slug' => 'asamblea', 'parent_id' => null, 'order_index' => 2, 'visible_frontend' => 1),
                array('name' => 'Archivo Histórico', 'slug' => 'archivo-historico', 'parent_id' => null, 'order_index' => 3, 'visible_frontend' => 1)
            );
            
            $created_folders = array();
            $folder_ids = array();
            
            // Crear carpetas padre primero
            foreach ($sample_folders as $folder_data) {
                $result = $wpdb->insert(
                    $table_folders,
                    $folder_data,
                    array('%s', '%s', '%d', '%d', '%d')
                );
                
                if ($result !== false) {
                    $folder_id = $wpdb->insert_id;
                    $folder_ids[$folder_data['slug']] = $folder_id;
                    $created_folders[] = $folder_data['name'] . ' (ID: ' . $folder_id . ')';
                }
            }
            
            // Crear carpetas hijas para "Actas de Junta Directiva"
            if (isset($folder_ids['junta-directiva'])) {
                $parent_id = $folder_ids['junta-directiva'];
                $year_folders = array(
                    array('name' => '2025', 'slug' => '2025', 'parent_id' => $parent_id, 'order_index' => 1, 'visible_frontend' => 1),
                    array('name' => '2024', 'slug' => '2024', 'parent_id' => $parent_id, 'order_index' => 2, 'visible_frontend' => 1),
                    array('name' => '2016', 'slug' => '2016', 'parent_id' => $parent_id, 'order_index' => 3, 'visible_frontend' => 1)
                );
                
                foreach ($year_folders as $year_data) {
                    $result = $wpdb->insert(
                        $table_folders,
                        $year_data,
                        array('%s', '%s', '%d', '%d', '%d')
                    );
                    
                    if ($result !== false) {
                        $folder_id = $wpdb->insert_id;
                        $created_folders[] = '  └─ ' . $year_data['name'] . ' (ID: ' . $folder_id . ', Hijo de: Actas de Junta Directiva)';
                    }
                }
            }
            
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
     * AJAX: Reorganizar actas por año automáticamente
     */
    public function ajax_reorganizar_actas_por_año() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            $table_folders = $wpdb->prefix . 'actas_folders';
            $table_metadata = $wpdb->prefix . 'actas_metadata';
            
            // Crear mapeo de carpetas por año
            $folders_map = array();
            $folders = $wpdb->get_results("SELECT id, name FROM $table_folders");
            foreach ($folders as $folder) {
                // Detectar años en nombres de carpetas
                if (preg_match('/\\b(20\\d{2})\\b/', $folder->name, $matches)) {
                    $folders_map[$matches[1]] = $folder->id;
                } else if (stripos($folder->name, 'hist') !== false || stripos($folder->name, 'archivo') !== false) {
                    $folders_map['historico'] = $folder->id;
                }
            }
            
            // Obtener todas las actas
            $actas = $wpdb->get_results(
                "SELECT id, title, upload_date, folder_id FROM $table_metadata 
                 WHERE status = 'active'"
            );
            
            $reorganized_count = 0;
            $assignments = array();
            
            foreach ($actas as $acta) {
                $year = null;
                $target_folder_id = null;
                
                // Método 1: Buscar año en el título
                if (preg_match('/\\b(20\\d{2})\\b/', $acta->title, $matches)) {
                    $year = $matches[1];
                    if (isset($folders_map[$year])) {
                        $target_folder_id = $folders_map[$year];
                    }
                }
                
                // Método 2: Usar año de la fecha de subida
                if (!$target_folder_id) {
                    $year = date('Y', strtotime($acta->upload_date));
                    if (isset($folders_map[$year])) {
                        $target_folder_id = $folders_map[$year];
                    } else if ($year < 2020 && isset($folders_map['historico'])) {
                        $target_folder_id = $folders_map['historico'];
                    }
                }
                
                // Actualizar si se encontró una carpeta adecuada y es diferente a la actual
                if ($target_folder_id && $target_folder_id != $acta->folder_id) {
                    $updated = $wpdb->update(
                        $table_metadata,
                        array('folder_id' => $target_folder_id),
                        array('id' => $acta->id),
                        array('%d'),
                        array('%d')
                    );
                    
                    if ($updated !== false) {
                        $reorganized_count++;
                        $folder_name = array_search($target_folder_id, $folders_map);
                        if ($folder_name === false) $folder_name = 'ID:' . $target_folder_id;
                        $assignments[] = "Acta {$acta->id} (£{$year}) → {$folder_name}";
                    }
                }
            }
            
            wp_send_json_success(array(
                'message' => "Se reorganizaron {$reorganized_count} actas",
                'reorganized_count' => $reorganized_count,
                'total_actas' => count($actas),
                'assignments' => $assignments
            ));
            
        } catch (Exception $e) {
            error_log('Error reorganizando actas: ' . $e->getMessage());
            wp_send_json_error('Error al reorganizar actas: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Migrar carpetas existentes a estructura jerárquica
     */
    public function ajax_migrate_to_hierarchy() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            $table_folders = $wpdb->prefix . 'actas_folders';
            
            $steps = array();
            
            // 1. Buscar si ya existe 'Actas de Junta Directiva'
            $junta_directiva = $wpdb->get_row(
                "SELECT * FROM $table_folders WHERE name = 'Actas de Junta Directiva'"
            );
            
            if (!$junta_directiva) {
                // Crear carpeta padre
                $wpdb->insert(
                    $table_folders,
                    array(
                        'name' => 'Actas de Junta Directiva',
                        'slug' => 'junta-directiva',
                        'parent_id' => null,
                        'order_index' => 1,
                        'visible_frontend' => 1
                    ),
                    array('%s', '%s', '%d', '%d', '%d')
                );
                
                $junta_directiva_id = $wpdb->insert_id;
                $steps[] = "Creada carpeta padre: 'Actas de Junta Directiva' (ID: $junta_directiva_id)";
            } else {
                $junta_directiva_id = $junta_directiva->id;
                $steps[] = "Usando carpeta padre existente: 'Actas de Junta Directiva' (ID: $junta_directiva_id)";
            }
            
            // 2. Buscar carpetas que parecen años y moverlas como hijas
            $year_folders = $wpdb->get_results(
                "SELECT * FROM $table_folders WHERE name REGEXP '^20[0-9]{2}$' AND (parent_id IS NULL OR parent_id = 0)"
            );
            
            $moved_count = 0;
            foreach ($year_folders as $year_folder) {
                $wpdb->update(
                    $table_folders,
                    array('parent_id' => $junta_directiva_id),
                    array('id' => $year_folder->id),
                    array('%d'),
                    array('%d')
                );
                
                $steps[] = "Movida carpeta '{$year_folder->name}' como hija de 'Actas de Junta Directiva'";
                $moved_count++;
            }
            
            // 3. Reorganizar actas existentes
            $this->organize_existing_actas();
            $steps[] = "Reorganizadas actas existentes en la nueva estructura";
            
            wp_send_json_success(array(
                'message' => "Migración completada: $moved_count carpetas movidas",
                'steps' => $steps
            ));
            
        } catch (Exception $e) {
            error_log('Error en migración: ' . $e->getMessage());
            wp_send_json_error('Error en migración: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Resetear todas las carpetas
     */
    public function ajax_reset_folders() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            $table_folders = $wpdb->prefix . 'actas_folders';
            $table_metadata = $wpdb->prefix . 'actas_metadata';
            
            // 1. Resetear folder_id de todas las actas
            $wpdb->update(
                $table_metadata,
                array('folder_id' => null),
                array('status' => 'active'),
                array('%s'),
                array('%s')
            );
            
            // 2. Eliminar todas las carpetas
            $deleted_count = $wpdb->query("DELETE FROM $table_folders");
            
            wp_send_json_success(array(
                'message' => "Eliminadas $deleted_count carpetas y reseteadas las asignaciones de actas"
            ));
            
        } catch (Exception $e) {
            error_log('Error reseteando carpetas: ' . $e->getMessage());
            wp_send_json_error('Error reseteando carpetas: ' . $e->getMessage());
        }
    }
    
    /**
     * Widget del dashboard
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'visor_pdf_analytics_widget',
                '<span class="dashicons dashicons-chart-area"></span> Visor PDF - Analytics',
                array($this, 'dashboard_widget_content')
            );
        }
    }
    
    public function dashboard_widget_content() {
        if (!class_exists('Visor_PDF_Analytics')) {
            echo '<p>Sistema de analytics no disponible.</p>';
            return;
        }
        
        $analytics = new Visor_PDF_Analytics();
        $general_stats = $analytics->get_general_stats();
        $realtime_stats = $analytics->get_realtime_stats();
        
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/dashboard-widget.php';
    }
    
    /**
     * AJAX: Verificar actualizaciones
     */
    public function ajax_check_update() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'visor_pdf_check_update')) {
            wp_die('Acceso denegado');
        }
        
        // Limpiar caché para forzar verificación
        delete_transient('visor_pdf_update_info');
        
        if ($this->updater && method_exists($this->updater, 'check_for_update')) {
            // Simular verificación de actualización
            $current_version = VISOR_PDF_CRISMAN_VERSION;
            $remote_info = $this->updater->get_remote_version();
            
            if ($remote_info && version_compare($current_version, $remote_info->version, '<')) {
                wp_send_json_success(array(
                    'update_available' => true,
                    'version' => $remote_info->version,
                    'current_version' => $current_version
                ));
            } else {
                wp_send_json_success(array(
                    'update_available' => false,
                    'version' => $current_version,
                    'current_version' => $current_version
                ));
            }
        } else {
            wp_send_json_error('Sistema de actualizaciones no disponible');
        }
    }
    
    /**
     * AJAX: Estado de actualización
     */
    public function ajax_update_status() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'visor_pdf_update_status')) {
            wp_die('Acceso denegado');
        }
        
        $current_version = VISOR_PDF_CRISMAN_VERSION;
        $response_data = array(
            'current_version' => $current_version,
            'update_available' => false,
            'latest_version' => null,
            'update_url' => admin_url('plugins.php')
        );
        
        if ($this->updater && method_exists($this->updater, 'get_remote_version')) {
            $remote_info = $this->updater->get_remote_version();
            
            if ($remote_info) {
                $response_data['latest_version'] = $remote_info->version;
                $response_data['update_available'] = version_compare($current_version, $remote_info->version, '<');
            }
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX: Forzar verificación de actualizaciones desde plugins
     */
    public function ajax_force_update_check() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acceso denegado');
        }
        
        try {
            // Limpiar transients para forzar verificación
            delete_site_transient('update_plugins');
            delete_transient('visor_pdf_update_info');
            
            // Limpiar caché de plugins
            wp_clean_plugins_cache();
            
            // Verificar si hay actualizaciones disponibles
            $has_update = false;
            $update_message = 'No hay actualizaciones disponibles';
            
            if ($this->updater && method_exists($this->updater, 'get_remote_version')) {
                $current_version = VISOR_PDF_CRISMAN_VERSION;
                $remote_info = $this->updater->get_remote_version();
                
                if ($remote_info === false) {
                    wp_send_json_success(array(
                        'has_update' => false,
                        'message' => 'No se pudo conectar con el servidor de actualizaciones. Por favor, intente más tarde.',
                        'reload' => false
                    ));
                    return;
                }
                
                if ($remote_info && isset($remote_info->version)) {
                    if (version_compare($current_version, $remote_info->version, '<')) {
                        $has_update = true;
                        $update_message = "¡Actualización disponible! Versión {$remote_info->version}";
                    } else {
                        $update_message = "Está usando la última versión ({$current_version})";
                    }
                } else {
                    $update_message = 'No se pudo obtener información de versión del servidor';
                }
            } else {
                $update_message = 'El sistema de actualizaciones no está disponible';
            }
            
            wp_send_json_success(array(
                'has_update' => $has_update,
                'message' => $update_message,
                'reload' => $has_update // Solo recargar si hay actualización
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error al verificar actualizaciones: ' . $e->getMessage());
        }
    }
    
    /**
     * Métodos de utilidad
     */
    private function get_actas_for_hybrid($atts) {
        global $wpdb;
        
        $where_conditions = array("a.status = 'active'");
        $query_params = array();
        
        // CORRECCIÓN: Solo filtrar por carpeta si se especifica y es diferente de 0
        if (!empty($atts['carpeta']) && is_numeric($atts['carpeta']) && $atts['carpeta'] != 0) {
            $where_conditions[] = "a.folder_id = %d";
            $query_params[] = intval($atts['carpeta']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT 
                a.*,
                f.name as folder_name,
                f.slug as folder_slug
            FROM {$wpdb->prefix}actas_metadata a
            LEFT JOIN {$wpdb->prefix}actas_folders f ON a.folder_id = f.id
            WHERE {$where_clause}
            ORDER BY a.upload_date DESC
        ";
        
        if (!empty($atts['limite']) && is_numeric($atts['limite'])) {
            $sql .= " LIMIT %d";
            $query_params[] = intval($atts['limite']);
        }
        
        if (!empty($query_params)) {
            $actas = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        } else {
            $actas = $wpdb->get_results($sql);
        }
        
        return $actas ?: array();
    }
    
    private function get_hybrid_template_path() {
        $theme_template = get_template_directory() . '/visor-pdf/viewer-hybrid.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        $plugin_template = VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/viewer-hybrid.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/viewer.php';
    }
    
    private function render_acta_card($acta) {
        ob_start();
        include VISOR_PDF_CRISMAN_PLUGIN_DIR . 'templates/acta-table-row.php';
        return ob_get_clean();
    }
    
    private function organize_existing_actas() {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'actas_folders';
        $table_metadata = $wpdb->prefix . 'actas_metadata';
        
        // Crear mapeo de carpetas por año (solo carpetas hijas)
        $folders_map = array();
        $folders = $wpdb->get_results("SELECT id, name, parent_id FROM $table_folders");
        foreach ($folders as $folder) {
            // Solo mapear carpetas que NO sean padre (que tengan parent_id)
            if ($folder->parent_id !== null && $folder->parent_id != 0) {
                // Detectar años en nombres de carpetas
                if (preg_match('/\\b(20\\d{2})\\b/', $folder->name, $matches)) {
                    $folders_map[$matches[1]] = $folder->id;
                }
            } else {
                // Para carpetas padre sin hijos, mapear normalmente
                if (stripos($folder->name, 'hist') !== false || stripos($folder->name, 'archivo') !== false) {
                    $folders_map['historico'] = $folder->id;
                } else if (stripos($folder->name, 'asamblea') !== false) {
                    $folders_map['asamblea'] = $folder->id;
                }
            }
        }
        
        $actas = $wpdb->get_results(
            "SELECT id, title, upload_date FROM $table_metadata 
             WHERE (folder_id IS NULL OR folder_id = 0) AND status = 'active'"
        );
        
        foreach ($actas as $acta) {
            $year = null;
            
            // Método 1: Buscar año en el título
            if (preg_match('/\\b(20\\d{2})\\b/', $acta->title, $matches)) {
                $year = $matches[1];
            } else {
                // Método 2: Usar año de la fecha de subida
                $year = date('Y', strtotime($acta->upload_date));
            }
            
            $folder_id = null;
            
            // Buscar carpeta por año (carpetas hijas)
            if (isset($folders_map[$year])) {
                $folder_id = $folders_map[$year];
            } 
            // Si no encuentra carpeta por año, intentar asignar a carpetas temáticas
            else if ($year < 2020 && isset($folders_map['historico'])) {
                $folder_id = $folders_map['historico'];
            } 
            else if (isset($folders_map['asamblea']) && stripos($acta->title, 'asamblea') !== false) {
                $folder_id = $folders_map['asamblea'];
            }
            
            // Asignar acta a carpeta encontrada
            if ($folder_id) {
                $wpdb->update(
                    $table_metadata,
                    array('folder_id' => $folder_id),
                    array('id' => $acta->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }
    
    private function handle_upload() {
        if (!isset($_FILES['acta_pdf']) || $_FILES['acta_pdf']['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'Error al subir el archivo.');
        }
        
        $file = $_FILES['acta_pdf'];
        $title = sanitize_text_field($_POST['acta_title']);
        $description = sanitize_textarea_field($_POST['acta_description']);
        
        if ($file['type'] !== 'application/pdf') {
            return array('success' => false, 'message' => 'Solo se permiten archivos PDF.');
        }
        
        $filename = uniqid() . '_' . sanitize_file_name($file['name']);
        $upload_dir = wp_upload_dir()['basedir'] . '/actas-pdf/';
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $total_pages = $this->core->get_pdf_page_count($filepath);
            
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'actas_metadata',
                array(
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'title' => $title,
                    'description' => $description,
                    'uploaded_by' => get_current_user_id(),
                    'total_pages' => $total_pages,
                    'file_size' => $file['size']
                )
            );
            
            return array('success' => true, 'message' => 'Acta subida exitosamente.');
        }
        
        return array('success' => false, 'message' => 'Error al guardar el archivo.');
    }
    
    private function delete_acta($acta_id) {
        global $wpdb;
        
        $acta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}actas_metadata WHERE id = %d",
            $acta_id
        ));
        
        if ($acta) {
            $upload_dir = wp_upload_dir()['basedir'] . '/actas-pdf/';
            $filepath = $upload_dir . $acta->filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            $wpdb->update(
                $wpdb->prefix . 'actas_metadata',
                array('status' => 'inactive'),
                array('id' => $acta_id)
            );
        }
    }
}

// Inicializar plugin de forma segura
add_action('plugins_loaded', function() {
    VisorPDFCrisman::get_instance();
});

// Campo personalizado para número de colegiado
add_action('show_user_profile', 'visor_pdf_add_numero_colegiado_field');
add_action('edit_user_profile', 'visor_pdf_add_numero_colegiado_field');
add_action('personal_options_update', 'visor_pdf_save_numero_colegiado_field');
add_action('edit_user_profile_update', 'visor_pdf_save_numero_colegiado_field');

function visor_pdf_add_numero_colegiado_field($user) {
    $numero_colegiado = get_user_meta($user->ID, 'numero_colegiado', true);
    ?>
    <h3>Información de Colegiado</h3>
    <table class="form-table">
        <tr>
            <th><label for="numero_colegiado">Número de Colegiado</label></th>
            <td>
                <input type="text" name="numero_colegiado" id="numero_colegiado" 
                       value="<?php echo esc_attr($numero_colegiado); ?>" class="regular-text" />
                <p class="description">Ingrese el número de colegiado del usuario.</p>
            </td>
        </tr>
    </table>
    <?php
}

function visor_pdf_save_numero_colegiado_field($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'numero_colegiado', sanitize_text_field($_POST['numero_colegiado']));
    }
}
