<?php
/**
 * Container Block Designer - Blocks-Liste
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Blocks aus der Datenbank abrufen
$blocks = CBD_Database::get_blocks();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Container Blocks', 'container-block-designer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="page-title-action">
        <?php _e('Neuer Block', 'container-block-designer'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php if (empty($blocks)): ?>
        <div class="cbd-empty-state">
            <h2><?php _e('Keine Blocks gefunden', 'container-block-designer'); ?></h2>
            <p><?php _e('Erstellen Sie Ihren ersten Container-Block, um loszulegen.', 'container-block-designer'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
            </a>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%"><?php _e('ID', 'container-block-designer'); ?></th>
                    <th width="18%"><?php _e('Name', 'container-block-designer'); ?></th>
                    <th width="17%"><?php _e('Titel', 'container-block-designer'); ?></th>
                    <th width="22%"><?php _e('Beschreibung', 'container-block-designer'); ?></th>
                    <th width="8%"><?php _e('Status', 'container-block-designer'); ?></th>
                    <th width="8%"><?php _e('Standard', 'container-block-designer'); ?></th>
                    <th width="10%"><?php _e('Erstellt', 'container-block-designer'); ?></th>
                    <th width="12%"><?php _e('Aktionen', 'container-block-designer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocks as $block): ?>
                    <tr>
                        <td><?php echo esc_html($block['id']); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=cbd-edit-block&id=' . $block['id']); ?>">
                                    <?php echo esc_html($block['name']); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html($block['title']); ?></td>
                        <td><?php
                            $description = $block['description'] ?? '';
                            if (is_array($description)) {
                                $description = implode(' ', $description);
                            }
                            echo esc_html($description);
                        ?></td>
                        <td>
                            <?php if ($block['status'] === 'active'): ?>
                                <span class="cbd-status-badge cbd-status-active">
                                    <?php _e('Aktiv', 'container-block-designer'); ?>
                                </span>
                            <?php elseif ($block['status'] === 'inactive'): ?>
                                <span class="cbd-status-badge cbd-status-inactive">
                                    <?php _e('Inaktiv', 'container-block-designer'); ?>
                                </span>
                            <?php else: ?>
                                <span class="cbd-status-badge cbd-status-draft">
                                    <?php _e('Entwurf', 'container-block-designer'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($block['is_default']) && $block['is_default'] == 1): ?>
                                <span class="dashicons dashicons-star-filled" style="color: #f39c12; font-size: 20px;" title="<?php esc_attr_e('Standard-Block', 'container-block-designer'); ?>"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-star-empty" style="color: #ccc; font-size: 20px;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($block['created'])); ?></td>
                        <td>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=cbd-edit-block&id=' . $block['id']); ?>">
                                        <?php _e('Bearbeiten', 'container-block-designer'); ?>
                                    </a>
                                </span> |
                                <span class="duplicate">
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=container-block-designer&action=duplicate&block_id=' . $block['id']),
                                        'cbd_duplicate_block_' . $block['id']
                                    ); ?>">
                                        <?php _e('Duplizieren', 'container-block-designer'); ?>
                                    </a>
                                </span> |
                                <span class="set-default">
                                    <?php if (!empty($block['is_default']) && $block['is_default'] == 1): ?>
                                        <span style="color: #999;"><?php _e('Standard', 'container-block-designer'); ?></span>
                                    <?php else: ?>
                                        <a href="#" class="cbd-set-default" data-block-id="<?php echo esc_attr($block['id']); ?>">
                                            <?php _e('Als Standard', 'container-block-designer'); ?>
                                        </a>
                                    <?php endif; ?>
                                </span> |
                                <span class="delete">
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=container-block-designer&action=delete&block_id=' . $block['id']),
                                        'cbd_delete_block_' . $block['id']
                                    ); ?>" 
                                    onclick="return confirm('<?php esc_attr_e('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'); ?>');"
                                    class="cbd-delete-link">
                                        <?php _e('Löschen', 'container-block-designer'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.cbd-empty-state {
    margin-top: 40px;
    text-align: center;
    padding: 40px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.cbd-empty-state h2 {
    font-size: 1.5em;
    margin-bottom: 10px;
}

.cbd-empty-state p {
    color: #666;
    margin-bottom: 20px;
}

.cbd-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.cbd-status-active {
    background: #d4f4dd;
    color: #1e8e3e;
}

.cbd-status-inactive {
    background: #fce8e6;
    color: #d33b27;
}

.cbd-status-draft {
    background: #f0f0f0;
    color: #666;
}

.cbd-delete-link {
    color: #b32d2e !important;
}

.cbd-delete-link:hover {
    color: #dc3232 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Als Standard setzen Handler
    $('.cbd-set-default').on('click', function(e) {
        e.preventDefault();

        const blockId = $(this).data('block-id');
        const $link = $(this);

        if (confirm('<?php esc_attr_e('Möchten Sie diesen Block als Standard festlegen?', 'container-block-designer'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_set_default_block',
                    block_id: blockId,
                    nonce: '<?php echo wp_create_nonce('cbd_set_default_block'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Seite neu laden um die Änderungen anzuzeigen
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php esc_attr_e('Fehler beim Setzen des Standard-Blocks.', 'container-block-designer'); ?>');
                    }
                },
                error: function() {
                    alert('<?php esc_attr_e('AJAX-Fehler. Bitte versuchen Sie es erneut.', 'container-block-designer'); ?>');
                }
            });
        }
    });
});
</script>