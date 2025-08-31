<?php
/**
 * Container Block Designer - Quick Fix Script
 * Version: 1.0.1 - FIXED
 * 
 * Dieses Skript behebt automatisch die häufigsten Probleme
 * Speichern Sie es als quickfix.php im Plugin-Hauptverzeichnis
 * Rufen Sie es auf über: /wp-admin/admin.php?page=cbd-quickfix
 * 
 * @package ContainerBlockDesigner
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Add to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden page
        'CBD Quick Fix',
        'CBD Quick Fix',
        'manage_options',
        'cbd-quickfix',
        'cbd_quickfix_page'
    );
});

/**
 * Quick Fix Page
 */
function cbd_quickfix_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung');
    }
    
    $fixes_applied = array();
    $errors = array();
    
    // Apply fixes if requested
    if (isset($_POST['apply_fixes']) && wp_verify_nonce($_POST['_wpnonce'], 'cbd_quickfix')) {
        
        // Fix 1: Ensure database table exists with correct structure
        if (isset($_POST['fix_database'])) {
            $result = cbd_fix_database_structure();
            if ($result) {
                $fixes_applied[] = '✅ Datenbank-Struktur repariert';
            } else {
                $errors[] = '❌ Fehler bei Datenbank-Reparatur';
            }
        }
        
        // Fix 2: Create sample block if none exists
        if (isset($_POST['create_sample'])) {
            $result = cbd_create_sample_block();
            if ($result) {
                $fixes_applied[] = '✅ Beispiel-Block erstellt';
            } else {
                $errors[] = '❌ Beispiel-Block konnte nicht erstellt werden';
            }
        }
        
        // Fix 3: Register block category
        if (isset($_POST['fix_category'])) {
            $result = cbd_fix_block_category();
            if ($result) {
                $fixes_applied[] = '✅ Block-Kategorie registriert';
            } else {
                $errors[] = '❌ Block-Kategorie konnte nicht registriert werden';
            }
        }
        
        // Fix 4: Clear caches
        if (isset($_POST['clear_cache'])) {
            cbd_clear_all_caches();
            $fixes_applied[] = '✅ Cache geleert';
        }
        
        // Fix 5: Re-register scripts
        if (isset($_POST['reregister_scripts'])) {
            cbd_reregister_scripts();
            $fixes_applied[] = '✅ Scripts neu registriert';
        }
    }
    
    // Display page
    ?>
    <div class="wrap">
        <h1>🔧 Container Block Designer - Quick Fix</h1>
        
        <?php if (!empty($fixes_applied)): ?>
            <div class="notice notice-success is-dismissible">
                <h3>Erfolgreich angewendete Fixes:</h3>
                <ul>
                    <?php foreach ($fixes_applied as $fix): ?>
                        <li><?php echo esc_html($fix); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="notice notice-error is-dismissible">
                <h3>Fehler:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>🔍 System-Status</h2>
            <?php cbd_check_system_status(); ?>
        </div>
        
        <div class="card">
            <h2>🚀 Automatische Fixes</h2>
            <form method="post" action="">
                <?php wp_nonce_field('cbd_quickfix'); ?>
                
                <p>
                    <label>
                        <input type="checkbox" name="fix_database" checked>
                        <strong>Datenbank-Struktur reparieren</strong>
                        <br>
                        <span class="description">Erstellt die Tabelle <?php echo esc_html(CBD_TABLE_BLOCKS); ?> mit korrekter Struktur</span>
                    </label>
                </p>
                
                <p>
                    <label>
                        <input type="checkbox" name="create_sample" checked>
                        <strong>Beispiel-Block erstellen</strong>
                        <br>
                        <span class="description">Erstellt einen funktionierenden Beispiel-Block zum Testen</span>
                    </label>
                </p>
                
                <p>
                    <label>
                        <input type="checkbox" name="fix_category" checked>
                        <strong>Block-Kategorie registrieren</strong>
                        <br>
                        <span class="description">Registriert die 'Container Blocks' Kategorie im Editor</span>
                    </label>
                </p>
                
                <p>
                    <label>
                        <input type="checkbox" name="clear_cache" checked>
                        <strong>Cache leeren</strong>
                        <br>
                        <span class="description">Leert WordPress Cache und Transients</span>
                    </label>
                </p>
                
                <p>
                    <label>
                        <input type="checkbox" name="reregister_scripts" checked>
                        <strong>Scripts neu registrieren</strong>
                        <br>
                        <span class="description">Registriert alle JavaScript und CSS Dateien neu</span>
                    </label>
                </p>
                
                <p>
                    <input type="submit" name="apply_fixes" class="button button-primary button-hero" value="🚀 Fixes anwenden">
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>🔍 JavaScript Debug</h2>
            <p>Öffne die Browser-Konsole (F12) um die Ergebnisse zu sehen:</p>
            <button type="button" class="button" onclick="testBlockRegistration()">Block-Registrierung testen</button>
            <button type="button" class="button" onclick="testAjax()">AJAX testen</button>
            <button type="button" class="button" onclick="testRestApi()">REST API testen</button>
            <div id="js-test-results"></div>
        </div>
        
        <div class="card">
            <h2>📝 Debug Log (Letzte 20 Zeilen)</h2>
            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
<?php
            $debug_log = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($debug_log)) {
                $lines = file($debug_log);
                $last_lines = array_slice($lines, -20);
                foreach ($last_lines as $line) {
                    // Filter for CBD related messages
                    if (stripos($line, 'cbd') !== false || stripos($line, 'container-block') !== false) {
                        echo '<strong>' . htmlspecialchars($line) . '</strong>';
                    } else {
                        echo htmlspecialchars($line);
                    }
                }
            } else {
                echo "Keine debug.log gefunden. Aktiviere WP_DEBUG_LOG in wp-config.php";
            }
?>
            </pre>
        </div>
        
    </div>
    
    <script>
    function testBlockRegistration() {
        if (typeof wp !== 'undefined' && wp.blocks) {
            const blockType = wp.blocks.getBlockType('container-block-designer/container');
            const results = document.getElementById('js-test-results');
            
            if (blockType) {
                results.innerHTML = '<div class="notice notice-success"><p>✅ Block ist registriert!</p></div>';
                console.log('Block Details:', blockType);
            } else {
                results.innerHTML = '<div class="notice notice-error"><p>❌ Block ist NICHT registriert!</p></div>';
                console.log('Verfügbare Blocks:', wp.blocks.getBlockTypes().map(b => b.name));
            }
        } else {
            alert('Dieser Test funktioniert nur im Gutenberg Editor!');
        }
    }
    
    function testAjax() {
        jQuery.post(ajaxurl, {
            action: 'cbd_get_blocks'
        }, function(response) {
            const results = document.getElementById('js-test-results');
            if (response.success) {
                results.innerHTML = '<div class="notice notice-success"><p>✅ AJAX funktioniert! ' + response.data.length + ' Blocks gefunden.</p></div>';
                console.log('AJAX Response:', response);
            } else {
                results.innerHTML = '<div class="notice notice-error"><p>❌ AJAX Fehler: ' + (response.data || 'Unbekannt') + '</p></div>';
            }
        }).fail(function(xhr) {
            const results = document.getElementById('js-test-results');
            results.innerHTML = '<div class="notice notice-error"><p>❌ AJAX Request fehlgeschlagen!</p></div>';
            console.error('AJAX Error:', xhr);
        });
    }
    
    function testRestApi() {
        fetch('/wp-json/cbd/v1/blocks')
            .then(response => response.json())
            .then(data => {
                const results = document.getElementById('js-test-results');
                results.innerHTML = '<div class="notice notice-success"><p>✅ REST API funktioniert!</p></div>';
                console.log('REST API Response:', data);
            })
            .catch(error => {
                const results = document.getElementById('js-test-results');
                results.innerHTML = '<div class="notice notice-warning"><p>⚠️ REST API nicht verfügbar (optional)</p></div>';
                console.error('REST API Error:', error);
            });
    }
    </script>
    
    <style>
    .card {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
    }
    .card h2 {
        margin-top: 0;
    }
    .button-hero {
        font-size: 16px !important;
        line-height: 28px !important;
        padding: 4px 16px !important;
        height: 40px !important;
    }
    pre {
        font-size: 12px;
        line-height: 1.4;
    }
    </style>
    <?php
}

/**
 * Check system status
 */
function cbd_check_system_status() {
    global $wpdb;
    
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>';
    echo '<tbody>';
    
    // Check 1: Database table
    $table_name = CBD_TABLE_BLOCKS;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    echo '<tr>';
    echo '<td>Datenbank-Tabelle</td>';
    echo '<td>' . ($table_exists ? '✅ Vorhanden' : '❌ Fehlt') . '</td>';
    echo '<td>' . esc_html($table_name) . '</td>';
    echo '</tr>';
    
    // Check 2: Block count
    if ($table_exists) {
        $block_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo '<tr>';
        echo '<td>Gespeicherte Blocks</td>';
        echo '<td>' . ($block_count > 0 ? '✅' : '⚠️') . ' ' . $block_count . ' Blocks</td>';
        echo '<td>' . ($block_count == 0 ? 'Erstelle einen Beispiel-Block mit den Fixes unten' : 'OK') . '</td>';
        echo '</tr>';
    }
    
    // Check 3: Plugin files
    $required_files = array(
        'container-block-designer.php' => 'Hauptdatei',
        'assets/js/container-block.js' => 'Block JavaScript',
        'assets/css/container-block.css' => 'Block Styles',
        'admin/blocks-list.php' => 'Admin Liste'
    );
    
    foreach ($required_files as $file => $description) {
        $file_path = CBD_PLUGIN_DIR . $file;
        $exists = file_exists($file_path);
        echo '<tr>';
        echo '<td>' . esc_html($description) . '</td>';
        echo '<td>' . ($exists ? '✅ Vorhanden' : '❌ Fehlt') . '</td>';
        echo '<td>' . esc_html($file) . '</td>';
        echo '</tr>';
    }
    
    // Check 4: WordPress version
    global $wp_version;
    $wp_ok = version_compare($wp_version, '6.0', '>=');
    echo '<tr>';
    echo '<td>WordPress Version</td>';
    echo '<td>' . ($wp_ok ? '✅' : '⚠️') . ' ' . $wp_version . '</td>';
    echo '<td>' . ($wp_ok ? 'Kompatibel' : 'Update empfohlen (min. 6.0)') . '</td>';
    echo '</tr>';
    
    // Check 5: PHP version
    $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
    echo '<tr>';
    echo '<td>PHP Version</td>';
    echo '<td>' . ($php_ok ? '✅' : '⚠️') . ' ' . PHP_VERSION . '</td>';
    echo '<td>' . ($php_ok ? 'Kompatibel' : 'Update empfohlen (min. 7.4)') . '</td>';
    echo '</tr>';
    
    echo '</tbody></table>';
}

/**
 * Fix database structure
 */
function cbd_fix_database_structure() {
    global $wpdb;
    
    $table_name = CBD_TABLE_BLOCKS;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        title varchar(255) NOT NULL,
        description text,
        config longtext,
        styles longtext,
        features longtext,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_status (status),
        INDEX idx_name (name)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verify table exists
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

/**
 * Create sample block
 */
function cbd_create_sample_block() {
    global $wpdb;
    
    // Check if sample already exists
    $exists = $wpdb->get_var("SELECT id FROM " . CBD_TABLE_BLOCKS . " WHERE name = 'sample-container'");
    
    if ($exists) {
        return true; // Already exists
    }
    
    $sample_config = json_encode(array(
        'styles' => array(
            'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
            'backgroundColor' => '#f0f0f0',
            'textColor' => '#333333',
            'borderWidth' => 2,
            'borderColor' => '#007cba',
            'borderRadius' => 8,
            'textAlignment' => 'left'
        )
    ));
    
    $sample_features = json_encode(array(
        'responsive' => true,
        'animation' => false,
        'customClass' => false,
        'visibility' => false,
        'export' => true
    ));
    
    $result = $wpdb->insert(
        CBD_TABLE_BLOCKS,
        array(
            'name' => 'sample-container',
            'title' => 'Beispiel Container',
            'description' => 'Ein Beispiel-Container-Block zum Testen',
            'config' => $sample_config,
            'styles' => $sample_config,
            'features' => $sample_features,
            'status' => 'active'
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    return $result !== false;
}

/**
 * Fix block category registration
 */
function cbd_fix_block_category() {
    add_filter('block_categories_all', function($categories) {
        array_unshift($categories, array(
            'slug' => 'container-blocks',
            'title' => __('Container Blocks', 'container-block-designer'),
            'icon' => 'layout'
        ));
        return $categories;
    }, 10, 1);
    
    return true;
}

/**
 * Clear all caches
 */
function cbd_clear_all_caches() {
    // WordPress Object Cache
    wp_cache_flush();
    
    // Transients
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
    
    // Rewrite rules
    flush_rewrite_rules();
    
    return true;
}

/**
 * Re-register scripts
 */
function cbd_reregister_scripts() {
    // Remove old registrations
    wp_deregister_script('cbd-container-block');
    wp_deregister_style('cbd-container-block');
    wp_deregister_script('cbd-container-block-editor');
    wp_deregister_style('cbd-container-block-editor');
    
    // Re-register with cache buster
    $version = CBD_VERSION . '.' . time();
    
    wp_register_script(
        'cbd-container-block',
        CBD_PLUGIN_URL . 'assets/js/container-block.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
        $version,
        true
    );
    
    wp_register_style(
        'cbd-container-block',
        CBD_PLUGIN_URL . 'assets/css/container-block.css',
        array(),
        $version
    );
    
    return true;
}