<?php
/**
 * Verificación Sintáctica Específica
 * Detecta errores de funciones duplicadas
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Verificación de Conflictos de Funciones</h1>";

// Lista de archivos a verificar
$files_to_check = [
    'visor-pdf-crisman.php',
    'includes/class-frontend-navigation.php',
    'includes/class-visor-core.php',
    'includes/class-folders-manager.php',
    'includes/class-mass-upload.php',
    'includes/class-analytics.php'
];

$base_path = __DIR__ . '/';
$errors_found = false;

foreach ($files_to_check as $file) {
    $full_path = $base_path . $file;
    
    echo "<h2>📄 Verificando: {$file}</h2>";
    
    if (!file_exists($full_path)) {
        echo "<p style='color: orange;'>⚠️ Archivo no encontrado: {$file}</p>";
        continue;
    }
    
    // Verificar sintaxis PHP
    $output = shell_exec("php -l " . escapeshellarg($full_path) . " 2>&1");
    
    if (strpos($output, 'No syntax errors') !== false) {
        echo "<p style='color: green;'>✅ Sintaxis correcta</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Error de sintaxis:</strong></p>";
        echo "<pre style='background: #ffe6e6; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($output) . "</pre>";
        $errors_found = true;
    }
    
    // Buscar funciones potencialmente duplicadas
    $content = file_get_contents($full_path);
    $functions = [];
    
    // Buscar declaraciones de función
    if (preg_match_all('/(?:public|private|protected)?\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $content, $matches)) {
        foreach ($matches[1] as $function_name) {
            if (!isset($functions[$function_name])) {
                $functions[$function_name] = 0;
            }
            $functions[$function_name]++;
        }
    }
    
    // Reportar funciones duplicadas
    $duplicated_functions = array_filter($functions, function($count) { return $count > 1; });
    
    if (!empty($duplicated_functions)) {
        echo "<p style='color: red;'>🚨 <strong>Funciones duplicadas encontradas:</strong></p>";
        echo "<ul>";
        foreach ($duplicated_functions as $func_name => $count) {
            echo "<li><strong>{$func_name}()</strong> - declarada {$count} veces</li>";
        }
        echo "</ul>";
        $errors_found = true;
    } else {
        echo "<p style='color: green;'>✅ No se encontraron funciones duplicadas</p>";
    }
    
    echo "<hr>";
}

// Verificar endpoints AJAX
echo "<h2>🔗 Verificación de Endpoints AJAX</h2>";

// Simular cargar WordPress mínimo
if (!function_exists('add_action')) {
    echo "<p style='color: orange;'>⚠️ WordPress no está disponible - no se pueden verificar endpoints</p>";
} else {
    global $wp_filter;
    
    $expected_endpoints = [
        'unified_navigator',
        'load_pdf_page',
        'get_folder_contents',
        'search_actas',
        'filter_actas'
    ];
    
    foreach ($expected_endpoints as $endpoint) {
        $logged_exists = isset($wp_filter['wp_ajax_' . $endpoint]);
        $public_exists = isset($wp_filter['wp_ajax_nopriv_' . $endpoint]);
        
        if ($logged_exists && $public_exists) {
            echo "<p style='color: green;'>✅ <strong>{$endpoint}</strong>: Registrado correctamente</p>";
        } elseif ($logged_exists) {
            echo "<p style='color: orange;'>⚠️ <strong>{$endpoint}</strong>: Solo para usuarios logueados</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>{$endpoint}</strong>: No registrado</p>";
        }
    }
}

// Resumen final
echo "<hr>";
echo "<h2>📊 Resumen Final</h2>";

if (!$errors_found) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px;'>";
    echo "<h3 style='margin: 0 0 10px 0;'>✅ Verificación Exitosa</h3>";
    echo "<p style='margin: 0;'>No se encontraron errores de sintaxis ni funciones duplicadas.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 6px;'>";
    echo "<h3 style='margin: 0 0 10px 0;'>❌ Errores Encontrados</h3>";
    echo "<p style='margin: 0;'>Se encontraron errores que deben corregirse antes de activar el plugin.</p>";
    echo "</div>";
}

echo "<p><small>Verificación ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";

// JavaScript para recargar automáticamente
echo "<script>
setTimeout(function() {
    if (confirm('¿Recargar la verificación para ver cambios?')) {
        location.reload();
    }
}, 5000);
</script>";
?>
