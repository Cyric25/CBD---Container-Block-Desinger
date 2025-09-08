<?php
/**
 * Container Block Designer - Database Class
 * Now uses unified Schema Manager for all database operations
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Load Schema Manager
require_once CBD_PLUGIN_DIR . 'includes/Database/class-schema-manager.php';

/**
 * Database handler class - Refactored to use Schema Manager
 */
class CBD_Database {
    
    /**
     * Create database tables - Now delegated to Schema Manager
     */
    public static function create_tables() {
        return CBD_Schema_Manager::create_tables();
    }
    
    /**
     * Update schema - Now delegated to Schema Manager
     */
    public static function update_schema() {
        return CBD_Schema_Manager::run_migrations();
    }
    
    /**
     * Get block by ID
     */
    public static function get_block($block_id) {
        global $wpdb;
        
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ), ARRAY_A);
        
        if ($block) {
            // Sichere JSON-Dekodierung mit Null-Check
            $block['config'] = !empty($block['config']) ? json_decode($block['config'], true) : array();
            $block['styles'] = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
            $block['features'] = !empty($block['features']) ? json_decode($block['features'], true) : array();
        }
        
        return $block;
    }
    
    /**
     * Get block by name
     */
    public static function get_block_by_name($name) {
        global $wpdb;
        
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s",
            $name
        ), ARRAY_A);
        
        if ($block) {
            // Sichere JSON-Dekodierung mit Null-Check
            $block['config'] = !empty($block['config']) ? json_decode($block['config'], true) : array();
            $block['styles'] = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
            $block['features'] = !empty($block['features']) ? json_decode($block['features'], true) : array();
        }
        
        return $block;
    }
    
    /**
     * Get all blocks
     */
    public static function get_blocks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM " . CBD_TABLE_BLOCKS;
        
        // WHERE clause
        $where = array();
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        // ORDER BY
        $allowed_orderby = array('id', 'name', 'title', 'status', 'created_at', 'updated_at');
        if (in_array($args['orderby'], $allowed_orderby)) {
            $sql .= " ORDER BY " . $args['orderby'];
            $sql .= ($args['order'] === 'DESC') ? ' DESC' : ' ASC';
        }
        
        // LIMIT
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            if ($args['offset'] > 0) {
                $sql .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        // Decode JSON fields
        foreach ($blocks as &$block) {
            $block['config'] = !empty($block['config']) ? json_decode($block['config'], true) : array();
            $block['styles'] = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
            $block['features'] = !empty($block['features']) ? json_decode($block['features'], true) : array();
        }
        
        return $blocks;
    }
    
    /**
     * Save block
     */
    public static function save_block($data, $block_id = 0) {
        global $wpdb;
        
        // Daten vorbereiten
        $save_data = array(
            'name' => sanitize_text_field($data['name'] ?? ''),
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'config' => wp_json_encode($data['config'] ?? array()),
            'styles' => wp_json_encode($data['styles'] ?? array()),
            'features' => wp_json_encode($data['features'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'updated_at' => current_time('mysql')
        );
        
        if ($block_id) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $save_data,
                array('id' => $block_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert
            $save_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                CBD_TABLE_BLOCKS,
                $save_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                $block_id = $wpdb->insert_id;
            }
        }
        
        return $result !== false ? $block_id : false;
    }
    
    /**
     * Delete block
     */
    public static function delete_block($block_id) {
        global $wpdb;
        
        return $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => $block_id),
            array('%d')
        );
    }
    
    /**
     * Update block status
     */
    public static function update_block_status($block_id, $status) {
        global $wpdb;
        
        return $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $block_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Update block features
     */
    public static function update_block_features($block_id, $features) {
        global $wpdb;
        
        return $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'features' => wp_json_encode($features),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $block_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Check if block name exists
     */
    public static function block_name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s",
            $name
        );
        
        if ($exclude_id > 0) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        return $wpdb->get_var($sql) > 0;
    }
    
    /**
     * Duplicate block
     */
    public static function duplicate_block($block_id) {
        global $wpdb;
        
        $original = self::get_block($block_id);
        
        if (!$original) {
            return false;
        }
        
        // Generate unique name
        $counter = 1;
        $new_name = $original['name'] . '_copy';
        while (self::block_name_exists($new_name)) {
            $counter++;
            $new_name = $original['name'] . '_copy_' . $counter;
        }
        
        // Prepare duplicate
        $duplicate_data = array(
            'name' => $new_name,
            'title' => $original['title'] . ' (Kopie)',
            'description' => $original['description'],
            'config' => $original['config'],
            'styles' => $original['styles'],
            'features' => $original['features'],
            'status' => 'inactive' // Set duplicate to inactive by default
        );
        
        return self::save_block($duplicate_data);
    }
    
    /**
     * Get block count
     */
    public static function get_block_count($status = '') {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS;
        
        if (!empty($status)) {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Search blocks
     */
    public static function search_blocks($search_term) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " 
            WHERE name LIKE %s 
            OR title LIKE %s 
            OR description LIKE %s 
            ORDER BY name ASC",
            $search_term,
            $search_term,
            $search_term
        );
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        // Decode JSON fields
        foreach ($blocks as &$block) {
            $block['config'] = !empty($block['config']) ? json_decode($block['config'], true) : array();
            $block['styles'] = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
            $block['features'] = !empty($block['features']) ? json_decode($block['features'], true) : array();
        }
        
        return $blocks;
    }
}
