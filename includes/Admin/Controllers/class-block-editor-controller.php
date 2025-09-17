<?php
/**
 * Container Block Designer - Block Editor Controller
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
 * Block Editor Controller
 * Handles block creation and editing
 */
class BlockEditorController {
    
    /**
     * Current block being edited
     */
    private $block = null;
    
    /**
     * Whether this is a new block
     */
    private $is_new = true;
    
    /**
     * Render the block editor page
     */
    public function render() {
        // Check permissions - Mitarbeiter können Blocks bearbeiten
        if (!current_user_can('edit_posts')) {
            wp_die(__('Sie haben keine Berechtigung, diese Seite zu besuchen.', 'container-block-designer'));
        }
        
        // Load block if editing
        $this->load_block();
        
        // Handle form submission
        $this->handle_form_submission();
        
        // Render view
        $this->render_view();
    }
    
    /**
     * Load block for editing
     */
    private function load_block() {
        $block_id = intval($_GET['id'] ?? 0);
        
        if ($block_id > 0) {
            $this->block = \CBD_Database::get_block($block_id);
            if ($this->block) {
                $this->is_new = false;
            } else {
                wp_die(__('Block nicht gefunden.', 'container-block-designer'));
            }
        }
    }
    
    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cbd_save_block')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
        }
        
        // Validate and sanitize data
        $block_data = $this->validate_block_data($_POST);
        
        if (is_wp_error($block_data)) {
            $this->add_notice($block_data->get_error_message(), 'error');
            return;
        }
        
        // Save block
        $block_id = $this->is_new ? 0 : $this->block['id'];
        $result = \CBD_Database::save_block($block_data, $block_id);
        
        if ($result) {
            if ($this->is_new) {
                $this->add_notice(__('Block wurde erfolgreich erstellt.', 'container-block-designer'), 'success');
                // Redirect to edit page
                wp_redirect(admin_url('admin.php?page=cbd-edit-block&id=' . $result));
                exit;
            } else {
                $this->add_notice(__('Block wurde erfolgreich aktualisiert.', 'container-block-designer'), 'success');
                // Reload block
                $this->block = \CBD_Database::get_block($this->block['id']);
            }
        } else {
            $this->add_notice(__('Fehler beim Speichern des Blocks.', 'container-block-designer'), 'error');
        }
    }
    
    /**
     * Validate block data
     */
    private function validate_block_data($data) {
        $errors = new \WP_Error();
        
        // Required fields
        $name = sanitize_text_field($data['name'] ?? '');
        $title = sanitize_text_field($data['title'] ?? '');
        
        if (empty($name)) {
            $errors->add('name_required', __('Name ist erforderlich.', 'container-block-designer'));
        }
        
        if (empty($title)) {
            $errors->add('title_required', __('Titel ist erforderlich.', 'container-block-designer'));
        }
        
        // Check for duplicate name
        if (!empty($name)) {
            $exclude_id = $this->is_new ? 0 : $this->block['id'];
            if (\CBD_Database::block_name_exists($name, $exclude_id)) {
                $errors->add('name_exists', __('Ein Block mit diesem Namen existiert bereits.', 'container-block-designer'));
            }
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        // Build sanitized data array
        return array(
            'name' => $name,
            'title' => $title,
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'config' => $this->validate_json($data['config'] ?? '{}'),
            'styles' => $this->validate_json($data['styles'] ?? '{}'),
            'features' => $this->validate_json($data['features'] ?? '{}'),
            'status' => sanitize_text_field($data['status'] ?? 'active')
        );
    }
    
    /**
     * Validate and sanitize JSON
     */
    private function validate_json($json_string) {
        if (empty($json_string)) {
            return array();
        }
        
        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array();
        }
        
        return $decoded;
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
            <h1>
                <?php echo $this->is_new ? 
                    __('Neuen Block erstellen', 'container-block-designer') : 
                    __('Block bearbeiten', 'container-block-designer'); ?>
            </h1>
            
            <?php settings_errors('cbd_notices'); ?>
            
            <form method="post" class="cbd-block-editor-form">
                <?php wp_nonce_field('cbd_save_block'); ?>
                
                <div class="cbd-editor-layout">
                    <div class="cbd-editor-main">
                        <?php $this->render_basic_fields(); ?>
                        <?php $this->render_style_editor(); ?>
                        <?php $this->render_features_editor(); ?>
                    </div>
                    
                    <div class="cbd-editor-sidebar">
                        <?php $this->render_publish_box(); ?>
                        <?php $this->render_preview_box(); ?>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize block editor functionality
            if (typeof CBDBlockEditor !== 'undefined') {
                CBDBlockEditor.init();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render basic fields
     */
    private function render_basic_fields() {
        $name = $this->block['name'] ?? '';
        $title = $this->block['title'] ?? '';
        $description = $this->block['description'] ?? '';
        
        ?>
        <div class="cbd-editor-section">
            <h2><?php _e('Grundeinstellungen', 'container-block-designer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="block_name"><?php _e('Name', 'container-block-designer'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="block_name" name="name" value="<?php echo esc_attr($name); ?>" 
                               class="regular-text" required <?php echo $this->is_new ? '' : 'readonly'; ?>>
                        <p class="description">
                            <?php _e('Eindeutiger Name für den Block (nur Buchstaben, Zahlen und Bindestriche).', 'container-block-designer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="block_title"><?php _e('Titel', 'container-block-designer'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="block_title" name="title" value="<?php echo esc_attr($title); ?>" 
                               class="regular-text" required>
                        <p class="description">
                            <?php _e('Anzeigename des Blocks im Editor.', 'container-block-designer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="block_description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                    </th>
                    <td>
                        <textarea id="block_description" name="description" class="large-text" rows="3"><?php echo esc_textarea($description); ?></textarea>
                        <p class="description">
                            <?php _e('Kurze Beschreibung des Blocks.', 'container-block-designer'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render style editor
     */
    private function render_style_editor() {
        $styles = $this->block['styles'] ?? array();
        ?>
        <div class="cbd-editor-section">
            <h2><?php _e('Styling', 'container-block-designer'); ?></h2>
            <div id="cbd-style-editor" data-styles="<?php echo esc_attr(wp_json_encode($styles)); ?>">
                <!-- Style editor will be rendered via JavaScript -->
            </div>
            <textarea name="styles" id="styles_json" class="hidden"><?php echo esc_textarea(wp_json_encode($styles)); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Render features editor
     */
    private function render_features_editor() {
        $features = $this->block['features'] ?? array();
        ?>
        <div class="cbd-editor-section">
            <h2><?php _e('Features', 'container-block-designer'); ?></h2>
            <div id="cbd-features-editor" data-features="<?php echo esc_attr(wp_json_encode($features)); ?>">
                <!-- Features editor will be rendered via JavaScript -->
            </div>
            <textarea name="features" id="features_json" class="hidden"><?php echo esc_textarea(wp_json_encode($features)); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Render publish box
     */
    private function render_publish_box() {
        $status = $this->block['status'] ?? 'active';
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('Veröffentlichen', 'container-block-designer'); ?></h3>
            <div class="inside">
                <div class="submitbox">
                    <div class="misc-pub-section">
                        <label for="block_status"><?php _e('Status:', 'container-block-designer'); ?></label>
                        <select name="status" id="block_status">
                            <option value="active" <?php selected($status, 'active'); ?>>
                                <?php _e('Aktiv', 'container-block-designer'); ?>
                            </option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>>
                                <?php _e('Inaktiv', 'container-block-designer'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <?php if (!$this->is_new): ?>
                    <div class="misc-pub-section">
                        <span><?php _e('Erstellt:', 'container-block-designer'); ?></span>
                        <strong><?php echo esc_html(mysql2date('d.m.Y H:i', $this->block['created_at'])); ?></strong>
                    </div>
                    
                    <div class="misc-pub-section">
                        <span><?php _e('Zuletzt geändert:', 'container-block-designer'); ?></span>
                        <strong><?php echo esc_html(mysql2date('d.m.Y H:i', $this->block['updated_at'])); ?></strong>
                    </div>
                    <?php endif; ?>
                    
                    <div class="major-publishing-actions">
                        <div class="publishing-action">
                            <input type="submit" class="button button-primary button-large" 
                                   value="<?php echo $this->is_new ? 
                                       __('Block erstellen', 'container-block-designer') : 
                                       __('Block aktualisieren', 'container-block-designer'); ?>">
                        </div>
                        <div class="clear"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render preview box
     */
    private function render_preview_box() {
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('Vorschau', 'container-block-designer'); ?></h3>
            <div class="inside">
                <div id="cbd-block-preview">
                    <div id="cbd-block-preview-content" style="padding: 20px; background-color: #ffffff; border: 1px solid #dddddd; border-radius: 4px; color: #333333;">
                        <p><?php _e('Hier wird die Vorschau Ihres Blocks angezeigt', 'container-block-designer'); ?></p>
                    </div>
                </div>
                <button type="button" class="button" id="cbd-update-preview">
                    <?php _e('Vorschau aktualisieren', 'container-block-designer'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}