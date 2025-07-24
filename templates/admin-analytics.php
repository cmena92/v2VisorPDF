<?php
/**
 * Template para Dashboard de Analytics
 * FASE 5B: Dashboard Básico
 * 
 * @package VisorPDFCrisman
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Obtener estadísticas
$analytics = new Visor_PDF_Analytics();
$general_stats = $analytics->get_general_stats();
$realtime_stats = $analytics->get_realtime_stats();
?>

<div class="wrap visor-analytics-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-area"></span>
        Analytics del Visor PDF
    </h1>
    
    <p class="description">
        Dashboard de estadísticas y métricas del sistema de visualización de actas PDF.
        Datos actualizados automáticamente cada 5 minutos.
    </p>
    
    <!-- Estadísticas en Tiempo Real -->
    <div class="analytics-realtime-section">
        <h2><span class="dashicons dashicons-clock"></span> Estadísticas en Tiempo Real</h2>
        
        <div class="analytics-cards-grid">
            <div class="analytics-card realtime-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="card-content">
                    <h3>Usuarios Activos</h3>
                    <span class="metric-value"><?php echo number_format($realtime_stats['active_users']); ?></span>
                    <span class="metric-label">Últimos 30 minutos</span>
                </div>
            </div>
            
            <div class="analytics-card realtime-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-visibility"></span>
                </div>
                <div class="card-content">
                    <h3>Visualizaciones</h3>
                    <span class="metric-value"><?php echo number_format($realtime_stats['views_last_hour']); ?></span>
                    <span class="metric-label">Última hora</span>
                </div>
            </div>
            
            <div class="analytics-card realtime-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="card-content">
                    <h3>Actividades Sospechosas</h3>
                    <span class="metric-value"><?php echo number_format($realtime_stats['suspicious_today']); ?></span>
                    <span class="metric-label">Hoy</span>
                </div>
            </div>
            
            <div class="analytics-card realtime-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="card-content">
                    <h3>Última Actividad</h3>
                    <span class="metric-value">
                        <?php 
                        if ($realtime_stats['last_activity']) {
                            echo human_time_diff(strtotime($realtime_stats['last_activity']->viewed_at), current_time('timestamp')) . ' ago';
                        } else {
                            echo 'Sin actividad';
                        }
                        ?>
                    </span>
                    <span class="metric-label">
                        <?php 
                        if ($realtime_stats['last_activity']) {
                            echo esc_html($realtime_stats['last_activity']->display_name) . ' - ' . esc_html($realtime_stats['last_activity']->title);
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas Generales -->
    <div class="analytics-general-section">
        <h2><span class="dashicons dashicons-chart-bar"></span> Estadísticas Generales</h2>
        
        <div class="analytics-cards-grid">
            <div class="analytics-card primary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="card-content">
                    <h3>Total de Actas</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['total_actas']); ?></span>
                    <span class="metric-label">Documentos activos</span>
                </div>
            </div>
            
            <div class="analytics-card primary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-visibility"></span>
                </div>
                <div class="card-content">
                    <h3>Total Visualizaciones</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['total_views']); ?></span>
                    <span class="metric-label">Páginas vistas</span>
                </div>
            </div>
            
            <div class="analytics-card primary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="card-content">
                    <h3>Usuarios Únicos</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['unique_users']); ?></span>
                    <span class="metric-label">Usuarios diferentes</span>
                </div>
            </div>
            
            <div class="analytics-card secondary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="card-content">
                    <h3>Hoy</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['views_today']); ?></span>
                    <span class="metric-label">Visualizaciones</span>
                </div>
            </div>
            
            <div class="analytics-card secondary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-calendar"></span>
                </div>
                <div class="card-content">
                    <h3>Esta Semana</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['views_this_week']); ?></span>
                    <span class="metric-label">Visualizaciones</span>
                </div>
            </div>
            
            <div class="analytics-card secondary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="card-content">
                    <h3>Este Mes</h3>
                    <span class="metric-value"><?php echo number_format($general_stats['views_this_month']); ?></span>
                    <span class="metric-label">Visualizaciones</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rankings y Listas -->
    <div class="analytics-rankings-section">
        <div class="rankings-grid">
            <!-- Top Actas -->
            <div class="ranking-card">
                <h3><span class="dashicons dashicons-star-filled"></span> Actas Más Vistas</h3>
                
                <?php if (!empty($general_stats['top_actas'])): ?>
                    <div class="ranking-list">
                        <?php foreach ($general_stats['top_actas'] as $index => $acta): ?>
                            <div class="ranking-item">
                                <span class="ranking-position"><?php echo ($index + 1); ?></span>
                                <div class="ranking-content">
                                    <span class="ranking-title"><?php echo esc_html($acta->title ?: 'Sin título'); ?></span>
                                    <span class="ranking-meta"><?php echo number_format($acta->view_count); ?> visualizaciones</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">No hay datos de visualizaciones disponibles.</p>
                <?php endif; ?>
            </div>
            
            <!-- Top Usuarios -->
            <div class="ranking-card">
                <h3><span class="dashicons dashicons-admin-users"></span> Usuarios Más Activos</h3>
                
                <?php if (!empty($general_stats['top_users'])): ?>
                    <div class="ranking-list">
                        <?php foreach ($general_stats['top_users'] as $index => $user): ?>
                            <div class="ranking-item">
                                <span class="ranking-position"><?php echo ($index + 1); ?></span>
                                <div class="ranking-content">
                                    <span class="ranking-title"><?php echo esc_html($user->display_name); ?></span>
                                    <span class="ranking-meta">
                                        Colegiado: <?php echo esc_html($user->numero_colegiado); ?> | 
                                        <?php echo number_format($user->view_count); ?> visualizaciones
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">No hay datos de usuarios disponibles.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Distribución por Dispositivos -->
    <?php if (!empty($general_stats['device_distribution'])): ?>
    <div class="analytics-devices-section">
        <h2><span class="dashicons dashicons-smartphone"></span> Distribución por Dispositivos</h2>
        
        <div class="device-stats-container">
            <div class="device-chart-container">
                <!-- Gráfico simple con CSS -->
                <div class="device-chart">
                    <?php 
                    $total_devices = array_sum(array_column($general_stats['device_distribution'], 'count'));
                    $colors = array('desktop' => '#3498db', 'mobile' => '#e74c3c', 'tablet' => '#f39c12');
                    ?>
                    
                    <?php foreach ($general_stats['device_distribution'] as $device): ?>
                        <?php 
                        $percentage = $total_devices > 0 ? ($device->count / $total_devices) * 100 : 0;
                        $color = $colors[$device->device_type] ?? '#95a5a6';
                        ?>
                        <div class="device-bar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;">
                            <span class="device-label">
                                <?php echo ucfirst($device->device_type); ?>: <?php echo number_format($device->count); ?> (<?php echo round($percentage, 1); ?>%)
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="device-legend">
                <?php foreach ($general_stats['device_distribution'] as $device): ?>
                    <?php 
                    $percentage = $total_devices > 0 ? ($device->count / $total_devices) * 100 : 0;
                    $color = $colors[$device->device_type] ?? '#95a5a6';
                    $icon = array('desktop' => 'desktop', 'mobile' => 'smartphone', 'tablet' => 'tablet');
                    ?>
                    <div class="device-legend-item">
                        <span class="device-icon dashicons dashicons-<?php echo $icon[$device->device_type] ?? 'admin-generic'; ?>"></span>
                        <span class="device-name"><?php echo ucfirst($device->device_type); ?></span>
                        <span class="device-count"><?php echo number_format($device->count); ?></span>
                        <span class="device-percentage">(<?php echo round($percentage, 1); ?>%)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Botones de Acción -->
    <div class="analytics-actions">
        <button type="button" class="button button-primary" id="refresh-analytics">
            <span class="dashicons dashicons-update"></span>
            Actualizar Estadísticas
        </button>
        
        <button type="button" class="button button-secondary" id="clear-analytics-cache">
            <span class="dashicons dashicons-trash"></span>
            Limpiar Cache
        </button>
        
        <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-logs'); ?>" class="button">
            <span class="dashicons dashicons-list-view"></span>
            Ver Logs Detallados
        </a>
    </div>
    
    <!-- Loading overlay -->
    <div id="analytics-loading" class="analytics-loading" style="display: none;">
        <div class="loading-spinner">
            <span class="dashicons dashicons-update-alt"></span>
            <p>Actualizando estadísticas...</p>
        </div>
    </div>
</div>

<!-- JavaScript para interactividad -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Actualizar estadísticas
    $('#refresh-analytics').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Actualizando...');
        $('#analytics-loading').show();
        
        // Simular actualización y recargar página
        setTimeout(function() {
            location.reload();
        }, 1500);
    });
    
    // Limpiar cache
    $('#clear-analytics-cache').on('click', function() {
        if (!confirm('¿Estás seguro de que quieres limpiar el cache de analytics?')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Limpiando...');
        
        $.post(ajaxurl, {
            action: 'clear_analytics_cache',
            nonce: '<?php echo wp_create_nonce('analytics_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Cache limpiado exitosamente');
                location.reload();
            } else {
                alert('Error al limpiar cache');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Auto-refresh cada 5 minutos
    setInterval(function() {
        // Solo actualizar estadísticas en tiempo real sin recargar toda la página
        updateRealtimeStats();
    }, 300000); // 5 minutos
    
    function updateRealtimeStats() {
        $.post(ajaxurl, {
            action: 'get_realtime_stats',
            nonce: '<?php echo wp_create_nonce('analytics_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                // Actualizar solo los valores en tiempo real
                // Esta funcionalidad se puede expandir en futuras versiones
            }
        });
    }
});
</script>
