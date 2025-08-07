# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin "Visor PDF Crisman" v2.0.3 - Secure PDF document management system with watermarking for protected collegiate documents (actas). Uses member numbers (numero_colegiado) for access control.

## Key Architecture Components

### Main Plugin Structure
- **visor-pdf-crisman.php**: Main plugin bootstrap file containing `VisorPDFCrisman` class
  - Singleton pattern implementation
  - Handles plugin activation/deactivation
  - Manages AJAX endpoints and shortcodes
  - Coordinates all plugin modules

### Core Modules (includes/)

**Inheritance Hierarchy**: All modules extend from `Visor_PDF_Core` base class

- **class-visor-core.php**: `Visor_PDF_Core` - Base class with core functionality
  - PDF rendering with watermarks using Imagick (critical dependency)
  - User permission verification via `numero_colegiado` meta field
  - Database operations for all 4 actas tables
  - Logging and security features, requirements checking
  - ~1300 lines - largest and most critical class

- **class-folders-manager.php**: `Visor_PDF_Folders_Manager` - Folder organization
  - Hierarchical folder structure (max 2 levels: parent → child)
  - CRUD operations for folder management
  - Bulk document reorganization capabilities
  - Admin interface for folder administration

- **class-mass-upload.php**: `Visor_PDF_Mass_Upload` - Bulk operations
  - Mass PDF upload with progress tracking
  - Batch file validation and processing
  - Metadata assignment during upload

- **class-frontend-navigation.php**: `Visor_PDF_Frontend_Navigation` - UI navigation
  - Visual navigator with folder-based browsing
  - Hierarchical folder display logic
  - Frontend user interaction handling

- **class-analytics.php**: `Visor_PDF_Analytics` - Statistics & reporting
  - Usage analytics with caching system
  - Administrative dashboards and metrics
  - Performance tracking and optimization

- **class-plugin-updater.php**: `Visor_PDF_Plugin_Updater` - GitHub update system
  - Automatic updates from GitHub repository
  - Version checking and update notifications

### Database Tables
- `{prefix}_actas_logs` - Document viewing logs
- `{prefix}_actas_metadata` - Document metadata and information
- `{prefix}_actas_folders` - Folder hierarchy structure
- `{prefix}_actas_suspicious_logs` - Security and suspicious activity logs

### Key Features
- PDF viewing with watermarks (using Imagick PHP extension)
- User authentication via "numero_colegiado" (member number)
- Hierarchical folder organization
- Activity logging and analytics
- Security features (anti-download protection)
- GitHub-based auto-updates

## Development Commands

### WordPress Development
```bash
# No build tools required - pure PHP WordPress plugin
# Development happens directly in WordPress plugins directory

# To test the plugin:
# 1. Copy to wp-content/plugins/visor-pdf-crisman/
# 2. Activate in WordPress admin
# 3. Verify Imagick extension is installed

# Check PHP syntax
php -l visor-pdf-crisman.php
php -l includes/*.php
php -l templates/*.php

# Check syntax for all PHP files (utility script available)
php verificar_sintaxis.php
```

### Testing & Debugging
```bash
# Enable WordPress debug mode in wp-config.php:
# define('WP_DEBUG', true);
# define('WP_DEBUG_LOG', true);
# define('WP_DEBUG_DISPLAY', false);

# View debug logs at:
# wp-content/debug.log
```

### Built-in Testing Tools
Access these debug utilities via WordPress admin (admin only):
- `debug-navigator.php` - Debug folder hierarchy and navigation structure
- `debug-filtering.php` - Debug document filtering functionality  
- `test-simple.php` - Simple test for visual navigator shortcode
- `test-selector-carpetas.php` - Test folder selector functionality
- `test-table-format.php` - Test document table formatting

### Manual Testing Workflow
1. Ensure test user has `numero_colegiado` meta field set
2. Upload test PDFs to verify watermark generation
3. Check folder hierarchy creation and navigation
4. Verify security logs are created during document access
5. Test responsive behavior and mobile compatibility

## Key Shortcodes
- `[actas_viewer]` - Basic document viewer
- `[actas_navigator_visual]` - Visual folder navigation
- `[actas_hybrid]` - Hybrid viewer with folder navigation

## AJAX Endpoints

### Core Viewer Endpoints (handled by main plugin class)
- `load_pdf_page` - Load PDF page with user-specific watermark
- `actas_heartbeat` - Keep session alive during document viewing
- `log_suspicious_activity` - Security logging for suspicious behavior
- `get_folder_actas` - Get documents within specified folder
- `visor_diagnostico` - System health and requirements check

### Module-Specific Endpoints
- **Folders Manager**: `migrate_to_hierarchy` - Convert flat structure to hierarchical
- **Analytics**: `get_quick_analytics`, `clear_analytics_cache` - Statistics management

### Endpoint Access Control
- All endpoints available to both logged-in (`wp_ajax_`) and non-logged (`wp_ajax_nopriv_`) users
- Permission verification happens within each endpoint using `numero_colegiado` validation
- Security logging applied to all document access attempts

## Important Considerations

### Security
- All PDFs stored in protected `/wp-content/uploads/actas-pdf/` directory
- Access controlled via `.htaccess` rules
- Watermarks applied on-the-fly with user's member number
- Extensive activity logging for audit trails
- Multiple frontend protections (disabled dev tools, right-click, text selection)

### Requirements
- **PHP 7.4+** with **Imagick extension** (critical for PDF processing)
- WordPress 5.0+
- MySQL 5.7+
- Minimum 256MB PHP memory (512MB recommended)

### Current Development Focus
Based on recent git commits:
- Table view format for document listings (`acta-table-row.php`)
- Hybrid viewer improvements
- GitHub auto-update system implementation
- Modal error handling improvements

## Module Interaction Patterns

### Initialization Flow
1. `VisorPDFCrisman::delayed_init()` → WordPress `init` hook (priority 20)
2. `load_dependencies()` → Include all class files
3. `init_modules()` → Instantiate module objects (all extend `Visor_PDF_Core`)
4. `init_hooks()` → Register AJAX endpoints and shortcodes

### Data Flow for PDF Viewing
1. User requests PDF via shortcode → `VisorPDFCrisman` handles shortcode
2. Frontend JavaScript calls `load_pdf_page` AJAX endpoint
3. `Visor_PDF_Core` validates user permissions and `numero_colegiado`
4. Imagick processes PDF page with user-specific watermark
5. Activity logged to `actas_logs` table

### Module Communication
- All modules inherit from `Visor_PDF_Core` (shared database access, logging)
- Main plugin class coordinates between modules via object properties
- No direct module-to-module communication - always through main class
- Shared utilities available through base class methods

## Common Tasks

### Adding New Features
1. Create new class in `includes/` extending `Visor_PDF_Core`
2. Add instantiation in main plugin file's `init_modules()` method
3. Register AJAX handlers in `init_hooks()` if needed
4. Create templates in `templates/` directory for UI components
5. Test using built-in debug tools (`debug-*.php`, `test-*.php`)

### Modifying PDF Viewer Behavior
- **Core logic**: `includes/class-visor-core.php` (watermark generation, permissions)
- **Frontend JavaScript**: `assets/visor-pdf.js` (page navigation, zoom, security)
- **Viewer styles**: `assets/visor-pdf.css` (responsive design, UI components)
- **Templates**: `templates/viewer-hybrid.php` (HTML structure)

### Database Schema Changes
1. Modify table creation in `VisorPDFCrisman::create_tables()` method
2. Use WordPress `dbDelta()` for schema updates
3. Implement migration logic in separate function
4. Test with `debug-navigator.php` to verify data structure
5. Consider backward compatibility for existing installations

### Security Considerations
- Always validate `numero_colegiado` before granting PDF access
- Log all document access attempts to `actas_logs` table
- Use nonces for AJAX requests to prevent CSRF
- Sanitize all user inputs with `sanitize_text_field()` and similar functions
- Never expose direct file paths or allow direct PDF access