<?php
/**
 * Archivo: includes/security-config.php
 * Configuraciones adicionales de seguridad para el plugin
 */

if (!defined('ABSPATH')) exit;

class VisorPDFSecurityConfig {
    
    public function __construct() {
        add_action('init', array($this, 'init_security'));
        add_action('wp_head', array($this, 'add_security_headers'));
        add_filter('upload_mimes', array($this, 'restrict_upload_mimes'));
        add_action('wp_ajax_validate_user_access', array($this, 'validate_user_access'));
        add_action('wp_ajax_nopriv_validate_user_access', array($this, 'validate_user_access'));
    }
    
    public function init_security() {
        // Crear archivo .htaccess adicional en uploads si no existe
        $this->secure_upload_directory();
        
        // Configurar headers de seguridad
        $this->set_security_headers();
        
        // Limpiar archivos temporales antiguos
        $this->cleanup_temp_files();
    }
    
    private function secure_upload_directory() {
        $upload_dir = wp_upload_dir()['basedir'] . '/actas-pdf/';
        $htaccess_file = $upload_dir . '.htaccess';
        
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Visor PDF Crisman - Protección de archivos\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "# Prevenir ejecución de PHP\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "    deny from all\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "# Prevenir acceso directo a PDFs\n";
            $htaccess_content .= "<Files *.pdf>\n";
            $htaccess_content .= "    deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Crear archivo index.php vacío para prevenir listado de directorio
        $index_file = $upload_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
    private function set_security_headers() {
        if (!headers_sent()) {
            // Solo aplicar en páginas con el visor de actas
            if (is_page() && has_shortcode(get_post()->post_content, 'actas_viewer')) {
                header('X-Frame-Options: SAMEORIGIN');
                header('X-Content-Type-Options: nosniff');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            }
        }
    }
    
    public function add_security_headers() {
        // Solo en páginas con el shortcode de actas
        global $post;
        if (is_object($post) && has_shortcode($post->post_content, 'actas_viewer')) {
            ?>
            <script>
            // Protección adicional contra DevTools
            (function() {
                'use strict';
                
                // Detectar DevTools avanzado
                let devtools = false;
                const threshold = 160;
                
                setInterval(function() {
                    const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                    const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                    
                    if (!(heightThreshold && widthThreshold) &&
                        ((window.Firebug && window.Firebug.chrome && window.Firebug.chrome.isInitialized) || widthThreshold || heightThreshold)) {
                        if (!devtools) {
                            devtools = true;
                            console.log('%cAtención: Herramientas de desarrollador detectadas', 'color: red; font-size: 20px; font-weight: bold;');
                            
                            // Registrar actividad sospechosa
                            if (typeof jQuery !== 'undefined' && typeof actas_ajax !== 'undefined') {
                                jQuery.ajax({
                                    url: actas_ajax.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'log_suspicious_activity',
                                        acta_id: 0,
                                        activity: 'devtools_detected',
                                        nonce: actas_ajax.nonce
                                    }
                                });
                            }
                        }
                    } else {
                        devtools = false;
                    }
                }, 500);
                
                // Deshabilitar atajos de teclado peligrosos
                document.addEventListener('keydown', function(e) {
                    // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, Ctrl+S, Ctrl+A, Ctrl+P
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) ||
                        (e.ctrlKey && (e.keyCode === 85 || e.keyCode === 83 || e.keyCode === 65 || e.keyCode === 80))) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });
                
                // Protección contra selección de texto
                document.addEventListener('selectstart', function(e) {
                    if (e.target.closest('.pdf-viewer-container')) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Deshabilitar clic derecho en el visor
                document.addEventListener('contextmenu', function(e) {
                    if (e.target.closest('.pdf-viewer-container')) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Protección contra drag and drop
                document.addEventListener('dragstart', function(e) {
                    if (e.target.closest('.pdf-viewer-container')) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Ofuscar métodos de consola
                const noop = function() {};
                if (window.console) {
                    console.log = noop;
                    console.warn = noop;
                    console.error = noop;
                    console.info = noop;
                    console.debug = noop;
                    console.clear = noop;
                }
                
            })();
            </script>
            
            <style>
            /* Protección adicional CSS */
            .pdf-viewer-container {
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                -khtml-user-select: none !important;
                -moz-user-select: none !important;
                -ms-user-select: none !important;
                user-select: none !important;
                -webkit-user-drag: none !important;
                -khtml-user-drag: none !important;
                -moz-user-drag: none !important;
                -o-user-drag: none !important;
                user-drag: none !important;
            }
            
            .pdf-viewer-container * {
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                -khtml-user-select: none !important;
                -moz-user-select: none !important;
                -ms-user-select: none !important;
                user-select: none !important;
                -webkit-user-drag: none !important;
                -khtml-user-drag: none !important;
                -moz-user-drag: none !important;
                -o-user-drag: none !important;
                user-drag: none !important;
            }
            
            .pdf-viewer-container button,
            .pdf-viewer-container input,
            .pdf-page-display {
                pointer-events: auto !important;
            }
            
            /* Prevenir impresión */
            @media print {
                .pdf-viewer-container {
                    display: none !important;
                }
                body::after {
                    content: "La impresión de este contenido no está permitida";
                    display: block;
                    text-align: center;
                    font-size: 24px;
                    margin-top: 50px;
                }
            }
            </style>
            <?php
        }
    }
    
    public function restrict_upload_mimes($mimes) {
        // Solo permitir PDFs en el contexto del plugin
        if (isset($_POST['action']) && $_POST['action'] === 'upload_acta') {
            return array('pdf' => 'application/pdf');
        }
        return $mimes;
    }
    
    public function validate_user_access() {
        if (!wp_verify_nonce($_POST['nonce'], 'actas_nonce')) {
            wp_send_json_error('Nonce inválido');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
        }
        
        $user = wp_get_current_user();
		$numero_colegiado = get_field('user_numcolegiado', 'user_' . $user->ID);
        
        if (empty($numero_colegiado)) {
            wp_send_json_error('Número de colegiado no asignado');
        }
        
        wp_send_json_success(array(
            'user_id' => $user->ID,
            'numero_colegiado' => $numero_colegiado,
            'access_level' => $this->get_user_access_level($user)
        ));
    }
    
    private function get_user_access_level($user) {
        if (user_can($user, 'manage_options')) {
            return 'admin';
        } elseif (user_can($user, 'edit_posts')) {
            return 'editor';
        } else {
            return 'subscriber';
        }
    }
    
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir()['basedir'] . '/actas-pdf/temp/';
        
        if (is_dir($upload_dir)) {
            $files = glob($upload_dir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > 3600) { // 1 hora
                    unlink($file);
                }
            }
        }
    }
}

// Inicializar configuración de seguridad
new VisorPDFSecurityConfig();

// Hook para verificar acceso en cada carga de página
add_action('template_redirect', function() {
    global $post;
    if (is_object($post) && has_shortcode($post->post_content, 'actas_viewer')) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
        
        $user = wp_get_current_user();
        
        // Verificar número de colegiado - priorizar meta de usuario
        $numero_colegiado = get_user_meta($user->ID, 'numero_colegiado', true);
        
        // Compatibilidad con ACF si existe
        if (empty($numero_colegiado) && function_exists('get_field')) {
            $numero_colegiado = get_field('user_numcolegiado', 'user_' . $user->ID);
        }
        
        if (empty($numero_colegiado)) {
            wp_die('Su cuenta no tiene asignado un número de colegiado. Contacte al administrador.', 'Acceso Denegado', array('response' => 403));
        }
    }
});
?>