<?php
/**
 * Container Block Designer - Settings Controller
 * 
 * @package ContainerBlockDesigner\Admin\Controllers
 * @since 2.6.0
 */

namespace ContainerBlockDesigner\Admin\Controllers;

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Controller
 * Handles plugin settings and configuration
 */
class SettingsController {
    
    /**
     * Settings sections
     */
    private $sections = array();
    
    /**
     * Settings fields
     */
    private $fields = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_settings();
    }
    
    /**
     * Render the settings page
     */
    public function render() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben keine Berechtigung, diese Seite zu besuchen.', 'container-block-designer'));
        }
        
        // Handle form submission
        $this->handle_form_submission();
        
        // Render view
        $this->render_view();
    }
    
    /**
     * Initialize settings
     */
    private function init_settings() {
        $this->sections = array(
            'general' => array(
                'title' => __('Allgemeine Einstellungen', 'container-block-designer'),
                'description' => __('Grundlegende Plugin-Konfiguration.', 'container-block-designer')
            ),
            'advanced' => array(
                'title' => __('Erweiterte Einstellungen', 'container-block-designer'),
                'description' => __('Erweiterte Optionen für erfahrene Benutzer.', 'container-block-designer')
            ),
            'performance' => array(
                'title' => __('Performance', 'container-block-designer'),
                'description' => __('Optimierungseinstellungen für bessere Performance.', 'container-block-designer')
            )
        );
        
        $this->fields = array(
            'general' => array(
                'enable_frontend_assets' => array(
                    'title' => __('Frontend-Assets laden', 'container-block-designer'),
                    'type' => 'checkbox',
                    'description' => __('Lädt CSS und JavaScript im Frontend.', 'container-block-designer'),
                    'default' => true
                ),
                'enable_editor_assets' => array(
                    'title' => __('Editor-Assets laden', 'container-block-designer'),
                    'type' => 'checkbox',
                    'description' => __('Lädt CSS und JavaScript im Block-Editor.', 'container-block-designer'),
                    'default' => true
                ),
                'default_block_status' => array(
                    'title' => __('Standard-Block-Status', 'container-block-designer'),
                    'type' => 'select',
                    'options' => array(
                        'active' => __('Aktiv', 'container-block-designer'),
                        'inactive' => __('Inaktiv', 'container-block-designer')
                    ),
                    'description' => __('Status für neue Blöcke.', 'container-block-designer'),
                    'default' => 'active'
                )
            ),
            'advanced' => array(
                'custom_css' => array(
                    'title' => __('Benutzerdefiniertes CSS', 'container-block-designer'),
                    'type' => 'textarea',
                    'description' => __('Zusätzliches CSS für alle Container-Blöcke.', 'container-block-designer'),
                    'default' => ''
                ),
                'enable_debug_mode' => array(
                    'title' => __('Debug-Modus', 'container-block-designer'),
                    'type' => 'checkbox',
                    'description' => __('Aktiviert erweiterte Logging-Funktionen.', 'container-block-designer'),
                    'default' => false
                ),
                'allowed_html_tags' => array(
                    'title' => __('Erlaubte HTML-Tags', 'container-block-designer'),
                    'type' => 'text',
                    'description' => __('Kommaseparierte Liste erlaubter HTML-Tags in Blöcken.', 'container-block-designer'),
                    'default' => 'div,span,p,h1,h2,h3,h4,h5,h6,a,img,ul,ol,li'
                )
            ),
            'performance' => array(
                'enable_caching' => array(
                    'title' => __('Block-Caching aktivieren', 'container-block-designer'),
                    'type' => 'checkbox',
                    'description' => __('Cached Block-Daten für bessere Performance.', 'container-block-designer'),
                    'default' => true
                ),
                'cache_duration' => array(
                    'title' => __('Cache-Dauer (Stunden)', 'container-block-designer'),
                    'type' => 'number',
                    'description' => __('Wie lange Block-Daten im Cache gespeichert werden.', 'container-block-designer'),
                    'default' => 24,
                    'min' => 1,
                    'max' => 168
                ),
                'minify_assets' => array(
                    'title' => __('Assets minifizieren', 'container-block-designer'),
                    'type' => 'checkbox',
                    'description' => __('Verwendet minifizierte CSS- und JS-Dateien.', 'container-block-designer'),
                    'default' => true
                )
            )
        );
    }
    
    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cbd_settings')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
        }
        
        // Process settings
        foreach ($this->fields as $section => $fields) {
            foreach ($fields as $field_key => $field_config) {
                $value = $this->sanitize_field_value($_POST[$field_key] ?? '', $field_config);
                update_option('cbd_' . $field_key, $value);
            }
        }
        
        // Clear caches if caching settings changed
        if (isset($_POST['enable_caching']) || isset($_POST['cache_duration'])) {
            $this->clear_caches();
        }
        
        $this->add_notice(__('Einstellungen wurden gespeichert.', 'container-block-designer'), 'success');
    }
    
    /**
     * Sanitize field value based on field type
     */
    private function sanitize_field_value($value, $config) {
        switch ($config['type']) {
            case 'checkbox':
                return !empty($value);
                
            case 'number':
                $number = intval($value);
                if (isset($config['min'])) {
                    $number = max($number, $config['min']);
                }
                if (isset($config['max'])) {
                    $number = min($number, $config['max']);
                }
                return $number;
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'select':
                return in_array($value, array_keys($config['options'])) ? $value : $config['default'];
                
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Clear all caches
     */
    private function clear_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        delete_transient('cbd_blocks_cache');
        delete_transient('cbd_styles_cache');
        
        // Clear any other plugin-specific caches
        do_action('cbd_clear_caches');
    }
    
    /**
     * Add admin notice
     */
    private function add_notice($message, $type = 'info') {
        add_settings_error('cbd_notices', 'cbd_notice', $message, $type);
    }
    
    /**
     * Render the view
     */
    private function render_view() {
        ?>
        <div class="wrap">
            <h1><?php _e('Container Block Designer - Einstellungen', 'container-block-designer'); ?></h1>
            
            <?php settings_errors('cbd_notices'); ?>
            
            <form method="post">
                <?php wp_nonce_field('cbd_settings'); ?>
                
                <div class="cbd-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <?php foreach ($this->sections as $section_key => $section): ?>
                            <a href="#<?php echo esc_attr($section_key); ?>" 
                               class="nav-tab <?php echo $section_key === 'general' ? 'nav-tab-active' : ''; ?>">
                                <?php echo esc_html($section['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    
                    <?php foreach ($this->sections as $section_key => $section): ?>
                        <div id="<?php echo esc_attr($section_key); ?>" 
                             class="tab-content <?php echo $section_key === 'general' ? 'active' : ''; ?>">
                            
                            <h2><?php echo esc_html($section['title']); ?></h2>
                            <p><?php echo esc_html($section['description']); ?></p>
                            
                            <table class="form-table">
                                <?php $this->render_section_fields($section_key); ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" 
                           value="<?php _e('Einstellungen speichern', 'container-block-designer'); ?>">
                    
                    <button type="button" id="cbd-clear-cache" class="button">
                        <?php _e('Cache leeren', 'container-block-designer'); ?>
                    </button>
                    
                    <button type="button" id="cbd-reset-settings" class="button">
                        <?php _e('Einstellungen zurücksetzen', 'container-block-designer'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show corresponding content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            // Clear cache button
            $('#cbd-clear-cache').on('click', function() {
                if (confirm('<?php _e('Sind Sie sicher, dass Sie den Cache leeren möchten?', 'container-block-designer'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'cbd_clear_cache',
                        _wpnonce: '<?php echo wp_create_nonce('cbd_clear_cache'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('Cache wurde geleert.', 'container-block-designer'); ?>');
                        } else {
                            alert('<?php _e('Fehler beim Leeren des Cache.', 'container-block-designer'); ?>');
                        }
                    });
                }
            });
            
            // Reset settings button
            $('#cbd-reset-settings').on('click', function() {
                if (confirm('<?php _e('Sind Sie sicher, dass Sie alle Einstellungen zurücksetzen möchten?', 'container-block-designer'); ?>')) {
                    window.location.href = '<?php echo wp_nonce_url(admin_url('admin.php?page=cbd-settings&action=reset'), 'cbd_reset_settings'); ?>';
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render fields for a specific section
     */
    private function render_section_fields($section_key) {
        if (!isset($this->fields[$section_key])) {
            return;
        }
        
        foreach ($this->fields[$section_key] as $field_key => $config) {
            $value = get_option('cbd_' . $field_key, $config['default']);
            
            ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($field_key); ?>">
                        <?php echo esc_html($config['title']); ?>
                    </label>
                </th>
                <td>
                    <?php $this->render_field($field_key, $config, $value); ?>
                    <?php if (!empty($config['description'])): ?>
                        <p class="description"><?php echo esc_html($config['description']); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    
    /**
     * Render individual field
     */
    private function render_field($field_key, $config, $value) {
        switch ($config['type']) {
            case 'checkbox':
                ?>
                <input type="checkbox" id="<?php echo esc_attr($field_key); ?>" 
                       name="<?php echo esc_attr($field_key); ?>" 
                       value="1" <?php checked($value); ?>>
                <?php
                break;
                
            case 'select':
                ?>
                <select id="<?php echo esc_attr($field_key); ?>" 
                        name="<?php echo esc_attr($field_key); ?>">
                    <?php foreach ($config['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" 
                                <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo esc_attr($field_key); ?>" 
                          name="<?php echo esc_attr($field_key); ?>" 
                          class="large-text" rows="5"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
                
            case 'number':
                ?>
                <input type="number" id="<?php echo esc_attr($field_key); ?>" 
                       name="<?php echo esc_attr($field_key); ?>" 
                       value="<?php echo esc_attr($value); ?>"
                       <?php if (isset($config['min'])): ?>min="<?php echo esc_attr($config['min']); ?>"<?php endif; ?>
                       <?php if (isset($config['max'])): ?>max="<?php echo esc_attr($config['max']); ?>"<?php endif; ?>>
                <?php
                break;
                
            default:
                ?>
                <input type="text" id="<?php echo esc_attr($field_key); ?>" 
                       name="<?php echo esc_attr($field_key); ?>" 
                       value="<?php echo esc_attr($value); ?>" 
                       class="regular-text">
                <?php
                break;
        }
    }
}