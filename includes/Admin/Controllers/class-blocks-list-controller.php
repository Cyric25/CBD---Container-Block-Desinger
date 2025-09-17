<?php
/**
 * Container Block Designer - Blocks List Controller
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
 * Blocks List Controller
 * Handles the display and management of blocks list
 */
class BlocksListController {
    
    /**
     * Render the blocks list page
     */
    public function render() {
        // Check permissions - Block-Redakteure können Blocks ansehen
        if (!cbd_user_can_use_blocks()) {
            wp_die(__('Sie haben keine Berechtigung, diese Seite zu besuchen.', 'container-block-designer'));
        }
        
        // Handle bulk actions - nur für Admins
        if (cbd_user_can_admin_blocks()) {
            $this->handle_bulk_actions();
        }
        
        // Get blocks
        $blocks = $this->get_blocks();
        
        // Render view
        $this->render_view($blocks);
    }
    
    /**
     * Get blocks with pagination and sorting
     */
    private function get_blocks() {
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'name');
        $order = sanitize_text_field($_GET['order'] ?? 'ASC');
        $status = sanitize_text_field($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        
        $args = array(
            'orderby' => $orderby,
            'order' => $order
        );
        
        if ($status) {
            $args['status'] = $status;
        }
        
        if ($search) {
            return \CBD_Database::search_blocks($search);
        }
        
        return \CBD_Database::get_blocks($args);
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['bulk_action']) || !isset($_POST['block_ids'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-blocks')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $block_ids = array_map('intval', $_POST['block_ids']);
        
        switch ($action) {
            case 'activate':
                $this->bulk_update_status($block_ids, 'active');
                $this->add_notice(__('Blocks wurden aktiviert.', 'container-block-designer'), 'success');
                break;
                
            case 'deactivate':
                $this->bulk_update_status($block_ids, 'inactive');
                $this->add_notice(__('Blocks wurden deaktiviert.', 'container-block-designer'), 'success');
                break;
                
            case 'delete':
                $this->bulk_delete($block_ids);
                $this->add_notice(__('Blocks wurden gelöscht.', 'container-block-designer'), 'success');
                break;
        }
        
        // Redirect to prevent form resubmission
        wp_redirect(remove_query_arg(array('action', 'block_ids', '_wpnonce')));
        exit;
    }
    
    /**
     * Bulk update block status
     */
    private function bulk_update_status($block_ids, $status) {
        foreach ($block_ids as $block_id) {
            \CBD_Database::update_block_status($block_id, $status);
        }
    }
    
    /**
     * Bulk delete blocks
     */
    private function bulk_delete($block_ids) {
        foreach ($block_ids as $block_id) {
            \CBD_Database::delete_block($block_id);
        }
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
    private function render_view($blocks) {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Container Blocks', 'container-block-designer'); ?>
                <?php
                $user = wp_get_current_user();
                if ($user && in_array('block_redakteur', $user->roles)) {
                    echo ' <small style="color: #666;">' . __('(Nur-Lese-Modus)', 'container-block-designer') . '</small>';
                }
                ?>
            </h1>

            <?php if (cbd_user_can_admin_blocks()): ?>
                <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="page-title-action">
                    <?php _e('Neuer Block', 'container-block-designer'); ?>
                </a>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php settings_errors('cbd_notices'); ?>
            
            <?php $this->render_search_form(); ?>
            
            <?php if (empty($blocks)): ?>
                <?php $this->render_empty_state(); ?>
            <?php else: ?>
                <?php $this->render_blocks_table($blocks); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render search form
     */
    private function render_search_form() {
        $search = sanitize_text_field($_GET['s'] ?? '');
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="block-search-input">
                <?php _e('Blocks durchsuchen', 'container-block-designer'); ?>
            </label>
            <input type="search" id="block-search-input" name="s" value="<?php echo esc_attr($search); ?>">
            <input type="submit" id="search-submit" class="button" value="<?php _e('Blocks durchsuchen', 'container-block-designer'); ?>">
        </p>
        <?php
    }
    
    /**
     * Render empty state
     */
    private function render_empty_state() {
        ?>
        <div class="cbd-empty-state">
            <h2><?php _e('Keine Blocks gefunden', 'container-block-designer'); ?></h2>
            <p><?php _e('Erstellen Sie Ihren ersten Container-Block, um loszulegen.', 'container-block-designer'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Render blocks table
     */
    private function render_blocks_table($blocks) {
        ?>
        <form method="post">
            <?php wp_nonce_field('bulk-blocks'); ?>

            <?php if (cbd_user_can_admin_blocks()): ?>
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="bulk_action">
                            <option value=""><?php _e('Bulk-Aktion wählen', 'container-block-designer'); ?></option>
                            <option value="activate"><?php _e('Aktivieren', 'container-block-designer'); ?></option>
                            <option value="deactivate"><?php _e('Deaktivieren', 'container-block-designer'); ?></option>
                            <option value="delete"><?php _e('Löschen', 'container-block-designer'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Anwenden', 'container-block-designer'); ?>">
                    </div>

                    <?php $this->render_status_filter(); ?>
                </div>
            <?php else: ?>
                <div class="tablenav top">
                    <?php $this->render_status_filter(); ?>
                </div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <?php $this->render_table_header(); ?>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <?php $this->render_table_row($block); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php
    }
    
    /**
     * Render status filter
     */
    private function render_status_filter() {
        $current_status = sanitize_text_field($_GET['status'] ?? '');
        $statuses = array(
            '' => __('Alle Status', 'container-block-designer'),
            'active' => __('Aktiv', 'container-block-designer'),
            'inactive' => __('Inaktiv', 'container-block-designer')
        );
        
        ?>
        <div class="alignleft actions">
            <select name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" class="button" value="<?php _e('Filtern', 'container-block-designer'); ?>">
        </div>
        <?php
    }
    
    /**
     * Render table header
     */
    private function render_table_header() {
        ?>
        <thead>
            <tr>
                <?php if (cbd_user_can_admin_blocks()): ?>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                <?php endif; ?>
                <th scope="col" class="manage-column column-name sortable">
                    <a href="<?php echo $this->get_sort_link('name'); ?>">
                        <?php _e('Name', 'container-block-designer'); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-title">
                    <?php _e('Titel', 'container-block-designer'); ?>
                </th>
                <th scope="col" class="manage-column column-description">
                    <?php _e('Beschreibung', 'container-block-designer'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php _e('Status', 'container-block-designer'); ?>
                </th>
                <th scope="col" class="manage-column column-date sortable">
                    <a href="<?php echo $this->get_sort_link('created_at'); ?>">
                        <?php _e('Erstellt', 'container-block-designer'); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Aktionen', 'container-block-designer'); ?>
                </th>
            </tr>
        </thead>
        <?php
    }
    
    /**
     * Render table row
     */
    private function render_table_row($block) {
        $edit_url = admin_url('admin.php?page=cbd-edit-block&id=' . $block['id']);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=cbd-blocks&action=delete&id=' . $block['id']),
            'delete_block_' . $block['id']
        );
        
        ?>
        <tr>
            <?php if (cbd_user_can_admin_blocks()): ?>
                <th scope="row" class="check-column">
                    <input type="checkbox" name="block_ids[]" value="<?php echo esc_attr($block['id']); ?>">
                </th>
            <?php endif; ?>
            <td class="column-name">
                <strong>
                    <?php if (cbd_user_can_admin_blocks()): ?>
                        <a href="<?php echo esc_url($edit_url); ?>">
                            <?php echo esc_html($block['name']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($block['name']); ?>
                    <?php endif; ?>
                </strong>
                <?php if (cbd_user_can_admin_blocks()): ?>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php _e('Bearbeiten', 'container-block-designer'); ?>
                            </a>
                        </span>
                        |
                        <span class="delete">
                            <a href="<?php echo esc_url($delete_url); ?>"
                               onclick="return confirm('<?php _e('Sind Sie sicher?', 'container-block-designer'); ?>')">
                                <?php _e('Löschen', 'container-block-designer'); ?>
                            </a>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="row-actions">
                        <span style="color: #666; font-style: italic;">
                            <?php _e('Vorschau-Modus', 'container-block-designer'); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </td>
            <td class="column-title">
                <?php echo esc_html($block['title']); ?>
            </td>
            <td class="column-description">
                <?php
                $description = $block['description'] ?? '';
                if (is_array($description)) {
                    $description = implode(' ', $description);
                }
                echo esc_html(wp_trim_words($description, 10));
                ?>
            </td>
            <td class="column-status">
                <span class="status-badge status-<?php echo esc_attr($block['status']); ?>">
                    <?php echo $block['status'] === 'active' ? 
                        __('Aktiv', 'container-block-designer') : 
                        __('Inaktiv', 'container-block-designer'); ?>
                </span>
            </td>
            <td class="column-date">
                <?php echo esc_html(mysql2date('d.m.Y H:i', $block['created_at'])); ?>
            </td>
            <td class="column-actions">
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                    <?php _e('Bearbeiten', 'container-block-designer'); ?>
                </a>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Get sort link for column
     */
    private function get_sort_link($column) {
        $current_orderby = sanitize_text_field($_GET['orderby'] ?? '');
        $current_order = sanitize_text_field($_GET['order'] ?? 'ASC');
        
        $new_order = ($current_orderby === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
        
        return add_query_arg(array(
            'orderby' => $column,
            'order' => $new_order
        ));
    }
}