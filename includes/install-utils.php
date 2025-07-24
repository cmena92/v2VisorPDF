<?php
/**
 * Archivo: includes/install-utils.php
 * Utilidades para verificar e instalar dependencias del plugin
 */

if (!defined('ABSPATH')) exit;

class VisorPDFInstallUtils {
    
    public static function check_requirements() {
        $requirements = array();
        
        // Verificar versión de WordPress
        $wp_version = get_bloginfo('version');
        $requirements['wordpress'] = array(
            'required' => '5.0',
            'current' => $wp_version,
            'status' => version_compare($wp_version, '5.0', '>=')
        );
        
        // Verificar versión de PHP
        $php_version = phpversion();
        $requirements['php'] = array(
            'required' => '7.4',
            'current' => $php_version,
            'status' => version_compare($php_version, '7.4', '>=')
        );
        
        // Verificar Imagick
        $requirements['imagick'] = array(
            'required' => 'Extensión Imagick',
            'current' => extension_loaded('imagick') ? 'Instalado' : 'No instalado',
            'status' => extension_loaded('imagick')
        );
        
        // Verificar soporte PDF en Imagick
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats('PDF');
                $pdf_support = in_array('PDF', $formats);
            } catch (Exception $e) {
                $pdf_support = false;
            }
            
            $requirements['imagick_pdf'] = array(
                'required' => 'Soporte PDF en Imagick',
                'current' => $pdf_support ? 'Disponible' : 'No disponible',
                'status' => $pdf_support
            );
        }
        
        // Verificar permisos de escritura
        $upload_dir = wp_upload_dir();
        $writable = wp_is_writable($upload_dir['basedir']);
        $requirements['uploads_writable'] = array(
            'required' => 'Permisos de escritura en uploads',
            'current' => $writable ? 'Correcto' : 'Sin permisos',
            'status' => $writable
        );
        
        // Verificar límite de memoria
        $memory_limit = ini_get('memory_limit');
        $memory_mb = self::convert_to_mb($memory_limit);
        $requirements['memory_limit'] = array(
            'required' => '256M',
            'current' => $memory_limit,
            'status' => $memory_mb >= 256
        );
        
        return $requirements;
    }
    
    public static function display_requirements_page() {
        $requirements = self::check_requirements();
        $all_ok = true;
        
        foreach ($requirements as $req) {
            if (!$req['status']) {
                $all_ok = false;
                break;
            }
        }
        ?>
        <div class="wrap">
            <h1>Verificación de Requisitos - Visor PDF Crisman</h1>
            
            <?php if ($all_ok): ?>
                <div class="notice notice-success">
                    <p><strong>✓ Todos los requisitos están cumplidos.</strong> El plugin funcionará correctamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong>⚠️ Algunos requisitos no están cumplidos.</strong> El plugin puede no funcionar correctamente.</p>
                </div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Requisito</th>
                        <th>Requerido</th>
                        <th>Actual</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $key => $req): ?>
                        <tr>
                            <td><strong><?php echo self::get_requirement_name($key); ?></strong></td>
                            <td><?php echo esc_html($req['required']); ?></td>
                            <td><?php echo esc_html($req['current']); ?></td>
                            <td>
                                <?php if ($req['status']): ?>
                                    <span style="color: green;">✓ OK</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Error</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo self::get_requirement_action($key, $req['status']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px;">
                <h2>Instrucciones de Instalación</h2>
                
                <div class="visor-pdf-install-instructions">
                    <h3>🔧 Instalar Imagick</h3>
                    <div class="install-section">
                        <h4>En Ubuntu/Debian:</h4>
                        <pre><code>sudo apt-get update
sudo apt-get install php-imagick
sudo service apache2 restart</code></pre>
                        
                        <h4>En CentOS/RHEL:</h4>
                        <pre><code>sudo yum install php-imagick
sudo service httpd restart</code></pre>
                        
                        <h4>En Windows (XAMPP):</h4>
                        <ol>
                            <li>Descargue la extensión Imagick para su versión de PHP</li>
                            <li>Copie los archivos DLL a la carpeta ext/ de PHP</li>
                            <li>Agregue "extension=imagick" en php.ini</li>
                            <li>Reinicie Apache</li>
                        </ol>
                    </div>
                    
                    <h3>💾 Configurar Memoria PHP</h3>
                    <div class="install-section">
                        <p>Agregue o modifique en su archivo <code>php.ini</code>:</p>
                        <pre><code>memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M</code></pre>
                    </div>
                    
                    <h3>📁 Configurar Permisos</h3>
                    <div class="install-section">
                        <p>Asegúrese de que la carpeta uploads tenga permisos de escritura:</p>
                        <pre><code>chmod 755 wp-content/uploads/
chown www-data:www-data wp-content/uploads/</code></pre>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-requirements'); ?>" 
                   class="button button-primary">Verificar Nuevamente</a>
                <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman'); ?>" 
                   class="button">Ir al Visor PDF</a>
            </div>
        </div>
        
        <style>
        .visor-pdf-install-instructions {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .install-section {
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 15px 0;
        }
        .install-section pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .install-section code {
            background: #2d3748;
            color: #e2e8f0;
            padding: 2px 5px;
            border-radius: 3px;
        }
        </style>
        <?php
    }
    
    private static function get_requirement_name($key) {
        $names = array(
            'wordpress' => 'WordPress',
            'php' => 'PHP',
            'imagick' => 'Imagick',
            'imagick_pdf' => 'Imagick PDF',
            'uploads_writable' => 'Permisos Uploads',
            'memory_limit' => 'Límite de Memoria'
        );
        
        return isset($names[$key]) ? $names[$key] : $key;
    }
    
    private static function get_requirement_action($key, $status) {
        if ($status) {
            return '<span style="color: green;">Correcto</span>';
        }
        
        $actions = array(
            'wordpress' => 'Actualizar WordPress',
            'php' => 'Actualizar PHP',
            'imagick' => '<a href="#" onclick="alert(\'Consulte las instrucciones de instalación abajo\')">Instalar Imagick</a>',
            'imagick_pdf' => '<a href="#" onclick="alert(\'Reinstale Imagick con soporte PDF\')">Configurar PDF</a>',
            'uploads_writable' => '<a href="#" onclick="alert(\'Configure permisos de escritura\')">Configurar Permisos</a>',
            'memory_limit' => '<a href="#" onclick="alert(\'Aumente memory_limit en php.ini\')">Aumentar Memoria</a>'
        );
        
        return isset($actions[$key]) ? $actions[$key] : 'Configurar';
    }
    
    private static function convert_to_mb($memory_limit) {
        $memory_limit = trim($memory_limit);
        $last = strtolower($memory_limit[strlen($memory_limit)-1]);
        $memory_limit = (int) $memory_limit;
        
        switch($last) {
            case 'g':
                $memory_limit *= 1024;
            case 'm':
                break;
            case 'k':
                $memory_limit /= 1024;
                break;
            default:
                $memory_limit /= 1024 * 1024;
        }
        
        return $memory_limit;
    }
    
    public static function run_diagnostics() {
        $diagnostics = array();
        
        // Test básico de Imagick
        $diagnostics['imagick_basic'] = array(
            'name' => 'Test básico de Imagick',
            'status' => extension_loaded('imagick'),
            'details' => extension_loaded('imagick') ? 'Extensión cargada' : 'Extensión no disponible'
        );
        
        // Test de formatos soportados
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats();
                $pdf_support = in_array('PDF', $formats);
                
                $diagnostics['imagick_formats'] = array(
                    'name' => 'Formatos soportados',
                    'status' => $pdf_support,
                    'details' => $pdf_support ? 'PDF soportado (' . count($formats) . ' formatos total)' : 'PDF no soportado'
                );
            } catch (Exception $e) {
                $diagnostics['imagick_formats'] = array(
                    'name' => 'Formatos soportados',
                    'status' => false,
                    'details' => 'Error: ' . $e->getMessage()
                );
            }
        }
        
        return $diagnostics;
    }
}

// Agregar página de diagnósticos al menú admin
add_action('admin_menu', function() {
    add_submenu_page(
        'visor-pdf-crisman',
        'Verificar Requisitos',
        'Requisitos del Sistema',
        'manage_options',
        'visor-pdf-crisman-requirements',
        array('VisorPDFInstallUtils', 'display_requirements_page')
    );
});

// Agregar notice si hay problemas de requisitos
add_action('admin_notices', function() {
    if (get_current_screen()->parent_base === 'visor-pdf-crisman') {
        $requirements = VisorPDFInstallUtils::check_requirements();
        $has_issues = false;
        
        foreach ($requirements as $req) {
            if (!$req['status']) {
                $has_issues = true;
                break;
            }
        }
        
        if ($has_issues) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Visor PDF Crisman:</strong> 
                    Algunos requisitos del sistema no están cumplidos. 
                    <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-requirements'); ?>">
                        Verificar requisitos
                    </a>
                </p>
            </div>
            <?php
        }
    }
});
?>