<?php
/**
 * Container Block Designer - Edit Block Page
 * Version: 2.2.2 - WITH INLINE FIX
 * 
 * @package ContainerBlockDesigner
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get block ID
$block_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$block_id) {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Fehler', 'container-block-designer'); ?></h1>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Ungültige Block-ID.', 'container-block-designer'); ?></p>
        </div>
        <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button">
            <?php echo esc_html__('Zurück zur Übersicht', 'container-block-designer'); ?>
        </a>
    </div>
    <?php
    return;
}

// Get block data
global $wpdb;
$block = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
    $block_id
));

if (!$block) {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Fehler', 'container-block-designer'); ?></h1>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Block nicht gefunden.', 'container-block-designer'); ?></p>
        </div>
        <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button">
            <?php echo esc_html__('Zurück zur Übersicht', 'container-block-designer'); ?>
        </a>
    </div>
    <?php
    return;
}

// Parse config and features
$config = json_decode($block->config, true) ?: [];
$styles = isset($config['styles']) ? $config['styles'] : [];
$features = json_decode($block->features, true) ?: [];
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Block bearbeiten:', 'container-block-designer'); ?> 
        <?php echo esc_html($block->name); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php echo esc_html__('← Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div class="cbd-edit-container">
        <div class="cbd-edit-main">
            <form id="cbd-block-form" method="post">
                <!-- Hidden fields -->
                <input type="hidden" id="block-id" name="block_id" value="<?php echo esc_attr($block_id); ?>">
                <input type="hidden" id="cbd-nonce" name="cbd_nonce" value="<?php echo wp_create_nonce('cbd-admin'); ?>">
                <?php wp_nonce_field('cbd-admin', 'cbd_nonce'); ?>
                
                <!-- Basic Information -->
                <div class="cbd-card">
                    <h2><?php echo esc_html__('Grundeinstellungen', 'container-block-designer'); ?></h2>
                    
                    <div class="cbd-form-field">
                        <label for="block-name">
                            <?php echo esc_html__('Name', 'container-block-designer'); ?> *
                        </label>
                        <input type="text" id="block-name" name="name" value="<?php echo esc_attr($block->name); ?>" required class="regular-text">
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-slug">
                            <?php echo esc_html__('Slug', 'container-block-designer'); ?>
                        </label>
                        <input type="text" id="block-slug" name="slug" value="<?php echo esc_attr($block->slug); ?>" class="regular-text">
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-description">
                            <?php echo esc_html__('Beschreibung', 'container-block-designer'); ?>
                        </label>
                        <textarea id="block-description" name="description" rows="3" class="large-text"><?php echo esc_textarea($block->description); ?></textarea>
                    </div>
                    
                    <div class="cbd-form-field">
                        <label for="block-status">
                            <input type="checkbox" id="block-status" name="status" value="1" <?php checked($block->status, 1); ?>>
                            <?php echo esc_html__('Block aktiviert', 'container-block-designer'); ?>
                        </label>
                    </div>
                </div>
                
                <!-- Style Settings -->
                <div class="cbd-card">
                    <h2><?php echo esc_html__('Style-Einstellungen', 'container-block-designer'); ?></h2>
                    
                    <div class="cbd-form-grid">
                        <div class="cbd-form-field">
                            <label for="background-color"><?php echo esc_html__('Hintergrundfarbe', 'container-block-designer'); ?></label>
                            <input type="text" id="background-color" name="background-color" 
                                   value="<?php echo esc_attr($styles['backgroundColor'] ?? '#ffffff'); ?>" 
                                   class="cbd-color-picker">
                        </div>
                        
                        <div class="cbd-form-field">
                            <label for="text-color"><?php echo esc_html__('Textfarbe', 'container-block-designer'); ?></label>
                            <input type="text" id="text-color" name="text-color" 
                                   value="<?php echo esc_attr($styles['color'] ?? '#000000'); ?>" 
                                   class="cbd-color-picker">
                        </div>
                        
                        <div class="cbd-form-field">
                            <label for="padding"><?php echo esc_html__('Innenabstand', 'container-block-designer'); ?></label>
                            <input type="number" id="padding" name="padding" 
                                   value="<?php echo esc_attr($styles['padding'] ?? 20); ?>" 
                                   min="0" class="small-text">
                            <span class="description">px</span>
                        </div>
                        
                        <div class="cbd-form-field">
                            <label for="border-radius"><?php echo esc_html__('Eckenradius', 'container-block-designer'); ?></label>
                            <input type="number" id="border-radius" name="border-radius" 
                                   value="<?php echo esc_attr($styles['borderRadius'] ?? 0); ?>" 
                                   min="0" class="small-text">
                            <span class="description">px</span>
                        </div>
                    </div>
                </div>
                
                <!-- Features -->
                <div class="cbd-card">
                    <div class="cbd-card-header">
                        <h2><?php echo esc_html__('Erweiterte Features', 'container-block-designer'); ?></h2>
                        <button type="button" class="button cbd-features-btn" 
                                data-block-id="<?php echo esc_attr($block_id); ?>" 
                                data-name="<?php echo esc_attr($block->name); ?>"
                                id="cbd-open-features-btn">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php echo esc_html__('Features konfigurieren', 'container-block-designer'); ?>
                        </button>
                    </div>
                    
                    <div class="cbd-features-info">
                        <?php if (!empty($features) && is_array($features)): ?>
                            <div class="cbd-active-features">
                                <p><strong><?php echo esc_html__('Aktive Features:', 'container-block-designer'); ?></strong></p>
                                <ul>
                                    <?php if (!empty($features['customIcon']['enabled'])): ?>
                                        <li>✅ Icon: <?php echo esc_html($features['customIcon']['icon'] ?? 'dashicons-admin-generic'); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['collapse']['enabled'])): ?>
                                        <li>✅ Ein-/Ausklappbar (Standard: <?php echo esc_html($features['collapse']['defaultState'] ?? 'expanded'); ?>)</li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['numbering']['enabled'])): ?>
                                        <li>✅ Nummerierung (Format: <?php echo esc_html($features['numbering']['format'] ?? 'numeric'); ?>)</li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['copyText']['enabled'])): ?>
                                        <li>✅ Text kopieren Button</li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['screenshot']['enabled'])): ?>
                                        <li>✅ Screenshot Button</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p class="cbd-no-features">
                                <?php echo esc_html__('Keine Features aktiviert', 'container-block-designer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="cbd-form-actions">
                    <button type="submit" id="cbd-save-block" name="cbd-save-block" class="button button-primary button-large">
                        <?php echo esc_html__('Änderungen speichern', 'container-block-designer'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button button-large">
                        <?php echo esc_html__('Abbrechen', 'container-block-designer'); ?>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Preview Sidebar -->
        <div class="cbd-edit-sidebar">
            <div class="cbd-card">
                <h3><?php echo esc_html__('Vorschau', 'container-block-designer'); ?></h3>
                <div id="cbd-preview-wrapper">
                    <div id="cbd-preview-content">
                        <h3><?php echo esc_html__('Container Block', 'container-block-designer'); ?></h3>
                        <p><?php echo esc_html__('Dies ist eine Vorschau Ihres Container Blocks.', 'container-block-designer'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inline JavaScript Fix für Features Button -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('🚀 CBD Edit Block Page - Initializing inline fix...');
    
    // Debug: Check if button exists
    const $button = $('.cbd-features-btn, #cbd-open-features-btn');
    console.log('Features button found:', $button.length > 0 ? 'Yes' : 'No');
    
    // Ensure ajaxurl is available
    if (typeof ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Create minimal features modal if CBDFeatures not loaded
    function createMinimalFeaturesModal() {
        if ($('#cbd-features-modal').length === 0) {
            const modalHTML = `
                <div id="cbd-features-modal" class="cbd-modal" style="display: none;">
                    <div class="cbd-modal-backdrop"></div>
                    <div class="cbd-modal-content">
                        <div class="cbd-modal-header">
                            <h2 id="cbd-modal-title">Block Features konfigurieren</h2>
                            <button type="button" class="cbd-modal-close">&times;</button>
                        </div>
                        
                        <div class="cbd-modal-body">
                            <input type="hidden" id="features-block-id" value="">
                            
                            <form id="cbd-features-form">
                                <!-- Feature 1: Custom Icon -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-icon-enabled">
                                        <span>Custom Icon</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-icon-settings" style="display: none;">
                                        <label>Icon auswählen:</label>
                                        <input type="text" id="block-icon-value" placeholder="dashicons-admin-generic" class="regular-text">
                                    </div>
                                </div>
                                
                                <!-- Feature 2: Collapsible -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-collapse-enabled">
                                        <span>Ein-/Ausklappbar</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-collapse-settings" style="display: none;">
                                        <label>Standardzustand:</label>
                                        <select id="collapse-default-state">
                                            <option value="expanded">Ausgeklappt</option>
                                            <option value="collapsed">Eingeklappt</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Other features... -->
                            </form>
                        </div>
                        
                        <div class="cbd-modal-footer">
                            <button type="button" id="cbd-save-features" class="button button-primary">Speichern</button>
                            <button type="button" id="cbd-modal-cancel" class="button">Abbrechen</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHTML);
        }
    }
    
    // Direct button handler - GUARANTEED TO WORK
    $(document).on('click', '.cbd-features-btn, #cbd-open-features-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('✅ Features button clicked!');
        
        const $btn = $(this);
        const blockId = $btn.data('block-id') || $('#block-id').val();
        const blockName = $btn.data('name') || $('#block-name').val();
        
        console.log('Block ID:', blockId);
        console.log('Block Name:', blockName);
        
        // Try to use CBDFeatures if available
        if (typeof CBDFeatures !== 'undefined' && CBDFeatures.openFeaturesModal) {
            console.log('Using CBDFeatures.openFeaturesModal');
            CBDFeatures.openFeaturesModal(blockId, blockName);
        } else {
            console.log('CBDFeatures not available, using fallback');
            
            // Create and show minimal modal
            createMinimalFeaturesModal();
            
            $('#features-block-id').val(blockId);
            $('#cbd-modal-title').text('Features für "' + blockName + '" konfigurieren');
            $('#cbd-features-modal').fadeIn(200);
            $('body').addClass('cbd-modal-open');
            
            // Load features via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_get_block_features',
                    block_id: blockId,
                    nonce: '<?php echo wp_create_nonce('cbd-admin'); ?>'
                },
                success: function(response) {
                    console.log('Features loaded:', response);
                    if (response.success && response.data) {
                        // Populate form with features data
                        const features = response.data;
                        if (features.customIcon) {
                            $('#feature-icon-enabled').prop('checked', features.customIcon.enabled);
                            $('#block-icon-value').val(features.customIcon.icon || 'dashicons-admin-generic');
                        }
                        if (features.collapse) {
                            $('#feature-collapse-enabled').prop('checked', features.collapse.enabled);
                            $('#collapse-default-state').val(features.collapse.defaultState || 'expanded');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading features:', error);
                }
            });
        }
    });
    
    // Modal close handlers
    $(document).on('click', '.cbd-modal-close, .cbd-modal-backdrop, #cbd-modal-cancel', function() {
        $('#cbd-features-modal').fadeOut(200);
        $('body').removeClass('cbd-modal-open');
    });
    
    // Feature toggle handlers
    $(document).on('change', '#cbd-features-form input[type="checkbox"]', function() {
        const $settings = $(this).closest('.cbd-feature-item').find('.cbd-feature-settings');
        if ($(this).prop('checked')) {
            $settings.slideDown(200);
        } else {
            $settings.slideUp(200);
        }
    });
    
    // Save features handler
    $(document).on('click', '#cbd-save-features', function() {
        const blockId = $('#features-block-id').val();
        
        const featuresData = {
            customIcon: {
                enabled: $('#feature-icon-enabled').is(':checked'),
                icon: $('#block-icon-value').val() || 'dashicons-admin-generic'
            },
            collapse: {
                enabled: $('#feature-collapse-enabled').is(':checked'),
                defaultState: $('#collapse-default-state').val() || 'expanded'
            }
            // Add other features as needed
        };
        
        console.log('Saving features:', featuresData);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cbd_save_block_features',
                block_id: blockId,
                features: JSON.stringify(featuresData),
                nonce: '<?php echo wp_create_nonce('cbd-admin'); ?>'
            },
            success: function(response) {
                console.log('Features saved:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Save error:', error);
                alert('Fehler beim Speichern der Features.');
            }
        });
    });
    
    console.log('✅ Inline fix initialized - Features button should work now!');
});
</script>

<!-- Minimal CSS for modal -->
<style>
.cbd-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}
.cbd-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}
.cbd-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 4px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.cbd-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cbd-modal-header h2 {
    margin: 0;
}
.cbd-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
}
.cbd-modal-body {
    padding: 20px;
}
.cbd-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.cbd-feature-item {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.cbd-feature-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
}
.cbd-feature-toggle input {
    margin-right: 10px;
}
.cbd-feature-settings {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}
body.cbd-modal-open {
    overflow: hidden;
}
</style>