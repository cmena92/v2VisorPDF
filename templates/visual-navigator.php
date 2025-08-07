<?php
/**
 * Template para Navegador Visual de Actas
 * Diseño unificado con selector visual de carpetas
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="visual-navigator-container" class="visual-navigator-wrapper">
    
    <!-- Selector de Carpetas Simplificado -->
    <div class="visual-nav-filters">
        <div class="folder-selector-section">
            <h4 class="filter-section-title">
                <span class="dashicons dashicons-category"></span>
                Seleccionar Carpeta
            </h4>
            <div class="folder-dropdown-container">
                <select id="folder-selector" name="folder_id" class="folder-dropdown">
                    <?php 
                    // Renderizar todas las carpetas jerárquicamente
                    foreach ($folders_var as $folder) {
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
                    ?>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Título y Breadcrumbs -->
    <div class="nav-header">
        <h2 class="actas-title">
            <span class="dashicons dashicons-media-document"></span>
            Actas
        </h2>
        <div id="breadcrumb-container" class="breadcrumb-nav">
            <!-- Se llena dinámicamente -->
        </div>
    </div>
    
    <!-- Estado de Carga -->
    <div id="loading-state" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <span class="dashicons dashicons-update spin"></span>
            <span>Cargando actas...</span>
        </div>
    </div>
    
    <!-- Resultados -->
    <div id="results-container" class="results-section">
        
        <!-- Información de Resultados -->
        <div id="results-info" class="results-info" style="display: none;">
            <span id="results-count"></span>
            <span id="current-filters" class="current-filters"></span>
        </div>
        
        <!-- Grid de Actas -->
        <div id="actas-grid" class="actas-grid">
            <!-- Se llena dinámicamente con JavaScript -->
        </div>
        
        <!-- Mensaje de Sin Resultados -->
        <div id="no-results" class="no-results-message" style="display: none;">
            <div class="no-results-content">
                <span class="dashicons dashicons-search"></span>
                <h3>No se encontraron actas</h3>
                <p>No hay actas que coincidan con los filtros aplicados.</p>
                <button type="button" class="btn-clear-filters" onclick="clearAllFilters()">
                    Mostrar todas las actas
                </button>
            </div>
        </div>
        
        <!-- Paginación -->
        <div id="pagination-container" class="pagination-wrapper" style="display: none;">
            <!-- Se llena dinámicamente -->
        </div>
    </div>
    
    <!-- Modal del Visor (reutilizar el existente) -->
    <div id="pdf-viewer-modal" class="pdf-modal" style="display: none;">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3 id="modal-title">Visualizando Acta</h3>
                <button class="pdf-modal-close" onclick="closePDFModal()">&times;</button>
            </div>
            <div class="pdf-modal-body">
                <div id="pdf-viewer-container">
                    <!-- El visor PDF se carga aquí -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Variables globales para JavaScript -->
<script>
window.visualNavigatorData = {
    numeroColegiado: '<?php echo esc_js($numero_colegiado_var); ?>',
    perPage: <?php echo intval($atts_var['per_page']); ?>,
    defaultOrder: '<?php echo esc_js($atts_var['default_order']); ?>',
    defaultDirection: '<?php echo esc_js($atts_var['default_direction']); ?>',
    currentPage: 1,
    currentFolderId: 0,
    totalPages: 0
};
</script>
