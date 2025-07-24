<?php
/**
 * Script de prueba para verificar el funcionamiento del selector de carpetas
 * Debe ejecutarse desde el admin de WordPress
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

echo "<h2>🔧 Test del Selector de Carpetas - Visor PDF Crisman</h2>\n";

// Obtener instancia del plugin
$visor = VisorPDFCrisman::get_instance();

// Verificar que las tablas existen
global $wpdb;
$tables_to_check = [
    $wpdb->prefix . 'actas_metadata',
    $wpdb->prefix . 'actas_folders'
];

echo "<h3>📊 Verificación de Tablas</h3>\n";
foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    echo "- Tabla <code>$table</code>: " . ($exists ? "✅ OK" : "❌ NO EXISTE") . "<br>\n";
}

// Verificar carpetas
echo "<h3>📁 Carpetas Disponibles</h3>\n";
$carpetas = $wpdb->get_results("
    SELECT f.*, COUNT(a.id) as actas_count 
    FROM {$wpdb->prefix}actas_folders f
    LEFT JOIN {$wpdb->prefix}actas_metadata a ON f.id = a.folder_id AND a.status = 'active'
    WHERE f.visible_frontend = 1
    GROUP BY f.id
    ORDER BY f.order_index ASC, f.name ASC
");

if (empty($carpetas)) {
    echo "❌ No hay carpetas configuradas<br>\n";
    echo "<button onclick='createSampleFolders()' style='background: #0073aa; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer;'>Crear Carpetas de Ejemplo</button><br>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background: #f1f1f1;'><th>ID</th><th>Nombre</th><th>Slug</th><th>Actas</th><th>Estado</th></tr>\n";
    foreach ($carpetas as $carpeta) {
        echo "<tr>";
        echo "<td>{$carpeta->id}</td>";
        echo "<td>{$carpeta->name}</td>";
        echo "<td>{$carpeta->slug}</td>";
        echo "<td>{$carpeta->actas_count}</td>";
        echo "<td>" . ($carpeta->visible_frontend ? "✅ Visible" : "❌ Oculta") . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Verificar actas
echo "<h3>📄 Actas Disponibles</h3>\n";
$actas = $wpdb->get_results("
    SELECT a.id, a.title, a.folder_id, f.name as folder_name
    FROM {$wpdb->prefix}actas_metadata a
    LEFT JOIN {$wpdb->prefix}actas_folders f ON a.folder_id = f.id
    WHERE a.status = 'active'
    ORDER BY a.upload_date DESC
    LIMIT 10
");

if (empty($actas)) {
    echo "❌ No hay actas disponibles<br>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background: #f1f1f1;'><th>ID</th><th>Título</th><th>Carpeta ID</th><th>Carpeta</th></tr>\n";
    foreach ($actas as $acta) {
        echo "<tr>";
        echo "<td>{$acta->id}</td>";
        echo "<td>" . esc_html(substr($acta->title, 0, 50)) . "...</td>";
        echo "<td>{$acta->folder_id}</td>";
        echo "<td>" . ($acta->folder_name ?: 'Sin carpeta') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Verificar AJAX endpoints
echo "<h3>🌐 Verificación de Endpoints AJAX</h3>\n";
$ajax_actions = [
    'get_folder_actas' => 'Obtener actas por carpeta'
];

foreach ($ajax_actions as $action => $description) {
    $hook_exists = has_action("wp_ajax_$action") || has_action("wp_ajax_nopriv_$action");
    echo "- <code>$action</code> ($description): " . ($hook_exists ? "✅ Registrado" : "❌ NO REGISTRADO") . "<br>\n";
}

// Verificar archivos de template
echo "<h3>📄 Verificación de Templates</h3>\n";
$templates_to_check = [
    'templates/viewer-hybrid.php' => 'Template principal del visor híbrido',
    'templates/acta-card.php' => 'Template de tarjeta de acta'
];

foreach ($templates_to_check as $template_path => $description) {
    $full_path = VISOR_PDF_CRISMAN_PLUGIN_DIR . $template_path;
    $exists = file_exists($full_path);
    echo "- <code>$template_path</code> ($description): " . ($exists ? "✅ OK" : "❌ NO EXISTE") . "<br>\n";
}

// Test de función get_actas_for_hybrid
echo "<h3>🧪 Test de Función get_actas_for_hybrid</h3>\n";

// Usar reflexión para acceder al método privado
$reflection = new ReflectionClass($visor);
$method = $reflection->getMethod('get_actas_for_hybrid');
$method->setAccessible(true);

try {
    // Test 1: Todas las actas
    $todas_actas = $method->invoke($visor, array());
    echo "- Todas las actas: " . count($todas_actas) . " encontradas ✅<br>\n";
    
    // Test 2: Actas por carpeta (si existe alguna carpeta)
    if (!empty($carpetas)) {
        $primera_carpeta = $carpetas[0];
        $actas_carpeta = $method->invoke($visor, array('carpeta' => $primera_carpeta->id));
        echo "- Actas de carpeta '{$primera_carpeta->name}' (ID: {$primera_carpeta->id}): " . count($actas_carpeta) . " encontradas ✅<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en test: " . $e->getMessage() . "<br>\n";
}

echo "<h3>✅ Verificación Completada</h3>\n";
echo "<p><strong>Shortcode para usar:</strong> <code>[actas_hybrid]</code></p>\n";

?>

<script>
function createSampleFolders() {
    if (confirm('¿Crear carpetas de ejemplo para testing?')) {
        jQuery.post(ajaxurl, {
            action: 'create_sample_folders',
            nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('✅ Carpetas creadas: ' + response.data.message);
                location.reload();
            } else {
                alert('❌ Error: ' + response.data);
            }
        });
    }
}
</script>
