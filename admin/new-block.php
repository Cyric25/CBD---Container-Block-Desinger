<?php
/**
 * Container Block Designer - New Block Page
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.3
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Formular verarbeiten
if (isset($_POST['cbd_save_block']) && wp_verify_nonce($_POST['cbd_nonce'], 'cbd_save_block')) {
    
    $block_data = array(
        'name' => sanitize_text_field($_POST['block_name']),
        'title' => sanitize_text_field($_POST['block_title'] ?? $_POST['block_name']),
        'description' => sanitize_textarea_field($_POST['block_description'] ?? ''),
        'config' => json_encode(array(
            'allowInnerBlocks' => isset($_POST['allow_inner_blocks']),
            'templateLock' => $_POST['template_lock'] ?? false
        )),
        'styles' => json_encode(array(
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
                'width' => intval($_POST['border_width'] ?? 0),
                'color' => sanitize_hex_color($_POST['border_color'] ?? '#000000'),
                'style' => sanitize_text_field($_POST['border_style'] ?? 'solid'),
                'radius' => intval($_POST['border_radius'] ?? 0)
            ),
            'text' => array(
                'color' => sanitize_hex_color($_POST['text_color'] ?? '#333333'),
                'alignment' => sanitize_text_field($_POST['text_alignment'] ?? 'left')
            )
        )),
        'features' => json_encode(array(
            'icon' => array(
                'enabled' => isset($_POST['feature_icon']),
                'value' => sanitize_text_field($_POST['icon_value'] ?? '')
            ),
            'collapse' => array(
                'enabled' => isset($_POST['feature_collapse']),
                'value' => sanitize_text_field($_POST['collapse_default'] ?? '')
            ),
            'numbering' => array(
                'enabled' => isset($_POST['feature_numbering']),
                'value' => sanitize_text_field($_POST['numbering_type'] ?? '')
            ),
            'copyText' => array(
                'enabled' => isset($_POST['feature_copy_text']),
                'value' => ''
            ),
            'screenshot' => array(
                'enabled' => isset($_POST['feature_screenshot']),
                'value' => ''
            )
        )),
        'status' => 'active'
    );
    
    // In Datenbank speichern (mit korrekten Spaltennamen)
    global $wpdb;
    $table_name = CBD_TABLE_BLOCKS;
    
    // Prüfe ob Name bereits existiert
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE name = %s",
        $block_data['name']
    ));
    
    if ($existing > 0) {
        $error_message = __('Ein Block mit diesem Namen existiert bereits.', 'container-block-designer');
    } else {
        // Füge created_at und updated_at hinzu
        $block_data['created_at'] = current_time('mysql');
        $block_data['updated_at'] = current_time('mysql');
        
        // Entferne slug falls vorhanden (wird nicht mehr benötigt)
        unset($block_data['slug']);
        
        $result = $wpdb->insert($table_name, $block_data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=container-block-designer&message=block_created'));
            exit;
        } else {
            $error_message = __('Fehler beim Speichern des Blocks: ', 'container-block-designer') . $wpdb->last_error;
        }
    }
}
?>

<div class="wrap cbd-admin-wrap">
    <h1><?php _e('Neuen Container Block erstellen', 'container-block-designer'); ?></h1>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="cbd-block-form">
        <?php wp_nonce_field('cbd_save_block', 'cbd_nonce'); ?>
        
        <!-- Basis-Informationen -->
        <div class="cbd-form-section">
            <h2><?php _e('Basis-Informationen', 'container-block-designer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="block_name"><?php _e('Block-Name (intern)', 'container-block-designer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="block_name" name="block_name" class="regular-text" required 
                               pattern="[a-z0-9-]+" title="<?php _e('Nur Kleinbuchstaben, Zahlen und Bindestriche', 'container-block-designer'); ?>">
                        <p class="description"><?php _e('Eindeutiger Name für diesen Block (nur a-z, 0-9, -)', 'container-block-designer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="block_title"><?php _e('Block-Titel', 'container-block-designer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="block_title" name="block_title" class="regular-text" required>
                        <p class="description"><?php _e('Anzeigename im Block-Editor', 'container-block-designer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="block_description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                    </th>
                    <td>
                        <textarea id="block_description" name="block_description" rows="3" class="large-text"></textarea>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Styling -->
        <div class="cbd-form-section">
            <h2><?php _e('Styling', 'container-block-designer'); ?></h2>
            
            <table class="form-table">
                <!-- Padding -->
                <tr>
                    <th scope="row"><?php _e('Innenabstand (Padding)', 'container-block-designer'); ?></th>
                    <td>
                        <div class="cbd-spacing-inputs">
                            <label><?php _e('Oben:', 'container-block-designer'); ?> 
                                <input type="number" name="padding_top" value="20" min="0" max="100" class="small-text">px
                            </label>
                            <label><?php _e('Rechts:', 'container-block-designer'); ?> 
                                <input type="number" name="padding_right" value="20" min="0" max="100" class="small-text">px
                            </label>
                            <label><?php _e('Unten:', 'container-block-designer'); ?> 
                                <input type="number" name="padding_bottom" value="20" min="0" max="100" class="small-text">px
                            </label>
                            <label><?php _e('Links:', 'container-block-designer'); ?> 
                                <input type="number" name="padding_left" value="20" min="0" max="100" class="small-text">px
                            </label>
                        </div>
                    </td>
                </tr>
                
                <!-- Hintergrund -->
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="background_color" name="background_color" value="#ffffff">
                    </td>
                </tr>
                
                <!-- Rahmen -->
                <tr>
                    <th scope="row"><?php _e('Rahmen', 'container-block-designer'); ?></th>
                    <td>
                        <div class="cbd-border-inputs">
                            <label><?php _e('Breite:', 'container-block-designer'); ?>
                                <input type="number" name="border_width" value="0" min="0" max="10" class="small-text">px
                            </label>
                            <label><?php _e('Stil:', 'container-block-designer'); ?>
                                <select name="border_style">
                                    <option value="solid"><?php _e('Durchgehend', 'container-block-designer'); ?></option>
                                    <option value="dashed"><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                    <option value="dotted"><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                    <option value="double"><?php _e('Doppelt', 'container-block-designer'); ?></option>
                                </select>
                            </label>
                            <label><?php _e('Farbe:', 'container-block-designer'); ?>
                                <input type="color" name="border_color" value="#000000">
                            </label>
                            <label><?php _e('Radius:', 'container-block-designer'); ?>
                                <input type="number" name="border_radius" value="0" min="0" max="50" class="small-text">px
                            </label>
                        </div>
                    </td>
                </tr>
                
                <!-- Text -->
                <tr>
                    <th scope="row"><?php _e('Text', 'container-block-designer'); ?></th>
                    <td>
                        <label><?php _e('Farbe:', 'container-block-designer'); ?>
                            <input type="color" name="text_color" value="#333333">
                        </label>
                        <label><?php _e('Ausrichtung:', 'container-block-designer'); ?>
                            <select name="text_alignment">
                                <option value="left"><?php _e('Links', 'container-block-designer'); ?></option>
                                <option value="center"><?php _e('Mitte', 'container-block-designer'); ?></option>
                                <option value="right"><?php _e('Rechts', 'container-block-designer'); ?></option>
                                <option value="justify"><?php _e('Blocksatz', 'container-block-designer'); ?></option>
                            </select>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Funktionen -->
        <div class="cbd-form-section">
            <h2><?php _e('Funktionen', 'container-block-designer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Verfügbare Funktionen', 'container-block-designer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="allow_inner_blocks" value="1" checked>
                                <?php _e('Inner Blocks erlauben', 'container-block-designer'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="feature_icon" value="1">
                                <?php _e('Icon-Unterstützung', 'container-block-designer'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="feature_collapse" value="1">
                                <?php _e('Ein-/Ausklappbar', 'container-block-designer'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="feature_numbering" value="1">
                                <?php _e('Nummerierung', 'container-block-designer'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="feature_copy_text" value="1">
                                <?php _e('Text kopieren', 'container-block-designer'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="feature_screenshot" value="1">
                                <?php _e('Screenshot', 'container-block-designer'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Vorschau -->
        <div class="cbd-form-section">
            <h2><?php _e('Vorschau', 'container-block-designer'); ?></h2>
            <div id="cbd-block-preview" class="cbd-preview-container">
                <div class="cbd-preview-block" style="
                    padding: 20px;
                    background-color: #ffffff;
                    border: 0px solid #000000;
                    border-radius: 0px;
                    color: #333333;
                    text-align: left;
                ">
                    <?php _e('Ihr Container Block wird hier angezeigt...', 'container-block-designer'); ?>
                </div>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="cbd_save_block" class="button button-primary" value="<?php _e('Block speichern', 'container-block-designer'); ?>">
            <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button button-secondary">
                <?php _e('Abbrechen', 'container-block-designer'); ?>
            </a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Live-Vorschau
    function updatePreview() {
        var preview = $('.cbd-preview-block');
        
        // Padding
        var paddingTop = $('input[name="padding_top"]').val();
        var paddingRight = $('input[name="padding_right"]').val();
        var paddingBottom = $('input[name="padding_bottom"]').val();
        var paddingLeft = $('input[name="padding_left"]').val();
        
        // Styling
        var bgColor = $('input[name="background_color"]').val();
        var borderWidth = $('input[name="border_width"]').val();
        var borderStyle = $('select[name="border_style"]').val();
        var borderColor = $('input[name="border_color"]').val();
        var borderRadius = $('input[name="border_radius"]').val();
        var textColor = $('input[name="text_color"]').val();
        var textAlign = $('select[name="text_alignment"]').val();
        
        preview.css({
            'padding': paddingTop + 'px ' + paddingRight + 'px ' + paddingBottom + 'px ' + paddingLeft + 'px',
            'background-color': bgColor,
            'border': borderWidth + 'px ' + borderStyle + ' ' + borderColor,
            'border-radius': borderRadius + 'px',
            'color': textColor,
            'text-align': textAlign
        });
    }
    
    // Update bei Änderungen
    $('.cbd-form-section input, .cbd-form-section select').on('change input', updatePreview);
    
    // Block-Name validieren
    $('#block_name').on('input', function() {
        var value = $(this).val();
        var valid = /^[a-z0-9-]*$/.test(value);
        
        if (!valid) {
            $(this).val(value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
        }
    });
});
</script>

<style>
.cbd-admin-wrap {
    background: #fff;
    padding: 20px;
    margin: 20px 20px 0 0;
    border-radius: 4px;
}

.cbd-form-section {
    margin-bottom: 30px;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 20px;
}

.cbd-form-section:last-of-type {
    border-bottom: none;
}

.cbd-spacing-inputs label,
.cbd-border-inputs label {
    display: inline-block;
    margin-right: 15px;
}

.cbd-preview-container {
    padding: 20px;
    background: #f1f1f1;
    border-radius: 4px;
    min-height: 100px;
}

.cbd-preview-block {
    background: #fff;
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>