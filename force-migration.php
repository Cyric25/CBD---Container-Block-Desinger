<?php
/**
 * Force Database Migration - Container Block Designer
 *
 * Run this to force the database migration immediately
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Load our classes
require_once __DIR__ . '/includes/Database/class-schema-manager.php';

echo "<h2>🔧 Force Database Migration</h2>";
echo "<pre>\n";

echo "=== Container Block Designer - Force Migration ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'cbd_blocks';

// Check current table structure
echo "1. Checking current table structure...\n";
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
echo "   Current columns: " . implode(', ', $columns) . "\n\n";

// Force run the migration
echo "2. Running migration to version 2.6.0...\n";
try {
    CBD_Schema_Manager::run_migrations();
    echo "   ✅ Migration completed successfully!\n\n";
} catch (Exception $e) {
    echo "   ❌ Migration failed: " . $e->getMessage() . "\n\n";
}

// Check final structure
echo "3. Checking final table structure...\n";
$final_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
echo "   Final columns: " . implode(', ', $final_columns) . "\n\n";

// Check for specific columns
$required_columns = array('styles', 'features', 'title', 'status');
foreach ($required_columns as $col) {
    if (in_array($col, $final_columns)) {
        echo "   ✅ Column '$col' exists\n";
    } else {
        echo "   ❌ Column '$col' missing\n";
    }
}

// Test insert
echo "\n4. Testing database insert...\n";
$test_data = array(
    'name' => 'test-migration-' . time(),
    'title' => 'Test Migration Block',
    'config' => '{}',
    'styles' => '{}',
    'features' => '{}',
    'status' => 'active'
);

$result = $wpdb->insert($table_name, $test_data);
if ($result) {
    echo "   ✅ Test insert successful!\n";
    // Clean up test data
    $wpdb->delete($table_name, array('name' => $test_data['name']));
} else {
    echo "   ❌ Test insert failed: " . $wpdb->last_error . "\n";
}

echo "\n5. Checking for existing blocks...\n";
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "   Total blocks in database: $count\n";

echo "\n=== Migration Complete ===\n";
echo "Please refresh your WordPress admin and try creating a block again.\n";

echo "</pre>";
?>