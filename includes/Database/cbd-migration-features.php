<?php
/**
 * Container Block Designer - Features Migration
 * 
 * Adds features column to blocks table
 * 
 * @package ContainerBlockDesigner
 * @subpackage Database
 * @since 2.0.0
 */

namespace ContainerBlockDesigner\Database;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Features Migration Class
 */
class Migration_Features {
    
    /**
     * Migration version
     */
    const VERSION = '2.0.0';
    
    /**
     * Check if migration is needed
     */
    public static function is_needed() {
        $current_version = get_option('cbd_features_migration_version', '0');
        return version_compare($current_version, self::VERSION, '<');
    }
    
    /**
     * Run migration up
     */
    public static function up() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        
        // Check if features column already exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'features'
        ));
        
        if (empty($column_exists)) {
            // Add features column
            $result = $wpdb->query(
                "ALTER TABLE `{$table_name}` 
                ADD COLUMN `features` LONGTEXT NULL AFTER `config`"
            );
            
            if ($result === false) {
                error_log('CBD Migration Error: Failed to add features column - ' . $wpdb->last_error);
                return false;
            }
        }
        
        // Check if css_variables column already exists
        $css_column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'css_variables'
        ));
        
        if (empty($css_column_exists)) {
            // Add css_variables column
            $result = $wpdb->query(
                "ALTER TABLE `{$table_name}` 
                ADD COLUMN `css_variables` TEXT NULL AFTER `features`"
            );
            
            if ($result === false) {
                error_log('CBD Migration Error: Failed to add css_variables column - ' . $wpdb->last_error);
                return false;
            }
        }
        
        // Set default features for existing blocks
        self::set_default_features();
        
        // Update migration version
        update_option('cbd_features_migration_version', self::VERSION);
        
        return true;
    }
    
    /**
     * Run migration down (rollback)
     */
    public static function down() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        
        // Remove features column
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `features`");
        
        // Remove css_variables column
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `css_variables`");
        
        // Remove migration version
        delete_option('cbd_features_migration_version');
        
        return true;
    }
    
    /**
     * Set default features for existing blocks
     */
    private static function set_default_features() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        
        // Get all blocks without features
        $blocks = $wpdb->get_results(
            "SELECT id, name FROM {$table_name} 
            WHERE features IS NULL OR features = ''"
        );
        
        if (empty($blocks)) {
            return;
        }
        
        // Default features configuration
        $default_features = [
            'icon' => [
                'enabled' => false,
                'value' => 'dashicons-admin-generic',
                'color' => '#007cba'
            ],
            'collapse' => [
                'enabled' => false,
                'defaultState' => 'expanded',
                'saveState' => true,
                'animationSpeed' => 300
            ],
            'numbering' => [
                'enabled' => false,
                'format' => 'numeric',
                'startFrom' => 1,
                'prefix' => '',
                'suffix' => '.'
            ],
            'copyText' => [
                'enabled' => false,
                'buttonText' => 'Text kopieren',
                'position' => 'top-right',
                'copyFormat' => 'plain'
            ],
            'screenshot' => [
                'enabled' => false,
                'buttonText' => 'Screenshot',
                'format' => 'png',
                'quality' => 0.95
            ]
        ];
        
        $features_json = json_encode($default_features);
        
        // Update each block with default features
        foreach ($blocks as $block) {
            $wpdb->update(
                $table_name,
                ['features' => $features_json],
                ['id' => $block->id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    /**
     * Check migration status
     */
    public static function get_status() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        $status = [
            'version' => get_option('cbd_features_migration_version', 'Not installed'),
            'features_column' => false,
            'css_variables_column' => false,
            'blocks_with_features' => 0,
            'total_blocks' => 0
        ];
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            $status['error'] = 'Table does not exist';
            return $status;
        }
        
        // Check columns
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
        foreach ($columns as $column) {
            if ($column->Field === 'features') {
                $status['features_column'] = true;
            }
            if ($column->Field === 'css_variables') {
                $status['css_variables_column'] = true;
            }
        }
        
        // Count blocks
        $status['total_blocks'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $status['blocks_with_features'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
            WHERE features IS NOT NULL AND features != ''"
        );
        
        return $status;
    }
    
    /**
     * Repair migration if needed
     */
    public static function repair() {
        // Check current status
        $status = self::get_status();
        
        // If columns are missing, run migration
        if (!$status['features_column'] || !$status['css_variables_column']) {
            return self::up();
        }
        
        // If blocks don't have features, set defaults
        if ($status['blocks_with_features'] < $status['total_blocks']) {
            self::set_default_features();
        }
        
        return true;
    }
}