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
            'color' => sanitize_hex_color($_POST['styles']['background']['color'] ?? $_POST['background_color'] ?? '#ffffff')
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
                
                <div class="postbox">
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
                                <th scope="row">
                                    <label for="background_color"><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="background_color" name="styles[background][color]" value="<?php echo esc_attr($block['styles']['background']['color'] ?? '#ffffff'); ?>" class="cbd-color-picker">
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
                                <label for="icon_value"><?php _e('Icon-Klasse:', 'container-block-designer'); ?></label>
                                <input type="text" id="icon_value" name="features[icon][value]" value="<?php echo esc_attr($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?>" placeholder="dashicons-admin-generic" class="regular-text">
                                <p class="description"><?php _e('Verwenden Sie Dashicon-Klassen wie "dashicons-admin-generic"', 'container-block-designer'); ?></p>
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
    // Color Picker initialisieren
    if ($.fn.wpColorPicker) {
        $('.cbd-color-picker').wpColorPicker();
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
    
    <?php if (isset($js_redirect) && $js_redirect && isset($redirect_url)): ?>
    // Automatic redirect after successful save
    setTimeout(function() {
        console.log('Redirecting to: <?php echo esc_js($redirect_url); ?>');
        window.location.href = '<?php echo esc_js($redirect_url); ?>';
    }, 2000); // 2 seconds delay to show the success message
    <?php endif; ?>
});
</script>
