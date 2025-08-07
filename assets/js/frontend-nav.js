/**
 * Frontend Navigation Manager - FASE 4
 * Gestiona navegación avanzada, breadcrumbs y búsqueda para usuarios finales
 */

class FrontendNavigator {
    constructor(config = {}) {
        this.config = {
            show_navigation: true,
            show_breadcrumb: true,
            show_search: true,
            show_folders: true,
            filters: ['fecha', 'paginas', 'orden'],
            initial_folder: 0,
            per_page: 10,
            ...config
        };
        
        this.currentFolder = this.config.initial_folder;
        this.currentPage = 1;
        this.isLoading = false;
        this.searchTimeout = null;
        this.activeFilters = {};
        
        this.init();
    }
    
    /**
     * Inicializar el navegador
     */
    init() {
        this.bindEvents();
        this.loadFolderContents(this.currentFolder);
        
        if (this.config.show_search) {
            this.initSearch();
        }
        
        if (this.config.filters.length > 0) {
            this.initFilters();
        }
        
        console.log('🚀 Frontend Navigator initialized');
    }
    
    /**
     * Vincular eventos del DOM
     */
    bindEvents() {
        const container = document.querySelector('.actas-navigator');
        if (!container) return;
        
        // Navegación de carpetas
        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('folder-link')) {
                e.preventDefault();
                const folderId = parseInt(e.target.dataset.folderId);
                this.navigateToFolder(folderId);
            }
            
            // Breadcrumb navigation
            if (e.target.classList.contains('breadcrumb-item')) {
                e.preventDefault();
                const folderId = parseInt(e.target.dataset.folderId);
                this.navigateToFolder(folderId);
            }
            
            // Paginación
            if (e.target.classList.contains('page-link')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                this.goToPage(page);
            }
            
            // Ver acta - integración con el visor PDF
            if (e.target.classList.contains('ver-acta-btn')) {
                e.preventDefault();
                const actaId = parseInt(e.target.dataset.actaId);
                const totalPages = parseInt(e.target.dataset.totalPages);
                this.viewActa(actaId, totalPages);
            }
        });
        
        // Búsqueda en tiempo real
        const searchInput = container.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearchInput(e.target.value);
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.performSearch(e.target.value);
                }
            });
        }
        
        // Filtros
        container.addEventListener('change', (e) => {
            if (e.target.classList.contains('filter-input')) {
                this.handleFilterChange();
            }
        });
        
        // Limpiar filtros
        const clearFiltersBtn = container.querySelector('.clear-filters-btn');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
    }
    
    /**
     * Navegar a una carpeta específica
     */
    async navigateToFolder(folderId) {
        if (this.isLoading) return;
        
        this.currentFolder = folderId;
        this.currentPage = 1;
        await this.loadFolderContents(folderId);
        this.updateURL(folderId);
    }
    
    /**
     * Cargar contenido de carpeta
     */
    async loadFolderContents(folderId) {
        this.setLoading(true);
        
        try {
            // En entorno de prueba sin WordPress, simular datos
            if (!window.frontendNavAjax || !window.frontendNavAjax.ajaxurl.includes('wp-admin')) {
                console.log('🧪 Modo de prueba: Simulando carga de carpeta', folderId);
                
                const mockData = this.getMockFolderData(folderId);
                this.renderFolderContents(mockData);
                
                if (this.config.show_breadcrumb) {
                    this.renderBreadcrumb(mockData.breadcrumb);
                }
                
                this.setLoading(false);
                return;
            }
            
            const response = await this.makeAjaxRequest('get_folder_contents', {
                folder_id: folderId,
                page: this.currentPage,
                per_page: this.config.per_page
            });
            
            if (response.success) {
                this.renderFolderContents(response.data);
                
                if (this.config.show_breadcrumb) {
                    this.renderBreadcrumb(response.data.breadcrumb);
                }
            } else {
                this.showError('Error al cargar el contenido');
            }
        } catch (error) {
            console.error('Error loading folder contents:', error);
            
            // En entorno de prueba, mostrar datos mock en lugar de error
            if (!window.frontendNavAjax || !window.frontendNavAjax.ajaxurl.includes('wp-admin')) {
                const mockData = this.getMockFolderData(folderId);
                this.renderFolderContents(mockData);
            } else {
                this.showError('Error de conexión');
            }
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Renderizar contenido de carpeta
     */
    renderFolderContents(data) {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        let html = '';
        
        // Renderizar subcarpetas si existen
        if (data.subfolders && data.subfolders.length > 0 && this.config.show_folders) {
            html += '<div class="subfolders-section">';
            html += '<h3 class="section-title">📁 Carpetas</h3>';
            html += '<div class="folders-grid">';
            
            data.subfolders.forEach(folder => {
                html += `
                    <div class="folder-card">
                        <div class="folder-icon">📁</div>
                        <div class="folder-info">
                            <h4 class="folder-name">
                                <a href="#" class="folder-link" data-folder-id="${folder.id}">
                                    ${this.escapeHtml(folder.name)}
                                </a>
                            </h4>
                            <p class="folder-description">${this.escapeHtml(folder.description || '')}</p>
                            <span class="actas-count">${folder.actas_count} acta(s)</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div></div>';
        }
        
        // Renderizar actas
        if (data.actas && data.actas.length > 0) {
            html += '<div class="actas-section">';
            html += '<h3 class="section-title">📄 Actas</h3>';
            html += '<div class="actas-grid">';
            
            data.actas.forEach(acta => {
                html += `
                    <div class="acta-card">
                        <div class="acta-header">
                            <h4 class="acta-title">${this.escapeHtml(acta.title)}</h4>
                            <span class="acta-date">${acta.upload_date}</span>
                        </div>
                        <div class="acta-content">
                            <p class="acta-description">${this.escapeHtml(acta.description || '')}</p>
                            <div class="acta-meta">
                                <span class="pages-count">📄 ${acta.total_pages} página(s)</span>
                                <span class="file-size">💾 ${acta.file_size_formatted}</span>
                                <span class="folder-name">📁 ${this.escapeHtml(acta.folder_name)}</span>
                            </div>
                        </div>
                        <div class="acta-actions">
                            <button class="btn btn-primary ver-acta-btn" data-acta-id="${acta.id}" data-total-pages="${acta.total_pages}">
                                👁️ Ver Acta
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div></div>';
        } else if (!data.subfolders || data.subfolders.length === 0) {
            html += '<div class="empty-state">';
            html += '<div class="empty-icon">📭</div>';
            html += '<h3>No hay contenido disponible</h3>';
            html += '<p>Esta sección no contiene actas ni subcarpetas.</p>';
            html += '</div>';
        }
        
        container.innerHTML = html;
        
        // Renderizar paginación si es necesaria
        if (data.pagination && data.pagination.total_pages > 1) {
            this.renderPagination(data.pagination);
        }
    }
    
    /**
     * Renderizar breadcrumb
     */
    renderBreadcrumb(breadcrumb) {
        const container = document.querySelector('.breadcrumb-container');
        if (!container || !breadcrumb) return;
        
        let html = '<nav class="breadcrumb-nav"><ol class="breadcrumb">';
        
        breadcrumb.forEach((item, index) => {
            const isLast = index === breadcrumb.length - 1;
            const classes = `breadcrumb-item ${isLast ? 'active' : ''}`;
            
            if (isLast) {
                html += `<li class="${classes}">${this.escapeHtml(item.name)}</li>`;
            } else {
                html += `
                    <li class="${classes}">
                        <a href="#" class="breadcrumb-item" data-folder-id="${item.folder_id}">
                            ${this.escapeHtml(item.name)}
                        </a>
                    </li>
                `;
            }
        });
        
        html += '</ol></nav>';
        container.innerHTML = html;
    }
    
    /**
     * Renderizar paginación
     */
    renderPagination(pagination) {
        const container = document.querySelector('.pagination-container');
        if (!container) return;
        
        const { current_page, total_pages, total } = pagination;
        
        let html = '<nav class="pagination-nav">';
        html += `<div class="pagination-info">Mostrando página ${current_page} de ${total_pages} (${total} total)</div>`;
        html += '<ul class="pagination">';
        
        // Botón anterior
        if (current_page > 1) {
            html += `<li><a href="#" class="page-link" data-page="${current_page - 1}">« Anterior</a></li>`;
        }
        
        // Números de página
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);
        
        if (startPage > 1) {
            html += `<li><a href="#" class="page-link" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li><span class="page-dots">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === current_page ? 'active' : '';
            html += `<li><a href="#" class="page-link ${activeClass}" data-page="${i}">${i}</a></li>`;
        }
        
        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                html += `<li><span class="page-dots">...</span></li>`;
            }
            html += `<li><a href="#" class="page-link" data-page="${total_pages}">${total_pages}</a></li>`;
        }
        
        // Botón siguiente
        if (current_page < total_pages) {
            html += `<li><a href="#" class="page-link" data-page="${current_page + 1}">Siguiente »</a></li>`;
        }
        
        html += '</ul></nav>';
        container.innerHTML = html;
    }
    
    /**
     * Ir a página específica
     */
    async goToPage(page) {
        if (this.isLoading || page === this.currentPage) return;
        
        this.currentPage = page;
        
        if (this.isSearchActive()) {
            await this.performSearch(this.getCurrentSearchTerm());
        } else {
            await this.loadFolderContents(this.currentFolder);
        }
    }
    
    /**
     * Inicializar búsqueda
     */
    initSearch() {
        // La búsqueda se maneja en bindEvents()
        console.log('🔍 Search initialized');
    }
    
    /**
     * Manejar input de búsqueda (con debounce)
     */
    handleSearchInput(searchTerm) {
        clearTimeout(this.searchTimeout);
        
        if (searchTerm.length < 2) {
            // Si es muy corto, volver a la navegación normal
            this.clearSearch();
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.performSearch(searchTerm);
        }, 500); // Debounce de 500ms
    }
    
    /**
     * Realizar búsqueda
     */
    async performSearch(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) return;
        
        this.setLoading(true);
        
        try {
            const response = await this.makeAjaxRequest('search_actas', {
                search_term: searchTerm,
                page: this.currentPage,
                per_page: this.config.per_page
            });
            
            if (response.success) {
                this.renderSearchResults(response.data);
                this.markAsSearchMode(searchTerm);
            } else {
                this.showError('Error en la búsqueda');
            }
        } catch (error) {
            console.error('Error performing search:', error);
            this.showError('Error de conexión en búsqueda');
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Renderizar resultados de búsqueda
     */
    renderSearchResults(data) {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        let html = `
            <div class="search-results-header">
                <h3>🔍 Resultados de búsqueda: "${this.escapeHtml(data.search_term)}"</h3>
                <button class="btn btn-secondary clear-search-btn">❌ Limpiar búsqueda</button>
            </div>
        `;
        
        if (data.actas && data.actas.length > 0) {
            html += '<div class="actas-section"><div class="actas-grid">';
            
            data.actas.forEach(acta => {
                html += `
                    <div class="acta-card search-result">
                        <div class="acta-header">
                            <h4 class="acta-title">${this.highlightSearchTerm(acta.title, data.search_term)}</h4>
                            <span class="acta-date">${acta.upload_date}</span>
                        </div>
                        <div class="acta-content">
                            <p class="acta-description">${this.highlightSearchTerm(acta.description || '', data.search_term)}</p>
                            <div class="acta-meta">
                                <span class="pages-count">📄 ${acta.total_pages} página(s)</span>
                                <span class="file-size">💾 ${acta.file_size_formatted}</span>
                                <span class="folder-name">📁 ${this.escapeHtml(acta.folder_name)}</span>
                            </div>
                        </div>
                        <div class="acta-actions">
                            <button class="btn btn-primary ver-acta-btn" data-acta-id="${acta.id}" data-total-pages="${acta.total_pages}">
                                👁️ Ver Acta
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div></div>';
        } else {
            html += `
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>No se encontraron resultados</h3>
                    <p>No hay actas que coincidan con "${this.escapeHtml(data.search_term)}"</p>
                </div>
            `;
        }
        
        container.innerHTML = html;
        
        // Renderizar paginación para búsqueda
        if (data.pagination && data.pagination.total_pages > 1) {
            this.renderPagination(data.pagination);
        }
        
        // Agregar evento para limpiar búsqueda
        const clearBtn = container.querySelector('.clear-search-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.clearSearch();
            });
        }
    }
    
    /**
     * Limpiar búsqueda y volver a navegación normal
     */
    clearSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        
        this.currentPage = 1;
        this.clearSearchMode();
        this.loadFolderContents(this.currentFolder);
    }
    
    /**
     * Inicializar filtros
     */
    initFilters() {
        console.log('🔧 Filters initialized:', this.config.filters);
    }
    
    /**
     * Manejar cambio de filtros
     */
    handleFilterChange() {
        // Recopilar valores de filtros
        this.activeFilters = {};
        
        const filterInputs = document.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            if (input.value) {
                this.activeFilters[input.name] = input.value;
            }
        });
        
        this.currentPage = 1;
        this.applyFilters();
    }
    
    /**
     * Aplicar filtros
     */
    async applyFilters() {
        this.setLoading(true);
        
        try {
            const response = await this.makeAjaxRequest('filter_actas', {
                filters: this.activeFilters,
                folder_id: this.currentFolder,
                page: this.currentPage,
                per_page: this.config.per_page
            });
            
            if (response.success) {
                this.renderFolderContents(response.data);
                this.markAsFilterMode();
            } else {
                this.showError('Error al aplicar filtros');
            }
        } catch (error) {
            console.error('Error applying filters:', error);
            this.showError('Error de conexión en filtros');
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Limpiar filtros
     */
    clearFilters() {
        this.activeFilters = {};
        
        const filterInputs = document.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            input.value = '';
        });
        
        this.currentPage = 1;
        this.clearFilterMode();
        this.loadFolderContents(this.currentFolder);
    }
    
    /**
     * Ver acta (usar el visor existente)
     */
    viewActa(actaId, totalPages) {
        console.log('🎯 FrontendNavigator: Opening acta', actaId, 'with', totalPages, 'pages');
        
        // Validar parámetros
        if (!actaId || actaId <= 0) {
            console.error('⚠️ Parámetros inválidos:', { actaId, totalPages });
            return;
        }
        
        let success = false;
        
        // Método 1: Instancia global del visor
        if (window.visorPDFCrisman && typeof window.visorPDFCrisman.openActa === 'function') {
            console.log('🟢 Método 1: Usando instancia global del visor');
            window.visorPDFCrisman.openActa(actaId, totalPages);
            success = true;
        }
        
        // Método 2: Función global
        else if (window.openActaModal && typeof window.openActaModal === 'function') {
            console.log('🟢 Método 2: Usando función global openActaModal');
            window.openActaModal(actaId, totalPages);
            success = true;
        }
        
        // Método 3: Trigger mediante jQuery (simular clic)
        else if (window.jQuery) {
            console.log('🟢 Método 3: Simulando clic con jQuery');
            const $fakeBtn = window.jQuery('<button>');
            $fakeBtn.data('acta-id', actaId);
            $fakeBtn.data('total-pages', totalPages);
            $fakeBtn.addClass('ver-acta-btn');
            $fakeBtn.trigger('click');
            success = true;
        }
        
        // Método 4: CustomEvent
        if (!success) {
            console.log('🟢 Método 4: Usando CustomEvent');
            const event = new CustomEvent('openActaViewer', {
                detail: { 
                    actaId: actaId, 
                    totalPages: totalPages,
                    source: 'navigator'
                }
            });
            document.dispatchEvent(event);
        }
        
        // Mostrar estado final
        if (success) {
            console.log('✅ Acta abierta exitosamente');
        } else {
            console.warn('⚠️ FrontendNavigator: Intentando abrir acta pero el visor podría no estar listo');
            
            // Último intento después de un breve delay
            setTimeout(() => {
                if (window.visorPDFCrisman && typeof window.visorPDFCrisman.openActa === 'function') {
                    console.log('🔄 Último intento exitoso');
                    window.visorPDFCrisman.openActa(actaId, totalPages);
                } else if (window.openActaModal) {
                    console.log('🔄 Último intento con función global');
                    window.openActaModal(actaId, totalPages);
                } else {
                    console.error('❌ Error: No se pudo abrir el visor PDF. Verifique que esté cargado.');
                    alert('Error: No se puede abrir el visor PDF. Por favor, recargue la página.');
                }
            }, 500);
        }
    }
    
    /**
     * Configurar integración con el visor PDF
     */
    setupVisorIntegration() {
        // Verificar que jQuery y el visor estén disponibles
        const checkVisorAvailability = () => {
            if (window.jQuery) {
                console.log('✅ jQuery disponible para integración');
                
                // Verificar si el visor PDF está inicializado
                if (window.jQuery('.actas-modal').length > 0) {
                    console.log('✅ Modal del visor PDF detectado');
                } else {
                    console.log('⏳ Modal del visor PDF no detectado aún');
                }
            } else {
                console.log('⚠️ jQuery no disponible');
            }
        };
        
        // Verificar inmediatamente
        checkVisorAvailability();
        
        // Verificar cada segundo durante los primeros 10 segundos
        let checkAttempts = 0;
        const checkInterval = setInterval(() => {
            checkAttempts++;
            checkVisorAvailability();
            
            if (checkAttempts >= 10) {
                clearInterval(checkInterval);
                console.log('🔍 Finalizada verificación de integración del visor');
            }
        }, 1000);
    }
    
    /**
     * Realizar petición AJAX
     */
    async makeAjaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', frontendNavAjax.nonce);
        
        Object.keys(data).forEach(key => {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        });
        
        const response = await fetch(frontendNavAjax.ajaxurl, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    /**
     * Establecer estado de carga
     */
    setLoading(loading) {
        this.isLoading = loading;
        
        const container = document.querySelector('.actas-navigator');
        if (container) {
            if (loading) {
                container.classList.add('loading');
                this.showLoadingIndicator();
            } else {
                container.classList.remove('loading');
                this.hideLoadingIndicator();
            }
        }
    }
    
    /**
     * Mostrar indicador de carga
     */
    showLoadingIndicator() {
        const existing = document.querySelector('.loading-indicator');
        if (existing) return;
        
        const indicator = document.createElement('div');
        indicator.className = 'loading-indicator';
        indicator.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>${frontendNavAjax.loading_text}</p>
            </div>
        `;
        
        const container = document.querySelector('.actas-list-container');
        if (container) {
            container.appendChild(indicator);
        }
    }
    
    /**
     * Ocultar indicador de carga
     */
    hideLoadingIndicator() {
        const indicator = document.querySelector('.loading-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    /**
     * Mostrar error
     */
    showError(message) {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <div class="error-content">
                <span class="error-icon">❌</span>
                <span class="error-text">${this.escapeHtml(message)}</span>
            </div>
        `;
        
        container.innerHTML = '';
        container.appendChild(errorDiv);
        
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
    
    /**
     * Actualizar URL sin recargar página
     */
    updateURL(folderId) {
        if (history.pushState) {
            const url = new URL(window.location);
            if (folderId > 0) {
                url.searchParams.set('carpeta', folderId);
            } else {
                url.searchParams.delete('carpeta');
            }
            history.pushState({}, '', url);
        }
    }
    
    /**
     * Marcar como modo búsqueda
     */
    markAsSearchMode(searchTerm) {
        const container = document.querySelector('.actas-navigator');
        if (container) {
            container.classList.add('search-mode');
            container.dataset.searchTerm = searchTerm;
        }
    }
    
    /**
     * Limpiar modo búsqueda
     */
    clearSearchMode() {
        const container = document.querySelector('.actas-navigator');
        if (container) {
            container.classList.remove('search-mode');
            delete container.dataset.searchTerm;
        }
    }
    
    /**
     * Marcar como modo filtros
     */
    markAsFilterMode() {
        const container = document.querySelector('.actas-navigator');
        if (container) {
            container.classList.add('filter-mode');
        }
    }
    
    /**
     * Limpiar modo filtros
     */
    clearFilterMode() {
        const container = document.querySelector('.actas-navigator');
        if (container) {
            container.classList.remove('filter-mode');
        }
    }
    
    /**
     * Verificar si está en modo búsqueda
     */
    isSearchActive() {
        const container = document.querySelector('.actas-navigator');
        return container && container.classList.contains('search-mode');
    }
    
    /**
     * Obtener término de búsqueda actual
     */
    getCurrentSearchTerm() {
        const container = document.querySelector('.actas-navigator');
        return container ? container.dataset.searchTerm || '' : '';
    }
    
    /**
     * Resaltar término de búsqueda en texto
     */
    highlightSearchTerm(text, searchTerm) {
        if (!text || !searchTerm) return this.escapeHtml(text);
        
        const escapedText = this.escapeHtml(text);
        const escapedTerm = this.escapeHtml(searchTerm);
        const regex = new RegExp(`(${escapedTerm})`, 'gi');
        
        return escapedText.replace(regex, '<mark>$1</mark>');
    }
    
    /**
     * Obtener datos mock para testing (sin WordPress)
     */
    getMockFolderData(folderId) {
        const mockActas = [
            {
                id: 1,
                title: 'Acta de Junta Directiva - Enero 2025',
                description: 'Reunión ordinaria de la Junta Directiva para revisar presupuesto anual.',
                upload_date: '15/01/2025',
                total_pages: 5,
                file_size_formatted: '2.3 MB',
                folder_name: 'Juntas Directivas'
            },
            {
                id: 2,
                title: 'Acta de Asamblea General - Diciembre 2024',
                description: 'Asamblea general ordinaria con aprobación de estados financieros.',
                upload_date: '12/01/2025',
                total_pages: 12,
                file_size_formatted: '5.7 MB',
                folder_name: 'Asambleas'
            },
            {
                id: 3,
                title: 'Acta de Comité Técnico - Octubre 2024',
                description: 'Evaluación de nuevos protocolos y procedimientos técnicos.',
                upload_date: '10/01/2025',
                total_pages: 8,
                file_size_formatted: '3.1 MB',
                folder_name: 'Comités'
            }
        ];
        
        const mockSubfolders = folderId === 0 ? [
            {
                id: 1,
                name: 'Juntas Directivas',
                description: 'Actas de las reuniones de Junta Directiva',
                actas_count: 15
            },
            {
                id: 2,
                name: 'Asambleas',
                description: 'Actas de Asambleas Generales',
                actas_count: 8
            },
            {
                id: 3,
                name: 'Comités',
                description: 'Actas de diversos comités',
                actas_count: 23
            }
        ] : [];
        
        const mockBreadcrumb = folderId === 0 ? [
            { name: 'Inicio', folder_id: 0, is_current: true }
        ] : [
            { name: 'Inicio', folder_id: 0, is_current: false },
            { name: 'Carpeta ' + folderId, folder_id: folderId, is_current: true }
        ];
        
        return {
            actas: mockActas,
            subfolders: mockSubfolders,
            breadcrumb: mockBreadcrumb,
            pagination: {
                current_page: 1,
                per_page: 10,
                total: mockActas.length,
                total_pages: 1
            }
        };
    }
    
    /**
     * Escapar HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const navigatorContainer = document.querySelector('.actas-navigator');
    if (navigatorContainer) {
        // Esperar a que jQuery y el visor PDF estén listos
        const initNavigator = () => {
            // Obtener configuración del atributo data
            const config = navigatorContainer.dataset.config ? 
                JSON.parse(navigatorContainer.dataset.config) : {};
            
            // Inicializar navegador
            window.frontendNavigator = new FrontendNavigator(config);
            
            console.log('🎯 Frontend Navigator ready');
            
            // Registro de compatibilidad con visor PDF
            window.frontendNavigator.setupVisorIntegration();
        };
        
        // Si jQuery está disponible, inicializar inmediatamente
        if (window.jQuery) {
            // Esperar a que jQuery esté completamente listo
            window.jQuery(document).ready(() => {
                // Esperar un poco más para que el visor PDF se inicialice
                setTimeout(initNavigator, 100);
            });
        } else {
            // Si no hay jQuery, intentar cada 100ms hasta 5 segundos
            let attempts = 0;
            const checkJQuery = setInterval(() => {
                attempts++;
                if (window.jQuery || attempts >= 50) {
                    clearInterval(checkJQuery);
                    if (window.jQuery) {
                        window.jQuery(document).ready(() => {
                            setTimeout(initNavigator, 100);
                        });
                    } else {
                        // Inicializar sin jQuery como último recurso
                        initNavigator();
                    }
                }
            }, 100);
        }
    }
});
