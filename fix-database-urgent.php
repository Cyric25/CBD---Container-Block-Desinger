<?php
/**
 * Container Block Designer - Urgent Database Fix
 *
 * Run this script to immediately fix database schema issues
 * This addresses the missing 'styles' and 'features' columns
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent direct access unless called from WordPress context
if (!defined('ABSPATH')) {
    // Allow direct execution for emergency fix
    if (!isset($_SERVER['HTTP_HOST'])) {
        echo "This script must be run through WordPress context.\n";
        echo "Please access it via: /wp-content/plugins/container-block-designer/fix-database-urgent.php\n";
        exit;
    }

    // Simple WordPress context loader for emergency
    $wp_load_paths = array(
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    );

    foreach ($wp_load_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            break;
        }
    }

    if (!defined('ABSPATH')) {
        die('Could not load WordPress. Please run this script from WordPress admin or copy to wp-admin directory.');
    }
}

/**
 * Emergency Database Fix Class
 */
class CBD_Emergency_DB_Fix {

    /**
     * Run the emergency fix
     */
    public static function run() {
        global $wpdb;

        echo "<h2>Container Block Designer - Emergency Database Fix</h2>\n";
        echo "<pre>\n";

        $table_name = $wpdb->prefix . 'cbd_blocks';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            echo "❌ Table $table_name does not exist. Creating it...\n";
            self::create_table();
            echo "✅ Table created successfully.\n";
            echo "</pre>";
            return;
        }

        echo "✅ Table $table_name exists.\n";

        // Get current columns
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        echo "📋 Current columns: " . implode(', ', $columns) . "\n\n";

        $fixes_applied = 0;

        // Add missing 'styles' column
        if (!in_array('styles', $columns)) {
            echo "🔧 Adding missing 'styles' column...\n";
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `styles` longtext DEFAULT NULL AFTER `config`");

            if ($result !== false) {
                echo "✅ 'styles' column added successfully.\n";
                $fixes_applied++;
            } else {
                echo "❌ Failed to add 'styles' column: " . $wpdb->last_error . "\n";
            }
        } else {
            echo "✅ 'styles' column already exists.\n";
        }

        // Add missing 'features' column
        if (!in_array('features', $columns)) {
            echo "🔧 Adding missing 'features' column...\n";
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `features` longtext DEFAULT NULL AFTER `styles`");

            if ($result !== false) {
                echo "✅ 'features' column added successfully.\n";
                $fixes_applied++;
            } else {
                echo "❌ Failed to add 'features' column: " . $wpdb->last_error . "\n";
            }
        } else {
            echo "✅ 'features' column already exists.\n";
        }

        // Add missing 'title' column if needed
        if (!in_array('title', $columns)) {
            echo "🔧 Adding missing 'title' column...\n";
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '' AFTER `name`");

            if ($result !== false) {
                echo "✅ 'title' column added successfully.\n";
                $fixes_applied++;
            } else {
                echo "❌ Failed to add 'title' column: " . $wpdb->last_error . "\n";
            }
        } else {
            echo "✅ 'title' column already exists.\n";
        }

        // Add missing 'status' column if needed
        if (!in_array('status', $columns)) {
            echo "🔧 Adding missing 'status' column...\n";
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`");

            if ($result !== false) {
                echo "✅ 'status' column added successfully.\n";
                $fixes_applied++;
            } else {
                echo "❌ Failed to add 'status' column: " . $wpdb->last_error . "\n";
            }
        } else {
            echo "✅ 'status' column already exists.\n";
        }

        // Fix NULL values
        echo "\n🔧 Setting default values for NULL fields...\n";

        $updated_configs = $wpdb->query("UPDATE $table_name SET config = '{}' WHERE config IS NULL");
        echo "📝 Updated $updated_configs config fields.\n";

        if (in_array('styles', $columns)) {
            $updated_styles = $wpdb->query("UPDATE $table_name SET styles = '{}' WHERE styles IS NULL");
            echo "📝 Updated $updated_styles styles fields.\n";
        }

        if (in_array('features', $columns)) {
            $updated_features = $wpdb->query("UPDATE $table_name SET features = '{}' WHERE features IS NULL");
            echo "📝 Updated $updated_features features fields.\n";
        }

        // Show final status
        echo "\n📊 Final table structure:\n";
        $final_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        foreach ($final_columns as $column) {
            echo "  ✓ $column\n";
        }

        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "\n📈 Total rows in table: $row_count\n";

        if ($fixes_applied > 0) {
            echo "\n🎉 Emergency fix completed! $fixes_applied database modifications applied.\n";
            echo "🔄 Please refresh your WordPress admin to see the changes.\n";
        } else {
            echo "\n✅ No fixes needed. Database is already up to date.\n";
        }

        echo "</pre>";
    }

    /**
     * Create the table from scratch if it doesn't exist
     */
    private static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cbd_blocks';
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
        dbDelta($sql);
    }
}

// Run the fix if accessed directly or via WordPress admin
if (is_admin() || !defined('ABSPATH')) {
    CBD_Emergency_DB_Fix::run();
} else {
    echo "<p>This script can only be run from WordPress admin area.</p>";
}
?>