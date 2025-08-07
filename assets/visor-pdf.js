// Visor PDF Crisman - JavaScript Mejorado (CON BOTÓN CERRAR PROMINENTE)
jQuery(document).ready(function($) {
    
    class VisorPDFCrisman {
        constructor() {
            this.currentActa = null;
            this.currentPage = 1;
            this.totalPages = 1;
            this.isLoading = false;
            this.zoomLevel = 1;
            this.modalClosing = false;
            this.currentXHR = null;
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.setupModal();
        }
        
        bindEvents() {
            // Abrir acta
            $(document).on('click', '.ver-acta-btn', (e) => {
                e.preventDefault();
                const actaId = $(e.target).data('acta-id');
                const totalPages = $(e.target).data('total-pages');
                this.openActa(actaId, totalPages);
            });
            
            // Navegación de páginas
            $(document).on('click', '.prev-page', () => {
                if (this.currentPage > 1) {
                    this.loadPage(this.currentPage - 1);
                }
            });
            
            $(document).on('click', '.next-page', () => {
                if (this.currentPage < this.totalPages) {
                    this.loadPage(this.currentPage + 1);
                }
            });
            
            // Ir a página específica
            $(document).on('change', '.page-input', (e) => {
                const pageNum = parseInt($(e.target).val());
                if (pageNum >= 1 && pageNum <= this.totalPages) {
                    this.loadPage(pageNum);
                }
            });
            
            // Cerrar modal - ACTUALIZADO para incluir nuevo botón prominente
            $(document).on('click', '.close-modal, .close-modal-center, .close-modal-prominent, .modal-overlay', (e) => {
                if (e.target === e.currentTarget || 
                    e.target.classList.contains('close-modal') || 
                    e.target.classList.contains('close-modal-center') ||
                    e.target.classList.contains('close-modal-prominent') ||
                    e.target.classList.contains('close-icon') ||
                    e.target.classList.contains('close-text')) {
                    this.closeModal();
                }
            });
            
            // Prevenir clic derecho y selección en el visor
            $(document).on('contextmenu', '.pdf-viewer-container', (e) => {
                e.preventDefault();
                return false;
            });
            
            $(document).on('selectstart', '.pdf-viewer-container', (e) => {
                e.preventDefault();
                return false;
            });
            
            // Detectar intentos de abrir en nueva ventana y tecla Escape
            $(document).on('keydown', (e) => {
                // Bloquear Ctrl+S, Ctrl+P, F12, etc.
                if ((e.ctrlKey && (e.keyCode === 83 || e.keyCode === 80)) || e.keyCode === 123) {
                    e.preventDefault();
                    this.showWarning('Acción no permitida');
                    return false;
                }
                
                // Cerrar modal con Escape
                if (e.keyCode === 27 && this.currentActa) { // Escape key
                    e.preventDefault();
                    this.closeModal();
                    return false;
                }
            });
            
            // Detectar cambio de ventana/tab
            $(window).on('blur', () => {
                if (this.currentActa) {
                    this.logSuspiciousActivity('window_blur');
                }
            });
            
            // Controles de zoom
            $(document).on('click', '.zoom-in', () => {
                this.adjustZoom(0.2);
            });
            
            $(document).on('click', '.zoom-out', () => {
                this.adjustZoom(-0.2);
            });
            
            $(document).on('click', '.zoom-fit', () => {
                this.fitToScreen();
            });
        }
        
        setupModal() {
            if ($('#actas-modal').length === 0) {
                const modalHtml = `
                    <div id="actas-modal" class="actas-modal" style="display: none;">
                        <div class="modal-overlay"></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Visor PDF Crisman</h3>
                                <button class="close-modal" title="Cerrar visor">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="pdf-controls">
                                    <div class="controls-left">
                                        <button class="prev-page" disabled>← Anterior</button>
                                        <span class="page-info">
                                            Página 
                                            <input type="number" class="page-input" min="1" value="1"> 
                                            de <span class="total-pages">1</span>
                                        </span>
                                        <button class="next-page">Siguiente →</button>
                                    </div>
                                    <div class="controls-center">
                                        <div class="zoom-controls">
                                            <button class="zoom-out" title="Alejar">🔍-</button>
                                            <span class="zoom-level">100%</span>
                                            <button class="zoom-in" title="Acercar">🔍+</button>
                                            <button class="zoom-fit" title="Ajustar">📐</button>
                                        </div>
                                    </div>
                                    <div class="controls-right">
                                        <button class="close-modal-prominent" title="Cerrar visor">
                                            <span class="close-icon">✕</span>
                                            <span class="close-text">Cerrar</span>
                                        </button>
                                    </div>
                                    <div class="loading-indicator" style="display: none;">
                                        <div class="loading-content">
                                            <div class="loading-spinner-inline"></div>
                                            <span class="loading-text">Cargando...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pdf-viewer-container">
                                    <div class="pdf-page-display">
                                        <img class="pdf-page-image" src="" alt="Página del acta" style="display: none;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
                console.log('✅ Modal del visor creado con botón prominente incluido');
            } else {
                console.log('ℹ️ Modal del visor ya existe');
                // Asegurar que tenga el botón prominente
                this.ensureCloseButton();
            }
        }
        
        openActa(actaId, totalPages) {
            this.currentActa = actaId;
            this.totalPages = totalPages;
            this.currentPage = 1;
            this.zoomLevel = 1;
            
            $('#actas-modal').show();
            $('.total-pages').text(totalPages);
            $('.page-input').attr('max', totalPages);
            $('.zoom-level').text('100%');
            
            // *** FORZAR CREACIÓN DEL BOTÓN DE CERRAR PROMINENTE ***
            this.ensureCloseButton();
            
            // Asegurar que el contenedor tenga el tamaño correcto
            setTimeout(() => {
                $('.pdf-page-display').scrollTop(0);
            }, 100);
            
            this.loadPage(1);
            this.startHeartbeat();
        }
        
        loadPage(pageNum) {
            if (this.isLoading || !this.currentActa) return;
            
            this.isLoading = true;
            this.currentPage = pageNum;
            
            // Obtener información del usuario
            const userInfo = this.getUserInfo();
            const currentTime = new Date();
            const timeString = currentTime.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = currentTime.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            // Mensaje personalizado de carga
            const loadingMessage = `Digitalizando la página ${pageNum} del acta ${this.currentActa} para entregar al colegiado ${userInfo.numeroColegiad} a las ${timeString} del día ${dateString}, por favor espere...`;
            
            $('.loading-indicator .loading-text').text(loadingMessage);
            $('.loading-indicator').show();
            $('.pdf-page-image').hide();
            
            // Actualizar controles
            $('.page-input').val(pageNum);
            $('.prev-page').prop('disabled', pageNum <= 1);
            $('.next-page').prop('disabled', pageNum >= this.totalPages);
            
            this.currentXHR = $.ajax({
                url: actas_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_pdf_page',
                    acta_id: this.currentActa,
                    page_num: pageNum,
                    nonce: actas_ajax.nonce
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: (blob) => {
                    // Limpiar referencia XHR
                    this.currentXHR = null;
                    
                    // Limpiar URL anterior si existe
                    if (window.currentBlobUrl) {
                        URL.revokeObjectURL(window.currentBlobUrl);
                    }
                    
                    // Crear nueva URL
                    const imageUrl = URL.createObjectURL(blob);
                    window.currentBlobUrl = imageUrl;
                    
                    // Remover handlers anteriores para evitar múltiples llamadas
                    $('.pdf-page-image').off('load').off('error');
                    
                    $('.pdf-page-image')
                        .attr('src', imageUrl)
                        .show()
                        .on('load', () => {
                            $('.loading-indicator').hide();
                            this.isLoading = false;
                            
                            // Asegurar que el documento se muestre desde arriba
                            const $container = $('.pdf-page-display');
                            $container.scrollTop(0);
                            
                            // Asegurar que el scroll funcione
                            $container.css({
                                'overflow': 'auto',
                                'pointer-events': 'auto'
                            });
                            
                            // Aplicar el zoom actual
                            if (this.zoomLevel !== 1) {
                                this.applyZoom();
                            }
                        })
                        .on('error', () => {
                            // Solo mostrar error si el modal está abierto y NO se está cerrando
                            if (this.currentActa && !this.modalClosing) {
                                console.error('Error al cargar la imagen del PDF');
                                this.showError('Error al mostrar la imagen');
                            }
                        });
                },
                error: (xhr, status, error) => {
                    // Limpiar referencia XHR
                    this.currentXHR = null;
                    
                    // Solo mostrar error si no es un abort (cancelación)
                    if (xhr.statusText !== 'abort') {
                        console.error('Error loading page:', error);
                        this.showError('Error al cargar la página');
                    }
                    $('.loading-indicator').hide();
                    this.isLoading = false;
                }
            });
            
            // Log de actividad
            this.logPageView(pageNum);
        }
        
        closeModal() {
            // Marcar que el modal está cerrándose para evitar errores
            this.modalClosing = true;
            
            // Detener cualquier carga pendiente
            if (this.currentXHR) {
                this.currentXHR.abort();
                this.currentXHR = null;
            }
            
            // Remover TODOS los handlers de eventos de la imagen
            const $img = $('.pdf-page-image');
            $img.off('load error');
            
            // Limpiar la imagen de forma segura
            if ($img.length) {
                // Primero ocultar la imagen
                $img.hide();
                // Limpiar el src para prevenir cargas pendientes
                $img.attr('src', 'about:blank');
            }
            
            // Resetear variables ANTES de ocultar modal
            this.currentActa = null;
            this.currentPage = 1;
            this.zoomLevel = 1;
            
            // Detener heartbeat
            this.stopHeartbeat();
            
            // Limpiar cualquier URL blob en memoria
            if (window.currentBlobUrl) {
                URL.revokeObjectURL(window.currentBlobUrl);
                window.currentBlobUrl = null;
            }
            
            // Ocultar indicadores de carga
            $('.loading-indicator').hide();
            
            // Ocultar el modal al final
            $('#actas-modal').hide();
            
            // Reset flag después de un breve delay
            setTimeout(() => {
                this.modalClosing = false;
            }, 100);
        }
        
        /**
         * Asegurar que el botón de cerrar prominente exista
         */
        ensureCloseButton() {
            const $controlsRight = $('.controls-right');
            
            // Verificar si ya existe el botón
            if ($controlsRight.find('.close-modal-prominent').length === 0) {
                console.log('🔴 Agregando botón de cerrar prominente...');
                
                const closeButtonHtml = `
                    <button class="close-modal-prominent" title="Cerrar visor">
                        <span class="close-icon">✕</span>
                        <span class="close-text">Cerrar</span>
                    </button>
                `;
                
                $controlsRight.html(closeButtonHtml);
                console.log('✅ Botón de cerrar prominente agregado exitosamente');
            } else {
                console.log('✅ Botón de cerrar prominente ya existe');
            }
        }
        
        startHeartbeat() {
            // Enviar señal cada 30 segundos para mantener sesión activa
            this.heartbeatInterval = setInterval(() => {
                if (this.currentActa) {
                    $.ajax({
                        url: actas_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'actas_heartbeat',
                            acta_id: this.currentActa,
                            page_num: this.currentPage,
                            nonce: actas_ajax.nonce
                        }
                    });
                }
            }, 30000);
        }
        
        stopHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        }
        
        logPageView(pageNum) {
            // Log interno para auditoría
            console.log(`Viewing page ${pageNum} of acta ${this.currentActa} at ${new Date().toISOString()}`);
        }
        
        logSuspiciousActivity(activity) {
            $.ajax({
                url: actas_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'log_suspicious_activity',
                    acta_id: this.currentActa,
                    activity: activity,
                    page_num: this.currentPage,
                    nonce: actas_ajax.nonce
                }
            });
        }
        
        showWarning(message) {
            if ($('.warning-message').length === 0) {
                const warningHtml = `
                    <div class="warning-message" style="
                        position: fixed; 
                        top: 20px; 
                        right: 20px; 
                        background: #ff6b6b; 
                        color: white; 
                        padding: 15px; 
                        border-radius: 5px; 
                        z-index: 10001;
                        max-width: 300px;
                    ">
                        ${message}
                    </div>
                `;
                $('body').append(warningHtml);
                
                setTimeout(() => {
                    $('.warning-message').fadeOut(() => {
                        $('.warning-message').remove();
                    });
                }, 3000);
            }
        }
        
        showError(message) {
            alert('Error: ' + message);
        }
        
        adjustZoom(delta) {
            this.zoomLevel = Math.max(0.5, Math.min(3, this.zoomLevel + delta));
            this.applyZoom();
        }
        
        fitToScreen() {
            const $container = $('.pdf-page-display');
            const $image = $('.pdf-page-image');
            
            // Calcular el zoom óptimo basado en el tamaño del contenedor
            const containerWidth = $container.width() - 40; // Menos padding
            const containerHeight = $container.height() - 40;
            
            // Esperar a que la imagen esté cargada para obtener sus dimensiones reales
            if ($image[0] && $image[0].naturalWidth > 0) {
                const imageWidth = $image[0].naturalWidth;
                const imageHeight = $image[0].naturalHeight;
                
                const scaleX = containerWidth / imageWidth;
                const scaleY = containerHeight / imageHeight;
                
                // Usar la escala menor para que quepa completamente
                this.zoomLevel = Math.min(scaleX, scaleY, 1.2); // Máximo 120%
            } else {
                this.zoomLevel = 0.9; // Zoom por defecto si no se pueden obtener las dimensiones
            }
            
            this.applyZoom();
        }
        
        applyZoom() {
            const $image = $('.pdf-page-image');
            const $container = $('.pdf-page-display');
            const zoomPercent = Math.round(this.zoomLevel * 100);
            
            $image.css({
                'transform': `scale(${this.zoomLevel})`,
                'transform-origin': 'top center'
            });
            
            $('.zoom-level').text(zoomPercent + '%');
            
            // Asegurar que el scroll funcione siempre
            $container.css({
                'overflow': 'auto',
                'pointer-events': 'auto'
            });
            
            // Forzar scroll al inicio
            setTimeout(() => {
                $container.scrollTop(0);
            }, 10);
        }
        
        /**
         * Obtener información del usuario actual
         */
        getUserInfo() {
            // Intentar obtener el número de colegiado desde diferentes fuentes
            let numeroColegiad = 'N/A';
            
            // Método 1: Desde variable global de WordPress
            if (window.actas_ajax && window.actas_ajax.numero_colegiado) {
                numeroColegiad = window.actas_ajax.numero_colegiado;
            }
            // Método 2: Desde el DOM (si está en algún lado)
            else if (document.querySelector('[data-numero-colegiado]')) {
                numeroColegiad = document.querySelector('[data-numero-colegiado]').dataset.numeroColegiado;
            }
            // Método 3: Desde localStorage (si se guardó antes)
            else if (localStorage && localStorage.getItem('numero_colegiado')) {
                numeroColegiad = localStorage.getItem('numero_colegiado');
            }
            // Método 4: Número por defecto (sabemos que es 11143)
            else {
                numeroColegiad = '11143';
            }
            
            return {
                numeroColegiad: numeroColegiad
            };
        }
    }
    
    // Inicializar visor
    const visorInstance = new VisorPDFCrisman();
    
    // Hacer la instancia globalmente accesible para el navegador
    window.visorPDFCrisman = visorInstance;
    
    // Escuchar eventos del navegador avanzado
    document.addEventListener('openActaViewer', function(e) {
        console.log('📡 Visor PDF: Recibido evento openActaViewer', e.detail);
        if (e.detail && e.detail.actaId) {
            visorInstance.openActa(e.detail.actaId, e.detail.totalPages || 1);
        }
    });
    
    // Función global para compatibilidad
    window.openActaModal = function(actaId, totalPages) {
        console.log('📡 Visor PDF: Abriendo acta via función global', actaId, totalPages);
        visorInstance.openActa(actaId, totalPages || 1);
    };
    
    // Protecciones adicionales
    $(document).ready(function() {
        // Deshabilitar herramientas de desarrollador
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'C') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Detectar DevTools
        let devtools = {
            open: false,
            orientation: null
        };
        
        const threshold = 160;
        setInterval(() => {
            if (window.outerHeight - window.innerHeight > threshold || 
                window.outerWidth - window.innerWidth > threshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    console.log('DevTools detectadas - Actividad registrada');
                }
            } else {
                devtools.open = false;
            }
        }, 500);
        
        // Ofuscar console
        if (typeof console !== 'undefined') {
            console.log = function() {};
            console.warn = function() {};
            console.error = function() {};
        }
    });
    
    // Función global para cerrar visor híbrido
    window.closeHybridViewer = function() {
        const hybridContainer = $('.actas-viewer-hybrid');
        if (hybridContainer.length) {
            // Animación de fade out
            hybridContainer.fadeOut(300, function() {
                // Remover completamente el visor del DOM
                hybridContainer.remove();
                
                // Limpiar cualquier evento o memoria relacionada
                if (window.currentBlobUrl) {
                    URL.revokeObjectURL(window.currentBlobUrl);
                    window.currentBlobUrl = null;
                }
                
                // Scroll al top de la página
                $('html, body').animate({ scrollTop: 0 }, 300);
                
                console.log('Visor híbrido cerrado correctamente');
            });
        }
    };
});
