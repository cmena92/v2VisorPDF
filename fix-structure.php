<?php
/**
 * Script de Corrección de Estructura de Carpetas
 * Corrige las relaciones padre-hijo según la imagen del backend
 */

// Solo ejecutar si es administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

echo '<div style="background: white; padding: 20px; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
echo '<h1>🔧 Corrección de Estructura de Carpetas</h1>';

global $wpdb;
$table_folders = $wpdb->prefix . 'actas_folders';

// Verificar estado actual
echo '<h2>📋 Estado Actual</h2>';
$current_folders = $wpdb->get_results(
    "SELECT id, name, parent_id FROM $table_folders ORDER BY name"
);

echo '<table border="1" style="border-collapse: collapse; margin-bottom: 20px;">';
echo '<tr><th>ID</th><th>Nombre</th><th>Parent ID Actual</th></tr>';
foreach ($current_folders as $folder) {
    echo '<tr>';
    echo '<td>' . $folder->id . '</td>';
    echo '<td>' . esc_html($folder->name) . '</td>';
    echo '<td>' . ($folder->parent_id ?: 'NULL') . '</td>';
    echo '</tr>';
}
echo '</table>';

if (isset($_POST['fix_structure'])) {
    echo '<h2>🔄 Aplicando Correcciones...</h2>';
    
    // Paso 1: Identificar carpeta "Actas de Junta Directiva"
    $junta_directiva = $wpdb->get_row(
        "SELECT id FROM $table_folders WHERE name LIKE '%Junta Directiva%' LIMIT 1"
    );
    
    if ($junta_directiva) {
        echo "<p>✅ Encontrada carpeta 'Junta Directiva' con ID: {$junta_directiva->id}</p>";
        
        // Paso 2: Hacer que 2025, 2024, 2016 sean hijos de Junta Directiva
        $years = ['2025', '2024', '2016'];
        $updated_count = 0;
        
        foreach ($years as $year) {
            $year_folder = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_folders WHERE name = %s LIMIT 1",
                $year
            ));
            
            if ($year_folder) {
                $result = $wpdb->update(
                    $table_folders,
                    array('parent_id' => $junta_directiva->id),
                    array('id' => $year_folder->id),
                    array('%d'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo "<p>✅ Carpeta '$year' (ID: {$year_folder->id}) ahora es hija de Junta Directiva</p>";
                    $updated_count++;
                } else {
                    echo "<p>❌ Error actualizando carpeta '$year'</p>";
                }
            } else {
                echo "<p>⚠️ No se encontró carpeta '$year'</p>";
            }
        }
        
        // Paso 3: Asegurar que otras carpetas sean independientes
        $independent_folders = ['Actas de Asamblea', 'Sin Clasificar', 'Archivo Histórico'];
        
        foreach ($independent_folders as $folder_name) {
            $folder = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_folders WHERE name LIKE %s LIMIT 1",
                '%' . $folder_name . '%'
            ));
            
            if ($folder) {
                $result = $wpdb->update(
                    $table_folders,
                    array('parent_id' => null),
                    array('id' => $folder->id),
                    array('%d'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo "<p>✅ Carpeta '$folder_name' (ID: {$folder->id}) configurada como independiente</p>";
                } else {
                    echo "<p>❌ Error configurando carpeta '$folder_name'</p>";
                }
            }
        }
        
        echo "<h3>🎉 Corrección Completada</h3>";
        echo "<p>Se actualizaron <strong>$updated_count</strong> carpetas de años como subcarpetas de Junta Directiva.</p>";
        
    } else {
        echo "<p>❌ No se encontró la carpeta 'Actas de Junta Directiva'. Asegúrate de que existe en la base de datos.</p>";
    }
    
    // Mostrar nueva estructura
    echo '<h2>📋 Nueva Estructura</h2>';
    $new_folders = $wpdb->get_results(
        "SELECT id, name, parent_id FROM $table_folders ORDER BY parent_id ASC, name ASC"
    );
    
    echo '<table border="1" style="border-collapse: collapse; margin-bottom: 20px;">';
    echo '<tr><th>ID</th><th>Nombre</th><th>Parent ID</th><th>Nivel</th></tr>';
    foreach ($new_folders as $folder) {
        $level = $folder->parent_id ? 'Subcarpeta' : 'Carpeta Principal';
        echo '<tr>';
        echo '<td>' . $folder->id . '</td>';
        echo '<td>' . ($folder->parent_id ? '&nbsp;&nbsp;└── ' : '') . esc_html($folder->name) . '</td>';
        echo '<td>' . ($folder->parent_id ?: 'NULL') . '</td>';
        echo '<td>' . $level . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} else {
    // Mostrar botón para aplicar correcciones
    echo '<h2>🛠️ Aplicar Correcciones</h2>';
    echo '<p>Esta acción hará lo siguiente:</p>';
    echo '<ul>';
    echo '<li><strong>2025, 2024, 2016</strong> se convertirán en subcarpetas de <strong>"Actas de Junta Directiva"</strong></li>';
    echo '<li><strong>"Actas de Asamblea"</strong> permanecerá como carpeta independiente</li>';
    echo '<li><strong>"Sin Clasificar"</strong> y otras carpetas permanecerán independientes</li>';
    echo '</ul>';
    
    echo '<form method="post" style="margin: 20px 0;">';
    echo '<input type="hidden" name="fix_structure" value="1">';
    echo '<button type="submit" style="background: #dc3232; color: white; padding: 15px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;" onclick="return confirm(\'¿Estás seguro de que quieres aplicar estas correcciones a la estructura de carpetas?\')">🔧 Aplicar Correcciones</button>';
    echo '</form>';
}

// Botón para probar el navegador
echo '<h2>🧪 Probar Navegador</h2>';
echo '<p>Después de aplicar las correcciones, puedes probar el navegador:</p>';
echo '<ul>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman-debug-navegador') . '" target="_blank">🔍 Ver Debug del Navegador</a></li>';
echo '<li>Crear una página con el shortcode: <code>[actas_navigator_visual]</code></li>';
echo '</ul>';

echo '</div>';
?>