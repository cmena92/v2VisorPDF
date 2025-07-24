<?php
/**
 * Template: Frontend Navigator - FASE 4
 * Interfaz avanzada de navegación para usuarios finales
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener configuración del shortcode
$navigation_config = $this->get_navigation_config($atts);
?>

<div class="actas-navigator" data-config='<?php echo esc_attr(json_encode($navigation_config)); ?>'>
    
    <!-- Configuración AJAX para JavaScript -->
    <script type="text/javascript">
        window.frontendNavAjax = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>',
            loading_text: '<?php echo esc_js(__('Cargando...', 'visor-pdf-crisman')); ?>',
            no_results_text: '<?php echo esc_js(__('No se encontraron resultados', 'visor-pdf-crisman')); ?>',
            search_placeholder: '<?php echo esc_js(__('Buscar actas...', 'visor-pdf-crisman')); ?>'
        };
    </script>
    
    <!-- Header con búsqueda y breadcrumb -->
    <div class="navigator-header">
        
        <?php if ($navigation_config['show_breadcrumb']): ?>
        <div class="breadcrumb-container">
            <!-- El breadcrumb se carga dinámicamente -->
        </div>
        <?php endif; ?>
        
        <?php if ($navigation_config['show_search']): ?>
        <div class="search-container">
            <div class="search-box">
                <input type="text" 
                       class="search-input" 
                       placeholder="<?php echo esc_attr__('Buscar actas...', 'visor-pdf-crisman'); ?>"
                       autocomplete="off">
                <button class="search-btn" type="button">
                    🔍
                </button>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Filtros avanzados -->
    <?php if (!empty($navigation_config['filters'])): ?>
    <div class="filters-container">
        <div class="filters-header">
            <h4>🔧 Filtros</h4>
            <button class="toggle-filters-btn">Mostrar/Ocultar</button>
        </div>
        
        <div class="filters-content" style="display: none;">
            <div class="filters-grid">
                
                <?php if (in_array('fecha', $navigation_config['filters'])): ?>
                <div class="filter-group">
                    <label>📅 Fecha de subida:</label>
                    <div class="date-range">
                        <input type="date" 
                               name="date_from" 
                               class="filter-input"
                               placeholder="Desde">
                        <span>hasta</span>
                        <input type="date" 
                               name="date_to" 
                               class="filter-input"
                               placeholder="Hasta">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('paginas', $navigation_config['filters'])): ?>
                <div class="filter-group">
                    <label>📄 Número de páginas:</label>
                    <div class="pages-range">
                        <input type="number" 
                               name="min_pages" 
                               class="filter-input"
                               placeholder="Mín"
                               min="1">
                        <span>a</span>
                        <input type="number" 
                               name="max_pages" 
                               class="filter-input"
                               placeholder="Máx"
                               min="1">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('orden', $navigation_config['filters'])): ?>
                <div class="filter-group">
                    <label>↕️ Ordenar por:</label>
                    <select name="sort_by" class="filter-input">
                        <option value="">Fecha de subida (reciente)</option>
                        <option value="title">Título (A-Z)</option>
                        <option value="pages">Más páginas</option>
                        <option value="size">Tamaño de archivo</option>
                    </select>
                </div>
                <?php endif; ?>
                
            </div>
            
            <div class="filters-actions">
                <button class="btn btn-primary apply-filters-btn">Aplicar Filtros</button>
                <button class="btn btn-secondary clear-filters-btn">Limpiar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contenedor principal de contenido -->
    <div class="navigator-content">
        
        <!-- Lista de actas y carpetas -->
        <div class="actas-list-container">
            <!-- El contenido se carga dinámicamente vía AJAX -->
            <div class="initial-loading">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Cargando contenido...</p>
                </div>
            </div>
        </div>
        
        <!-- Paginación -->
        <div class="pagination-container">
            <!-- La paginación se genera dinámicamente -->
        </div>
        
    </div>
    
</div>

<!-- CSS específico para el navegador frontend -->
<style>
.actas-navigator {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header */
.navigator-header {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

/* Breadcrumb */
.breadcrumb-nav {
    margin-bottom: 15px;
}

.breadcrumb {
    display: flex;
    flex-wrap: wrap;
    list-style: none;
    padding: 0;
    margin: 0;
    background: transparent;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
    margin: 0 8px;
    color: #6c757d;
}

.breadcrumb-item a {
    color: #007cba;
    text-decoration: none;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.breadcrumb-item a:hover {
    background-color: #e9ecef;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #6c757d;
    font-weight: 500;
}

/* Búsqueda */
.search-container {
    display: flex;
    justify-content: center;
}

.search-box {
    display: flex;
    max-width: 500px;
    width: 100%;
    position: relative;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 6px 0 0 6px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.2s;
}

.search-input:focus {
    border-color: #007cba;
}

.search-btn {
    padding: 12px 16px;
    background: #007cba;
    color: white;
    border: 2px solid #007cba;
    border-radius: 0 6px 6px 0;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-btn:hover {
    background: #005a87;
}

/* Filtros */
.filters-container {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.filters-header h4 {
    margin: 0;
    color: #495057;
}

.toggle-filters-btn {
    background: none;
    border: 1px solid #007cba;
    color: #007cba;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.toggle-filters-btn:hover {
    background: #007cba;
    color: white;
}

.filters-content {
    padding: 20px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
}

.filter-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.date-range, .pages-range {
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-range input, .pages-range input {
    flex: 1;
}

.filters-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Contenido principal */
.navigator-content {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}

/* Secciones */
.subfolders-section, .actas-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 20px;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e9ecef;
    color: #495057;
}

/* Grid de carpetas */
.folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.folder-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.2s;
    cursor: pointer;
}

.folder-card:hover {
    border-color: #007cba;
    box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
    transform: translateY(-1px);
}

.folder-icon {
    font-size: 32px;
    margin-right: 15px;
}

.folder-info {
    flex: 1;
}

.folder-name {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.folder-name a {
    color: #007cba;
    text-decoration: none;
    font-weight: 500;
}

.folder-description {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #6c757d;
}

.actas-count {
    font-size: 12px;
    color: #28a745;
    font-weight: 500;
}

/* Grid de actas */
.actas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.acta-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    transition: all 0.2s;
}

.acta-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.acta-card.search-result {
    border-left: 4px solid #007cba;
}

.acta-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.acta-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #212529;
    flex: 1;
    margin-right: 10px;
}

.acta-date {
    font-size: 12px;
    color: #6c757d;
    white-space: nowrap;
}

.acta-content {
    padding: 15px 20px;
}

.acta-description {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #495057;
    line-height: 1.4;
}

.acta-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 12px;
    color: #6c757d;
}

.acta-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

/* Botones */
.btn {
    display: inline-block;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.btn-primary {
    background: #007cba;
    color: white;
}

.btn-primary:hover {
    background: #005a87;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

/* Estados vacíos */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #495057;
}

/* Resultados de búsqueda */
.search-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #e3f2fd;
    border-bottom: 1px solid #bbdefb;
    margin-bottom: 20px;
}

.search-results-header h3 {
    margin: 0;
    color: #1565c0;
}

.clear-search-btn {
    font-size: 12px;
    padding: 6px 12px;
}

mark {
    background: #fff3cd;
    padding: 1px 2px;
    border-radius: 2px;
}

/* Paginación */
.pagination-nav {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.pagination-info {
    margin-bottom: 15px;
    font-size: 14px;
    color: #6c757d;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 5px;
}

.pagination li {
    display: flex;
}

.page-link {
    padding: 8px 12px;
    color: #007cba;
    text-decoration: none;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    transition: all 0.2s;
}

.page-link:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.page-link.active {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.page-dots {
    padding: 8px 12px;
    color: #6c757d;
}

/* Loading */
.loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    background: rgba(255, 255, 255, 0.9);
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100;
}

.loading-spinner {
    text-align: center;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.initial-loading {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

/* Error */
.error-message {
    text-align: center;
    padding: 40px 20px;
    background: #f8d7da;
    color: #721c24;
    border-radius: 8px;
    margin: 20px 0;
}

.error-content {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.error-icon {
    font-size: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .actas-navigator {
        padding: 10px;
    }
    
    .navigator-header {
        padding: 15px;
    }
    
    .search-box {
        max-width: 100%;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-actions {
        flex-direction: column;
    }
    
    .folders-grid,
    .actas-grid {
        grid-template-columns: 1fr;
    }
    
    .acta-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .acta-meta {
        flex-direction: column;
        gap: 6px;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
    
    .search-results-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .breadcrumb {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "↳";
        margin: 5px 0;
    }
    
    .date-range,
    .pages-range {
        flex-direction: column;
        gap: 8px;
    }
    
    .folder-card {
        flex-direction: column;
        text-align: center;
    }
    
    .folder-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
}

/* Estados especiales */
.actas-navigator.loading {
    position: relative;
}

.actas-navigator.search-mode .breadcrumb-container {
    opacity: 0.6;
}

.actas-navigator.filter-mode .section-title::after {
    content: " (filtrado)";
    font-size: 14px;
    color: #007cba;
    font-weight: normal;
}
</style>

<script>
// Toggle de filtros
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.toggle-filters-btn');
    const filtersContent = document.querySelector('.filters-content');
    
    if (toggleBtn && filtersContent) {
        toggleBtn.addEventListener('click', function() {
            const isVisible = filtersContent.style.display !== 'none';
            filtersContent.style.display = isVisible ? 'none' : 'block';
            toggleBtn.textContent = isVisible ? 'Mostrar Filtros' : 'Ocultar Filtros';
        });
    }
});
</script>
