<?php
/**
 * Container Block Designer - Import/Export UI
 * 
 * User interface for importing and exporting block configurations
 * 
 * @package ContainerBlockDesigner
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'export';
?>

<div class="cbd-import-export-page">
    <h2><?php echo esc_html__('Import/Export Container Blocks', 'container-block-designer'); ?></h2>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="?page=container-block-designer&tab=import-export&action=export" 
           class="nav-tab <?php echo $current_tab === 'export' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Export', 'container-block-designer'); ?>
        </a>
        <a href="?page=container-block-designer&tab=import-export&action=import" 
           class="nav-tab <?php echo $current_tab === 'import' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Import', 'container-block-designer'); ?>
        </a>
        <a href="?page=container-block-designer&tab=import-export&action=templates" 
           class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Template Library', 'container-block-designer'); ?>
        </a>
    </nav>
    
    <div class="cbd-import-export-content">
        <?php if ($current_tab === 'export'): ?>
        
        <!-- Export Section -->
        <div class="cbd-export-section">
            <div class="card">
                <h3><?php echo esc_html__('Export Blocks', 'container-block-designer'); ?></h3>
                <p><?php echo esc_html__('Exportieren Sie Ihre Container Blocks als JSON-Datei, um sie zu sichern oder auf einer anderen Website zu verwenden.', 'container-block-designer'); ?></p>
                
                <form id="cbd-export-form" method="post">
                    <?php wp_nonce_field('cbd_export_blocks', 'cbd_export_nonce'); ?>
                    
                    <h4><?php echo esc_html__('Wählen Sie Blocks zum Exportieren:', 'container-block-designer'); ?></h4>
                    
                    <div class="cbd-export-options">
                        <label class="cbd-export-option">
                            <input type="radio" name="export_type" value="all" checked>
                            <span><?php echo esc_html__('Alle Blocks exportieren', 'container-block-designer'); ?></span>
                        </label>
                        
                        <label class="cbd-export-option">
                            <input type="radio" name="export_type" value="active">
                            <span><?php echo esc_html__('Nur aktive Blocks exportieren', 'container-block-designer'); ?></span>
                        </label>
                        
                        <label class="cbd-export-option">
                            <input type="radio" name="export_type" value="selected">
                            <span><?php echo esc_html__('Ausgewählte Blocks exportieren', 'container-block-designer'); ?></span>
                        </label>
                    </div>
                    
                    <div id="cbd-block-selector" style="display: none;">
                        <?php
                        global $wpdb;
                        $blocks = $wpdb->get_results("SELECT id, name, slug, status FROM " . CBD_TABLE_BLOCKS . " ORDER BY name");
                        
                        if ($blocks):
                        ?>
                        <div class="cbd-block-list">
                            <?php foreach ($blocks as $block): ?>
                            <label class="cbd-block-item">
                                <input type="checkbox" name="blocks[]" value="<?php echo esc_attr($block->id); ?>">
                                <span class="cbd-block-name"><?php echo esc_html($block->name); ?></span>
                                <span class="cbd-block-meta">
                                    <code><?php echo esc_html($block->slug); ?></code>
                                    <span class="cbd-status-badge <?php echo esc_attr($block->status); ?>">
                                        <?php echo esc_html($block->status); ?>
                                    </span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p><?php echo esc_html__('Keine Blocks vorhanden.', 'container-block-designer'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <h4><?php echo esc_html__('Export-Optionen:', 'container-block-designer'); ?></h4>
                    
                    <div class="cbd-export-settings">
                        <label>
                            <input type="checkbox" name="include_features" value="1" checked>
                            <?php echo esc_html__('Features-Konfiguration einschließen', 'container-block-designer'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="include_css" value="1" checked>
                            <?php echo esc_html__('CSS-Variablen einschließen', 'container-block-designer'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="include_metadata" value="1" checked>
                            <?php echo esc_html__('Metadaten einschließen (Autor, Datum)', 'container-block-designer'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="compress" value="1">
                            <?php echo esc_html__('Datei komprimieren (ZIP)', 'container-block-designer'); ?>
                        </label>
                    </div>
                    
                    <div class="cbd-export-actions">
                        <button type="button" id="cbd-export-btn" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Blocks exportieren', 'container-block-designer'); ?>
                        </button>
                        
                        <button type="button" id="cbd-preview-export" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php echo esc_html__('Vorschau', 'container-block-designer'); ?>
                        </button>
                    </div>
                </form>
                
                <div id="cbd-export-preview" style="display: none;">
                    <h4><?php echo esc_html__('Export-Vorschau:', 'container-block-designer'); ?></h4>
                    <pre class="cbd-code-preview"></pre>
                </div>
            </div>
        </div>
        
        <?php elseif ($current_tab === 'import'): ?>
        
        <!-- Import Section -->
        <div class="cbd-import-section">
            <div class="card">
                <h3><?php echo esc_html__('Import Blocks', 'container-block-designer'); ?></h3>
                <p><?php echo esc_html__('Importieren Sie Container Blocks aus einer JSON-Datei oder fügen Sie JSON-Code direkt ein.', 'container-block-designer'); ?></p>
                
                <form id="cbd-import-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('cbd_import_blocks', 'cbd_import_nonce'); ?>
                    
                    <div class="cbd-import-methods">
                        <div class="cbd-import-method">
                            <h4><?php echo esc_html__('Methode 1: Datei hochladen', 'container-block-designer'); ?></h4>
                            <input type="file" name="import_file" id="cbd-import-file" accept=".json,.zip">
                            <p class="description">
                                <?php echo esc_html__('Wählen Sie eine JSON- oder ZIP-Datei mit exportierten Blocks.', 'container-block-designer'); ?>
                            </p>
                        </div>
                        
                        <div class="cbd-import-method">
                            <h4><?php echo esc_html__('Methode 2: JSON-Code einfügen', 'container-block-designer'); ?></h4>
                            <textarea name="import_json" id="cbd-import-json" rows="10" placeholder='{"blocks": [...]}' class="large-text code"></textarea>
                        </div>
                        
                        <div class="cbd-import-method">
                            <h4><?php echo esc_html__('Methode 3: URL importieren', 'container-block-designer'); ?></h4>
                            <input type="url" name="import_url" id="cbd-import-url" class="large-text" placeholder="https://example.com/blocks.json">
                            <p class="description">
                                <?php echo esc_html__('Geben Sie eine URL zu einer JSON-Datei ein.', 'container-block-designer'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <h4><?php echo esc_html__('Import-Optionen:', 'container-block-designer'); ?></h4>
                    
                    <div class="cbd-import-settings">
                        <label>
                            <input type="checkbox" name="overwrite_existing" value="1">
                            <?php echo esc_html__('Existierende Blocks überschreiben (gleicher Slug)', 'container-block-designer'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="import_inactive" value="1" checked>
                            <?php echo esc_html__('Blocks als inaktiv importieren (zur Überprüfung)', 'container-block-designer'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="backup_before_import" value="1" checked>
                            <?php echo esc_html__('Backup vor Import erstellen', 'container-block-designer'); ?>
                        </label>
                    </div>
                    
                    <div class="cbd-import-actions">
                        <button type="button" id="cbd-validate-import" class="button">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html__('Validieren', 'container-block-designer'); ?>
                        </button>
                        
                        <button type="button" id="cbd-import-btn" class="button button-primary" disabled>
                            <span class="dashicons dashicons-upload"></span>
                            <?php echo esc_html__('Blocks importieren', 'container-block-designer'); ?>
                        </button>
                    </div>
                </form>
                
                <div id="cbd-import-validation" style="display: none;">
                    <h4><?php echo esc_html__('Validierungsergebnis:', 'container-block-designer'); ?></h4>
                    <div class="cbd-validation-result"></div>
                </div>
            </div>
        </div>
        
        <?php elseif ($current_tab === 'templates'): ?>
        
        <!-- Template Library -->
        <div class="cbd-templates-section">
            <div class="card">
                <h3><?php echo esc_html__('Template Library', 'container-block-designer'); ?></h3>
                <p><?php echo esc_html__('Vorgefertigte Container Block Templates zum schnellen Start.', 'container-block-designer'); ?></p>
                
                <div class="cbd-template-filters">
                    <button class="cbd-filter-btn active" data-category="all">
                        <?php echo esc_html__('Alle', 'container-block-designer'); ?>
                    </button>
                    <button class="cbd-filter-btn" data-category="hero">
                        <?php echo esc_html__('Hero Sections', 'container-block-designer'); ?>
                    </button>
                    <button class="cbd-filter-btn" data-category="content">
                        <?php echo esc_html__('Content', 'container-block-designer'); ?>
                    </button>
                    <button class="cbd-filter-btn" data-category="cta">
                        <?php echo esc_html__('Call to Action', 'container-block-designer'); ?>
                    </button>
                    <button class="cbd-filter-btn" data-category="features">
                        <?php echo esc_html__('Features', 'container-block-designer'); ?>
                    </button>
                    <button class="cbd-filter-btn" data-category="testimonials">
                        <?php echo esc_html__('Testimonials', 'container-block-designer'); ?>
                    </button>
                </div>
                
                <div class="cbd-templates-grid">
                    <?php
                    $templates = [
                        [
                            'id' => 'hero-gradient',
                            'name' => 'Gradient Hero',
                            'category' => 'hero',
                            'preview' => CBD_PLUGIN_URL . 'assets/images/templates/hero-gradient.jpg',
                            'description' => 'Moderner Hero-Bereich mit Gradient-Hintergrund'
                        ],
                        [
                            'id' => 'content-cards',
                            'name' => 'Content Cards',
                            'category' => 'content',
                            'preview' => CBD_PLUGIN_URL . 'assets/images/templates/content-cards.jpg',
                            'description' => 'Kartenbasiertes Content-Layout'
                        ],
                        [
                            'id' => 'cta-centered',
                            'name' => 'Centered CTA',
                            'category' => 'cta',
                            'preview' => CBD_PLUGIN_URL . 'assets/images/templates/cta-centered.jpg',
                            'description' => 'Zentrierter Call-to-Action Bereich'
                        ],
                        [
                            'id' => 'features-grid',
                            'name' => 'Features Grid',
                            'category' => 'features',
                            'preview' => CBD_PLUGIN_URL . 'assets/images/templates/features-grid.jpg',
                            'description' => 'Grid-Layout für Feature-Präsentation'
                        ],
                        [
                            'id' => 'testimonial-slider',
                            'name' => 'Testimonial Slider',
                            'category' => 'testimonials',
                            'preview' => CBD_PLUGIN_URL . 'assets/images/templates/testimonial-slider.jpg',
                            'description' => 'Slider für Kundenbewertungen'
                        ]
                    ];
                    
                    foreach ($templates as $template):
                    ?>
                    <div class="cbd-template-card" data-category="<?php echo esc_attr($template['category']); ?>">
                        <div class="cbd-template-preview">
                            <?php if (file_exists(CBD_PLUGIN_DIR . 'assets/images/templates/' . $template['id'] . '.jpg')): ?>
                            <img src="<?php echo esc_url($template['preview']); ?>" alt="<?php echo esc_attr($template['name']); ?>">
                            <?php else: ?>
                            <div class="cbd-template-placeholder">
                                <span class="dashicons dashicons-layout"></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="cbd-template-info">
                            <h4><?php echo esc_html($template['name']); ?></h4>
                            <p><?php echo esc_html($template['description']); ?></p>
                            <div class="cbd-template-actions">
                                <button class="button cbd-preview-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php echo esc_html__('Vorschau', 'container-block-designer'); ?>
                                </button>
                                <button class="button button-primary cbd-import-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php echo esc_html__('Importieren', 'container-block-designer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<style>
/* Import/Export Styles */
.cbd-import-export-page {
    max-width: 1200px;
    margin: 20px 0;
}

.cbd-import-export-content {
    margin-top: 20px;
}

.cbd-export-options,
.cbd-export-settings,
.cbd-import-settings {
    margin: 20px 0;
}

.cbd-export-option,
.cbd-export-settings label,
.cbd-import-settings label {
    display: block;
    margin: 10px 0;
    font-size: 14px;
}

.cbd-export-option input,
.cbd-export-settings input,
.cbd-import-settings input {
    margin-right: 8px;
}

.cbd-block-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    margin: 15px 0;
}

.cbd-block-item {
    display: flex;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.cbd-block-item:last-child {
    border-bottom: none;
}

.cbd-block-name {
    flex: 1;
    font-weight: 500;
}

.cbd-block-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.cbd-export-actions,
.cbd-import-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.cbd-code-preview {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 400px;
}

.cbd-import-methods {
    display: grid;
    gap: 30px;
}

.cbd-import-method h4 {
    margin-bottom: 10px;
}

.cbd-validation-result {
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    margin-top: 10px;
}

.cbd-validation-result.success {
    background: #d4f4dd;
    border-left: 4px solid #00a32a;
}

.cbd-validation-result.error {
    background: #fef1f1;
    border-left: 4px solid #d63638;
}

/* Template Library */
.cbd-template-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.cbd-filter-btn {
    padding: 8px 16px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cbd-filter-btn:hover {
    background: #e0e0e0;
}

.cbd-filter-btn.active {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.cbd-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.cbd-template-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.cbd-template-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.cbd-template-preview {
    height: 200px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-template-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cbd-template-placeholder {
    font-size: 48px;
    color: #ccc;
}

.cbd-template-info {
    padding: 15px;
}

.cbd-template-info h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.cbd-template-info p {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 13px;
}

.cbd-template-actions {
    display: flex;
    gap: 10px;
}

.cbd-template-actions .button {
    flex: 1;
    text-align: center;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Export type selection
    $('input[name="export_type"]').on('change', function() {
        if ($(this).val() === 'selected') {
            $('#cbd-block-selector').slideDown();
        } else {
            $('#cbd-block-selector').slideUp();
        }
    });
    
    // Export preview
    $('#cbd-preview-export').on('click', function() {
        // Collect form data and show preview
        const formData = $('#cbd-export-form').serializeArray();
        
        // Make AJAX call to get preview
        $.post(ajaxurl, {
            action: 'cbd_preview_export',
            data: formData,
            nonce: '<?php echo wp_create_nonce('cbd_export_preview'); ?>'
        }, function(response) {
            if (response.success) {
                $('#cbd-export-preview').slideDown();
                $('#cbd-export-preview .cbd-code-preview').text(JSON.stringify(response.data, null, 2));
            }
        });
    });
    
    // Template filtering
    $('.cbd-filter-btn').on('click', function() {
        const category = $(this).data('category');
        
        $('.cbd-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        if (category === 'all') {
            $('.cbd-template-card').show();
        } else {
            $('.cbd-template-card').hide();
            $('.cbd-template-card[data-category="' + category + '"]').show();
        }
    });
});
</script>