<?php
/**
 * Container Block Designer - Migration Admin Page
 * Tool to add stable IDs to existing container blocks
 *
 * @package ContainerBlockDesigner
 * @since 2.9.73
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('cbd_admin_blocks') && !current_user_can('manage_options')) {
    wp_die(__('Sie haben keine Berechtigung, auf diese Seite zuzugreifen.'));
}

// Get nonce for AJAX
$nonce = wp_create_nonce('cbd-migration-nonce');
?>

<div class="wrap cbd-admin-wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <span class="cbd-version-badge">v<?php echo CBD_VERSION; ?></span>
    </h1>

    <div class="cbd-migration-container">

        <!-- Info Box -->
        <div class="cbd-info-box">
            <h2>🔧 Stable ID Migration</h2>
            <p><strong>Warum ist das nötig?</strong></p>
            <p>Ältere Container-Blöcke (vor Version 2.9.73) haben keine permanenten IDs und verwenden ein instabiles Hash-System.
            Das führt dazu, dass Lehrer-Markierungen verloren gehen wenn Seiten bearbeitet werden.</p>

            <p><strong>Was macht dieses Tool?</strong></p>
            <ul>
                <li>✓ Scannt alle Seiten nach Container-Blöcken ohne Stable-ID</li>
                <li>✓ Fügt permanente IDs zu allen betroffenen Blöcken hinzu</li>
                <li>✓ Aktualisiert bestehende Lehrer-Markierungen automatisch</li>
                <li>✓ Verhindert zukünftige ID-Konflikte</li>
            </ul>

            <p class="cbd-warning">
                <strong>⚠️ Wichtig:</strong> Dieser Vorgang ändert den Post-Content aller betroffenen Seiten.
                Es wird empfohlen, vorher ein Backup zu erstellen.
            </p>
        </div>

        <!-- Scan Section -->
        <div class="cbd-migration-section">
            <h2>Schritt 1: Seiten scannen</h2>
            <p>Zuerst scannen wir alle Seiten um zu sehen, wieviele Blöcke betroffen sind.</p>

            <button type="button" class="button button-primary button-hero" id="cbd-scan-btn">
                <span class="dashicons dashicons-search"></span>
                Seiten jetzt scannen
            </button>

            <div id="cbd-scan-results" class="cbd-results-box" style="display: none;">
                <h3>Scan-Ergebnis:</h3>
                <div class="cbd-stats">
                    <div class="cbd-stat-item">
                        <span class="cbd-stat-number" id="cbd-total-posts">0</span>
                        <span class="cbd-stat-label">Seiten mit Container-Blöcken</span>
                    </div>
                    <div class="cbd-stat-item">
                        <span class="cbd-stat-number" id="cbd-total-blocks">0</span>
                        <span class="cbd-stat-label">Container-Blöcke insgesamt</span>
                    </div>
                    <div class="cbd-stat-item cbd-stat-warning">
                        <span class="cbd-stat-number" id="cbd-blocks-without-id">0</span>
                        <span class="cbd-stat-label">Blöcke ohne Stable-ID</span>
                    </div>
                    <div class="cbd-stat-item cbd-stat-success">
                        <span class="cbd-stat-number" id="cbd-blocks-with-id">0</span>
                        <span class="cbd-stat-label">Blöcke mit Stable-ID</span>
                    </div>
                </div>

                <div id="cbd-affected-posts" style="display: none;">
                    <h4>Betroffene Seiten:</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Seite</th>
                                <th>Typ</th>
                                <th>Anzahl Blöcke</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="cbd-affected-posts-list">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Migration Section -->
        <div class="cbd-migration-section" id="cbd-migrate-section" style="display: none;">
            <h2>Schritt 2: Migration durchführen</h2>
            <p>Jetzt werden permanente IDs zu allen betroffenen Blöcken hinzugefügt und bestehende Markierungen aktualisiert.</p>

            <button type="button" class="button button-primary button-hero" id="cbd-migrate-btn">
                <span class="dashicons dashicons-update"></span>
                Migration jetzt starten
            </button>

            <div id="cbd-migrate-progress" style="display: none;">
                <div class="cbd-progress-bar">
                    <div class="cbd-progress-fill" id="cbd-progress-fill"></div>
                </div>
                <p class="cbd-progress-text" id="cbd-progress-text">Migration läuft...</p>
            </div>

            <div id="cbd-migrate-results" class="cbd-results-box" style="display: none;">
                <h3>Migration abgeschlossen!</h3>
                <div class="cbd-stats">
                    <div class="cbd-stat-item cbd-stat-success">
                        <span class="cbd-stat-number" id="cbd-updated-posts">0</span>
                        <span class="cbd-stat-label">Seiten aktualisiert</span>
                    </div>
                    <div class="cbd-stat-item cbd-stat-success">
                        <span class="cbd-stat-number" id="cbd-updated-blocks">0</span>
                        <span class="cbd-stat-label">Blöcke aktualisiert</span>
                    </div>
                    <div class="cbd-stat-item cbd-stat-success">
                        <span class="cbd-stat-number" id="cbd-updated-markings">0</span>
                        <span class="cbd-stat-label">Markierungen aktualisiert</span>
                    </div>
                </div>

                <div id="cbd-migration-errors" style="display: none;">
                    <h4 class="cbd-error-heading">⚠️ Fehler während der Migration:</h4>
                    <div id="cbd-error-list"></div>
                </div>

                <p class="cbd-success-message">
                    ✅ Die Migration wurde erfolgreich abgeschlossen. Alle Container-Blöcke haben jetzt permanente IDs.
                </p>
            </div>
        </div>

    </div>
</div>

<style>
.cbd-migration-container {
    max-width: 1200px;
}

.cbd-info-box {
    background: #fff;
    border-left: 4px solid #2271b1;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cbd-info-box h2 {
    margin-top: 0;
}

.cbd-info-box ul {
    list-style: none;
    padding-left: 0;
}

.cbd-info-box ul li {
    padding: 5px 0;
}

.cbd-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 12px;
    margin: 15px 0;
}

.cbd-migration-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cbd-results-box {
    background: #f8f9fa;
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
}

.cbd-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.cbd-stat-item {
    background: #fff;
    border-left: 4px solid #2271b1;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.cbd-stat-item.cbd-stat-warning {
    border-left-color: #f59e0b;
}

.cbd-stat-item.cbd-stat-success {
    border-left-color: #10b981;
}

.cbd-stat-number {
    display: block;
    font-size: 36px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 10px;
}

.cbd-stat-warning .cbd-stat-number {
    color: #f59e0b;
}

.cbd-stat-success .cbd-stat-number {
    color: #10b981;
}

.cbd-stat-label {
    display: block;
    font-size: 14px;
    color: #666;
}

.cbd-progress-bar {
    width: 100%;
    height: 30px;
    background: #e0e0e0;
    border-radius: 15px;
    overflow: hidden;
    margin: 20px 0;
}

.cbd-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #4c9bd4);
    width: 0%;
    transition: width 0.3s ease;
}

.cbd-progress-text {
    text-align: center;
    font-weight: bold;
    color: #2271b1;
}

.cbd-success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 12px;
    border-radius: 4px;
    margin: 15px 0;
}

.cbd-error-heading {
    color: #d32f2f;
    margin: 15px 0 10px;
}

#cbd-error-list {
    background: #fff;
    border: 1px solid #f5c6cb;
    padding: 15px;
    border-radius: 4px;
}

.button-hero {
    font-size: 16px !important;
    padding: 10px 20px !important;
    height: auto !important;
}

.button-hero .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    margin-right: 5px;
}

#cbd-affected-posts {
    margin-top: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo $nonce; ?>';
    let scanResults = null;

    // Scan button
    $('#cbd-scan-btn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanne...');
        $('#cbd-scan-results').hide();
        $('#cbd-migrate-section').hide();

        $.post(ajaxurl, {
            action: 'cbd_scan_blocks',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                scanResults = response.data;
                displayScanResults(scanResults);

                if (scanResults.blocks_without_stable_id > 0) {
                    $('#cbd-migrate-section').slideDown();
                }
            } else {
                alert('Fehler beim Scannen: ' + (response.data.message || 'Unbekannter Fehler'));
            }
        }).fail(function() {
            alert('Netzwerkfehler beim Scannen.');
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Seiten jetzt scannen');
        });
    });

    // Migrate button
    $('#cbd-migrate-btn').on('click', function() {
        if (!confirm('Sind Sie sicher, dass Sie die Migration starten möchten? Dieser Vorgang kann nicht rückgängig gemacht werden.')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).hide();
        $('#cbd-migrate-progress').show();
        $('#cbd-migrate-results').hide();

        // Simulate progress
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += 5;
            if (progress <= 90) {
                $('#cbd-progress-fill').css('width', progress + '%');
            }
        }, 200);

        $.post(ajaxurl, {
            action: 'cbd_migrate_blocks',
            nonce: nonce
        }, function(response) {
            clearInterval(progressInterval);
            $('#cbd-progress-fill').css('width', '100%');

            setTimeout(function() {
                $('#cbd-migrate-progress').hide();

                if (response.success) {
                    displayMigrationResults(response.data);
                } else {
                    alert('Fehler bei der Migration: ' + (response.data.message || 'Unbekannter Fehler'));
                    $btn.prop('disabled', false).show();
                }
            }, 500);
        }).fail(function() {
            clearInterval(progressInterval);
            alert('Netzwerkfehler bei der Migration.');
            $btn.prop('disabled', false).show();
            $('#cbd-migrate-progress').hide();
        });
    });

    function displayScanResults(data) {
        $('#cbd-total-posts').text(data.total_posts);
        $('#cbd-total-blocks').text(data.total_blocks);
        $('#cbd-blocks-without-id').text(data.blocks_without_stable_id);
        $('#cbd-blocks-with-id').text(data.blocks_with_stable_id);

        // Show affected posts
        if (data.affected_posts && data.affected_posts.length > 0) {
            let html = '';
            data.affected_posts.forEach(function(post) {
                html += '<tr>' +
                    '<td><strong>' + escapeHtml(post.title) + '</strong></td>' +
                    '<td>' + post.type + '</td>' +
                    '<td>' + post.blocks + '</td>' +
                    '<td><a href="' + post.url + '" target="_blank" class="button button-small">Bearbeiten</a></td>' +
                    '</tr>';
            });
            $('#cbd-affected-posts-list').html(html);
            $('#cbd-affected-posts').show();
        } else {
            $('#cbd-affected-posts').hide();
        }

        $('#cbd-scan-results').slideDown();
    }

    function displayMigrationResults(data) {
        $('#cbd-updated-posts').text(data.updated_posts);
        $('#cbd-updated-blocks').text(data.updated_blocks);
        $('#cbd-updated-markings').text(data.updated_markings);

        // Show errors if any
        if (data.errors && data.errors.length > 0) {
            let html = '<ul>';
            data.errors.forEach(function(error) {
                html += '<li><strong>' + escapeHtml(error.title) + '</strong>: ' + escapeHtml(error.error) + '</li>';
            });
            html += '</ul>';
            $('#cbd-error-list').html(html);
            $('#cbd-migration-errors').show();
        }

        $('#cbd-migrate-results').slideDown();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.dashicons.spin {
    animation: spin 1s linear infinite;
}
</style>
