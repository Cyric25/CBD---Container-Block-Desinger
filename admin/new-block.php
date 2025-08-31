<?php
/**
 * Container Block Designer - New Block Page
 * Version: 2.3.1 - Complete Version
 * 
 * @package ContainerBlockDesigner
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Default values for new block
$default_styles = [
    'padding' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
    'background' => ['color' => '#ffffff'],
    'text' => ['color' => '#333333', 'alignment' => 'left'],
    'border' => ['width' => 1, 'color' => '#e0e0e0', 'radius' => 4]
];

$default_features = [
    'icon' => ['enabled' => false, 'value' => 'dashicons-admin-generic'],
    'collapse' => ['enabled' => false, 'defaultState' => 'expanded'],
    'numbering' => ['enabled' => false, 'format' => 'numeric'],
    'copyText' => ['enabled' => false, 'buttonText' => 'Text kopieren'],
    'screenshot' => ['enabled' => false, 'buttonText' => 'Screenshot']
];
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Neuer Container Block', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php echo esc_html__('← Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Notices container -->
    <div id="cbd-notices"></div>
    
    <div class="cbd-edit-container">
        <div class="cbd-edit-main">
            <form id="cbd-block-form" method="post">
                <!-- Hidden fields -->
                <?php wp_nonce_field('cbd-admin', 'cbd_nonce'); ?>
                
                <!-- Basic Information -->
                <div class="cbd-card">
                    <h2><?php echo esc_html__('Grundeinstellungen', 'container-block-designer'); ?></h2>
                    
                    <div class="cbd-form-field">
                        <label for="block-name">
                            <?php echo esc_html__('Name', 'container-block-designer'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="block-name" name="name" required class="regular-text" placeholder="<?php echo esc_attr__('z.B. Hero Section', 'container-block-designer'); ?>">
                        <p class="description"><?php echo esc_html__('Der Name wird im Editor angezeigt', 'container-block-designer'); ?></p>
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-slug">
                            <?php echo esc_html__('Slug', 'container-block-designer'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="block-slug" name="slug" required class="regular-text" pattern="[a-z0-9-]+" placeholder="<?php echo esc_attr__('automatisch generiert', 'container-block-designer'); ?>">
                        <p class="description"><?php echo esc_html__('Eindeutige Kennung (nur Kleinbuchstaben, Zahlen und Bindestriche). Wird automatisch aus dem Namen generiert.', 'container-block-designer'); ?></p>
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-description">
                            <?php echo esc_html__('Beschreibung', 'container-block-designer'); ?>
                        </label>
                        <textarea id="block-description" name="description" rows="3" class="large-text" placeholder="<?php echo esc_attr__('Optionale Beschreibung für den Block', 'container-block-designer'); ?>"></textarea>
                        <p class="description"><?php echo esc_html__('Hilft bei der Identifizierung des Blocks', 'container-block-designer'); ?></p>
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-status">
                            <?php echo esc_html__('Status', 'container-block-designer'); ?>
                        </label>
                        <select id="block-status" name="status" class="regular-text">
                            <option value="active"><?php echo esc_html__('Aktiv', 'container-block-designer'); ?></option>
                            <option value="inactive"><?php echo esc_html__('Inaktiv', 'container-block-designer'); ?></option>
                            <option value="draft" selected><?php echo esc_html__('Entwurf', 'container-block-designer'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Neue Blöcke starten als Entwurf', 'container-block-designer'); ?></p>
                    </div>
                </div>
                
                <!-- Style Settings -->
                <div class="cbd-card">
                    <h2><?php echo esc_html__('Style-Einstellungen', 'container-block-designer'); ?></h2>
                    
                    <!-- Quick Templates -->
                    <div class="cbd-style-section">
                        <h3><?php echo esc_html__('Schnellvorlagen', 'container-block-designer'); ?></h3>
                        <div class="cbd-template-buttons">
                            <button type="button" class="button cbd-template-btn" data-template="minimal">
                                <?php echo esc_html__('Minimal', 'container-block-designer'); ?>
                            </button>
                            <button type="button" class="button cbd-template-btn" data-template="card">
                                <?php echo esc_html__('Karte', 'container-block-designer'); ?>
                            </button>
                            <button type="button" class="button cbd-template-btn" data-template="hero">
                                <?php echo esc_html__('Hero', 'container-block-designer'); ?>
                            </button>
                            <button type="button" class="button cbd-template-btn" data-template="notification">
                                <?php echo esc_html__('Hinweis', 'container-block-designer'); ?>
                            </button>
                            <button type="button" class="button cbd-template-btn" data-template="dark">
                                <?php echo esc_html__('Dunkel', 'container-block-designer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Padding -->
                    <div class="cbd-style-section">
                        <h3><?php echo esc_html__('Innenabstand (Padding)', 'container-block-designer'); ?></h3>
                        <div class="cbd-spacing-controls">
                            <div class="cbd-spacing-field">
                                <label><?php echo esc_html__('Oben', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="number" id="padding-top" value="<?php echo esc_attr($default_styles['padding']['top']); ?>" min="0" max="200">
                                    <span>px</span>
                                </div>
                            </div>
                            <div class="cbd-spacing-field">
                                <label><?php echo esc_html__('Rechts', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="number" id="padding-right" value="<?php echo esc_attr($default_styles['padding']['right']); ?>" min="0" max="200">
                                    <span>px</span>
                                </div>
                            </div>
                            <div class="cbd-spacing-field">
                                <label><?php echo esc_html__('Unten', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="number" id="padding-bottom" value="<?php echo esc_attr($default_styles['padding']['bottom']); ?>" min="0" max="200">
                                    <span>px</span>
                                </div>
                            </div>
                            <div class="cbd-spacing-field">
                                <label><?php echo esc_html__('Links', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="number" id="padding-left" value="<?php echo esc_attr($default_styles['padding']['left']); ?>" min="0" max="200">
                                    <span>px</span>
                                </div>
                            </div>
                        </div>
                        <div class="cbd-spacing-sync">
                            <button type="button" class="button button-small" id="cbd-sync-padding">
                                <span class="dashicons dashicons-admin-links"></span>
                                <?php echo esc_html__('Alle gleich', 'container-block-designer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Colors -->
                    <div class="cbd-style-section">
                        <h3><?php echo esc_html__('Farben', 'container-block-designer'); ?></h3>
                        <div class="cbd-color-controls">
                            <div class="cbd-color-field">
                                <label><?php echo esc_html__('Hintergrundfarbe', 'container-block-designer'); ?></label>
                                <div class="cbd-color-picker-wrapper">
                                    <input type="color" id="background-color" class="cbd-color-picker" value="<?php echo esc_attr($default_styles['background']['color']); ?>">
                                    <input type="text" id="background-color-text" class="cbd-color-text" value="<?php echo esc_attr($default_styles['background']['color']); ?>" pattern="^#[0-9A-Fa-f]{6}$">
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#ffffff" title="Weiß">⚪</button>
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#f8f9fa" title="Hellgrau">🔘</button>
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#212529" title="Dunkel">⚫</button>
                                </div>
                            </div>
                            <div class="cbd-color-field">
                                <label><?php echo esc_html__('Textfarbe', 'container-block-designer'); ?></label>
                                <div class="cbd-color-picker-wrapper">
                                    <input type="color" id="text-color" class="cbd-color-picker" value="<?php echo esc_attr($default_styles['text']['color']); ?>">
                                    <input type="text" id="text-color-text" class="cbd-color-text" value="<?php echo esc_attr($default_styles['text']['color']); ?>" pattern="^#[0-9A-Fa-f]{6}$">
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#333333" title="Dunkelgrau">⚫</button>
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#666666" title="Grau">🔘</button>
                                    <button type="button" class="button button-small cbd-color-preset" data-color="#ffffff" title="Weiß">⚪</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Text Alignment -->
                    <div class="cbd-style-section">
                        <h3><?php echo esc_html__('Text-Ausrichtung', 'container-block-designer'); ?></h3>
                        <div class="cbd-alignment-buttons">
                            <button type="button" class="button cbd-alignment-btn active" data-align="left" title="<?php echo esc_attr__('Links', 'container-block-designer'); ?>">
                                <span class="dashicons dashicons-editor-alignleft"></span>
                            </button>
                            <button type="button" class="button cbd-alignment-btn" data-align="center" title="<?php echo esc_attr__('Zentriert', 'container-block-designer'); ?>">
                                <span class="dashicons dashicons-editor-aligncenter"></span>
                            </button>
                            <button type="button" class="button cbd-alignment-btn" data-align="right" title="<?php echo esc_attr__('Rechts', 'container-block-designer'); ?>">
                                <span class="dashicons dashicons-editor-alignright"></span>
                            </button>
                            <button type="button" class="button cbd-alignment-btn" data-align="justify" title="<?php echo esc_attr__('Blocksatz', 'container-block-designer'); ?>">
                                <span class="dashicons dashicons-editor-justify"></span>
                            </button>
                        </div>
                        <input type="hidden" id="text-alignment" value="<?php echo esc_attr($default_styles['text']['alignment']); ?>">
                    </div>
                    
                    <!-- Border -->
                    <div class="cbd-style-section">
                        <h3><?php echo esc_html__('Rahmen', 'container-block-designer'); ?></h3>
                        <div class="cbd-border-controls">
                            <div class="cbd-border-field">
                                <label><?php echo esc_html__('Breite', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="range" id="border-width-range" value="<?php echo esc_attr($default_styles['border']['width']); ?>" min="0" max="10" class="cbd-range">
                                    <input type="number" id="border-width" value="<?php echo esc_attr($default_styles['border']['width']); ?>" min="0" max="20" class="cbd-range-value">
                                    <span>px</span>
                                </div>
                            </div>
                            <div class="cbd-border-field">
                                <label><?php echo esc_html__('Farbe', 'container-block-designer'); ?></label>
                                <div class="cbd-color-picker-wrapper">
                                    <input type="color" id="border-color" class="cbd-color-picker" value="<?php echo esc_attr($default_styles['border']['color']); ?>">
                                    <input type="text" id="border-color-text" class="cbd-color-text" value="<?php echo esc_attr($default_styles['border']['color']); ?>" pattern="^#[0-9A-Fa-f]{6}$">
                                </div>
                            </div>
                            <div class="cbd-border-field">
                                <label><?php echo esc_html__('Radius', 'container-block-designer'); ?></label>
                                <div class="cbd-input-group">
                                    <input type="range" id="border-radius-range" value="<?php echo esc_attr($default_styles['border']['radius']); ?>" min="0" max="50" class="cbd-range">
                                    <input type="number" id="border-radius" value="<?php echo esc_attr($default_styles['border']['radius']); ?>" min="0" max="50" class="cbd-range-value">
                                    <span>px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sidebar -->
        <div class="cbd-edit-sidebar">
            <!-- Live Preview -->
            <div class="cbd-card cbd-card-sticky">
                <h2><?php echo esc_html__('Live-Vorschau', 'container-block-designer'); ?></h2>
                <div class="cbd-preview-container">
                    <div class="cbd-preview-frame">
                        <div id="cbd-preview" class="cbd-container-preview">
                            <h3><?php echo esc_html__('Beispiel-Überschrift', 'container-block-designer'); ?></h3>
                            <p><?php echo esc_html__('Dies ist ein Beispieltext für die Vorschau. Hier können Sie sehen, wie Ihr Container-Block aussehen wird.', 'container-block-designer'); ?></p>
                            <p><?php echo esc_html__('Die Vorschau aktualisiert sich automatisch, wenn Sie die Einstellungen ändern.', 'container-block-designer'); ?></p>
                        </div>
                    </div>
                    <div class="cbd-preview-info">
                        <small><?php echo esc_html__('Die Vorschau zeigt den Block mit Ihren aktuellen Einstellungen', 'container-block-designer'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Quick Features -->
            <div class="cbd-card">
                <h2><?php echo esc_html__('Schnell-Features', 'container-block-designer'); ?></h2>
                <div class="cbd-quick-features">
                    <label class="cbd-quick-feature">
                        <input type="checkbox" id="quick-feature-icon">
                        <span><?php echo esc_html__('Block-Icon hinzufügen', 'container-block-designer'); ?></span>
                    </label>
                    <label class="cbd-quick-feature">
                        <input type="checkbox" id="quick-feature-collapse">
                        <span><?php echo esc_html__('Ein-/Ausklappbar machen', 'container-block-designer'); ?></span>
                    </label>
                    <label class="cbd-quick-feature">
                        <input type="checkbox" id="quick-feature-copy">
                        <span><?php echo esc_html__('Text-Kopieren Button', 'container-block-designer'); ?></span>
                    </label>
                </div>
                <p class="description">
                    <?php echo esc_html__('Features können nach dem Erstellen detailliert konfiguriert werden.', 'container-block-designer'); ?>
                </p>
            </div>
            
            <!-- Actions -->
            <div class="cbd-card">
                <h2><?php echo esc_html__('Aktionen', 'container-block-designer'); ?></h2>
                
                <div class="cbd-form-actions">
                    <button type="submit" form="cbd-block-form" id="cbd-save-block" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo esc_html__('Block erstellen', 'container-block-designer'); ?>
                    </button>
                    
                    <button type="button" id="cbd-save-and-edit" class="button button-large">
                        <span class="dashicons dashicons-edit"></span>
                        <?php echo esc_html__('Erstellen & Bearbeiten', 'container-block-designer'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button button-large">
                        <?php echo esc_html__('Abbrechen', 'container-block-designer'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Help -->
            <div class="cbd-card">
                <h2><?php echo esc_html__('Hilfe', 'container-block-designer'); ?></h2>
                <div class="cbd-help-content">
                    <p><strong><?php echo esc_html__('Tipps:', 'container-block-designer'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Der Slug wird automatisch aus dem Namen generiert', 'container-block-designer'); ?></li>
                        <li><?php echo esc_html__('Nutzen Sie die Schnellvorlagen für einen schnellen Start', 'container-block-designer'); ?></li>
                        <li><?php echo esc_html__('Die Live-Vorschau zeigt Änderungen sofort an', 'container-block-designer'); ?></li>
                        <li><?php echo esc_html__('Features können jederzeit nachträglich geändert werden', 'container-block-designer'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Template Buttons */
.cbd-template-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.cbd-template-btn {
    min-width: 80px;
}

/* Spacing Sync */
.cbd-spacing-sync {
    margin-top: 10px;
    text-align: center;
}

/* Color Presets */
.cbd-color-preset {
    width: 30px;
    height: 30px;
    padding: 0;
    font-size: 16px;
    line-height: 1;
}

/* Alignment Buttons */
.cbd-alignment-buttons {
    display: flex;
    gap: 5px;
}

.cbd-alignment-btn {
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-alignment-btn.active {
    background: #2271b1;
    color: white;
}

/* Range Inputs */
.cbd-range {
    flex: 1;
}

.cbd-range-value {
    width: 60px;
}

/* Quick Features */
.cbd-quick-features {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cbd-quick-feature {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.cbd-quick-feature input[type="checkbox"] {
    margin: 0;
}

/* Sticky Card */
.cbd-card-sticky {
    position: sticky;
    top: 32px;
}

/* Help Content */
.cbd-help-content ul {
    margin-left: 20px;
    list-style: disc;
}

.cbd-help-content li {
    margin: 5px 0;
}

/* Preview Info */
.cbd-preview-info {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Auto-generate slug from name
    $('#block-name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
        $('#block-slug').val(slug);
    });
    
    // Template presets
    $('.cbd-template-btn').on('click', function() {
        var template = $(this).data('template');
        applyTemplate(template);
    });
    
    function applyTemplate(template) {
        var templates = {
            minimal: {
                padding: {top: 10, right: 10, bottom: 10, left: 10},
                background: '#ffffff',
                text: '#333333',
                border: {width: 0, color: '#e0e0e0', radius: 0}
            },
            card: {
                padding: {top: 20, right: 20, bottom: 20, left: 20},
                background: '#ffffff',
                text: '#333333',
                border: {width: 1, color: '#e0e0e0', radius: 8}
            },
            hero: {
                padding: {top: 60, right: 30, bottom: 60, left: 30},
                background: '#f8f9fa',
                text: '#212529',
                border: {width: 0, color: '#dee2e6', radius: 0}
            },
            notification: {
                padding: {top: 15, right: 20, bottom: 15, left: 20},
                background: '#d4edda',
                text: '#155724',
                border: {width: 1, color: '#c3e6cb', radius: 4}
            },
            dark: {
                padding: {top: 30, right: 30, bottom: 30, left: 30},
                background: '#212529',
                text: '#ffffff',
                border: {width: 0, color: '#343a40', radius: 4}
            }
        };
        
        if (templates[template]) {
            var t = templates[template];
            $('#padding-top').val(t.padding.top);
            $('#padding-right').val(t.padding.right);
            $('#padding-bottom').val(t.padding.bottom);
            $('#padding-left').val(t.padding.left);
            $('#background-color, #background-color-text').val(t.background);
            $('#text-color, #text-color-text').val(t.text);
            $('#border-width, #border-width-range').val(t.border.width);
            $('#border-color, #border-color-text').val(t.border.color);
            $('#border-radius, #border-radius-range').val(t.border.radius);
            updatePreview();
        }
    }
    
    // Sync padding values
    $('#cbd-sync-padding').on('click', function() {
        var value = $('#padding-top').val() || 20;
        $('#padding-top, #padding-right, #padding-bottom, #padding-left').val(value);
        updatePreview();
    });
    
    // Color presets
    $('.cbd-color-preset').on('click', function() {
        var color = $(this).data('color');
        var $field = $(this).closest('.cbd-color-field');
        $field.find('.cbd-color-picker').val(color);
        $field.find('.cbd-color-text').val(color);
        updatePreview();
    });
    
    // Alignment buttons
    $('.cbd-alignment-btn').on('click', function() {
        $('.cbd-alignment-btn').removeClass('active');
        $(this).addClass('active');
        $('#text-alignment').val($(this).data('align'));
        updatePreview();
    });
    
    // Sync range inputs
    $('#border-width-range').on('input', function() {
        $('#border-width').val($(this).val());
        updatePreview();
    });
    
    $('#border-width').on('input', function() {
        $('#border-width-range').val($(this).val());
        updatePreview();
    });
    
    $('#border-radius-range').on('input', function() {
        $('#border-radius').val($(this).val());
        updatePreview();
    });
    
    $('#border-radius').on('input', function() {
        $('#border-radius-range').val($(this).val());
        updatePreview();
    });
    
    // Live preview updates
    function updatePreview() {
        var preview = $('#cbd-preview');
        
        // Update padding
        var paddingTop = $('#padding-top').val() || 20;
        var paddingRight = $('#padding-right').val() || 20;
        var paddingBottom = $('#padding-bottom').val() || 20;
        var paddingLeft = $('#padding-left').val() || 20;
        preview.css('padding', paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px');
        
        // Update colors
        preview.css('background-color', $('#background-color').val());
        preview.css('color', $('#text-color').val());
        
        // Update text alignment
        preview.css('text-align', $('#text-alignment').val());
        
        // Update border
        var borderWidth = $('#border-width').val() || 0;
        if (borderWidth > 0) {
            preview.css('border', borderWidth + 'px solid ' + $('#border-color').val());
        } else {
            preview.css('border', 'none');
        }
        preview.css('border-radius', $('#border-radius').val() + 'px');
    }
    
    // Bind events for live preview
    $('#padding-top, #padding-right, #padding-bottom, #padding-left').on('input', updatePreview);
    $('#background-color, #text-color, #border-color').on('input', updatePreview);
    $('#background-color-text, #text-color-text, #border-color-text').on('input', function() {
        var $picker = $(this).siblings('.cbd-color-picker');
        $picker.val($(this).val());
        updatePreview();
    });
    
    // Sync color pickers with text inputs
    $('.cbd-color-picker').on('input', function() {
        $(this).siblings('.cbd-color-text').val($(this).val());
    });
    
    // Initial preview
    updatePreview();
    
    // Form submission
    $('#cbd-block-form').on('submit', function(e) {
        e.preventDefault();
        saveBlock(false);
    });
    
    // Save and edit
    $('#cbd-save-and-edit').on('click', function() {
        saveBlock(true);
    });
    
    function saveBlock(andEdit) {
        // Collect styles
        var styles = {
            padding: {
                top: $('#padding-top').val() || 20,
                right: $('#padding-right').val() || 20,
                bottom: $('#padding-bottom').val() || 20,
                left: $('#padding-left').val() || 20
            },
            background: {
                color: $('#background-color').val() || '#ffffff'
            },
            text: {
                color: $('#text-color').val() || '#333333',
                alignment: $('#text-alignment').val() || 'left'
            },
            border: {
                width: $('#border-width').val() || 0,
                color: $('#border-color').val() || '#dddddd',
                radius: $('#border-radius').val() || 0
            }
        };
        
        // Save via AJAX
        if (typeof CBDAdmin !== 'undefined' && CBDAdmin.saveBlock) {
            CBDAdmin.saveBlock(
                null, // No ID for new block
                $('#block-name').val(),
                $('#block-slug').val(),
                $('#block-description').val(),
                $('#block-status').val(),
                styles,
                andEdit // Flag for redirect to edit page
            );
        }
    }
});
</script>
