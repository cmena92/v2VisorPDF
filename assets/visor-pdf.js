// Visor PDF Crisman - JavaScript Mejorado (VERSION 2.0.2 - CON BOTÓN CERRAR PROMINENTE)
jQuery(document).ready(function($) {
    
    class VisorPDFCrisman {
        constructor() {
            this.currentActa = null;
            this.currentPage = 1;
            this.totalPages = 1;
            this.isLoading = false;
            this.zoomLevel = 1;
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
            
            // Cerrar modal - SIMPLIFICADO para botón con onclick
            $(document).on('click', '.close-modal, .close-modal-backup, .modal-overlay', (e) => {
                if (e.target === e.currentTarget || 
                    e.target.classList.contains('close-modal') || 
                    e.target.classList.contains('close-modal-backup') ||
                    e.target.classList.contains('close-icon') ||
                    e.target.classList.contains('close-text')) {
                    console.log('🔴 Cerrando modal desde botón secundario:', e.target.className);
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
                console.log('🔧 Creando modal con botón cerrar prominente mejorado...');
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
                                        <button class="close-modal-backup" title="Cerrar visor">
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
                
                // CREAR BOTÓN CERRAR FIJO FUERA DEL MODAL (SIEMPRE VISIBLE)
                const closeButtonHtml = `
                    <button id="actas-close-button-fixed" 
                            title="Cerrar visor y volver a la lista" 
                            onclick="window.visorPDFCrisman.closeModal()" 
                            onmouseover="this.style.background='#c82333'; this.style.transform='scale(1.05)';" 
                            onmouseout="this.style.background='#dc3545'; this.style.transform='scale(1)';" 
                            style="
                                position: fixed !important;
                                top: 20px !important;
                                right: 20px !important;
                                z-index: 999999 !important;
                                background: #dc3545 !important;
                                color: white !important;
                                border: 2px solid white !important;
                                border-radius: 25px !important;
                                padding: 12px 24px !important;
                                cursor: pointer !important;
                                font-size: 18px !important;
                                font-weight: bold !important;
                                display: none !important;
                                align-items: center !important;
                                gap: 8px !important;
                                min-width: 140px !important;
                                min-height: 50px !important;
                                justify-content: center !important;
                                font-family: Arial, sans-serif !important;
                                text-align: center !important;
                                line-height: 1.2 !important;
                                box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important;
                                transition: all 0.3s ease !important;
                            ">
                        ✕ Cerrar
                    </button>
                `;
                $('body').append(closeButtonHtml);
                
                console.log('🎨 Botón con estilos inline directo creado');
                
                // VERIFICAR BOTÓN CERRAR FIJO
                const closeBtn = $('#actas-close-button-fixed');
                
                if (closeBtn.length > 0) {
                    console.log('✅ Botón cerrar fijo encontrado:', closeBtn.length);
                    console.log('📦 Dimensiones del botón:', closeBtn.outerWidth() + 'x' + closeBtn.outerHeight());
                    console.log('🎨 Estilos aplicados correctamente');
                    
                    // Borde temporal para debugging visual (solo si está visible)
                    setTimeout(() => {
                        if (closeBtn.is(':visible')) {
                            closeBtn.css('border', '3px solid yellow');
                            setTimeout(() => {
                                closeBtn.css('border', '2px solid white');
                            }, 2000);
                        }
                    }, 100);
                } else {
                    console.error('❌ Botón .close-modal-prominent-fixed NO encontrado');
                }
                
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
            
            // Mostrar modal
            $('#actas-modal').show();
            
            // MOSTRAR BOTÓN CERRAR FIJO
            const closeBtn = $('#actas-close-button-fixed');
            if (closeBtn.length > 0) {
                closeBtn.css('display', 'flex');
                console.log('🔴 Botón cerrar fijo mostrado');
                
                // Verificar dimensiones ahora que está visible
                setTimeout(() => {
                    console.log('📦 Dimensiones botón (ahora visible):', closeBtn.outerWidth() + 'x' + closeBtn.outerHeight());
                    console.log('🔍 Botón visible?', closeBtn.is(':visible'));
                    
                    // Borde de debugging amarillo por 2 segundos
                    closeBtn.css('border', '3px solid yellow');
                    setTimeout(() => {
                        closeBtn.css('border', '2px solid white');
                    }, 2000);
                }, 200);
            } else {
                console.error('❌ Botón fijo no encontrado al abrir modal');
                // Crear botón de emergencia
                this.ensureCloseButton();
            }
            
            $('.total-pages').text(totalPages);
            $('.page-input').attr('max', totalPages);
            $('.zoom-level').text('100%');
            
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
            
            $.ajax({
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
                success: (blob, textStatus, xhr) => {
                    console.log('📦 Respuesta AJAX recibida:', {
                        tipo: typeof blob,
                        esBlob: blob instanceof Blob,
                        tamaño: blob ? blob.size : 'N/A',
                        contentType: xhr.getResponseHeader('Content-Type')
                    });
                    
                    // VALIDACIÓN ROBUSTA DEL BLOB
                    if (!(blob instanceof Blob)) {
                        console.error('❌ Error: Respuesta no es un objeto Blob válido:', blob);
                        this.showError('Error: Respuesta del servidor inválida');
                        $('.loading-indicator').hide();
                        this.isLoading = false;
                        return;
                    }
                    
                    if (blob.size === 0) {
                        console.error('❌ Error: Blob vacío recibido');
                        this.showError('Error: Archivo vacío recibido del servidor');
                        $('.loading-indicator').hide();
                        this.isLoading = false;
                        return;
                    }
                    
                    try {
                        const imageUrl = URL.createObjectURL(blob);
                        console.log('✅ ObjectURL creado exitosamente:', imageUrl);
                        
                        $('.pdf-page-image')
                            .attr('src', imageUrl)
                            .show()
                            .on('load', () => {
                                console.log('✅ Imagen cargada exitosamente');
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
                                
                                // Limpiar URL anterior para evitar acumulación de memoria
                                setTimeout(() => {
                                    URL.revokeObjectURL(imageUrl);
                                    console.log('🗑️ ObjectURL liberado de memoria');
                                }, 1000);
                            })
                            .on('error', () => {
                                console.error('❌ Error al cargar la imagen en el elemento IMG');
                                this.showError('Error al mostrar la imagen');
                                $('.loading-indicator').hide();
                                this.isLoading = false;
                                URL.revokeObjectURL(imageUrl);
                            });
                    } catch (error) {
                        console.error('❌ Error al crear ObjectURL:', error);
                        this.showError('Error al procesar la imagen: ' + error.message);
                        $('.loading-indicator').hide();
                        this.isLoading = false;
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Error AJAX completo:', {
                        status: status,
                        error: error,
                        responseStatus: xhr.status,
                        responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'N/A',
                        contentType: xhr.getResponseHeader('Content-Type')
                    });
                    
                    let errorMessage = 'Error al cargar la página';
                    if (xhr.status === 404) {
                        errorMessage = 'Página no encontrada (404)';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Acceso denegado (403)';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Error interno del servidor (500)';
                    }
                    
                    this.showError(errorMessage + ' - Código: ' + xhr.status);
                    $('.loading-indicator').hide();
                    this.isLoading = false;
                }
            });
            
            // Log de actividad
            this.logPageView(pageNum);
        }
        
        closeModal() {
            console.log('🔴 Cerrando modal del visor...');
            
            // Ocultar modal
            $('#actas-modal').hide();
            
            // OCULTAR BOTÓN CERRAR FIJO
            const closeBtn = $('#actas-close-button-fixed');
            if (closeBtn.length > 0) {
                closeBtn.css('display', 'none');
                console.log('✅ Botón cerrar fijo ocultado');
            }
            
            // Limpiar imagen y estado
            $('.pdf-page-image').attr('src', '');
            this.currentActa = null;
            this.currentPage = 1;
            this.stopHeartbeat();
            
            console.log('✅ Modal cerrado completamente');
        }
        
        /**
         * Asegurar que el botón de cerrar prominente exista
         */
        ensureCloseButton() {
            // Verificar botón fijo prominente por ID
            if ($('#actas-close-button-fixed').length === 0) {
                console.log('🔧 Creando botón cerrar fijo de emergencia...');
                
                const emergencyButtonHtml = `
                    <button id="actas-close-button-fixed" class="close-modal-prominent-fixed" 
                            title="Cerrar visor y volver a la lista" style="
                        position: fixed !important;
                        top: 20px !important;
                        right: 20px !important;
                        z-index: 999999 !important;
                        background: #dc3545 !important;
                        color: white !important;
                        border: 2px solid white !important;
                        border-radius: 25px !important;
                        padding: 12px 24px !important;
                        cursor: pointer !important;
                        font-size: 18px !important;
                        font-weight: bold !important;
                        display: none !important;
                        align-items: center !important;
                        gap: 8px !important;
                        min-width: 140px !important;
                        min-height: 50px !important;
                        justify-content: center !important;
                    ">
                        <span class="close-icon-prominent">✕</span>
                        <span class="close-text-prominent">Cerrar</span>
                    </button>
                `;
                
                $('body').append(emergencyButtonHtml);
                console.log('✅ Botón de emergencia creado');
            } else {
                console.log('✅ Botón cerrar fijo ya existe (ID: actas-close-button-fixed)');
            }
            
            // Verificar botón de respaldo en controles
            const $controlsRight = $('.controls-right');
            if ($controlsRight.length > 0 && $controlsRight.find('.close-modal-backup').length === 0) {
                console.log('🔧 Agregando botón de respaldo...');
                
                const backupButtonHtml = `
                    <button class="close-modal-backup" title="Cerrar visor">
                        <span class="close-icon">✕</span>
                        <span class="close-text">Cerrar</span>
                    </button>
                `;
                
                $controlsRight.append(backupButtonHtml);
                console.log('✅ Botón de respaldo agregado');
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
        
        // FUNCIÓN DE EMERGENCIA PARA FORZAR BOTÓN CERRAR
        window.forceCloseButton = function() {
            setTimeout(() => {
                const closeBtn = $('.close-modal-prominent');
                if (closeBtn.length > 0) {
                    closeBtn.css({
                        'position': 'absolute !important',
                        'top': '15px !important',
                        'right': '15px !important',
                        'z-index': '10001 !important',
                        'background': '#dc3545 !important',
                        'color': 'white !important',
                        'border': 'none !important',
                        'border-radius': '8px !important',
                        'padding': '12px 20px !important',
                        'cursor': 'pointer !important',
                        'font-size': '16px !important',
                        'font-weight': '600 !important',
                        'display': 'flex !important',
                        'align-items': 'center !important',
                        'gap': '8px !important',
                        'box-shadow': '0 4px 12px rgba(220, 53, 69, 0.4) !important',
                        'min-width': '100px !important',
                        'justify-content': 'center !important'
                    });
                    closeBtn.show();
                    console.log('🚑 EMERGENCIA: Botón cerrar forzado!', closeBtn);
                    return true;
                }
                console.error('❌ EMERGENCIA: Botón no encontrado');
                return false;
            }, 50);
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
});
