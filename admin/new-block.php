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

// WICHTIG: Formularverarbeitung MUSS VOR jeglichem Output erfolgen
if (isset($_POST['cbd_save_block']) && isset($_POST['cbd_nonce']) && wp_verify_nonce($_POST['cbd_nonce'], 'cbd_save_block')) {
    
    global $wpdb;
    
    // Block ID holen
    $block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
    
    // Daten sammeln
    $name = sanitize_text_field($_POST['name']);
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Styles sammeln
    $styles = array(
        'padding' => array(
            'top' => intval($_POST['padding_top'] ?? 20),
            'right' => intval($_POST['padding_right'] ?? 20),
            'bottom' => intval($_POST['padding_bottom'] ?? 20),
            'left' => intval($_POST['padding_left'] ?? 20)
        ),
        'background' => array(
            'color' => sanitize_hex_color($_POST['background_color'] ?? '#ffffff')
        ),
        'border' => array(
            'width' => intval($_POST['border_width'] ?? 1),
            'color' => sanitize_hex_color($_POST['border_color'] ?? '#e0e0e0'),
            'style' => sanitize_text_field($_POST['border_style'] ?? 'solid'),
            'radius' => intval($_POST['border_radius'] ?? 4)
        ),
        'text' => array(
            'color' => sanitize_hex_color($_POST['text_color'] ?? '#333333'),
            'alignment' => sanitize_text_field($_POST['text_alignment'] ?? 'left')
        )
    );
    
    // Features sammeln
    $features = array(
        'icon' => array(
            'enabled' => isset($_POST['features']['icon']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['icon']['value'] ?? '')
        ),
        'collapse' => array(
            'enabled' => isset($_POST['features']['collapse']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['collapse']['value'] ?? '')
        ),
        'numbering' => array(
            'enabled' => isset($_POST['features']['numbering']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['numbering']['value'] ?? '')
        ),
        'copyText' => array(
            'enabled' => isset($_POST['features']['copyText']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['copyText']['value'] ?? '')
        ),
        'screenshot' => array(
            'enabled' => isset($_POST['features']['screenshot']['enabled']) ? true : false,
            'value' => sanitize_text_field($_POST['features']['screenshot']['value'] ?? '')
        )
    );
    
    // Config sammeln
    $config = array(
        'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
        'templateLock' => isset($_POST['template_lock']) ? false : true
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
        // Redirect mit der neuen Block ID wenn neu erstellt
        if (!isset($_GET['block_id']) && $block_id) {
            wp_redirect(admin_url('admin.php?page=cbd-new-block&block_id=' . $block_id . '&cbd_message=saved'));
        } else {
            wp_redirect(admin_url('admin.php?page=cbd-new-block&block_id=' . $block_id . '&cbd_message=saved'));
        }
        exit;
    }
}

// ERST JETZT beginnt die Ausgabe
global $wpdb;

$block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
$block = null;

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
    
    <?php if (isset($_GET['cbd_message']) && $_GET['cbd_message'] === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Block erfolgreich gespeichert.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($result) && $result === false): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e('Fehler beim Speichern des Blocks.', 'container-block-designer'); ?></p>
            <?php if ($wpdb->last_error): ?>
                <p><code><?php echo esc_html($wpdb->last_error); ?></code></p>
            <?php endif; ?>
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
                                            <input type="number" name="padding_top" value="<?php echo esc_attr($block['styles']['padding']['top'] ?? 20); ?>" min="0" max="100">
                                        </label>
                                        <label>
                                            <?php _e('Rechts:', 'container-block-designer'); ?>
                                            <input type="number" name="padding_right" value="<?php echo esc_attr($block['styles']['padding']['right'] ?? 20); ?>" min="0" max="100">
                                        </label>
                                        <label>
                                            <?php _e('Unten:', 'container-block-designer'); ?>
                                            <input type="number" name="padding_bottom" value="<?php echo esc_attr($block['styles']['padding']['bottom'] ?? 20); ?>" min="0" max="100">
                                        </label>
                                        <label>
                                            <?php _e('Links:', 'container-block-designer'); ?>
                                            <input type="number" name="padding_left" value="<?php echo esc_attr($block['styles']['padding']['left'] ?? 20); ?>" min="0" max="100">
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="background_color"><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="background_color" name="background_color" value="<?php echo esc_attr($block['styles']['background']['color'] ?? '#ffffff'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="text_color"><?php _e('Textfarbe', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="text_color" name="text_color" value="<?php echo esc_attr($block['styles']['text']['color'] ?? '#333333'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="text_alignment"><?php _e('Textausrichtung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="text_alignment" name="text_alignment">
                                        <option value="left" <?php selected($block['styles']['text']['alignment'] ?? 'left', 'left'); ?>><?php _e('Links', 'container-block-designer'); ?></option>
                                        <option value="center" <?php selected($block['styles']['text']['alignment'] ?? '', 'center'); ?>><?php _e('Zentriert', 'container-block-designer'); ?></option>
                                        <option value="right" <?php selected($block['styles']['text']['alignment'] ?? '', 'right'); ?>><?php _e('Rechts', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Rahmen', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-border-controls">
                                        <label>
                                            <?php _e('Breite:', 'container-block-designer'); ?>
                                            <input type="number" name="border_width" value="<?php echo esc_attr($block['styles']['border']['width'] ?? 1); ?>" min="0" max="10">
                                        </label>
                                        <label>
                                            <?php _e('Farbe:', 'container-block-designer'); ?>
                                            <input type="color" name="border_color" value="<?php echo esc_attr($block['styles']['border']['color'] ?? '#e0e0e0'); ?>">
                                        </label>
                                        <label>
                                            <?php _e('Stil:', 'container-block-designer'); ?>
                                            <select name="border_style">
                                                <option value="solid" <?php selected($block['styles']['border']['style'] ?? 'solid', 'solid'); ?>><?php _e('Durchgehend', 'container-block-designer'); ?></option>
                                                <option value="dashed" <?php selected($block['styles']['border']['style'] ?? '', 'dashed'); ?>><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                                <option value="dotted" <?php selected($block['styles']['border']['style'] ?? '', 'dotted'); ?>><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                                <option value="none" <?php selected($block['styles']['border']['style'] ?? '', 'none'); ?>><?php _e('Kein Rahmen', 'container-block-designer'); ?></option>
                                            </select>
                                        </label>
                                        <label>
                                            <?php _e('Radius:', 'container-block-designer'); ?>
                                            <input type="number" name="border_radius" value="<?php echo esc_attr($block['styles']['border']['radius'] ?? 4); ?>" min="0" max="50">
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
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Features', 'container-block-designer'); ?></h2>
                    <div class="inside">
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[icon][enabled]" value="1" <?php checked($block['features']['icon']['enabled'] ?? false); ?>>
                                <strong><?php _e('Icon', 'container-block-designer'); ?></strong>
                            </label>
                            <input type="text" name="features[icon][value]" value="<?php echo esc_attr($block['features']['icon']['value'] ?? ''); ?>" placeholder="dashicons-admin-generic" class="regular-text">
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[collapse][enabled]" value="1" <?php checked($block['features']['collapse']['enabled'] ?? false); ?>>
                                <strong><?php _e('Klappbar', 'container-block-designer'); ?></strong>
                            </label>
                            <select name="features[collapse][value]">
                                <option value="expanded" <?php selected($block['features']['collapse']['value'] ?? 'expanded', 'expanded'); ?>><?php _e('Ausgeklappt', 'container-block-designer'); ?></option>
                                <option value="collapsed" <?php selected($block['features']['collapse']['value'] ?? '', 'collapsed'); ?>><?php _e('Eingeklappt', 'container-block-designer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[numbering][enabled]" value="1" <?php checked($block['features']['numbering']['enabled'] ?? false); ?>>
                                <strong><?php _e('Nummerierung', 'container-block-designer'); ?></strong>
                            </label>
                            <select name="features[numbering][value]">
                                <option value="numeric" <?php selected($block['features']['numbering']['value'] ?? 'numeric', 'numeric'); ?>><?php _e('1, 2, 3...', 'container-block-designer'); ?></option>
                                <option value="alpha" <?php selected($block['features']['numbering']['value'] ?? '', 'alpha'); ?>><?php _e('A, B, C...', 'container-block-designer'); ?></option>
                                <option value="roman" <?php selected($block['features']['numbering']['value'] ?? '', 'roman'); ?>><?php _e('I, II, III...', 'container-block-designer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[copyText][enabled]" value="1" <?php checked($block['features']['copyText']['enabled'] ?? false); ?>>
                                <strong><?php _e('Text kopieren', 'container-block-designer'); ?></strong>
                            </label>
                            <input type="text" name="features[copyText][value]" value="<?php echo esc_attr($block['features']['copyText']['value'] ?? 'Text kopieren'); ?>" placeholder="Button-Text" class="regular-text">
                        </div>
                        
                        <div class="cbd-feature-item">
                            <label>
                                <input type="checkbox" name="features[screenshot][enabled]" value="1" <?php checked($block['features']['screenshot']['enabled'] ?? false); ?>>
                                <strong><?php _e('Screenshot', 'container-block-designer'); ?></strong>
                            </label>
                            <input type="text" name="features[screenshot][value]" value="<?php echo esc_attr($block['features']['screenshot']['value'] ?? 'Screenshot'); ?>" placeholder="Button-Text" class="regular-text">
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

@media (max-width: 1200px) {
    .cbd-form-wrapper {
        flex-direction: column;
    }
    
    .cbd-sidebar {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
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
});
</script>
