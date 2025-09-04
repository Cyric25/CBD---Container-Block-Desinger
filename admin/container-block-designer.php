<?php
/**
 * Container Block Designer - Hauptseite der Admin-Oberfläche
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.3
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Lade alle gespeicherten Blöcke
global $wpdb;
$blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created_at DESC");

// Feature-Definitionen
$available_features = array(
    'icon' => array(
        'label' => __('Block-Icon', 'container-block-designer'),
        'description' => __('Zeigt ein Icon im Block-Header', 'container-block-designer'),
        'dashicon' => 'dashicons-star-filled'
    ),
    'collapse' => array(
        'label' => __('Ein-/Ausklappen', 'container-block-designer'),
        'description' => __('Ermöglicht das Ein- und Ausklappen des Blocks', 'container-block-designer'),
        'dashicon' => 'dashicons-arrow-down-alt2'
    ),
    'numbering' => array(
        'label' => __('Nummerierung', 'container-block-designer'),
        'description' => __('Automatische Nummerierung der Blöcke', 'container-block-designer'),
        'dashicon' => 'dashicons-editor-ol'
    ),
    'copyText' => array(
        'label' => __('Text kopieren', 'container-block-designer'),
        'description' => __('Button zum Kopieren des Block-Inhalts', 'container-block-designer'),
        'dashicon' => 'dashicons-admin-page'
    ),
    'screenshot' => array(
        'label' => __('Screenshot', 'container-block-designer'),
        'description' => __('Erstellt einen Screenshot des Blocks', 'container-block-designer'),
        'dashicon' => 'dashicons-camera'
    )
);
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Container Block Designer', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="page-title-action">
        <?php _e('Neuer Block', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php
    // Nachrichten anzeigen
    if (isset($_GET['message'])) {
        $message = '';
        $type = 'success';
        
        switch ($_GET['message']) {
            case 'block_created':
                $message = __('Block wurde erfolgreich erstellt!', 'container-block-designer');
                break;
            case 'block_updated':
                $message = __('Block wurde erfolgreich aktualisiert!', 'container-block-designer');
                break;
            case 'block_deleted':
                $message = __('Block wurde erfolgreich gelöscht!', 'container-block-designer');
                break;
            case 'status_changed':
                $message = __('Block-Status wurde geändert!', 'container-block-designer');
                break;
            case 'error':
                $message = __('Ein Fehler ist aufgetreten.', 'container-block-designer');
                $type = 'error';
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    ?>
    
    <!-- Hauptbereich mit Tabs -->
    <div class="cbd-admin-container">
        
        <!-- Tab-Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#blocks" class="nav-tab nav-tab-active" data-tab="blocks">
                <?php _e('Alle Blöcke', 'container-block-designer'); ?>
                <span class="count">(<?php echo count($blocks); ?>)</span>
            </a>
            <a href="#features" class="nav-tab" data-tab="features">
                <?php _e('Verfügbare Features', 'container-block-designer'); ?>
            </a>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <?php _e('Einstellungen', 'container-block-designer'); ?>
            </a>
        </h2>
        
        <!-- Tab: Alle Blöcke -->
        <div id="tab-blocks" class="cbd-tab-content active">
            
            <?php if (empty($blocks)): ?>
                <div class="cbd-empty-state">
                    <span class="dashicons dashicons-layout"></span>
                    <h2><?php _e('Keine Container-Blöcke gefunden', 'container-block-designer'); ?></h2>
                    <p><?php _e('Erstellen Sie Ihren ersten Container-Block, um loszulegen.', 'container-block-designer'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary button-large">
                        <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="cbd-blocks-grid">
                    <?php foreach ($blocks as $block): 
                        $config = json_decode($block->config, true) ?: array();
                        $styles = json_decode($block->styles, true) ?: array();
                        $features = json_decode($block->features, true) ?: array();
                        
                        // Erstelle eine kleine Vorschau
                        $preview_style = '';
                        if (!empty($styles['background']['color'])) {
                            $preview_style .= 'background-color: ' . $styles['background']['color'] . ';';
                        }
                        if (!empty($styles['border']['width']) && $styles['border']['width'] > 0) {
                            $preview_style .= ' border: ' . $styles['border']['width'] . 'px ';
                            $preview_style .= ($styles['border']['style'] ?: 'solid') . ' ';
                            $preview_style .= ($styles['border']['color'] ?: '#000') . ';';
                        }
                        if (!empty($styles['border']['radius'])) {
                            $preview_style .= ' border-radius: ' . $styles['border']['radius'] . 'px;';
                        }
                    ?>
                        <div class="cbd-block-card">
                            <div class="cbd-block-preview" style="<?php echo esc_attr($preview_style); ?>">
                                <div class="cbd-block-preview-content">
                                    <?php if (!empty($features['icon']['enabled'])): ?>
                                        <span class="dashicons dashicons-<?php echo esc_attr($features['icon']['value'] ?: 'star-filled'); ?>"></span>
                                    <?php endif; ?>
                                    <span><?php _e('Beispielinhalt', 'container-block-designer'); ?></span>
                                </div>
                            </div>
                            
                            <div class="cbd-block-info">
                                <h3><?php echo esc_html($block->title ?: $block->name); ?></h3>
                                
                                <?php if (!empty($block->description)): ?>
                                    <p class="cbd-block-description"><?php echo esc_html($block->description); ?></p>
                                <?php endif; ?>
                                
                                <div class="cbd-block-meta">
                                    <span class="cbd-block-name">
                                        <code><?php echo esc_html($block->name); ?></code>
                                    </span>
                                    
                                    <?php if ($block->status !== 'active'): ?>
                                        <span class="cbd-block-status cbd-status-<?php echo esc_attr($block->status); ?>">
                                            <?php echo esc_html($block->status); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="cbd-block-features">
                                    <?php 
                                    $active_features = array();
                                    foreach ($features as $key => $feature) {
                                        if (!empty($feature['enabled'])) {
                                            $active_features[] = $key;
                                        }
                                    }
                                    
                                    if (!empty($active_features)): ?>
                                        <span class="cbd-features-label"><?php _e('Features:', 'container-block-designer'); ?></span>
                                        <?php foreach ($active_features as $feature_key): 
                                            if (isset($available_features[$feature_key])): ?>
                                                <span class="cbd-feature-tag" title="<?php echo esc_attr($available_features[$feature_key]['description']); ?>">
                                                    <span class="<?php echo esc_attr($available_features[$feature_key]['dashicon']); ?>"></span>
                                                    <?php echo esc_html($available_features[$feature_key]['label']); ?>
                                                </span>
                                            <?php endif;
                                        endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="cbd-block-actions">
                                    <a href="<?php echo admin_url('admin.php?page=cbd-edit-block&id=' . $block->id); ?>" class="button button-small">
                                        <?php _e('Bearbeiten', 'container-block-designer'); ?>
                                    </a>
                                    
                                    <button class="button button-small cbd-duplicate-block" data-block-id="<?php echo esc_attr($block->id); ?>">
                                        <?php _e('Duplizieren', 'container-block-designer'); ?>
                                    </button>
                                    
                                    <?php if ($block->status === 'active'): ?>
                                        <button class="button button-small cbd-toggle-status" data-block-id="<?php echo esc_attr($block->id); ?>" data-status="inactive">
                                            <?php _e('Deaktivieren', 'container-block-designer'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="button button-small cbd-toggle-status" data-block-id="<?php echo esc_attr($block->id); ?>" data-status="active">
                                            <?php _e('Aktivieren', 'container-block-designer'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="button button-small cbd-delete-block" data-block-id="<?php echo esc_attr($block->id); ?>">
                                        <?php _e('Löschen', 'container-block-designer'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Tab: Features -->
        <div id="tab-features" class="cbd-tab-content">
            <div class="cbd-features-list">
                <?php foreach ($available_features as $feature_key => $feature): ?>
                    <div class="cbd-feature-card">
                        <div class="cbd-feature-icon">
                            <span class="<?php echo esc_attr($feature['dashicon']); ?>"></span>
                        </div>
                        <div class="cbd-feature-content">
                            <h3><?php echo esc_html($feature['label']); ?></h3>
                            <p><?php echo esc_html($feature['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Tab: Einstellungen -->
        <div id="tab-settings" class="cbd-tab-content">
            <form method="post" action="options.php">
                <?php settings_fields('cbd_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cbd_enable_debug">
                                <?php _e('Debug-Modus', 'container-block-designer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" id="cbd_enable_debug" name="cbd_enable_debug" value="1" <?php checked(get_option('cbd_enable_debug')); ?>>
                            <label for="cbd_enable_debug">
                                <?php _e('Debug-Ausgaben aktivieren', 'container-block-designer'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Zeigt zusätzliche Informationen für die Fehlersuche an.', 'container-block-designer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cbd_cache_styles">
                                <?php _e('Style-Caching', 'container-block-designer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" id="cbd_cache_styles" name="cbd_cache_styles" value="1" <?php checked(get_option('cbd_cache_styles', 1)); ?>>
                            <label for="cbd_cache_styles">
                                <?php _e('Block-Styles cachen für bessere Performance', 'container-block-designer'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Empfohlen für Produktivumgebungen.', 'container-block-designer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Wartung', 'container-block-designer'); ?></h2>
            
            <p>
                <button class="button cbd-clear-cache">
                    <?php _e('Style-Cache leeren', 'container-block-designer'); ?>
                </button>
                
                <button class="button cbd-regenerate-styles">
                    <?php _e('Styles neu generieren', 'container-block-designer'); ?>
                </button>
            </p>
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab-Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        const tab = $(this).data('tab');
        
        // Tabs umschalten
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Content umschalten
        $('.cbd-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // Block löschen
    $('.cbd-delete-block').on('click', function() {
        if (!confirm('<?php _e('Möchten Sie diesen Block wirklich löschen?', 'container-block-designer'); ?>')) {
            return;
        }
        
        const blockId = $(this).data('block-id');
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e('Löschen...', 'container-block-designer'); ?>');
        
        $.post(ajaxurl, {
            action: 'cbd_delete_block',
            block_id: blockId,
            nonce: '<?php echo wp_create_nonce('cbd_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $button.closest('.cbd-block-card').fadeOut(400, function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message || '<?php _e('Fehler beim Löschen', 'container-block-designer'); ?>');
                $button.prop('disabled', false).text('<?php _e('Löschen', 'container-block-designer'); ?>');
            }
        });
    });
    
    // Status ändern
    $('.cbd-toggle-status').on('click', function() {
        const blockId = $(this).data('block-id');
        const newStatus = $(this).data('status');
        const $button = $(this);
        
        $button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'cbd_toggle_block_status',
            block_id: blockId,
            nonce: '<?php echo wp_create_nonce('cbd_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('Fehler beim Ändern des Status', 'container-block-designer'); ?>');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Block duplizieren
    $('.cbd-duplicate-block').on('click', function() {
        const blockId = $(this).data('block-id');
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e('Duplizieren...', 'container-block-designer'); ?>');
        
        $.post(ajaxurl, {
            action: 'cbd_duplicate_block',
            block_id: blockId,
            nonce: '<?php echo wp_create_nonce('cbd_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('Fehler beim Duplizieren', 'container-block-designer'); ?>');
                $button.prop('disabled', false).text('<?php _e('Duplizieren', 'container-block-designer'); ?>');
            }
        });
    });
    
    // Cache leeren
    $('.cbd-clear-cache').on('click', function() {
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e('Cache wird geleert...', 'container-block-designer'); ?>');
        
        $.post(ajaxurl, {
            action: 'cbd_regenerate_styles',
            nonce: '<?php echo wp_create_nonce('cbd_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $button.text('<?php _e('Cache geleert!', 'container-block-designer'); ?>');
                setTimeout(function() {
                    $button.prop('disabled', false).text('<?php _e('Style-Cache leeren', 'container-block-designer'); ?>');
                }, 2000);
            } else {
                alert(response.data.message || '<?php _e('Fehler beim Leeren des Caches', 'container-block-designer'); ?>');
                $button.prop('disabled', false).text('<?php _e('Style-Cache leeren', 'container-block-designer'); ?>');
            }
        });
    });
});
</script>

<style>
.cbd-admin-wrap {
    margin-top: 20px;
}

.cbd-admin-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.cbd-tab-content {
    display: none;
}

.cbd-tab-content.active {
    display: block;
}

.cbd-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.cbd-empty-state .dashicons {
    font-size: 60px;
    width: 60px;
    height: 60px;
    color: #ccd0d4;
}

.cbd-blocks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.cbd-block-card {
    border: 1px solid #ccd0d4;
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
}

.cbd-block-preview {
    height: 100px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-block-preview-content {
    text-align: center;
}

.cbd-block-info {
    padding: 15px;
    border-top: 1px solid #e2e4e7;
}

.cbd-block-info h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.cbd-block-description {
    color: #646970;
    margin: 10px 0;
}

.cbd-block-meta {
    margin: 10px 0;
}

.cbd-block-name code {
    background: #f0f0f1;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 12px;
}

.cbd-block-features {
    margin: 10px 0;
}

.cbd-feature-tag {
    display: inline-block;
    background: #e3f2fd;
    color: #2196f3;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
}

.cbd-block-actions {
    margin-top: 15px;
    display: flex;
    gap: 5px;
}

.cbd-features-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.cbd-feature-card {
    display: flex;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    background: #fff;
}

.cbd-feature-icon {
    font-size: 40px;
    margin-right: 15px;
    color: #2271b1;
}

.cbd-feature-content h3 {
    margin: 0 0 10px 0;
}

.cbd-feature-content p {
    margin: 0;
    color: #646970;
}
</style>