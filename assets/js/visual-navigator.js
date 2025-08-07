/**
 * JavaScript para Navegador Visual de Actas
 * Maneja filtros unificados, AJAX y navegación
 */

(function($) {
    'use strict';
    
    console.log('🚀 Navegador Visual JS iniciando...'); // Debug inicial
    
    // Variables globales
    let currentFilters = {
        folder_id: 0,
        search_term: '',
        date_from: '',
        date_to: '',
        order_by: 'upload_date',
        order_direction: 'DESC',
        page: 1
    };
    
    let isLoading = false;
    let currentRequest = null;
    
    /**
     * Inicialización cuando el DOM esté listo
     */
    $(document).ready(function() {
        console.log('🚀 DOM listo, inicializando navegador visual...'); // Debug
        initVisualNavigator();
        loadInitialData();
    });
    
    /**
     * Inicializar eventos y configuración (Versión Simplificada Corregida)
     */
    function initVisualNavigator() {
        // Selector de carpetas (dropdown) - EVENTO PRINCIPAL
        $('#folder-selector').on('change', function(e) {
            const selectedFolderId = parseInt($(this).val()) || 0;
            console.log('Carpeta seleccionada:', selectedFolderId); // Debug
            selectFolderById(selectedFolderId);
        });
        
        // Navegación por breadcrumbs
        $(document).on('click', '.breadcrumb-link', function(e) {
            e.preventDefault();
            const folderId = parseInt($(this).data('folder-id')) || 0;
            console.log('Breadcrumb clickeado:', folderId); // Debug
            selectFolderById(folderId);
        });
        
        // Click en actas para abrir visor (mejorado)
        $(document).on('click', '.acta-card', function(e) {
            // Si el clic fue en el botón, no procesar el clic de la tarjeta
            if ($(e.target).closest('.btn-ver-acta').length) {
                return;
            }
            
            const actaId = $(this).data('acta-id');
            const actaTitle = $(this).find('.acta-title').text();
            openActaViewer(actaId, actaTitle);
        });
        
        // Click en botón "Ver Acta"
        $(document).on('click', '.btn-ver-acta', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Evitar que se dispare el evento de la tarjeta
            
            const actaId = $(this).data('acta-id');
            const actaTitle = $(this).data('acta-title');
            
            console.log('Botón Ver Acta clickeado:', actaId, actaTitle); // Debug
            
            openActaViewer(actaId, actaTitle);
        });
        
        // Paginación
        $(document).on('click', '.pagination-btn', function(e) {
            e.preventDefault();
            if ($(this).hasClass('disabled') || $(this).hasClass('active')) {
                return;
            }
            
            const page = parseInt($(this).data('page'));
            if (page > 0) {
                currentFilters.page = page;
                loadActas();
            }
        });
        
        console.log('Navegador visual inicializado'); // Debug
    }
    
    /**
     * Seleccionar carpeta por ID (Corregido)
     */
    function selectFolderById(folderId) {
        console.log('selectFolderById llamado con:', folderId); // Debug
        
        // Actualizar dropdown
        $('#folder-selector').val(folderId);
        
        // Actualizar filtros
        currentFilters.folder_id = folderId;
        currentFilters.page = 1; // Resetear a primera página
        
        console.log('Filtros actualizados:', currentFilters); // Debug
        
        // Cargar actas
        loadActas();
    }
    
    /**
     * Aplicar filtro por carpeta seleccionada
     */
    function applyFilters() {
        // Solo filtro por carpeta, sin otros filtros
        currentFilters.page = 1; // Resetear a primera página
        loadActas();
    }
    
    /**
     * Limpiar filtros (solo resetear a "Todas las actas")
     */
    function clearAllFilters() {
        // Resetear filtros a valores por defecto
        currentFilters = {
            folder_id: 0,
            search_term: '',
            date_from: '',
            date_to: '',
            order_by: window.visualNavigatorData.defaultOrder,
            order_direction: window.visualNavigatorData.defaultDirection,
            page: 1
        };
        
        // Resetear selector de carpetas
        $('#folder-selector').val(0);
        
        // Cargar actas
        loadActas();
    }
    
    /**
     * Cargar datos iniciales
     */
    function loadInitialData() {
        // Configurar filtros iniciales
        currentFilters.order_by = window.visualNavigatorData.defaultOrder;
        currentFilters.order_direction = window.visualNavigatorData.defaultDirection;
        
        // Cargar actas
        loadActas();
    }
    
    /**
     * Cargar actas con AJAX (Con debugging mejorado)
     */
    function loadActas() {
        console.log('loadActas iniciado con filtros:', currentFilters); // Debug
        
        if (isLoading) {
            if (currentRequest) {
                currentRequest.abort();
            }
        }
        
        isLoading = true;
        showLoading(true);
        hideResults();
        
        const requestData = {
            action: 'unified_navigator',
            nonce: visualNavigator.nonce,
            ...currentFilters,
            per_page: window.visualNavigatorData.perPage
        };
        
        console.log('Datos de la petición AJAX:', requestData); // Debug
        
        currentRequest = $.post(visualNavigator.ajax_url, requestData)
            .done(function(response) {
                console.log('Respuesta AJAX recibida:', response); // Debug
                
                if (response.success) {
                    displayResults(response.data);
                    updateBreadcrumb(response.data.breadcrumb);
                    updateResultsInfo(response.data);
                    showResults();
                    
                    console.log('Resultados mostrados:', response.data.actas.length, 'actas'); // Debug
                } else {
                    console.error('Error en respuesta AJAX:', response.data); // Debug
                    showError(response.data || 'Error al cargar las actas');
                }
            })
            .fail(function(xhr, status, error) {
                if (status !== 'abort') {
                    console.error('Error AJAX:', error, xhr); // Debug
                    showError('Error de conexión. Por favor, inténtelo de nuevo.');
                }
            })
            .always(function() {
                isLoading = false;
                showLoading(false);
                currentRequest = null;
                console.log('Petición AJAX completada'); // Debug
            });
    }
    
    /**
     * Mostrar/ocultar estado de carga
     */
    function showLoading(show) {
        if (show) {
            $('#loading-state').fadeIn(200);
        } else {
            $('#loading-state').fadeOut(200);
        }
    }
    
    /**
     * Mostrar/ocultar resultados
     */
    function showResults() {
        $('#results-container').fadeIn(300);
        $('#no-results').hide();
    }
    
    function hideResults() {
        $('#results-container').hide();
        $('#no-results').hide();
    }
    
    /**
     * Mostrar mensaje de sin resultados
     */
    function showNoResults() {
        $('#results-container').hide();
        $('#no-results').fadeIn(300);
    }
    
    /**
     * Mostrar error
     */
    function showError(message) {
        showNotification(message, 'error');
        showNoResults();
    }
    
    /**
     * Mostrar notificación
     */
    function showNotification(message, type = 'info') {
        // Crear notificación temporal
        const $notification = $(`
            <div class="visual-nav-notification ${type}" style="
                position: fixed; 
                top: 20px; 
                right: 20px; 
                background: ${type === 'error' ? '#dc3232' : type === 'warning' ? '#ffb900' : '#0073aa'};
                color: white; 
                padding: 15px 20px; 
                border-radius: 6px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 9999;
                font-size: 14px;
                max-width: 300px;
                animation: slideInRight 0.3s ease-out;
            ">
                ${message}
            </div>
        `);
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }
    
    /**
     * Mostrar resultados
     */
    function displayResults(data) {
        const { actas, pagination } = data;
        
        if (!actas || actas.length === 0) {
            showNoResults();
            return;
        }
        
        // Generar HTML de actas
        let actasHTML = '';
        actas.forEach(function(acta) {
            actasHTML += generateActaCardHTML(acta);
        });
        
        // Actualizar grid
        $('#actas-grid').html(actasHTML);
        
        // Actualizar paginación
        updatePagination(pagination);
        
        // Scroll suave al inicio de resultados
        $('html, body').animate({
            scrollTop: $('#results-container').offset().top - 100
        }, 500);
    }
    
    /**
     * Generar HTML de tarjeta de acta (Corregido - Sin onclick inline)
     */
    function generateActaCardHTML(acta) {
        const description = acta.description || 'Sin descripción disponible';
        const truncatedDesc = description.length > 120 ? description.substring(0, 120) + '...' : description;
        
        return `
            <div class="acta-card" data-acta-id="${acta.id}" role="button" tabindex="0">
                <div class="acta-card-header">
                    <h3 class="acta-title">${escapeHtml(acta.title)}</h3>
                    <span class="acta-folder">${escapeHtml(acta.folder_name)}</span>
                </div>
                
                <div class="acta-description">
                    ${escapeHtml(truncatedDesc)}
                </div>
                
                <div class="acta-meta">
                    <div class="acta-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        ${acta.upload_date_formatted}
                    </div>
                    <div class="acta-pages">
                        <span class="dashicons dashicons-media-document"></span>
                        ${acta.total_pages || 0} páginas
                    </div>
                </div>
                
                <div class="acta-actions">
                    <button class="btn-ver-acta" data-acta-id="${acta.id}" data-acta-title="${escapeHtml(acta.title)}">
                        <span class="dashicons dashicons-visibility"></span>
                        Ver Acta
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Actualizar breadcrumbs con iconos jerárquicos
     */
    function updateBreadcrumb(breadcrumb) {
        if (!breadcrumb || breadcrumb.length === 0) {
            $('#breadcrumb-container').empty();
            return;
        }
        
        let breadcrumbHTML = '';
        breadcrumb.forEach(function(item, index) {
            if (index > 0) {
                breadcrumbHTML += '<span class="breadcrumb-separator"><span class="dashicons dashicons-arrow-right-alt2"></span></span>';
            }
            
            // Determinar icono según el tipo de carpeta
            let icon = '';
            if (item.folder_id === 0) {
                icon = '📋'; // Todas las actas
            } else if (item.is_parent) {
                icon = '📁'; // Carpeta padre
            } else if (item.parent_id && item.parent_id > 0) {
                icon = '📄'; // Subcarpeta
            } else {
                icon = '📋'; // Carpeta normal
            }
            
            const displayName = item.title || item.name;
            
            if (item.is_current) {
                breadcrumbHTML += `<span class="breadcrumb-current">${icon} ${escapeHtml(displayName)}</span>`;
            } else {
                breadcrumbHTML += `<a href="#" class="breadcrumb-link" data-folder-id="${item.folder_id}">${icon} ${escapeHtml(displayName)}</a>`;
            }
        });
        
        $('#breadcrumb-container').html(breadcrumbHTML);
    }
    
    /**
     * Actualizar información de resultados (Simplificado)
     */
    function updateResultsInfo(data) {
        const { pagination } = data;
        
        // Contador de resultados
        let countText = '';
        if (pagination.total === 0) {
            countText = 'No se encontraron actas';
        } else if (pagination.total === 1) {
            countText = '1 acta encontrada';
        } else {
            const start = ((pagination.page - 1) * pagination.per_page) + 1;
            const end = Math.min(pagination.page * pagination.per_page, pagination.total);
            countText = `Mostrando ${start}-${end} de ${pagination.total.toLocaleString()} actas`;
        }
        
        $('#results-count').text(countText);
        
        // No mostrar filtros aplicados ya que solo tenemos carpetas
        $('#current-filters').empty();
        
        // Mostrar/ocultar sección de info
        if (pagination.total > 0) {
            $('#results-info').show();
        } else {
            $('#results-info').hide();
        }
    }
    
    /**
     * Actualizar paginación
     */
    function updatePagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#pagination-container').hide();
            return;
        }
        
        let paginationHTML = '';
        
        // Botón anterior
        const prevDisabled = pagination.page <= 1 ? 'disabled' : '';
        paginationHTML += `<button class="pagination-btn ${prevDisabled}" data-page="${pagination.page - 1}">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Anterior
        </button>`;
        
        // Números de página
        const startPage = Math.max(1, pagination.page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.page + 2);
        
        if (startPage > 1) {
            paginationHTML += `<button class="pagination-btn" data-page="1">1</button>`;
            if (startPage > 2) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.page ? 'active' : '';
            paginationHTML += `<button class="pagination-btn ${activeClass}" data-page="${i}">${i}</button>`;
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
            paginationHTML += `<button class="pagination-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</button>`;
        }
        
        // Botón siguiente
        const nextDisabled = pagination.page >= pagination.total_pages ? 'disabled' : '';
        paginationHTML += `<button class="pagination-btn ${nextDisabled}" data-page="${pagination.page + 1}">
            Siguiente
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </button>`;
        
        $('#pagination-container').html(paginationHTML).show();
    }
    
    /**
     * Abrir visor de PDF (Mejorado)
     */
    function openActaViewer(actaId, actaTitle) {
        console.log('Abriendo visor para acta:', actaId, actaTitle); // Debug
        
        // Verificar si ya existe el visor del sistema principal
        if (typeof window.visorPDFCrisman !== 'undefined' && window.visorPDFCrisman.openViewer) {
            // Usar el visor del sistema principal
            window.visorPDFCrisman.openViewer(actaId, actaTitle);
            return;
        }
        
        // Si no existe el visor principal, crear modal básico
        if ($('#pdf-viewer-modal').length === 0) {
            createBasicPDFModal();
        }
        
        // Mostrar modal
        $('#modal-title').text(`Visualizando: ${actaTitle}`);
        $('#pdf-viewer-modal').fadeIn(300);
        
        // Cargar contenido del visor
        loadPDFViewer(actaId, actaTitle);
    }
    
    /**
     * Crear modal básico de PDF
     */
    function createBasicPDFModal() {
        const modalHTML = `
            <div id="pdf-viewer-modal" class="pdf-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            ">
                <div class="pdf-modal-content" style="
                    background: white;
                    border-radius: 8px;
                    width: 100%;
                    height: 100%;
                    max-width: 1200px;
                    max-height: 90vh;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                ">
                    <div class="pdf-modal-header" style="
                        padding: 20px;
                        border-bottom: 1px solid #ddd;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        background: #f9f9f9;
                    ">
                        <h3 id="modal-title" style="margin: 0; font-size: 18px; color: #333;">Visualizando Acta</h3>
                        <button class="pdf-modal-close" onclick="closePDFModal()" style="
                            background: none;
                            border: none;
                            font-size: 24px;
                            cursor: pointer;
                            padding: 5px;
                            line-height: 1;
                            color: #666;
                        ">&times;</button>
                    </div>
                    <div class="pdf-modal-body" style="
                        flex: 1;
                        overflow: hidden;
                        position: relative;
                    ">
                        <div id="pdf-viewer-container" style="
                            width: 100%;
                            height: 100%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 18px;
                            color: #666;
                        ">
                            <!-- El visor PDF se carga aquí -->
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
    }
    
    /**
     * Cargar visor PDF para el acta
     */
    function loadPDFViewer(actaId, actaTitle) {
        const container = $('#pdf-viewer-container');
        
        // Mostrar estado de carga
        container.html(`
            <div style="text-align: center;">
                <div class="dashicons dashicons-update" style="
                    width: 48px;
                    height: 48px;
                    font-size: 48px;
                    animation: spin 1s linear infinite;
                    margin-bottom: 20px;
                    color: #0073aa;
                "></div>
                <p>Cargando visor PDF...</p>
                <p style="font-size: 14px; color: #999;">Acta ID: ${actaId}</p>
            </div>
        `);
        
        // Verificar si existe el endpoint para cargar el visor
        const checkData = {
            action: 'load_pdf_page',
            nonce: visualNavigator.nonce,
            acta_id: actaId,
            page_num: 1
        };
        
        $.post(visualNavigator.ajax_url, checkData)
            .done(function(response, textStatus, xhr) {
                if (xhr.getResponseHeader('content-type') && xhr.getResponseHeader('content-type').includes('image')) {
                    // Es una imagen - crear visor básico
                    createBasicImageViewer(actaId, actaTitle);
                } else {
                    // Respuesta inesperada
                    showPDFError('El visor PDF no está disponible en este momento.');
                }
            })
            .fail(function() {
                showPDFError('No se pudo cargar el visor PDF. Verifique que el sistema esté configurado correctamente.');
            });
    }
    
    /**
     * Crear visor básico de imágenes
     */
    function createBasicImageViewer(actaId, actaTitle) {
        const container = $('#pdf-viewer-container');
        
        container.html(`
            <div style="width: 100%; height: 100%; display: flex; flex-direction: column;">
                <div style="background: #f0f0f0; padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">
                    <button onclick="previousPage()" style="margin-right: 10px; padding: 8px 15px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        ← Anterior
                    </button>
                    <span id="page-info">Página 1</span>
                    <button onclick="nextPage()" style="margin-left: 10px; padding: 8px 15px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Siguiente →
                    </button>
                </div>
                <div style="flex: 1; overflow: auto; text-align: center; padding: 20px; background: #f9f9f9;">
                    <img id="pdf-page-image" src="" style="max-width: 100%; max-height: 100%; box-shadow: 0 4px 8px rgba(0,0,0,0.2);" alt="Página del PDF">
                </div>
            </div>
        `);
        
        // Inicializar variables globales para el visor
        window.currentActaId = actaId;
        window.currentPage = 1;
        window.totalPages = 1; // Se actualizará cuando sepamos cuántas páginas tiene
        
        // Cargar primera página
        loadPDFPage(1);
    }
    
    /**
     * Cargar página específica del PDF
     */
    window.loadPDFPage = function(pageNum) {
        const img = $('#pdf-page-image');
        const pageInfo = $('#page-info');
        
        img.attr('src', '');
        pageInfo.text('Cargando...');
        
        const pageData = {
            action: 'load_pdf_page',
            nonce: visualNavigator.nonce,
            acta_id: window.currentActaId,
            page_num: pageNum
        };
        
        $.post(visualNavigator.ajax_url, pageData)
            .done(function(data, textStatus, xhr) {
                if (xhr.getResponseHeader('content-type') && xhr.getResponseHeader('content-type').includes('image')) {
                    // Crear URL de blob para la imagen
                    const blob = new Blob([data], { type: 'image/png' });
                    const imageUrl = URL.createObjectURL(blob);
                    
                    img.attr('src', imageUrl);
                    window.currentPage = pageNum;
                    pageInfo.text(`Página ${pageNum}`);
                    
                    // Limpiar URL anterior para evitar memory leaks
                    setTimeout(() => URL.revokeObjectURL(imageUrl), 30000);
                } else {
                    showPDFError('Error al cargar la página del PDF.');
                }
            })
            .fail(function() {
                showPDFError('Error de conexión al cargar la página.');
            });
    };
    
    /**
     * Funciones de navegación del PDF
     */
    window.previousPage = function() {
        if (window.currentPage > 1) {
            window.loadPDFPage(window.currentPage - 1);
        }
    };
    
    window.nextPage = function() {
        window.loadPDFPage(window.currentPage + 1);
    };
    
    /**
     * Mostrar error en el visor PDF
     */
    function showPDFError(message) {
        $('#pdf-viewer-container').html(`
            <div style="text-align: center; padding: 40px;">
                <div class="dashicons dashicons-warning" style="
                    width: 64px;
                    height: 64px;
                    font-size: 64px;
                    color: #dc3232;
                    margin-bottom: 20px;
                "></div>
                <h3 style="margin: 0 0 10px 0; color: #333;">Error en el Visor</h3>
                <p style="color: #666; margin-bottom: 20px;">${message}</p>
                <button onclick="closePDFModal()" style="
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                ">Cerrar</button>
            </div>
        `);
    }
    
    /**
     * Función global para cerrar modal
     */
    window.closePDFModal = function() {
        $('#pdf-viewer-modal').fadeOut(300);
    };
    
    /**
     * Función global para limpiar filtros (usada desde template)
     */
    window.clearAllFilters = function() {
        clearAllFilters();
    };
    
    /**
     * Escapar HTML para seguridad
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Manejo de teclado para accesibilidad
     */
    $(document).on('keydown', '.acta-card', function(e) {
        if (e.keyCode === 13 || e.keyCode === 32) { // Enter o Espacio
            e.preventDefault();
            $(this).click();
        }
    });
    
    // Cerrar modal con Escape
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#pdf-viewer-modal').is(':visible')) { // Escape
            window.closePDFModal();
        }
    });
    
    /**
     * Manejar errores globales de AJAX
     */
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (settings.url.includes('unified_navigator') && xhr.status !== 0) {
            console.error('Error AJAX en navegador visual:', thrownError);
            showError('Error de conexión con el servidor. Por favor, recargue la página.');
        }
    });
    
})(jQuery);
