<?php
/**
 * Template: Estado de Instalación del Plugin
 * 
 * @package VisorPDFCrisman
 * @version 2.0.8
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener información del estado
global $wpdb;
$current_version = get_option('visor_pdf_crisman_version', 'No instalado');
$installation_date = get_option('visor_pdf_crisman_installed', 'No disponible');
$last_update = get_option('visor_pdf_crisman_last_update', 'No disponible');
$created_folders = get_option('visor_pdf_default_folders_created', array());

// Verificar tablas
$tables_status = array();
$required_tables = array(
    'actas_logs' => 'Logs de visualización',
    'actas_metadata' => 'Metadatos de actas',
    'actas_folders' => 'Carpetas jerárquicas',
    'actas_suspicious_logs' => 'Logs de actividad sospechosa',
    'actas_analytics' => 'Analytics y estadísticas',
    'actas_user_sessions' => 'Sesiones de usuario'
);

foreach ($required_tables as $table_suffix => $description) {
    $table_name = $wpdb->prefix . $table_suffix;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $tables_status[] = array(
            'name' => $table_name,
            'description' => $description,
            'exists' => true,
            'count' => $count
        );
    } else {
        $tables_status[] = array(
            'name' => $table_name,
            'description' => $description,
            'exists' => false,
            'count' => 0
        );
    }
}

// Verificar directorios
$upload_dir = wp_upload_dir();
$base_dir = $upload_dir['basedir'];
$required_directories = array(
    $base_dir . '/actas-pdf' => 'Directorio principal de actas',
    $base_dir . '/actas-pdf/temp' => 'Archivos temporales',
    $base_dir . '/actas-pdf/cache' => 'Cache del sistema',
    $base_dir . '/actas-pdf/thumbnails' => 'Miniaturas de páginas',
    $base_dir . '/actas-pdf/watermarks' => 'Marcas de agua',
    $base_dir . '/actas-pdf/backups' => 'Respaldos'
);

$directories_status = array();
foreach ($required_directories as $dir => $description) {
    $exists = file_exists($dir) && is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    $files_count = 0;
    
    if ($exists) {
        $files = glob($dir . '/*');
        $files_count = $files ? count($files) : 0;
    }
    
    $directories_status[] = array(
        'path' => $dir,
        'description' => $description,
        'exists' => $exists,
        'writable' => $writable,
        'files_count' => $files_count
    );
}

// Verificar carpetas en base de datos
$folders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_folders");
$actas_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actas_metadata WHERE status = 'active'");

// Procesamiento de acciones
if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'visor_pdf_actions')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'reinstall':
            Visor_PDF_Plugin_Installer::install();
            echo '<div class="notice notice-success"><p>✅ Reinstalación completada</p></div>';
            break;
            
        case 'update':
            Visor_PDF_Plugin_Installer::update();
            echo '<div class="notice notice-success"><p>✅ Actualización completada</p></div>';
            break;
            
        case 'create_directories':
            $created = 0;
            foreach ($required_directories as $dir => $desc) {
                if (!file_exists($dir)) {
                    if (wp_mkdir_p($dir)) {
                        $created++;
                    }
                }
            }
            echo '<div class="notice notice-success"><p>✅ Se crearon ' . $created . ' directorios</p></div>';
            break;
    }
    
    // Recargar la página para mostrar cambios
    echo '<script>window.location.reload();</script>';
}
?>

<div class="wrap">
    <h1>🔧 Estado de Instalación - Visor PDF Crisman</h1>
    
    <!-- Información General -->
    <div class="card">
        <h2>📋 Información General</h2>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>Versión del Plugin:</strong></td>
                    <td><?php echo VISOR_PDF_CRISMAN_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong>Versión Instalada:</strong></td>
                    <td><?php echo $current_version; ?>
                        <?php if (Visor_PDF_Plugin_Installer::needs_update()): ?>
                            <span style="color: orange;"> ⚠️ Actualización disponible</span>
                        <?php else: ?>
                            <span style="color: green;"> ✅ Actualizado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Fecha de Instalación:</strong></td>
                    <td><?php echo $installation_date; ?></td>
                </tr>
                <tr>
                    <td><strong>Última Actualización:</strong></td>
                    <td><?php echo $last_update; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Estado de las Tablas -->
    <div class="card">
        <h2>🗄️ Estado de las Tablas de Base de Datos</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Tabla</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Registros</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables_status as $table): ?>
                <tr>
                    <td><code><?php echo esc_html($table['name']); ?></code></td>
                    <td><?php echo esc_html($table['description']); ?></td>
                    <td>
                        <?php if ($table['exists']): ?>
                            <span style="color: green;">✅ Existe</span>
                        <?php else: ?>
                            <span style="color: red;">❌ No existe</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($table['count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Estado de los Directorios -->
    <div class="card">
        <h2>📁 Estado de los Directorios</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Directorio</th>
                    <th>Descripción</th>
                    <th>Existe</th>
                    <th>Escribible</th>
                    <th>Archivos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($directories_status as $dir): ?>
                <tr>
                    <td><code><?php echo esc_html($dir['path']); ?></code></td>
                    <td><?php echo esc_html($dir['description']); ?></td>
                    <td>
                        <?php if ($dir['exists']): ?>
                            <span style="color: green;">✅ Sí</span>
                        <?php else: ?>
                            <span style="color: red;">❌ No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($dir['writable']): ?>
                            <span style="color: green;">✅ Sí</span>
                        <?php elseif ($dir['exists']): ?>
                            <span style="color: orange;">⚠️ No</span>
                        <?php else: ?>
                            <span style="color: red;">❌ N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($dir['files_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Estado de las Carpetas -->
    <div class="card">
        <h2>📂 Estado de las Carpetas del Sistema</h2>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>Carpetas en Base de Datos:</strong></td>
                    <td><?php echo number_format($folders_count); ?></td>
                </tr>
                <tr>
                    <td><strong>Actas Activas:</strong></td>
                    <td><?php echo number_format($actas_count); ?></td>
                </tr>
                <tr>
                    <td><strong>Carpetas Creadas en Instalación:</strong></td>
                    <td>
                        <?php if (empty($created_folders)): ?>
                            <span style="color: orange;">⚠️ No hay registro de carpetas creadas</span>
                        <?php else: ?>
                            <span style="color: green;">✅ <?php echo count($created_folders); ?> carpetas registradas</span>
                            <details style="margin-top: 10px;">
                                <summary>Ver detalles</summary>
                                <ul>
                                    <?php foreach ($created_folders as $folder): ?>
                                        <li><?php echo esc_html($folder); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Acciones de Mantenimiento -->
    <div class="card">
        <h2>🔧 Acciones de Mantenimiento</h2>
        <p>Estas acciones pueden ayudar a resolver problemas de instalación:</p>
        
        <form method="post" style="margin-bottom: 10px;">
            <?php wp_nonce_field('visor_pdf_actions'); ?>
            <input type="hidden" name="action" value="reinstall">
            <button type="submit" class="button button-secondary" 
                    onclick="return confirm('¿Estás seguro de que quieres reinstalar completamente el plugin? Esto recreará todas las tablas y carpetas.')">
                🔄 Reinstalar Completamente
            </button>
        </form>
        
        <?php if (Visor_PDF_Plugin_Installer::needs_update()): ?>
        <form method="post" style="margin-bottom: 10px;">
            <?php wp_nonce_field('visor_pdf_actions'); ?>
            <input type="hidden" name="action" value="update">
            <button type="submit" class="button button-primary">
                ⬆️ Ejecutar Actualización
            </button>
        </form>
        <?php endif; ?>
        
        <form method="post" style="margin-bottom: 10px;">
            <?php wp_nonce_field('visor_pdf_actions'); ?>
            <input type="hidden" name="action" value="create_directories">
            <button type="submit" class="button button-secondary">
                📁 Crear Directorios Faltantes
            </button>
        </form>
    </div>
    
    <!-- Información de Diagnóstico -->
    <div class="card">
        <h2>🩺 Información de Diagnóstico</h2>
        <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">
Visor PDF Crisman - Diagnóstico
================================
Versión Plugin: <?php echo VISOR_PDF_CRISMAN_VERSION; ?>

Versión Instalada: <?php echo $current_version; ?>

Instalación: <?php echo $installation_date; ?>

Última Actualización: <?php echo $last_update; ?>

WordPress: <?php echo get_bloginfo('version'); ?>

PHP: <?php echo PHP_VERSION; ?>

MySQL: <?php echo $wpdb->db_version(); ?>

Tablas:
<?php foreach ($tables_status as $table): ?>
- <?php echo $table['name']; ?>: <?php echo $table['exists'] ? 'OK (' . $table['count'] . ' registros)' : 'FALTANTE'; ?>

<?php endforeach; ?>

Carpetas: <?php echo $folders_count; ?> | Actas: <?php echo $actas_count; ?>

Directorio Upload: <?php echo $upload_dir['basedir']; ?>
        </textarea>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.widefat th,
.widefat td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.widefat tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 2px;
    font-family: monospace;
}
</style>
