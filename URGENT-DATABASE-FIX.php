<?php
/**
 * URGENT DATABASE FIX - Container Block Designer
 *
 * This script will immediately fix the missing database columns
 * Run this NOW to fix your database issues
 */

// Set headers to prevent caching
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load WordPress - try multiple paths
$wordpress_loaded = false;
$wp_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
];

foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wordpress_loaded = true;
        break;
    }
}

if (!$wordpress_loaded) {
    die('❌ WordPress could not be loaded. Please check the file path.');
}

// Start the fix
?>
<!DOCTYPE html>
<html>
<head>
    <title>🚨 URGENT Database Fix - Container Block Designer</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0; padding: 20px; background: #f1f1f1;
        }
        .container {
            max-width: 800px; margin: 0 auto; background: white;
            padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #f57900; font-weight: bold; }
        .info { color: #0073aa; font-weight: bold; }
        pre {
            background: #f6f7f7; padding: 15px; border-radius: 4px;
            overflow-x: auto; border-left: 4px solid #00a32a;
        }
        h1 { color: #d63638; }
        h2 { color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
        .step { background: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚨 URGENT Database Fix - Container Block Designer</h1>
        <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        $fixes_applied = 0;

        echo "<h2>Step 1: Checking Current Database</h2>";

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            echo "<div class='error'>❌ Table $table_name does not exist!</div>";
            echo "<div class='step'><strong>Creating table now...</strong></div>";

            // Create table with all required columns
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                title varchar(200) NOT NULL DEFAULT '',
                description text DEFAULT NULL,
                config longtext DEFAULT NULL,
                styles longtext DEFAULT NULL,
                features longtext DEFAULT NULL,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY name (name),
                KEY status (status)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                echo "<div class='success'>✅ Table created successfully!</div>";
                $fixes_applied++;
            } else {
                echo "<div class='error'>❌ Failed to create table</div>";
            }
        } else {
            echo "<div class='success'>✅ Table $table_name exists</div>";
        }

        // Check current columns
        echo "<h2>Step 2: Checking Table Structure</h2>";
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        echo "<pre>Current columns: " . implode(', ', $columns) . "</pre>";

        // Add missing columns
        echo "<h2>Step 3: Adding Missing Columns</h2>";

        $required_columns = [
            'title' => "ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '' AFTER `name`",
            'styles' => "ALTER TABLE $table_name ADD COLUMN `styles` longtext DEFAULT NULL AFTER `config`",
            'features' => "ALTER TABLE $table_name ADD COLUMN `features` longtext DEFAULT NULL AFTER `styles`",
            'status' => "ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`"
        ];

        foreach ($required_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                echo "<div class='warning'>⚠️ Adding missing column: $column</div>";
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    echo "<div class='success'>✅ Column '$column' added successfully</div>";
                    $fixes_applied++;
                } else {
                    echo "<div class='error'>❌ Failed to add '$column': " . $wpdb->last_error . "</div>";
                }
            } else {
                echo "<div class='success'>✅ Column '$column' already exists</div>";
            }
        }

        // Fix NULL values
        echo "<h2>Step 4: Setting Default Values</h2>";
        $null_fixes = [
            'config' => "UPDATE $table_name SET config = '{}' WHERE config IS NULL",
            'styles' => "UPDATE $table_name SET styles = '{}' WHERE styles IS NULL",
            'features' => "UPDATE $table_name SET features = '{}' WHERE features IS NULL"
        ];

        foreach ($null_fixes as $field => $sql) {
            $updated = $wpdb->query($sql);
            echo "<div class='info'>📝 Updated $updated $field fields</div>";
        }

        // Create default blocks
        echo "<h2>Step 5: Creating Default Blocks</h2>";
        $block_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($block_count == 0) {
            echo "<div class='warning'>⚠️ No blocks found. Creating default blocks...</div>";

            $default_blocks = [
                [
                    'name' => 'basic-container',
                    'title' => 'Einfacher Container',
                    'description' => 'Ein einfacher Container mit Rahmen und Padding',
                    'config' => '{"allowInnerBlocks":true,"maxWidth":"100%","minHeight":"100px"}',
                    'styles' => '{"padding":{"top":20,"right":20,"bottom":20,"left":20},"background":{"color":"#ffffff"},"border":{"width":1,"style":"solid","color":"#e0e0e0","radius":4}}',
                    'features' => '{}',
                    'status' => 'active'
                ],
                [
                    'name' => 'card-container',
                    'title' => 'Info-Box',
                    'description' => 'Eine Info-Box mit Icon und blauem Hintergrund',
                    'config' => '{"allowInnerBlocks":true,"maxWidth":"100%","minHeight":"80px"}',
                    'styles' => '{"padding":{"top":15,"right":20,"bottom":15,"left":50},"background":{"color":"#e3f2fd"},"border":{"width":0,"radius":4},"typography":{"color":"#1565c0"}}',
                    'features' => '{"icon":{"enabled":true,"value":"dashicons-info","position":"left","color":"#1565c0"}}',
                    'status' => 'active'
                ],
                [
                    'name' => 'hero-section',
                    'title' => 'Hero Section',
                    'description' => 'Ein Hero-Bereich für prominente Inhalte',
                    'config' => '{"allowInnerBlocks":true,"maxWidth":"100%","minHeight":"60px"}',
                    'styles' => '{"padding":{"top":15,"right":15,"bottom":15,"left":15},"background":{"color":"#f5f5f5"},"border":{"width":1,"style":"solid","color":"#d0d0d0","radius":4}}',
                    'features' => '{"collapsible":{"enabled":true,"defaultState":"expanded"}}',
                    'status' => 'active'
                ]
            ];

            foreach ($default_blocks as $block) {
                $result = $wpdb->insert($table_name, $block);
                if ($result) {
                    echo "<div class='success'>✅ Created block: {$block['title']}</div>";
                    $fixes_applied++;
                } else {
                    echo "<div class='error'>❌ Failed to create block: {$block['title']} - " . $wpdb->last_error . "</div>";
                }
            }
        } else {
            echo "<div class='success'>✅ Found $block_count existing blocks</div>";
        }

        // Final verification
        echo "<h2>Step 6: Final Verification</h2>";
        $final_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        echo "<pre>Final columns: " . implode(', ', $final_columns) . "</pre>";

        $final_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
        echo "<div class='info'>📊 Active blocks in database: $final_count</div>";

        // Test the problematic query
        echo "<h2>Step 7: Testing Problematic Query</h2>";
        try {
            $test_features = $wpdb->get_results("SELECT features FROM $table_name WHERE status = 'active' LIMIT 1");
            echo "<div class='success'>✅ Query 'SELECT features FROM table WHERE status = active' works!</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Query still fails: " . $e->getMessage() . "</div>";
        }

        // Summary
        echo "<h2>🎉 Fix Summary</h2>";
        if ($fixes_applied > 0) {
            echo "<div class='success'><strong>✅ SUCCESS: $fixes_applied database fixes applied!</strong></div>";
            echo "<div class='step'>";
            echo "<h3>Next Steps:</h3>";
            echo "<ol>";
            echo "<li>Go to your WordPress Admin</li>";
            echo "<li>Navigate to the Container Block Designer plugin</li>";
            echo "<li>The dropdown should now be filled with blocks</li>";
            echo "<li>Try creating a new block - it should work without errors</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='info'>ℹ️ Database was already up to date. No fixes needed.</div>";
        }

        echo "<div class='warning'><strong>⚠️ Important:</strong> Clear any caching plugins you might have!</div>";
        ?>

        <h2>🔍 Debug Information</h2>
        <pre><?php
        echo "WordPress Version: " . get_bloginfo('version') . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "MySQL Version: " . $wpdb->db_version() . "\n";
        echo "Table Prefix: " . $wpdb->prefix . "\n";
        echo "Full Table Name: " . $table_name . "\n";
        echo "Current User: " . wp_get_current_user()->user_login . "\n";
        ?></pre>

    </div>
</body>
</html><?php
// Clear any output buffers
if (ob_get_level()) ob_end_flush();
?>