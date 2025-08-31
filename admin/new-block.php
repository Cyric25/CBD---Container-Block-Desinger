<?php
/**
 * Container Block Designer - Neuer Block
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Standardwerte für neuen Block
$styles = array(
    'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
    'background' => array('color' => '#ffffff'),
    'text' => array('color' => '#333333', 'alignment' => 'left'),
    'border' => array('width' => 1, 'color' => '#e0e0e0', 'style' => 'solid', 'radius' => 4)
);

$features = array(
    'icon' => array('enabled' => false, 'value' => 'dashicons-admin-generic'),
    'collapse' => array('enabled' => false, 'defaultState' => 'expanded'),
    'numbering' => array('enabled' => false, 'format' => 'numeric'),
    'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
    'screenshot' => array('enabled' => false, 'buttonText' => 'Screenshot')
);

$config = array(
    'allowInnerBlocks' => true,
    'templateLock' => false
);
?>

<div class="wrap cbd-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Neuer Container Block', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="page-title-action">
        <?php _e('← Zurück zur Übersicht', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div id="cbd-edit-container">
        <form id="cbd-block-form" method="post">
            <?php wp_nonce_field('cbd-admin', 'cbd_nonce'); ?>
            
            <div class="cbd-form-grid">
                <div class="cbd-main-content">
                    
                    <!-- Grundeinstellungen -->
                    <div class="cbd-card">
                        <h2><?php _e('Grundeinstellungen', 'container-block-designer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="block-name"><?php _e('Name', 'container-block-designer'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="block-name" name="name" 
                                           class="regular-text" required 
                                           pattern="[a-z0-9-]+" 
                                           placeholder="mein-container-block">
                                    <p class="description"><?php _e('Eindeutiger Name (nur Kleinbuchstaben, Zahlen und Bindestriche)', 'container-block-designer'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-title"><?php _e('Titel', 'container-block-designer'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="block-title" name="title" 
                                           class="regular-text" required
                                           placeholder="<?php esc_attr_e('Mein Container Block', 'container-block-designer'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <textarea id="block-description" name="description" 
                                              rows="3" class="large-text"
                                              placeholder="<?php esc_attr_e('Beschreiben Sie den Zweck dieses Blocks...', 'container-block-designer'); ?>"></textarea>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="block-status"><?php _e('Status', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="block-status" name="status">
                                        <option value="draft"><?php _e('Entwurf', 'container-block-designer'); ?></option>
                                        <option value="active"><?php _e('Aktiv', 'container-block-designer'); ?></option>
                                        <option value="inactive"><?php _e('Inaktiv', 'container-block-designer'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Neue Blocks werden standardmäßig als Entwurf erstellt', 'container-block-designer'); ?></p>
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
                                               value="20" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Oben', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][right]" 
                                               value="20" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Rechts', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][bottom]" 
                                               value="20" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Unten', 'container-block-designer'); ?></label>
                                        
                                        <input type="number" name="styles[padding][left]" 
                                               value="20" 
                                               min="0" max="100" class="small-text">
                                        <label><?php _e('Links', 'container-block-designer'); ?></label>
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
                                           value="#ffffff" 
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
                                           value="#333333" 
                                           class="cbd-color-picker">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="text-alignment"><?php _e('Textausrichtung', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <select id="text-alignment" name="styles[text][alignment]">
                                        <option value="left"><?php _e('Links', 'container-block-designer'); ?></option>
                                        <option value="center"><?php _e('Zentriert', 'container-block-designer'); ?></option>
                                        <option value="right"><?php _e('Rechts', 'container-block-designer'); ?></option>
                                        <option value="justify"><?php _e('Blocksatz', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Border -->
                            <tr>
                                <th scope="row"><?php _e('Rahmen', 'container-block-designer'); ?></th>
                                <td>
                                    <div class="cbd-border-inputs">
                                        <input type="number" name="styles[border][width]" 
                                               value="1" 
                                               min="0" max="10" class="small-text">
                                        <label><?php _e('Breite (px)', 'container-block-designer'); ?></label>
                                        
                                        <select name="styles[border][style]">
                                            <option value="solid"><?php _e('Durchgezogen', 'container-block-designer'); ?></option>
                                            <option value="dashed"><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                            <option value="dotted"><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                        </select>
                                        
                                        <input type="text" name="styles[border][color]" 
                                               value="#e0e0e0" 
                                               class="cbd-color-picker">
                                        
                                        <input type="number" name="styles[border][radius]" 
                                               value="4" 
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
                                        <input type="checkbox" name="features[icon][enabled]" value="1">
                                        <?php _e('Icon anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <input type="text" name="features[icon][value]" 
                                           value="dashicons-admin-generic" 
                                           class="regular-text" 
                                           placeholder="dashicons-admin-generic">
                                    <p class="description">
                                        <?php _e('Dashicon-Klasse eingeben.', 'container-block-designer'); ?>
                                        <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank"><?php _e('Dashicons anzeigen', 'container-block-designer'); ?></a>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Collapse -->
                            <tr>
                                <th scope="row"><?php _e('Einklappen', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[collapse][enabled]" value="1">
                                        <?php _e('Einklappen ermöglichen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <select name="features[collapse][defaultState]">
                                        <option value="expanded"><?php _e('Standardmäßig ausgeklappt', 'container-block-designer'); ?></option>
                                        <option value="collapsed"><?php _e('Standardmäßig eingeklappt', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Numbering -->
                            <tr>
                                <th scope="row"><?php _e('Nummerierung', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[numbering][enabled]" value="1">
                                        <?php _e('Automatische Nummerierung', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <select name="features[numbering][format]">
                                        <option value="numeric"><?php _e('Numerisch (1, 2, 3)', 'container-block-designer'); ?></option>
                                        <option value="alphabetic"><?php _e('Alphabetisch (A, B, C)', 'container-block-designer'); ?></option>
                                        <option value="roman"><?php _e('Römisch (I, II, III)', 'container-block-designer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Copy Text -->
                            <tr>
                                <th scope="row"><?php _e('Text kopieren', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="features[copyText][enabled]" value="1">
                                        <?php _e('Kopier-Button anzeigen', 'container-block-designer'); ?>
                                    </label>
                                    <br><br>
                                    <input type="text" name="features[copyText][buttonText]" 
                                           value="<?php esc_attr_e('Text kopieren', 'container-block-designer'); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Konfiguration -->
                    <div class="cbd-card">
                        <h2><?php _e('Konfiguration', 'container-block-designer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Innere Blocks', 'container-block-designer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="config[allowInnerBlocks]" value="1" checked>
                                        <?php _e('Erlaube andere Blocks innerhalb dieses Containers', 'container-block-designer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                </div>
                
                <!-- Sidebar -->
                <div class="cbd-sidebar">
                    <div class="cbd-card">
                        <h3><?php _e('Aktionen', 'container-block-designer'); ?></h3>
                        
                        <button type="submit" class="button button-primary button-large" id="cbd-save-block">
                            <?php _e('Block erstellen', 'container-block-designer'); ?>
                        </button>
                        
                        <hr>
                        
                        <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button button-secondary">
                            <?php _e('Abbrechen', 'container-block-designer'); ?>
                        </a>
                    </div>
                    
                    <div class="cbd-card">
                        <h3><?php _e('Tipps', 'container-block-designer'); ?></h3>
                        
                        <ul>
                            <li><?php _e('Verwenden Sie einen eindeutigen Namen für jeden Block', 'container-block-designer'); ?></li>
                            <li><?php _e('Der Name darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten', 'container-block-designer'); ?></li>
                            <li><?php _e('Sie können die Einstellungen später jederzeit ändern', 'container-block-designer'); ?></li>
                            <li><?php _e('Blocks im Entwurfsstatus sind nicht im Editor verfügbar', 'container-block-designer'); ?></li>
                        </ul>
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

.required {
    color: #dc3232;
}

@media (max-width: 782px) {
    .cbd-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Color Picker initialisieren
    $('.cbd-color-picker').wpColorPicker();
    
    // Form Submit Handler
    $('#cbd-block-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#cbd-save-block');
        var originalText = $button.text();
        
        // Button deaktivieren
        $button.prop('disabled', true).text('<?php esc_attr_e('Erstellen...', 'container-block-designer'); ?>');
        
        // Daten sammeln
        var formData = $form.serialize();
        formData += '&action=cbd_save_block';
        
        // AJAX Request
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                // Erfolg - zur Bearbeitungsseite weiterleiten
                window.location.href = '<?php echo admin_url('admin.php?page=cbd-edit-block&id='); ?>' + response.data.block.id;
            } else {
                // Fehler
                alert(response.data.message || '<?php esc_attr_e('Ein Fehler ist aufgetreten', 'container-block-designer'); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        }).fail(function() {
            alert('<?php esc_attr_e('Verbindungsfehler', 'container-block-designer'); ?>');
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Name-Feld Validierung
    $('#block-name').on('input', function() {
        var value = $(this).val();
        var cleaned = value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/--+/g, '-');
        if (value !== cleaned) {
            $(this).val(cleaned);
        }
    });
});
</script>