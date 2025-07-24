<?php
/**
 * Prueba Simple del Navegador Visual
 * Para verificar si los scripts se cargan correctamente
 */

// Solo administradores
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

echo '<div style="background: white; padding: 20px; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
echo '<h1>🧪 Prueba Simple del Navegador Visual</h1>';

// Verificar número de colegiado del usuario actual
$numero_colegiado = get_user_meta(get_current_user_id(), 'numero_colegiado', true);

if (!$numero_colegiado) {
    echo '<div style="background: #ffebee; border: 1px solid #f44336; padding: 15px; border-radius: 4px; color: #d32f2f; margin-bottom: 20px;">';
    echo '<h3>⚠️ Problema de Configuración</h3>';
    echo '<p><strong>Tu usuario no tiene número de colegiado asignado.</strong></p>';
    echo '<p>Para que el navegador funcione, necesitas:</p>';
    echo '<ol>';
    echo '<li>Ir a <strong>Usuarios → Tu Perfil</strong></li>';
    echo '<li>Agregar un <strong>Número de Colegiado</strong></li>';
    echo '<li>Guardar los cambios</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 4px; color: #2e7d32; margin-bottom: 20px;">';
    echo '<h3>✅ Configuración Correcta</h3>';
    echo '<p><strong>Tu número de colegiado:</strong> ' . esc_html($numero_colegiado) . '</p>';
    echo '<p>El navegador debería funcionar correctamente.</p>';
    echo '</div>';
}

echo '<h2>📋 Shortcode del Navegador</h2>';
echo '<p>El siguiente es el resultado del shortcode <code>[actas_navigator_visual]</code>:</p>';

echo '<div style="border: 2px solid #0073aa; padding: 20px; border-radius: 4px; background: #f9f9f9;">';

// Ejecutar el shortcode directamente
if ($numero_colegiado) {
    echo do_shortcode('[actas_navigator_visual]');
} else {
    echo '<p style="color: #d32f2f; font-weight: bold;">⚠️ No se puede mostrar el navegador sin número de colegiado</p>';
}

echo '</div>';

echo '<h2>🔧 Instrucciones de Debugging</h2>';
echo '<div style="background: #f0f0f0; padding: 15px; border-radius: 4px;">';
echo '<p><strong>Para verificar que funciona:</strong></p>';
echo '<ol>';
echo '<li><strong>Abre las herramientas de desarrollador</strong> (F12)</li>';
echo '<li><strong>Ve a la pestaña "Console"</strong></li>';
echo '<li><strong>Busca mensajes como:</strong>';
echo '<ul>';
echo '<li>🚀 "Navegador Visual JS iniciando..."</li>';
echo '<li>🚀 "DOM listo, inicializando navegador visual..."</li>';
echo '<li>✅ "Navegador visual inicializado"</li>';
echo '</ul>';
echo '</li>';
echo '<li><strong>Intenta cambiar de carpeta</strong> en el selector</li>';
echo '<li><strong>Verifica que aparezcan logs como:</strong>';
echo '<ul>';
echo '<li>"Carpeta seleccionada: [ID]"</li>';
echo '<li>"Filtros actualizados: [objeto]"</li>';
echo '<li>"loadActas iniciado con filtros: [objeto]"</li>';
echo '</ul>';
echo '</li>';
echo '</ol>';
echo '</div>';

echo '<h2>🚀 Enlaces Útiles</h2>';
echo '<ul>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman-debug-filtering') . '">🔧 Debug Completo del Filtrado</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=visor-pdf-crisman-fix-structure') . '">🗂️ Corregir Estructura de Carpetas</a></li>';
echo '<li><a href="' . admin_url('profile.php') . '">👤 Editar tu Perfil (agregar número de colegiado)</a></li>';
echo '</ul>';

echo '</div>';
?>