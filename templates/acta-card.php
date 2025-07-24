<?php
if (!defined('ABSPATH')) exit;
?>
<div class="acta-card" data-acta-id="<?php echo $acta->id; ?>" data-folder-id="<?php echo $acta->folder_id ?: 0; ?>">
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
        <?php if (isset($acta->folder_name) && $acta->folder_name): ?>
            <span class="acta-folder">
                📂 <?php echo esc_html($acta->folder_name); ?>
            </span>
        <?php endif; ?>
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
            👁️ Ver Acta
        </button>
        
        <div class="acta-security-info">
            <small>🔒 Documento protegido con marca de agua</small>
        </div>
    </div>
</div>
