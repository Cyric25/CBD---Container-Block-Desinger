<?php
/**
 * LaTeX Bulk Cleanup - Fix corrupted LaTeX formulas in all posts/pages
 *
 * @package ContainerBlockDesigner
 * @since 2.8.7
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_LaTeX_Bulk_Cleanup {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // AJAX handler for bulk cleanup
        add_action('wp_ajax_cbd_latex_bulk_cleanup', array($this, 'ajax_bulk_cleanup'));
    }

    /**
     * Add admin menu page
     * DISABLED: Tool is too dangerous, strips backslashes
     */
    public function add_admin_menu() {
        // DISABLED - DO NOT USE
        // WordPress's wp_update_post() strips backslashes from LaTeX formulas
        // This makes the cleanup tool dangerous and unusable
        // Users should restore from revisions instead
        return;

        /*
        add_submenu_page(
            'tools.php',
            'LaTeX Formeln bereinigen',
            'LaTeX Bereinigung',
            'manage_options',
            'cbd-latex-cleanup',
            array($this, 'render_admin_page')
        );
        */
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }

        ?>
        <div class="wrap">
            <h1>LaTeX Formeln Bulk-Bereinigung</h1>
            <p>Dieses Tool bereinigt beschädigte LaTeX-Formeln in allen Beiträgen und Seiten.</p>

            <div class="card">
                <h2>Was wird behoben?</h2>
                <ul>
                    <li><code>pK&lt;em&gt;S&lt;/em&gt;</code> → <code>$pK_S$</code></li>
                    <li><code>K&lt;em&gt;S&lt;/em&gt;</code> → <code>$K_S$</code></li>
                    <li><code>pKS</code> → <code>$pK_S$</code></li>
                    <li>HTML-Entities in LaTeX-Formeln werden dekodiert</li>
                    <li>Beschädigte LaTeX-Tabellen werden repariert</li>
                </ul>
            </div>

            <div class="notice notice-warning">
                <p><strong>⚠️ WICHTIG:</strong> Stellen Sie sicher, dass WordPress Post-Revisionen aktiviert sind!</p>
                <p>Falls etwas schiefgeht, können Sie über <strong>Seite bearbeiten → Revisionen</strong> wiederherstellen.</p>
            </div>

            <div class="card">
                <h2>Was macht dieses Tool?</h2>
                <p>Dieses Tool behebt <strong>nur <code>&lt;em&gt;</code> Tags</strong> in LaTeX-Formeln.</p>
                <p><strong>Es ändert KEINE:</strong></p>
                <ul>
                    <li>✓ Backslashes (werden bewahrt)</li>
                    <li>✓ Bereits korrekte Formeln</li>
                    <li>✓ Inhalte außerhalb von $...$</li>
                </ul>
            </div>

            <div class="card">
                <h2>Bereinigung starten</h2>
                <p>
                    <button id="cbd-start-cleanup" class="button button-primary button-large">
                        Alle Seiten und Beiträge bereinigen
                    </button>
                </p>
                <p class="description">
                    <strong>Hinweis:</strong> Dieser Vorgang kann einige Minuten dauern, je nach Anzahl der Seiten.
                </p>
            </div>

            <div id="cbd-cleanup-progress" style="display: none; margin-top: 20px;">
                <h3>Fortschritt:</h3>
                <div style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 4px;">
                    <div id="cbd-progress-bar" style="background: #0073aa; height: 30px; width: 0%; transition: width 0.3s; border-radius: 3px; color: white; text-align: center; line-height: 30px;">
                        0%
                    </div>
                    <p id="cbd-progress-text" style="margin-top: 10px;">Starte Bereinigung...</p>
                </div>
            </div>

            <div id="cbd-cleanup-results" style="display: none; margin-top: 20px;">
                <h3>Ergebnisse:</h3>
                <div style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 4px;">
                    <div id="cbd-results-content"></div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#cbd-start-cleanup').on('click', function() {
                var button = $(this);
                button.prop('disabled', true);

                $('#cbd-cleanup-progress').show();
                $('#cbd-cleanup-results').hide();

                // Start cleanup
                cleanupBatch(0);
            });

            function cleanupBatch(offset) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cbd_latex_bulk_cleanup',
                        offset: offset,
                        nonce: '<?php echo wp_create_nonce('cbd_latex_cleanup'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;

                            // Update progress
                            var percent = Math.round((data.processed / data.total) * 100);
                            $('#cbd-progress-bar').css('width', percent + '%').text(percent + '%');
                            $('#cbd-progress-text').text(
                                'Verarbeitet: ' + data.processed + ' von ' + data.total +
                                ' (' + data.cleaned + ' bereinigt)'
                            );

                            // Continue if not done
                            if (!data.done) {
                                cleanupBatch(data.next_offset);
                            } else {
                                // Show results
                                $('#cbd-cleanup-results').show();
                                $('#cbd-results-content').html(
                                    '<p style="color: green; font-weight: bold;">✓ Bereinigung abgeschlossen!</p>' +
                                    '<ul>' +
                                    '<li>Gesamt verarbeitet: ' + data.total + '</li>' +
                                    '<li>Bereinigt: ' + data.cleaned + '</li>' +
                                    '<li>Keine Änderungen: ' + (data.total - data.cleaned) + '</li>' +
                                    '</ul>'
                                );
                                $('#cbd-start-cleanup').prop('disabled', false);
                            }
                        } else {
                            alert('Fehler: ' + response.data);
                            $('#cbd-start-cleanup').prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('AJAX-Fehler bei der Bereinigung.');
                        $('#cbd-start-cleanup').prop('disabled', false);
                    }
                });
            }
        });
        </script>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccc;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .card h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for bulk cleanup
     */
    public function ajax_bulk_cleanup() {
        // Security check
        check_ajax_referer('cbd_latex_cleanup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.');
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10; // Process 10 posts at a time

        // Get posts and pages
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'any',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        );

        $query = new WP_Query($args);

        // Get total count (only on first batch)
        if ($offset === 0) {
            $total_args = array(
                'post_type' => array('post', 'page'),
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            $total_query = new WP_Query($total_args);
            $total = $total_query->post_count;
            set_transient('cbd_latex_cleanup_total', $total, HOUR_IN_SECONDS);
        } else {
            $total = get_transient('cbd_latex_cleanup_total');
        }

        $cleaned = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $content = get_post_field('post_content', $post_id);

                // Clean the content
                $cleaned_content = $this->clean_latex_content($content);

                // Update if changed
                if ($cleaned_content !== $content) {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => $cleaned_content
                    ));
                    $cleaned++;
                }
            }
            wp_reset_postdata();
        }

        // Calculate progress
        $processed = $offset + $query->post_count;
        $done = ($processed >= $total);

        // Clean up transient if done
        if ($done) {
            delete_transient('cbd_latex_cleanup_total');
        }

        wp_send_json_success(array(
            'processed' => $processed,
            'total' => $total,
            'cleaned' => $cleaned,
            'done' => $done,
            'next_offset' => $offset + $batch_size
        ));
    }

    /**
     * Clean LaTeX content - ONLY fix <em> tags, DON'T decode entities
     */
    private function clean_latex_content($content) {
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        // DO NOT decode HTML entities - that strips backslashes!
        // Only fix <em> tags that WordPress created

        // Pattern 1: Remove <em> tags within dollar signs
        $content = preg_replace_callback(
            '/(\$[^$]*?)<em>([^<]+?)<\/em>([^$]*?\$)/i',
            function($matches) {
                return $matches[1] . '_' . $matches[2] . '_' . $matches[3];
            },
            $content
        );

        // Pattern 2: Fix common chemistry notation (pK_S, K_S, etc.)
        // ONLY if NOT already in dollar signs
        $content = preg_replace('/(?<!\$)pK<em>([A-Z])<\/em>(?!\$)/i', '$pK_$1$', $content);
        $content = preg_replace('/(?<!\$)([^a-z])K<em>([A-Z])<\/em>(?!\$)/i', '$1$K_$2$', $content);

        // Pattern 3: REMOVED - Don't auto-wrap, too aggressive

        return $content;
    }
}
