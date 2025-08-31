<?php
/**
 * Container Block Designer - Admin Class
 * Version: 2.4.0
 * 
 * Datei speichern als: includes/admin.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality class
 */
class CBD_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            $capability,
            'container-block-designer',
            array($this, 'blocks_list_page'),
            'dashicons-layout',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'container-block-designer',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            $capability,
            'container-block-designer',
            array($this, 'blocks_list_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Neuen Block hinzufügen', 'container-block-designer'),
            __('Block hinzufügen', 'container-block-designer'),
            $capability,
            'container-block-designer-new',
            array($this, 'block_edit_page')
        );
        
        add_submenu_page(
            null, // Hidden from menu
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            $capability,
            'container-block-designer-edit',
            array($this, 'block_edit_page')
        );
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle block save
        if (isset($_POST['cbd_save_block']) && wp_verify_nonce($_POST['_wpnonce'], 'cbd-save-block')) {
            $this->save_block();
        }
        
        // Handle block delete
        if (isset($_POST['cbd_delete_block']) && wp_verify_nonce($_POST['_wpnonce'], 'cbd-delete-block')) {
            $this->delete_block();
        }
        
        // Handle bulk actions
        if (isset($_POST['cbd_bulk_action']) && wp_verify_nonce($_POST['_wpnonce'], 'cbd-bulk-actions')) {
            $this->handle_bulk_actions();
        }
    }
    
    /**
     * Save block
     */
    private function save_block() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        try {
            // Sanitize input
            $block_data = $this->sanitize_block_data($_POST);
            
            // Handle features with position settings
            $features = array();
            
            // Icon feature
            if (!empty($_POST['feature_icon_enabled'])) {
                $features['icon'] = array(
                    'enabled' => true,
                    'value' => sanitize_text_field($_POST['icon_value']),
                    'position' => CBD_Position_Settings::save_position_settings('icon', $_POST)
                );
            }
            
            // Numbering feature
            if (!empty($_POST['feature_numbering_enabled'])) {
                $features['numbering'] = array(
                    'enabled' => true,
                    'format' => sanitize_text_field($_POST['numbering_format']),
                    'position' => CBD_Position_Settings::save_position_settings('numbering', $_POST)
                );
            }
            
            // Collapse feature
            if (!empty($_POST['feature_collapse_enabled'])) {
                $features['collapse'] = array(
                    'enabled' => true,
                    'defaultState' => sanitize_text_field($_POST['collapse_default_state']),
                    'title' => sanitize_text_field($_POST['collapse_title'])
                );
            }
            
            // Copy text feature
            if (!empty($_POST['feature_copy_enabled'])) {
                $features['copyText'] = array(
                    'enabled' => true,
                    'buttonText' => sanitize_text_field($_POST['copy_button_text'])
                );
            }
            
            // Screenshot feature
            if (!empty($_POST['feature_screenshot_enabled'])) {
                $features['screenshot'] = array(
                    'enabled' => true,
                    'buttonText' => sanitize_text_field($_POST['screenshot_button_text'])
                );
            }
            
            $block_data['features'] = json_encode($features);
            
            // Update or insert
            if (!empty($_POST['block_id'])) {
                // Update existing block
                $result = $wpdb->update(
                    $table_name,
                    $block_data,
                    array('id' => intval($_POST['block_id']))
                );
                
                if ($result !== false) {
                    $this->add_admin_notice(__('Block erfolgreich aktualisiert.', 'container-block-designer'), 'success');
                    wp_redirect(admin_url('admin.php?page=container-block-designer-edit&id=' . intval($_POST['block_id']) . '&updated=1'));
                    exit;
                } else {
                    throw new Exception(__('Fehler beim Aktualisieren des Blocks.', 'container-block-designer'));
                }
            } else {
                // Insert new block
                $result = $wpdb->insert($table_name, $block_data);
                
                if ($result) {
                    $block_id = $wpdb->insert_id;
                    $this->add_admin_notice(__('Block erfolgreich erstellt.', 'container-block-designer'), 'success');
                    wp_redirect(admin_url('admin.php?page=container-block-designer-edit&id=' . $block_id . '&created=1'));
                    exit;
                } else {
                    throw new Exception(__('Fehler beim Erstellen des Blocks.', 'container-block-designer'));
                }
            }
            
        } catch (Exception $e) {
            $this->add_admin_notice($e->getMessage(), 'error');
        }
    }
    
    /**
     * Sanitize block data
     */
    private function sanitize_block_data($data) {
        return array(
            'name' => sanitize_text_field($data['block_name']),
            'slug' => sanitize_title($data['block_slug']),
            'description' => sanitize_textarea_field($data['block_description']),
            'styles' => json_encode($this->sanitize_styles($data)),
            'config' => json_encode($this->sanitize_config($data)),
            'status' => in_array($data['block_status'], array('active', 'inactive', 'draft')) ? $data['block_status'] : 'active'
        );
    }
    
    /**
     * Sanitize styles data
     */
    private function sanitize_styles($data) {
        $styles = array();
        
        // Background
        $styles['background'] = array(
            'color' => sanitize_hex_color($data['bg_color'] ?? '#ffffff'),
            'type' => sanitize_text_field($data['bg_type'] ?? 'solid')
        );
        
        // Border
        $styles['border'] = array(
            'width' => sanitize_text_field($data['border_width'] ?? '1px'),
            'style' => sanitize_text_field($data['border_style'] ?? 'solid'),
            'color' => sanitize_hex_color($data['border_color'] ?? '#e0e0e0'),
            'radius' => sanitize_text_field($data['border_radius'] ?? '6px')
        );
        
        // Spacing
        $styles['spacing'] = array(
            'padding' => sanitize_text_field($data['spacing_padding'] ?? '20px'),
            'margin' => sanitize_text_field($data['spacing_margin'] ?? '20px 0')
        );
        
        // Text
        $styles['text'] = array(
            'color' => sanitize_hex_color($data['text_color'] ?? '#000000'),
            'size' => sanitize_text_field($data['text_size'] ?? '16px'),
            'align' => sanitize_text_field($data['text_align'] ?? 'left')
        );
        
        // Shadow
        $styles['shadow'] = array(
            'enabled' => !empty($data['shadow_enabled']),
            'x' => sanitize_text_field($data['shadow_x'] ?? '0'),
            'y' => sanitize_text_field($data['shadow_y'] ?? '2px'),
            'blur' => sanitize_text_field($data['shadow_blur'] ?? '8px'),
            'color' => sanitize_text_field($data['shadow_color'] ?? 'rgba(0,0,0,0.1)')
        );
        
        return $styles;
    }
    
    /**
     * Sanitize config data
     */
    private function sanitize_config($data) {
        return array(
            'allowInnerBlocks' => !empty($data['allow_inner_blocks']),
            'templateLock' => sanitize_text_field($data['template_lock'] ?? 'false'),
            'maxWidth' => sanitize_text_field($data['max_width'] ?? ''),
            'customClass' => sanitize_text_field($data['custom_class'] ?? '')
        );
    }
    
    /**
     * Delete block
     */
    private function delete_block() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block_id = intval($_POST['block_id']);
        
        if ($wpdb->delete($table_name, array('id' => $block_id))) {
            $this->add_admin_notice(__('Block erfolgreich gelöscht.', 'container-block-designer'), 'success');
        } else {
            $this->add_admin_notice(__('Fehler beim Löschen des Blocks.', 'container-block-designer'), 'error');
        }
        
        wp_redirect(admin_url('admin.php?page=container-block-designer'));
        exit;
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $block_ids = array_map('intval', $_POST['block_ids'] ?? array());
        
        if (empty($block_ids)) {
            $this->add_admin_notice(__('Keine Blöcke ausgewählt.', 'container-block-designer'), 'error');
            return;
        }
        
        $success_count = 0;
        
        switch ($action) {
            case 'activate':
                foreach ($block_ids as $id) {
                    if ($wpdb->update($table_name, array('status' => 'active'), array('id' => $id))) {
                        $success_count++;
                    }
                }
                $this->add_admin_notice(sprintf(__('%d Blöcke aktiviert.', 'container-block-designer'), $success_count), 'success');
                break;
                
            case 'deactivate':
                foreach ($block_ids as $id) {
                    if ($wpdb->update($table_name, array('status' => 'inactive'), array('id' => $id))) {
                        $success_count++;
                    }
                }
                $this->add_admin_notice(sprintf(__('%d Blöcke deaktiviert.', 'container-block-designer'), $success_count), 'success');
                break;
                
            case 'delete':
                foreach ($block_ids as $id) {
                    if ($wpdb->delete($table_name, array('id' => $id))) {
                        $success_count++;
                    }
                }
                $this->add_admin_notice(sprintf(__('%d Blöcke gelöscht.', 'container-block-designer'), $success_count), 'success');
                break;
        }
    }
    
    /**
     * Blocks list page
     */
    public function blocks_list_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Get blocks
        $blocks = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC", ARRAY_A);
        $total_blocks = count($blocks);
        
        // Include the list template
        include CBD_PLUGIN_DIR . 'admin/blocks-list.php';
    }
    
    /**
     * Block edit page
     */
    public function block_edit_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block = null;
        $is_new = true;
        
        // Get block for editing
        if (isset($_GET['id'])) {
            $block_id = intval($_GET['id']);
            $block = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $block_id), ARRAY_A);
            
            if ($block) {
                $is_new = false;
                // Parse JSON data
                $block['styles'] = json_decode($block['styles'], true);
                $block['features'] = json_decode($block['features'], true);
                $block['config'] = json_decode($block['config'], true);
            }
        }
        
        // Include the edit template
        include CBD_PLUGIN_DIR . 'admin/block-edit.php';
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        $notices = get_transient('cbd_admin_notices') ?: array();
        $notices[] = array(
            'message' => $message,
            'type' => $type
        );
        set_transient('cbd_admin_notices', $notices, 30);
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        $notices = get_transient('cbd_admin_notices');
        
        if (empty($notices)) {
            return;
        }
        
        foreach ($notices as $notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
        }
        
        delete_transient('cbd_admin_notices');
    }
}