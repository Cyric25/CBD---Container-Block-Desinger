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

// Block-ID abrufen
$block_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
    'border' => array('width' => 1, 'color' => '#e0e0e0', 'style' => 'solid', 'radius' => 4)
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
                        </table>
                    </div>
                    
                </div>
                
                <!-- Sidebar -->
                <div class="cbd-sidebar">
                    <div class="cbd-card">
                        <h3><?php _e('Aktionen', 'container-block-designer'); ?></h3>
                        
                        <button type="submit" class="button button-primary button-large" id="cbd-save-block">
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
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($block['created'])); ?>
                        </p>
                        
                        <p>
                            <strong><?php _e('Aktualisiert:', 'container-block-designer'); ?></strong><br>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($block['updated'])); ?>
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
        $button.prop('disabled', true).text('<?php esc_attr_e('Speichern...', 'container-block-designer'); ?>');
        
        // Daten sammeln
        var formData = $form.serialize();
        formData += '&action=cbd_save_block';
        
        // AJAX Request
        $.post(ajaxurl, formData, function(response) {
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
        }).fail(function() {
            alert('<?php esc_attr_e('Verbindungsfehler', 'container-block-designer'); ?>');
            $button.prop('disabled', false).text(originalText);
        });
    });
});
</script>