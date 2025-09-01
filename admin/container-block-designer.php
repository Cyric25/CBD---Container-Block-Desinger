<?php
/**
 * Container Block Designer - Hauptseite der Admin-Oberfläche
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.1
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Lade alle gespeicherten Blöcke
global $wpdb;
$blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created DESC");

// WICHTIG: Verarbeite Feature-Toggle Aktionen VOR jeglicher HTML-Ausgabe
if (isset($_POST['toggle_feature']) && isset($_POST['block_id']) && isset($_POST['feature_key'])) {
    // Nonce-Prüfung - Prüfe ob Nonce existiert bevor Verifikation
    if (!isset($_POST['cbd_toggle_feature_nonce']) || !wp_verify_nonce($_POST['cbd_toggle_feature_nonce'], 'cbd_toggle_feature')) {
        wp_die('Sicherheitsprüfung fehlgeschlagen');
    }
    
    $block_id = intval($_POST['block_id']);
    $feature_key = sanitize_text_field($_POST['feature_key']);
    
    // Hole aktuellen Block
    $block = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $block_id
    ));
    
    if ($block) {
        $features = json_decode($block->features, true) ?: array();
        
        // Toggle Feature Status
        if (!isset($features[$feature_key])) {
            $features[$feature_key] = array('enabled' => true);
        } else {
            $features[$feature_key]['enabled'] = !$features[$feature_key]['enabled'];
        }
        
        // Speichere Änderungen
        $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'features' => json_encode($features),
                'updated' => current_time('mysql')
            ),
            array('id' => $block_id)
        );
        
        // Redirect mit Message
        $redirect_url = admin_url('admin.php?page=container-block-designer&cbd_message=feature_toggled');
        wp_safe_redirect($redirect_url);
        exit;
    }
}

// Verarbeite Block-Löschung (MUSS VOR HTML-Output sein!)
if (isset($_GET['delete_block']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'cbd_delete_block')) {
        $block_id = intval($_GET['delete_block']);
        
        $result = $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => $block_id),
            array('%d')
        );
        
        if ($result) {
            $redirect_url = admin_url('admin.php?page=container-block-designer&cbd_message=deleted');
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

// Verarbeite Status-Toggle (Aktiv/Inaktiv) - MUSS VOR HTML-Output sein!
if (isset($_GET['toggle_status']) && isset($_GET['block_id']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'cbd_toggle_status')) {
        $block_id = intval($_GET['block_id']);
        
        // Hole aktuellen Status
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if ($current_status) {
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            
            $wpdb->update(
                CBD_TABLE_BLOCKS,
                array(
                    'status' => $new_status,
                    'updated' => current_time('mysql')
                ),
                array('id' => $block_id)
            );
            
            $redirect_url = admin_url('admin.php?page=container-block-designer&cbd_message=status_changed');
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

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
    if (isset($_GET['cbd_message'])) {
        $message = '';
        switch ($_GET['cbd_message']) {
            case 'saved':
                $message = __('Block wurde erfolgreich gespeichert!', 'container-block-designer');
                break;
            case 'deleted':
                $message = __('Block wurde erfolgreich gelöscht!', 'container-block-designer');
                break;
            case 'status_changed':
                $message = __('Block-Status wurde geändert!', 'container-block-designer');
                break;
            case 'feature_toggled':
                $message = __('Feature wurde aktualisiert!', 'container-block-designer');
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    ?>
    
    <div class="cbd-admin-content">
        <?php if (empty($blocks)) : ?>
            <div class="cbd-empty-state">
                <span class="dashicons dashicons-layout"></span>
                <h2><?php _e('Keine Blöcke vorhanden', 'container-block-designer'); ?></h2>
                <p><?php _e('Erstellen Sie Ihren ersten Container-Block, um loszulegen.', 'container-block-designer'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                    <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="cbd-blocks-grid">
                <?php foreach ($blocks as $block) : 
                    $features = !empty($block->features) ? json_decode($block->features, true) : array();
                    $styles = !empty($block->styles) ? json_decode($block->styles, true) : array();
                    $active_features = array_filter($features, function($f) { 
                        return isset($f['enabled']) && $f['enabled']; 
                    });
                ?>
                    <div class="cbd-block-card <?php echo $block->status === 'inactive' ? 'cbd-inactive' : ''; ?>">
                        <div class="cbd-block-header">
                            <h3><?php echo esc_html($block->title); ?></h3>
                            <div class="cbd-block-status">
                                <span class="cbd-status-badge cbd-status-<?php echo esc_attr($block->status); ?>">
                                    <?php echo $block->status === 'active' ? __('Aktiv', 'container-block-designer') : __('Inaktiv', 'container-block-designer'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($block->description) : ?>
                            <p class="cbd-block-description"><?php echo esc_html($block->description); ?></p>
                        <?php endif; ?>
                        
                        <!-- Features-Bereich -->
                        <div class="cbd-block-features">
                            <h4><?php _e('Features', 'container-block-designer'); ?></h4>
                            <div class="cbd-features-grid">
                                <?php foreach ($available_features as $key => $feature_info) : 
                                    $is_enabled = isset($features[$key]['enabled']) && $features[$key]['enabled'];
                                ?>
                                    <div class="cbd-feature-item <?php echo $is_enabled ? 'cbd-feature-active' : 'cbd-feature-inactive'; ?>">
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('cbd_toggle_feature'); ?>
                                            <input type="hidden" name="toggle_feature" value="1">
                                            <input type="hidden" name="block_id" value="<?php echo $block->id; ?>">
                                            <input type="hidden" name="feature_key" value="<?php echo esc_attr($key); ?>">
                                            
                                            <button type="submit" class="cbd-feature-toggle" title="<?php echo esc_attr($feature_info['description']); ?>">
                                                <span class="dashicons <?php echo esc_attr($feature_info['dashicon']); ?>"></span>
                                                <span class="cbd-feature-label"><?php echo esc_html($feature_info['label']); ?></span>
                                                <span class="cbd-feature-status">
                                                    <?php if ($is_enabled) : ?>
                                                        <span class="dashicons dashicons-yes"></span>
                                                    <?php else : ?>
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                    <?php endif; ?>
                                                </span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (!empty($active_features)) : ?>
                                <div class="cbd-active-features-summary">
                                    <strong><?php _e('Aktive Features:', 'container-block-designer'); ?></strong>
                                    <?php 
                                    $active_names = array();
                                    foreach ($active_features as $key => $feature) {
                                        if (isset($available_features[$key])) {
                                            $active_names[] = $available_features[$key]['label'];
                                        }
                                    }
                                    echo esc_html(implode(', ', $active_names));
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Vorschau-Bereich -->
                        <div class="cbd-block-preview">
                            <div class="cbd-preview-box" style="
                                padding: <?php echo intval($styles['padding']['top'] ?? 20); ?>px <?php echo intval($styles['padding']['right'] ?? 20); ?>px <?php echo intval($styles['padding']['bottom'] ?? 20); ?>px <?php echo intval($styles['padding']['left'] ?? 20); ?>px;
                                background-color: <?php echo esc_attr($styles['background']['color'] ?? '#ffffff'); ?>;
                                border: <?php echo intval($styles['border']['width'] ?? 1); ?>px <?php echo esc_attr($styles['border']['style'] ?? 'solid'); ?> <?php echo esc_attr($styles['border']['color'] ?? '#e0e0e0'); ?>;
                                border-radius: <?php echo intval($styles['border']['radius'] ?? 4); ?>px;
                                color: <?php echo esc_attr($styles['text']['color'] ?? '#333333'); ?>;
                            ">
                                <small><?php _e('Stil-Vorschau', 'container-block-designer'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Aktions-Buttons -->
                        <div class="cbd-block-actions">
                            <a href="<?php echo admin_url('admin.php?page=cbd-new-block&block_id=' . $block->id); ?>" class="button">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Bearbeiten', 'container-block-designer'); ?>
                            </a>
                            
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=container-block-designer&toggle_status=1&block_id=' . $block->id), 'cbd_toggle_status'); ?>" 
                               class="button <?php echo $block->status === 'active' ? '' : 'button-primary'; ?>">
                                <span class="dashicons dashicons-<?php echo $block->status === 'active' ? 'pause' : 'controls-play'; ?>"></span>
                                <?php echo $block->status === 'active' ? __('Deaktivieren', 'container-block-designer') : __('Aktivieren', 'container-block-designer'); ?>
                            </a>
                            
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=container-block-designer&delete_block=' . $block->id), 'cbd_delete_block'); ?>" 
                               class="button button-link-delete"
                               onclick="return confirm('<?php _e('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Löschen', 'container-block-designer'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Zusätzliche Styles für die Feature-Verwaltung */
.cbd-blocks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.cbd-block-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    transition: all 0.2s;
}

.cbd-block-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cbd-block-card.cbd-inactive {
    opacity: 0.7;
    background: #f9f9f9;
}

.cbd-block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.cbd-block-header h3 {
    margin: 0;
    font-size: 18px;
}

.cbd-status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.cbd-status-active {
    background: #d4f4dd;
    color: #00a32a;
}

.cbd-status-inactive {
    background: #f4f4f4;
    color: #757575;
}

.cbd-block-description {
    color: #666;
    margin: 10px 0;
    font-size: 14px;
}

.cbd-block-features {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.cbd-block-features h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
}

.cbd-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
    margin-bottom: 10px;
}

.cbd-feature-item {
    position: relative;
}

.cbd-feature-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 6px 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
}

.cbd-feature-toggle:hover {
    background: #f0f0f1;
    border-color: #0073aa;
}

.cbd-feature-active .cbd-feature-toggle {
    background: #e8f5e9;
    border-color: #4caf50;
}

.cbd-feature-toggle .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin-right: 4px;
}

.cbd-feature-label {
    flex: 1;
    text-align: left;
}

.cbd-feature-status .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.cbd-feature-status .dashicons-yes {
    color: #4caf50;
}

.cbd-feature-status .dashicons-no-alt {
    color: #999;
}

.cbd-active-features-summary {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e0e0e0;
    font-size: 13px;
    color: #555;
}

.cbd-block-preview {
    margin: 15px 0;
}

.cbd-preview-box {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-preview-box small {
    opacity: 0.5;
    font-style: italic;
}

.cbd-block-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.cbd-block-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.cbd-block-actions .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.cbd-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.cbd-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ddd;
}

.cbd-empty-state h2 {
    margin: 20px 0 10px;
    color: #666;
}

.cbd-empty-state p {
    color: #999;
    margin-bottom: 20px;
}
</style>