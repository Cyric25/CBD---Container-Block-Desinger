<?php
/**
 * Container Block Designer - Debug Test
 * 
 * Speichern Sie diese Datei im Plugin-Hauptverzeichnis und rufen Sie sie auf:
 * /wp-admin/admin.php?page=cbd-debug
 */

// Add debug page to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden page
        'CBD Debug',
        'CBD Debug',
        'manage_options',
        'cbd-debug',
        'cbd_debug_page'
    );
});

function cbd_debug_page() {
    ?>
    <div class="wrap">
        <h1>Container Block Designer - Debug</h1>
        
        <h2>1. PHP Version Check</h2>
        <pre>
PHP Version: <?php echo PHP_VERSION; ?>
Required: 7.4+
Status: <?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '✅ OK' : '❌ FEHLER'; ?>
        </pre>
        
        <h2>2. WordPress Version Check</h2>
        <pre>
WordPress Version: <?php echo get_bloginfo('version'); ?>
Required: 6.0+
Status: <?php echo version_compare(get_bloginfo('version'), '6.0', '>=') ? '✅ OK' : '❌ FEHLER'; ?>
        </pre>
        
        <h2>3. Plugin Constants</h2>
        <pre>
CBD_VERSION: <?php echo defined('CBD_VERSION') ? CBD_VERSION : 'NOT DEFINED'; ?>
CBD_PLUGIN_DIR: <?php echo defined('CBD_PLUGIN_DIR') ? CBD_PLUGIN_DIR : 'NOT DEFINED'; ?>
CBD_PLUGIN_URL: <?php echo defined('CBD_PLUGIN_URL') ? CBD_PLUGIN_URL : 'NOT DEFINED'; ?>
CBD_TABLE_BLOCKS: <?php echo defined('CBD_TABLE_BLOCKS') ? CBD_TABLE_BLOCKS : 'NOT DEFINED'; ?>
        </pre>
        
        <h2>4. File Check</h2>
        <pre>
<?php
        $files = array(
            'install.php',
            'includes/ajax-handlers.php',
            'includes/rest-api.php',
            'includes/Admin/class-admin-features.php',
            'includes/Blocks/block-frontend-renderer.php',
            'admin/blocks-list.php',
            'admin/edit-block.php',
            'admin/new-block.php',
            'admin/views/edit-block.php',
            'assets/js/admin.js',
            'assets/js/admin-features.js',
            'assets/js/container-block.js',
            'assets/js/advanced-features.js',
            'assets/css/admin.css',
            'assets/css/admin-features.css',
            'assets/css/container-block.css',
            'assets/css/advanced-features.css'
        );
        
        foreach ($files as $file) {
            $path = CBD_PLUGIN_DIR . $file;
            $exists = file_exists($path);
            echo sprintf(
                "%-50s %s\n",
                $file . ':',
                $exists ? '✅ EXISTS' : '❌ MISSING'
            );
        }
?>
        </pre>
        
        <h2>5. Database Table Check</h2>
        <pre>
<?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        echo "Table $table_name: " . ($table_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";
        
        if ($table_exists) {
            echo "\nTable Structure:\n";
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
            foreach ($columns as $column) {
                echo "  - " . $column->Field . " (" . $column->Type . ")\n";
            }
            
            echo "\nRow Count: " . $wpdb->get_var("SELECT COUNT(*) FROM $table_name") . "\n";
        }
?>
        </pre>
        
        <h2>6. Error Log (Last 10 lines)</h2>
        <pre>
<?php
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log)) {
            $lines = file($debug_log);
            $last_lines = array_slice($lines, -10);
            foreach ($last_lines as $line) {
                echo htmlspecialchars($line);
            }
        } else {
            echo "No debug.log found";
        }
?>
        </pre>
        
        <h2>7. Actions</h2>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cbd-debug&action=create-table'), 'cbd_debug'); ?>" class="button">Create Database Table</a>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cbd-debug&action=reset-plugin'), 'cbd_debug'); ?>" class="button">Reset Plugin</a>
        </p>
        
        <?php
        if (isset($_GET['action']) && wp_verify_nonce($_GET['_wpnonce'], 'cbd_debug')) {
            if ($_GET['action'] === 'create-table') {
                cbd_create_tables();
                echo '<div class="notice notice-success"><p>Table created!</p></div>';
            } elseif ($_GET['action'] === 'reset-plugin') {
                cbd_install();
                echo '<div class="notice notice-success"><p>Plugin reset!</p></div>';
            }
        }
        ?>
    </div>
    <?php
}
?>