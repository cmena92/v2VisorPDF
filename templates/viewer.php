<?php
if (!defined('ABSPATH')) exit;
?>

<div class="actas-viewer-container">
    
        <h3>Biblioteca de Actas</h3>
           (Colegiado: <strong><?php echo esc_html($numero_colegiado); ?></strong>)</p>
    
    <!-- Debug Info -->
    <div class="debug-info" style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px; color: #666;">
        <strong>Debug:</strong> 
        Usuario ID: <?php echo get_current_user_id(); ?> | 
        Número Colegiado: <?php echo esc_html($numero_colegiado); ?> | 
        Total Actas: <?php echo count($actas); ?> |
        AJAX URL: <?php echo admin_url('admin-ajax.php'); ?> |
        Nonce: <?php echo wp_create_nonce('actas_nonce'); ?>
    </div>
    
    
    <?php if (empty($actas)): ?>
        <div class="no-actas-message">
            <p>No hay actas disponibles en este momento.</p>
        </div>
    <?php else: ?>
        <div class="actas-list">
            <?php foreach ($actas as $acta): ?>
                <div class="acta-card" data-acta-id="<?php echo $acta->id; ?>">
                    <div class="acta-title">
                        <?php echo esc_html($acta->title ?: 'Acta sin título'); ?>
                    </div>
                    
                    <div class="acta-meta">
                        <span class="acta-date">
                            📅 <?php echo date('d/m/Y', strtotime($acta->upload_date)); ?>
                        </span>
                        <span class="acta-pages">
                            📄 <?php echo intval($acta->total_pages); ?> páginas
                        </span>
                        <span class="acta-size">
                            💾 <?php echo size_format($acta->file_size); ?>
                        </span>
                    </div>
                    
                    <?php if ($acta->description): ?>
                        <div class="acta-description">
                            <?php echo esc_html($acta->description); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="acta-actions">
                        <button class="ver-acta-btn" 
                                data-acta-id="<?php echo $acta->id; ?>"
                                data-total-pages="<?php echo intval($acta->total_pages); ?>"
                                data-acta-title="<?php echo esc_attr($acta->title); ?>">
                             Ver Acta
                        </button>
                        
                        <div class="acta-security-info">
                            <small>🔒 Documento protegido con marca de agua</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="actas-info">
            <div class="security-notice">
                <h4> Información de Seguridad</h4>
                <ul>
                    <li>Todos los documentos están protegidos con su número de colegiado</li>
                    <li>Se registra cada visualización para auditoría</li>
                    <li>No es posible descargar o imprimir los documentos</li>
                    <li>El contenido está protegido contra capturas de pantalla</li>
                </ul>
            </div>
            
            <div class="usage-instructions">
                <h4> Instrucciones de Uso</h4>
                <ul>
                    <li>Haga clic en "Ver Acta" para abrir el documento</li>
                    <li>Use los botones de navegación para cambiar de página</li>
                    <li>Puede ir directamente a una página específica</li>
                    <li>El documento se carga de forma segura página por página</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>