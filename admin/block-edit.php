<?php
/**
 * Container Block Designer - Block Edit Template
 * Template für das Bearbeiten/Erstellen von Blöcken
 * Version: 2.4.0
 * 
 * Datei speichern als: admin/block-edit.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $is_new ? __('Neuen Block hinzufügen', 'container-block-designer') : __('Block bearbeiten', 'container-block-designer');
?>

<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Block erfolgreich aktualisiert.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['created'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Block erfolgreich erstellt.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="cbd-block-form">
        <?php wp_nonce_field('cbd-save-block'); ?>
        
        <?php if (!$is_new): ?>
            <input type="hidden" name="block_id" value="<?php echo esc_attr($block['id']); ?>">
        <?php endif; ?>
        
        <div class="cbd-form-container">
            <!-- Left Column - Main Settings -->
            <div class="cbd-form-main">
                
                <!-- Basic Information -->
                <div class="cbd-form-section">
                    <h2><?php _e('Block-Informationen', 'container-block-designer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="block_name"><?php _e('Block-Name', 'container-block-designer'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="block_name" 
                                       name="block_name" 
                                       value="<?php echo esc_attr($block['name'] ?? ''); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description"><?php _e('Ein aussagekräftiger Name für den Block.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="block_slug"><?php _e('Block-Slug', 'container-block-designer'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="block_slug" 
                                       name="block_slug" 
                                       value="<?php echo esc_attr($block['slug'] ?? ''); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description"><?php _e('Eindeutige Kennung (nur Kleinbuchstaben, Zahlen und Bindestriche).', 'container-block-designer'); ?></p>
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
                                          class="large-text"><?php echo esc_textarea($block['description'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('Kurze Beschreibung des Blocks.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="block_status"><?php _e('Status', 'container-block-designer'); ?></label>
                            </th>
                            <td>
                                <select id="block_status" name="block_status">
                                    <option value="active" <?php selected($block['status'] ?? 'active', 'active'); ?>><?php _e('Aktiv', 'container-block-designer'); ?></option>
                                    <option value="inactive" <?php selected($block['status'] ?? 'active', 'inactive'); ?>><?php _e('Inaktiv', 'container-block-designer'); ?></option>
                                    <option value="draft" <?php selected($block['status'] ?? 'active', 'draft'); ?>><?php _e('Entwurf', 'container-block-designer'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Styling Settings -->
                <div class="cbd-form-section">
                    <h2><?php _e('Design-Einstellungen', 'container-block-designer'); ?></h2>
                    
                    <div class="cbd-style-tabs">
                        <nav class="nav-tab-wrapper">
                            <a href="#style-background" class="nav-tab nav-tab-active"><?php _e('Hintergrund', 'container-block-designer'); ?></a>
                            <a href="#style-border" class="nav-tab"><?php _e('Rahmen', 'container-block-designer'); ?></a>
                            <a href="#style-spacing" class="nav-tab"><?php _e('Abstände', 'container-block-designer'); ?></a>
                            <a href="#style-text" class="nav-tab"><?php _e('Text', 'container-block-designer'); ?></a>
                            <a href="#style-shadow" class="nav-tab"><?php _e('Schatten', 'container-block-designer'); ?></a>
                        </nav>
                        
                        <div class="tab-content">
                            <!-- Background Tab -->
                            <div id="style-background" class="tab-pane active">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="bg_color"><?php _e('Hintergrundfarbe', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="bg_color" 
                                                   name="bg_color" 
                                                   value="<?php echo esc_attr($block['styles']['background']['color'] ?? '#ffffff'); ?>" 
                                                   class="cbd-color-picker">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="bg_type"><?php _e('Hintergrund-Typ', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <select id="bg_type" name="bg_type">
                                                <option value="solid" <?php selected($block['styles']['background']['type'] ?? 'solid', 'solid'); ?>><?php _e('Einfarbig', 'container-block-designer'); ?></option>
                                                <option value="gradient" <?php selected($block['styles']['background']['type'] ?? 'solid', 'gradient'); ?>><?php _e('Verlauf', 'container-block-designer'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Border Tab -->
                            <div id="style-border" class="tab-pane">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="border_width"><?php _e('Rahmen-Breite', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="border_width" 
                                                   name="border_width" 
                                                   value="<?php echo esc_attr($block['styles']['border']['width'] ?? '1px'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="border_style"><?php _e('Rahmen-Stil', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <select id="border_style" name="border_style">
                                                <option value="solid" <?php selected($block['styles']['border']['style'] ?? 'solid', 'solid'); ?>><?php _e('Durchgezogen', 'container-block-designer'); ?></option>
                                                <option value="dashed" <?php selected($block['styles']['border']['style'] ?? 'solid', 'dashed'); ?>><?php _e('Gestrichelt', 'container-block-designer'); ?></option>
                                                <option value="dotted" <?php selected($block['styles']['border']['style'] ?? 'solid', 'dotted'); ?>><?php _e('Gepunktet', 'container-block-designer'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="border_color"><?php _e('Rahmen-Farbe', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="border_color" 
                                                   name="border_color" 
                                                   value="<?php echo esc_attr($block['styles']['border']['color'] ?? '#e0e0e0'); ?>" 
                                                   class="cbd-color-picker">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="border_radius"><?php _e('Ecken-Radius', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="border_radius" 
                                                   name="border_radius" 
                                                   value="<?php echo esc_attr($block['styles']['border']['radius'] ?? '6px'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Spacing Tab -->
                            <div id="style-spacing" class="tab-pane">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="spacing_padding"><?php _e('Innen-Abstand', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="spacing_padding" 
                                                   name="spacing_padding" 
                                                   value="<?php echo esc_attr($block['styles']['spacing']['padding'] ?? '20px'); ?>" 
                                                   class="regular-text">
                                            <p class="description"><?php _e('z.B. "20px" oder "20px 30px 20px 30px"', 'container-block-designer'); ?></p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="spacing_margin"><?php _e('Außen-Abstand', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="spacing_margin" 
                                                   name="spacing_margin" 
                                                   value="<?php echo esc_attr($block['styles']['spacing']['margin'] ?? '20px 0'); ?>" 
                                                   class="regular-text">
                                            <p class="description"><?php _e('z.B. "20px 0" oder "20px 30px 20px 30px"', 'container-block-designer'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Text Tab -->
                            <div id="style-text" class="tab-pane">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="text_color"><?php _e('Text-Farbe', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="text_color" 
                                                   name="text_color" 
                                                   value="<?php echo esc_attr($block['styles']['text']['color'] ?? '#000000'); ?>" 
                                                   class="cbd-color-picker">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="text_size"><?php _e('Schriftgröße', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="text_size" 
                                                   name="text_size" 
                                                   value="<?php echo esc_attr($block['styles']['text']['size'] ?? '16px'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="text_align"><?php _e('Text-Ausrichtung', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <select id="text_align" name="text_align">
                                                <option value="left" <?php selected($block['styles']['text']['align'] ?? 'left', 'left'); ?>><?php _e('Links', 'container-block-designer'); ?></option>
                                                <option value="center" <?php selected($block['styles']['text']['align'] ?? 'left', 'center'); ?>><?php _e('Zentriert', 'container-block-designer'); ?></option>
                                                <option value="right" <?php selected($block['styles']['text']['align'] ?? 'left', 'right'); ?>><?php _e('Rechts', 'container-block-designer'); ?></option>
                                                <option value="justify" <?php selected($block['styles']['text']['align'] ?? 'left', 'justify'); ?>><?php _e('Blocksatz', 'container-block-designer'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Shadow Tab -->
                            <div id="style-shadow" class="tab-pane">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="shadow_enabled"><?php _e('Schatten aktivieren', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <label class="cbd-switch">
                                                <input type="checkbox" 
                                                       id="shadow_enabled" 
                                                       name="shadow_enabled" 
                                                       value="1" 
                                                       <?php checked(!empty($block['styles']['shadow']['enabled'])); ?>>
                                                <span class="cbd-switch-slider"></span>
                                                <span class="cbd-switch-label"><?php _e('Schatten anzeigen', 'container-block-designer'); ?></span>
                                            </label>
                                        </td>
                                    </tr>
                                    
                                    <tr class="cbd-shadow-settings" <?php if (empty($block['styles']['shadow']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                        <th scope="row">
                                            <label for="shadow_x"><?php _e('Schatten X-Offset', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="shadow_x" 
                                                   name="shadow_x" 
                                                   value="<?php echo esc_attr($block['styles']['shadow']['x'] ?? '0'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                    
                                    <tr class="cbd-shadow-settings" <?php if (empty($block['styles']['shadow']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                        <th scope="row">
                                            <label for="shadow_y"><?php _e('Schatten Y-Offset', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="shadow_y" 
                                                   name="shadow_y" 
                                                   value="<?php echo esc_attr($block['styles']['shadow']['y'] ?? '2px'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                    
                                    <tr class="cbd-shadow-settings" <?php if (empty($block['styles']['shadow']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                        <th scope="row">
                                            <label for="shadow_blur"><?php _e('Schatten Unschärfe', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="shadow_blur" 
                                                   name="shadow_blur" 
                                                   value="<?php echo esc_attr($block['styles']['shadow']['blur'] ?? '8px'); ?>" 
                                                   class="small-text">
                                        </td>
                                    </tr>
                                    
                                    <tr class="cbd-shadow-settings" <?php if (empty($block['styles']['shadow']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                        <th scope="row">
                                            <label for="shadow_color"><?php _e('Schatten Farbe', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="shadow_color" 
                                                   name="shadow_color" 
                                                   value="<?php echo esc_attr($block['styles']['shadow']['color'] ?? 'rgba(0,0,0,0.1)'); ?>" 
                                                   class="regular-text">
                                            <p class="description"><?php _e('z.B. "rgba(0,0,0,0.1)" oder "#000000"', 'container-block-designer'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Features Section -->
                <div class="cbd-form-section">
                    <h2><?php _e('Block-Features', 'container-block-designer'); ?></h2>
                    
                    <div class="cbd-features-container">
                        
                        <!-- Icon Feature -->
                        <div class="cbd-feature-item">
                            <div class="cbd-feature-header">
                                <label class="cbd-feature-toggle">
                                    <input type="checkbox" 
                                           id="feature_icon_enabled" 
                                           name="feature_icon_enabled" 
                                           value="1"
                                           <?php checked(!empty($block['features']['icon']['enabled'])); ?>>
                                    <span class="cbd-toggle-slider"></span>
                                </label>
                                <div class="cbd-feature-info">
                                    <strong><?php _e('Block-Icon', 'container-block-designer'); ?></strong>
                                    <p><?php _e('Zeigt ein Icon im Container an', 'container-block-designer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="cbd-feature-settings" id="feature-icon-settings" <?php if (empty($block['features']['icon']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="icon_value"><?php _e('Icon auswählen', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <div class="cbd-icon-selector">
                                                <input type="text" 
                                                       id="icon_value" 
                                                       name="icon_value" 
                                                       value="<?php echo esc_attr($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?>" 
                                                       class="regular-text">
                                                <button type="button" class="button cbd-icon-picker"><?php _e('Icon wählen', 'container-block-designer'); ?></button>
                                                <div class="cbd-current-icon">
                                                    <span class="dashicons <?php echo esc_attr($block['features']['icon']['value'] ?? 'dashicons-admin-generic'); ?>"></span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Icon Position Settings -->
                                <?php 
                                $icon_position_settings = $block['features']['icon']['position'] ?? array();
                                CBD_Position_Settings::render_position_settings('icon', $icon_position_settings); 
                                ?>
                            </div>
                        </div>
                        
                        <!-- Numbering Feature -->
                        <div class="cbd-feature-item">
                            <div class="cbd-feature-header">
                                <label class="cbd-feature-toggle">
                                    <input type="checkbox" 
                                           id="feature_numbering_enabled" 
                                           name="feature_numbering_enabled" 
                                           value="1"
                                           <?php checked(!empty($block['features']['numbering']['enabled'])); ?>>
                                    <span class="cbd-toggle-slider"></span>
                                </label>
                                <div class="cbd-feature-info">
                                    <strong><?php _e('Nummerierung', 'container-block-designer'); ?></strong>
                                    <p><?php _e('Automatische Nummerierung der Container', 'container-block-designer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="cbd-feature-settings" id="feature-numbering-settings" <?php if (empty($block['features']['numbering']['enabled'])): ?>style="display:none;"<?php endif; ?>>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="numbering_format"><?php _e('Nummerierungs-Format', 'container-block-designer'); ?></label>
                                        </th>
                                        <td>
                                            <select id="numbering_format" name="numbering_format">
                                                <option value="decimal" <?php selected($block['features']['numbering']['format'] ?? 'decimal', 'decimal'); ?>><?php _e('Dezimal (1, 2, 3...)', 'container-block-designer'); ?></option>
                                                <option value="alpha" <?php selected($block['features']['numbering']['format'] ?? 'decimal', 'alpha'); ?>><?php _e('Alphabetisch (A, B, C...)', 'container-block-designer'); ?></option>
                                                <option value="roman" <?php selected($block['features']['numbering']['format'] ?? 'decimal', 'roman'); ?>><?php _e('Römisch (I, II, III...)', 'container-block-designer'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Numbering Position Settings -->
                                <?php 
                                $numbering_position_settings = $block['features']['numbering']['position'] ?? array();
                                CBD_Position_Settings::render_position_settings('numbering', $numbering_position_settings); 
                                ?>
                            </div>
                        </div>
                        
                        <!-- Additional Features (Collapse, Copy, Screenshot) -->
                        <!-- Diese würden hier ähnlich implementiert -->
                        
                    </div>
                </div>
                
                <!-- Block Configuration -->
                <div class="cbd-form-section">
                    <h2><?php _e('Block-Konfiguration', 'container-block-designer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="allow_inner_blocks"><?php _e('Innere Blöcke erlauben', 'container-block-designer'); ?></label>
                            </th>
                            <td>
                                <label class="cbd-switch">
                                    <input type="checkbox" 
                                           id="allow_inner_blocks" 
                                           name="allow_inner_blocks" 
                                           value="1" 
                                           <?php checked(!empty($block['config']['allowInnerBlocks'])); ?>>
                                    <span class="cbd-switch-slider"></span>
                                    <span class="cbd-switch-label"><?php _e('Benutzer können Inhalte in den Container einfügen', 'container-block-designer'); ?></span>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="template_lock"><?php _e('Template Lock', 'container-block-designer'); ?></label>
                            </th>
                            <td>
                                <select id="template_lock" name="template_lock">
                                    <option value="false" <?php selected($block['config']['templateLock'] ?? 'false', 'false'); ?>><?php _e('Keine Beschränkung', 'container-block-designer'); ?></option>
                                    <option value="all" <?php selected($block['config']['templateLock'] ?? 'false', 'all'); ?>><?php _e('Alle Aktionen sperren', 'container-block-designer'); ?></option>
                                    <option value="insert" <?php selected($block['config']['templateLock'] ?? 'false', 'insert'); ?>><?php _e('Einfügen sperren', 'container-block-designer'); ?></option>
                                </select>
                                <p class="description"><?php _e('Bestimmt, welche Aktionen Benutzer mit inneren Blöcken ausführen können.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_width"><?php _e('Maximale Breite', 'container-block-designer'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="max_width" 
                                       name="max_width" 
                                       value="<?php echo esc_attr($block['config']['maxWidth'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php _e('z.B. "800px" oder "100%" (leer = keine Begrenzung)', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="custom_class"><?php _e('Benutzerdefinierte CSS-Klasse', 'container-block-designer'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="custom_class" 
                                       name="custom_class" 
                                       value="<?php echo esc_attr($block['config']['customClass'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php _e('Zusätzliche CSS-Klasse für erweiterte Anpassungen.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            </div>
            
            <!-- Right Sidebar - Preview & Actions -->
            <div class="cbd-form-sidebar">
                
                <!-- Actions -->
                <div class="cbd-sidebar-section">
                    <h3><?php _e('Aktionen', 'container-block-designer'); ?></h3>
                    
                    <div class="cbd-actions">
                        <p class="submit">
                            <button type="submit" name="cbd_save_block" class="button-primary">
                                <?php echo $is_new ? __('Block erstellen', 'container-block-designer') : __('Block aktualisieren', 'container-block-designer'); ?>
                            </button>
                        </p>
                        
                        <?php if (!$is_new): ?>
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=container-block-designer-new'); ?>" class="button">
                                    <?php _e('Als neuen Block kopieren', 'container-block-designer'); ?>
                                </a>
                            </p>
                            
                            <p>
                                <button type="submit" 
                                        name="cbd_delete_block" 
                                        class="button button-link-delete" 
                                        onclick="return confirm('<?php _e('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'); ?>')">
                                    <?php _e('Block löschen', 'container-block-designer'); ?>
                                </button>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Preview -->
                <div class="cbd-sidebar-section">
                    <h3><?php _e('Vorschau', 'container-block-designer'); ?></h3>
                    
                    <div class="cbd-preview-container" id="block-preview">
                        <div class="cbd-preview-frame">
                            <div class="cbd-container-preview" id="preview-output">
                                <!-- Preview wird hier dynamisch generiert -->
                                <div class="cbd-preview-placeholder">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <p><?php _e('Vorschau wird geladen...', 'container-block-designer'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cbd-preview-controls">
                        <label class="cbd-switch">
                            <input type="checkbox" id="live-preview-toggle" checked>
                            <span class="cbd-switch-slider"></span>
                            <span class="cbd-switch-label"><?php _e('Live-Vorschau', 'container-block-designer'); ?></span>
                        </label>
                        
                        <button type="button" class="button button-small" id="refresh-preview">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Aktualisieren', 'container-block-designer'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Block Information -->
                <?php if (!$is_new): ?>
                <div class="cbd-sidebar-section">
                    <h3><?php _e('Block-Information', 'container-block-designer'); ?></h3>
                    
                    <div class="cbd-block-info">
                        <dl>
                            <dt><?php _e('Erstellt:', 'container-block-designer'); ?></dt>
                            <dd><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $block['created_at'])); ?></dd>
                            
                            <dt><?php _e('Zuletzt geändert:', 'container-block-designer'); ?></dt>
                            <dd><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $block['updated_at'])); ?></dd>
                            
                            <dt><?php _e('Block-ID:', 'container-block-designer'); ?></dt>
                            <dd><?php echo esc_html($block['id']); ?></dd>
                            
                            <dt><?php _e('Status:', 'container-block-designer'); ?></dt>
                            <dd>
                                <span class="cbd-status cbd-status-<?php echo esc_attr($block['status']); ?>">
                                    <?php 
                                    switch ($block['status']) {
                                        case 'active':
                                            _e('Aktiv', 'container-block-designer');
                                            break;
                                        case 'inactive':
                                            _e('Inaktiv', 'container-block-designer');
                                            break;
                                        case 'draft':
                                            _e('Entwurf', 'container-block-designer');
                                            break;
                                    }
                                    ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Help -->
                <div class="cbd-sidebar-section">
                    <h3><?php _e('Hilfe', 'container-block-designer'); ?></h3>
                    
                    <div class="cbd-help-content">
                        <h4><?php _e('Positionierung', 'container-block-designer'); ?></h4>
                        <p><?php _e('Icons und Zähler können flexibel positioniert werden:', 'container-block-designer'); ?></p>
                        <ul>
                            <li><?php _e('<strong>Innerhalb:</strong> Element wird im Container platziert', 'container-block-designer'); ?></li>
                            <li><?php _e('<strong>Außerhalb:</strong> Element wird außerhalb des Container-Rahmens platziert', 'container-block-designer'); ?></li>
                            <li><?php _e('<strong>Offset:</strong> Feinabstimmung der Position in Pixeln', 'container-block-designer'); ?></li>
                            <li><?php _e('<strong>Z-Index:</strong> Bestimmt die Ebenen-Reihenfolge (höher = weiter vorne)', 'container-block-designer'); ?></li>
                        </ul>
                        
                        <h4><?php _e('CSS-Werte', 'container-block-designer'); ?></h4>
                        <p><?php _e('Alle Farbwerte können in verschiedenen Formaten eingegeben werden:', 'container-block-designer'); ?></p>
                        <ul>
                            <li><code>#ff0000</code> (Hex)</li>
                            <li><code>rgb(255, 0, 0)</code> (RGB)</li>
                            <li><code>rgba(255, 0, 0, 0.5)</code> (RGBA)</li>
                        </ul>
                        
                        <h4><?php _e('Abstände', 'container-block-designer'); ?></h4>
                        <p><?php _e('Abstände können in verschiedenen Einheiten angegeben werden:', 'container-block-designer'); ?></p>
                        <ul>
                            <li><code>20px</code> (alle Seiten)</li>
                            <li><code>20px 30px</code> (oben/unten, links/rechts)</li>
                            <li><code>10px 20px 30px 40px</code> (oben, rechts, unten, links)</li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
        
    </form>
</div>

<!-- JavaScript für Live-Vorschau und Interaktivität -->
<script>
jQuery(document).ready(function($) {
    
    // Tab-Funktionalität
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Tab-Navigation aktualisieren
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Tab-Content anzeigen/verstecken
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    
    // Feature-Toggle
    $('.cbd-feature-toggle input[type="checkbox"]').on('change', function() {
        var $settings = $(this).closest('.cbd-feature-item').find('.cbd-feature-settings');
        
        if ($(this).is(':checked')) {
            $settings.slideDown();
        } else {
            $settings.slideUp();
        }
        
        updatePreview();
    });
    
    // Schatten-Toggle
    $('#shadow_enabled').on('change', function() {
        var $shadowSettings = $('.cbd-shadow-settings');
        
        if ($(this).is(':checked')) {
            $shadowSettings.show();
        } else {
            $shadowSettings.hide();
        }
        
        updatePreview();
    });
    
    // Live-Vorschau
    var livePreviewEnabled = true;
    
    $('#live-preview-toggle').on('change', function() {
        livePreviewEnabled = $(this).is(':checked');
        
        if (livePreviewEnabled) {
            updatePreview();
        }
    });
    
    // Vorschau aktualisieren bei Eingabe-Änderungen
    $('.cbd-form-main input, .cbd-form-main select, .cbd-form-main textarea').on('input change', function() {
        if (livePreviewEnabled) {
            debounce(updatePreview, 300)();
        }
    });
    
    // Vorschau manuell aktualisieren
    $('#refresh-preview').on('click', function() {
        updatePreview();
    });
    
    // Block-Name zu Slug konvertieren
    $('#block_name').on('input', function() {
        if ($('#block_slug').val() === '' || isGeneratedSlug) {
            var slug = $(this).val()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            $('#block_slug').val(slug);
            isGeneratedSlug = true;
        }
    });
    
    var isGeneratedSlug = $('#block_slug').val() === '';
    
    $('#block_slug').on('input', function() {
        isGeneratedSlug = false;
    });
    
    // Vorschau-Funktion
    function updatePreview() {
        var previewData = {
            styles: getStylesData(),
            features: getFeaturesData(),
            config: getConfigData()
        };
        
        generatePreviewHTML(previewData);
    }
    
    function getStylesData() {
        return {
            background: {
                color: $('#bg_color').val(),
                type: $('#bg_type').val()
            },
            border: {
                width: $('#border_width').val(),
                style: $('#border_style').val(),
                color: $('#border_color').val(),
                radius: $('#border_radius').val()
            },
            spacing: {
                padding: $('#spacing_padding').val(),
                margin: $('#spacing_margin').val()
            },
            text: {
                color: $('#text_color').val(),
                size: $('#text_size').val(),
                align: $('#text_align').val()
            },
            shadow: {
                enabled: $('#shadow_enabled').is(':checked'),
                x: $('#shadow_x').val(),
                y: $('#shadow_y').val(),
                blur: $('#shadow_blur').val(),
                color: $('#shadow_color').val()
            }
        };
    }
    
    function getFeaturesData() {
        var features = {};
        
        // Icon
        if ($('#feature_icon_enabled').is(':checked')) {
            features.icon = {
                enabled: true,
                value: $('#icon_value').val(),
                position: {
                    placement: $('input[name="icon_placement"]:checked').val(),
                    position: $('select[name="icon_position"]').val(),
                    offset_x: $('input[name="icon_offset_x"]').val(),
                    offset_y: $('input[name="icon_offset_y"]').val(),
                    z_index: $('input[name="icon_z_index"]').val()
                }
            };
        }
        
        // Numbering
        if ($('#feature_numbering_enabled').is(':checked')) {
            features.numbering = {
                enabled: true,
                format: $('#numbering_format').val(),
                position: {
                    placement: $('input[name="numbering_placement"]:checked').val(),
                    position: $('select[name="numbering_position"]').val(),
                    offset_x: $('input[name="numbering_offset_x"]').val(),
                    offset_y: $('input[name="numbering_offset_y"]').val(),
                    z_index: $('input[name="numbering_z_index"]').val()
                }
            };
        }
        
        return features;
    }
    
    function getConfigData() {
        return {
            allowInnerBlocks: $('#allow_inner_blocks').is(':checked'),
            templateLock: $('#template_lock').val(),
            maxWidth: $('#max_width').val(),
            customClass: $('#custom_class').val()
        };
    }
    
    function generatePreviewHTML(data) {
        var $preview = $('#preview-output');
        
        // Container-Styles generieren
        var containerStyles = '';
        
        if (data.styles.background.color) {
            containerStyles += 'background-color: ' + data.styles.background.color + '; ';
        }
        
        if (data.styles.border.width && data.styles.border.style && data.styles.border.color) {
            containerStyles += 'border: ' + data.styles.border.width + ' ' + data.styles.border.style + ' ' + data.styles.border.color + '; ';
        }
        
        if (data.styles.border.radius) {
            containerStyles += 'border-radius: ' + data.styles.border.radius + '; ';
        }
        
        if (data.styles.spacing.padding) {
            containerStyles += 'padding: ' + data.styles.spacing.padding + '; ';
        }
        
        if (data.styles.text.color) {
            containerStyles += 'color: ' + data.styles.text.color + '; ';
        }
        
        if (data.styles.text.size) {
            containerStyles += 'font-size: ' + data.styles.text.size + '; ';
        }
        
        if (data.styles.text.align) {
            containerStyles += 'text-align: ' + data.styles.text.align + '; ';
        }
        
        if (data.styles.shadow.enabled && data.styles.shadow.x && data.styles.shadow.y && data.styles.shadow.blur && data.styles.shadow.color) {
            containerStyles += 'box-shadow: ' + data.styles.shadow.x + ' ' + data.styles.shadow.y + ' ' + data.styles.shadow.blur + ' ' + data.styles.shadow.color + '; ';
        }
        
        if (data.config.maxWidth) {
            containerStyles += 'max-width: ' + data.config.maxWidth + '; ';
        }
        
        // HTML generieren
        var html = '<div class="cbd-container" style="' + containerStyles + ' position: relative; min-height: 100px;">';
        
        // Icon hinzufügen
        if (data.features.icon && data.features.icon.enabled) {
            var iconPosition = data.features.icon.position;
            var iconStyles = generatePositionStyles(iconPosition);
            var iconClasses = generatePositionClasses(iconPosition);
            
            html += '<div class="cbd-container-icon cbd-positioned ' + iconClasses + '" style="' + iconStyles + '">';
            html += '<span class="dashicons ' + data.features.icon.value + '"></span>';
            html += '</div>';
        }
        
        // Numbering hinzufügen
        if (data.features.numbering && data.features.numbering.enabled) {
            var numberPosition = data.features.numbering.position;
            var numberStyles = generatePositionStyles(numberPosition);
            var numberClasses = generatePositionClasses(numberPosition);
            var numberValue = getFormattedNumber(data.features.numbering.format, 1);
            
            html += '<div class="cbd-container-number cbd-positioned ' + numberClasses + '" style="' + numberStyles + '">';
            html += numberValue;
            html += '</div>';
        }
        
        // Platzhalter-Inhalt
        html += '<div class="cbd-preview-content">';
        html += '<h4>Beispiel-Überschrift</h4>';
        html += '<p>Dies ist ein Beispieltext, um zu zeigen, wie der Container mit Inhalt aussehen wird. Der Text passt sich an die gewählten Styling-Einstellungen an.</p>';
        html += '</div>';
        
        html += '</div>';
        
        $preview.html(html);
    }
    
    function generatePositionStyles(position) {
        if (!position) return '';
        
        var styles = [];
        styles.push('position: absolute');
        styles.push('z-index: ' + (position.z_index || '100'));
        
        var placement = position.placement || 'inside';
        var pos = position.position || 'top-left';
        var offsetX = parseInt(position.offset_x || '10');
        var offsetY = parseInt(position.offset_y || '10');
        
        if (placement === 'outside') {
            switch (pos) {
                case 'outside-top-left':
                    styles.push('top: -' + offsetY + 'px');
                    styles.push('left: -' + offsetX + 'px');
                    break;
                case 'outside-top-center':
                    styles.push('top: -' + offsetY + 'px');
                    styles.push('left: 50%');
                    styles.push('transform: translateX(-50%)');
                    break;
                case 'outside-top-right':
                    styles.push('top: -' + offsetY + 'px');
                    styles.push('right: -' + offsetX + 'px');
                    break;
                case 'outside-bottom-left':
                    styles.push('bottom: -' + offsetY + 'px');
                    styles.push('left: -' + offsetX + 'px');
                    break;
                case 'outside-bottom-center':
                    styles.push('bottom: -' + offsetY + 'px');
                    styles.push('left: 50%');
                    styles.push('transform: translateX(-50%)');
                    break;
                case 'outside-bottom-right':
                    styles.push('bottom: -' + offsetY + 'px');
                    styles.push('right: -' + offsetX + 'px');
                    break;
                case 'outside-left-middle':
                    styles.push('left: -' + offsetX + 'px');
                    styles.push('top: 50%');
                    styles.push('transform: translateY(-50%)');
                    break;
                case 'outside-right-middle':
                    styles.push('right: -' + offsetX + 'px');
                    styles.push('top: 50%');
                    styles.push('transform: translateY(-50%)');
                    break;
            }
        } else {
            // Inside positioning
            var cleanPos = pos.replace('outside-', '');
            switch (cleanPos) {
                case 'top-left':
                    styles.push('top: ' + offsetY + 'px');
                    styles.push('left: ' + offsetX + 'px');
                    break;
                case 'top-center':
                    styles.push('top: ' + offsetY + 'px');
                    styles.push('left: 50%');
                    styles.push('transform: translateX(-50%)');
                    break;
                case 'top-right':
                    styles.push('top: ' + offsetY + 'px');
                    styles.push('right: ' + offsetX + 'px');
                    break;
                case 'middle-left':
                    styles.push('left: ' + offsetX + 'px');
                    styles.push('top: 50%');
                    styles.push('transform: translateY(-50%)');
                    break;
                case 'middle-center':
                    styles.push('top: 50%');
                    styles.push('left: 50%');
                    styles.push('transform: translate(-50%, -50%)');
                    break;
                case 'middle-right':
                    styles.push('right: ' + offsetX + 'px');
                    styles.push('top: 50%');
                    styles.push('transform: translateY(-50%)');
                    break;
                case 'bottom-left':
                    styles.push('bottom: ' + offsetY + 'px');
                    styles.push('left: ' + offsetX + 'px');
                    break;
                case 'bottom-center':
                    styles.push('bottom: ' + offsetY + 'px');
                    styles.push('left: 50%');
                    styles.push('transform: translateX(-50%)');
                    break;
                case 'bottom-right':
                    styles.push('bottom: ' + offsetY + 'px');
                    styles.push('right: ' + offsetX + 'px');
                    break;
            }
        }
        
        return styles.join('; ');
    }
    
    function generatePositionClasses(position) {
        if (!position) return '';
        
        var classes = ['cbd-positioned'];
        classes.push('cbd-' + (position.placement || 'inside'));
        
        var cleanPos = (position.position || 'top-left').replace('outside-', '');
        classes.push('cbd-' + cleanPos);
        
        return classes.join(' ');
    }
    
    function getFormattedNumber(format, index) {
        switch (format) {
            case 'alpha':
                return String.fromCharCode(64 + index); // A, B, C...
            case 'roman':
                return intToRoman(index);
            case 'decimal':
            default:
                return index.toString();
        }
    }
    
    function intToRoman(num) {
        var lookup = {M: 1000, CM: 900, D: 500, CD: 400, C: 100, XC: 90, L: 50, XL: 40, X: 10, IX: 9, V: 5, IV: 4, I: 1};
        var roman = '';
        for (var i in lookup) {
            while (num >= lookup[i]) {
                roman += i;
                num -= lookup[i];
            }
        }
        return roman;
    }
    
    // Debounce-Funktion
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Initiale Vorschau laden
    updatePreview();
});
</script>