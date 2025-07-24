<?php
/**
 * Verificación Rápida del Navegador Visual
 * Página de prueba para verificar que todo funciona
 */

// Verificar que estamos en WordPress
if (!function_exists('add_action')) {
    echo '<h1>⚠️ Error: Este archivo debe ejecutarse desde WordPress</h1>';
    echo '<p>Sube este archivo a tu instalación de WordPress y accede via wp-admin.</p>';
    exit;
}

// Verificar que el plugin está activo
if (!class_exists('VisorPDFCrisman')) {
    echo '<h1>❌ Error: Plugin no activo</h1>';
    echo '<p>El plugin Visor PDF Crisman no está activo.</p>';
    exit;
}

echo '<h1>✅ Verificación del Navegador Visual</h1>';

// Verificar clases necesarias
echo '<h2>🔍 Verificación de Clases</h2>';
$required_classes = [
    'VisorPDFCrisman' => 'Clase principal',
    'Visor_PDF_Frontend_Navigation' => 'Navegación frontend',
    'Visor_PDF_Core' => 'Core del sistema'
];

foreach ($required_classes as $class => $description) {
    $exists = class_exists($class);
    $status = $exists ? '✅' : '❌';
    echo "<p>{$status} <strong>{$class}</strong>: {$description}</p>";
}

// Verificar shortcodes
echo '<h2>📝 Verificación de Shortcodes</h2>';
$shortcodes = [
    'actas_viewer' => 'Visor original',
    'actas_navigator_visual' => 'Navegador visual',
    'actas_navigator' => 'Navegador avanzado'
];

global $shortcode_tags;
foreach ($shortcodes as $shortcode => $description) {
    $exists = array_key_exists($shortcode, $shortcode_tags);
    $status = $exists ? '✅' : '❌';
    echo "<p>{$status} <strong>[{$shortcode}]</strong>: {$description}</p>";
}

// Verificar archivos del navegador visual
echo '<h2>📁 Verificación de Archivos</h2>';
$required_files = [
    'templates/visual-navigator.php' => 'Template del navegador',
    'assets/css/visual-navigator.css' => 'Estilos CSS',
    'assets/js/visual-navigator.js' => 'JavaScript',
    'includes/class-frontend-navigation.php' => 'Clase de navegación'
];

$plugin_dir = WP_PLUGIN_DIR . '/visor-actas-cpic/';
foreach ($required_files as $file => $description) {
    $full_path = $plugin_dir . $file;
    $exists = file_exists($full_path);
    $status = $exists ? '✅' : '❌';
    $size = $exists ? ' (' . human_readable_bytes(filesize($full_path)) . ')' : '';
    echo "<p>{$status} <strong>{$file}</strong>: {$description}{$size}</p>";
}

// Verificar endpoints AJAX
echo '<h2>🔗 Verificación de Endpoints AJAX</h2>';
global $wp_filter;
$ajax_actions = [
    'unified_navigator' => 'Navegador unificado',
    'load_pdf_page' => 'Cargar página PDF',
    'get_folder_contents' => 'Contenido de carpetas',
    'search_actas' => 'Búsqueda de actas'
];

foreach ($ajax_actions as $action => $description) {
    $logged_exists = isset($wp_filter['wp_ajax_' . $action]);
    $public_exists = isset($wp_filter['wp_ajax_nopriv_' . $action]);
    
    if ($logged_exists && $public_exists) {
        echo "<p>✅ <strong>{$action}</strong>: {$description} (público y privado)</p>";
    } elseif ($logged_exists) {
        echo "<p>⚠️ <strong>{$action}</strong>: {$description} (solo usuarios logueados)</p>";
    } else {
        echo "<p>❌ <strong>{$action}</strong>: {$description} (no registrado)</p>";
    }
}

// Verificar base de datos
echo '<h2>🗄️ Verificación de Base de Datos</h2>';
global $wpdb;
$required_tables = [
    'actas_metadata' => 'Metadatos de actas',
    'actas_folders' => 'Carpetas de organización',
    'actas_logs' => 'Logs de visualización',
    'actas_suspicious_logs' => 'Logs de actividades sospechosas'
];

foreach ($required_tables as $table => $description) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") == $full_table;
    $status = $exists ? '✅' : '❌';
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table}");
        echo "<p>{$status} <strong>{$table}</strong>: {$description} ({$count} registros)</p>";
    } else {
        echo "<p>{$status} <strong>{$table}</strong>: {$description} (tabla no existe)</p>";
    }
}

// Verificar carpetas de ejemplo
if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actas_folders'") == $wpdb->prefix . 'actas_folders') {
    echo '<h2>📂 Carpetas Disponibles</h2>';
    $folders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}actas_folders ORDER BY order_index");
    
    if (empty($folders)) {
        echo '<p>⚠️ No hay carpetas configuradas. <a href="#" onclick="createSampleFolders()">Crear carpetas de ejemplo</a></p>';
    } else {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Visible Frontend</th><th>Orden</th></tr>';
        foreach ($folders as $folder) {
            echo "<tr>";
            echo "<td>{$folder->id}</td>";
            echo "<td>{$folder->name}</td>";
            echo "<td>{$folder->slug}</td>";
            echo "<td>" . ($folder->visible_frontend ? 'Sí' : 'No') . "</td>";
            echo "<td>{$folder->order_index}</td>";
            echo "</tr>";
        }
        echo '</table>';
    }
}

// Mostrar ejemplo de uso
echo '<h2>🚀 Ejemplo de Uso</h2>';
echo '<p>Para usar el navegador visual en una página o entrada, usa este shortcode:</p>';
echo '<pre><code>[actas_navigator_visual]</code></pre>';

echo '<p>Con opciones personalizadas:</p>';
echo '<pre><code>[actas_navigator_visual per_page="8" show_search="true" show_date_filters="true" default_order="title"]</code></pre>';

// URLs de prueba
echo '<h2>🔗 URLs de Prueba</h2>';
$ajax_url = admin_url('admin-ajax.php');
echo "<p><strong>AJAX URL:</strong> <a href='{$ajax_url}' target='_blank'>{$ajax_url}</a></p>";

$nonce = wp_create_nonce('actas_nonce');
$test_url = $ajax_url . '?action=unified_navigator&nonce=' . $nonce;
echo "<p><strong>Test Endpoint:</strong> <a href='{$test_url}' target='_blank'>Probar unified_navigator</a></p>";

// JavaScript para crear carpetas de ejemplo
?>
<script>
function createSampleFolders() {
    if (confirm('¿Crear carpetas de ejemplo para el navegador?')) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_sample_folders&nonce=<?php echo wp_create_nonce('actas_nonce'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Carpetas creadas exitosamente!');
                location.reload();
            } else {
                alert('❌ Error: ' + (data.data || 'No se pudieron crear las carpetas'));
            }
        })
        .catch(error => {
            alert('❌ Error de conexión: ' + error.message);
        });
    }
}
</script>

<?php
function human_readable_bytes($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

echo '<hr>';
echo '<p><small>Verificación completada el ' . date('Y-m-d H:i:s') . '</small></p>';
?>
