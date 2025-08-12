<?php
if (!defined('ABSPATH')) exit;
?>
<tr class="acta-row" data-acta-id="<?php echo $acta->id; ?>" data-folder-id="<?php echo $acta->folder_id ?: 0; ?>">
    <td class="acta-title-cell">
        <img src="https://preproduccion.cpic.or.cr/wp-content/wp-file-download/icons/svg/pdf.svg?version=1733930755" 
             alt="PDF" class="pdf-icon" width="20" height="20">
        <span class="acta-title-text"><?php echo esc_html($acta->title ?: 'Acta sin título'); ?></span>

        <button class="ver-acta-btn-mobile" 
                data-acta-id="<?php echo $acta->id; ?>"
                data-total-pages="<?php echo intval($acta->total_pages); ?>"
                data-acta-title="<?php echo esc_attr($acta->title); ?>">
            Ver Acta
        </button>
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
