<?php
/**
 * IMMEDIATE DATABASE FIX - Container Block Designer
 * Repariert die Datenbank-Struktur sofort und ohne Abhängigkeiten
 */

// Load WordPress
if (!defined('ABSPATH')) {
    $wp_paths = array(
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    );

    foreach ($wp_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            break;
        }
    }

    if (!defined('ABSPATH')) {
        die('WordPress konnte nicht geladen werden');
    }
}

echo "<h2>🚨 IMMEDIATE Database Fix - Container Block Designer</h2>";
echo "<pre>\n";

echo "=== URGENT FIX - Container Block Designer ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'cbd_blocks';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo "❌ Table $table_name does not exist. Creating it now...\n";

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        title varchar(200) NOT NULL DEFAULT '',
        slug varchar(100) NOT NULL DEFAULT '',
        description text DEFAULT NULL,
        config longtext DEFAULT NULL,
        styles longtext DEFAULT NULL,
        features longtext DEFAULT NULL,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name (name),
        KEY status (status),
        KEY slug (slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        echo "✅ Table created successfully!\n\n";
    } else {
        echo "❌ Failed to create table\n\n";
        exit;
    }
} else {
    echo "✅ Table $table_name exists\n";
}

// Check current table structure
echo "1. Checking current table structure...\n";
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
echo "   Current columns: " . implode(', ', $columns) . "\n\n";

// Add missing columns directly
echo "2. Adding missing columns...\n";

$required_columns = array(
    'title' => "ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '' AFTER `name`",
    'slug' => "ALTER TABLE $table_name ADD COLUMN `slug` varchar(100) NOT NULL DEFAULT '' AFTER `title`",
    'styles' => "ALTER TABLE $table_name ADD COLUMN `styles` longtext DEFAULT NULL AFTER `config`",
    'features' => "ALTER TABLE $table_name ADD COLUMN `features` longtext DEFAULT NULL AFTER `styles`",
    'status' => "ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`"
);

$fixes_applied = 0;

foreach ($required_columns as $column => $sql) {
    if (!in_array($column, $columns)) {
        echo "   🔧 Adding missing column: $column\n";
        $result = $wpdb->query($sql);
        if ($result !== false) {
            echo "   ✅ Column '$column' added successfully\n";
            $fixes_applied++;
        } else {
            echo "   ❌ Failed to add column '$column': " . $wpdb->last_error . "\n";
        }
    } else {
        echo "   ✅ Column '$column' already exists\n";
    }
}

// Set default values for NULL fields
echo "\n3. Setting default values for NULL fields...\n";
$wpdb->query("UPDATE $table_name SET config = '{}' WHERE config IS NULL OR config = ''");
$wpdb->query("UPDATE $table_name SET styles = '{}' WHERE styles IS NULL OR styles = ''");
$wpdb->query("UPDATE $table_name SET features = '{}' WHERE features IS NULL OR features = ''");
$wpdb->query("UPDATE $table_name SET slug = name WHERE slug = '' OR slug IS NULL");
echo "   ✅ Default values set\n";

// Check final structure
echo "\n4. Checking final table structure...\n";
$final_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
echo "   Final columns: " . implode(', ', $final_columns) . "\n\n";

// Test the problematic insert
echo "5. Testing problematic INSERT query...\n";
$test_data = array(
    'name' => 'test-fix-' . time(),
    'title' => 'Test Fix Block',
    'slug' => 'test-fix-' . time(),
    'description' => 'Test description',
    'config' => '{"allowInnerBlocks":true,"templateLock":false}',
    'styles' => '{"padding":{"top":20,"right":20,"bottom":20,"left":20}}',
    'features' => '{"icon":{"enabled":true}}',
    'status' => 'active',
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql')
);

$result = $wpdb->insert($table_name, $test_data);
if ($result) {
    echo "   ✅ Test INSERT successful! New ID: " . $wpdb->insert_id . "\n";
    // Clean up test data
    $wpdb->delete($table_name, array('id' => $wpdb->insert_id));
    echo "   ✅ Test data cleaned up\n";
} else {
    echo "   ❌ Test INSERT failed: " . $wpdb->last_error . "\n";
}

echo "\n6. Checking for existing blocks...\n";
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "   Total blocks in database: $count\n";

if ($fixes_applied > 0) {
    echo "\n🎉 SUCCESS: $fixes_applied database fixes applied!\n";
    echo "🔄 Please refresh your WordPress admin and try again.\n";
} else {
    echo "\n✅ Database structure was already correct.\n";
}

echo "\n=== Fix Complete ===\n";
echo "The 'Unknown column' errors should now be resolved.\n";

echo "</pre>";
?>