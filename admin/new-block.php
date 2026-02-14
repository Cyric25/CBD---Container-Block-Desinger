<?php
/**
 * Container Block Designer - New/Edit Block Page
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Output Buffering starten um Headers bereits sent zu verhindern
ob_start();

// Formularverarbeitung
if (isset($_POST['cbd_save_block']) && isset($_POST['cbd_nonce']) && wp_verify_nonce($_POST['cbd_nonce'], 'cbd_save_block')) {
    // WICHTIG: Formularverarbeitung MUSS VOR jeglichem Output erfolgen
    
    global $wpdb;
    
    // Block ID holen
    $block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;

    // Prüfe ob Benutzer Styles bearbeiten kann
    $can_edit_styles = cbd_user_can_edit_styles();
    
    // Daten sammeln
    $name = sanitize_text_field($_POST['name']);
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Styles sammeln - Verwende korrekte Field-Namen
    $styles = array(
        'padding' => array(
            'top' => intval($_POST['styles']['padding']['top'] ?? $_POST['padding_top'] ?? 20),
            'right' => intval($_POST['styles']['padding']['right'] ?? $_POST['padding_right'] ?? 20),
            'bottom' => intval($_POST['styles']['padding']['bottom'] ?? $_POST['padding_bottom'] ?? 20),
            'left' => intval($_POST['styles']['padding']['left'] ?? $_POST['padding_left'] ?? 20)
        ),
        'background' => array(
            'type' => sanitize_text_field($_POST['styles']['background']['type'] ?? 'color'),
            'color' => sanitize_hex_color($_POST['styles']['background']['color'] ?? $_POST['background_color'] ?? '#ffffff'),
            'gradient' => array(
                'type' => sanitize_text_field($_POST['styles']['background']['gradient']['type'] ?? 'linear'),
                'angle' => intval($_POST['styles']['background']['gradient']['angle'] ?? 45),
                'color1' => sanitize_hex_color($_POST['styles']['background']['gradient']['color1'] ?? '#ff6b6b'),
                'color2' => sanitize_hex_color($_POST['styles']['background']['gradient']['color2'] ?? '#4ecdc4'),
                'color3' => sanitize_hex_color($_POST['styles']['background']['gradient']['color3'] ?? '')
            )
        ),
        'border' => array(
            'width' => intval($_POST['styles']['border']['width'] ?? $_POST['border_width'] ?? 1),
            'color' => sanitize_hex_color($_POST['styles']['border']['color'] ?? $_POST['border_color'] ?? '#e0e0e0'),
            'style' => sanitize_text_field($_POST['styles']['border']['style'] ?? $_POST['border_style'] ?? 'solid'),
            'radius' => intval($_POST['styles']['border']['radius'] ?? $_POST['border_radius'] ?? 4)
        ),
        'text' => array(
            'color' => sanitize_hex_color($_POST['styles']['text']['color'] ?? $_POST['text_color'] ?? '#333333'),
            'alignment' => sanitize_text_field($_POST['styles']['text']['alignment'] ?? $_POST['text_alignment'] ?? 'left')
        ),
        'shadow' => array(
            'outer' => array(
                'enabled' => isset($_POST['styles']['shadow']['outer']['enabled']),
                'x' => intval($_POST['styles']['shadow']['outer']['x'] ?? 0),
                'y' => intval($_POST['styles']['shadow']['outer']['y'] ?? 4),
                'blur' => intval($_POST['styles']['shadow']['outer']['blur'] ?? 6),
                'spread' => intval($_POST['styles']['shadow']['outer']['spread'] ?? 0),
                'color' => sanitize_hex_color($_POST['styles']['shadow']['outer']['color'] ?? '#00000040')
            ),
            'inner' => array(
                'enabled' => isset($_POST['styles']['shadow']['inner']['enabled']),
                'x' => intval($_POST['styles']['shadow']['inner']['x'] ?? 0),
                'y' => intval($_POST['styles']['shadow']['inner']['y'] ?? 2),
                'blur' => intval($_POST['styles']['shadow']['inner']['blur'] ?? 4),
                'spread' => intval($_POST['styles']['shadow']['inner']['spread'] ?? 0),
                'color' => sanitize_hex_color($_POST['styles']['shadow']['inner']['color'] ?? '#00000030')
            )
        ),
        'effects' => array(
            'glassmorphism' => array(
                'enabled' => isset($_POST['styles']['effects']['glassmorphism']['enabled']),
                'opacity' => floatval($_POST['styles']['effects']['glassmorphism']['opacity'] ?? 0.1),
                'blur' => intval($_POST['styles']['effects']['glassmorphism']['blur'] ?? 10),
                'saturate' => intval($_POST['styles']['effects']['glassmorphism']['saturate'] ?? 100),
                'color' => sanitize_hex_color($_POST['styles']['effects']['glassmorphism']['color'] ?? '#ffffff')
            ),
            'filters' => array(
                'brightness' => intval($_POST['styles']['effects']['filters']['brightness'] ?? 100),
                'contrast' => intval($_POST['styles']['effects']['filters']['contrast'] ?? 100),
                'hue' => intval($_POST['styles']['effects']['filters']['hue'] ?? 0)
            ),
            'neumorphism' => array(
                'enabled' => isset($_POST['styles']['effects']['neumorphism']['enabled']),
                'style' => sanitize_text_field($_POST['styles']['effects']['neumorphism']['style'] ?? 'raised'),
                'intensity' => intval($_POST['styles']['effects']['neumorphism']['intensity'] ?? 10),
                'background' => sanitize_hex_color($_POST['styles']['effects']['neumorphism']['background'] ?? '#e0e0e0'),
                'distance' => intval($_POST['styles']['effects']['neumorphism']['distance'] ?? 15)
            ),
            'animation' => array(
                'hover' => sanitize_text_field($_POST['styles']['effects']['animation']['hover'] ?? 'none'),
                'origin' => sanitize_text_field($_POST['styles']['effects']['animation']['origin'] ?? 'center'),
                'duration' => intval($_POST['styles']['effects']['animation']['duration'] ?? 300),
                'easing' => sanitize_text_field($_POST['styles']['effects']['animation']['easing'] ?? 'ease')
            )
        )
    );
    
    // Features sammeln - mit korrekten Feldnamen
    $features = array(
        'icon' => array(
            'enabled' => isset($_POST['features']['icon']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['icon']['value'] ?? 'dashicons-admin-generic')
        ),
        'collapse' => array(
            'enabled' => isset($_POST['features']['collapse']['enabled']) ? true : false,
            'defaultState' => sanitize_text_field($_POST['features']['collapse']['defaultState'] ?? 'expanded')
        ),
        'numbering' => array(
            'enabled' => isset($_POST['features']['numbering']['enabled']) ? true : false,
            'format' => sanitize_text_field($_POST['features']['numbering']['format'] ?? 'numeric')
        ),
        'copyText' => array(
            'enabled' => isset($_POST['features']['copyText']['enabled']) ? true : false,
            'buttonText' => sanitize_text_field($_POST['features']['copyText']['buttonText'] ?? 'Text kopieren')
        ),
        'screenshot' => array(
            'enabled' => isset($_POST['features']['screenshot']['enabled']) ? true : false,
            'buttonText' => sanitize_text_field($_POST['features']['screenshot']['buttonText'] ?? 'Screenshot')
        ),
        'boardMode' => array(
            'enabled' => isset($_POST['features']['boardMode']['enabled']) ? true : false,
            'boardColor' => sanitize_hex_color($_POST['features']['boardMode']['boardColor'] ?? '#1a472a') ?: '#1a472a'
        )
    );

    // Config sammeln
    $config = array(
        'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
        'templateLock' => isset($_POST['template_lock']) ? false : true
    );
    
    // Slug aus dem Namen generieren
    $slug = sanitize_title($name);
    // Falls der Slug leer ist (z.B. bei nicht-lateinischen Zeichen), verwende einen Fallback
    if (empty($slug)) {
        $slug = 'block-' . time();
    }
    
    // Überprüfe ob der Slug bereits existiert und mache ihn eindeutig
    $original_slug = $slug;
    $counter = 1;
    
    // Bei Update: Überprüfe nicht den aktuellen Block
    $exclude_id = $block_id ? $block_id : 0;
    
    while (true) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s AND id != %d", 
            $slug, 
            $exclude_id
        ));
        
        if (!$existing) {
            break; // Slug ist eindeutig
        }
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    // Daten vorbereiten
    $data = array(
        'name' => $name,
        'title' => $title,
        'slug' => $slug,
        'description' => $description,
        'config' => json_encode($config),
        'styles' => json_encode($styles),
        'features' => json_encode($features),
        'status' => $status,
        'updated_at' => current_time('mysql')
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
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert(CBD_TABLE_BLOCKS, $data);
        if ($result) {
            $block_id = $wpdb->insert_id;
        }
    }
    
    if ($result !== false) {
        // Erfolgreich gespeichert - setze Variablen für JavaScript-Redirect
        if (!isset($_GET['block_id']) && $block_id) {
            // Neuer Block erstellt
            $success_message = __('Block erfolgreich erstellt.', 'container-block-designer');
            $redirect_url = admin_url('admin.php?page=container-block-designer');
            $js_redirect = true;
            error_log('[CBD New Block] New block created with ID: ' . $block_id);
        } else {
            // Block aktualisiert
            $success_message = __('Block erfolgreich aktualisiert.', 'container-block-designer');
            $redirect_url = admin_url('admin.php?page=cbd-edit-block&block_id=' . $block_id);
            $js_redirect = true;
            error_log('[CBD New Block] Block updated with ID: ' . $block_id);
        }
    } else {
        error_log('[CBD New Block] Database operation failed');
        $error_message = __('Fehler beim Speichern des Blocks.', 'container-block-designer');
        if ($wpdb->last_error) {
            $error_message .= ' ' . $wpdb->last_error;
        }
    }
}

// ERST JETZT beginnt die Ausgabe
global $wpdb;

$block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
$block = null;

// Admin-Titel für WordPress-Header setzen
$admin_title = $block_id ? __('Block bearbeiten', 'container-block-designer') : __('Neuen Block erstellen', 'container-block-designer');
$GLOBALS['title'] = $admin_title . ' - ' . get_option('blogname');

// Block laden wenn ID vorhanden
if ($block_id > 0) {
    $block = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $block_id
    ), ARRAY_A);
    
    if ($block) {
        $block['config'] = json_decode($block['config'], true);
        $block['styles'] = json_decode($block['styles'], true);  
        $block['features'] = json_decode($block['features'], true);
    }
}

?>
<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php echo $block_id ? __('Block bearbeiten', 'container-block-designer') : __('Neuen Block erstellen', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php _e('Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" class="cbd-block-form">
        <?php wp_nonce_field('cbd_save_block', 'cbd_nonce'); ?>
        
        <div class="cbd-form-wrapper">
            <div class="cbd-main-settings">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Grundeinstellungen', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="name"><?php _e('Name (intern)', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="name" name="name" value="<?php echo esc_attr($block['name'] ?? ''); ?>" class="regular-text" required>
                                    <p class="description"><?php _e('Interner Name für die Verwaltung (keine Leerzeichen, nur Kleinbuchstaben und Unterstriche)', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="title"><?php _e('Titel', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="title" name="title" value="<?php echo esc_attr($block['title'] ?? ''); ?>" class="regular-text" required>
                                    <p class="description"><?php _e('Wird im Block-Editor angezeigt', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($block['description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Status', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="status" value="1" <?php checked(($block['status'] ?? 'active') === 'active'); ?>>
                                        <?php _e('Aktiv', 'container-block-designer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox cbd-style-section">
                    <h2 class="hndle"><?php _e('Styling', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Padding', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-spacing-controls">
                                        <label>
                                            <?php _e('Oben:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[padding][top]" value="<?php echo esc_attr($block['styles']['padding']['top'] ?? 20); ?>" min="0" max="100" class="small-text">px
                                        </label>
                                        <label>
                                            <?php _e('Rechts:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[padding][right]" value="<?php echo esc_attr($block['styles']['padding']['right'] ?? 20); ?>" min="0" max="100" class="small-text">px
                                        </label>
                                        <label>
                                            <?php _e('Unten:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[padding][bottom]" value="<?php echo esc_attr($block['styles']['padding']['bottom'] ?? 20); ?>" min="0" max="100" class="small-text">px
                                        </label>
                                        <label>
                                            <?php _e('Links:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[padding][left]" value="<?php echo esc_attr($block['styles']['padding']['left'] ?? 20); ?>" min="0" max="100" class="small-text">px
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Schatten', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-shadow-controls">
                                        <fieldset>
                                            <legend><?php _e('Äußerer Schatten (Box Shadow)', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[shadow][outer][enabled]" value="1" <?php checked($block['styles']['shadow']['outer']['enabled'] ?? false); ?>>
                                                <?php _e('Aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-shadow-options">
                                                <label>
                                                    <?php _e('Horizontal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][x]" value="<?php echo esc_attr($block['styles']['shadow']['outer']['x'] ?? 0); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Vertikal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][y]" value="<?php echo esc_attr($block['styles']['shadow']['outer']['y'] ?? 4); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Unschärfe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][blur]" value="<?php echo esc_attr($block['styles']['shadow']['outer']['blur'] ?? 6); ?>" min="0" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Größe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][outer][spread]" value="<?php echo esc_attr($block['styles']['shadow']['outer']['spread'] ?? 0); ?>" min="-25" max="25" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Farbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[shadow][outer][color]" value="<?php echo esc_attr($block['styles']['shadow']['outer']['color'] ?? '#00000040'); ?>" class="cbd-color-picker">
                                                </label>
                                            </div>
                                        </fieldset>
                                        <br>
                                        <fieldset>
                                            <legend><?php _e('Innerer Schatten (Inset Shadow)', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[shadow][inner][enabled]" value="1" <?php checked($block['styles']['shadow']['inner']['enabled'] ?? false); ?>>
                                                <?php _e('Aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-shadow-options">
                                                <label>
                                                    <?php _e('Horizontal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][x]" value="<?php echo esc_attr($block['styles']['shadow']['inner']['x'] ?? 0); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Vertikal:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][y]" value="<?php echo esc_attr($block['styles']['shadow']['inner']['y'] ?? 2); ?>" min="-50" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Unschärfe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][blur]" value="<?php echo esc_attr($block['styles']['shadow']['inner']['blur'] ?? 4); ?>" min="0" max="50" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Größe:', 'container-block-designer'); ?>
                                                    <input type="number" name="styles[shadow][inner][spread]" value="<?php echo esc_attr($block['styles']['shadow']['inner']['spread'] ?? 0); ?>" min="-25" max="25" class="small-text">px
                                                </label>
                                                <label>
                                                    <?php _e('Farbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[shadow][inner][color]" value="<?php echo esc_attr($block['styles']['shadow']['inner']['color'] ?? '#00000030'); ?>" class="cbd-color-picker">
                                                </label>
                                            </div>
                                        </fieldset>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Hintergrund', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-background-controls">
                                        <fieldset>
                                            <legend><?php _e('Hintergrund-Typ', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="radio" name="styles[background][type]" value="color" <?php checked($block['styles']['background']['type'] ?? 'color', 'color'); ?>>
                                                <?php _e('Farbe', 'container-block-designer'); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="styles[background][type]" value="gradient" <?php checked($block['styles']['background']['type'] ?? 'color', 'gradient'); ?>>
                                                <?php _e('Gradient', 'container-block-designer'); ?>
                                            </label>
                                        </fieldset>
                                        
                                        <!-- Farbe Optionen -->
                                        <div class="cbd-bg-color-options" style="<?php echo ($block['styles']['background']['type'] ?? 'color') !== 'color' ? 'display: none;' : ''; ?>">
                                            <label>
                                                <?php _e('Farbe:', 'container-block-designer'); ?>
                                                <input type="color" name="styles[background][color]" value="<?php echo esc_attr($block['styles']['background']['color'] ?? '#ffffff'); ?>" class="cbd-color-picker">
                                            </label>
                                        </div>
                                        
                                        <!-- Gradient Optionen -->
                                        <div class="cbd-bg-gradient-options" style="<?php echo ($block['styles']['background']['type'] ?? 'color') !== 'gradient' ? 'display: none;' : ''; ?>">
                                            <div class="cbd-gradient-controls">
                                                <label>
                                                    <?php _e('Gradient-Typ:', 'container-block-designer'); ?>
                                                    <select name="styles[background][gradient][type]">
                                                        <option value="linear" <?php selected($block['styles']['background']['gradient']['type'] ?? 'linear', 'linear'); ?>><?php _e('Linear', 'container-block-designer'); ?></option>
                                                        <option value="radial" <?php selected($block['styles']['background']['gradient']['type'] ?? 'linear', 'radial'); ?>><?php _e('Radial', 'container-block-designer'); ?></option>
                                                        <option value="conic" <?php selected($block['styles']['background']['gradient']['type'] ?? 'linear', 'conic'); ?>><?php _e('Konisch', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <?php _e('Richtung (Grad):', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[background][gradient][angle]" min="0" max="360" value="<?php echo esc_attr($block['styles']['background']['gradient']['angle'] ?? 45); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['background']['gradient']['angle'] ?? 45); ?>°</span>
                                                </label>
                                                <label>
                                                    <?php _e('Startfarbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[background][gradient][color1]" value="<?php echo esc_attr($block['styles']['background']['gradient']['color1'] ?? '#ff6b6b'); ?>" class="cbd-color-picker">
                                                </label>
                                                <label>
                                                    <?php _e('Endfarbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[background][gradient][color2]" value="<?php echo esc_attr($block['styles']['background']['gradient']['color2'] ?? '#4ecdc4'); ?>" class="cbd-color-picker">
                                                </label>
                                                <label>
                                                    <?php _e('Mittlere Farbe (optional):', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[background][gradient][color3]" value="<?php echo esc_attr($block['styles']['background']['gradient']['color3'] ?? ''); ?>" class="cbd-color-picker">
                                                    <small><?php _e('Leer lassen für 2-Farben-Gradient', 'container-block-designer'); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="text_color"><?php _e('Textfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="text_color" name="styles[text][color]" value="<?php echo esc_attr($block['styles']['text']['color'] ?? '#333333'); ?>" class="cbd-color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="text_alignment"><?php _e('Textausrichtung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="text_alignment" name="styles[text][alignment]">
                                        <option value="left" <?php selected($block['styles']['text']['alignment'] ?? 'left', 'left'); ?>><?php _e('Links', 'container-block-designer'); ?></option>
                                        <option value="center" <?php selected($block['styles']['text']['alignment'] ?? 'left', 'center'); ?>><?php _e('Zentriert', 'container-block-designer'); ?></option>
                                        <option value="right" <?php selected($block['styles']['text']['alignment'] ?? 'left', 'right'); ?>><?php _e('Rechts', 'container-block-designer'); ?></option>
                                        <option value="justify" <?php selected($block['styles']['text']['alignment'] ?? 'left', 'justify'); ?>><?php _e('Blocksatz', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Moderne Effekte', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-effects-controls">
                                        <!-- Glassmorphism -->
                                        <fieldset>
                                            <legend><?php _e('Glassmorphism', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[effects][glassmorphism][enabled]" value="1" <?php checked($block['styles']['effects']['glassmorphism']['enabled'] ?? false); ?>>
                                                <?php _e('Glaseffekt aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-glassmorphism-options">
                                                <label>
                                                    <?php _e('Hintergrund-Transparenz:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][glassmorphism][opacity]" min="0" max="1" step="0.1" value="<?php echo esc_attr($block['styles']['effects']['glassmorphism']['opacity'] ?? 0.1); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['glassmorphism']['opacity'] ?? 0.1); ?></span>
                                                </label>
                                                <label>
                                                    <?php _e('Unschärfe-Stärke:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][glassmorphism][blur]" min="0" max="50" value="<?php echo esc_attr($block['styles']['effects']['glassmorphism']['blur'] ?? 10); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['glassmorphism']['blur'] ?? 10); ?>px</span>
                                                </label>
                                                <label>
                                                    <?php _e('Sättigung:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][glassmorphism][saturate]" min="0" max="200" value="<?php echo esc_attr($block['styles']['effects']['glassmorphism']['saturate'] ?? 100); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['glassmorphism']['saturate'] ?? 100); ?>%</span>
                                                </label>
                                                <label>
                                                    <?php _e('Glasfarbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[effects][glassmorphism][color]" value="<?php echo esc_attr($block['styles']['effects']['glassmorphism']['color'] ?? '#ffffff'); ?>" class="cbd-color-picker">
                                                </label>
                                            </div>
                                        </fieldset>
                                        
                                        <!-- Neumorphism -->
                                        <fieldset>
                                            <legend><?php _e('Neumorphism', 'container-block-designer'); ?></legend>
                                            <label>
                                                <input type="checkbox" name="styles[effects][neumorphism][enabled]" value="1" <?php checked($block['styles']['effects']['neumorphism']['enabled'] ?? false); ?>>
                                                <?php _e('Neumorphism-Effekt aktivieren', 'container-block-designer'); ?>
                                            </label>
                                            <br><br>
                                            <div class="cbd-neumorphism-options">
                                                <label>
                                                    <?php _e('Stil:', 'container-block-designer'); ?>
                                                    <select name="styles[effects][neumorphism][style]">
                                                        <option value="raised" <?php selected($block['styles']['effects']['neumorphism']['style'] ?? 'raised', 'raised'); ?>><?php _e('Erhaben', 'container-block-designer'); ?></option>
                                                        <option value="inset" <?php selected($block['styles']['effects']['neumorphism']['style'] ?? 'raised', 'inset'); ?>><?php _e('Eingedrückt', 'container-block-designer'); ?></option>
                                                        <option value="flat" <?php selected($block['styles']['effects']['neumorphism']['style'] ?? 'raised', 'flat'); ?>><?php _e('Flach', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <?php _e('Schatten-Intensität:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][neumorphism][intensity]" min="1" max="20" value="<?php echo esc_attr($block['styles']['effects']['neumorphism']['intensity'] ?? 10); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['neumorphism']['intensity'] ?? 10); ?>px</span>
                                                </label>
                                                <label>
                                                    <?php _e('Hintergrundfarbe:', 'container-block-designer'); ?>
                                                    <input type="color" name="styles[effects][neumorphism][background]" value="<?php echo esc_attr($block['styles']['effects']['neumorphism']['background'] ?? '#e0e0e0'); ?>" class="cbd-color-picker">
                                                    <small><?php _e('Neumorphism funktioniert am besten mit neutralen Farben', 'container-block-designer'); ?></small>
                                                </label>
                                                <label>
                                                    <?php _e('Schatten-Distanz:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][neumorphism][distance]" min="5" max="30" value="<?php echo esc_attr($block['styles']['effects']['neumorphism']['distance'] ?? 15); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['neumorphism']['distance'] ?? 15); ?>px</span>
                                                </label>
                                            </div>
                                        </fieldset>
                                        
                                        <!-- Weitere moderne Effekte -->
                                        <fieldset>
                                            <legend><?php _e('CSS Filter', 'container-block-designer'); ?></legend>
                                            <div class="cbd-filter-controls">
                                                <label>
                                                    <?php _e('Helligkeit:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][filters][brightness]" min="0" max="200" value="<?php echo esc_attr($block['styles']['effects']['filters']['brightness'] ?? 100); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['filters']['brightness'] ?? 100); ?>%</span>
                                                </label>
                                                <label>
                                                    <?php _e('Kontrast:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][filters][contrast]" min="0" max="200" value="<?php echo esc_attr($block['styles']['effects']['filters']['contrast'] ?? 100); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['filters']['contrast'] ?? 100); ?>%</span>
                                                </label>
                                                <label>
                                                    <?php _e('Farbton-Rotation:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][filters][hue]" min="0" max="360" value="<?php echo esc_attr($block['styles']['effects']['filters']['hue'] ?? 0); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['filters']['hue'] ?? 0); ?>°</span>
                                                </label>
                                            </div>
                                        </fieldset>
                                        
                                        <!-- Animations & Transform -->
                                        <fieldset>
                                            <legend><?php _e('Animation & Transform', 'container-block-designer'); ?></legend>
                                            <div class="cbd-animation-controls">
                                                <label>
                                                    <?php _e('Hover-Animation:', 'container-block-designer'); ?>
                                                    <select name="styles[effects][animation][hover]">
                                                        <option value="none" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'none'); ?>><?php _e('Keine', 'container-block-designer'); ?></option>
                                                        <option value="lift" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'lift'); ?>><?php _e('Anheben', 'container-block-designer'); ?></option>
                                                        <option value="scale" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'scale'); ?>><?php _e('Vergrößern', 'container-block-designer'); ?></option>
                                                        <option value="rotate" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'rotate'); ?>><?php _e('Rotieren', 'container-block-designer'); ?></option>
                                                        <option value="pulse" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'pulse'); ?>><?php _e('Pulsieren', 'container-block-designer'); ?></option>
                                                        <option value="bounce" <?php selected($block['styles']['effects']['animation']['hover'] ?? 'none', 'bounce'); ?>><?php _e('Springen', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <?php _e('Transformations-Ursprung:', 'container-block-designer'); ?>
                                                    <select name="styles[effects][animation][origin]">
                                                        <option value="center" <?php selected($block['styles']['effects']['animation']['origin'] ?? 'center', 'center'); ?>><?php _e('Zentrum', 'container-block-designer'); ?></option>
                                                        <option value="top left" <?php selected($block['styles']['effects']['animation']['origin'] ?? 'center', 'top left'); ?>><?php _e('Oben Links', 'container-block-designer'); ?></option>
                                                        <option value="top right" <?php selected($block['styles']['effects']['animation']['origin'] ?? 'center', 'top right'); ?>><?php _e('Oben Rechts', 'container-block-designer'); ?></option>
                                                        <option value="bottom left" <?php selected($block['styles']['effects']['animation']['origin'] ?? 'center', 'bottom left'); ?>><?php _e('Unten Links', 'container-block-designer'); ?></option>
                                                        <option value="bottom right" <?php selected($block['styles']['effects']['animation']['origin'] ?? 'center', 'bottom right'); ?>><?php _e('Unten Rechts', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <?php _e('Animation-Dauer:', 'container-block-designer'); ?>
                                                    <input type="range" name="styles[effects][animation][duration]" min="100" max="2000" step="100" value="<?php echo esc_attr($block['styles']['effects']['animation']['duration'] ?? 300); ?>" class="cbd-range-input">
                                                    <span class="cbd-range-value"><?php echo esc_attr($block['styles']['effects']['animation']['duration'] ?? 300); ?>ms</span>
                                                </label>
                                                <label>
                                                    <?php _e('Easing-Funktion:', 'container-block-designer'); ?>
                                                    <select name="styles[effects][animation][easing]">
                                                        <option value="ease" <?php selected($block['styles']['effects']['animation']['easing'] ?? 'ease', 'ease'); ?>><?php _e('Standard', 'container-block-designer'); ?></option>
                                                        <option value="ease-in" <?php selected($block['styles']['effects']['animation']['easing'] ?? 'ease', 'ease-in'); ?>><?php _e('Beschleunigen', 'container-block-designer'); ?></option>
                                                        <option value="ease-out" <?php selected($block['styles']['effects']['animation']['easing'] ?? 'ease', 'ease-out'); ?>><?php _e('Verlangsamen', 'container-block-designer'); ?></option>
                                                        <option value="ease-in-out" <?php selected($block['styles']['effects']['animation']['easing'] ?? 'ease', 'ease-in-out'); ?>><?php _e('Smooth', 'container-block-designer'); ?></option>
                                                        <option value="cubic-bezier(0.68, -0.55, 0.265, 1.55)" <?php selected($block['styles']['effects']['animation']['easing'] ?? 'ease', 'cubic-bezier(0.68, -0.55, 0.265, 1.55)'); ?>><?php _e('Elastisch', 'container-block-designer'); ?></option>
                                                    </select>
                                                </label>
                                            </div>
                                        </fieldset>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Rahmen', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-border-controls">
                                        <label>
                                            <?php _e('Breite:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[border][width]" value="<?php echo esc_attr($block['styles']['border']['width'] ?? 1); ?>" min="0" max="10" class="small-text">px
                                        </label>
                                        <label>
                                            <?php _e('Farbe:', 'container-block-designer'); ?>
                                            <input type="color" name="styles[border][color]" value="<?php echo esc_attr($block['styles']['border']['color'] ?? '#e0e0e0'); ?>" class="cbd-color-picker">
                                        </label>
                                        <label>
                                            <?php _e('Stil:', 'container-block-designer'); ?>
                                            <select name="styles[border][style]">
                                                <option value="solid" <?php selected($block['styles']['border']['style'] ?? 'solid', 'solid'); ?>><?php _e('Durchgehend', 'container-block-designer'); ?></option>
                                                <option value="dashed" <?php selected($block['styles']['border']['style'] ?? 'solid', 'dashed'); ?>><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                                <option value="dotted" <?php selected($block['styles']['border']['style'] ?? 'solid', 'dotted'); ?>><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                                <option value="none" <?php selected($block['styles']['border']['style'] ?? 'solid', 'none'); ?>><?php _e('Kein Rahmen', 'container-block-designer'); ?></option>
                                            </select>
                                        </label>
                                        <label>
                                            <?php _e('Radius:', 'container-block-designer'); ?>
                                            <input type="number" name="styles[border][radius]" value="<?php echo esc_attr($block['styles']['border']['radius'] ?? 4); ?>" min="0" max="50" class="small-text">px
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Block-Konfiguration', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Innere Blöcke', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="allow_inner_blocks" value="1" <?php checked($block['config']['allowInnerBlocks'] ?? true); ?>>
                                        <?php _e('Innere Blöcke erlauben', 'container-block-designer'); ?>
                                    </label>
                                    <p class="description"><?php _e('Erlaubt das Hinzufügen von anderen Blöcken innerhalb dieses Containers', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Template Lock', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="template_lock" value="1" <?php checked(!($block['config']['templateLock'] ?? false)); ?>>
                                        <?php _e('Template nicht sperren', 'container-block-designer'); ?>
                                    </label>
                                    <p class="description"><?php _e('Wenn aktiviert, können Benutzer die inneren Blöcke frei bearbeiten', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sticky Toggle Button -->
            <button type="button" class="cbd-sticky-toggle" title="Live-Preview anheften">
                <span class="dashicons dashicons-sticky"></span>
            </button>

            <div class="cbd-sidebar">
                <!-- Live Preview -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Live Preview', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <div id="cbd-live-preview" class="cbd-preview-container">
                            <div id="cbd-preview-block" class="cbd-preview-block">
                                <div class="cbd-preview-content">
                                    <h4 id="cbd-preview-title">Neuer Block</h4>
                                    <p id="cbd-preview-description">Block Beschreibung...</p>
                                    <div class="cbd-preview-features" id="cbd-preview-features"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Features', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[icon][enabled]" value="1" <?php checked($block['features']['icon']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Icon', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['icon']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label><?php _e('Icon auswählen:', 'container-block-designer'); ?></label>
                                <div class="cbd-icon-picker">
                                    <input type="hidden" id="icon_value" name="features[icon][value]" value="<?php echo esc_attr($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?>">
                                    
                                    <!-- Selected Icon Display -->
                                    <div class="cbd-selected-icon">
                                        <span class="dashicons <?php echo esc_attr($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?>"></span>
                                        <span class="cbd-icon-name"><?php echo esc_html($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?></span>
                                        <button type="button" class="cbd-open-icon-picker button"><?php _e('Icon ändern', 'container-block-designer'); ?></button>
                                    </div>
                                    
                                    <!-- Icon is now fixed in top-left position with title -->
                                    <div style="margin-top: 15px; padding: 10px; background: #e8f4f8; border: 1px solid #2196f3; border-radius: 4px;">
                                        <p style="margin: 0; color: #1976d2; font-weight: 600;">
                                            <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                                            <?php _e('Das Icon wird automatisch in der linken oberen Ecke neben dem Titel angezeigt.', 'container-block-designer'); ?>
                                        </p>
                                    </div>

                                    <!-- Icon Picker Modal -->
                                    <div class="cbd-icon-picker-modal" style="display: none;">
                                        <div class="cbd-icon-picker-backdrop">
                                            <div class="cbd-icon-picker-content">
                                                <div class="cbd-icon-picker-header">
                                                    <h3><?php _e('Icon auswählen', 'container-block-designer'); ?></h3>
                                                    <button type="button" class="cbd-close-icon-picker">&times;</button>
                                                </div>

                                                <!-- Library Tabs -->
                                                <div class="cbd-icon-library-tabs">
                                                    <button type="button" class="cbd-library-tab active" data-library="dashicons">
                                                        <span class="dashicons dashicons-wordpress"></span>
                                                        <?php _e('Dashicons', 'container-block-designer'); ?>
                                                    </button>
                                                    <button type="button" class="cbd-library-tab" data-library="fontawesome">
                                                        <i class="fa-solid fa-font-awesome"></i>
                                                        <?php _e('Font Awesome', 'container-block-designer'); ?>
                                                    </button>
                                                    <button type="button" class="cbd-library-tab" data-library="material">
                                                        <span class="material-icons">star</span>
                                                        <?php _e('Material Icons', 'container-block-designer'); ?>
                                                    </button>
                                                    <button type="button" class="cbd-library-tab" data-library="lucide">
                                                        <i class="lucide lucide-zap"></i>
                                                        <?php _e('Lucide', 'container-block-designer'); ?>
                                                    </button>
                                                    <button type="button" class="cbd-library-tab" data-library="emoji">
                                                        <span style="font-size: 20px;">😀</span>
                                                        <?php _e('Emojis', 'container-block-designer'); ?>
                                                    </button>
                                                </div>

                                                <!-- Search -->
                                                <div class="cbd-icon-search">
                                                    <input type="text" placeholder="<?php _e('Icons durchsuchen...', 'container-block-designer'); ?>" class="cbd-icon-search-input">
                                                </div>

                                                <!-- Icon Categories (for libraries with categories) -->
                                                <div class="cbd-icon-categories">
                                                    <!-- Categories will be populated by JavaScript based on active library -->
                                                </div>

                                                <!-- Icon Grid -->
                                                <div class="cbd-icon-grid">
                                                    <!-- Icons will be populated by JavaScript -->
                                                </div>

                                                <!-- Emoji Picker Container (only visible when emoji tab is active) -->
                                                <div class="cbd-emoji-picker-container" style="display: none;">
                                                    <emoji-picker></emoji-picker>
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
                            </div>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[collapse][enabled]" value="1" <?php checked($block['features']['collapse']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Klappbar', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['collapse']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label for="collapse_state"><?php _e('Standard-Zustand:', 'container-block-designer'); ?></label>
                                <select id="collapse_state" name="features[collapse][defaultState]">
                                    <option value="expanded" <?php selected($block['features']['collapse']['defaultState'] ?? 'expanded', 'expanded'); ?>><?php _e('Ausgeklappt', 'container-block-designer'); ?></option>
                                    <option value="collapsed" <?php selected($block['features']['collapse']['defaultState'] ?? 'expanded', 'collapsed'); ?>><?php _e('Eingeklappt', 'container-block-designer'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[numbering][enabled]" value="1" <?php checked($block['features']['numbering']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Nummerierung', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['numbering']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label for="numbering_format"><?php _e('Format:', 'container-block-designer'); ?></label>
                                <select id="numbering_format" name="features[numbering][format]">
                                    <option value="numeric" <?php selected($block['features']['numbering']['format'] ?? 'numeric', 'numeric'); ?>><?php _e('1, 2, 3...', 'container-block-designer'); ?></option>
                                    <option value="alphabetic" <?php selected($block['features']['numbering']['format'] ?? 'numeric', 'alphabetic'); ?>><?php _e('A, B, C...', 'container-block-designer'); ?></option>
                                    <option value="roman" <?php selected($block['features']['numbering']['format'] ?? 'numeric', 'roman'); ?>><?php _e('I, II, III...', 'container-block-designer'); ?></option>
                                </select>
                                
                                <!-- Counting Mode -->
                                <div style="margin-top: 15px;">
                                    <label><?php _e('Zählmodus:', 'container-block-designer'); ?></label>
                                    <div class="cbd-counting-mode-options">
                                        <label class="cbd-radio-option">
                                            <input type="radio" name="features[numbering][countingMode]" value="same-design" <?php checked($block['features']['numbering']['countingMode'] ?? 'same-design', 'same-design'); ?>>
                                            <span class="cbd-radio-label">
                                                <strong><?php _e('Zähle Blöcke mit diesem Design', 'container-block-designer'); ?></strong>
                                                <small><?php _e('Nummerierung beginnt bei 1 für jeden Block-Typ', 'container-block-designer'); ?></small>
                                            </span>
                                        </label>
                                        <label class="cbd-radio-option">
                                            <input type="radio" name="features[numbering][countingMode]" value="all-blocks" <?php checked($block['features']['numbering']['countingMode'] ?? 'same-design', 'all-blocks'); ?>>
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
                                                <input type="radio" name="features[numbering][position]" value="top-left" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'top-left'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator top-left"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Oben Links', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="top-center" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'top-center'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator top-center"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Oben Mitte', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="top-right" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'top-right'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator top-right"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Oben Rechts', 'container-block-designer'); ?></span>
                                            </label>
                                        </div>
                                        <div class="cbd-position-row">
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="middle-left" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'middle-left'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator middle-left"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Mitte Links', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="middle-center" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'middle-center'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator middle-center"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Mitte', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="middle-right" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'middle-right'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator middle-right"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Mitte Rechts', 'container-block-designer'); ?></span>
                                            </label>
                                        </div>
                                        <div class="cbd-position-row">
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="bottom-left" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'bottom-left'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator bottom-left"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Unten Links', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="bottom-center" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'bottom-center'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator bottom-center"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Unten Mitte', 'container-block-designer'); ?></span>
                                            </label>
                                            <label class="cbd-position-option">
                                                <input type="radio" name="features[numbering][position]" value="bottom-right" <?php checked($block['features']['numbering']['position'] ?? 'top-left', 'bottom-right'); ?>>
                                                <span class="cbd-position-visual">
                                                    <span class="cbd-position-indicator bottom-right"></span>
                                                </span>
                                                <span class="cbd-position-label"><?php _e('Unten Rechts', 'container-block-designer'); ?></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[copyText][enabled]" value="1" <?php checked($block['features']['copyText']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Text kopieren', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['copyText']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label for="copy_text_button"><?php _e('Button-Text:', 'container-block-designer'); ?></label>
                                <input type="text" id="copy_text_button" name="features[copyText][buttonText]" value="<?php echo esc_attr($block['features']['copyText']['buttonText'] ?? 'Text kopieren'); ?>" placeholder="Text kopieren" class="regular-text">
                            </div>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[screenshot][enabled]" value="1" <?php checked($block['features']['screenshot']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Screenshot', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['screenshot']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label for="screenshot_button"><?php _e('Button-Text:', 'container-block-designer'); ?></label>
                                <input type="text" id="screenshot_button" name="features[screenshot][buttonText]" value="<?php echo esc_attr($block['features']['screenshot']['buttonText'] ?? 'Screenshot'); ?>" placeholder="Screenshot" class="regular-text">
                            </div>
                        </div>

                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[boardMode][enabled]" value="1" <?php checked($block['features']['boardMode']['enabled'] ?? false); ?> class="cbd-feature-toggle">
                                <strong><?php _e('Tafel-Modus', 'container-block-designer'); ?></strong>
                            </label>
                            <div class="cbd-feature-options" <?php echo !($block['features']['boardMode']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                                <label for="board_color"><?php _e('Tafel-Hintergrundfarbe:', 'container-block-designer'); ?></label>
                                <input type="color" id="board_color" name="features[boardMode][boardColor]" value="<?php echo esc_attr($block['features']['boardMode']['boardColor'] ?? '#1a472a'); ?>">
                                <p class="description"><?php _e('Hintergrundfarbe der Zeichenfläche (Standard: Dunkelgrün)', 'container-block-designer'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><?php _e('Aktionen', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <button type="submit" name="cbd_save_block" class="button button-primary button-large">
                            <?php echo $block_id ? __('Block aktualisieren', 'container-block-designer') : __('Block erstellen', 'container-block-designer'); ?>
                        </button>
                        
                        <?php if ($block_id): ?>
                            <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-secondary">
                                <?php _e('Neuer Block', 'container-block-designer'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($block_id): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Block-Info', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <p><strong><?php _e('Block ID:', 'container-block-designer'); ?></strong> <?php echo $block_id; ?></p>
                        <?php if (!empty($block['created_at'])): ?>
                            <p><strong><?php _e('Erstellt:', 'container-block-designer'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($block['created_at'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($block['updated_at'])): ?>
                            <p><strong><?php _e('Aktualisiert:', 'container-block-designer'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($block['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<style>
.cbd-form-wrapper {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.cbd-main-settings {
    flex: 1;
    min-width: 0;
}

.cbd-sidebar {
    width: 300px;
    flex-shrink: 0;
}

.cbd-spacing-controls,
.cbd-border-controls {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.cbd-spacing-controls label,
.cbd-border-controls label {
    display: flex;
    align-items: center;
    gap: 5px;
}

.cbd-spacing-controls input[type="number"],
.cbd-border-controls input[type="number"] {
    width: 60px;
}

.cbd-feature-item {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e0e0e0;
}

.cbd-feature-item:last-child {
    border-bottom: none;
}

.cbd-feature-item label {
    display: block;
    margin-bottom: 5px;
}

.cbd-feature-item input[type="text"],
.cbd-feature-item select {
    width: 100%;
    margin-top: 5px;
}

.cbd-feature-options {
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.cbd-feature-options label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

@media (max-width: 1200px) {
    .cbd-form-wrapper {
        flex-direction: column;
    }
    
    .cbd-sidebar {
        width: 100%;
    }
}

/* Live Preview Styles */
.cbd-preview-container {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    margin-top: 15px;
}

.cbd-preview-block {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 20px;
    transition: all 0.3s ease;
}

.cbd-preview-content h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: inherit;
}

.cbd-preview-content p {
    margin: 0 0 15px 0;
    color: inherit;
    opacity: 0.8;
}

.cbd-preview-features {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.cbd-preview-feature {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: rgba(0, 123, 186, 0.1);
    border-radius: 3px;
    font-size: 12px;
    color: #007cba;
}

.cbd-preview-feature.inactive {
    background: rgba(0, 0, 0, 0.05);
    color: #666;
}

.cbd-preview-feature .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Sticky Live Preview */
.cbd-sidebar.sticky {
    position: fixed;
    top: 32px; /* WordPress admin bar height */
    right: 20px;
    width: 300px;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    z-index: 1000;
    transition: all 0.3s ease;
}

.cbd-sidebar.sticky .postbox {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    border: 1px solid #ddd;
}

/* Sticky toggle button */
.cbd-sticky-toggle {
    position: fixed;
    top: 100px;
    right: 20px;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.cbd-sticky-toggle:hover {
    background: #005a87;
    transform: scale(1.1);
}

.cbd-sticky-toggle .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* Placeholder für sticky sidebar */
.cbd-sidebar-placeholder {
    width: 300px;
    visibility: hidden;
}

/* Mobile Responsiveness für sticky */
@media (max-width: 1200px) {
    .cbd-sidebar.sticky {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        width: auto !important;
        max-height: none !important;
    }

    .cbd-sticky-toggle {
        display: none !important;
    }
}

<?php if (!$can_edit_styles): ?>
/* Hide style editing for non-admin users */
.cbd-style-section {
    display: none !important;
}

.cbd-main-settings::before {
    content: "ℹ️ Hinweis: Style-Bearbeitung ist nur für Administratoren verfügbar. Sie können die Inhalte bearbeiten und das Preview betrachten.";
    display: block;
    background: #e7f3ff;
    border: 1px solid #72aee6;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
    color: #0073aa;
    font-weight: 500;
}
<?php endif; ?>

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

/* Effects Controls Styles */
.cbd-effects-controls fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 10px 0;
}

.cbd-effects-controls legend {
    font-weight: 600;
    padding: 0 10px;
}

.cbd-glassmorphism-options,
.cbd-filter-controls,
.cbd-neumorphism-options,
.cbd-animation-controls {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 10px;
}

.cbd-glassmorphism-options label,
.cbd-filter-controls label,
.cbd-neumorphism-options label,
.cbd-animation-controls label {
    display: flex;
    flex-direction: column;
    font-size: 12px;
    font-weight: 500;
}

.cbd-effects-controls .cbd-range-input {
    margin: 5px 0;
    width: 100%;
}

.cbd-effects-controls .cbd-range-value {
    font-weight: bold;
    color: #007cba;
}

/* Glassmorphism Preview Helper */
.cbd-glassmorphism-preview {
    position: relative;
    overflow: hidden;
}

.cbd-glassmorphism-preview::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    z-index: -1;
}

/* Animation Preview Styles */
.cbd-preview-block {
    transition: all 300ms ease;
    transform-origin: center;
}

.cbd-animation-lift:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.cbd-animation-scale:hover {
    transform: scale(1.05);
}

.cbd-animation-rotate:hover {
    transform: rotate(5deg);
}

.cbd-animation-pulse {
    animation: cbd-pulse 2s infinite;
}

@keyframes cbd-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); }
    100% { transform: scale(1); }
}

.cbd-animation-bounce:hover {
    animation: cbd-bounce 0.6s ease;
}

@keyframes cbd-bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
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
</style>

<script>
jQuery(document).ready(function($) {
    // Color Picker initialisieren
    if ($.fn.wpColorPicker) {
        $('.cbd-color-picker').wpColorPicker();
    }
    
    // Dashicons Liste
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
        selectedIcon = $('#icon_value').val();
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
            $('#icon_value').val(selectedIcon);
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
    
    // Feature Toggle Funktionalität
    $('.cbd-feature-toggle').on('change', function() {
        var $checkbox = $(this);
        var $options = $checkbox.closest('.cbd-feature-item').find('.cbd-feature-options');
        
        if ($checkbox.is(':checked')) {
            $options.slideDown(300);
        } else {
            $options.slideUp(300);
        }
    });
    
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
        var value = $input.val();
        
        // Different units for different ranges
        if ($input.attr('name').includes('angle') || $input.attr('name').includes('hue')) {
            $valueSpan.text(value + '°');
        } else if ($input.attr('name').includes('opacity')) {
            $valueSpan.text(value);
        } else if ($input.attr('name').includes('blur')) {
            $valueSpan.text(value + 'px');
        } else {
            $valueSpan.text(value + '%');
        }
        
        updateLivePreview();
    });
    
    // Name validierung
    $('#name').on('input', function() {
        var value = $(this).val();
        // Entferne ungültige Zeichen
        value = value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
        $(this).val(value);
    });
    
    // Form Validierung
    $('.cbd-block-form').on('submit', function() {
        var name = $('#name').val();
        var title = $('#title').val();
        
        if (!name || !title) {
            alert('Name und Titel sind Pflichtfelder!');
            return false;
        }
        
        // Name Format prüfen
        if (!/^[a-z0-9_]+$/.test(name)) {
            alert('Der Name darf nur Kleinbuchstaben, Zahlen und Unterstriche enthalten!');
            return false;
        }
        
        return true;
    });
    
    // Live Preview für Styles (optional)
    $('input[name^="styles"], select[name^="styles"]').on('change input', function() {
        updateStylePreview();
    });
    
    function updateStylePreview() {
        updateLivePreview();
    }
    
    // Helper function to convert hex to RGB
    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }
    
    // Helper function to lighten/darken a hex color
    function adjustColor(hex, amount) {
        var rgb = hexToRgb(hex);
        if (!rgb) return hex;
        
        var r = Math.max(0, Math.min(255, rgb.r + amount));
        var g = Math.max(0, Math.min(255, rgb.g + amount));
        var b = Math.max(0, Math.min(255, rgb.b + amount));
        
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    
    // Generate neumorphism shadows
    function generateNeumorphismShadows(style, intensity, distance, background) {
        var lightShadow = adjustColor(background, parseInt(intensity) * 2);
        var darkShadow = adjustColor(background, -parseInt(intensity));
        
        var shadows = [];
        var dist = parseInt(distance);
        
        if (style === 'raised') {
            shadows.push(dist + 'px ' + dist + 'px ' + (dist * 2) + 'px ' + darkShadow);
            shadows.push('-' + dist + 'px -' + dist + 'px ' + (dist * 2) + 'px ' + lightShadow);
        } else if (style === 'inset') {
            shadows.push('inset ' + dist + 'px ' + dist + 'px ' + (dist * 2) + 'px ' + darkShadow);
            shadows.push('inset -' + dist + 'px -' + dist + 'px ' + (dist * 2) + 'px ' + lightShadow);
        } else if (style === 'flat') {
            shadows.push(dist + 'px ' + dist + 'px ' + dist + 'px ' + darkShadow);
        }
        
        return shadows.join(', ');
    }
    
    // Live Preview Update Function
    function updateLivePreview() {
        var $previewBlock = $('#cbd-preview-block');
        var $previewTitle = $('#cbd-preview-title');
        var $previewDescription = $('#cbd-preview-description');
        var $previewFeatures = $('#cbd-preview-features');
        
        // Update content
        var title = $('#title').val() || 'Neuer Block';
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
        
        // Glassmorphism and filters
        var glassmorphismEnabled = $('input[name="styles[effects][glassmorphism][enabled]"]').is(':checked');
        var glassOpacity = $('input[name="styles[effects][glassmorphism][opacity]"]').val() || '0.1';
        var glassBlur = $('input[name="styles[effects][glassmorphism][blur]"]').val() || '10';
        var glassSaturate = $('input[name="styles[effects][glassmorphism][saturate]"]').val() || '100';
        var glassColor = $('input[name="styles[effects][glassmorphism][color]"]').val() || '#ffffff';
        
        var filterBrightness = $('input[name="styles[effects][filters][brightness]"]').val() || '100';
        var filterContrast = $('input[name="styles[effects][filters][contrast]"]').val() || '100';
        var filterHue = $('input[name="styles[effects][filters][hue]"]').val() || '0';
        
        // Build filter and backdrop-filter values
        var filters = [];
        if (filterBrightness != 100) filters.push('brightness(' + filterBrightness + '%)');
        if (filterContrast != 100) filters.push('contrast(' + filterContrast + '%)');
        if (filterHue != 0) filters.push('hue-rotate(' + filterHue + 'deg)');
        
        var backdropFilters = [];
        if (glassmorphismEnabled) {
            backdropFilters.push('blur(' + glassBlur + 'px)');
            backdropFilters.push('saturate(' + glassSaturate + '%)');
        }
        
        // Apply glassmorphism background if enabled
        var finalBackground = backgroundValue;
        if (glassmorphismEnabled) {
            // Create semi-transparent background with glass color
            var rgbColor = hexToRgb(glassColor);
            if (rgbColor) {
                finalBackground = 'rgba(' + rgbColor.r + ', ' + rgbColor.g + ', ' + rgbColor.b + ', ' + glassOpacity + ')';
            }
        }
        
        $previewBlock.css({
            'background': finalBackground,
            'color': textColor,
            'border': borderWidth + 'px ' + borderStyle + ' ' + borderColor,
            'border-radius': borderRadius + 'px',
            'padding': paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px',
            'box-shadow': boxShadows.length > 0 ? boxShadows.join(', ') : 'none',
            'filter': filters.length > 0 ? filters.join(' ') : 'none',
            'backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none',
            '-webkit-backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none'
        });
        
        // Neumorphism effects
        var neumorphismEnabled = $('input[name="styles[effects][neumorphism][enabled]"]').is(':checked');
        var neumoStyle = $('select[name="styles[effects][neumorphism][style]"]').val() || 'raised';
        var neumoIntensity = $('input[name="styles[effects][neumorphism][intensity]"]').val() || '10';
        var neumoBackground = $('input[name="styles[effects][neumorphism][background]"]').val() || '#e0e0e0';
        var neumoDistance = $('input[name="styles[effects][neumorphism][distance]"]').val() || '15';
        
        // Apply neumorphism if enabled (overrides other shadow effects)
        if (neumorphismEnabled) {
            var neumoBoxShadows = generateNeumorphismShadows(neumoStyle, neumoIntensity, neumoDistance, neumoBackground);
            finalBackground = neumoBackground; // Use neumorphism background color
            
            $previewBlock.css({
                'background': finalBackground,
                'color': textColor,
                'border': borderWidth + 'px ' + borderStyle + ' ' + borderColor,
                'border-radius': borderRadius + 'px',
                'padding': paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px',
                'box-shadow': neumoBoxShadows,
                'filter': filters.length > 0 ? filters.join(' ') : 'none',
                'backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none',
                '-webkit-backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none'
            });
        } else {
            $previewBlock.css({
                'background': finalBackground,
                'color': textColor,
                'border': borderWidth + 'px ' + borderStyle + ' ' + borderColor,
                'border-radius': borderRadius + 'px',
                'padding': paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px',
                'box-shadow': boxShadows.length > 0 ? boxShadows.join(', ') : 'none',
                'filter': filters.length > 0 ? filters.join(' ') : 'none',
                'backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none',
                '-webkit-backdrop-filter': backdropFilters.length > 0 ? backdropFilters.join(' ') : 'none'
            });
        }
        
        // Add classes for additional styling
        if (glassmorphismEnabled) {
            $previewBlock.addClass('cbd-glassmorphism-preview');
        } else {
            $previewBlock.removeClass('cbd-glassmorphism-preview');
        }
        
        if (neumorphismEnabled) {
            $previewBlock.addClass('cbd-neumorphism-preview');
        } else {
            $previewBlock.removeClass('cbd-neumorphism-preview');
        }
        
        // Animation effects
        var hoverAnimation = $('select[name="styles[effects][animation][hover]"]').val() || 'none';
        var animOrigin = $('select[name="styles[effects][animation][origin]"]').val() || 'center';
        var animDuration = $('input[name="styles[effects][animation][duration]"]').val() || '300';
        var animEasing = $('select[name="styles[effects][animation][easing]"]').val() || 'ease';
        
        // Remove existing animation classes
        $previewBlock.removeClass('cbd-animation-lift cbd-animation-scale cbd-animation-rotate cbd-animation-pulse cbd-animation-bounce');
        
        // Apply animation class
        if (hoverAnimation !== 'none') {
            $previewBlock.addClass('cbd-animation-' + hoverAnimation);
        }
        
        // Apply animation properties
        $previewBlock.css({
            'transform-origin': animOrigin,
            'transition-duration': animDuration + 'ms',
            'transition-timing-function': animEasing
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

    // Sticky Live Preview Functionality
    var $sidebar = $('.cbd-sidebar');
    var $stickyToggle = $('.cbd-sticky-toggle');
    var $sidebarPlaceholder = null;
    var isSticky = false;

    // Create placeholder element
    function createPlaceholder() {
        if (!$sidebarPlaceholder) {
            $sidebarPlaceholder = $('<div class="cbd-sidebar-placeholder"></div>');
            $sidebar.before($sidebarPlaceholder);
        }
    }

    // Toggle sticky mode
    function toggleSticky() {
        isSticky = !isSticky;

        if (isSticky) {
            createPlaceholder();
            $sidebarPlaceholder.css({
                'width': $sidebar.outerWidth() + 'px',
                'height': $sidebar.outerHeight() + 'px',
                'visibility': 'visible'
            });
            $sidebar.addClass('sticky');
            $stickyToggle.find('.dashicons')
                .removeClass('dashicons-sticky')
                .addClass('dashicons-unlock')
                .parent().attr('title', 'Live-Preview lösen');
        } else {
            $sidebar.removeClass('sticky');
            if ($sidebarPlaceholder) {
                $sidebarPlaceholder.css('visibility', 'hidden');
            }
            $stickyToggle.find('.dashicons')
                .removeClass('dashicons-unlock')
                .addClass('dashicons-sticky')
                .parent().attr('title', 'Live-Preview anheften');
        }

        // Save preference
        localStorage.setItem('cbd_sticky_preview', isSticky);
    }

    // Auto-hide/show toggle button based on scroll
    function updateToggleVisibility() {
        var scrollTop = $(window).scrollTop();
        var sidebarTop = $sidebar.offset().top - scrollTop;

        if (scrollTop > 100 && sidebarTop < 100 && !isSticky) {
            $stickyToggle.fadeIn(300);
        } else if (isSticky) {
            $stickyToggle.fadeIn(300);
        } else {
            $stickyToggle.fadeOut(300);
        }
    }

    // Events
    $stickyToggle.on('click', toggleSticky);
    $(window).on('scroll resize', updateToggleVisibility);

    // Initialize based on saved preference (nur auf Desktop)
    if ($(window).width() > 1200) {
        var savedPreference = localStorage.getItem('cbd_sticky_preview');
        if (savedPreference === 'true') {
            toggleSticky();
        }
    }

    // Initial visibility check
    updateToggleVisibility();

    <?php if (isset($js_redirect) && $js_redirect && isset($redirect_url)): ?>
    // Automatic redirect after successful save
    setTimeout(function() {
        console.log('Redirecting to: <?php echo esc_js($redirect_url); ?>');
        window.location.href = '<?php echo esc_js($redirect_url); ?>';
    }, 2000); // 2 seconds delay to show the success message
    <?php endif; ?>
});
</script>
