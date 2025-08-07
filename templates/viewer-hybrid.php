<?php
if (!defined('ABSPATH')) exit;

// Obtener carpetas disponibles con jerarquía
global $wpdb;
$table_folders = $wpdb->prefix . 'actas_folders';

// Obtener todas las carpetas con conteo de actas
$all_carpetas = $wpdb->get_results("
    SELECT f.*, COUNT(a.id) as actas_count 
    FROM $table_folders f
    LEFT JOIN {$wpdb->prefix}actas_metadata a ON f.id = a.folder_id AND a.status = 'active'
    WHERE f.visible_frontend = 1
    GROUP BY f.id
    ORDER BY f.order_index ASC, f.name ASC
");

// Organizar carpetas en jerarquía padre-hijos
$carpetas_padres = array();
$carpetas_hijas = array();

foreach ($all_carpetas as $carpeta) {
    if ($carpeta->parent_id === null || $carpeta->parent_id == 0) {
        $carpetas_padres[] = $carpeta;
    } else {
        if (!isset($carpetas_hijas[$carpeta->parent_id])) {
            $carpetas_hijas[$carpeta->parent_id] = array();
        }
        $carpetas_hijas[$carpeta->parent_id][] = $carpeta;
    }
}

// Para compatibilidad con código existente
$carpetas = $all_carpetas;
?>

<div class="actas-viewer-container actas-viewer-hybrid">

<!-- Header simplificado sin degradado -->
<div class="actas-header-simple">
<div class="header-info">
<span class="colegiado-info">Colegiado: <?php echo esc_html($numero_colegiado); ?></span>
</div>

<div class="folder-selector-wrapper">
    <label for="folder-selector">Seleccionar Carpeta:</label>
    <select id="folder-selector" class="folder-selector">
    <option value="0">Todas las actas</option>
    <?php foreach ($carpetas_padres as $padre): ?>
    <?php if (isset($carpetas_hijas[$padre->id]) && !empty($carpetas_hijas[$padre->id])): ?>
        <!-- Carpeta padre con hijos - No seleccionable -->
    <optgroup label="<?php echo esc_html($padre->name); ?>">
    <?php foreach ($carpetas_hijas[$padre->id] as $hija): ?>
        <?php if ($hija->actas_count > 0): ?>
        <option value="<?php echo $hija->id; ?>" data-count="<?php echo $hija->actas_count; ?>">
        <?php echo esc_html($hija->name); ?> (<?php echo $hija->actas_count; ?>)
</option>
<?php endif; ?>
<?php endforeach; ?>
</optgroup>
<?php else: ?>
<!-- Carpeta sin hijos - Seleccionable -->
    <?php if ($padre->actas_count > 0): ?>
    <option value="<?php echo $padre->id; ?>" data-count="<?php echo $padre->actas_count; ?>">
        <?php echo esc_html($padre->name); ?> (<?php echo $padre->actas_count; ?>)
</option>
<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>
</select>
</div>
</div>
    
    <!-- Loading indicator para filtrado -->
    <div id="loading-filter" class="loading-filter" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <span id="loading-text">Cargando actas...</span>
        </div>
    </div>
    
    <!-- Contador de actas -->
    <div class="actas-counter">
        <span id="actas-count">Mostrando <?php echo count($actas); ?> actas de: <?php echo isset($_GET['carpeta']) ? esc_html($_GET['carpeta']) : 'Todas'; ?></span>
    </div>
    
    <!-- Tabla de actas -->
    <div id="actas-container">
        <?php if (empty($actas)): ?>
            <div class="no-actas-message">
                <div class="no-actas-content">
                    <span class="no-actas-icon">📄</span>
                    <h4>No hay actas disponibles</h4>
                    <p>No se encontraron actas en la carpeta seleccionada.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="actas-table-container" id="actas-table-container">
                <table class="actas-table" id="actas-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Fecha Agregada</th>
                            <th>Páginas</th>
                            <th>Carpeta</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="actas-tbody">
                        <?php foreach ($actas as $acta): ?>
                            <tr class="acta-row" data-acta-id="<?php echo $acta->id; ?>" data-folder-id="<?php echo $acta->folder_id ?: 0; ?>">
                                <td class="acta-title-cell">
                                    <img src="https://preproduccion.cpic.or.cr/wp-content/wp-file-download/icons/svg/pdf.svg?version=1733930755" 
                                         alt="PDF" class="pdf-icon" width="20" height="20">
                                    <span class="acta-title-text"><?php echo esc_html($acta->title ?: 'Acta sin título'); ?></span>
                                </td>
                                <td class="acta-date-cell">
                                    <?php echo date('d/m/Y', strtotime($acta->upload_date)); ?>
                                </td>
                                <td class="acta-pages-cell">
                                    <?php echo intval($acta->total_pages); ?> págs
                                </td>
                                <td class="acta-folder-cell">
                                    <?php echo isset($acta->folder_name) ? esc_html($acta->folder_name) : 'Sin carpeta'; ?>
                                </td>
                                <td class="acta-action-cell">
                                    <button class="ver-acta-btn" 
                                            data-acta-id="<?php echo $acta->id; ?>"
                                            data-total-pages="<?php echo intval($acta->total_pages); ?>"
                                            data-acta-title="<?php echo esc_attr($acta->title); ?>">
                                        Ver Acta
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Información adicional -->
    <div class="actas-info">
        <div class="security-notice">
            <h4>🔒 Información de Seguridad</h4>
            <ul>
                <li>Todos los documentos están protegidos con su número de colegiado</li>
                <li>Se registra cada visualización para auditoría</li>
                <li>No es posible descargar o imprimir los documentos</li>
                <li>El contenido está protegido contra capturas de pantalla</li>
            </ul>
        </div>
        
        <div class="usage-instructions">
            <h4>📖 Instrucciones de Uso</h4>
            <ul>
                <li>Seleccione una carpeta para filtrar las actas</li>
                <li>Haga clic en "Ver Acta" para abrir el documento</li>
                <li>Use los botones de navegación para cambiar de página</li>
                <li>Puede ir directamente a una página específica</li>
                <li>El documento se carga de forma segura página por página</li>
            </ul>
        </div>
    </div>
</div>

<!-- ESTILOS -->
<style>
/* IMPORTANTE: Ocultar TODOS los navegadores conflictivos */
.visual-navigator-container,
.frontend-navigation-container,
.advanced-navigator,
.actas-navigator,
[id*="visual-navigator"],
[class*="visual-navigator"]:not(.actas-viewer-hybrid),
[id*="frontend-nav"],
[class*="frontend-nav"],
[id*="advanced-nav"],
[class*="advanced-nav"],
.navigator-modal,
.nav-modal,
[id*="nav-modal"],
[class*="nav-modal"] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    z-index: -1 !important;
    position: absolute !important;
    top: -9999px !important;
    left: -9999px !important;
}

/* Asegurar que SOLO el visor híbrido sea visible */
.actas-viewer-hybrid {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 1 !important;
    position: relative !important;
}

/* IMPORTANTE: Solo permitir el modal original del visor */
#actas-modal {
    z-index: 99999 !important;
}

/* Ocultar cualquier modal que no sea el original */
.modal:not(#actas-modal),
[id*="modal"]:not(#actas-modal),
[class*="modal"]:not(.actas-modal):not(.modal-overlay):not(.modal-content):not(.modal-header):not(.modal-body) {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
}

/* Estilos específicos para el visor híbrido */
.actas-viewer-hybrid {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header simplificado */
.actas-header-simple {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.header-info .colegiado-info {
    font-size: 16px;
    font-weight: 600;
    color: #495057;
}

.folder-selector-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.folder-selector-wrapper label {
    font-size: 14px;
    font-weight: 500;
    color: #495057;
    white-space: nowrap;
}

.folder-selector {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background: white;
    color: #495057;
    font-size: 14px;
    cursor: pointer;
    min-width: 200px;
}

.folder-selector:hover,
.folder-selector:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Estilos para optgroup */
.folder-selector optgroup {
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.folder-selector optgroup option {
    font-weight: 400;
    padding-left: 20px;
    background-color: white;
    color: #6c757d;
}

.loading-filter {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid #667eea;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    font-weight: 500;
    color: #333;
}

.loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.actas-counter {
    background: #f8f9fa;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
    color: #495057;
    border-left: 4px solid #667eea;
}

.no-actas-message {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    color: #6c757d;
}

.no-actas-content {
    max-width: 400px;
    margin: 0 auto;
}

.no-actas-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 20px;
}

.no-actas-content h4 {
    margin: 0 0 10px 0;
    color: #495057;
    font-size: 20px;
}

.no-actas-content p {
    margin: 0;
    line-height: 1.5;
}

/* Estilos para la tabla de actas */
.actas-table-container {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    margin: 20px 0;
}

.actas-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.actas-table thead {
    background: #f8f9fa;
}

.actas-table th {
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}

.actas-table tbody tr {
    border-bottom: 1px solid #dee2e6;
    transition: background-color 0.2s;
}

.actas-table tbody tr:hover {
    background-color: #f8f9fa;
}

.actas-table tbody tr:last-child {
    border-bottom: none;
}

.actas-table td {
    padding: 12px 15px;
    vertical-align: middle;
    color: #495057;
}

.acta-title-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pdf-icon {
    flex-shrink: 0;
}

.acta-title-text {
    font-weight: 500;
    color: #495057;
}

.acta-date-cell {
    color: #6c757d;
    white-space: nowrap;
}

.acta-pages-cell {
    text-align: center;
    color: #6c757d;
    white-space: nowrap;
}

.acta-folder-cell {
    color: #6c757d;
}

.acta-action-cell {
    text-align: center;
}

.ver-acta-btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.ver-acta-btn:hover {
    background: #0056b3;
}

.actas-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 40px;
}

.security-notice, .usage-instructions {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.security-notice {
    border-left: 4px solid #dc3545;
}

.usage-instructions {
    border-left: 4px solid #17a2b8;
}

.security-notice h4, .usage-instructions h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 18px;
}

.security-notice ul, .usage-instructions ul {
    margin: 0;
    padding-left: 20px;
}

.security-notice li, .usage-instructions li {
    margin-bottom: 8px;
    color: #495057;
    line-height: 1.4;
}

/* Responsive */
@media (max-width: 768px) {
    .actas-header-simple {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .folder-selector-wrapper {
        flex-direction: column;
        width: 100%;
        gap: 5px;
    }
    
    .folder-selector {
        width: 100%;
        min-width: auto;
    }
    
    .actas-viewer-hybrid {
        padding: 15px;
    }
    
    .actas-table-container {
        overflow-x: auto;
    }
    
    .actas-table {
        min-width: 600px;
    }
    
    .actas-table th,
    .actas-table td {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .ver-acta-btn {
        padding: 4px 8px;
        font-size: 11px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // PASO 1: Limpiar completamente conflictos de navegadores
    function cleanupNavigatorConflicts() {
        console.log('🧹 Limpiando conflictos de navegadores...');
        
        // Eliminar completamente navegadores conflictivos
        $('.visual-navigator-container').remove();
        $('.frontend-navigation-container').remove();
        $('.advanced-navigator').remove();
        $('.actas-navigator').remove();
        $('[id*="visual-navigator"]').remove();
        $('[id*="frontend-nav"]').remove();
        $('[id*="advanced-nav"]').remove();
        $('[class*="visual-navigator"]').not('.actas-viewer-hybrid').remove();
        $('[class*="frontend-nav"]').remove();
        $('[class*="advanced-nav"]').remove();
        $('.navigator-modal').remove();
        $('.nav-modal').remove();
        $('[id*="nav-modal"]').remove();
        $('[class*="nav-modal"]').remove();
        
        // Eliminar modales no deseados (mantener solo el original)
        $('.modal').not('#actas-modal').remove();
        $('[id*="modal"]').not('#actas-modal').remove();
        $('[class*="modal"]').not('.actas-modal, .modal-overlay, .modal-content, .modal-header, .modal-body').remove();
        
        console.log('✅ Conflictos limpiados');
    }
    
    // PASO 2: Inicializar solo el visor híbrido
    function initHybridViewer() {
        console.log('🚀 Inicializando visor híbrido...');
        
        // Asegurar que el visor híbrido sea visible
        $('.actas-viewer-hybrid').show().css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1',
            'z-index': '1',
            'position': 'relative'
        });
        
        console.log('✅ Visor híbrido inicializado');
    }
    
    // PASO 3: Funcionalidad del selector de carpetas (AJAX REAL)
    function initFolderSelector() {
        console.log('📁 Inicializando selector de carpetas con AJAX...');
        
        $('#folder-selector').off('change').on('change', function() {
            const folderId = $(this).val();
            const folderName = $(this).find('option:selected').text().trim();
            
            console.log('📁 Carpeta seleccionada:', folderId, '(' + folderName + ')');
            
            // Limpiar mensajes previos
            $('.no-actas-message').remove();
            
            if (folderId == '0') {
                // Recargar TODAS las actas via AJAX
                console.log('📋 Cargando TODAS las actas...');
                $('#loading-text').text('Cargando todas las actas disponibles...');
                $('#loading-filter').show();
                $('#actas-container').hide();
                loadActasAjax(0, 'Todas las actas');
            } else {
                // Cargar actas de carpeta específica via AJAX
                console.log('🎯 Cargando actas de carpeta ID:', folderId);
                const cleanFolderName = folderName.replace(/📂\s*/, '').replace(/\s*\(\d+\)/, '');
                $('#loading-text').text(`Cargando actas de: ${cleanFolderName}...`);
                $('#loading-filter').show();
                $('#actas-container').hide();
                loadActasAjax(folderId, cleanFolderName);
            }
        });
        
        console.log('✅ Selector de carpetas AJAX configurado');
    }
    
    // Nueva función para cargar actas via AJAX
    function loadActasAjax(folderId, folderName) {
        console.log('🌐 Iniciando carga AJAX para carpeta:', folderId);
        
        $.ajax({
            url: actas_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_folder_actas',
                folder_id: folderId,
                nonce: actas_ajax.nonce
            },
            success: function(response) {
                console.log('✅ Respuesta AJAX recibida:', response);
                
                if (response.success) {
                    // Actualizar contador
                    const count = response.data.count;
                    if (folderId == 0) {
                        $('#actas-count').text(`Mostrando todas las actas (${count})`);
                    } else {
                        $('#actas-count').text(`Mostrando ${count} actas de: ${folderName}`);
                    }
                    
                    // Actualizar contenido de la tabla
                    if (count > 0) {
                        $('#actas-tbody').html(response.data.actas_html);
                        $('#actas-table-container').show();
                        console.log(`✅ ${count} actas cargadas exitosamente`);
                    } else {
                        $('#actas-table-container').hide();
                        $('#actas-container').prepend(`
                            <div class="no-actas-message">
                                <div class="no-actas-content">
                                    <span class="no-actas-icon">📄</span>
                                    <h4>No hay actas en esta carpeta</h4>
                                    <p>La carpeta "${folderName}" no contiene actas.</p>
                                    <p><small>Carpeta ID: ${folderId}</small></p>
                                </div>
                            </div>
                        `);
                        console.log('❌ No se encontraron actas en esta carpeta');
                    }
                } else {
                    console.error('❌ Error en respuesta AJAX:', response.data);
                    showAjaxError('Error al cargar las actas: ' + response.data);
                }
                
                // Ocultar loading y mostrar resultados
                $('#loading-filter').hide();
                $('#actas-container').show();
                
            },
            error: function(xhr, status, error) {
                console.error('❌ Error AJAX:', error);
                showAjaxError('Error de conexión. Por favor, intente nuevamente.');
                
                // Ocultar loading
                $('#loading-filter').hide();
                $('#actas-container').show();
            }
        });
    }
    
    // Función para mostrar errores AJAX
    function showAjaxError(message) {
        $('#actas-table-container').hide();
        $('#actas-container').prepend(`
            <div class="no-actas-message ajax-error">
                <div class="no-actas-content">
                    <span class="no-actas-icon" style="color: #dc3545;">⚠️</span>
                    <h4 style="color: #dc3545;">Error al cargar actas</h4>
                    <p>${message}</p>
                    <button onclick="location.reload()" style="
                        background: #007cba; 
                        color: white; 
                        border: none; 
                        padding: 10px 20px; 
                        border-radius: 5px; 
                        cursor: pointer; 
                        margin-top: 10px;
                    ">Recargar página</button>
                </div>
            </div>
        `);
    }
    
    // EJECUCIÓN PRINCIPAL
    setTimeout(() => {
        cleanupNavigatorConflicts();
        initHybridViewer();
        initFolderSelector();
        
        console.log('🎉 Visor híbrido completamente inicializado');
        console.log('📊 Total actas:', $('.acta-card').length);
        console.log('📁 Carpetas disponibles:', $('#folder-selector option').length - 1);
        
    }, 100);
    
    // Limpieza continua para prevenir conflictos
    setInterval(() => {
        // Limpiar cualquier modal no deseado que aparezca dinámicamente
        $('.modal').not('#actas-modal').remove();
        $('[class*="navigator"]:not(.actas-viewer-hybrid)').remove();
    }, 2000);
});

// FUNCIÓN PARA REORGANIZAR ACTAS POR AÑO
function reorganizarActas() {
    if (!confirm('¿Reorganizar las actas automáticamente por año basándose en sus títulos?')) {
        return;
    }
    
    jQuery.ajax({
        url: actas_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'reorganizar_actas_por_año',
            nonce: actas_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                alert('✅ Actas reorganizadas exitosamente: ' + response.data.message);
                location.reload(); // Recargar página para ver cambios
            } else {
                alert('❌ Error: ' + response.data);
            }
        },
        error: function() {
            alert('❌ Error de conexión');
        }
    });
}
</script>
