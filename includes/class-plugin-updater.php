<?php
/**
 * Sistema de actualización para Visor PDF Crisman
 * 
 * Permite actualizar el plugin desde un servidor remoto o GitHub
 * 
 * @package VisorPDFCrisman
 * @since 2.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Visor_PDF_Plugin_Updater {
    
    private $plugin_slug;
    private $plugin_data;
    private $plugin_file;
    private $github_username = 'cmena92'; // Usuario de GitHub
    private $github_repo = 'v2VisorPDF'; // Repositorio de GitHub
    private $update_server = ''; // URL alternativa para servidor de actualizaciones
    private $access_token = ''; // Token de GitHub si el repo es privado
    
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_action('upgrader_process_complete', array($this, 'purge_transients'), 10, 2);
        add_action('admin_init', array($this, 'setup_update_settings'));
        
        // Agregar página de configuración
        add_action('admin_menu', array($this, 'add_update_settings_page'));
    }
    
    /**
     * Verificar si hay actualizaciones disponibles
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Obtener información del plugin
        if (!$this->plugin_data) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }
        
        // Verificar actualizaciones desde GitHub o servidor personalizado
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->plugin_data['Version'], $remote_version->version, '<')) {
            $update_data = array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version->version,
                'url' => $remote_version->homepage ?? $this->plugin_data['PluginURI'],
                'package' => $remote_version->download_url,
                'icons' => array(
                    '2x' => plugin_dir_url(dirname(__FILE__)) . 'assets/icon-256x256.png',
                    '1x' => plugin_dir_url(dirname(__FILE__)) . 'assets/icon-128x128.png',
                ),
                'banners' => array(
                    'high' => plugin_dir_url(dirname(__FILE__)) . 'assets/banner-1544x500.png',
                    'low' => plugin_dir_url(dirname(__FILE__)) . 'assets/banner-772x250.png'
                ),
                'tested' => $remote_version->tested ?? '',
                'requires_php' => $remote_version->requires_php ?? '7.4',
                'compatibility' => new stdClass()
            );
            
            $transient->response[$this->plugin_slug] = (object) $update_data;
        }
        
        return $transient;
    }
    
    /**
     * Obtener información de versión remota
     */
    public function get_remote_version() {
        // Intentar obtener desde caché
        $cache_key = 'visor_pdf_update_info';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $remote_info = null;
        
        // Método 1: GitHub Releases API
        if ($this->github_username && $this->github_repo) {
            $remote_info = $this->get_github_release_info();
        }
        
        // Método 2: Servidor de actualización personalizado
        if (!$remote_info && $this->update_server) {
            $remote_info = $this->get_custom_server_info();
        }
        
        // Método 3: Archivo JSON en el repositorio
        if (!$remote_info) {
            $remote_info = $this->get_json_info();
        }
        
        // Guardar en caché por 12 horas
        if ($remote_info) {
            set_transient($cache_key, $remote_info, 12 * HOUR_IN_SECONDS);
        }
        
        return $remote_info;
    }
    
    /**
     * Obtener información desde GitHub Releases
     */
    private function get_github_release_info() {
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            )
        );
        
        // Agregar token si está disponible
        if ($this->access_token) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body);
        
        if (!isset($release->tag_name)) {
            return false;
        }
        
        // Buscar el archivo ZIP en los assets
        $download_url = '';
        if (isset($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }
        
        // Si no hay asset, usar el zipball_url
        if (!$download_url) {
            $download_url = $release->zipball_url;
        }
        
        return (object) array(
            'version' => ltrim($release->tag_name, 'v'),
            'download_url' => $download_url,
            'homepage' => $release->html_url,
            'body' => $release->body ?? '',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4'
        );
    }
    
    /**
     * Obtener información desde servidor personalizado
     */
    private function get_custom_server_info() {
        if (!$this->update_server) {
            return false;
        }
        
        $info_url = trailingslashit($this->update_server) . 'info.json';
        
        $response = wp_remote_get($info_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $info = json_decode($body);
        
        if (!isset($info->version)) {
            return false;
        }
        
        return $info;
    }
    
    /**
     * Obtener información desde archivo JSON en el repositorio
     */
    private function get_json_info() {
        // URL del archivo update-info.json en tu servidor o repositorio
        $json_url = 'https://tu-servidor.com/plugins/visor-pdf-crisman/update-info.json';
        
        $response = wp_remote_get($json_url, array(
            'timeout' => 10,
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body);
    }
    
    /**
     * Proporcionar información del plugin para el modal de actualización
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        $remote_info = $this->get_remote_version();
        
        if (!$remote_info) {
            return $result;
        }
        
        $plugin_info = array(
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_info->version,
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'last_updated' => date('Y-m-d'),
            'homepage' => $this->plugin_data['PluginURI'],
            'short_description' => $this->plugin_data['Description'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => $this->get_changelog($remote_info),
                'upgrade_notice' => $this->get_upgrade_notice($remote_info)
            ),
            'download_link' => $remote_info->download_url,
            'trunk' => $remote_info->download_url,
            'requires' => '5.0',
            'tested' => $remote_info->tested ?? get_bloginfo('version'),
            'requires_php' => $remote_info->requires_php ?? '7.4',
            'banners' => array(
                'low' => plugin_dir_url(dirname(__FILE__)) . 'assets/banner-772x250.png',
                'high' => plugin_dir_url(dirname(__FILE__)) . 'assets/banner-1544x500.png'
            ),
            'icons' => array(
                '1x' => plugin_dir_url(dirname(__FILE__)) . 'assets/icon-128x128.png',
                '2x' => plugin_dir_url(dirname(__FILE__)) . 'assets/icon-256x256.png'
            )
        );
        
        return (object) $plugin_info;
    }
    
    /**
     * Obtener changelog
     */
    private function get_changelog($remote_info) {
        if (isset($remote_info->body)) {
            return nl2br($remote_info->body);
        }
        
        return '<h4>Versión ' . $remote_info->version . '</h4>
                <ul>
                    <li>Mejoras de rendimiento</li>
                    <li>Corrección de errores</li>
                    <li>Nuevas características</li>
                </ul>';
    }
    
    /**
     * Obtener aviso de actualización
     */
    private function get_upgrade_notice($remote_info) {
        return 'Nueva versión ' . $remote_info->version . ' disponible. Se recomienda actualizar para obtener las últimas mejoras y correcciones.';
    }
    
    /**
     * Limpiar transients después de actualizar
     */
    public function purge_transients($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            delete_transient('visor_pdf_update_info');
        }
    }
    
    /**
     * Configuración de actualizaciones
     */
    public function setup_update_settings() {
        register_setting('visor_pdf_update_settings', 'visor_pdf_update_config');
    }
    
    /**
     * Agregar página de configuración de actualizaciones
     */
    public function add_update_settings_page() {
        add_submenu_page(
            'visor-pdf-crisman',
            'Configuración de Actualizaciones',
            'Actualizaciones',
            'manage_options',
            'visor-pdf-updates',
            array($this, 'render_update_settings_page')
        );
    }
    
    /**
     * Renderizar página de configuración
     */
    public function render_update_settings_page() {
        $config = get_option('visor_pdf_update_config', array());
        ?>
        <div class="wrap">
            <h1>Configuración de Actualizaciones - Visor PDF Crisman</h1>
            
            <div class="notice notice-info">
                <p><strong>Versión actual:</strong> <?php echo $this->plugin_data['Version'] ?? '2.0.1'; ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('visor_pdf_update_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Método de Actualización</th>
                        <td>
                            <select name="visor_pdf_update_config[method]">
                                <option value="github" <?php selected($config['method'] ?? '', 'github'); ?>>GitHub</option>
                                <option value="custom" <?php selected($config['method'] ?? '', 'custom'); ?>>Servidor Personalizado</option>
                                <option value="manual" <?php selected($config['method'] ?? '', 'manual'); ?>>Manual</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Usuario de GitHub</th>
                        <td>
                            <input type="text" name="visor_pdf_update_config[github_user]" 
                                   value="<?php echo esc_attr($config['github_user'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">Tu nombre de usuario de GitHub</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Repositorio de GitHub</th>
                        <td>
                            <input type="text" name="visor_pdf_update_config[github_repo]" 
                                   value="<?php echo esc_attr($config['github_repo'] ?? 'visor-pdf-crisman'); ?>" 
                                   class="regular-text" />
                            <p class="description">Nombre del repositorio en GitHub</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Token de Acceso (Opcional)</th>
                        <td>
                            <input type="password" name="visor_pdf_update_config[access_token]" 
                                   value="<?php echo esc_attr($config['access_token'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">Token de GitHub para repositorios privados</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Servidor de Actualización</th>
                        <td>
                            <input type="url" name="visor_pdf_update_config[update_server]" 
                                   value="<?php echo esc_attr($config['update_server'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">URL del servidor personalizado de actualizaciones</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Verificar Actualizaciones</th>
                        <td>
                            <button type="button" class="button button-secondary" id="check-updates-now">
                                Verificar Ahora
                            </button>
                            <span id="update-check-result"></span>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>Estado de Actualización</h2>
            <div id="update-status" class="card">
                <p>Verificando...</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#check-updates-now').on('click', function() {
                var $button = $(this);
                var $result = $('#update-check-result');
                
                $button.prop('disabled', true);
                $result.html(' <span class="spinner is-active"></span> Verificando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'visor_pdf_check_update',
                        nonce: '<?php echo wp_create_nonce('visor_pdf_check_update'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.update_available) {
                                $result.html(' <span style="color: green;">✓</span> Nueva versión ' + response.data.version + ' disponible');
                            } else {
                                $result.html(' <span style="color: green;">✓</span> El plugin está actualizado');
                            }
                        } else {
                            $result.html(' <span style="color: red;">✗</span> Error al verificar');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // Verificar estado al cargar
            function checkUpdateStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'visor_pdf_update_status',
                        nonce: '<?php echo wp_create_nonce('visor_pdf_update_status'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<p><strong>Versión actual:</strong> ' + response.data.current_version + '</p>';
                            if (response.data.latest_version) {
                                html += '<p><strong>Última versión:</strong> ' + response.data.latest_version + '</p>';
                                if (response.data.update_available) {
                                    html += '<p class="notice notice-warning" style="padding: 10px;">Nueva versión disponible. <a href="' + response.data.update_url + '">Actualizar ahora</a></p>';
                                } else {
                                    html += '<p class="notice notice-success" style="padding: 10px;">El plugin está actualizado</p>';
                                }
                            }
                            $('#update-status').html(html);
                        }
                    }
                });
            }
            
            checkUpdateStatus();
        });
        </script>
        <?php
    }
}