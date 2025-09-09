<?php
/**
 * Container Block Designer - Block bearbeiten
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Output Buffering starten um Headers bereits sent zu verhindern
ob_start();

// Block-ID abrufen - unterstütze sowohl 'id' als auch 'block_id' Parameter
$block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if (!$block_id) {
    wp_die(__('Ungültige Block-ID', 'container-block-designer'));
}

// Block-Daten laden
$block = CBD_Database::get_block($block_id);

if (!$block) {
    wp_die(__('Block nicht gefunden', 'container-block-designer'));
}

// Standardwerte setzen
$styles = $block['styles'] ?: array(
    'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
    'background' => array('color' => '#ffffff'),
    'text' => array('color' => '#333333', 'alignment' => 'left'),
    'border' => array('width' => 1, 'color' => '#e0e0e0', 'style' => 'solid', 'radius' => 4),
    'shadow' => array(
        'outer' => array('enabled' => false, 'x' => 0, 'y' => 4, 'blur' => 6, 'spread' => 0, 'color' => '#00000040'),
        'inner' => array('enabled' => false, 'x' => 0, 'y' => 2, 'blur' => 4, 'spread' => 0, 'color' => '#00000030')
    )
);

$features = $block['features'] ?: array(
    'icon' => array('enabled' => false, 'value' => 'dashicons-admin-generic'),
    'collapse' => array('enabled' => false, 'defaultState' => 'expanded'),
    'numbering' => array('enabled' => false, 'format' => 'numeric'),
    'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
    'screenshot' => array('enabled' => false, 'buttonText' => 'Screenshot')
);

$config = $block['config'] ?: array(
    'allowInnerBlocks' => true,
    'templateLock' => false
);
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Block bearbeiten:', 'container-block-designer'); ?> 
        <?php echo esc_html($block['title']); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php _e('← Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div id="cbd-edit-container">
        <form id="cbd-block-form" method="post">
            <?php wp_nonce_field('cbd-admin', 'cbd_nonce'); ?>
            <input type="hidden" name="block_id" value="<?php echo esc_attr($block_id); ?>">
            
            <div class="cbd-form-grid">
                <div class="cbd-main-content">
                    
                    <!-- Grundeinstellungen -->
                    <div class="cbd-card">
                        <h2><?php _e('Grundeinstellungen', 'container-block-designer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="block-name"><?php _e('Name', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="block-name" name="name" 
                                           value="<?php echo esc_attr($block['name']); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Eindeutiger Name (nur Kleinbuchstaben und Bindestriche)', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-title"><?php _e('Titel', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="block-title" name="title" 
                                           value="<?php echo esc_attr($block['title']); ?>" 
                                           class="regular-text" required>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <textarea id="block-description" name="description" 
                                              rows="3" class="large-text"><?php echo esc_textarea($block['description']); ?></textarea>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-status"><?php _e('Status', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="block-status" name="status">
                                        <option value="active" <?php selected($block['status'], 'active'); ?>><?php _e('Aktiv', 'container-block-designer'); ?></option>
                                        <option value="inactive" <?php selected($block['status'], 'inactive'); ?>><?php _e('Inaktiv', 'container-block-designer'); ?></option>
                                        <option value="draft" <?php selected($block['status'], 'draft'); ?>><?php _e('Entwurf', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Styles -->
                    <div class="cbd-card">
                        <h2><?php _e('Styles', 'container-block-designer'); ?></h2>
                        
                        <table class="form-table">
                            <!-- Padding -->
                            <tr>
                                <th scope="row"><?php _e('Padding', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-spacing-inputs">
                                        <input type="number" name="styles[padding][top]" 
                                               value="<?php echo esc_attr($styles['padding']['top'] ?? 20); ?>" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Oben', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][right]" 
                                               value="<?php echo esc_attr($styles['padding']['right'] ?? 20); ?>" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Rechts', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][bottom]" 
                                               value="<?php echo esc_attr($styles['padding']['bottom'] ?? 20); ?>" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Unten', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][left]" 
                                               value="<?php echo esc_attr($styles['padding']['left'] ?? 20); ?>" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Links', 'container-block-designer'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Shadow -->
                            <tr>
                                <th scope="row"><?php _e('Schatten', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-shadow-controls">
                                        <fieldset>
                                            <legend><?php _e('Äußerer Schatten (Box Shadow)', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[shadow][outer][enabled]" value="1" <?php checked($styles['shadow']['outer']['enabled'] ?? false); ?>>
                                                <?php _e('Aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-shadow-options">
                                                <label>
                                                    <?php _e('Horizontal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][x]" value="<?php echo esc_attr($styles['shadow']['outer']['x'] ?? 0); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Vertikal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][y]" value="<?php echo esc_attr($styles['shadow']['outer']['y'] ?? 4); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Unschärfe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][blur]" value="<?php echo esc_attr($styles['shadow']['outer']['blur'] ?? 6); ?>" min="0" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Größe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][spread]" value="<?php echo esc_attr($styles['shadow']['outer']['spread'] ?? 0); ?>" min="-25" max="25" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Farbe:', 'container-block-designer'); ?>
                                                    <input type="text" name="styles[shadow][outer][color]" value="<?php echo esc_attr($styles['shadow']['outer']['color'] ?? '#00000040'); ?>" class="cbd-color-picker">
                                                </label>
                                            </div>
                                        </fieldset>
                                        <br>
                                        <fieldset>
                                            <legend><?php _e('Innerer Schatten (Inset Shadow)', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[shadow][inner][enabled]" value="1" <?php checked($styles['shadow']['inner']['enabled'] ?? false); ?>>
                                                <?php _e('Aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-shadow-options">
                                                <label>
                                                    <?php _e('Horizontal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][x]" value="<?php echo esc_attr($styles['shadow']['inner']['x'] ?? 0); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Vertikal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][y]" value="<?php echo esc_attr($styles['shadow']['inner']['y'] ?? 2); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Unschärfe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][blur]" value="<?php echo esc_attr($styles['shadow']['inner']['blur'] ?? 4); ?>" min="0" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Größe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][spread]" value="<?php echo esc_attr($styles['shadow']['inner']['spread'] ?? 0); ?>" min="-25" max="25" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Farbe:', 'container-block-designer'); ?>
                                                    <input type="text" name="styles[shadow][inner][color]" value="<?php echo esc_attr($styles['shadow']['inner']['color'] ?? '#00000030'); ?>" class="cbd-color-picker">
                                                </label>
                                            </div>
                                        </fieldset>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Background -->
                            <tr>
                                <th scope="row">
                                    <label for="bg-color"><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="bg-color" name="styles[background][color]" 
                                           value="<?php echo esc_attr($styles['background']['color'] ?? '#ffffff'); ?>" 
                                           class="cbd-color-picker">
                                </td>
                            </tr>
                            
                            <!-- Text -->
                            <tr>
                                <th scope="row">
                                    <label for="text-color"><?php _e('Textfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="text-color" name="styles[text][color]" 
                                           value="<?php echo esc_attr($styles['text']['color'] ?? '#333333'); ?>" 
                                           class="cbd-color-picker">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="text-alignment"><?php _e('Textausrichtung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="text-alignment" name="styles[text][alignment]">
                                        <option value="left" <?php selected($styles['text']['alignment'] ?? 'left', 'left'); ?>><?php _e('Links', 'container-block-designer'); ?></option>
                                        <option value="center" <?php selected($styles['text']['alignment'] ?? 'left', 'center'); ?>><?php _e('Zentriert', 'container-block-designer'); ?></option>
                                        <option value="right" <?php selected($styles['text']['alignment'] ?? 'left', 'right'); ?>><?php _e('Rechts', 'container-block-designer'); ?></option>
                                        <option value="justify" <?php selected($styles['text']['alignment'] ?? 'left', 'justify'); ?>><?php _e('Blocksatz', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Border -->
                            <tr>
                                <th scope="row"><?php _e('Rahmen', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-border-inputs">
                                        <input type="number" name="styles[border][width]" 
                                               value="<?php echo esc_attr($styles['border']['width'] ?? 1); ?>" 
                                               min="0" max="10" class="small-text">
                                        <label><?php _e('Breite (px)', 'container-block-designer'); ?></label>
                                        
                                        <select name="styles[border][style]">
                                            <option value="solid" <?php selected($styles['border']['style'] ?? 'solid', 'solid'); ?>><?php _e('Durchgezogen', 'container-block-designer'); ?></option>
                                            <option value="dashed" <?php selected($styles['border']['style'] ?? 'solid', 'dashed'); ?>><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                            <option value="dotted" <?php selected($styles['border']['style'] ?? 'solid', 'dotted'); ?>><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                        </select>
                                        
                                        <input type="text" name="styles[border][color]" 
                                               value="<?php echo esc_attr($styles['border']['color'] ?? '#e0e0e0'); ?>" 
                                               class="cbd-color-picker">
                                        
                                        <input type="number" name="styles[border][radius]" 
                                               value="<?php echo esc_attr($styles['border']['radius'] ?? 4); ?>" 
                                               min="0" max="50" class="small-text">
                                        <label><?php _e('Radius (px)', 'container-block-designer'); ?></label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Features -->
                    <div class="cbd-card">
                        <h2><?php _e('Features', 'container-block-designer'); ?></h2>
                        
                        <table class="form-table">
                            <!-- Icon -->
                            <tr>
                                <th scope="row"><?php _e('Icon', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[icon][enabled]" value="1" 
                                               <?php checked($features['icon']['enabled'] ?? false, true); ?>>
                                        <?php _e('Icon anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <input type="text" name="features[icon][value]" 
                                           value="<?php echo esc_attr($features['icon']['value'] ?? 'dashicons-admin-generic'); ?>" 
                                           class="regular-text" 
                                           placeholder="dashicons-admin-generic">
                                    <p class="description"><?php _e('Dashicon-Klasse eingeben', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            
                            <!-- Collapse -->
                            <tr>
                                <th scope="row"><?php _e('Einklappen', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[collapse][enabled]" value="1" 
                                               <?php checked($features['collapse']['enabled'] ?? false, true); ?>>
                                        <?php _e('Einklappen ermöglichen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <select name="features[collapse][defaultState]">
                                        <option value="expanded" <?php selected($features['collapse']['defaultState'] ?? 'expanded', 'expanded'); ?>><?php _e('Ausgeklappt', 'container-block-designer'); ?></option>
                                        <option value="collapsed" <?php selected($features['collapse']['defaultState'] ?? 'expanded', 'collapsed'); ?>><?php _e('Eingeklappt', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Numbering -->
                            <tr>
                                <th scope="row"><?php _e('Nummerierung', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[numbering][enabled]" value="1" 
                                               <?php checked($features['numbering']['enabled'] ?? false, true); ?>>
                                        <?php _e('Nummerierung anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <select name="features[numbering][format]">
                                        <option value="numeric" <?php selected($features['numbering']['format'] ?? 'numeric', 'numeric'); ?>><?php _e('Numerisch (1, 2, 3)', 'container-block-designer'); ?></option>
                                        <option value="alphabetic" <?php selected($features['numbering']['format'] ?? 'numeric', 'alphabetic'); ?>><?php _e('Alphabetisch (A, B, C)', 'container-block-designer'); ?></option>
                                        <option value="roman" <?php selected($features['numbering']['format'] ?? 'numeric', 'roman'); ?>><?php _e('Römisch (I, II, III)', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Copy Text -->
                            <tr>
                                <th scope="row"><?php _e('Text kopieren', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[copyText][enabled]" value="1" 
                                               <?php checked($features['copyText']['enabled'] ?? false, true); ?>>
                                        <?php _e('Text kopieren Button anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <input type="text" name="features[copyText][buttonText]" 
                                           value="<?php echo esc_attr($features['copyText']['buttonText'] ?? 'Text kopieren'); ?>" 
                                           class="regular-text" 
                                           placeholder="Text kopieren">
                                    <p class="description"><?php _e('Text für den Button', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            
                            <!-- Screenshot -->
                            <tr>
                                <th scope="row"><?php _e('Screenshot', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[screenshot][enabled]" value="1" 
                                               <?php checked($features['screenshot']['enabled'] ?? false, true); ?>>
                                        <?php _e('Screenshot Button anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <input type="text" name="features[screenshot][buttonText]" 
                                           value="<?php echo esc_attr($features['screenshot']['buttonText'] ?? 'Screenshot'); ?>" 
                                           class="regular-text" 
                                           placeholder="Screenshot">
                                    <p class="description"><?php _e('Text für den Button', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                </div>
                
                <!-- Sidebar -->
                <div class="cbd-sidebar">
                    <!-- Live Preview -->
                    <div class="cbd-card">
                        <h3><?php _e('Live Preview', 'container-block-designer'); ?></h3>
                        <div id="cbd-live-preview" class="cbd-preview-container">
                            <div id="cbd-preview-block" class="cbd-preview-block">
                                <div class="cbd-preview-content">
                                    <h4 id="cbd-preview-title"><?php echo esc_html($block['title'] ?? 'Block Titel'); ?></h4>
                                    <p id="cbd-preview-description"><?php echo esc_html($block['description'] ?? 'Block Beschreibung...'); ?></p>
                                    <div class="cbd-preview-features" id="cbd-preview-features"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cbd-card">
                        <h3><?php _e('Aktionen', 'container-block-designer'); ?></h3>
                        
                        <button type="submit" name="save_block" class="button button-primary button-large" id="cbd-save-block">
                            <?php _e('Änderungen speichern', 'container-block-designer'); ?>
                        </button>
                        
                        <hr>
                        
                        <a href="<?php echo wp_nonce_url(
                            admin_url('admin.php?page=container-block-designer&action=duplicate&block_id=' . $block_id),
                            'cbd_duplicate_block_' . $block_id
                        ); ?>" class="button button-secondary">
                            <?php _e('Block duplizieren', 'container-block-designer'); ?>
                        </a>
                        
                        <hr>
                        
                        <a href="<?php echo wp_nonce_url(
                            admin_url('admin.php?page=container-block-designer&action=delete&block_id=' . $block_id),
                            'cbd_delete_block_' . $block_id
                        ); ?>" 
                        class="button button-link-delete"
                        onclick="return confirm('<?php esc_attr_e('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'); ?>');">
                            <?php _e('Block löschen', 'container-block-designer'); ?>
                        </a>
                    </div>
                    
                    <div class="cbd-card">
                        <h3><?php _e('Informationen', 'container-block-designer'); ?></h3>
                        
                        <p>
                            <strong><?php _e('Erstellt:', 'container-block-designer'); ?></strong><br>
                            <?php 
                            $created = isset($block['created']) && $block['created'] ? $block['created'] : current_time('mysql');
                            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created)); 
                            ?>
                        </p>
                        
                        <p>
                            <strong><?php _e('Aktualisiert:', 'container-block-designer'); ?></strong><br>
                            <?php 
                            $updated = isset($block['updated']) && $block['updated'] ? $block['updated'] : current_time('mysql');
                            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($updated)); 
                            ?>
                        </p>
                        
                        <p>
                            <strong><?php _e('Block-ID:', 'container-block-designer'); ?></strong><br>
                            <?php echo esc_html($block['id']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.cbd-admin-wrap {
    margin-right: 20px;
}

.cbd-form-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    margin-top: 20px;
}

.cbd-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.cbd-card h2,
.cbd-card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.cbd-spacing-inputs,
.cbd-border-inputs {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.cbd-spacing-inputs label,
.cbd-border-inputs label {
    margin-right: 15px;
}

.cbd-sidebar .button {
    width: 100%;
    margin-bottom: 10px;
}

.cbd-sidebar hr {
    margin: 15px 0;
}

/* Live Preview Styles */
.cbd-preview-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
    margin-top: 10px;
}

#cbd-preview-block {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
}

#cbd-preview-title {
    margin: 0 0 10px 0;
    font-weight: 600;
    color: #333;
}

#cbd-preview-description {
    margin: 0 0 15px 0;
    color: #666;
    line-height: 1.5;
}

.cbd-preview-features {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.cbd-preview-feature {
    font-size: 11px;
    background: #0073aa;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.cbd-preview-feature.inactive {
    background: #ccc;
    color: #666;
}

.cbd-preview-feature .dashicons {
    font-size: 12px;
    width: 12px;
    height: 12px;
}

@media (max-width: 782px) {
    .cbd-form-grid {
        grid-template-columns: 1fr;
    }
}

/* Shadow Controls Styles */
.cbd-shadow-controls fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 10px 0;
}

.cbd-shadow-controls legend {
    font-weight: 600;
    padding: 0 10px;
}

.cbd-shadow-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.cbd-shadow-options label {
    display: flex;
    flex-direction: column;
    font-size: 12px;
    font-weight: 500;
}

.cbd-shadow-options input {
    margin-top: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Edit block JavaScript loaded');
    
    // Color Picker initialisieren
    if ($.fn.wpColorPicker) {
        $('.cbd-color-picker').wpColorPicker();
    }
    
    // Form Submit Handler - mit höherer Priorität
    $(document).off('submit', '#cbd-block-form');
    $('#cbd-block-form').off('submit');
    
    $('#cbd-block-form').on('submit', function(e) {
        console.log('Edit block form submitted - intercepting for AJAX');
        e.preventDefault();
        e.stopPropagation();
        
        try {
            var $form = $(this);
            var $button = $('#cbd-save-block');
            var originalText = $button.text();
            
            console.log('Form element found:', $form.length);
            console.log('Button element found:', $button.length);
            
            // Button deaktivieren
            $button.prop('disabled', true).text('<?php esc_attr_e('Speichern...', 'container-block-designer'); ?>');
            
            // Verwende neue funktionierende Action mit allen Daten
            var formData = $form.serialize();
            formData += '&action=cbd_edit_save';
            
            console.log('Data serialized successfully');
        
        // AJAX Request
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        console.log('Sending AJAX request to:', ajaxurl);
        console.log('Form data:', formData);
        
        $.post(ajaxurl, formData, function(response) {
            console.log('AJAX response received:', response);
            console.log('Response success:', response.success);
            console.log('Response data:', response.data);
            if (response.success) {
                // Erfolg
                $button.text('<?php esc_attr_e('Gespeichert!', 'container-block-designer'); ?>');
                
                // Nach 2 Sekunden zurücksetzen
                setTimeout(function() {
                    $button.prop('disabled', false).text(originalText);
                }, 2000);
            } else {
                // Fehler
                alert(response.data.message || '<?php esc_attr_e('Ein Fehler ist aufgetreten', 'container-block-designer'); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        }).fail(function(xhr, status, error) {
            console.log('AJAX request failed:', status, error);
            alert('<?php esc_attr_e('Verbindungsfehler', 'container-block-designer'); ?>');
            $button.prop('disabled', false).text(originalText);
        });
        
        } catch (err) {
            console.error('JavaScript error in form handler:', err);
            alert('JavaScript-Fehler: ' + err.message);
        }
        
        return false;
    });
    
    // Live Preview Update Function
    function updateLivePreview() {
        var $previewBlock = $('#cbd-preview-block');
        var $previewTitle = $('#cbd-preview-title');
        var $previewDescription = $('#cbd-preview-description');
        var $previewFeatures = $('#cbd-preview-features');
        
        // Update content
        var title = $('#block-title').val() || 'Block Titel';
        var description = $('#description').val() || 'Block Beschreibung...';
        
        $previewTitle.text(title);
        $previewDescription.text(description);
        
        // Update styles
        var bgColor = $('input[name="styles[background][color]"]').val() || '#ffffff';
        var textColor = $('input[name="styles[text][color]"]').val() || '#333333';
        var borderWidth = $('input[name="styles[border][width]"]').val() || '1';
        var borderColor = $('input[name="styles[border][color]"]').val() || '#e0e0e0';
        var borderStyle = $('select[name="styles[border][style]"]').val() || 'solid';
        var borderRadius = $('input[name="styles[border][radius]"]').val() || '4';
        var paddingTop = $('input[name="styles[padding][top]"]').val() || '20';
        var paddingRight = $('input[name="styles[padding][right]"]').val() || '20';
        var paddingBottom = $('input[name="styles[padding][bottom]"]').val() || '20';
        var paddingLeft = $('input[name="styles[padding][left]"]').val() || '20';
        
        // Shadow styles
        var outerEnabled = $('input[name="styles[shadow][outer][enabled]"]').is(':checked');
        var outerX = $('input[name="styles[shadow][outer][x]"]').val() || '0';
        var outerY = $('input[name="styles[shadow][outer][y]"]').val() || '4';
        var outerBlur = $('input[name="styles[shadow][outer][blur]"]').val() || '6';
        var outerSpread = $('input[name="styles[shadow][outer][spread]"]').val() || '0';
        var outerColor = $('input[name="styles[shadow][outer][color]"]').val() || '#00000040';
        
        var innerEnabled = $('input[name="styles[shadow][inner][enabled]"]').is(':checked');
        var innerX = $('input[name="styles[shadow][inner][x]"]').val() || '0';
        var innerY = $('input[name="styles[shadow][inner][y]"]').val() || '2';
        var innerBlur = $('input[name="styles[shadow][inner][blur]"]').val() || '4';
        var innerSpread = $('input[name="styles[shadow][inner][spread]"]').val() || '0';
        var innerColor = $('input[name="styles[shadow][inner][color]"]').val() || '#00000030';
        
        // Build box-shadow value
        var boxShadows = [];
        if (outerEnabled) {
            boxShadows.push(outerX + 'px ' + outerY + 'px ' + outerBlur + 'px ' + outerSpread + 'px ' + outerColor);
        }
        if (innerEnabled) {
            boxShadows.push('inset ' + innerX + 'px ' + innerY + 'px ' + innerBlur + 'px ' + innerSpread + 'px ' + innerColor);
        }
        
        $previewBlock.css({
            'background-color': bgColor,
            'color': textColor,
            'border': borderWidth + 'px ' + borderStyle + ' ' + borderColor,
            'border-radius': borderRadius + 'px',
            'padding': paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px',
            'box-shadow': boxShadows.length > 0 ? boxShadows.join(', ') : 'none'
        });
        
        // Update features
        $previewFeatures.empty();
        var features = [
            { key: 'icon', name: 'Icon', icon: 'dashicons-star-filled' },
            { key: 'collapse', name: 'Klappbar', icon: 'dashicons-arrow-up-alt2' },
            { key: 'numbering', name: 'Nummerierung', icon: 'dashicons-editor-ol' },
            { key: 'copyText', name: 'Text kopieren', icon: 'dashicons-clipboard' },
            { key: 'screenshot', name: 'Screenshot', icon: 'dashicons-camera' }
        ];
        
        features.forEach(function(feature) {
            var isEnabled = $('input[name="features[' + feature.key + '][enabled]"]').is(':checked');
            var $featureEl = $('<div class="cbd-preview-feature' + (isEnabled ? '' : ' inactive') + '">')
                .append('<span class="dashicons ' + feature.icon + '"></span>')
                .append(feature.name);
            $previewFeatures.append($featureEl);
        });
    }
    
    // Initialize preview and bind events
    updateLivePreview();
    
    // Bind live preview updates
    $('input[name^="styles"], select[name^="styles"], input[name="title"], textarea[name="description"], input[name^="features"]').on('input change', function() {
        updateLivePreview();
    });
});
</script>