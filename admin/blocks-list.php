<?php
/**
 * Container Block Designer - Blocks List
 * Version: 2.3.0 - FIXED
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get blocks from database
global $wpdb;
$table_name = CBD_TABLE_BLOCKS;

// Ensure table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo '<div class="notice notice-error"><p>Die Datenbanktabelle existiert nicht. Bitte deaktivieren und reaktivieren Sie das Plugin.</p></div>';
    return;
}

$blocks = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Container Blocks</h1>
    <a href="?page=cbd-new-block" class="page-title-action">Neuen Block erstellen</a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Beschreibung</th>
                <th style="width: 200px;">Styles</th>
                <th style="width: 150px;">Features</th>
                <th>Status</th>
                <th>Erstellt</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($blocks): ?>
                <?php foreach ($blocks as $block): ?>
                    <?php 
                    // Parse config and features
                    $config = json_decode($block->config, true) ?: array();
                    $styles = isset($config['styles']) ? $config['styles'] : array();
                    $features = json_decode($block->features, true) ?: array();
                    
                    // Style preview
                    $style_preview = array();
                    if (!empty($styles['background']['color'])) {
                        $style_preview[] = 'BG: ' . $styles['background']['color'];
                    }
                    if (!empty($styles['padding'])) {
                        $padding_values = array_filter($styles['padding']);
                        if (!empty($padding_values)) {
                            $style_preview[] = 'Padding: ' . implode('/', $padding_values) . 'px';
                        }
                    }
                    if (!empty($styles['border']['width']) && $styles['border']['width'] > 0) {
                        $style_preview[] = 'Border: ' . $styles['border']['width'] . 'px';
                    }
                    
                    // Features preview
                    $active_features = array();
                    foreach ($features as $feature_key => $feature_data) {
                        if (!empty($feature_data['enabled'])) {
                            $feature_names = array(
                                'icon' => 'Icon',
                                'collapse' => 'Collapse',
                                'numbering' => 'Nummerierung',
                                'copyText' => 'Text kopieren',
                                'screenshot' => 'Screenshot'
                            );
                            $active_features[] = $feature_names[$feature_key] ?? $feature_key;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($block->id); ?></td>
                        <td><strong><?php echo esc_html($block->name); ?></strong></td>
                        <td><code><?php echo esc_html($block->slug); ?></code></td>
                        <td><?php echo esc_html($block->description ?: '-'); ?></td>
                        <td>
                            <?php if (!empty($style_preview)): ?>
                                <small><?php echo esc_html(implode(' | ', $style_preview)); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">Keine Styles</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($active_features)): ?>
                                <small><?php echo esc_html(implode(', ', $active_features)); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">Keine Features</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($block->status); ?>">
                                <?php echo $block->status === 'active' ? 'Aktiv' : 'Inaktiv'; ?>
                            </span>
                        </td>
                        <td><?php echo date_i18n('d.m.Y', strtotime($block->created)); ?></td>
                        <td>
                            <a href="?page=cbd-edit-block&id=<?php echo $block->id; ?>" class="button button-small">Bearbeiten</a>
                            <button class="button button-small cbd-delete-block" data-id="<?php echo $block->id; ?>">Löschen</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 20px;">
                        Keine Blocks gefunden. <a href="?page=cbd-new-block">Erstellen Sie Ihren ersten Block</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge.status-active {
    background: #d4f4dd;
    color: #1e8e3e;
}
.status-badge.status-inactive {
    background: #fce8e6;
    color: #d33b27;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.cbd-delete-block').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Sind Sie sicher, dass Sie diesen Block löschen möchten?')) {
            return;
        }
        
        var blockId = $(this).data('id');
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cbd_delete_block',
                block_id: blockId,
                nonce: '<?php echo wp_create_nonce('cbd_delete_block'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Fehler: ' + (response.data || 'Unbekannter Fehler'));
                }
            },
            error: function() {
                alert('Ein Fehler ist aufgetreten.');
            }
        });
    });
});
</script>