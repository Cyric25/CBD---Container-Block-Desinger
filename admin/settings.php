<?php
/**
 * Container Block Designer - Einstellungen
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Einstellungen speichern
if (isset($_POST['cbd_save_settings']) && wp_verify_nonce($_POST['cbd_settings_nonce'], 'cbd_settings')) {
    update_option('cbd_remove_data_on_uninstall', isset($_POST['remove_data_on_uninstall']) ? 1 : 0);
    update_option('cbd_enable_debug_mode', isset($_POST['enable_debug_mode']) ? 1 : 0);
    update_option('cbd_default_block_status', sanitize_text_field($_POST['default_block_status']));
    update_option('cbd_enable_block_caching', isset($_POST['enable_block_caching']) ? 1 : 0);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen gespeichert.', 'container-block-designer') . '</p></div>';
}

// Aktuelle Einstellungen laden
$remove_data = get_option('cbd_remove_data_on_uninstall', 0);
$debug_mode = get_option('cbd_enable_debug_mode', 0);
$default_status = get_option('cbd_default_block_status', 'draft');
$enable_caching = get_option('cbd_enable_block_caching', 1);
?>

<div class="wrap">
    <h1><?php _e('Container Block Designer - Einstellungen', 'container-block-designer'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cbd_settings', 'cbd_settings_nonce'); ?>
        
        <div class="cbd-settings-grid">
            <div class="cbd-settings-main">
                
                <!-- Allgemeine Einstellungen -->
                <div class="cbd-card">
                    <h2><?php _e('Allgemeine Einstellungen', 'container-block-designer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Standard-Blockstatus', 'container-block-designer'); ?></th>
                            <td>
                                <select name="default_block_status">
                                    <option value="draft" <?php selected($default_status, 'draft'); ?>><?php _e('Entwurf', 'container-block-designer'); ?></option>
                                    <option value="active" <?php selected($default_status, 'active'); ?>><?php _e('Aktiv', 'container-block-designer'); ?></option>
                                    <option value="inactive" <?php selected($default_status, 'inactive'); ?>><?php _e('Inaktiv', 'container-block-designer'); ?></option>
                                </select>
                                <p class="description"><?php _e('Standard-Status für neue Blocks', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Block-Caching', 'container-block-designer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_block_caching" value="1" <?php checked($enable_caching, 1); ?>>
                                    <?php _e('Block-Caching aktivieren', 'container-block-designer'); ?>
                                </label>
                                <p