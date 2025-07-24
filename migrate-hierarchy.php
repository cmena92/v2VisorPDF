<?php
/**
 * Script para migrar carpetas existentes a estructura jerárquica
 * Ejecutar desde admin de WordPress
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

echo "<h2>🔧 Migración a Estructura Jerárquica - Visor PDF Crisman</h2>\n";

// Obtener todas las carpetas actuales
global $wpdb;
$table_folders = $wpdb->prefix . 'actas_folders';
$table_metadata = $wpdb->prefix . 'actas_metadata';

$carpetas_actuales = $wpdb->get_results("SELECT * FROM $table_folders ORDER BY order_index, name");

echo "<h3>📊 Carpetas Actuales</h3>\n";
if (empty($carpetas_actuales)) {
    echo "❌ No hay carpetas configuradas<br>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background: #f1f1f1;'><th>ID</th><th>Nombre</th><th>Parent ID</th><th>Visible</th></tr>\n";
    foreach ($carpetas_actuales as $carpeta) {
        echo "<tr>";
        echo "<td>{$carpeta->id}</td>";
        echo "<td>{$carpeta->name}</td>";
        echo "<td>" . ($carpeta->parent_id ?: 'NULL') . "</td>";
        echo "<td>" . ($carpeta->visible_frontend ? "✅" : "❌") . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h3>🔄 Opciones de Migración</h3>\n";

?>

<button onclick="migrateToHierarchy()" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px;">
    📁 Migrar a Estructura Jerárquica
</button>

<button onclick="resetFolders()" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px;">
    🗑️ Resetear Carpetas (Crear Nuevas)
</button>

<div id="migration-log" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 20px 0; min-height: 100px;">
    <strong>📋 Log de migración:</strong><br>
    Selecciona una opción para comenzar...
</div>

<script>
function logMessage(message) {
    const logDiv = document.getElementById('migration-log');
    const timestamp = new Date().toLocaleTimeString();
    logDiv.innerHTML += `[${timestamp}] ${message}<br>`;
    logDiv.scrollTop = logDiv.scrollHeight;
}

function migrateToHierarchy() {
    if (!confirm('¿Migrar carpetas existentes a estructura jerárquica?\n\nEsto creará "Actas de Junta Directiva" como carpeta padre y moverá los años (2024, 2025, etc.) como carpetas hijas.')) {
        return;
    }
    
    logMessage('🚀 Iniciando migración a estructura jerárquica...');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'migrate_to_hierarchy',
            nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                logMessage('✅ ' + response.data.message);
                if (response.data.steps) {
                    response.data.steps.forEach(step => logMessage('  • ' + step));
                }
                setTimeout(() => location.reload(), 2000);
            } else {
                logMessage('❌ Error: ' + response.data);
            }
        },
        error: function() {
            logMessage('❌ Error de conexión');
        }
    });
}

function resetFolders() {
    if (!confirm('¿Resetear TODAS las carpetas y crear la estructura jerárquica desde cero?\n\n⚠️ ADVERTENCIA: Esto eliminará todas las carpetas existentes y reasignará las actas.')) {
        return;
    }
    
    logMessage('🗑️ Reseteando carpetas y creando estructura nueva...');
    
    // Primero eliminar carpetas existentes
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'reset_folders',
            nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                logMessage('✅ Carpetas eliminadas exitosamente');
                
                // Crear nuevas carpetas jerárquicas
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'create_sample_folders',
                        nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>'
                    },
                    success: function(response2) {
                        if (response2.success) {
                            logMessage('✅ Estructura jerárquica creada exitosamente');
                            if (response2.data.folders_created) {
                                response2.data.folders_created.forEach(folder => logMessage('  • ' + folder));
                            }
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            logMessage('❌ Error creando estructura: ' + response2.data);
                        }
                    },
                    error: function() {
                        logMessage('❌ Error de conexión al crear estructura');
                    }
                });
            } else {
                logMessage('❌ Error: ' + response.data);
            }
        },
        error: function() {
            logMessage('❌ Error de conexión');
        }
    });
}
</script>

<?php
echo "<h3>✅ Instrucciones</h3>\n";
echo "<div style='background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;'>\n";
echo "<strong>📁 Migrar a Estructura Jerárquica:</strong><br>\n";
echo "• Mantiene las carpetas existentes<br>\n";
echo "• Crea 'Actas de Junta Directiva' como carpeta padre<br>\n";
echo "• Mueve años (2024, 2025, etc.) como carpetas hijas<br>\n";
echo "• Reasigna las actas automáticamente<br><br>\n";
echo "<strong>🗑️ Resetear Carpetas:</strong><br>\n";
echo "• ⚠️ Elimina TODAS las carpetas existentes<br>\n";
echo "• Crea estructura jerárquica completamente nueva<br>\n";
echo "• Reasigna todas las actas a las nuevas carpetas<br>\n";
echo "</div>\n";
?>
