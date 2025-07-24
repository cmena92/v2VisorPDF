<?php
/**
 * Template: Gestión de Carpetas - Admin
 * FASE 2: Sistema completo de gestión de carpetas
 */

if (!defined('ABSPATH')) exit;

function render_folders_tree($folders, $level = 0) {
    if (empty($folders)) {
        echo '<div class="empty-state"><p>No hay carpetas disponibles</p></div>';
        return;
    }
    
    foreach ($folders as $folder) {
        $isParent = $level === 0;
        $hasChildren = !empty($folder->children);
        $isPredefined = in_array($folder->slug, ['junta-directiva', 'asamblea', 'sin-clasificar']);
        
        echo '<div class="folder-item ' . ($isParent ? 'parent' : 'child') . '">';
        echo '<div class="folder-header">';
        echo '<div class="folder-info">';
        echo '<span class="dashicons ' . ($isParent ? 'dashicons-category' : 'dashicons-media-document') . '"></span>';
        echo '<span class="folder-name">' . esc_html($folder->name) . '</span>';
        
        if ($isPredefined) {
            echo '<span class="badge">Sistema</span>';
        }
        
        echo '</div>';
        echo '<div class="folder-actions">';
        
        if (!$isPredefined) {
            echo '<button class="btn-edit" data-id="' . $folder->id . '" data-name="' . esc_attr($folder->name) . '">Editar</button>';
            echo '<button class="btn-delete" data-id="' . $folder->id . '" data-name="' . esc_attr($folder->name) . '" data-actas="' . $folder->actas_count . '" data-children="' . ($hasChildren ? 'true' : 'false') . '">Eliminar</button>';
        }
        
        echo '</div></div>';
        echo '<div class="folder-stats">' . $folder->actas_count . ' actas';
        if ($hasChildren) echo ' • ' . count($folder->children) . ' subcarpetas';
        echo '</div></div>';
        
        if ($hasChildren) render_folders_tree($folder->children, $level + 1);
    }
}

function render_folder_options($folders, $selected = null, $depth = 0, $prefix = '') {
    foreach ($folders as $folder) {
        if ($folder->slug !== 'sin-clasificar' || $depth > 0) {
            $sel = $selected == $folder->id ? ' selected' : '';
            echo '<option value="' . $folder->id . '"' . $sel . '>' . $prefix . esc_html($folder->name) . ' (' . $folder->actas_count . ')</option>';
        }
        if (!empty($folder->children) && $depth < 1) {
            render_folder_options($folder->children, $selected, $depth + 1, $prefix . '└─ ');
        }
    }
}
?>

<div class="wrap">
    <h1><?php echo get_admin_page_title(); ?></h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>
    
    <div class="folders-wrap">
        <nav class="nav-tab-wrapper">
            <a href="#folders" class="nav-tab nav-tab-active" data-tab="folders">📁 Gestionar</a>
            <a href="#reassign" class="nav-tab" data-tab="reassign">🔄 Reasignar</a>
            <a href="#create" class="nav-tab" data-tab="create">➕ Crear</a>
        </nav>
        
        <!-- GESTIÓN -->
        <div id="tab-folders" class="tab-content active">
            <div class="postbox">
                <h2>Estructura de Carpetas</h2>
                <div class="inside">
                    <?php render_folders_tree($folders_hierarchy); ?>
                </div>
            </div>
        </div>
        
        <!-- REASIGNACIÓN -->
        <div id="tab-reassign" class="tab-content">
            <div class="postbox">
                <h2>Reasignar Actas</h2>
                <div class="inside">
                    <div class="controls">
                        <label>Filtrar: <select id="filter-folder"><option value="">Todas</option><?php render_folder_options($folders_hierarchy, null, 999); ?></select></label>
                        <label>Buscar: <input type="text" id="search-actas" placeholder="Título..."></label>
                        <label>Mover a: <select id="target-folder"><option value="">Seleccionar...</option><?php render_folder_options($folders_hierarchy); ?></select></label>
                        <button id="btn-reassign" class="button button-primary" disabled>Mover</button>
                    </div>
                    
                    <div id="status" class="hidden"></div>
                    
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>Acta</th>
                                <th>Carpeta</th>
                                <th>Fecha</th>
                                <th>Páginas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_actas as $acta): ?>
                                <tr class="acta-row" data-folder="<?php echo $acta->folder_id ?: ''; ?>">
                                    <td><input type="checkbox" class="acta-check" value="<?php echo $acta->id; ?>"></td>
                                    <td><strong><?php echo esc_html($acta->title ?: $acta->original_name); ?></strong></td>
                                    <td><?php echo esc_html($acta->folder_name ?: 'Sin asignar'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($acta->upload_date)); ?></td>
                                    <td><?php echo $acta->total_pages; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- CREAR -->
        <div id="tab-create" class="tab-content">
            <div class="postbox">
                <h2>Crear Carpeta</h2>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('folders_management', '_wpnonce'); ?>
                        <input type="hidden" name="action" value="create_folder">
                        <table class="form-table">
                            <tr>
                                <th>Nombre</th>
                                <td><input type="text" name="folder_name" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Carpeta Padre</th>
                                <td>
                                    <select name="parent_id">
                                        <option value="">Principal</option>
                                        <?php render_folder_options($folders_hierarchy, null, 1); ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p><input type="submit" class="button button-primary" value="Crear"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales -->
<div id="edit-modal" class="modal hidden">
    <div class="modal-content">
        <h3>Editar Carpeta</h3>
        <form>
            <input type="hidden" id="edit-id">
            <p><label>Nombre: <input type="text" id="edit-name" required></label></p>
            <p>
                <button type="button" id="save-edit" class="button button-primary">Guardar</button>
                <button type="button" class="modal-close button">Cancelar</button>
            </p>
        </form>
    </div>
</div>

<div id="delete-modal" class="modal hidden">
    <div class="modal-content">
        <h3>Eliminar Carpeta</h3>
        <p>¿Eliminar "<span id="delete-name"></span>"?</p>
        <ul id="delete-info"></ul>
        <p>
            <button type="button" id="confirm-delete" class="button button-primary">Eliminar</button>
            <button type="button" class="modal-close button">Cancelar</button>
        </p>
    </div>
</div>

<style>
.folders-wrap { margin-top: 20px; }
.tab-content { display: none; }
.tab-content.active { display: block; }

.folder-item { 
    background: white; 
    border: 1px solid #ddd; 
    margin: 10px 0; 
    padding: 15px; 
    border-radius: 4px; 
}
.folder-item.parent { border-left: 4px solid #0073aa; }
.folder-item.child { margin-left: 30px; border-left: 4px solid #00a32a; }

.folder-header { display: flex; justify-content: space-between; align-items: center; }
.folder-info { display: flex; align-items: center; gap: 10px; }
.folder-name { font-weight: bold; }
.badge { background: #0073aa; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
.folder-actions { display: flex; gap: 5px; }
.folder-stats { margin-top: 10px; font-size: 12px; color: #666; }

.controls { 
    background: #f0f0f1; 
    padding: 15px; 
    margin-bottom: 20px; 
    display: flex; 
    gap: 15px; 
    align-items: center; 
    flex-wrap: wrap;
}

.acta-row.hidden { display: none; }

.modal { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.7); 
    z-index: 999999; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
}
.modal.hidden { display: none; }
.modal-content { 
    background: white; 
    padding: 30px; 
    border-radius: 6px; 
    max-width: 500px; 
    width: 90%; 
}

.hidden { display: none !important; }

@media (max-width: 768px) {
    .folder-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .controls { flex-direction: column; align-items: stretch; }
}
</style>

<!-- JavaScript ahora se carga desde assets/js/folders-admin.js -->
