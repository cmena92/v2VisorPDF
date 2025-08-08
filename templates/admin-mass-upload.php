<?php
/**
 * Template: Subida Masiva - Admin
 * FASE 3: Interfaz de subida masiva de PDFs
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderizar opciones de carpetas para el selector
 */
function render_folder_select_options($folders, $prefix = '') {
    foreach ($folders as $folder) {
        if ($folder->slug !== 'sin-clasificar') {
            echo '<option value="' . esc_attr($folder->id) . '">';
            echo esc_html($prefix . $folder->name);
            echo '</option>';
            
            if (!empty($folder->children)) {
                render_folder_select_options($folder->children, $prefix . '└─ ');
            }
        }
    }
}
?>

<div class="wrap mass-upload-container">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-upload"></span>
        Subida Masiva de Actas
    </h1>
    
    <div class="mass-upload-intro">
        <p>Sube hasta <strong>20 archivos PDF</strong> simultáneamente. Arrastra y suelta archivos o usa el selector.</p>
    </div>
    
    <!-- Notificaciones -->
    <div id="notifications-container" class="notifications-area"></div>
    
    <!-- Estadísticas del sistema -->
    <div class="system-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($upload_stats['total_actas']); ?></div>
                <div class="stat-label">Total Actas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($upload_stats['today_uploads']); ?></div>
                <div class="stat-label">Subidas Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($upload_stats['week_uploads']); ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo size_format($upload_stats['total_size']); ?></div>
                <div class="stat-label">Espacio Usado</div>
            </div>
        </div>
    </div>
    
    <div class="mass-upload-wrapper">
        
        <!-- Panel de configuración -->
        <div class="upload-config-panel">
            <h2><span class="dashicons dashicons-admin-settings"></span> Configuración</h2>
            
            <div class="config-section">
                <label for="target-folder" class="config-label">
                    <span class="dashicons dashicons-category"></span>
                    Carpeta Destino:
                </label>
                <select id="target-folder" class="regular-text" required>
                    <option value="">Seleccionar carpeta...</option>
                    <?php render_folder_select_options($folders_hierarchy); ?>
                </select>
                <p class="description">Todas las actas se guardarán en la carpeta seleccionada.</p>
            </div>
            
            <div class="config-limits">
                <h4><span class="dashicons dashicons-info"></span> Límites del Sistema</h4>
                <ul class="limits-list">
                    <li><strong>Máximo archivos:</strong> 20 por sesión</li>
                    <li><strong>Tamaño por archivo:</strong> 20MB máximo</li>
                    <li><strong>Tipo permitido:</strong> Solo archivos PDF</li>
                    <li><strong>Procesamiento:</strong> Secuencial para estabilidad</li>
                </ul>
            </div>
        </div>
        
        <!-- Zona de drop -->
        <div class="drop-zone-panel">
            <div id="drop-zone" class="drop-zone">
                <div class="drop-zone-content">
                    <div class="drop-zone-icon">
                        <span class="dashicons dashicons-cloud-upload"></span>
                    </div>
                    <div class="drop-zone-text">
                        <h3>Arrastra archivos PDF aquí</h3>
                        <p>o haz clic para seleccionar archivos</p>
                    </div>
                    <div class="drop-zone-actions">
                        <button type="button" id="btn-select-files" class="button button-primary">
                            <span class="dashicons dashicons-media-default"></span>
                            Seleccionar Archivos
                        </button>
                        <input type="file" id="file-input" multiple accept=".pdf" style="display: none;">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de archivos -->
        <div class="files-panel">
            <div class="files-header">
                <h2>
                    <span class="dashicons dashicons-media-document"></span>
                    Archivos Seleccionados
                    <span id="files-count" class="files-count">0 archivo(s) seleccionado(s)</span>
                </h2>
                <div class="files-actions">
                    <button type="button" id="btn-clear-files" class="button">
                        <span class="dashicons dashicons-trash"></span>
                        Limpiar Todo
                    </button>
                    <button type="button" id="btn-start-upload" class="button button-primary" disabled>
                        <span class="dashicons dashicons-upload"></span>
                        Subir Archivos
                    </button>
                </div>
            </div>
            
            <div id="files-list" class="files-list">
                <p class="no-files">No hay archivos seleccionados</p>
            </div>
        </div>
        
        <!-- Progreso de subida -->
        <div id="upload-progress" class="upload-progress-panel hidden">
            <h3><span class="dashicons dashicons-update"></span> Progreso de Subida</h3>
            <div class="progress-container">
                <div class="progress-bar-container">
                    <div id="progress-bar" class="progress-bar"></div>
                </div>
                <div class="progress-info">
                    <span id="progress-text">Iniciando...</span>
                    <span id="progress-stats"></span>
                </div>
            </div>
        </div>
        
        <!-- Reporte de subida -->
        <div id="upload-report" class="upload-report-panel hidden">
            <!-- El contenido se genera dinámicamente -->
        </div>
    </div>
</div>

<style>
/* Estilos para Subida Masiva */
.mass-upload-container {
    max-width: 1200px;
    margin: 0 auto;
}

.mass-upload-intro {
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 6px;
    padding: 15px;
    margin: 20px 0;
    border-left: 4px solid #0073aa;
}

.notifications-area {
    margin: 20px 0;
}

.upload-notification {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    margin-bottom: 10px;
    border-radius: 6px;
    font-weight: 500;
    animation: slideIn 0.3s ease;
}

.upload-notification.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.upload-notification.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.upload-notification.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.upload-notification.info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Estadísticas del sistema */
.system-stats {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.stat-card {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
    display: block;
}

.stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

/* Wrapper principal */
.mass-upload-wrapper {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    margin-top: 20px;
}

/* Panel de configuración */
.upload-config-panel {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.upload-config-panel h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 0;
    color: #1d2327;
    font-size: 16px;
}

.config-section {
    margin-bottom: 20px;
}

.config-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1d2327;
}

.config-limits h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #646970;
    font-size: 14px;
    margin-bottom: 10px;
}

.limits-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.limits-list li {
    padding: 5px 0;
    font-size: 13px;
    color: #646970;
    border-bottom: 1px solid #f0f0f1;
}

.limits-list li:last-child {
    border-bottom: none;
}

/* Zona de drop */
.drop-zone-panel {
    grid-column: 2;
}

.drop-zone {
    border: 2px dashed #c3c4c7;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: #fafafa;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.drop-zone:hover {
    border-color: #0073aa;
    background: #f0f8ff;
}

.drop-zone.drop-zone-highlight {
    border-color: #00a32a;
    background: #f0fff0;
    transform: scale(1.02);
}

.drop-zone-content {
    pointer-events: none;
}

.drop-zone-icon .dashicons {
    font-size: 48px;
    color: #c3c4c7;
    margin-bottom: 15px;
}

.drop-zone-highlight .drop-zone-icon .dashicons {
    color: #00a32a;
}

.drop-zone-text h3 {
    margin: 0 0 8px 0;
    color: #1d2327;
    font-size: 18px;
}

.drop-zone-text p {
    margin: 0 0 20px 0;
    color: #646970;
}

.drop-zone-actions {
    pointer-events: auto;
}

.drop-zone-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    padding: 8px 16px;
}

/* Panel de archivos */
.files-panel {
    grid-column: 1 / -1;
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e4e7;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 8px 8px 0 0;
}

.files-header h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    color: #1d2327;
    font-size: 16px;
}

.files-count {
    background: #f0f0f1;
    color: #646970;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: normal;
}

.files-actions {
    display: flex;
    gap: 10px;
}

.files-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.files-list {
    padding: 20px;
    max-height: 600px;
    overflow-y: auto;
}

.no-files {
    text-align: center;
    color: #646970;
    font-style: italic;
    margin: 40px 0;
}

/* Items de archivo */
.file-item {
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 15px;
    background: white;
    transition: all 0.3s ease;
}

.file-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.file-item.success {
    border-color: #00a32a;
    background: linear-gradient(135deg, #ffffff 0%, #f0fff0 100%);
}

.file-item.error {
    border-color: #d63638;
    background: linear-gradient(135deg, #ffffff 0%, #fff0f0 100%);
}

.file-item.processing {
    border-color: #0073aa;
    background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.file-icon {
    font-size: 20px;
}

.file-details {
    flex: 1;
}

.file-name {
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 4px;
}

.file-size {
    font-size: 12px;
    color: #646970;
}

.file-actions {
    display: flex;
    gap: 8px;
}

.btn-remove-file {
    background: none;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-remove-file:hover {
    background: #f0f0f1;
    border-color: #c3c4c7;
}

.file-metadata {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 0 15px 15px 15px;
}

.file-metadata input {
    padding: 8px;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    font-size: 13px;
}

.file-metadata input:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.file-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 10px 15px;
    margin: 0 15px 15px 15px;
    border-radius: 4px;
    font-size: 13px;
}

.file-progress {
    padding: 0 15px 15px 15px;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: #e2e4e7;
    border-radius: 3px;
    overflow: hidden;
    animation: pulse 1.5s ease-in-out infinite;
}

.progress-bar::after {
    content: '';
    display: block;
    width: 30%;
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    border-radius: 3px;
    animation: slide 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.7; }
    50% { opacity: 1; }
}

@keyframes slide {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(350%); }
}

/* Progreso general */
.upload-progress-panel {
    grid-column: 1 / -1;
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.upload-progress-panel h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 0;
    color: #1d2327;
}

.progress-container {
    margin-top: 15px;
}

.progress-bar-container {
    width: 100%;
    height: 12px;
    background: #e2e4e7;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
}

#progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    border-radius: 6px;
    transition: width 0.3s ease;
    width: 0%;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #646970;
}

#progress-stats {
    display: flex;
    gap: 15px;
}

.success-count {
    color: #00a32a;
    font-weight: 600;
}

.failed-count {
    color: #d63638;
    font-weight: 600;
}

/* Reporte de subida */
.upload-report {
    grid-column: 1 / -1;
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.upload-report h3 {
    margin-top: 0;
    color: #1d2327;
    text-align: center;
}

.report-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin: 20px 0;
}

.stat-item {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    min-width: 100px;
}

.stat-item.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.stat-item.failed {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.stat-item.total {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    display: block;
}

.stat-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
    opacity: 0.8;
}

.report-note {
    text-align: center;
    color: #646970;
    font-style: italic;
    margin-top: 15px;
}

/* Estados ocultos */
.hidden {
    display: none !important;
}

/* Responsive */
@media (max-width: 1024px) {
    .mass-upload-wrapper {
        grid-template-columns: 1fr;
    }
    
    .drop-zone-panel {
        grid-column: 1;
    }
    
    .files-panel {
        grid-column: 1;
    }
}

@media (max-width: 768px) {
    .files-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .files-actions {
        justify-content: center;
    }
    
    .file-metadata {
        grid-template-columns: 1fr;
    }
    
    .progress-info {
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }
    
    .report-stats {
        flex-direction: column;
        gap: 15px;
        align-items: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .drop-zone {
        padding: 20px 10px;
        min-height: 150px;
    }
    
    .drop-zone-icon .dashicons {
        font-size: 32px;
    }
    
    .drop-zone-text h3 {
        font-size: 16px;
    }
}
</style>

<!-- JavaScript se carga desde assets/js/mass-upload.js -->
