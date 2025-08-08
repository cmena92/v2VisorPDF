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
        
        // Hooks para mostrar actualizaciones en la página de plugins
        add_action('after_plugin_row_' . $this->plugin_slug, array($this, 'show_update_notification'), 10, 2);
        
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
            $download_url = $this->get_download_url($remote_version->download_url);
            
            $update_data = array(
                'id' => $this->plugin_slug,
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version->version,
                'url' => $remote_version->homepage ?? $this->plugin_data['PluginURI'],
                'package' => $download_url,
                'icons' => array(),
                'banners' => array(),
                'tested' => $remote_version->tested ?? get_bloginfo('version'),
                'requires_php' => $remote_version->requires_php ?? '7.4',
                'compatibility' => new stdClass()
            );
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Visor PDF Updater - Actualización disponible: ' . $remote_version->version);
                error_log('Visor PDF Updater - Plugin slug: ' . $this->plugin_slug);
                error_log('Visor PDF Updater - Download URL: ' . $download_url);
            }
            
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
        
        // Método 3: GitHub Tags API (fallback si no hay releases)
        if (!$remote_info && $this->github_username && $this->github_repo) {
            $remote_info = $this->get_github_tags_info();
        }
        
        // Método 4: Archivo JSON en el repositorio
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
            // Log del error para debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Visor PDF Updater - Error al obtener release de GitHub: ' . $response->get_error_message());
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body);
        
        if (!isset($release->tag_name)) {
            // Log para debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Visor PDF Updater - No se encontró release en GitHub: ' . $body);
            }
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
     * Obtener URL de descarga correcta
     */
    private function get_download_url($url) {
        // Si es una URL de GitHub API, convertirla a URL de descarga directa
        if (strpos($url, 'api.github.com') !== false && strpos($url, 'zipball') !== false) {
            // Convertir de API URL a descarga directa
            // De: https://api.github.com/repos/USER/REPO/zipball/refs/tags/vX.X.X
            // A: https://github.com/USER/REPO/archive/refs/tags/vX.X.X.zip
            $url = str_replace('api.github.com/repos/', 'github.com/', $url);
            $url = str_replace('/zipball/', '/archive/', $url) . '.zip';
        }
        
        return $url;
    }
    
    /**
     * Obtener información desde GitHub Tags API (fallback)
     */
    private function get_github_tags_info() {
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/tags";
        
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
        $tags = json_decode($body);
        
        if (!is_array($tags) || empty($tags)) {
            return false;
        }
        
        // Obtener el primer tag (más reciente)
        $latest_tag = $tags[0];
        
        if (!isset($latest_tag->name)) {
            return false;
        }
        
        // Crear URL del repositorio para homepage
        $repo_url = "https://github.com/{$this->github_username}/{$this->github_repo}";
        
        return (object) array(
            'version' => ltrim($latest_tag->name, 'v'),
            'download_url' => $latest_tag->zipball_url,
            'homepage' => $repo_url . "/releases/tag/" . $latest_tag->name,
            'body' => 'Actualización disponible desde GitHub Tags',
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
                'description' => $this->get_full_description(),
                'changelog' => $this->get_changelog($remote_info),
                'installation' => $this->get_installation_instructions(),
                'faq' => $this->get_faq_section(),
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
        if (isset($remote_info->body) && !empty($remote_info->body)) {
            return nl2br(esc_html($remote_info->body));
        }
        
        // Changelog específico por versión
        $version = $remote_info->version;
        $changelog = $this->get_version_changelog($version);
        
        return $changelog;
    }
    
    /**
     * Obtener changelog específico por versión
     */
    private function get_version_changelog($version) {
        $changelogs = array(
            '2.1.1' => '
                <h4>📚 Versión 2.1.1 - Documentación Mejorada</h4>
                <h5>📖 Mejoras de Documentación:</h5>
                <ul>
                    <li><strong>Changelog detallado</strong>: Información específica por versión en el modal de actualización</li>
                    <li><strong>Descripción completa</strong>: Documentación expandida con características y casos de uso</li>
                    <li><strong>Instrucciones de instalación</strong>: Guía paso a paso con requisitos y configuración</li>
                    <li><strong>FAQ comprehensivo</strong>: Sección de preguntas frecuentes con respuestas detalladas</li>
                </ul>
                <h5>✨ Información Adicional:</h5>
                <ul>
                    <li>Avisos de actualización específicos por versión</li>
                    <li>Documentación técnica mejorada para desarrolladores</li>
                    <li>Guías de troubleshooting y resolución de problemas</li>
                </ul>',
                
            '2.1.0' => '
                <h4>🚀 Versión 2.1.0 - Activación Automática Post-Actualización</h4>
                <h5>✨ Nuevas Funcionalidades:</h5>
                <ul>
                    <li><strong>Activación automática</strong>: El plugin se reactiva automáticamente después de cada actualización</li>
                    <li><strong>Notificaciones visuales</strong>: Confirmación visual de actualización exitosa en el admin</li>
                    <li><strong>Limpieza de cachés</strong>: Eliminación automática de cachés obsoletos post-actualización</li>
                </ul>
                <h5>🔧 Mejoras Técnicas:</h5>
                <ul>
                    <li>Verificación automática de integridad de tablas después de actualizar</li>
                    <li>Reconfiguración automática de carpetas predeterminadas</li>
                    <li>Validación de permisos del directorio de uploads</li>
                    <li>Sistema de transients para rastrear actualizaciones exitosas</li>
                </ul>
                <h5>🛡️ Seguridad y Estabilidad:</h5>
                <ul>
                    <li>Hooks mejorados para el proceso de actualización</li>
                    <li>Logging detallado para troubleshooting (con WP_DEBUG)</li>
                    <li>Manejo robusto de errores durante reactivación</li>
                </ul>',
                
            '2.0.9' => '
                <h4>📝 Versión 2.0.9 - Gestión de Actas Individuales</h4>
                <h5>✨ Nuevas Funcionalidades:</h5>
                <ul>
                    <li><strong>Eliminar actas individuales</strong>: Función de eliminación segura (soft delete)</li>
                    <li><strong>Renombrar actas</strong>: Edición inline con atajos de teclado (Enter/ESC)</li>
                    <li><strong>Interfaz mejorada</strong>: Botones de acción en tabla de reorganización</li>
                </ul>
                <h5>🔧 Mejoras Técnicas:</h5>
                <ul>
                    <li>AJAX endpoints para gestión individual de actas</li>
                    <li>Eliminación física del archivo junto con soft delete en BD</li>
                    <li>Logging de actividades de eliminación y renombrado</li>
                    <li>Validación de permisos y nonces de seguridad</li>
                </ul>',
                
            '2.0.8' => '
                <h4>📦 Versión 2.0.8 - Límites de Archivo Ampliados</h4>
                <h5>🔧 Mejoras:</h5>
                <ul>
                    <li><strong>Tamaño máximo aumentado</strong>: De 10MB a 20MB por archivo PDF</li>
                    <li><strong>Validación mejorada</strong>: Mensajes de error más descriptivos</li>
                    <li><strong>Interfaz actualizada</strong>: Reflejos de nuevos límites en subida masiva</li>
                </ul>',
                
            '2.0.7' => '
                <h4>🗄️ Versión 2.0.7 - Correcciones de Base de Datos</h4>
                <h5>🐛 Correcciones:</h5>
                <ul>
                    <li><strong>Campo device_type</strong>: Corregido error de columna faltante</li>
                    <li><strong>Índices duplicados</strong>: Prevención de errores de índices ya existentes</li>
                    <li><strong>Sistema de migración mejorado</strong>: Verificación de existencia antes de crear</li>
                </ul>',
        );
        
        if (isset($changelogs[$version])) {
            return $changelogs[$version];
        }
        
        // Fallback para versiones no especificadas
        return '<h4>Versión ' . esc_html($version) . '</h4>
                <h5>🚀 Mejoras y Actualizaciones:</h5>
                <ul>
                    <li><strong>Corrección de errores</strong>: Solución de issues reportados</li>
                    <li><strong>Mejoras de rendimiento</strong>: Optimizaciones en el código</li>
                    <li><strong>Actualizaciones de seguridad</strong>: Mejoras en la protección</li>
                    <li><strong>Nuevas características</strong>: Funcionalidades adicionales</li>
                </ul>
                <p><em>Para obtener detalles específicos, consulte la documentación del repositorio.</em></p>';
    }
    
    /**
     * Obtener aviso de actualización
     */
    private function get_upgrade_notice($remote_info) {
        $version = $remote_info->version;
        $notices = array(
            '2.1.1' => '📚 <strong>DOCUMENTACIÓN MEJORADA:</strong> Ahora incluye changelog detallado por versión, instrucciones completas de instalación, FAQ comprehensivo y documentación técnica expandida. Mejora la experiencia de usuario con información más clara y accesible.',
            
            '2.1.0' => '🚀 <strong>NUEVA FUNCIONALIDAD:</strong> Activación automática post-actualización. El plugin se reactiva automáticamente después de cada update, eliminando la necesidad de reconfiguración manual. Incluye notificaciones visuales de éxito y limpieza automática de cachés.',
            
            '2.0.9' => '📝 <strong>GESTIÓN AVANZADA:</strong> Ahora puedes eliminar y renombrar actas individuales directamente desde el panel de administración. Funcionalidad con eliminación segura (soft delete) y edición inline.',
            
            '2.0.8' => '📦 <strong>LÍMITES AMPLIADOS:</strong> El tamaño máximo de archivos PDF se ha aumentado de 10MB a 20MB. Ideal para actas más extensas o con mayor resolución.',
            
            '2.0.7' => '🗄️ <strong>CORRECCIÓN IMPORTANTE:</strong> Resuelve errores críticos de base de datos (columna device_type e índices duplicados). Actualización recomendada para usuarios con issues de BD.',
        );
        
        if (isset($notices[$version])) {
            return $notices[$version];
        }
        
        // Aviso genérico para versiones no especificadas
        return '✨ <strong>Nueva versión ' . esc_html($version) . ' disponible.</strong> Se recomienda actualizar para obtener las últimas mejoras, correcciones de errores y nuevas funcionalidades. El plugin se reactivará automáticamente después de la actualización.';
    }
    
    /**
     * Obtener descripción completa del plugin
     */
    private function get_full_description() {
        return '
        <h3>🛡️ Visor PDF Crisman - Sistema Seguro de Gestión de Documentos</h3>
        
        <p><strong>Visor PDF Crisman</strong> es un plugin especializado para WordPress que permite la gestión segura de documentos PDF con control de acceso basado en números de colegiado y marcas de agua personalizadas.</p>
        
        <h4>🎯 Características Principales:</h4>
        <ul>
            <li><strong>📋 Gestión de Actas PDF</strong>: Subida individual y masiva de documentos PDF</li>
            <li><strong>🔐 Control de Acceso</strong>: Basado en números de colegiado de usuarios registrados</li>
            <li><strong>💧 Marcas de Agua</strong>: Generación automática con número de colegiado del usuario</li>
            <li><strong>📁 Organización Jerárquica</strong>: Sistema de carpetas de hasta 2 niveles</li>
            <li><strong>🔍 Navegación Visual</strong>: Interfaz intuitiva con shortcodes personalizables</li>
            <li><strong>📊 Analytics Integrado</strong>: Seguimiento de visualizaciones y actividad</li>
            <li><strong>🔒 Seguridad Avanzada</strong>: Protección contra descargas no autorizadas</li>
            <li><strong>📱 Responsive</strong>: Compatible con dispositivos móviles</li>
        </ul>
        
        <h4>🚀 Funcionalidades Avanzadas:</h4>
        <ul>
            <li><strong>Subida Masiva</strong>: Hasta 20 archivos simultáneos de hasta 20MB cada uno</li>
            <li><strong>Viewer Híbrido</strong>: Navegación por carpetas integrada en el visor</li>
            <li><strong>Logging Detallado</strong>: Registro de accesos y actividades sospechosas</li>
            <li><strong>Sistema de Actualizaciones</strong>: Updates automáticos desde GitHub</li>
            <li><strong>Activación Post-Update</strong>: Reconfiguración automática después de actualizaciones</li>
            <li><strong>Gestión Individual</strong>: Eliminar y renombrar actas desde el admin</li>
        </ul>
        
        <h4>⚙️ Requisitos Técnicos:</h4>
        <ul>
            <li><strong>WordPress:</strong> 5.0 o superior</li>
            <li><strong>PHP:</strong> 7.4 o superior</li>
            <li><strong>Imagick:</strong> Extensión requerida para procesamiento de PDF</li>
            <li><strong>Memoria:</strong> Mínimo 256MB (recomendado 512MB)</li>
            <li><strong>MySQL:</strong> 5.7 o superior</li>
        </ul>
        
        <h4>🎯 Casos de Uso Ideales:</h4>
        <ul>
            <li>Colegios profesionales y asociaciones</li>
            <li>Organizaciones que requieren control de acceso a documentos</li>
            <li>Empresas con necesidades de trazabilidad de visualizaciones</li>
            <li>Entidades que manejan documentación sensible o confidencial</li>
        </ul>';
    }
    
    /**
     * Obtener instrucciones de instalación
     */
    private function get_installation_instructions() {
        return '
        <h3>📦 Instrucciones de Instalación</h3>
        
        <h4>✅ Requisitos Previos:</h4>
        <ol>
            <li><strong>Verificar PHP Imagick:</strong> Asegúrate de que la extensión Imagick esté instalada y activa en tu servidor</li>
            <li><strong>Permisos de escritura:</strong> El directorio <code>wp-content/uploads/</code> debe tener permisos de escritura</li>
            <li><strong>Memoria PHP:</strong> Configura al menos 256MB de memoria PHP (recomendado 512MB)</li>
        </ol>
        
        <h4>🔧 Instalación Automática:</h4>
        <ol>
            <li>Ve a <strong>Plugins → Añadir nuevo</strong> en tu admin de WordPress</li>
            <li>Busca "Visor PDF Crisman" o sube el archivo ZIP</li>
            <li>Haz clic en <strong>"Instalar ahora"</strong></li>
            <li>Activa el plugin cuando la instalación termine</li>
        </ol>
        
        <h4>⚙️ Configuración Inicial:</h4>
        <ol>
            <li><strong>Accede al menú:</strong> "Visor PDF" en el admin de WordPress</li>
            <li><strong>Verifica requisitos:</strong> Usa la herramienta de diagnóstico incluida</li>
            <li><strong>Configura usuarios:</strong> Asigna números de colegiado en los perfiles de usuario</li>
            <li><strong>Crea carpetas:</strong> Organiza la estructura de carpetas según tus necesidades</li>
            <li><strong>Sube documentos:</strong> Utiliza la subida individual o masiva</li>
        </ol>
        
        <h4>📋 Primeros Pasos:</h4>
        <ol>
            <li><strong>Shortcode básico:</strong> <code>[actas_viewer]</code> - Visor simple</li>
            <li><strong>Navegador visual:</strong> <code>[actas_navigator_visual]</code> - Con navegación por carpetas</li>
            <li><strong>Visor híbrido:</strong> <code>[actas_hybrid]</code> - Navegación integrada</li>
        </ol>
        
        <h4>🔍 Verificación Post-Instalación:</h4>
        <ul>
            <li>Verifica que las tablas de BD se hayan creado correctamente</li>
            <li>Confirma que el directorio <code>/wp-content/uploads/actas-pdf/</code> existe</li>
            <li>Prueba la subida de un PDF de prueba</li>
            <li>Verifica que las marcas de agua se generen correctamente</li>
        </ul>';
    }
    
    /**
     * Obtener sección de FAQ
     */
    private function get_faq_section() {
        return '
        <h3>❓ Preguntas Frecuentes (FAQ)</h3>
        
        <h4>🔧 Configuración y Requisitos</h4>
        
        <p><strong>P: ¿Es necesario tener Imagick instalado?</strong><br>
        R: Sí, Imagick es <strong>absolutamente necesario</strong> para procesar los PDF y generar las marcas de agua. Sin esta extensión el plugin no funcionará.</p>
        
        <p><strong>P: ¿Cuál es el tamaño máximo de archivo permitido?</strong><br>
        R: El plugin permite archivos PDF de hasta <strong>20MB</strong>. Este límite se puede ajustar en la configuración del servidor.</p>
        
        <p><strong>P: ¿Cómo asigno números de colegiado a los usuarios?</strong><br>
        R: Ve a <strong>Usuarios → Editar usuario</strong> y agrega el número de colegiado en el campo personalizado <code>numero_colegiado</code>.</p>
        
        <h4>📁 Gestión de Documentos</h4>
        
        <p><strong>P: ¿Puedo organizar las actas en carpetas?</strong><br>
        R: Sí, el plugin incluye un sistema de carpetas jerárquico con hasta 2 niveles de profundidad.</p>
        
        <p><strong>P: ¿Cómo subo múltiples archivos a la vez?</strong><br>
        R: Utiliza la función de <strong>"Subida Masiva"</strong> que permite hasta 20 archivos simultáneos con drag & drop.</p>
        
        <p><strong>P: ¿Puedo eliminar actas individuales?</strong><br>
        R: Sí, desde la versión 2.0.9 puedes eliminar y renombrar actas individuales desde el panel de administración.</p>
        
        <h4>🛡️ Seguridad y Acceso</h4>
        
        <p><strong>P: ¿Los PDF están protegidos contra descarga directa?</strong><br>
        R: Sí, los archivos se almacenan en un directorio protegido con reglas .htaccess que impiden el acceso directo.</p>
        
        <p><strong>P: ¿Se registran los accesos a los documentos?</strong><br>
        R: Sí, el plugin registra todas las visualizaciones con timestamps, IP y número de colegiado del usuario.</p>
        
        <p><strong>P: ¿Qué pasa si un usuario no tiene número de colegiado?</strong><br>
        R: Los usuarios sin número de colegiado no podrán acceder a ningún documento del sistema.</p>
        
        <h4>🚀 Actualizaciones y Mantenimiento</h4>
        
        <p><strong>P: ¿Cómo se actualiza el plugin?</strong><br>
        R: El plugin incluye un sistema de actualizaciones automáticas desde GitHub. Se notificará cuando haya versiones nuevas.</p>
        
        <p><strong>P: ¿Qué pasa con mis datos al actualizar?</strong><br>
        R: Todos los datos se preservan. El plugin se reactiva automáticamente después de cada actualización.</p>
        
        <p><strong>P: ¿Dónde puedo encontrar los logs de errores?</strong><br>
        R: Con WP_DEBUG activo, los logs se guardan en <code>wp-content/debug.log</code>.</p>
        
        <h4>🎯 Uso y Shortcodes</h4>
        
        <p><strong>P: ¿Cuáles son los shortcodes disponibles?</strong><br>
        R: <code>[actas_viewer]</code>, <code>[actas_navigator_visual]</code>, y <code>[actas_hybrid]</code></p>
        
        <p><strong>P: ¿El visor es responsive?</strong><br>
        R: Sí, todos los componentes están optimizados para dispositivos móviles y tablets.</p>
        
        <p><strong>P: ¿Puedo personalizar la apariencia?</strong><br>
        R: Sí, puedes agregar CSS personalizado para modificar la apariencia según tu tema.</p>';
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
    
    /**
     * Mostrar notificación de actualización en la página de plugins
     */
    public function show_update_notification($plugin_file, $plugin_data) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $remote_version = $this->get_remote_version();
        
        if (!$remote_version || !version_compare($plugin_data['Version'], $remote_version->version, '<')) {
            return;
        }
        
        $wp_list_table = _get_list_table('WP_Plugins_List_Table');
        $plugin_name = $plugin_data['Name'];
        
        echo '<tr class="plugin-update-tr active" id="' . esc_attr($this->plugin_slug . '-update') . '" data-slug="' . esc_attr(dirname($this->plugin_slug)) . '" data-plugin="' . esc_attr($this->plugin_slug) . '">';
        echo '<td colspan="' . esc_attr($wp_list_table->get_column_count()) . '" class="plugin-update colspanchange">';
        echo '<div class="update-message notice inline notice-warning notice-alt">';
        
        $update_url = wp_nonce_url(
            self_admin_url('update.php?action=upgrade-plugin&plugin=') . $this->plugin_slug,
            'upgrade-plugin_' . $this->plugin_slug
        );
        
        printf(
            '<p><strong>%s</strong> versión %s está disponible. <a href="%s" class="update-link" aria-label="Actualizar %s ahora">Actualizar ahora</a>.</p>',
            esc_html($plugin_name),
            esc_html($remote_version->version),
            esc_url($update_url),
            esc_attr($plugin_name)
        );
        
        echo '</div></td></tr>';
    }
}