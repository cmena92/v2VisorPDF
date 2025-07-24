<?php
/**
 * Prueba Rápida del Navegador Visual
 * Para verificar que las correcciones funcionan
 */

// Solo administradores
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

echo '<div style="background: white; padding: 20px; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
echo '<h1>🧪 Prueba Rápida del Navegador Visual</h1>';

// Incluir la clase del navegador
require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-frontend-navigation.php';

$nav = new Visor_PDF_Frontend_Navigation();

echo '<h2>1. Verificar Método get_folders_for_selector()</h2>';
$folders = $nav->get_folders_for_selector();

echo '<h3>Estructura Jerárquica:</h3>';
echo '<ul style="font-family: monospace; background: #f0f0f0; padding: 15px; border-radius: 4px;">';

function print_folder_tree($folders, $level = 0) {
    foreach ($folders as $folder) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $icon = $folder->id == 0 ? '📋' : ($level == 0 ? '📁' : '📄');
        
        echo '<li>' . $indent . $icon . ' <strong>' . esc_html($folder->name) . '</strong> (' . $folder->actas_count . ' actas)';
        
        if (!empty($folder->children)) {
            echo '<ul>';
            print_folder_tree($folder->children, $level + 1);
            echo '</ul>';
        }
        
        echo '</li>';
    }
}

print_folder_tree($folders);
echo '</ul>';

// Verificar que no hay duplicaciones
echo '<h2>2. Verificar Duplicaciones</h2>';

$all_folder_ids = array();
function collect_folder_ids($folders, &$ids) {
    foreach ($folders as $folder) {
        $ids[] = $folder->id;
        if (!empty($folder->children)) {
            collect_folder_ids($folder->children, $ids);
        }
    }
}

collect_folder_ids($folders, $all_folder_ids);

$duplicates = array_diff_key($all_folder_ids, array_unique($all_folder_ids));

if (empty($duplicates)) {
    echo '<p style="color: green;">✅ No se encontraron duplicaciones en la estructura jerárquica</p>';
} else {
    echo '<p style="color: red;">❌ Se encontraron duplicaciones: ' . implode(', ', $duplicates) . '</p>';
}

// Previsualizar cómo se vería el selector
echo '<h2>3. Preview del Selector</h2>';

echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 15px 0;">';
echo '<h4>Selector como se renderizaría:</h4>';

echo '<select style="width: 100%; padding: 10px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';

foreach ($folders as $folder) {
    // Skip "Archivo Histórico" si existe
    if (stripos($folder->name, 'Archivo Histórico') !== false) {
        continue;
    }
    
    // Si es "Todas las actas"
    if ($folder->id == 0) {
        echo '<option value="0">📋 ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')</option>';
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
        echo '📋 ' . esc_html($folder->name) . ' (' . number_format($folder->actas_count) . ')';
        echo '</option>';
    }
}

echo '</select>';
echo '</div>';

// Probar endpoint AJAX
echo '<h2>4. Probar Endpoint AJAX</h2>';
echo '<button onclick="testUnifiedNavigator()" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">🔬 Probar unified_navigator</button>';
echo '<div id="ajax-test-results" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;"></div>';

// Probar shortcode
echo '<h2>5. Probar Shortcode</h2>';
echo '<p>Para probar el shortcode en una página, usa:</p>';
echo '<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px;">[actas_navigator_visual]</code>';

echo '<h3>Simulación del Shortcode:</h3>';
echo '<div style="border: 2px dashed #ccc; padding: 20px; margin: 15px 0; background: #fafafa;">';

// Simular verificación de permisos
$numero_colegiado = get_user_meta(get_current_user_id(), 'numero_colegiado', true);

if (!$numero_colegiado) {
    echo '<p style="color: orange;">⚠️ Tu usuario no tiene número de colegiado asignado. El shortcode mostraría un mensaje de error.</p>';
    echo '<p>Ve a tu perfil de usuario y agrega un número de colegiado para poder usar el navegador.</p>';
} else {
    echo '<p style="color: green;">✅ Tu usuario tiene número de colegiado: <strong>' . esc_html($numero_colegiado) . '</strong></p>';
    echo '<p>El shortcode debería funcionar correctamente para ti.</p>';
}

echo '</div>';

// Enlaces útiles
echo '<h2>6. Enlaces Útiles</h2>';
echo '<ul>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman-fix-structure') . '">🔧 Corregir Estructura de Carpetas</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman-debug-navegador') . '">🔍 Debug Completo del Navegador</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman') . '">📋 Lista de Actas</a></li>';
echo '</ul>';

?>

<script>
function testUnifiedNavigator() {
    const resultsDiv = document.getElementById('ajax-test-results');
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
                <h4 style="color: green;">✅ Endpoint Funciona Correctamente</h4>
                <p><strong>Actas encontradas:</strong> ${data.data.actas.length}</p>
                <p><strong>Total en BD:</strong> ${data.data.pagination.total}</p>
                <p><strong>Breadcrumb:</strong> ${data.data.breadcrumb.map(b => b.name).join(' → ')}</p>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; color: #0073aa;">Ver primera acta de ejemplo</summary>
                    <pre style="background: white; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin-top: 5px;">${data.data.actas.length > 0 ? JSON.stringify(data.data.actas[0], null, 2) : 'No hay actas'}</pre>
                </details>
            `;
        } else {
            resultsDiv.innerHTML = `<h4 style="color: red;">❌ Error en Endpoint</h4><p>${data.data || 'Error desconocido'}</p>`;
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = `<h4 style="color: red;">❌ Error de Red</h4><p>${error.message}</p>`;
    });
}
</script>

<?php
echo '</div>';
?>