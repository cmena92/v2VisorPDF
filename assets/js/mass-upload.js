/**
 * JavaScript para Subida Masiva - FASE 3
 * Maneja drag & drop, progreso y procesamiento
 */

class MassUploadManager {
    constructor() {
        this.config = massUploadAjax;
        this.files = [];
        this.currentSession = null;
        this.isProcessing = false;
        this.processedCount = 0;
        this.successCount = 0;
        this.failedCount = 0;
        
        this.init();
    }
    
    init() {
        this.createDropZone();
        this.bindEvents();
        this.initializeUI();
    }
    
    createDropZone() {
        const dropZone = document.getElementById('drop-zone');
        if (!dropZone) return;
        
        // Prevenir comportamiento por defecto
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, this.preventDefaults.bind(this), false);
            document.body.addEventListener(eventName, this.preventDefaults.bind(this), false);
        });
        
        // Resaltar zona al arrastrar
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('drop-zone-highlight');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drop-zone-highlight');
            }, false);
        });
        
        // Manejar drop
        dropZone.addEventListener('drop', this.handleDrop.bind(this), false);
    }
    
    bindEvents() {
        // Selector de archivos
        jQuery('#file-input').on('change', (e) => {
            this.handleFileSelect(e.target.files);
        });
        
        // Botón de seleccionar
        jQuery('#btn-select-files').on('click', () => {
            jQuery('#file-input').trigger('click');
        });
        
        // Botón de limpiar
        jQuery('#btn-clear-files').on('click', () => {
            this.clearFiles();
        });
        
        // Botón de subir
        jQuery('#btn-start-upload').on('click', () => {
            this.startUpload();
        });
        
        // Selector de carpeta
        jQuery('#target-folder').on('change', () => {
            this.updateUploadButton();
        });
    }
    
    initializeUI() {
        this.updateFilesList();
        this.updateUploadButton();
        this.resetProgress();
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        this.handleFileSelect(files);
    }
    
    handleFileSelect(fileList) {
        const files = Array.from(fileList);
        
        // Filtrar solo PDFs
        const pdfFiles = files.filter(file => file.type === 'application/pdf');
        
        if (pdfFiles.length !== files.length) {
            this.showNotification('Solo se permiten archivos PDF', 'warning');
        }
        
        // Verificar límite de archivos
        if (this.files.length + pdfFiles.length > this.config.maxFiles) {
            this.showNotification(`Máximo ${this.config.maxFiles} archivos permitidos`, 'error');
            return;
        }
        
        // Verificar tamaño de archivos
        const oversizedFiles = pdfFiles.filter(file => file.size > this.config.maxFileSize);
        if (oversizedFiles.length > 0) {
            this.showNotification(`${oversizedFiles.length} archivos son demasiado grandes (máximo 10MB)`, 'error');
            return;
        }
        
        // Agregar archivos válidos
        pdfFiles.forEach(file => {
            // Verificar duplicados por nombre
            if (!this.files.find(f => f.name === file.name)) {
                this.files.push({
                    file: file,
                    id: 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                    name: file.name,
                    size: file.size,
                    title: this.extractTitle(file.name),
                    description: '',
                    status: 'pending',
                    error: null
                });
            }
        });
        
        this.updateFilesList();
        this.updateUploadButton();
        
        if (pdfFiles.length > 0) {
            this.showNotification(`${pdfFiles.length} archivos agregados`, 'success');
        }
    }
    
    extractTitle(filename) {
        // Extraer título del nombre del archivo
        return filename.replace(/\.[^/.]+$/, "").replace(/[-_]/g, " ");
    }
    
    updateFilesList() {
        const container = jQuery('#files-list');
        container.empty();
        
        if (this.files.length === 0) {
            container.html('<p class="no-files">No hay archivos seleccionados</p>');
            return;
        }
        
        this.files.forEach(fileData => {
            const fileItem = this.createFileItem(fileData);
            container.append(fileItem);
        });
        
        // Actualizar contador
        jQuery('#files-count').text(`${this.files.length} archivo(s) seleccionado(s)`);
    }
    
    createFileItem(fileData) {
        const statusClass = fileData.status === 'success' ? 'success' : 
                           fileData.status === 'error' ? 'error' : 
                           fileData.status === 'processing' ? 'processing' : 'pending';
        
        const statusIcon = fileData.status === 'success' ? '✅' : 
                          fileData.status === 'error' ? '❌' : 
                          fileData.status === 'processing' ? '⏳' : '📄';
        
        return `
            <div class="file-item ${statusClass}" data-file-id="${fileData.id}">
                <div class="file-header">
                    <div class="file-info">
                        <span class="file-icon">${statusIcon}</span>
                        <div class="file-details">
                            <div class="file-name">${fileData.name}</div>
                            <div class="file-size">${this.formatFileSize(fileData.size)}</div>
                        </div>
                    </div>
                    <div class="file-actions">
                        ${fileData.status === 'pending' ? `<button type="button" class="btn-remove-file" data-file-id="${fileData.id}">🗑️</button>` : ''}
                    </div>
                </div>
                <div class="file-metadata">
                    <input type="text" placeholder="Título" value="${fileData.title}" 
                           onchange="massUploadManager.updateFileMetadata('${fileData.id}', 'title', this.value)"
                           ${fileData.status !== 'pending' ? 'disabled' : ''}>
                    <input type="text" placeholder="Descripción (opcional)" value="${fileData.description}"
                           onchange="massUploadManager.updateFileMetadata('${fileData.id}', 'description', this.value)"
                           ${fileData.status !== 'pending' ? 'disabled' : ''}>
                </div>
                ${fileData.error ? `<div class="file-error">Error: ${fileData.error}</div>` : ''}
                ${fileData.status === 'processing' ? '<div class="file-progress"><div class="progress-bar"></div></div>' : ''}
            </div>
        `;
    }
    
    updateFileMetadata(fileId, field, value) {
        const file = this.files.find(f => f.id === fileId);
        if (file) {
            file[field] = value;
        }
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    clearFiles() {
        if (this.isProcessing) {
            this.showNotification('No se puede limpiar durante el procesamiento', 'warning');
            return;
        }
        
        this.files = [];
        this.updateFilesList();
        this.updateUploadButton();
        this.resetProgress();
        this.showNotification('Archivos limpiados', 'info');
    }
    
    updateUploadButton() {
        const hasFiles = this.files.length > 0;
        const hasFolder = jQuery('#target-folder').val();
        const canUpload = hasFiles && hasFolder && !this.isProcessing;
        
        jQuery('#btn-start-upload')
            .prop('disabled', !canUpload)
            .text(this.isProcessing ? 'Procesando...' : `Subir ${this.files.length} archivo(s)`);
    }
    
    async startUpload() {
        if (this.isProcessing) return;
        
        const folderId = jQuery('#target-folder').val();
        if (!folderId) {
            this.showNotification('Selecciona una carpeta destino', 'error');
            return;
        }
        
        if (this.files.length === 0) {
            this.showNotification('No hay archivos para subir', 'warning');
            return;
        }
        
        this.isProcessing = true;
        this.processedCount = 0;
        this.successCount = 0;
        this.failedCount = 0;
        
        try {
            // Iniciar sesión de subida
            const sessionResponse = await this.initializeUploadSession(folderId);
            if (!sessionResponse.success) {
                throw new Error(sessionResponse.data);
            }
            
            this.currentSession = sessionResponse.data.session_id;
            this.showProgress();
            
            // Procesar archivos uno por uno
            for (let i = 0; i < this.files.length; i++) {
                await this.processFile(this.files[i], i);
                this.updateOverallProgress();
            }
            
            this.completeUpload();
            
        } catch (error) {
            this.showNotification('Error durante la subida: ' + error.message, 'error');
            this.isProcessing = false;
            this.updateUploadButton();
        }
    }
    
    async initializeUploadSession(folderId) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mass_upload_files',
                    nonce: this.config.nonce,
                    folder_id: folderId,
                    files_count: this.files.length
                },
                timeout: 30000,
                success: resolve,
                error: (xhr, status, error) => reject(new Error(error || 'Error de conexión'))
            });
        });
    }
    
    async processFile(fileData, index) {
        fileData.status = 'processing';
        this.updateFileItem(fileData);
        
        const formData = new FormData();
        formData.append('action', 'process_single_file');
        formData.append('nonce', this.config.nonce);
        formData.append('session_id', this.currentSession);
        formData.append('file_index', index);
        formData.append('file', fileData.file);
        formData.append('title', fileData.title);
        formData.append('description', fileData.description);
        
        try {
            const response = await this.uploadFile(formData);
            
            if (response.success) {
                fileData.status = 'success';
                this.successCount++;
            } else {
                fileData.status = 'error';
                fileData.error = response.data;
                this.failedCount++;
            }
            
        } catch (error) {
            fileData.status = 'error';
            fileData.error = error.message;
            this.failedCount++;
        }
        
        this.processedCount++;
        this.updateFileItem(fileData);
    }
    
    async uploadFile(formData) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 60000, // 1 minuto por archivo
                success: resolve,
                error: (xhr, status, error) => reject(new Error(error || 'Error de conexión'))
            });
        });
    }
    
    updateFileItem(fileData) {
        const fileElement = jQuery(`[data-file-id="${fileData.id}"]`);
        const newItem = jQuery(this.createFileItem(fileData));
        fileElement.replaceWith(newItem);
        
        // Re-bind eventos para el botón de eliminar
        newItem.find('.btn-remove-file').on('click', (e) => {
            this.removeFile(e.target.getAttribute('data-file-id'));
        });
    }
    
    removeFile(fileId) {
        if (this.isProcessing) {
            this.showNotification('No se puede eliminar durante el procesamiento', 'warning');
            return;
        }
        
        this.files = this.files.filter(f => f.id !== fileId);
        this.updateFilesList();
        this.updateUploadButton();
    }
    
    showProgress() {
        jQuery('#upload-progress').removeClass('hidden');
        this.updateOverallProgress();
    }
    
    updateOverallProgress() {
        const percentage = this.files.length > 0 ? (this.processedCount / this.files.length) * 100 : 0;
        
        jQuery('#progress-bar').css('width', percentage + '%');
        jQuery('#progress-text').text(`${this.processedCount}/${this.files.length} archivos procesados`);
        jQuery('#progress-stats').html(`
            <span class="success-count">✅ ${this.successCount} exitosos</span>
            <span class="failed-count">❌ ${this.failedCount} fallaron</span>
        `);
    }
    
    completeUpload() {
        this.isProcessing = false;
        this.updateUploadButton();
        
        const message = `Subida completada: ${this.successCount} exitosos, ${this.failedCount} fallaron`;
        this.showNotification(message, this.failedCount > 0 ? 'warning' : 'success');
        
        // Mostrar reporte final
        this.showUploadReport();
        
        // Limpiar archivos exitosos después de 5 segundos
        if (this.successCount > 0) {
            setTimeout(() => {
                this.files = this.files.filter(f => f.status !== 'success');
                this.updateFilesList();
                this.updateUploadButton();
            }, 5000);
        }
    }
    
    showUploadReport() {
        const report = `
            <div class="upload-report">
                <h3>📊 Reporte de Subida</h3>
                <div class="report-stats">
                    <div class="stat-item success">
                        <span class="stat-number">${this.successCount}</span>
                        <span class="stat-label">Exitosos</span>
                    </div>
                    <div class="stat-item failed">
                        <span class="stat-number">${this.failedCount}</span>
                        <span class="stat-label">Fallaron</span>
                    </div>
                    <div class="stat-item total">
                        <span class="stat-number">${this.files.length}</span>
                        <span class="stat-label">Total</span>
                    </div>
                </div>
                ${this.failedCount > 0 ? '<p class="report-note">Los archivos con error permanecen en la lista para reintentar.</p>' : ''}
            </div>
        `;
        
        jQuery('#upload-report').html(report).removeClass('hidden');
    }
    
    resetProgress() {
        jQuery('#upload-progress').addClass('hidden');
        jQuery('#upload-report').addClass('hidden');
        jQuery('#progress-bar').css('width', '0%');
        jQuery('#progress-text').text('');
        jQuery('#progress-stats').text('');
    }
    
    showNotification(message, type = 'info') {
        const notification = `
            <div class="upload-notification ${type}">
                <span class="notification-icon">${this.getNotificationIcon(type)}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        const $notification = jQuery(notification);
        jQuery('#notifications-container').append($notification);
        
        // Auto-hide después de 5 segundos
        setTimeout(() => {
            $notification.fadeOut(() => $notification.remove());
        }, 5000);
    }
    
    getNotificationIcon(type) {
        const icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || icons.info;
    }
}

// Inicializar cuando el DOM esté listo
jQuery(document).ready(function() {
    window.massUploadManager = new MassUploadManager();
    
    // Bind eventos para botones de eliminar archivos
    jQuery(document).on('click', '.btn-remove-file', function(e) {
        e.preventDefault();
        const fileId = jQuery(this).data('file-id');
        window.massUploadManager.removeFile(fileId);
    });
});
