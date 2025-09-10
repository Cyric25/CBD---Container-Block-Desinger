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
    'background' => array(
        'type' => 'color',
        'color' => '#ffffff',
        'gradient' => array(
            'type' => 'linear',
            'angle' => 45,
            'color1' => '#ff6b6b',
            'color2' => '#4ecdc4',
            'color3' => ''
        )
    ),
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
                                <th scope="row"><?php _e('Hintergrund', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-background-controls">
                                        <fieldset>
                                            <legend><?php _e('Hintergrund-Typ', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="radio" name="styles[background][type]" value="color" <?php checked($styles['background']['type'] ?? 'color', 'color'); ?>>
                                                <?php _e('Farbe', 'container-block-designer'); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="styles[background][type]" value="gradient" <?php checked($styles['background']['type'] ?? 'color', 'gradient'); ?>>
                                                <?php _e('Gradient', 'container-block-designer'); ?>
                                            </label>
                                        </fieldset>
                                        
                                        <!-- Farbe Optionen -->
                                        <div class="cbd-bg-color-options" style="<?php echo ($styles['background']['type'] ?? 'color') !== 'color' ? 'display: none;' : ''; ?>">
                                            <label>
                                                <?php _e('Farbe:', 'container-block-designer'); ?>
                                                <input type="text" name="styles[background][color]" value="<?php echo esc_attr($styles['background']['color'] ?? '#ffffff'); ?>" class="cbd-color-picker">
                                            </label>
                                        </div>
                                        
                                        <!-- Gradient Optionen -->
                                        <div class="cbd-bg-gradient-options" style="<?php echo ($styles['background']['type'] ?? 'color') !== 'gradient' ? 'display: none;' : ''; ?>">
                                            <div class="cbd-gradient-controls">
                                                <label>
                                                    <?php _e('Gradient-Typ:', 'container-block-designer'); ?>
                                                    <select name="styles[background][gradient][type]">
                                                        <option value="linear" <?php selected($styles['background']['gradient']['type'] ?? 'linear', 'linear'); ?>><?php _e('Linear', 'container-block-designer'); ?></option>
                                                        <option value="radial" <?php selected($styles['background']['gradient']['type'] ?? 'linear', 'radial'); ?>><?php _e('Radial', 'container-block-designer'); ?></option>
                                                        <option value="conic" <?php selected($styles['background']['gradient']['type'] ?? 'linear', 'conic'); ?>><?php _e('Konisch', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <?php _e('Richtung (Grad):', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[background][gradient][angle]" min="0" max="360" value="<?php echo esc_attr($styles['background']['gradient']['angle'] ?? 45); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($styles['background']['gradient']['angle'] ?? 45); ?>°</span>
                                                </label>
                                                <label>
                                                    <?php _e('Startfarbe:', 'container-block-designer'); ?>
                                                    <input type="text" name="styles[background][gradient][color1]" value="<?php echo esc_attr($styles['background']['gradient']['color1'] ?? '#ff6b6b'); ?>" class="cbd-color-picker">
                                                </label>
                                                <label>
                                                    <?php _e('Endfarbe:', 'container-block-designer'); ?>
                                                    <input type="text" name="styles[background][gradient][color2]" value="<?php echo esc_attr($styles['background']['gradient']['color2'] ?? '#4ecdc4'); ?>" class="cbd-color-picker">
                                                </label>
                                                <label>
                                                    <?php _e('Mittlere Farbe (optional):', 'container-block-designer'); ?>
                                                    <input type="text" name="styles[background][gradient][color3]" value="<?php echo esc_attr($styles['background']['gradient']['color3'] ?? ''); ?>" class="cbd-color-picker">
                                                    <small><?php _e('Leer lassen für 2-Farben-Gradient', 'container-block-designer'); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
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
                                    <div class="cbd-icon-picker">
                                        <input type="hidden" name="features[icon][value]" value="<?php echo esc_attr($features['icon']['value'] ?? 'dashicons-admin-generic'); ?>">
                                        
                                        <!-- Selected Icon Display -->
                                        <div class="cbd-selected-icon">
                                            <span class="dashicons <?php echo esc_attr($features['icon']['value'] ?? 'dashicons-admin-generic'); ?>"></span>
                                            <span class="cbd-icon-name"><?php echo esc_html($features['icon']['value'] ?? 'dashicons-admin-generic'); ?></span>
                                            <button type="button" class="cbd-open-icon-picker button"><?php _e('Icon ändern', 'container-block-designer'); ?></button>
                                        </div>
                                        
                                        <!-- Icon Position Controls -->
                                        <div class="cbd-icon-position-controls" style="margin-top: 15px;">
                                            <label><?php _e('Icon Position:', 'container-block-designer'); ?></label>
                                            <div class="cbd-position-grid">
                                                <div class="cbd-position-row">
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="top-left" <?php checked($features['icon']['position'] ?? 'top-left', 'top-left'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator top-left"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Oben Links', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="top-center" <?php checked($features['icon']['position'] ?? 'top-left', 'top-center'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator top-center"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Oben Mitte', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="top-right" <?php checked($features['icon']['position'] ?? 'top-left', 'top-right'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator top-right"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Oben Rechts', 'container-block-designer'); ?></span>
                                                    </label>
                                                </div>
                                                <div class="cbd-position-row">
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="middle-left" <?php checked($features['icon']['position'] ?? 'top-left', 'middle-left'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator middle-left"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Mitte Links', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="middle-center" <?php checked($features['icon']['position'] ?? 'top-left', 'middle-center'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator middle-center"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Mitte', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="middle-right" <?php checked($features['icon']['position'] ?? 'top-left', 'middle-right'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator middle-right"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Mitte Rechts', 'container-block-designer'); ?></span>
                                                    </label>
                                                </div>
                                                <div class="cbd-position-row">
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="bottom-left" <?php checked($features['icon']['position'] ?? 'top-left', 'bottom-left'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator bottom-left"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Unten Links', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="bottom-center" <?php checked($features['icon']['position'] ?? 'top-left', 'bottom-center'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator bottom-center"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Unten Mitte', 'container-block-designer'); ?></span>
                                                    </label>
                                                    <label class="cbd-position-option">
                                                        <input type="radio" name="features[icon][position]" value="bottom-right" <?php checked($features['icon']['position'] ?? 'top-left', 'bottom-right'); ?>>
                                                        <span class="cbd-position-visual">
                                                            <span class="cbd-position-indicator bottom-right"></span>
                                                        </span>
                                                        <span class="cbd-position-label"><?php _e('Unten Rechts', 'container-block-designer'); ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Icon Picker Modal -->
                                        <div class="cbd-icon-picker-modal" style="display: none;">
                                            <div class="cbd-icon-picker-backdrop">
                                                <div class="cbd-icon-picker-content">
                                                    <div class="cbd-icon-picker-header">
                                                        <h3><?php _e('Icon auswählen', 'container-block-designer'); ?></h3>
                                                        <button type="button" class="cbd-close-icon-picker">&times;</button>
                                                    </div>
                                                    
                                                    <!-- Search -->
                                                    <div class="cbd-icon-search">
                                                        <input type="text" placeholder="<?php _e('Icons durchsuchen...', 'container-block-designer'); ?>" class="cbd-icon-search-input">
                                                    </div>
                                                    
                                                    <!-- Icon Categories -->
                                                    <div class="cbd-icon-categories">
                                                        <button type="button" class="cbd-icon-category active" data-category="all"><?php _e('Alle', 'container-block-designer'); ?></button>
                                                        <button type="button" class="cbd-icon-category" data-category="admin"><?php _e('Admin', 'container-block-designer'); ?></button>
                                                        <button type="button" class="cbd-icon-category" data-category="post"><?php _e('Posts', 'container-block-designer'); ?></button>
                                                        <button type="button" class="cbd-icon-category" data-category="media"><?php _e('Medien', 'container-block-designer'); ?></button>
                                                        <button type="button" class="cbd-icon-category" data-category="misc"><?php _e('Verschiedenes', 'container-block-designer'); ?></button>
                                                        <button type="button" class="cbd-icon-category" data-category="social"><?php _e('Social', 'container-block-designer'); ?></button>
                                                    </div>
                                                    
                                                    <!-- Icon Grid -->
                                                    <div class="cbd-icon-grid">
                                                        <!-- Icons will be populated by JavaScript -->
                                                    </div>
                                                    
                                                    <div class="cbd-icon-picker-footer">
                                                        <button type="button" class="button button-secondary cbd-close-icon-picker"><?php _e('Abbrechen', 'container-block-designer'); ?></button>
                                                        <button type="button" class="button button-primary cbd-select-icon"><?php _e('Icon auswählen', 'container-block-designer'); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="description"><?php _e('Klicken Sie auf "Icon ändern" um ein Icon aus der visuellen Liste auszuwählen', 'container-block-designer'); ?></p>
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
                                    
                                    <!-- Counting Mode -->
                                    <div style="margin-top: 15px;">
                                        <label><?php _e('Zählmodus:', 'container-block-designer'); ?></label>
                                        <div class="cbd-counting-mode-options">
                                            <label class="cbd-radio-option">
                                                <input type="radio" name="features[numbering][countingMode]" value="same-design" <?php checked($features['numbering']['countingMode'] ?? 'same-design', 'same-design'); ?>>
                                                <span class="cbd-radio-label">
                                                    <strong><?php _e('Zähle Blöcke mit diesem Design', 'container-block-designer'); ?></strong>
                                                    <small><?php _e('Nummerierung beginnt bei 1 für jeden Block-Typ', 'container-block-designer'); ?></small>
                                                </span>
                                            </label>
                                            <label class="cbd-radio-option">
                                                <input type="radio" name="features[numbering][countingMode]" value="all-blocks" <?php checked($features['numbering']['countingMode'] ?? 'same-design', 'all-blocks'); ?>>
                                                <span class="cbd-radio-label">
                                                    <strong><?php _e('Zähle alle Container-Blöcke', 'container-block-designer'); ?></strong>
                                                    <small><?php _e('Durchgängige Nummerierung über alle Block-Typen hinweg', 'container-block-designer'); ?></small>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Numbering Position Controls -->
                                    <div class="cbd-numbering-position-controls" style="margin-top: 15px;">
                                        <label><?php _e('Nummerierung Position:', 'container-block-designer'); ?></label>
                                        <div class="cbd-position-grid">
                                            <div class="cbd-position-row">
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="top-left" <?php checked($features['numbering']['position'] ?? 'top-left', 'top-left'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator top-left"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Oben Links', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="top-center" <?php checked($features['numbering']['position'] ?? 'top-left', 'top-center'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator top-center"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Oben Mitte', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="top-right" <?php checked($features['numbering']['position'] ?? 'top-left', 'top-right'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator top-right"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Oben Rechts', 'container-block-designer'); ?></span>
                                                </label>
                                            </div>
                                            <div class="cbd-position-row">
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="middle-left" <?php checked($features['numbering']['position'] ?? 'top-left', 'middle-left'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator middle-left"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Mitte Links', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="middle-center" <?php checked($features['numbering']['position'] ?? 'top-left', 'middle-center'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator middle-center"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Mitte', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="middle-right" <?php checked($features['numbering']['position'] ?? 'top-left', 'middle-right'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator middle-right"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Mitte Rechts', 'container-block-designer'); ?></span>
                                                </label>
                                            </div>
                                            <div class="cbd-position-row">
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="bottom-left" <?php checked($features['numbering']['position'] ?? 'top-left', 'bottom-left'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator bottom-left"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Unten Links', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="bottom-center" <?php checked($features['numbering']['position'] ?? 'top-left', 'bottom-center'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator bottom-center"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Unten Mitte', 'container-block-designer'); ?></span>
                                                </label>
                                                <label class="cbd-position-option">
                                                    <input type="radio" name="features[numbering][position]" value="bottom-right" <?php checked($features['numbering']['position'] ?? 'top-left', 'bottom-right'); ?>>
                                                    <span class="cbd-position-visual">
                                                        <span class="cbd-position-indicator bottom-right"></span>
                                                    </span>
                                                    <span class="cbd-position-label"><?php _e('Unten Rechts', 'container-block-designer'); ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
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

/* Icon Picker Styles */
.cbd-selected-icon {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
}

.cbd-selected-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    color: #666;
}

.cbd-icon-name {
    font-family: monospace;
    color: #666;
    flex-grow: 1;
}

.cbd-icon-picker-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.cbd-icon-picker-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-icon-picker-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 800px;
    max-height: 90%;
    display: flex;
    flex-direction: column;
}

.cbd-icon-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
}

.cbd-icon-picker-header h3 {
    margin: 0;
}

.cbd-close-icon-picker {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cbd-close-icon-picker:hover {
    color: #333;
}

.cbd-icon-search {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}

.cbd-icon-search-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.cbd-icon-categories {
    display: flex;
    gap: 5px;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    flex-wrap: wrap;
}

.cbd-icon-category {
    padding: 6px 12px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.cbd-icon-category:hover,
.cbd-icon-category.active {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.cbd-icon-grid {
    flex-grow: 1;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.cbd-icon-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px;
    border: 2px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    background: #f9f9f9;
}

.cbd-icon-item:hover {
    border-color: #007cba;
    background: #e7f5ff;
}

.cbd-icon-item.selected {
    border-color: #007cba;
    background: #007cba;
    color: white;
}

.cbd-icon-item .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-bottom: 5px;
}

.cbd-icon-item.selected .dashicons {
    color: white;
}

.cbd-icon-label {
    font-size: 10px;
    text-align: center;
    word-break: break-word;
    line-height: 1.2;
}

.cbd-icon-picker-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Icon Position Controls */
.cbd-icon-position-controls,
.cbd-numbering-position-controls {
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 10px;
}

.cbd-icon-position-controls > label,
.cbd-numbering-position-controls > label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: #23282d;
}

.cbd-position-grid {
    display: grid;
    gap: 8px;
}

.cbd-position-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.cbd-position-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 8px;
    border: 2px solid #ddd;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.cbd-position-option:hover {
    border-color: #007cba;
    background: #f0f8ff;
}

.cbd-position-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.cbd-position-option input[type="radio"]:checked + .cbd-position-visual {
    background: #007cba;
    border-color: #007cba;
}

.cbd-position-option input[type="radio"]:checked + .cbd-position-visual .cbd-position-indicator {
    background: white;
}

.cbd-position-option input[type="radio"]:checked ~ .cbd-position-label {
    color: #007cba;
    font-weight: 600;
}

.cbd-position-visual {
    width: 50px;
    height: 40px;
    border: 2px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
    position: relative;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.cbd-position-indicator {
    width: 8px;
    height: 8px;
    background: #666;
    border-radius: 50%;
    position: absolute;
    transition: all 0.2s ease;
}

.cbd-position-indicator.top-left {
    top: 4px;
    left: 4px;
}

.cbd-position-indicator.top-center {
    top: 4px;
    left: 50%;
    transform: translateX(-50%);
}

.cbd-position-indicator.top-right {
    top: 4px;
    right: 4px;
}

.cbd-position-indicator.middle-left {
    top: 50%;
    left: 4px;
    transform: translateY(-50%);
}

.cbd-position-indicator.middle-center {
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.cbd-position-indicator.middle-right {
    top: 50%;
    right: 4px;
    transform: translateY(-50%);
}

.cbd-position-indicator.bottom-left {
    bottom: 4px;
    left: 4px;
}

.cbd-position-indicator.bottom-center {
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
}

.cbd-position-indicator.bottom-right {
    bottom: 4px;
    right: 4px;
}

.cbd-position-label {
    font-size: 11px;
    color: #666;
    transition: all 0.2s ease;
}

/* Counting Mode Options */
.cbd-counting-mode-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
}

.cbd-radio-option {
    display: flex;
    align-items: flex-start;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cbd-radio-option:hover {
    border-color: #007cba;
    background: #f0f8ff;
}

.cbd-radio-option input[type="radio"] {
    margin-right: 12px;
    margin-top: 2px;
    flex-shrink: 0;
}

.cbd-radio-option input[type="radio"]:checked {
    accent-color: #007cba;
}

.cbd-radio-option input[type="radio"]:checked + .cbd-radio-label {
    color: #007cba;
}

.cbd-radio-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cbd-radio-label strong {
    font-size: 14px;
    color: #23282d;
    transition: color 0.2s ease;
}

.cbd-radio-label small {
    font-size: 12px;
    color: #666;
    font-style: italic;
    line-height: 1.4;
}

/* Background Controls Styles */
.cbd-background-controls fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 10px 0;
}

.cbd-background-controls legend {
    font-weight: 600;
    padding: 0 10px;
}

.cbd-background-controls label {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 10px;
}

.cbd-bg-color-options,
.cbd-bg-gradient-options {
    margin-top: 15px;
}

.cbd-gradient-controls {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 10px;
}

.cbd-gradient-controls label {
    display: flex;
    flex-direction: column;
    font-size: 12px;
    font-weight: 500;
}

.cbd-range-input {
    margin: 5px 0;
    width: 100%;
}

.cbd-range-value {
    font-weight: bold;
    color: #007cba;
}

.cbd-gradient-controls small {
    font-style: italic;
    color: #666;
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
    
    // Dashicons Liste (identisch zu new-block.php)
    var dashicons = {
        'admin': [
            'dashicons-admin-appearance', 'dashicons-admin-collapse', 'dashicons-admin-comments',
            'dashicons-admin-generic', 'dashicons-admin-home', 'dashicons-admin-media',
            'dashicons-admin-network', 'dashicons-admin-page', 'dashicons-admin-plugins',
            'dashicons-admin-settings', 'dashicons-admin-site', 'dashicons-admin-tools',
            'dashicons-admin-users', 'dashicons-dashboard', 'dashicons-database'
        ],
        'post': [
            'dashicons-align-center', 'dashicons-align-left', 'dashicons-align-right',
            'dashicons-edit', 'dashicons-trash', 'dashicons-sticky', 'dashicons-book',
            'dashicons-book-alt', 'dashicons-archive', 'dashicons-tagcloud',
            'dashicons-category', 'dashicons-post-status', 'dashicons-menu-alt'
        ],
        'media': [
            'dashicons-camera', 'dashicons-images-alt', 'dashicons-images-alt2',
            'dashicons-video-alt', 'dashicons-video-alt2', 'dashicons-video-alt3',
            'dashicons-media-archive', 'dashicons-media-audio', 'dashicons-media-code',
            'dashicons-media-default', 'dashicons-media-document', 'dashicons-media-interactive',
            'dashicons-media-spreadsheet', 'dashicons-media-text', 'dashicons-media-video',
            'dashicons-playlist-audio', 'dashicons-playlist-video'
        ],
        'misc': [
            'dashicons-arrow-down', 'dashicons-arrow-down-alt', 'dashicons-arrow-down-alt2',
            'dashicons-arrow-left', 'dashicons-arrow-left-alt', 'dashicons-arrow-left-alt2',
            'dashicons-arrow-right', 'dashicons-arrow-right-alt', 'dashicons-arrow-right-alt2',
            'dashicons-arrow-up', 'dashicons-arrow-up-alt', 'dashicons-arrow-up-alt2',
            'dashicons-controls-back', 'dashicons-controls-forward', 'dashicons-controls-pause',
            'dashicons-controls-play', 'dashicons-controls-repeat', 'dashicons-controls-skipback',
            'dashicons-controls-skipforward', 'dashicons-controls-volumeoff', 'dashicons-controls-volumeon',
            'dashicons-exit', 'dashicons-fullscreen-alt', 'dashicons-fullscreen-exit-alt',
            'dashicons-image-crop', 'dashicons-image-filter', 'dashicons-image-flip-horizontal',
            'dashicons-image-flip-vertical', 'dashicons-image-rotate', 'dashicons-image-rotate-left',
            'dashicons-image-rotate-right', 'dashicons-undo', 'dashicons-redo',
            'dashicons-editor-bold', 'dashicons-editor-customchar', 'dashicons-editor-distractionfree',
            'dashicons-editor-help', 'dashicons-editor-indent', 'dashicons-editor-insertmore',
            'dashicons-editor-italic', 'dashicons-editor-justify', 'dashicons-editor-kitchensink',
            'dashicons-editor-ol', 'dashicons-editor-outdent', 'dashicons-editor-paragraph',
            'dashicons-editor-paste-text', 'dashicons-editor-paste-word', 'dashicons-editor-quote',
            'dashicons-editor-removeformatting', 'dashicons-editor-rtl', 'dashicons-editor-spellcheck',
            'dashicons-editor-strikethrough', 'dashicons-editor-table', 'dashicons-editor-textcolor',
            'dashicons-editor-ul', 'dashicons-editor-underline', 'dashicons-editor-unlink',
            'dashicons-editor-video', 'dashicons-align-center', 'dashicons-align-left',
            'dashicons-align-none', 'dashicons-align-right', 'dashicons-lock', 'dashicons-unlock',
            'dashicons-calendar', 'dashicons-calendar-alt', 'dashicons-hidden', 'dashicons-visibility',
            'dashicons-post-status', 'dashicons-edit', 'dashicons-post-trash', 'dashicons-sticky',
            'dashicons-external', 'dashicons-insert', 'dashicons-table-col-after', 'dashicons-table-col-before',
            'dashicons-table-col-delete', 'dashicons-table-row-after', 'dashicons-table-row-before',
            'dashicons-table-row-delete', 'dashicons-saved', 'dashicons-smartphone', 'dashicons-tablet'
        ],
        'social': [
            'dashicons-email', 'dashicons-email-alt', 'dashicons-facebook', 'dashicons-facebook-alt',
            'dashicons-googleplus', 'dashicons-networking', 'dashicons-hammer', 'dashicons-art',
            'dashicons-migrate', 'dashicons-performance', 'dashicons-universal-access',
            'dashicons-universal-access-alt', 'dashicons-tickets', 'dashicons-nametag',
            'dashicons-clipboard', 'dashicons-heart', 'dashicons-megaphone', 'dashicons-schedule'
        ]
    };
    
    var selectedIcon = '';
    var currentCategory = 'all';
    
    // Icon Picker Modal öffnen
    $('.cbd-open-icon-picker').on('click', function(e) {
        e.preventDefault();
        var $modal = $('.cbd-icon-picker-modal');
        selectedIcon = $('input[name="features[icon][value]"]').val();
        populateIconGrid(currentCategory);
        $modal.show();
        $('body').addClass('modal-open');
    });
    
    // Icon Picker Modal schließen
    $('.cbd-close-icon-picker, .cbd-icon-picker-backdrop').on('click', function(e) {
        if (e.target === this) {
            $('.cbd-icon-picker-modal').hide();
            $('body').removeClass('modal-open');
        }
    });
    
    // Kategorie wechseln
    $('.cbd-icon-category').on('click', function() {
        $('.cbd-icon-category').removeClass('active');
        $(this).addClass('active');
        currentCategory = $(this).data('category');
        populateIconGrid(currentCategory);
    });
    
    // Icon suchen
    $('.cbd-icon-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        filterIcons(searchTerm);
    });
    
    // Icon auswählen
    $(document).on('click', '.cbd-icon-item', function() {
        $('.cbd-icon-item').removeClass('selected');
        $(this).addClass('selected');
        selectedIcon = $(this).data('icon');
    });
    
    // Icon bestätigen
    $('.cbd-select-icon').on('click', function() {
        if (selectedIcon) {
            $('input[name="features[icon][value]"]').val(selectedIcon);
            $('.cbd-selected-icon .dashicons').removeClass().addClass('dashicons ' + selectedIcon);
            $('.cbd-icon-name').text(selectedIcon);
            $('.cbd-icon-picker-modal').hide();
            $('body').removeClass('modal-open');
            updateLivePreview();
        }
    });
    
    function populateIconGrid(category) {
        var $grid = $('.cbd-icon-grid');
        $grid.empty();
        
        var iconsToShow = [];
        
        if (category === 'all') {
            // Alle Icons aus allen Kategorien
            Object.keys(dashicons).forEach(function(cat) {
                iconsToShow = iconsToShow.concat(dashicons[cat]);
            });
        } else {
            iconsToShow = dashicons[category] || [];
        }
        
        // Icons sortieren
        iconsToShow.sort();
        
        iconsToShow.forEach(function(icon) {
            var iconName = icon.replace('dashicons-', '');
            var isSelected = icon === selectedIcon ? 'selected' : '';
            var $iconItem = $('<div class="cbd-icon-item ' + isSelected + '" data-icon="' + icon + '">' +
                '<span class="dashicons ' + icon + '"></span>' +
                '<div class="cbd-icon-label">' + iconName + '</div>' +
                '</div>');
            $grid.append($iconItem);
        });
    }
    
    function filterIcons(searchTerm) {
        $('.cbd-icon-item').each(function() {
            var iconName = $(this).data('icon').toLowerCase();
            var iconLabel = $(this).find('.cbd-icon-label').text().toLowerCase();
            
            if (iconName.includes(searchTerm) || iconLabel.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    // Background Type Toggle
    $('input[name="styles[background][type]"]').on('change', function() {
        var selectedType = $(this).val();
        
        $('.cbd-bg-color-options, .cbd-bg-gradient-options').hide();
        
        if (selectedType === 'color') {
            $('.cbd-bg-color-options').show();
        } else if (selectedType === 'gradient') {
            $('.cbd-bg-gradient-options').show();
        }
        
        updateLivePreview();
    });
    
    // Range Input Live Update
    $('.cbd-range-input').on('input', function() {
        var $input = $(this);
        var $valueSpan = $input.siblings('.cbd-range-value');
        $valueSpan.text($input.val() + '°');
        updateLivePreview();
    });
    
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
        var bgType = $('input[name="styles[background][type]"]:checked').val() || 'color';
        var bgColor = $('input[name="styles[background][color]"]').val() || '#ffffff';
        
        // Background gradient styles
        var gradientType = $('select[name="styles[background][gradient][type]"]').val() || 'linear';
        var gradientAngle = $('input[name="styles[background][gradient][angle]"]').val() || '45';
        var gradientColor1 = $('input[name="styles[background][gradient][color1]"]').val() || '#ff6b6b';
        var gradientColor2 = $('input[name="styles[background][gradient][color2]"]').val() || '#4ecdc4';
        var gradientColor3 = $('input[name="styles[background][gradient][color3]"]').val() || '';
        
        // Build background value
        var backgroundValue = bgColor;
        if (bgType === 'gradient') {
            var colors = gradientColor3 ? gradientColor1 + ', ' + gradientColor3 + ', ' + gradientColor2 : gradientColor1 + ', ' + gradientColor2;
            
            if (gradientType === 'linear') {
                backgroundValue = 'linear-gradient(' + gradientAngle + 'deg, ' + colors + ')';
            } else if (gradientType === 'radial') {
                backgroundValue = 'radial-gradient(circle, ' + colors + ')';
            } else if (gradientType === 'conic') {
                backgroundValue = 'conic-gradient(from ' + gradientAngle + 'deg, ' + colors + ')';
            }
        }
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
            'background': backgroundValue,
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