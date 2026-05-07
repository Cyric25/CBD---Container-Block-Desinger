<?php
/**
 * Container Block Designer - Block Organizer Admin-Seite
 *
 * @package ContainerBlockDesigner
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'Keine Berechtigung.', 'container-block-designer' ) );
}

$pages_with_blocks = CBD_Block_Organizer::get_pages_with_blocks();
$nonce             = wp_create_nonce( 'cbd_block_organizer' );
?>

<div class="wrap" id="cbd-block-organizer">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-move" style="font-size:30px;width:30px;height:30px;margin-right:8px;vertical-align:middle;"></span>
        <?php _e( 'Block-Organizer', 'container-block-designer' ); ?>
    </h1>
    <p class="description" style="margin-top:8px;">
        <?php _e( 'Container-Blöcke zwischen Seiten kopieren oder verschieben.', 'container-block-designer' ); ?>
    </p>

    <hr class="wp-header-end">

    <?php if ( empty( $pages_with_blocks ) ) : ?>
        <div class="notice notice-info">
            <p><?php _e( 'Keine Seiten mit Container-Blöcken gefunden.', 'container-block-designer' ); ?></p>
        </div>
    <?php else : ?>

    <div id="cbd-organizer-app">

        <!-- Zweispaltiges Layout -->
        <div class="cbd-organizer-columns">

            <!-- ── LINKE SPALTE: Quell-Seite ── -->
            <div class="cbd-organizer-panel" id="cbd-source-panel">
                <h2><?php _e( 'Quell-Seite', 'container-block-designer' ); ?></h2>

                <div class="cbd-field-row">
                    <label for="cbd-source-page"><?php _e( 'Seite auswählen:', 'container-block-designer' ); ?></label>
                    <select id="cbd-source-page">
                        <option value=""><?php _e( '— Seite wählen —', 'container-block-designer' ); ?></option>
                        <?php foreach ( $pages_with_blocks as $p ) : ?>
                            <option value="<?php echo esc_attr( $p['id'] ); ?>">
                                <?php echo esc_html( $p['title'] ); ?>
                                <span style="color:#999">(<?php echo esc_html( $p['type'] ); ?>)</span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="cbd-source-blocks-wrap" style="display:none;">
                    <h3><?php _e( 'Container-Blöcke:', 'container-block-designer' ); ?></h3>
                    <div id="cbd-source-blocks-list">
                        <p class="cbd-loading"><?php _e( 'Lade Blöcke…', 'container-block-designer' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Pfeil-Trenner -->
            <div class="cbd-organizer-arrow">
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </div>

            <!-- ── RECHTE SPALTE: Ziel-Seite ── -->
            <div class="cbd-organizer-panel" id="cbd-target-panel">
                <h2><?php _e( 'Ziel-Seite', 'container-block-designer' ); ?></h2>

                <div class="cbd-field-row">
                    <label for="cbd-target-page"><?php _e( 'Seite auswählen:', 'container-block-designer' ); ?></label>
                    <select id="cbd-target-page">
                        <option value=""><?php _e( '— Seite wählen —', 'container-block-designer' ); ?></option>
                        <?php
                        // Alle Seiten als Ziel (nicht nur jene mit Blöcken)
                        $all_pages = get_posts( array(
                            'post_type'      => array( 'page', 'post' ),
                            'post_status'    => 'any',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ) );
                        foreach ( $all_pages as $ap ) :
                        ?>
                            <option value="<?php echo esc_attr( $ap->ID ); ?>">
                                <?php echo esc_html( $ap->post_title ?: __( '(ohne Titel)', 'container-block-designer' ) ); ?>
                                <span style="color:#999">(<?php echo esc_html( $ap->post_type ); ?>)</span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cbd-field-row">
                    <label for="cbd-insert-position"><?php _e( 'Einfügeposition:', 'container-block-designer' ); ?></label>
                    <select id="cbd-insert-position">
                        <option value="end"><?php _e( 'Am Ende', 'container-block-designer' ); ?></option>
                        <option value="start"><?php _e( 'Am Anfang', 'container-block-designer' ); ?></option>
                    </select>
                </div>

                <div id="cbd-target-blocks-preview" style="display:none;">
                    <h3><?php _e( 'Vorhandene Blöcke:', 'container-block-designer' ); ?></h3>
                    <div id="cbd-target-blocks-list"></div>
                </div>
            </div>

        </div><!-- .cbd-organizer-columns -->

        <!-- Statusmeldung -->
        <div id="cbd-organizer-message" style="display:none;" class="notice" role="alert"></div>

    </div><!-- #cbd-organizer-app -->

    <?php endif; ?>
</div><!-- .wrap -->

<style>
#cbd-block-organizer .cbd-organizer-columns {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    margin-top: 24px;
    flex-wrap: wrap;
}
#cbd-block-organizer .cbd-organizer-panel {
    flex: 1;
    min-width: 280px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
#cbd-block-organizer .cbd-organizer-panel h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 1.1em;
}
#cbd-block-organizer .cbd-organizer-arrow {
    display: flex;
    align-items: center;
    padding-top: 80px;
    color: #999;
}
#cbd-block-organizer .cbd-organizer-arrow .dashicons {
    font-size: 36px;
    width: 36px;
    height: 36px;
}
#cbd-block-organizer .cbd-field-row {
    margin-bottom: 16px;
}
#cbd-block-organizer .cbd-field-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
#cbd-block-organizer .cbd-field-row select {
    width: 100%;
    max-width: 400px;
}
#cbd-block-organizer .cbd-block-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    margin-bottom: 6px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    flex-wrap: wrap;
    gap: 8px;
}
#cbd-block-organizer .cbd-block-item .cbd-block-label {
    flex: 1;
    font-size: 13px;
    min-width: 120px;
    word-break: break-word;
}
#cbd-block-organizer .cbd-block-item .cbd-block-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
#cbd-block-organizer .cbd-block-actions .button {
    font-size: 12px;
    height: 28px;
    line-height: 26px;
    padding: 0 10px;
}
#cbd-block-organizer .cbd-block-actions .button-copy {
    border-color: #2271b1;
    color: #2271b1;
}
#cbd-block-organizer .cbd-block-actions .button-copy:hover {
    background: #2271b1;
    color: #fff;
}
#cbd-block-organizer .cbd-block-actions .button-move {
    border-color: #d63638;
    color: #d63638;
}
#cbd-block-organizer .cbd-block-actions .button-move:hover {
    background: #d63638;
    color: #fff;
}
#cbd-block-organizer #cbd-organizer-message {
    margin-top: 20px;
    padding: 10px 16px;
}
#cbd-block-organizer .cbd-target-block-item {
    padding: 6px 10px;
    margin-bottom: 4px;
    background: #f0f6fc;
    border-left: 3px solid #2271b1;
    font-size: 12px;
    color: #555;
    border-radius: 2px;
}
@media (max-width: 900px) {
    #cbd-block-organizer .cbd-organizer-arrow { display: none; }
}
</style>

<script>
(function($) {
    var nonce = <?php echo json_encode( $nonce ); ?>;
    var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    // Quell-Seite gewählt: Blöcke laden
    $('#cbd-source-page').on('change', function() {
        var pageId = $(this).val();
        if (!pageId) {
            $('#cbd-source-blocks-wrap').hide();
            return;
        }
        $('#cbd-source-blocks-wrap').show();
        $('#cbd-source-blocks-list').html('<p><?php echo esc_js( __( 'Lade Blöcke…', 'container-block-designer' ) ); ?></p>');

        $.post(ajaxUrl, {
            action: 'cbd_get_page_blocks',
            post_id: pageId,
            nonce: nonce
        }, function(response) {
            if (!response.success) {
                showMessage(response.data || '<?php echo esc_js( __( 'Fehler beim Laden.', 'container-block-designer' ) ); ?>', 'error');
                $('#cbd-source-blocks-list').html('');
                return;
            }
            renderSourceBlocks(response.data, pageId);
        });
    });

    // Ziel-Seite gewählt: Vorschau der vorhandenen Blöcke
    $('#cbd-target-page').on('change', function() {
        var pageId = $(this).val();
        if (!pageId) {
            $('#cbd-target-blocks-preview').hide();
            return;
        }
        $.post(ajaxUrl, {
            action: 'cbd_get_page_blocks',
            post_id: pageId,
            nonce: nonce
        }, function(response) {
            if (!response.success || !response.data.length) {
                $('#cbd-target-blocks-preview').hide();
                return;
            }
            renderTargetPreview(response.data);
        });
    });

    function renderSourceBlocks(blocks, sourcePageId) {
        if (!blocks.length) {
            $('#cbd-source-blocks-list').html('<p><?php echo esc_js( __( 'Keine Container-Blöcke gefunden.', 'container-block-designer' ) ); ?></p>');
            return;
        }
        var html = '';
        $.each(blocks, function(i, block) {
            html += '<div class="cbd-block-item">' +
                '<span class="cbd-block-label">' + escHtml(block.label) + '</span>' +
                '<span class="cbd-block-actions">' +
                    '<button class="button button-copy" data-action="copy" data-index="' + block.index + '" data-source="' + sourcePageId + '">' +
                        '<span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span>' +
                        '<?php echo esc_js( __( 'Kopieren', 'container-block-designer' ) ); ?>' +
                    '</button>' +
                    '<button class="button button-move" data-action="move" data-index="' + block.index + '" data-source="' + sourcePageId + '">' +
                        '<span class="dashicons dashicons-move" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span>' +
                        '<?php echo esc_js( __( 'Verschieben', 'container-block-designer' ) ); ?>' +
                    '</button>' +
                '</span>' +
            '</div>';
        });
        $('#cbd-source-blocks-list').html(html);
    }

    function renderTargetPreview(blocks) {
        var html = '';
        $.each(blocks, function(i, block) {
            html += '<div class="cbd-target-block-item">' + escHtml(block.label) + '</div>';
        });
        $('#cbd-target-blocks-list').html(html);
        $('#cbd-target-blocks-preview').show();
    }

    // Copy / Move Buttons
    $(document).on('click', '.button-copy, .button-move', function() {
        var $btn      = $(this);
        var action    = $btn.data('action');
        var sourceId  = $btn.data('source');
        var blockIdx  = $btn.data('index');
        var targetId  = $('#cbd-target-page').val();
        var position  = $('#cbd-insert-position').val();

        if (!targetId) {
            showMessage('<?php echo esc_js( __( 'Bitte zuerst eine Ziel-Seite auswählen.', 'container-block-designer' ) ); ?>', 'error');
            return;
        }
        if (action === 'move' && sourceId == targetId) {
            showMessage('<?php echo esc_js( __( 'Quelle und Ziel sind identisch.', 'container-block-designer' ) ); ?>', 'error');
            return;
        }

        var ajaxAction = (action === 'copy') ? 'cbd_copy_block' : 'cbd_move_block';
        $btn.prop('disabled', true).text('<?php echo esc_js( __( '…', 'container-block-designer' ) ); ?>');

        $.post(ajaxUrl, {
            action: ajaxAction,
            source_post_id: sourceId,
            block_index: blockIdx,
            target_post_id: targetId,
            position: position,
            nonce: nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                var msg = (action === 'copy')
                    ? '<?php echo esc_js( __( 'Block erfolgreich kopiert.', 'container-block-designer' ) ); ?>'
                    : '<?php echo esc_js( __( 'Block erfolgreich verschoben.', 'container-block-designer' ) ); ?>';
                showMessage(msg, 'success');

                // Restore button label
                if (action === 'copy') {
                    $btn.html('<span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span><?php echo esc_js( __( 'Kopieren', 'container-block-designer' ) ); ?>');
                } else {
                    // Bei Verschieben: Block-Item entfernen und Quelle neu laden
                    $btn.closest('.cbd-block-item').fadeOut(300, function() { $(this).remove(); });
                }

                // Ziel-Vorschau aktualisieren
                var newTargetId = $('#cbd-target-page').val();
                if (newTargetId) {
                    $.post(ajaxUrl, { action: 'cbd_get_page_blocks', post_id: newTargetId, nonce: nonce }, function(r) {
                        if (r.success) renderTargetPreview(r.data);
                    });
                }
            } else {
                var errMsg = response.data || '<?php echo esc_js( __( 'Fehler aufgetreten.', 'container-block-designer' ) ); ?>';
                showMessage(errMsg, 'error');
                // Restore button label
                if (action === 'copy') {
                    $btn.html('<span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span><?php echo esc_js( __( 'Kopieren', 'container-block-designer' ) ); ?>');
                } else {
                    $btn.html('<span class="dashicons dashicons-move" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span><?php echo esc_js( __( 'Verschieben', 'container-block-designer' ) ); ?>');
                }
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            showMessage('<?php echo esc_js( __( 'Server-Fehler. Bitte Seite neu laden.', 'container-block-designer' ) ); ?>', 'error');
        });
    });

    function showMessage(text, type) {
        var $msg = $('#cbd-organizer-message');
        $msg.removeClass('notice-success notice-error notice-warning')
            .addClass(type === 'success' ? 'notice-success' : 'notice-error')
            .html('<p>' + escHtml(text) + '</p>')
            .show();
        // Automatisch ausblenden nach 5 Sekunden
        clearTimeout($msg.data('timer'));
        $msg.data('timer', setTimeout(function() { $msg.fadeOut(); }, 5000));
    }

    function escHtml(str) {
        return $('<div>').text(String(str)).html();
    }

})(jQuery);
</script>
