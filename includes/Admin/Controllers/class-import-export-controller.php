<?php
/**
 * Container Block Designer - Import/Export Controller
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
 * Import/Export Controller
 * Handles block import and export functionality
 */
class ImportExportController {
    
    /**
     * Render the import/export page
     */
    public function render() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben keine Berechtigung, diese Seite zu besuchen.', 'container-block-designer'));
        }
        
        // Handle form submissions
        $this->handle_form_submissions();
        
        // Render view
        $this->render_view();
    }
    
    /**
     * Handle form submissions
     */
    private function handle_form_submissions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (isset($_POST['export_blocks'])) {
            $this->handle_export();
        }
        
        if (isset($_POST['import_blocks'])) {
            $this->handle_import();
        }
    }
    
    /**
     * Handle block export
     */
    private function handle_export() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cbd_export_blocks')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
        }
        
        $selected_blocks = array_map('intval', $_POST['selected_blocks'] ?? array());
        
        if (empty($selected_blocks)) {
            $this->add_notice(__('Bitte wählen Sie mindestens einen Block zum Exportieren aus.', 'container-block-designer'), 'error');
            return;
        }
        
        $export_data = $this->prepare_export_data($selected_blocks);
        
        // Send file download
        $this->send_export_file($export_data);
    }
    
    /**
     * Handle block import
     */
    private function handle_import() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cbd_import_blocks')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
        }
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->add_notice(__('Bitte wählen Sie eine gültige Datei zum Importieren aus.', 'container-block-designer'), 'error');
            return;
        }
        
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_notice(__('Die Import-Datei ist nicht gültig.', 'container-block-designer'), 'error');
            return;
        }
        
        $result = $this->process_import($import_data);
        
        if ($result['success']) {
            $this->add_notice(
                sprintf(__('%d Blöcke wurden erfolgreich importiert.', 'container-block-designer'), $result['imported']),
                'success'
            );
        } else {
            $this->add_notice($result['message'], 'error');
        }
    }
    
    /**
     * Prepare export data
     */
    private function prepare_export_data($block_ids) {
        $blocks = array();
        
        foreach ($block_ids as $block_id) {
            $block = \CBD_Database::get_block($block_id);
            if ($block) {
                // Remove ID and timestamps for clean import
                unset($block['id'], $block['created_at'], $block['updated_at']);
                $blocks[] = $block;
            }
        }
        
        return array(
            'version' => CBD_VERSION,
            'export_date' => current_time('mysql'),
            'blocks' => $blocks
        );
    }
    
    /**
     * Send export file
     */
    private function send_export_file($data) {
        $filename = 'cbd-blocks-export-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(wp_json_encode($data)));
        
        echo wp_json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Process import
     */
    private function process_import($data) {
        $result = array(
            'success' => false,
            'imported' => 0,
            'message' => ''
        );
        
        if (!isset($data['blocks']) || !is_array($data['blocks'])) {
            $result['message'] = __('Ungültiges Import-Format.', 'container-block-designer');
            return $result;
        }
        
        $imported = 0;
        
        foreach ($data['blocks'] as $block_data) {
            // Validate required fields
            if (empty($block_data['name']) || empty($block_data['title'])) {
                continue;
            }
            
            // Check if block name already exists
            if (\CBD_Database::block_name_exists($block_data['name'])) {
                // Generate unique name
                $counter = 1;
                $original_name = $block_data['name'];
                
                do {
                    $block_data['name'] = $original_name . '_imported_' . $counter;
                    $counter++;
                } while (\CBD_Database::block_name_exists($block_data['name']));
            }
            
            // Import block
            if (\CBD_Database::save_block($block_data)) {
                $imported++;
            }
        }
        
        $result['success'] = $imported > 0;
        $result['imported'] = $imported;
        
        if ($imported === 0) {
            $result['message'] = __('Keine Blöcke konnten importiert werden.', 'container-block-designer');
        }
        
        return $result;
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
        $blocks = \CBD_Database::get_blocks();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Import/Export', 'container-block-designer'); ?></h1>
            
            <?php settings_errors('cbd_notices'); ?>
            
            <div class="cbd-import-export-container">
                <div class="cbd-export-section">
                    <h2><?php _e('Export', 'container-block-designer'); ?></h2>
                    <p><?php _e('Wählen Sie die Blöcke aus, die Sie exportieren möchten.', 'container-block-designer'); ?></p>
                    
                    <?php if (empty($blocks)): ?>
                        <p><em><?php _e('Keine Blöcke zum Exportieren vorhanden.', 'container-block-designer'); ?></em></p>
                    <?php else: ?>
                        <form method="post">
                            <?php wp_nonce_field('cbd_export_blocks'); ?>
                            
                            <div class="cbd-blocks-selection">
                                <label>
                                    <input type="checkbox" id="select-all-export"> 
                                    <?php _e('Alle auswählen', 'container-block-designer'); ?>
                                </label>
                                
                                <?php foreach ($blocks as $block): ?>
                                    <label>
                                        <input type="checkbox" name="selected_blocks[]" value="<?php echo esc_attr($block['id']); ?>">
                                        <?php echo esc_html($block['title']); ?>
                                        <small>(<?php echo esc_html($block['name']); ?>)</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="submit">
                                <input type="submit" name="export_blocks" class="button button-primary" 
                                       value="<?php _e('Ausgewählte Blöcke exportieren', 'container-block-designer'); ?>">
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="cbd-import-section">
                    <h2><?php _e('Import', 'container-block-designer'); ?></h2>
                    <p><?php _e('Wählen Sie eine JSON-Datei aus, um Blöcke zu importieren.', 'container-block-designer'); ?></p>
                    
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('cbd_import_blocks'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php _e('Import-Datei', 'container-block-designer'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="import_file" name="import_file" accept=".json" required>
                                    <p class="description">
                                        <?php _e('Nur JSON-Dateien werden unterstützt.', 'container-block-designer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="import_blocks" class="button button-primary" 
                                   value="<?php _e('Blöcke importieren', 'container-block-designer'); ?>">
                        </p>
                    </form>
                    
                    <div class="cbd-import-info">
                        <h3><?php _e('Hinweise zum Import', 'container-block-designer'); ?></h3>
                        <ul>
                            <li><?php _e('Blöcke mit bereits vorhandenen Namen werden automatisch umbenannt.', 'container-block-designer'); ?></li>
                            <li><?php _e('Importierte Blöcke werden standardmäßig als "inaktiv" markiert.', 'container-block-designer'); ?></li>
                            <li><?php _e('Nur gültige JSON-Dateien im Container Block Designer Format werden akzeptiert.', 'container-block-designer'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all functionality
            $('#select-all-export').on('change', function() {
                $('input[name="selected_blocks[]"]').prop('checked', this.checked);
            });
            
            // Update select all when individual checkboxes change
            $('input[name="selected_blocks[]"]').on('change', function() {
                var allChecked = $('input[name="selected_blocks[]"]:checked').length === $('input[name="selected_blocks[]"]').length;
                $('#select-all-export').prop('checked', allChecked);
            });
        });
        </script>
        <?php
    }
}