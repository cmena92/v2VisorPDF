<?php
/**
 * Script de Debug para Navegador Visual
 * Verificar estructura de carpetas y jerarquía
 */

// Solo permitir acceso a administradores
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

// Incluir archivos necesarios
require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-frontend-navigation.php';

$nav = new Visor_PDF_Frontend_Navigation();

echo '<div style="background: white; padding: 20px; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
echo '<h1>🔍 Debug del Navegador Visual</h1>';

// 1. Verificar estructura de base de datos
global $wpdb;
$table_folders = $wpdb->prefix . 'actas_folders';

echo '<h2>📊 Estructura de Base de Datos</h2>';

$folders_raw = $wpdb->get_results(
    "SELECT id, name, slug, parent_id, order_index, visible_frontend 
     FROM $table_folders 
     ORDER BY parent_id ASC, order_index ASC"
);

echo '<h3>Carpetas en Base de Datos:</h3>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Parent ID</th><th>Order</th><th>Visible</th></tr>';
foreach ($folders_raw as $folder) {
    echo '<tr>';
    echo '<td>' . $folder->id . '</td>';
    echo '<td>' . esc_html($folder->name) . '</td>';
    echo '<td>' . esc_html($folder->slug) . '</td>';
    echo '<td>' . ($folder->parent_id ?: 'NULL') . '</td>';
    echo '<td>' . $folder->order_index . '</td>';
    echo '<td>' . ($folder->visible_frontend ? 'Sí' : 'No') . '</td>';
    echo '</tr>';
}
echo '</table>';

// 2. Verificar método get_folders_for_selector
echo '<h2>🗂️ Resultado del Método get_folders_for_selector()</h2>';

$hierarchical_folders = $nav->get_folders_for_selector();

echo '<h3>Estructura Jerárquica Generada:</h3>';
echo '<pre style="background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto;">';

function debug_print_folders($folders, $level = 0) {
    foreach ($folders as $folder) {
        $indent = str_repeat('  ', $level);
        echo $indent . "📁 ID: {$folder->id} | Nombre: {$folder->name} | Actas: {$folder->actas_count}";
        
        if (isset($folder->parent_id)) {
            echo " | Parent: " . ($folder->parent_id ?: 'NULL');
        }
        
        echo "\n";
        
        if (!empty($folder->children)) {
            echo $indent . "  └── SUBCARPETAS:\n";
            debug_print_folders($folder->children, $level + 2);
        }
    }
}

debug_print_folders($hierarchical_folders);
echo '</pre>';

// 3. Mostrar cómo se renderizaría en el selector
echo '<h2>🎨 Preview del Selector HTML</h2>';

ob_start();
?>
<select style="width: 100%; padding: 10px; font-size: 14px;">
<?php
function render_folder_options_debug($folders, $level = 0) {
    foreach ($folders as $folder) {
        // Skip "Archivo Histórico" si existe
        if (stripos($folder->name, 'Archivo Histórico') !== false) {
            continue;
        }
        
        $indent = str_repeat('&nbsp;&nbsp;', $level);
        $icon = $level === 0 ? '📋' : '└──';
        
        // Si es "Todas las actas"
        if ($folder->id == 0) {
            echo '<option value="0">' . $icon . ' ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')</option>';
        }
        // Si tiene hijos, crear optgroup
        else if (!empty($folder->children)) {
            echo '<optgroup label="📁 ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')">';
            
            // Renderizar la carpeta padre como opción también
            echo '<option value="' . esc_attr($folder->id) . '" data-is-parent="true">';
            echo '📁 Ver todas en ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')';
            echo '</option>';
            
            // Renderizar hijos
            foreach ($folder->children as $child) {
                echo '<option value="' . esc_attr($child->id) . '" data-parent="' . esc_attr($folder->id) . '">';
                echo '&nbsp;&nbsp;└── ' . esc_html($child->name) . ' (' . number_format($child->actas_count) . ')';
                echo '</option>';
            }
            
            echo '</optgroup>';
        }
        // Carpeta sin hijos
        else {
            echo '<option value="' . esc_attr($folder->id) . '">';
            echo $indent . $icon . ' ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')';
            echo '</option>';
        }
    }
}

render_folder_options_debug($hierarchical_folders);
?>
</select>
<?php
$selector_html = ob_get_clean();
echo $selector_html;

// 4. Verificar conteos de actas
echo '<h2>📊 Verificación de Conteos de Actas</h2>';

$table_metadata = $wpdb->prefix . 'actas_metadata';

foreach ($hierarchical_folders as $folder) {
    if ($folder->id == 0) continue;
    
    $real_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_metadata WHERE folder_id = %d AND status = 'active'",
        $folder->id
    ));
    
    echo "<p><strong>{$folder->name}:</strong> Reportado: {$folder->actas_count}, Real: {$real_count}";
    if ($folder->actas_count != $real_count) {
        echo " <span style='color: red;'>❌ DISCREPANCIA</span>";
    } else {
        echo " <span style='color: green;'>✅ OK</span>";
    }
    echo "</p>";
}

// 5. Scripts para testing
echo '<h2>🧪 Testing del Shortcode</h2>';
echo '<p>Para probar el shortcode, usa: <code>[actas_navigator_visual]</code></p>';

echo '<h3>Testing AJAX</h3>';
echo '<button onclick="testNavigatorAjax()" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Probar AJAX Unificado</button>';
echo '<div id="ajax-results" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;"></div>';

?>
<script>
function testNavigatorAjax() {
    const resultsDiv = document.getElementById('ajax-results');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '🔄 Probando endpoint unified_navigator...';
    
    const formData = new FormData();
    formData.append('action', 'unified_navigator');
    formData.append('nonce', '<?php echo wp_create_nonce('actas_nonce'); ?>');
    formData.append('folder_id', 0);
    formData.append('page', 1);
    formData.append('per_page', 5);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultsDiv.innerHTML = `
                <h4>✅ AJAX Exitoso</h4>
                <p><strong>Actas encontradas:</strong> ${data.data.actas.length}</p>
                <p><strong>Total:</strong> ${data.data.pagination.total}</p>
                <p><strong>Breadcrumb:</strong> ${data.data.breadcrumb.map(b => b.name).join(' > ')}</p>
                <details>
                    <summary>Ver respuesta completa</summary>
                    <pre style="background: white; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;">${JSON.stringify(data, null, 2)}</pre>
                </details>
            `;
        } else {
            resultsDiv.innerHTML = `❌ Error: ${data.data || 'Respuesta inválida'}`;
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = `❌ Error de red: ${error.message}`;
    });
}
</script>
<?php

echo '</div>';
?>