<?php
/**
 * Container Block Designer - Database Class
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.1
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler class
 */
class CBD_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = CBD_TABLE_BLOCKS;
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            title varchar(200) NOT NULL,
            description text,
            config longtext,
            styles longtext,
            features longtext,
            status varchar(20) DEFAULT 'active',
            created datetime DEFAULT CURRENT_TIMESTAMP,
            updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
            'orderby' => 'created',
            'order' => 'DESC',
            'limit' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM " . CBD_TABLE_BLOCKS;
        $where = array();
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        
        if ($args['limit'] > 0) {
            $sql .= " LIMIT " . intval($args['limit']);
        }
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        // Dekodiere JSON für alle Blöcke mit Null-Check
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
        
        // Bereite Daten vor
        $save_data = array(
            'name' => sanitize_text_field($data['name']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'config' => wp_json_encode($data['config'] ?? array()),
            'styles' => wp_json_encode($data['styles'] ?? array()),
            'features' => wp_json_encode($data['features'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'updated' => current_time('mysql')
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
            $save_data['created'] = current_time('mysql');
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
                'updated' => current_time('mysql')
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
                'updated' => current_time('mysql')
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
        
        return (bool) $wpdb->get_var($sql);
    }
    
    /**
     * Get default block config
     */
    public static function get_default_config() {
        return array(
            'allowInnerBlocks' => true,
            'templateLock' => false
        );
    }
    
    /**
     * Get default block styles
     */
    public static function get_default_styles() {
        return array(
            'padding' => array(
                'top' => 20,
                'right' => 20,
                'bottom' => 20,
                'left' => 20
            ),
            'background' => array(
                'color' => '#ffffff'
            ),
            'border' => array(
                'width' => 1,
                'color' => '#e0e0e0',
                'style' => 'solid',
                'radius' => 4
            ),
            'text' => array(
                'color' => '#333333',
                'alignment' => 'left'
            )
        );
    }
    
    /**
     * Get default block features
     */
    public static function get_default_features() {
        return array(
            'icon' => array('enabled' => false, 'value' => 'dashicons-admin-generic'),
            'collapse' => array('enabled' => false, 'defaultState' => 'expanded'),
            'numbering' => array('enabled' => false, 'format' => 'numeric'),
            'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
            'screenshot' => array('enabled' => false, 'buttonText' => 'Screenshot')
        );
    }
}