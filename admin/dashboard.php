<?php
/**
 * Container Block Designer - Admin Hauptseite
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// WICHTIG: Verwende created_at statt created!
$blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created_at DESC");

// Echte Features aus der Datenbank verwenden
$available_features = array(
    'icon' => array(
        'label' => __('Icon', 'container-block-designer'),
        'description' => __('Icon am Anfang des Blocks anzeigen', 'container-block-designer'),
        'dashicon' => 'dashicons-star-filled'
    ),
    'collapse' => array(
        'label' => __('Klappbar', 'container-block-designer'),
        'description' => __('Block kann ein- und ausgeklappt werden', 'container-block-designer'),
        'dashicon' => 'dashicons-arrow-up-alt2'
    ),
    'numbering' => array(
        'label' => __('Nummerierung', 'container-block-designer'),
        'description' => __('Automatische Nummerierung der Blocks', 'container-block-designer'),
        'dashicon' => 'dashicons-editor-ol'
    ),
    'copyText' => array(
        'label' => __('Text kopieren', 'container-block-designer'),
        'description' => __('Button zum Kopieren des Textes', 'container-block-designer'),
        'dashicon' => 'dashicons-clipboard'
    ),
    'screenshot' => array(
        'label' => __('Screenshot', 'container-block-designer'),
        'description' => __('Screenshot-Funktion für den Block', 'container-block-designer'),
        'dashicon' => 'dashicons-camera'
    )
);

// Helper-Funktion um Farben aufzuhellen
function lighten_color($hex, $percent) {
    // Entferne # falls vorhanden
    $hex = ltrim($hex, '#');
    
    // Konvertiere zu RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Helle auf
    $r = min(255, $r + ($r * $percent));
    $g = min(255, $g + ($g * $percent));
    $b = min(255, $b + ($b * $percent));
    
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}

?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Container Block Designer', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="page-title-action">
        <?php _e('Neuer Block', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php if (empty($blocks)) : ?>
        <div class="cbd-empty-state">
            <div class="cbd-empty-state-icon">
                <span class="dashicons dashicons-layout"></span>
            </div>
            <h2><?php _e('Keine Container-Blöcke vorhanden', 'container-block-designer'); ?></h2>
            <p><?php _e('Erstellen Sie Ihren ersten Container-Block, um loszulegen.', 'container-block-designer'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary button-hero">
                <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="cbd-blocks-grid">
            <?php foreach ($blocks as $block) : 
                $features = !empty($block->features) ? json_decode($block->features, true) : array();
                $styles = !empty($block->styles) ? json_decode($block->styles, true) : array();
                $config = !empty($block->config) ? json_decode($block->config, true) : array();
                
                // Verwende 'title' für Anzeige, 'name' für interne Referenz
                $display_name = !empty($block->title) ? $block->title : $block->name;
                
                // Dynamische Styles basierend auf Block-Konfiguration
                $card_styles = '';
                if (!empty($styles)) {
                    $bg_color = $styles['background']['color'] ?? '#ffffff';
                    $text_color = $styles['text']['color'] ?? '#333333';
                    $border_width = $styles['border']['width'] ?? 1;
                    $border_color = $styles['border']['color'] ?? '#e0e0e0';
                    $border_style = $styles['border']['style'] ?? 'solid';
                    $border_radius = $styles['border']['radius'] ?? 4;
                    
                    $card_styles = sprintf(
                        'background: linear-gradient(135deg, %s 0%%, %s 100%%); color: %s; border: %dpx %s %s; border-radius: %dpx;',
                        $bg_color,
                        lighten_color($bg_color, 0.05),
                        $text_color,
                        $border_width,
                        $border_style,
                        $border_color,
                        $border_radius
                    );
                }
            ?>
                <div class="cbd-block-card <?php echo $block->status !== 'active' ? 'cbd-inactive' : ''; ?>" 
                     style="<?php echo esc_attr($card_styles); ?>"
                     data-block-name="<?php echo esc_attr($block->name); ?>">
                    <div class="cbd-block-header">
                        <h3><?php echo esc_html($display_name); ?></h3>
                        <div class="cbd-block-actions">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('cbd_toggle_status'); ?>
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="block_id" value="<?php echo $block->id; ?>">
                                <button type="submit" class="cbd-status-toggle" title="<?php _e('Status ändern', 'container-block-designer'); ?>">
                                    <span class="cbd-status-badge cbd-status-<?php echo esc_attr($block->status); ?>">
                                        <?php echo $block->status === 'active' ? __('Aktiv', 'container-block-designer') : __('Inaktiv', 'container-block-designer'); ?>
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (!empty($block->slug)) : ?>
                        <p class="cbd-block-slug">
                            <code><?php echo esc_html($block->slug); ?></code>
                        </p>
                    <?php endif; ?>
                    
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
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Aktionen -->
                    <div class="cbd-block-actions-footer">
                        <a href="<?php echo admin_url('admin.php?page=cbd-edit-block&block_id=' . $block->id); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Bearbeiten', 'container-block-designer'); ?>
                        </a>
                        
                        <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Sind Sie sicher?', 'container-block-designer'); ?>');">
                            <?php wp_nonce_field('cbd_delete_block'); ?>
                            <input type="hidden" name="delete_block" value="1">
                            <input type="hidden" name="block_id" value="<?php echo $block->id; ?>">
                            <button type="submit" class="button button-link-delete">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Löschen', 'container-block-designer'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.cbd-admin-wrap {
    margin: 20px 20px 0 2px;
}

.cbd-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    margin-top: 20px;
}

.cbd-empty-state-icon {
    font-size: 60px;
    color: #dcdcde;
    margin-bottom: 20px;
}

.cbd-empty-state h2 {
    color: #23282d;
    font-size: 24px;
    margin-bottom: 10px;
}

.cbd-blocks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.cbd-block-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    position: relative;
    transition: box-shadow 0.3s;
}

.cbd-block-card:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.cbd-block-header h3 {
    margin: 0;
    font-size: 18px;
    color: #23282d;
}

.cbd-block-slug {
    margin: 5px 0;
    font-size: 12px;
}

.cbd-block-slug code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}

.cbd-block-description {
    color: #666;
    margin: 10px 0;
    font-size: 14px;
}

.cbd-status-toggle {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
}

.cbd-status-badge {
    display: inline-block;
    padding: 4px 10px;
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
    background: #fef1f1;
    color: #d63638;
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
    color: #50575e;
}

.cbd-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}

.cbd-feature-item {
    text-align: center;
}

.cbd-feature-toggle {
    background: #fff;
    border: 2px solid #dcdcde;
    padding: 10px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.cbd-feature-active .cbd-feature-toggle {
    background: #007cba;
    border-color: #007cba;
    color: #fff;
}

.cbd-feature-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cbd-feature-toggle .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-bottom: 5px;
}

.cbd-feature-label {
    font-size: 11px;
    display: block;
}

.cbd-block-actions-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
}

.cbd-block-actions-footer .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.cbd-block-actions-footer .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>