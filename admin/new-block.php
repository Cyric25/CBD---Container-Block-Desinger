<?php
/**
 * Container Block Designer - Neuer/Bearbeiten Block
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.1
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Block-ID für Bearbeitung
$block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
$block = null;

// Wenn Block-ID vorhanden, lade Block-Daten
if ($block_id) {
    global $wpdb;
    $block = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $block_id
    ));
    
    if (!$block) {
        wp_die(__('Block nicht gefunden.', 'container-block-designer'));
    }
    
    // Dekodiere JSON-Daten
    $config = json_decode($block->config, true) ?: array();
    $styles = json_decode($block->styles, true) ?: array();
    $features = json_decode($block->features, true) ?: array();
} else {
    // Standardwerte für neuen Block
    $config = array(
        'allowInnerBlocks' => true,
        'templateLock' => false
    );
    
    $styles = array(
        'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
        'background' => array('color' => '#ffffff'),
        'border' => array('width' => 1, 'color' => '#e0e0e0', 'style' => 'solid', 'radius' => 4),
        'text' => array('color' => '#333333', 'alignment' => 'left')
    );
    
    $features = array(
        'icon' => array('enabled' => false, 'value' => 'dashicons-admin-generic'),
        'collapse' => array('enabled' => false, 'defaultState' => 'expanded'),
        'numbering' => array('enabled' => false, 'format' => 'numeric'),
        'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
        'screenshot' => array('enabled' => false, 'buttonText' => 'Screenshot')
    );
}

// Verarbeite Formular-Submission
if (isset($_POST['cbd_save_block']) && isset($_POST['cbd_nonce'])) {
    if (wp_verify_nonce($_POST['cbd_nonce'], 'cbd-save-block')) {
        global $wpdb;
        
        // Sammle Daten
        $name = sanitize_text_field($_POST['block_name']);
        $title = sanitize_text_field($_POST['block_title']);
        $description = sanitize_textarea_field($_POST['block_description']);
        $status = $_POST['block_status'] === 'active' ? 'active' : 'inactive';
        
        // Styles sammeln
        $styles = array(
            'padding' => array(
                'top' => intval($_POST['padding_top']),
                'right' => intval($_POST['padding_right']),
                'bottom' => intval($_POST['padding_bottom']),
                'left' => intval($_POST['padding_left'])
            ),
            'background' => array(
                'color' => sanitize_hex_color($_POST['background_color'])
            ),
            'border' => array(
                'width' => intval($_POST['border_width']),
                'color' => sanitize_hex_color($_POST['border_color']),
                'style' => sanitize_text_field($_POST['border_style']),
                'radius' => intval($_POST['border_radius'])
            ),
            'text' => array(
                'color' => sanitize_hex_color($_POST['text_color']),
                'alignment' => sanitize_text_field($_POST['text_alignment'])
            )
        );
        
        // Features sammeln
        $features = array();
        if (isset($_POST['features'])) {
            foreach ($_POST['features'] as $key => $feature_data) {
                $features[$key] = array(
                    'enabled' => isset($feature_data['enabled']) ? true : false,
                    'value' => isset($feature_data['value']) ? sanitize_text_field($feature_data['value']) : ''
                );
            }
        }
        
        // Config sammeln
        $config = array(
            'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
            'templateLock' => isset($_POST['template_lock']) ? true : false
        );
        
        // Daten vorbereiten
        $data = array(
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'config' => json_encode($config),
            'styles' => json_encode($styles),
            'features' => json_encode($features),
            'status' => $status,
            'updated' => current_time('mysql')
        );
        
        if ($block_id) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $data,
                array('id' => $block_id)
            );
        } else {
            // Insert
            $data['created'] = current_time('mysql');
            $result = $wpdb->insert(CBD_TABLE_BLOCKS, $data);
            $block_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=container-block-designer&cbd_message=saved'));
            exit;
        }
    }
}
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php echo $block_id ? __('Block bearbeiten', 'container-block-designer') : __('Neuer Container Block', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php _e('← Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <form method="post" id="cbd-block-form">
        <?php wp_nonce_field('cbd-save-block', 'cbd_nonce'); ?>
        
        <div class="cbd-form-container">
            <!-- Grundeinstellungen -->
            <div class="cbd-card">
                <h2><?php _e('Grundeinstellungen', 'container-block-designer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="block_name"><?php _e('Interner Name', 'container-block-designer'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="block_name" 
                                   name="block_name" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($block->name ?? ''); ?>" 
                                   required>
                            <p class="description"><?php _e('Eindeutiger Name für interne Verwendung (keine Leerzeichen)', 'container-block-designer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="block_title"><?php _e('Anzeigename', 'container-block-designer'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="block_title" 
                                   name="block_title" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($block->title ?? ''); ?>" 
                                   required>
                            <p class="description"><?php _e('Name der im Editor angezeigt wird', 'container-block-designer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="block_description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                        </th>
                        <td>
                            <textarea id="block_description" 
                                      name="block_description" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($block->description ?? ''); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="block_status"><?php _e('Status', 'container-block-designer'); ?></label>
                        </th>
                        <td>
                            <select id="block_status" name="block_status">
                                <option value="active" <?php selected($block->status ?? 'active', 'active'); ?>>
                                    <?php _e('Aktiv', 'container-block-designer'); ?>
                                </option>
                                <option value="inactive" <?php selected($block->status ?? '', 'inactive'); ?>>
                                    <?php _e('Inaktiv', 'container-block-designer'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Styles -->
            <div class="cbd-card">
                <h2><?php _e('Styles', 'container-block-designer'); ?></h2>
                
                <h3><?php _e('Padding', 'container-block-designer'); ?></h3>
                <div class="cbd-padding-inputs">
                    <label>
                        <?php _e('Oben', 'container-block-designer'); ?>
                        <input type="number" name="padding_top" value="<?php echo esc_attr($styles['padding']['top'] ?? 20); ?>" min="0" max="100">
                    </label>
                    <label>
                        <?php _e('Rechts', 'container-block-designer'); ?>
                        <input type="number" name="padding_right" value="<?php echo esc_attr($styles['padding']['right'] ?? 20); ?>" min="0" max="100">
                    </label>
                    <label>
                        <?php _e('Unten', 'container-block-designer'); ?>
                        <input type="number" name="padding_bottom" value="<?php echo esc_attr($styles['padding']['bottom'] ?? 20); ?>" min="0" max="100">
                    </label>
                    <label>
                        <?php _e('Links', 'container-block-designer'); ?>
                        <input type="number" name="padding_left" value="<?php echo esc_attr($styles['padding']['left'] ?? 20); ?>" min="0" max="100">
                    </label>
                </div>
                
                <h3><?php _e('Farben', 'container-block-designer'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></th>
                        <td>
                            <input type="text" 
                                   name="background_color" 
                                   value="<?php echo esc_attr($styles['background']['color'] ?? '#ffffff'); ?>" 
                                   class="cbd-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Textfarbe', 'container-block-designer'); ?></th>
                        <td>
                            <input type="text" 
                                   name="text_color" 
                                   value="<?php echo esc_attr($styles['text']['color'] ?? '#333333'); ?>" 
                                   class="cbd-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Text-Ausrichtung', 'container-block-designer'); ?></th>
                        <td>
                            <select name="text_alignment">
                                <option value="left" <?php selected($styles['text']['alignment'] ?? 'left', 'left'); ?>>
                                    <?php _e('Links', 'container-block-designer'); ?>
                                </option>
                                <option value="center" <?php selected($styles['text']['alignment'] ?? '', 'center'); ?>>
                                    <?php _e('Zentriert', 'container-block-designer'); ?>
                                </option>
                                <option value="right" <?php selected($styles['text']['alignment'] ?? '', 'right'); ?>>
                                    <?php _e('Rechts', 'container-block-designer'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Rahmen', 'container-block-designer'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Rahmenbreite', 'container-block-designer'); ?></th>
                        <td>
                            <input type="number" 
                                   name="border_width" 
                                   value="<?php echo esc_attr($styles['border']['width'] ?? 1); ?>" 
                                   min="0" 
                                   max="10"> px
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Rahmenfarbe', 'container-block-designer'); ?></th>
                        <td>
                            <input type="text" 
                                   name="border_color" 
                                   value="<?php echo esc_attr($styles['border']['color'] ?? '#e0e0e0'); ?>" 
                                   class="cbd-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Rahmenstil', 'container-block-designer'); ?></th>
                        <td>
                            <select name="border_style">
                                <option value="solid" <?php selected($styles['border']['style'] ?? 'solid', 'solid'); ?>>
                                    <?php _e('Durchgezogen', 'container-block-designer'); ?>
                                </option>
                                <option value="dashed" <?php selected($styles['border']['style'] ?? '', 'dashed'); ?>>
                                    <?php _e('Gestrichelt', 'container-block-designer'); ?>
                                </option>
                                <option value="dotted" <?php selected($styles['border']['style'] ?? '', 'dotted'); ?>>
                                    <?php _e('Gepunktet', 'container-block-designer'); ?>
                                </option>
                                <option value="none" <?php selected($styles['border']['style'] ?? '', 'none'); ?>>
                                    <?php _e('Kein Rahmen', 'container-block-designer'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Eckenradius', 'container-block-designer'); ?></th>
                        <td>
                            <input type="number" 
                                   name="border_radius" 
                                   value="<?php echo esc_attr($styles['border']['radius'] ?? 4); ?>" 
                                   min="0" 
                                   max="50"> px
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Features -->
            <div class="cbd-card">
                <h2><?php _e('Features', 'container-block-designer'); ?></h2>
                
                <div class="cbd-features-list">
                    <label>
                        <input type="checkbox" 
                               name="features[icon][enabled]" 
                               value="1" 
                               <?php checked($features['icon']['enabled'] ?? false); ?>>
                        <strong><?php _e('Icon', 'container-block-designer'); ?></strong>
                        <p class="description"><?php _e('Zeigt ein Icon im Block-Header', 'container-block-designer'); ?></p>
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="features[collapse][enabled]" 
                               value="1" 
                               <?php checked($features['collapse']['enabled'] ?? false); ?>>
                        <strong><?php _e('Ein-/Ausklappen', 'container-block-designer'); ?></strong>
                        <p class="description"><?php _e('Ermöglicht das Ein- und Ausklappen des Block-Inhalts', 'container-block-designer'); ?></p>
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="features[numbering][enabled]" 
                               value="1" 
                               <?php checked($features['numbering']['enabled'] ?? false); ?>>
                        <strong><?php _e('Nummerierung', 'container-block-designer'); ?></strong>
                        <p class="description"><?php _e('Automatische Nummerierung der Blöcke', 'container-block-designer'); ?></p>
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="features[copyText][enabled]" 
                               value="1" 
                               <?php checked($features['copyText']['enabled'] ?? false); ?>>
                        <strong><?php _e('Text kopieren', 'container-block-designer'); ?></strong>
                        <p class="description"><?php _e('Button zum Kopieren des Block-Inhalts', 'container-block-designer'); ?></p>
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="features[screenshot][enabled]" 
                               value="1" 
                               <?php checked($features['screenshot']['enabled'] ?? false); ?>>
                        <strong><?php _e('Screenshot', 'container-block-designer'); ?></strong>
                        <p class="description"><?php _e('Erstellt einen Screenshot des Blocks', 'container-block-designer'); ?></p>
                    </label>
                </div>
            </div>
            
            <!-- Konfiguration -->
            <div class="cbd-card">
                <h2><?php _e('Konfiguration', 'container-block-designer'); ?></h2>
                
                <label>
                    <input type="checkbox" 
                           name="allow_inner_blocks" 
                           value="1" 
                           <?php checked($config['allowInnerBlocks'] ?? true); ?>>
                    <strong><?php _e('Innere Blöcke erlauben', 'container-block-designer'); ?></strong>
                    <p class="description"><?php _e('Erlaubt das Hinzufügen von anderen Blöcken innerhalb dieses Containers', 'container-block-designer'); ?></p>
                </label>
                
                <label>
                    <input type="checkbox" 
                           name="template_lock" 
                           value="1" 
                           <?php checked($config['templateLock'] ?? false); ?>>
                    <strong><?php _e('Template sperren', 'container-block-designer'); ?></strong>
                    <p class="description"><?php _e('Verhindert das Hinzufügen oder Entfernen von inneren Blöcken', 'container-block-designer'); ?></p>
                </label>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" name="cbd_save_block" class="button button-primary">
                <?php echo $block_id ? __('Änderungen speichern', 'container-block-designer') : __('Block erstellen', 'container-block-designer'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button">
                <?php _e('Abbrechen', 'container-block-designer'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.cbd-admin-wrap {
    max-width: 1200px;
}

.cbd-form-container {
    margin-top: 20px;
}

.cbd-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.cbd-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.cbd-card h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
}

.cbd-padding-inputs {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.cbd-padding-inputs label {
    display: flex;
    flex-direction: column;
    font-size: 13px;
}

.cbd-padding-inputs input {
    margin-top: 5px;
    width: 60px;
}

.cbd-features-list label {
    display: block;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 3px;
}

.cbd-features-list input[type="checkbox"] {
    margin-right: 8px;
}

.cbd-features-list strong {
    font-weight: 600;
}

.cbd-features-list .description {
    margin: 5px 0 0 24px;
    color: #666;
    font-size: 13px;
}

.cbd-color-picker {
    width: 100px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Color Picker initialisieren
    if ($.fn.wpColorPicker) {
        $('.cbd-color-picker').wpColorPicker();
    }
});
</script>