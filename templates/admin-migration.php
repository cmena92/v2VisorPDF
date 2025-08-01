<?php
/**
 * Template: Herramientas de Migración del Plugin
 * 
 * @package VisorPDFCrisman
 * @version 2.0.8
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Procesamiento de acciones
$message = '';
$message_type = '';

if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'visor_pdf_migration')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'run_migration':
            $result = Visor_PDF_Migration_Helper::run_full_migration();
            if ($result) {
                $message = '✅ Migración completada exitosamente';
                $message_type = 'success';
            } else {
                $message = '❌ Error durante la migración';
                $message_type = 'error';
            }
            break;
            
        case 'repair_issues':
            $result = Visor_PDF_Migration_Helper::repair_common_issues();
            if ($result['success']) {
                $message = '🔧 Se repararon ' . $result['total_fixes'] . ' problemas: ' . implode(', ', $result['issues_fixed']);
                $message_type = 'success';
            } else {
                $message = '❌ Error durante la reparación';
                $message_type = 'error';
            }
            break;
            
        case 'cleanup_migration':
            $result = Visor_PDF_Migration_Helper::cleanup_migration();
            if ($result['success']) {
                $message = '🧹 Datos de migración limpiados correctamente';
                $message_type = 'success';
            }
            break;
    }
}

// Obtener estado actual
$current_state = Visor_PDF_Migration_Helper::check_current_state();
$migration_backup = get_option('visor_pdf_migration_backup', null);

?>

<div class="wrap">
    <h1>🔄 Herramientas de Migración - Visor PDF Crisman</h1>
    
    <?php if ($message): ?>
    <div class="notice notice-<?php echo $message_type; ?>">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Estado del Sistema -->
    <div class="card">
        <h2>📊 Estado Actual del Sistema</h2>
        <div class="migration-status-grid">
            <div class="status-item">
                <h4>📋 Tablas de Base de Datos</h4>
                <ul>
                    <?php foreach ($current_state['tables'] as $table => $info): ?>
                    <li>
                        <?php if ($info['exists']): ?>
                            <span class="status-ok">✅</span> <?php echo $table; ?> 
                            <small>(<?php echo number_format($info['count']); ?> registros)</small>
                        <?php else: ?>
                            <span class="status-error">❌</span> <?php echo $table; ?> <small>(no existe)</small>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="status-item">
                <h4>📁 Contenido del Sistema</h4>
                <ul>
                    <li><strong>Carpetas:</strong> <?php echo number_format($current_state['folders']); ?></li>
                    <li><strong>Actas:</strong> <?php echo number_format($current_state['actas']); ?></li>
                    <li><strong>Directorio base:</strong> 
                        <?php if ($current_state['directories']['base']): ?>
                            <span class="status-ok">✅ Existe</span>
                        <?php else: ?>
                            <span class="status-error">❌ No existe</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>Permisos:</strong>
                        <?php if ($current_state['directories']['writable']): ?>
                            <span class="status-ok">✅ Escribible</span>
                        <?php else: ?>
                            <span class="status-warning">⚠️ Sin permisos</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            
            <div class="status-item">
                <h4>⚙️ Opciones del Sistema</h4>
                <ul>
                    <?php foreach ($current_state['options'] as $option => $value): ?>
                    <li>
                        <?php if ($value !== 'no_existe'): ?>
                            <span class="status-ok">✅</span>
                        <?php else: ?>
                            <span class="status-error">❌</span>
                        <?php endif; ?>
                        <code><?php echo $option; ?></code>
                        <?php if ($value !== 'no_existe' && strlen($value) < 50): ?>
                            <small>(<?php echo esc_html($value); ?>)</small>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Herramientas de Migración -->
    <div class="card">
        <h2>🛠️ Herramientas de Migración</h2>
        <p>Estas herramientas te ayudan a migrar desde versiones anteriores del plugin o reparar problemas comunes.</p>
        
        <div class="migration-tools">
            <!-- Migración Completa -->
            <div class="tool-section">
                <h3>🔄 Migración Completa</h3>
                <p>Ejecuta un proceso completo de migración que incluye:</p>
                <ul>
                    <li>✅ Backup de datos existentes</li>
                    <li>✅ Instalación de nueva estructura</li>
                    <li>✅ Migración de datos a nuevo formato</li>
                    <li>✅ Verificación de integridad</li>
                </ul>
                
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('visor_pdf_migration'); ?>
                    <input type="hidden" name="action" value="run_migration">
                    <button type="submit" class="button button-primary button-large" 
                            onclick="return confirm('¿Estás seguro de ejecutar la migración completa? Se hará un backup automático de los datos actuales.')">
                        🔄 Ejecutar Migración Completa
                    </button>
                </form>
            </div>
            
            <!-- Reparación de Problemas -->
            <div class="tool-section">
                <h3>🔧 Reparar Problemas Comunes</h3>
                <p>Repara problemas específicos sin ejecutar migración completa:</p>
                <ul>
                    <li>🔨 Recrear carpetas faltantes</li>
                    <li>🔨 Reparar estructura de directorios</li>
                    <li>🔨 Actualizar opciones del sistema</li>
                    <li>🔨 Asignar actas huérfanas</li>
                    <li>🔨 Reparar jerarquía de carpetas</li>
                </ul>
                
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('visor_pdf_migration'); ?>
                    <input type="hidden" name="action" value="repair_issues">
                    <button type="submit" class="button button-secondary">
                        🔧 Reparar Problemas
                    </button>
                </form>
            </div>
            
            <?php if ($migration_backup): ?>
            <!-- Limpieza de Datos -->
            <div class="tool-section">
                <h3>🧹 Limpiar Datos de Migración</h3>
                <p>Elimina los datos de backup de migración para liberar espacio:</p>
                <ul>
                    <li>📅 Fecha de backup: <?php echo $migration_backup['timestamp']; ?></li>
                    <li>📊 Tablas respaldadas: <?php echo count($migration_backup['tables']); ?></li>
                    <li>📄 Archivos encontrados: <?php echo $migration_backup['files_count']; ?></li>
                </ul>
                
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('visor_pdf_migration'); ?>
                    <input type="hidden" name="action" value="cleanup_migration">
                    <button type="submit" class="button button-secondary">
                        🧹 Limpiar Datos de Migración
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Información de Migración -->
    <?php if ($migration_backup): ?>
    <div class="card">
        <h2>📋 Información del Último Backup</h2>
        <div class="backup-info">
            <h4>📊 Datos Respaldados:</h4>
            <ul>
                <?php foreach ($migration_backup['tables'] as $table => $data): ?>
                <li><strong><?php echo $table; ?>:</strong> <?php echo count($data); ?> registros</li>
                <?php endforeach; ?>
            </ul>
            
            <h4>⚙️ Opciones Respaldadas:</h4>
            <ul>
                <?php foreach ($migration_backup['options'] as $option => $value): ?>
                <li><code><?php echo $option; ?></code></li>
                <?php endforeach; ?>
            </ul>
            
            <details>
                <summary>Ver datos de backup completos (JSON)</summary>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 11px;">
<?php echo json_encode($migration_backup, JSON_PRETTY_PRINT); ?>
                </textarea>
            </details>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Guía de Migración -->
    <div class="card">
        <h2>📖 Guía de Migración</h2>
        <div class="migration-guide">
            <h3>¿Cuándo usar cada herramienta?</h3>
            
            <div class="guide-section">
                <h4>🔄 Migración Completa</h4>
                <p><strong>Usar cuando:</strong></p>
                <ul>
                    <li>Actualizas desde una versión muy antigua (< 2.0)</li>
                    <li>Tienes problemas graves de estructura de datos</li>
                    <li>Necesitas una instalación limpia preservando datos</li>
                    <li>Es la primera vez que usas el nuevo sistema</li>
                </ul>
            </div>
            
            <div class="guide-section">
                <h4>🔧 Reparar Problemas</h4>
                <p><strong>Usar cuando:</strong></p>
                <ul>
                    <li>Faltan algunas carpetas o directorios</li>
                    <li>Hay actas sin carpeta asignada</li>
                    <li>Los permisos de archivos están mal</li>
                    <li>Solo necesitas arreglos menores</li>
                </ul>
            </div>
            
            <div class="guide-section">
                <h4>⚠️ Precauciones</h4>
                <ul>
                    <li>🔐 Siempre haz un backup manual antes de migraciones importantes</li>
                    <li>🕐 La migración completa puede tomar varios minutos</li>
                    <li>👥 Informa a los usuarios antes de ejecutar migraciones</li>
                    <li>🔍 Verifica el estado del sistema después de cada operación</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.migration-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.status-item {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.status-item h4 {
    margin-top: 0;
    color: #0073aa;
}

.status-item ul {
    margin: 0;
    padding-left: 20px;
}

.status-item li {
    margin-bottom: 5px;
}

.status-ok {
    color: #00a32a;
    font-weight: bold;
}

.status-error {
    color: #d63638;
    font-weight: bold;
}

.status-warning {
    color: #dba617;
    font-weight: bold;
}

.migration-tools {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.tool-section {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.tool-section h3 {
    margin-top: 0;
    color: #0073aa;
}

.tool-section ul {
    margin-bottom: 15px;
}

.button-large {
    padding: 8px 16px !important;
    height: auto !important;
    font-size: 14px !important;
}

.backup-info {
    background: #f0f6fc;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #0969da;
}

.backup-info h4 {
    color: #0969da;
    margin-bottom: 10px;
}

.backup-info ul {
    margin-bottom: 15px;
}

.migration-guide {
    background: #fffbf0;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #dba617;
}

.guide-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.guide-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.guide-section h4 {
    color: #b7730c;
    margin-bottom: 10px;
}

code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 2px;
    font-family: monospace;
    font-size: 12px;
}

details {
    margin-top: 15px;
}

details summary {
    cursor: pointer;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 4px;
}

details[open] summary {
    margin-bottom: 10px;
}
</style>
