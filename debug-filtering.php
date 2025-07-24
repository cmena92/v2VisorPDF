<?php
/**
 * Debug específico para el filtrado de carpetas
 * Verificar que el endpoint AJAX funciona correctamente
 */

// Solo administradores
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

echo '<div style="background: white; padding: 20px; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
echo '<h1>🔧 Debug del Filtrado por Carpetas</h1>';

// Incluir la clase del navegador
require_once VISOR_PDF_CRISMAN_PLUGIN_DIR . 'includes/class-frontend-navigation.php';

$nav = new Visor_PDF_Frontend_Navigation();

// 1. Verificar que el endpoint existe
echo '<h2>1. Verificación del Endpoint AJAX</h2>';

global $wp_filter;
$unified_navigator_registered = isset($wp_filter['wp_ajax_unified_navigator']) || isset($wp_filter['wp_ajax_nopriv_unified_navigator']);

if ($unified_navigator_registered) {
    echo '<p style="color: green;">✅ Endpoint "unified_navigator" está registrado</p>';
} else {
    echo '<p style="color: red;">❌ Endpoint "unified_navigator" NO está registrado</p>';
}

// 2. Verificar que el método existe en la clase
echo '<h2>2. Verificación del Método</h2>';

if (method_exists($nav, 'ajax_unified_navigator')) {
    echo '<p style="color: green;">✅ Método "ajax_unified_navigator" existe en la clase</p>';
} else {
    echo '<p style="color: red;">❌ Método "ajax_unified_navigator" NO existe en la clase</p>';
}

// 3. Probar manualmente el filtrado
echo '<h2>3. Prueba Manual del Filtrado</h2>';

// Simular la llamada AJAX
global $wpdb;
$table_folders = $wpdb->prefix . 'actas_folders';
$table_metadata = $wpdb->prefix . 'actas_metadata';

// Obtener todas las carpetas
$carpetas = $wpdb->get_results("SELECT id, name FROM $table_folders WHERE visible_frontend = 1 ORDER BY name");

echo '<h3>Carpetas Disponibles:</h3>';
echo '<table border="1" style="border-collapse: collapse; margin-bottom: 20px;">';
echo '<tr><th>ID</th><th>Nombre</th><th>Actas</th><th>Probar</th></tr>';

foreach ($carpetas as $carpeta) {
    $actas_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_metadata WHERE folder_id = %d AND status = 'active'",
        $carpeta->id
    ));
    
    echo '<tr>';
    echo '<td>' . $carpeta->id . '</td>';
    echo '<td>' . esc_html($carpeta->name) . '</td>';
    echo '<td>' . $actas_count . '</td>';
    echo '<td><button onclick="testFolder(' . $carpeta->id . ')" style="background: #0073aa; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Probar</button></td>';
    echo '</tr>';
}

// Agregar "Todas las actas"
$total_actas = $wpdb->get_var("SELECT COUNT(*) FROM $table_metadata WHERE status = 'active'");
echo '<tr>';
echo '<td>0</td>';
echo '<td><strong>Todas las actas</strong></td>';
echo '<td>' . $total_actas . '</td>';
echo '<td><button onclick="testFolder(0)" style="background: #dc3232; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Probar</button></td>';
echo '</tr>';

echo '</table>';

// 4. Área de resultados
echo '<h3>Resultados de Pruebas:</h3>';
echo '<div id="test-results" style="background: #f9f9f9; padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; min-height: 200px;">Haz clic en "Probar" junto a cualquier carpeta para ver los resultados del filtrado.</div>';

// 5. Verificar variables JavaScript necesarias
echo '<h2>4. Verificación de Variables JavaScript</h2>';
echo '<div id="js-verification" style="background: #f0f0f0; padding: 15px; border-radius: 4px;"></div>';

// 6. Probar el shortcode directamente
echo '<h2>5. Prueba del Shortcode Completo</h2>';
echo '<p>El shortcode <code>[actas_navigator_visual]</code> se renderizaría así:</p>';

// Verificar permisos del usuario actual
$numero_colegiado = get_user_meta(get_current_user_id(), 'numero_colegiado', true);

if (!$numero_colegiado) {
    echo '<div style="background: #ffebee; border: 1px solid #f44336; padding: 15px; border-radius: 4px; color: #d32f2f;">';
    echo '<strong>⚠️ Tu usuario no tiene número de colegiado.</strong><br>';
    echo 'Para que el shortcode funcione, necesitas agregar un número de colegiado en tu perfil de usuario.';
    echo '</div>';
} else {
    echo '<div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 4px; color: #2e7d32;">';
    echo '<strong>✅ Tu usuario tiene número de colegiado: ' . esc_html($numero_colegiado) . '</strong><br>';
    echo 'El shortcode debería funcionar para ti.';
    echo '</div>';
    
    echo '<h4>Vista previa del selector:</h4>';
    echo '<div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin: 15px 0;">';
    
    // Obtener carpetas para el selector
    $folders = $nav->get_folders_for_selector();
    
    echo '<select style="width: 100%; padding: 10px; font-size: 14px;" onchange="testFolderFromSelector(this.value)">';
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
}

?>

<script>
// Verificar variables JavaScript al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const verificationDiv = document.getElementById('js-verification');
    let html = '';
    
    // Verificar jQuery
    html += 'jQuery disponible: ' + (typeof window.jQuery !== 'undefined' ? '✅ Sí' : '❌ No') + '\n';
    
    // Verificar variables del navegador
    html += 'visualNavigator disponible: ' + (typeof window.visualNavigator !== 'undefined' ? '✅ Sí' : '❌ No') + '\n';
    html += 'visualNavigatorData disponible: ' + (typeof window.visualNavigatorData !== 'undefined' ? '✅ Sí' : '❌ No') + '\n';
    
    if (typeof window.visualNavigator !== 'undefined') {
        html += 'AJAX URL: ' + window.visualNavigator.ajax_url + '\n';
        html += 'Nonce: ' + window.visualNavigator.nonce + '\n';
    }
    
    verificationDiv.textContent = html;
});

// Función para probar filtrado por carpeta
function testFolder(folderId) {
    const resultsDiv = document.getElementById('test-results');
    resultsDiv.textContent = '🔄 Probando filtrado para carpeta ID: ' + folderId + '...\n';
    
    const formData = new FormData();
    formData.append('action', 'unified_navigator');
    formData.append('nonce', '<?php echo wp_create_nonce('actas_nonce'); ?>');
    formData.append('folder_id', folderId);
    formData.append('page', 1);
    formData.append('per_page', 10);
    
    // Mostrar datos que se envían
    resultsDiv.textContent += '\nDatos enviados:\n';
    resultsDiv.textContent += '- Action: unified_navigator\n';
    resultsDiv.textContent += '- Folder ID: ' + folderId + '\n';
    resultsDiv.textContent += '- Page: 1\n';
    resultsDiv.textContent += '- Per Page: 10\n\n';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        resultsDiv.textContent += 'Respuesta recibida:\n';
        
        try {
            const json = JSON.parse(text);
            if (json.success) {
                resultsDiv.textContent += '✅ SUCCESS\n';
                resultsDiv.textContent += 'Actas encontradas: ' + json.data.actas.length + '\n';
                resultsDiv.textContent += 'Total en BD: ' + json.data.pagination.total + '\n';
                resultsDiv.textContent += 'Carpeta actual: ' + (json.data.current_folder ? json.data.current_folder.name : 'Todas') + '\n';
                resultsDiv.textContent += 'Breadcrumb: ' + json.data.breadcrumb.map(b => b.name).join(' → ') + '\n\n';
                
                if (json.data.actas.length > 0) {
                    resultsDiv.textContent += 'Primera acta:\n';
                    resultsDiv.textContent += '- ID: ' + json.data.actas[0].id + '\n';
                    resultsDiv.textContent += '- Título: ' + json.data.actas[0].title + '\n';
                    resultsDiv.textContent += '- Carpeta: ' + json.data.actas[0].folder_name + '\n';
                }
            } else {
                resultsDiv.textContent += '❌ ERROR\n';
                resultsDiv.textContent += 'Mensaje: ' + (json.data || 'Error desconocido') + '\n';
            }
        } catch (e) {
            resultsDiv.textContent += '❌ RESPUESTA NO ES JSON\n';
            resultsDiv.textContent += 'Respuesta cruda:\n' + text.substring(0, 500) + (text.length > 500 ? '...' : '') + '\n';
        }
    })
    .catch(error => {
        resultsDiv.textContent += '❌ ERROR DE RED\n';
        resultsDiv.textContent += 'Error: ' + error.message + '\n';
    });
}

// Función para probar desde el selector visual
function testFolderFromSelector(folderId) {
    if (folderId) {
        testFolder(parseInt(folderId));
    }
}
</script>

<?php
echo '</div>';
?>