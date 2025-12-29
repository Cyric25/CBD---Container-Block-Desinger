# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Container Block Designer is a WordPress plugin that creates customizable container blocks for the Gutenberg Block Editor. It allows users to create, manage, and apply styled container blocks with features like collapsible sections, copy-to-clipboard, screenshots, and automatic numbering.

**Current Version:** 2.9.0
**WordPress Requirements:** 6.0+
**PHP Requirements:** 8.0+
**Tested up to:** WordPress 6.4, PHP 8.4

## Architecture

### Core Plugin Structure

The plugin follows a **singleton pattern** with **service container architecture**:

1. **Main Plugin Class** (`container-block-designer.php`): `ContainerBlockDesigner` singleton that bootstraps the entire plugin
2. **Service Container** (`includes/class-service-container.php`): Dependency injection container managing all services
3. **Autoloader** (`includes/class-autoloader.php`): PSR-4 compatible fallback autoloader when Composer is unavailable

### Initialization Flow

1. `ContainerBlockDesigner::get_instance()` creates singleton
2. Service container initialized via `init_container()`
3. Dependencies loaded via `load_dependencies()`
4. Services registered through container: database, style_loader, block_registration, ajax_handler, admin
5. Block registration occurs on `init` hook through `CBD_Block_Registration::register_blocks()`

### Key Services (Service Container)

Access services via: `cbd_get_service('service_name')` or through the container

- **database**: Database operations wrapper
- **style_loader**: Dynamic CSS generation and caching (`CBD_Style_Loader`)
- **block_registration**: Registers blocks with WordPress (`CBD_Block_Registration`)
- **ajax_handler**: Handles AJAX requests (`CBD_Ajax_Handler`)
- **admin**: Admin interface management (`CBD_Admin`)
- **schema_manager**: Database schema and migrations (`CBD_Schema_Manager`)

### Database Schema

**Table:** `{$wpdb->prefix}cbd_blocks`

Columns:
- `id`: Auto-increment primary key
- `name`: Unique block identifier (slug format, e.g., 'basic-container')
- `title`: Display name
- `description`: Block description
- `config`: JSON configuration (allowInnerBlocks, maxWidth, minHeight)
- `styles`: JSON styles (padding, background, border, typography)
- `features`: JSON features (collapsible, icon, copyText, screenshot, numbering)
- `status`: 'active' or 'inactive'
- `created_at`, `updated_at`: Timestamps

### Block Registration System

Blocks are **dynamically registered** from database entries:

1. `CBD_Block_Registration::register_blocks()` queries active blocks from database
2. Each block registered as `container-block-designer/{sanitized-name}`
3. Blocks support nested InnerBlocks via Gutenberg
4. Render callback: `CBD_Block_Registration::render_block()`

**Important:** Block names in database must be lowercase, hyphenated (e.g., 'basic-container', 'card-container', 'hero-section')

### Frontend Rendering

- **Primary Renderer**: `CBD_Block_Registration::render_block()` (server-side block rendering)
- **Frontend JavaScript**: `assets/js/interactivity-fallback.js` handles interactive features (collapsible, copy, screenshot)
- **Interactivity API**: `assets/js/interactivity-store.js` uses WordPress Interactivity API for modern state management
- **Features Handled:**
  - Collapsible sections (with toggle buttons)
  - Copy text to clipboard
  - Screenshot generation (using html2canvas)
  - Automatic numbering of nested blocks
  - LaTeX math rendering via KaTeX

### Admin Interface

Admin pages (located in `admin/` directory):
- **Blocks List** (`blocks-list.php`): Overview of all container blocks
- **New Block** (`new-block.php`): Create new container block designs
- **Edit Block** (`edit-block.php`): Modify existing block configurations
- **Settings** (`settings.php`): Plugin settings
- **Import/Export** (`import-export.php`): Bulk operations for blocks

Admin menu registered via `CBD_Admin::add_admin_menu()`

### Custom User Roles

**Block-Redakteur Role:** Limited access role for content editors
- Can edit pages and use container blocks
- Cannot create/modify block designs
- Cannot access Posts menu (hidden via CSS/JS)
- Managed by `ContainerBlockDesigner::create_block_editor_role()`

**Capabilities:**
- `cbd_edit_blocks`: Use container blocks in editor
- `cbd_edit_styles`: Edit block styles (admins only)
- `cbd_admin_blocks`: Access admin interface (admins only)

## Development Commands

### PHP/WordPress

```bash
# Run WordPress CLI commands
wp core version

# Check database tables
wp db query "SHOW TABLES LIKE 'wp_cbd_blocks'"

# Clear plugin caches
wp cache flush
```

### Code Quality (Composer scripts)

```bash
# Run PHPUnit tests
composer test

# Run PHP CodeSniffer (WordPress Coding Standards)
composer cs

# Auto-fix coding standards issues
composer cbf

# Static analysis (PHPStan)
composer analyze
```

### Common Development Tasks

1. **Clearing Plugin Cache:**
   - Style cache stored in WordPress transients
   - Clear via: `CBD_Style_Loader::get_instance()->clear_styles_cache()`
   - Or flush all caches: `wp cache flush`

2. **Adding New Block Types:**
   - Insert into `{$wpdb->prefix}cbd_blocks` table
   - Or use admin interface: Admin → Container Blocks → Block hinzufügen

3. **Debugging Block Registration:**
   - Enable WP_DEBUG in wp-config.php
   - Check error logs at: `wp-content/debug.log`
   - Block registration logs prefixed with `[CBD Block Registration]`

### Plugin ZIP Creation

**CRITICAL: Always run syntax check before creating plugin ZIP!**

CDB-Designer uses pure PHP with vanilla JavaScript (no build process required), but syntax checking is mandatory.

**Create distributable plugin ZIP:**

```bash
# ALWAYS run syntax check first!
for file in *.php includes/*.php includes/Database/*.php; do php -l "$file" || exit 1; done && node create-plugin-zip.js
```

**Syntax Check (MANDATORY before ZIP creation):**

```bash
# Check all PHP files for syntax errors
for file in *.php includes/*.php includes/Database/*.php; do
  echo "Checking $file..."
  php -l "$file" || exit 1
done
```

**Complete workflow (recommended):**

```bash
# 1. Syntax check all PHP files
for file in *.php includes/*.php includes/Database/*.php; do
  php -l "$file" || exit 1
done

# 2. If no errors: Create plugin ZIP
node create-plugin-zip.js

# 3. Commit and push
git add .
git commit -m "Your commit message"
git push origin main
```

**Why this matters:**
- Prevents distributing broken PHP code
- Catches syntax errors in main plugin file, includes, and Database classes
- Ensures WordPress won't show fatal errors
- Required before every ZIP creation

**What gets checked:**
- Plugin main file: `container-block-designer.php`
- All files in `includes/` directory
- All files in `includes/Database/` directory
- Syntax validation via `php -l`
- Exit immediately on first error (`|| exit 1`)

**If syntax error found:**
- Fix the error
- Re-run syntax check
- Only then create ZIP

**ZIP output location:** `container-block-designer-v{version}.zip` (plugin root)

## Important Files

### Core PHP Files
- `container-block-designer.php` - Main plugin file, bootstrap
- `includes/class-service-container.php` - Dependency injection container
- `includes/class-cbd-block-registration.php` - Block registration logic (lines 51-80 for registration flow, 545-883 for rendering)
- `includes/class-cbd-style-loader.php` - Dynamic CSS generation
- `includes/class-cbd-admin.php` - Admin interface controller
- `includes/class-cbd-ajax-handler.php` - AJAX endpoint handler
- `includes/Database/class-schema-manager.php` - Database schema management
- `includes/class-latex-parser.php` - LaTeX math formula parsing (lines 114-170 for parsing logic)

### JavaScript Files
- `assets/js/block-editor.js` - Gutenberg block editor integration
- `assets/js/interactivity-store.js` - WordPress Interactivity API integration (modern)
- `assets/js/interactivity-fallback.js` - jQuery-based fallback for interactive features
- `assets/js/floating-pdf-button.js` - Floating PDF export button
- `assets/js/latex-renderer.js` - LaTeX rendering with KaTeX
- `assets/js/jspdf-loader.js` - PDF export functionality loader

### Admin Templates
- `admin/blocks-list.php` - Block management list view
- `admin/new-block.php` - Block creation form
- `admin/edit-block.php` - Block editing interface
- `admin/settings.php` - Plugin settings page
- `admin/import-export.php` - Import/export functionality

## Known Issues & Technical Debt

1. **Double Rendering Prevention:** Frontend renderer (`CBD_Consolidated_Frontend`) disabled to prevent conflicts with block registration system (lines 114-118, 244-247 in main plugin file)

2. **Block Isolation:** Nested container blocks are isolated to prevent interference (v2.7.7 fix)

3. **iOS Screenshot Compatibility:** Special handling for iOS devices documented in `IOS-SCREENSHOT-STRATEGY.md`

4. **PHP 8 Compatibility:** Compatibility layer in `includes/php8-wordpress-compatibility.php`

5. **LaTeX Parser Integration:** LaTeX formulas parsed in `CBD_Block_Registration::render_block()` at line 850-853 via `CBD_LaTeX_Parser::parse_latex()`

## Database Migrations

Managed by `CBD_Schema_Manager`:
- Current DB version: 2.6.0
- Migration history stored in `cbd_db_version` option
- Migrations run automatically on plugin activation/update
- Manual migration: `CBD_Schema_Manager::run_migrations()`

## Security Considerations

- Nonces required for all AJAX requests
- Capability checks via custom capabilities (cbd_edit_blocks, cbd_admin_blocks)
- SQL queries use `$wpdb->prepare()` for prepared statements
- Direct file access prevented via `ABSPATH` checks
- Upload directory protected with `.htaccess`

## Testing

- Test files should be in `tests/` directory (PSR-4: `ContainerBlockDesigner\Tests\`)
- Run tests via `composer test` (PHPUnit)
- Manual testing workflow:
  1. Create test block in admin
  2. Add block to page in editor
  3. Preview/publish page
  4. Test frontend features (collapse, copy, screenshot, numbering)
  5. Verify styles applied correctly

## Git Workflow

Current branch: `main` (also the main branch for PRs)

Recent fixes:
- v2.7.6: Numbering shows only top-level blocks
- v2.7.7: Fixed nested container block isolation
- v2.7.7: Repaired copy text and screenshot functions

## Additional Documentation

Status/technical notes available in repository:
- `FRONTEND_STATUS.md` - Frontend rendering status
- `HTML_ELEMENTS_FIX_STATUS.md` - HTML compatibility fixes
- `INLINE-SCRIPT-ISOLATION.md` - Script isolation strategy
- `INTERACTIVITY-API.md` - WordPress Interactivity API usage
- `IOS-SCREENSHOT-STRATEGY.md` - iOS screenshot handling
- `PHP8-COMPATIBILITY.md` - PHP 8 compatibility notes
- `POSITIONING_FIX_COMPLETE.md` - Layout positioning fixes
