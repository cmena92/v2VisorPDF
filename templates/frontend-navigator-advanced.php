<?php
/**
 * Template: Frontend Navigator Advanced - Navegador con Panel Lateral
 * Interfaz avanzada con panel lateral de navegación por carpetas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener configuración del shortcode
$advanced_config = $this->get_advanced_navigation_config($atts);
?>

<div class="actas-navigator-advanced" data-config='<?php echo esc_attr(json_encode($advanced_config)); ?>'>
    
    <!-- Configuración AJAX para JavaScript -->
    <script type="text/javascript">
        window.advancedNavAjax = window.advancedNavAjax || {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('actas_nonce'); ?>',
            loading_text: '<?php echo esc_js(__('Cargando...', 'visor-pdf-crisman')); ?>',
            no_folders_text: '<?php echo esc_js(__('No hay carpetas disponibles', 'visor-pdf-crisman')); ?>',
            recent_actas_text: '<?php echo esc_js(__('Últimas actas subidas', 'visor-pdf-crisman')); ?>'
        };
    </script>
    
    <!-- Header con breadcrumb -->
    <?php if ($advanced_config['show_breadcrumb']): ?>
    <div class="advanced-header">
        <div class="breadcrumb-container">
            <!-- El breadcrumb se carga dinámicamente -->
            <nav class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">
                        <span class="breadcrumb-home">🏠 Inicio</span>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contenedor principal con layout grid -->
    <div class="advanced-layout">
        
        <!-- Panel lateral de navegación (solo desktop) -->
        <?php if ($advanced_config['sidebar_navigation']): ?>
        <aside class="sidebar-navigation">
            <div class="sidebar-header">
                <h3 class="sidebar-title">📁 Navegación</h3>
                <button class="sidebar-toggle" aria-label="Alternar navegación">
                    <span class="toggle-icon">⚡</span>
                </button>
            </div>
            
            <div class="sidebar-content">
                <!-- Árbol de carpetas se carga dinámicamente -->
                <div class="folders-tree">
                    <div class="tree-loading">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Cargando estructura...</p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <?php endif; ?>
        
        <!-- Contenido principal -->
        <main class="main-content">
            
            <!-- Búsqueda y filtros (si están habilitados) -->
            <?php if ($advanced_config['show_search'] || !empty($advanced_config['filters'])): ?>
            <div class="content-controls">
                
                <?php if ($advanced_config['show_search']): ?>
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
                
                <?php if (!empty($advanced_config['filters'])): ?>
                <div class="filters-toggle">
                    <button class="btn btn-secondary toggle-filters-btn">
                        🔧 Filtros
                    </button>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Panel de filtros (inicialmente oculto) -->
            <?php if (!empty($advanced_config['filters'])): ?>
            <div class="filters-panel" style="display: none;">
                <div class="filters-content">
                    
                    <?php if (in_array('fecha', $advanced_config['filters'])): ?>
                    <div class="filter-group">
                        <label>📅 Fecha de subida:</label>
                        <div class="date-range">
                            <input type="date" name="date_from" class="filter-input" placeholder="Desde">
                            <span>hasta</span>
                            <input type="date" name="date_to" class="filter-input" placeholder="Hasta">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array('paginas', $advanced_config['filters'])): ?>
                    <div class="filter-group">
                        <label>📄 Número de páginas:</label>
                        <div class="pages-range">
                            <input type="number" name="min_pages" class="filter-input" placeholder="Mín" min="1">
                            <span>a</span>
                            <input type="number" name="max_pages" class="filter-input" placeholder="Máx" min="1">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array('orden', $advanced_config['filters'])): ?>
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
                    
                    <div class="filters-actions">
                        <button class="btn btn-primary apply-filters-btn">Aplicar Filtros</button>
                        <button class="btn btn-secondary clear-filters-btn">Limpiar</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Área de contenido de actas -->
            <div class="actas-content-area">
                
                <!-- Título dinámico del contenido -->
                <div class="content-header">
                    <h2 class="content-title">
                        <?php if ($advanced_config['initial_view'] === 'ultimas'): ?>
                            ✨ Últimas <?php echo $advanced_config['initial_limit']; ?> actas subidas
                        <?php else: ?>
                            📁 Seleccione una carpeta para ver su contenido
                        <?php endif; ?>
                    </h2>
                    <div class="content-meta">
                        <span class="results-count">Cargando...</span>
                    </div>
                </div>
                
                <!-- Lista de actas -->
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
            
        </main>
        
    </div>
    
</div>

<!-- Estilos CSS básicos integrados -->
<style>
/* Layout principal responsive */
.actas-navigator-advanced {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.advanced-header {
    margin-bottom: 20px;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.advanced-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 25px;
    min-height: 600px;
}

/* Panel lateral */
.sidebar-navigation {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #007cba;
    color: white;
    border-radius: 8px 8px 0 0;
}

.sidebar-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 5px;
    border-radius: 3px;
    display: none; /* Solo visible en responsive */
}

.sidebar-content {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

/* Contenido principal */
.main-content {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
}

.content-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.search-box {
    display: flex;
    max-width: 400px;
    flex: 1;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 6px 0 0 6px;
    font-size: 16px;
    outline: none;
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
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.content-title {
    margin: 0;
    color: #495057;
    font-size: 20px;
}

.content-meta {
    font-size: 14px;
    color: #6c757d;
}

/* Responsive */
@media (max-width: 768px) {
    .advanced-layout {
        grid-template-columns: 1fr;
    }
    
    .sidebar-navigation {
        display: none; /* Ocultar panel lateral en móvil */
    }
    
    .content-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        max-width: none;
    }
}

/* Loading states */
.loading-spinner {
    text-align: center;
    padding: 40px 20px;
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

/* Estados de loading para el sidebar */
.tree-loading {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

.tree-loading .spinner {
    width: 30px;
    height: 30px;
    margin-bottom: 10px;
}

/* Botones básicos */
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
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
</style>
