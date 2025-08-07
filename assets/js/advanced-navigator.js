/**
 * Advanced Navigator - Navegador con Panel Lateral
 * Extiende el FrontendNavigator básico con funcionalidades avanzadas
 */

class AdvancedNavigator {
    constructor(config = {}) {
        this.config = {
            sidebar_navigation: true,
            show_breadcrumb: true,
            show_counters: true,
            initial_view: 'ultimas',
            initial_limit: 5,
            responsive_breakpoint: 768,
            show_search: true,
            filters: ['fecha', 'paginas', 'orden'],
            ...config
        };
        
        this.currentFolder = 0;
        this.currentPage = 1;
        this.isLoading = false;
        this.searchTimeout = null;
        this.activeFilters = {};
        this.folderTree = null;
        this.currentView = this.config.initial_view; // 'ultimas', 'folder', 'search', 'filtered'
        
        this.init();
    }
    
    /**
     * Inicializar el navegador avanzado
     */
    init() {
        console.log('🚀 Advanced Navigator initializing...');
        
        this.bindEvents();
        this.setupResponsive();
        
        if (this.config.sidebar_navigation) {
            this.loadFolderTree();
        }
        
        this.loadInitialContent();
        
        console.log('✅ Advanced Navigator initialized');
    }
    
    /**
     * Vincular eventos del DOM
     */
    bindEvents() {
        const container = document.querySelector('.actas-navigator-advanced');
        if (!container) return;
        
        // Navegación del panel lateral
        container.addEventListener('click', (e) => {
            // Carpetas en el árbol lateral
            if (e.target.classList.contains('folder-item')) {
                e.preventDefault();
                const folderId = parseInt(e.target.dataset.folderId);
                this.navigateToFolder(folderId);
            }
            
            // Breadcrumb navigation
            if (e.target.classList.contains('breadcrumb-item') || e.target.classList.contains('breadcrumb-home')) {
                e.preventDefault();
                const folderId = parseInt(e.target.dataset.folderId) || 0;
                this.navigateToFolder(folderId);
            }
            
            // Paginación
            if (e.target.classList.contains('page-link')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                this.goToPage(page);
            }
            
            // Toggle de filtros
            if (e.target.classList.contains('toggle-filters-btn')) {
                e.preventDefault();
                this.toggleFilters();
            }
            
            // Aplicar filtros
            if (e.target.classList.contains('apply-filters-btn')) {
                e.preventDefault();
                this.applyFilters();
            }
            
            // Limpiar filtros
            if (e.target.classList.contains('clear-filters-btn')) {
                e.preventDefault();
                this.clearFilters();
            }
            
            // Toggle sidebar en móvil
            if (e.target.classList.contains('sidebar-toggle')) {
                e.preventDefault();
                this.toggleSidebar();
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
        
        // Cambios en filtros
        container.addEventListener('change', (e) => {
            if (e.target.classList.contains('filter-input')) {
                // Auto-aplicar filtros después de un delay
                clearTimeout(this.filterTimeout);
                this.filterTimeout = setTimeout(() => {
                    this.applyFilters();
                }, 1000);
            }
        });
    }
    
    /**
     * Configurar comportamiento responsive
     */
    setupResponsive() {
        const checkResponsive = () => {
            const isMobile = window.innerWidth < this.config.responsive_breakpoint;
            const sidebar = document.querySelector('.sidebar-navigation');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (sidebar) {
                if (isMobile) {
                    sidebar.style.display = 'none';
                    if (toggleBtn) toggleBtn.style.display = 'block';
                } else {
                    sidebar.style.display = 'block';
                    if (toggleBtn) toggleBtn.style.display = 'none';
                }
            }
        };
        
        window.addEventListener('resize', checkResponsive);
        checkResponsive(); // Ejecutar inmediatamente
    }
    
    /**
     * Cargar árbol de carpetas para el panel lateral
     */
    async loadFolderTree() {
        try {
            const response = await this.makeAjaxRequest('get_folder_tree', {});
            
            if (response.success) {
                this.folderTree = response.data.folders;
                this.renderFolderTree();
            } else {
                this.showSidebarError('Error al cargar estructura de carpetas');
            }
        } catch (error) {
            console.error('Error loading folder tree:', error);
            this.showSidebarError('Error de conexión');
        }
    }
    
    /**
     * Renderizar árbol de carpetas en el panel lateral
     */
    renderFolderTree() {
        const treeContainer = document.querySelector('.folders-tree');
        if (!treeContainer || !this.folderTree) return;
        
        let html = '<div class="tree-content">';
        
        // Renderizar carpetas raíz y sus hijos
        this.folderTree.forEach(folder => {
            html += this.renderFolderNode(folder);
        });
        
        html += '</div>';
        treeContainer.innerHTML = html;
    }
    
    /**
     * Renderizar un nodo del árbol de carpetas
     */
    renderFolderNode(folder) {
        const isActive = this.currentFolder === folder.id;
        const hasChildren = folder.children && folder.children.length > 0;
        const countText = this.config.show_counters ? ` (${folder.actas_count})` : '';
        
        let html = `
            <div class="tree-node level-${folder.level}">
                <div class="folder-item ${isActive ? 'active' : ''}" data-folder-id="${folder.id}">
                    <span class="folder-icon">${hasChildren ? '📁' : '📄'}</span>
                    <span class="folder-name">${this.escapeHtml(folder.name)}</span>
                    <span class="folder-count">${countText}</span>
                </div>
        `;
        
        // Renderizar hijos si existen
        if (hasChildren) {
            html += '<div class="tree-children">';
            folder.children.forEach(child => {
                html += this.renderFolderNode(child);
            });
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * Cargar contenido inicial según configuración
     */
    async loadInitialContent() {
        switch (this.config.initial_view) {
            case 'ultimas':
                await this.loadRecentActas();
                break;
            case 'carpetas':
                await this.loadRootFolders();
                break;
            case 'vacio':
            default:
                this.showEmptyState();
                break;
        }
    }
    
    /**
     * Cargar actas más recientes
     */
    async loadRecentActas() {
        this.setLoading(true);
        this.currentView = 'ultimas';
        
        try {
            const response = await this.makeAjaxRequest('get_recent_actas', {
                limit: this.config.initial_limit
            });
            
            if (response.success) {
                this.renderActasList(response.data.actas, 'recent');
                this.updateContentTitle(`✨ Últimas ${response.data.total} actas subidas`);
                this.updateBreadcrumb([{ name: 'Inicio', folder_id: 0, is_current: true }]);
            } else {
                this.showError('Error al cargar actas recientes');
            }
        } catch (error) {
            console.error('Error loading recent actas:', error);
            this.showError('Error de conexión');
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Navegar a una carpeta específica
     */
    async navigateToFolder(folderId) {
        if (this.isLoading || this.currentFolder === folderId) return;
        
        this.currentFolder = folderId;
        this.currentPage = 1;
        this.currentView = 'folder';
        
        await this.loadFolderContents(folderId);
        this.updateActiveFolderInTree(folderId);
    }
    
    /**
     * Cargar contenido de una carpeta específica
     */
    async loadFolderContents(folderId) {
        this.setLoading(true);
        
        try {
            // Reutilizar el método existente del navegador básico
            const response = await this.makeAjaxRequest('get_folder_contents', {
                folder_id: folderId,
                page: this.currentPage,
                per_page: 10
            });
            
            if (response.success) {
                const data = response.data;
                
                // Renderizar subcarpetas y actas
                this.renderFolderContent(data);
                
                // Actualizar breadcrumb
                if (data.breadcrumb) {
                    this.updateBreadcrumb(data.breadcrumb);
                }
                
                // Actualizar título
                const folderName = data.folder ? data.folder.name : 'Carpeta raíz';
                const totalItems = (data.actas ? data.actas.length : 0) + (data.subfolders ? data.subfolders.length : 0);
                this.updateContentTitle(`📁 ${folderName} (${totalItems} elementos)`);
                
                // Renderizar paginación si es necesaria
                if (data.pagination && data.pagination.total_pages > 1) {
                    this.renderPagination(data.pagination);
                }
            } else {
                this.showError('Error al cargar contenido de la carpeta');
            }
        } catch (error) {
            console.error('Error loading folder contents:', error);
            this.showError('Error de conexión');
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Renderizar contenido mixto (carpetas + actas)
     */
    renderFolderContent(data) {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        let html = '';
        
        // Renderizar subcarpetas si existen
        if (data.subfolders && data.subfolders.length > 0) {
            html += '<div class="subfolders-section">';
            html += '<h3 class="section-title">📁 Subcarpetas</h3>';
            html += '<div class="folders-grid">';
            
            data.subfolders.forEach(folder => {
                html += `
                    <div class="folder-card">
                        <div class="folder-icon">📁</div>
                        <div class="folder-info">
                            <h4 class="folder-name">
                                <a href="#" class="folder-item" data-folder-id="${folder.id}">
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
        
        // Renderizar actas si existen
        if (data.actas && data.actas.length > 0) {
            html += '<div class="actas-section">';
            html += '<h3 class="section-title">📄 Actas</h3>';
            html += this.renderActasGrid(data.actas);
            html += '</div>';
        }
        
        // Estado vacío
        if ((!data.subfolders || data.subfolders.length === 0) && 
            (!data.actas || data.actas.length === 0)) {
            html = this.getEmptyStateHtml('Esta carpeta no contiene elementos');
        }
        
        container.innerHTML = html;
    }
    
    /**
     * Renderizar lista de actas
     */
    renderActasList(actas, type = 'normal') {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        let html = '';
        
        if (actas && actas.length > 0) {
            html += '<div class="actas-section">';
            if (type === 'recent') {
                html += '<div class="recent-badge">⭐ Contenido más reciente</div>';
            }
            html += this.renderActasGrid(actas);
            html += '</div>';
        } else {
            html = this.getEmptyStateHtml('No se encontraron actas');
        }
        
        container.innerHTML = html;
    }
    
    /**
     * Renderizar grid de actas
     */
    renderActasGrid(actas) {
        let html = '<div class="actas-grid">';
        
        actas.forEach(acta => {
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
        
        html += '</div>';
        return html;
    }
    
    /**
     * Actualizar breadcrumb
     */
    updateBreadcrumb(breadcrumb) {
        const container = document.querySelector('.breadcrumb');
        if (!container || !breadcrumb) return;
        
        let html = '';
        
        breadcrumb.forEach((item, index) => {
            const isLast = index === breadcrumb.length - 1;
            const classes = `breadcrumb-item ${isLast ? 'active' : ''}`;
            
            if (isLast) {
                html += `<li class="${classes}"><span>${this.escapeHtml(item.name)}</span></li>`;
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
        
        container.innerHTML = html;
    }
    
    /**
     * Actualizar carpeta activa en el árbol lateral
     */
    updateActiveFolderInTree(folderId) {
        const treeItems = document.querySelectorAll('.folder-item');
        treeItems.forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.folderId) === folderId) {
                item.classList.add('active');
            }
        });
    }
    
    /**
     * Actualizar título del contenido
     */
    updateContentTitle(title) {
        const titleElement = document.querySelector('.content-title');
        if (titleElement) {
            titleElement.textContent = title;
        }
    }
    
    /**
     * Buscar actas (reutilizar método del navegador básico)
     */
    async performSearch(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) return;
        
        this.setLoading(true);
        this.currentView = 'search';
        
        try {
            const response = await this.makeAjaxRequest('search_actas', {
                search_term: searchTerm,
                page: this.currentPage,
                per_page: 10
            });
            
            if (response.success) {
                this.renderSearchResults(response.data);
                this.updateContentTitle(`🔍 Resultados para "${searchTerm}"`);
                this.updateBreadcrumb([
                    { name: 'Inicio', folder_id: 0, is_current: false },
                    { name: `Búsqueda: ${searchTerm}`, folder_id: -1, is_current: true }
                ]);
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
                <div class="search-info">
                    <h3>🔍 Resultados de búsqueda</h3>
                    <button class="btn btn-secondary clear-search-btn">❌ Limpiar búsqueda</button>
                </div>
            </div>
        `;
        
        if (data.actas && data.actas.length > 0) {
            html += '<div class="actas-section">';
            html += this.renderActasGrid(data.actas);
            html += '</div>';
        } else {
            html += this.getEmptyStateHtml(`No se encontraron resultados para "${data.search_term}"`);
        }
        
        container.innerHTML = html;
        
        // Agregar evento para limpiar búsqueda
        const clearBtn = container.querySelector('.clear-search-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.clearSearch();
            });
        }
        
        // Renderizar paginación para búsqueda
        if (data.pagination && data.pagination.total_pages > 1) {
            this.renderPagination(data.pagination);
        }
    }
    
    /**
     * Limpiar búsqueda
     */
    clearSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        
        this.currentPage = 1;
        this.loadInitialContent();
    }
    
    /**
     * Manejar input de búsqueda con debounce
     */
    handleSearchInput(searchTerm) {
        clearTimeout(this.searchTimeout);
        
        if (searchTerm.length < 2) {
            this.clearSearch();
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.performSearch(searchTerm);
        }, 500);
    }
    
    /**
     * Toggle del panel de filtros
     */
    toggleFilters() {
        const filtersPanel = document.querySelector('.filters-panel');
        if (filtersPanel) {
            const isVisible = filtersPanel.style.display !== 'none';
            filtersPanel.style.display = isVisible ? 'none' : 'block';
        }
    }
    
    /**
     * Aplicar filtros
     */
    async applyFilters() {
        // Implementar lógica de filtros similar al navegador básico
        console.log('Applying filters...');
        // Por ahora, simplemente recargar contenido
        if (this.currentView === 'folder') {
            await this.loadFolderContents(this.currentFolder);
        }
    }
    
    /**
     * Limpiar filtros
     */
    clearFilters() {
        const filterInputs = document.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            input.value = '';
        });
        this.applyFilters();
    }
    
    /**
     * Toggle sidebar en móvil
     */
    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar-navigation');
        if (sidebar) {
            const isVisible = sidebar.style.display !== 'none';
            sidebar.style.display = isVisible ? 'none' : 'block';
        }
    }
    
    /**
     * Ir a página específica
     */
    async goToPage(page) {
        if (this.isLoading || page === this.currentPage) return;
        
        this.currentPage = page;
        
        switch (this.currentView) {
            case 'folder':
                await this.loadFolderContents(this.currentFolder);
                break;
            case 'search':
                const searchTerm = document.querySelector('.search-input')?.value;
                if (searchTerm) {
                    await this.performSearch(searchTerm);
                }
                break;
            default:
                await this.loadInitialContent();
                break;
        }
    }
    
    /**
     * Renderizar paginación
     */
    renderPagination(pagination) {
        // Reutilizar método del navegador básico
        const container = document.querySelector('.pagination-container');
        if (!container) return;
        
        const { current_page, total_pages, total } = pagination;
        
        let html = '<nav class="pagination-nav">';
        html += `<div class="pagination-info">Página ${current_page} de ${total_pages} (${total} elementos)</div>`;
        html += '<ul class="pagination">';
        
        // Botón anterior
        if (current_page > 1) {
            html += `<li><a href="#" class="page-link" data-page="${current_page - 1}">« Anterior</a></li>`;
        }
        
        // Números de página
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === current_page ? 'active' : '';
            html += `<li><a href="#" class="page-link ${activeClass}" data-page="${i}">${i}</a></li>`;
        }
        
        // Botón siguiente
        if (current_page < total_pages) {
            html += `<li><a href="#" class="page-link" data-page="${current_page + 1}">Siguiente »</a></li>`;
        }
        
        html += '</ul></nav>';
        container.innerHTML = html;
    }
    
    /**
     * Estados de loading y error
     */
    setLoading(loading) {
        this.isLoading = loading;
        
        const container = document.querySelector('.actas-content-area');
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
    
    showLoadingIndicator() {
        const container = document.querySelector('.actas-list-container');
        if (container && !container.querySelector('.loading-indicator')) {
            container.innerHTML = `
                <div class="loading-indicator">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>${advancedNavAjax.loading_text}</p>
                    </div>
                </div>
            `;
        }
    }
    
    hideLoadingIndicator() {
        const indicator = document.querySelector('.loading-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    showError(message) {
        const container = document.querySelector('.actas-list-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="error-message">
                <div class="error-content">
                    <span class="error-icon">❌</span>
                    <span class="error-text">${this.escapeHtml(message)}</span>
                </div>
            </div>
        `;
    }
    
    showSidebarError(message) {
        const treeContainer = document.querySelector('.folders-tree');
        if (treeContainer) {
            treeContainer.innerHTML = `
                <div class="tree-error">
                    <span class="error-icon">❌</span>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    showEmptyState() {
        const container = document.querySelector('.actas-list-container');
        if (container) {
            container.innerHTML = this.getEmptyStateHtml('Seleccione una carpeta para ver su contenido');
        }
        this.updateContentTitle('📁 Navegador de Actas');
    }
    
    getEmptyStateHtml(message) {
        return `
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>Sin contenido</h3>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }
    
    /**
     * Realizar petición AJAX
     */
    async makeAjaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', advancedNavAjax.nonce);
        
        Object.keys(data).forEach(key => {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        });
        
        const response = await fetch(advancedNavAjax.ajaxurl, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
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
    const navigatorContainer = document.querySelector('.actas-navigator-advanced');
    if (navigatorContainer) {
        // Obtener configuración del atributo data
        const config = navigatorContainer.dataset.config ? 
            JSON.parse(navigatorContainer.dataset.config) : {};
        
        // Inicializar navegador avanzado
        window.advancedNavigator = new AdvancedNavigator(config);
        
        console.log('🎯 Advanced Navigator ready');
    }
});
