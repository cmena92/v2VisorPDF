<?php
// Verificación sintáctica simple del plugin
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificación Sintáctica del Plugin</h1>";

// Verificar archivo principal
$main_file = __DIR__ . '/visor-pdf-crisman.php';
if (file_exists($main_file)) {
    echo "<p style='color: green;'>✅ Archivo principal existe: $main_file</p>";
    
    // Verificar sintaxis PHP
    $output = shell_exec("php -l " . escapeshellarg($main_file) . " 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "<p style='color: green;'>✅ Sintaxis PHP correcta</p>";
    } else {
        echo "<p style='color: red;'>❌ Error de sintaxis:</p>";
        echo "<pre>$output</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ Archivo principal no encontrado</p>";
}

// Verificar archivos include
$required_files = [
    'includes/install-utils.php',
    'includes/security-config.php', 
    'includes/class-visor-core.php',
    'includes/class-folders-manager.php',
    'includes/class-mass-upload.php',
    'includes/class-frontend-navigation.php',
    'includes/class-analytics.php'
];

echo "<h2>Verificación de Archivos Include</h2>";
foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✅ $file</p>";
        
        // Verificar sintaxis
        $output = shell_exec("php -l " . escapeshellarg($full_path) . " 2>&1");
        if (strpos($output, 'No syntax errors') === false) {
            echo "<p style='color: orange;'>⚠️ Posible error de sintaxis en $file:</p>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ $file - NO ENCONTRADO</p>";
    }
}

// Verificar templates
$template_files = [
    'templates/admin-list.php',
    'templates/admin-upload.php',
    'templates/admin-logs.php',
    'templates/admin-analytics.php',
    'templates/viewer.php'
];

echo "<h2>Verificación de Templates</h2>";
foreach ($template_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✅ $file</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ $file - Opcional, no crítico</p>";
    }
}

echo "<h2>Verificación de Assets</h2>";
$asset_files = [
    'assets/visor-pdf.js',
    'assets/visor-pdf.css'
];

foreach ($asset_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✅ $file</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ $file - Asset faltante</p>";
    }
}

echo "<p><strong>Verificación completada.</strong></p>";
?>
