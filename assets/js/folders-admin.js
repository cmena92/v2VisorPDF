/**
 * JavaScript para Gestión de Carpetas - FASE 2
 * Archivo modular independiente
 */

class FoldersManager {
    constructor() {
        this.nonce = typeof foldersAjax !== 'undefined' ? foldersAjax.nonce : '';
        this.ajaxUrl = typeof foldersAjax !== 'undefined' ? foldersAjax.ajaxurl : ajaxurl;
        this.currentEditingFolder = null;
        this.currentDeletingFolder = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initializeState();
    }
    
    bindEvents() {
        // Navegación por pestañas
        jQuery('.nav-tab').on('click', (e) => this.handleTabClick(e));
        
        // Gestión de carpetas
        jQuery(document).on('click', '.btn-edit', (e) => this.handleEditFolder(e));
        jQuery(document).on('click', '.btn-delete', (e) => this.handleDeleteFolder(e));
        jQuery('#save-edit').on('click', () => this.saveFolder());
        jQuery('#confirm-delete').on('click', () => this.confirmDelete());
        
        // Reasignación masiva
        jQuery('#filter-folder, #search-actas').on('input change', () => this.filterActas());
        jQuery('#select-all').on('change', (e) => this.toggleSelectAll(e));
        jQuery(document).on('change', '.acta-check', () => this.updateSelection());
        jQuery('#target-folder').on('change', () => this.updateReassignButton());
        jQuery('#btn-reassign').on('click', () => this.reassignActas());
        
        // Modales
        jQuery('.modal-close').on('click', () => this.closeModals());
        jQuery(document).on('keydown', (e) => {
            if (e.key === 'Escape') this.closeModals();
        });
    }
    
    handleTabClick(e) {
        e.preventDefault();
        const $tab = jQuery(e.currentTarget);
        const targetTab = $tab.data('tab');
        
        jQuery('.nav-tab').removeClass('nav-tab-active');
        $tab.addClass('nav-tab-active');
        
        jQuery('.tab-content').removeClass('active');
        jQuery('#tab-' + targetTab).addClass('active');
    }
    
    handleEditFolder(e) {
        e.preventDefault();
        const $btn = jQuery(e.currentTarget);
        
        this.currentEditingFolder = $btn.data('id');
        jQuery('#edit-id').val($btn.data('id'));
        jQuery('#edit-name').val($btn.data('name'));
        jQuery('#edit-modal').removeClass('hidden');
    }
    
    handleDeleteFolder(e) {
        e.preventDefault();
        const $btn = jQuery(e.currentTarget);
        
        this.currentDeletingFolder = $btn.data('id');
        jQuery('#delete-name').text($btn.data('name'));
        
        const actas = parseInt($btn.data('actas')) || 0;
        const hasChildren = $btn.data('children') === 'true';
        const $info = jQuery('#delete-info').empty();
        
        if (hasChildren) {
            $info.append('<li>No se puede eliminar: contiene subcarpetas</li>');
            jQuery('#confirm-delete').prop('disabled', true);
        } else {
            if (actas > 0) {
                $info.append(`<li>${actas} actas se moverán a "Sin Clasificar"</li>`);
            } else {
                $info.append('<li style="color: green;">✓ No hay actas en esta carpeta</li>');
            }
            jQuery('#confirm-delete').prop('disabled', false);
        }
        
        jQuery('#delete-modal').removeClass('hidden');
    }
    
    saveFolder() {
        const folderName = jQuery('#edit-name').val().trim();
        if (!folderName) {
            alert('El nombre es requerido');
            return;
        }
        
        const $btn = jQuery('#save-edit');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Guardando...');
        
        jQuery.post(this.ajaxUrl, {
            action: 'update_folder',
            nonce: this.nonce,
            folder_id: this.currentEditingFolder,
            folder_name: folderName
        })
        .done((response) => {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        })
        .fail(() => {
            alert('Error de conexión');
        })
        .always(() => {
            $btn.prop('disabled', false).text(originalText);
        });
    }
    
    confirmDelete() {
        if (jQuery('#confirm-delete').prop('disabled')) return;
        
        const $btn = jQuery('#confirm-delete');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Eliminando...');
        
        jQuery.post(this.ajaxUrl, {
            action: 'delete_folder',
            nonce: this.nonce,
            folder_id: this.currentDeletingFolder
        })
        .done((response) => {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        })
        .fail(() => {
            alert('Error de conexión');
        })
        .always(() => {
            $btn.prop('disabled', false).text(originalText);
        });
    }
    
    filterActas() {
        const folderFilter = jQuery('#filter-folder').val();
        const searchText = jQuery('#search-actas').val().toLowerCase().trim();
        
        jQuery('.acta-row').each(function() {
            const $row = jQuery(this);
            const folderMatch = !folderFilter || $row.data('folder') == folderFilter;
            const titleMatch = !searchText || $row.find('td:nth-child(2)').text().toLowerCase().includes(searchText);
            
            if (folderMatch && titleMatch) {
                $row.removeClass('hidden');
            } else {
                $row.addClass('hidden');
                $row.find('.acta-check').prop('checked', false);
            }
        });
        
        this.updateReassignButton();
    }
    
    toggleSelectAll(e) {
        const isChecked = jQuery(e.currentTarget).prop('checked');
        jQuery('.acta-check:visible').prop('checked', isChecked);
        this.updateReassignButton();
    }
    
    updateSelection() {
        this.updateReassignButton();
        
        const total = jQuery('.acta-check:visible').length;
        const checked = jQuery('.acta-check:visible:checked').length;
        jQuery('#select-all').prop('checked', total > 0 && total === checked);
    }
    
    updateReassignButton() {
        const selectedCount = jQuery('.acta-check:checked').length;
        const targetFolder = jQuery('#target-folder').val();
        
        jQuery('#btn-reassign')
            .prop('disabled', selectedCount === 0 || !targetFolder)
            .text(`Mover (${selectedCount})`);
    }
    
    reassignActas() {
        const selectedIds = jQuery('.acta-check:checked').map(function() {
            return jQuery(this).val();
        }).get();
        
        const targetFolder = jQuery('#target-folder').val();
        const targetName = jQuery('#target-folder option:selected').text();
        
        if (selectedIds.length === 0) {
            alert('Selecciona al menos una acta');
            return;
        }
        
        if (!confirm(`¿Mover ${selectedIds.length} actas a "${targetName}"?`)) {
            return;
        }
        
        const $btn = jQuery('#btn-reassign');
        const $status = jQuery('#status');
        
        $btn.prop('disabled', true).text('Procesando...');
        $status.removeClass('hidden').html('<p>Moviendo actas...</p>');
        
        jQuery.post(this.ajaxUrl, {
            action: 'reassign_actas',
            nonce: this.nonce,
            acta_ids: selectedIds,
            new_folder_id: targetFolder
        })
        .done((response) => {
            if (response.success) {
                $status.html(`<p style="color: green;">${response.data.message}</p>`);
                setTimeout(() => location.reload(), 2000);
            } else {
                $status.html(`<p style="color: red;">Error: ${response.data}</p>`);
            }
        })
        .fail(() => {
            $status.html('<p style="color: red;">Error de conexión</p>');
        })
        .always(() => {
            $btn.prop('disabled', false);
            this.updateReassignButton();
        });
    }
    
    closeModals() {
        jQuery('.modal').addClass('hidden');
        this.currentEditingFolder = null;
        this.currentDeletingFolder = null;
    }
    
    initializeState() {
        this.updateReassignButton();
    }
}

// Inicializar cuando el DOM esté listo
jQuery(document).ready(function() {
    new FoldersManager();
});
