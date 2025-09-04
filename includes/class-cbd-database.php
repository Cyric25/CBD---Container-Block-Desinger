<?php
/**
 * Container Block Designer - Database Class
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.3
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
        
        // Einheitliches Schema mit created_at und updated_at
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            title varchar(200) NOT NULL,
            description text,
            config longtext,
            styles longtext,
            features longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Migration für bestehende Tabellen
        self::migrate_columns();
    }
    
    /**
     * Migrate old column names to new ones
     */
    public static function migrate_columns() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        
        // Prüfe ob Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // Hole aktuelle Spalten
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        
        // Entferne slug Spalte wenn sie existiert (wird nicht mehr benötigt)
        if (in_array('slug', $columns)) {
            // Prüfe zuerst ob die slug Spalte einen UNIQUE KEY hat
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Column_name = 'slug'");
            if (!empty($indexes)) {
                // Entferne den UNIQUE KEY
                foreach ($indexes as $index) {
                    if ($index->Key_name != 'PRIMARY') {
                        $wpdb->query("ALTER TABLE $table_name DROP INDEX `{$index->Key_name}`");
                    }
                }
            }
            // Entferne die Spalte
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN `slug`");
        }
        
        // Migration von 'created' zu 'created_at'
        if (in_array('created', $columns) && !in_array('created_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `created` `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Migration von 'updated' oder 'modified' zu 'updated_at'
        if (in_array('updated', $columns) && !in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `updated` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } elseif (in_array('modified', $columns) && !in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `modified` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // Falls keine Zeitstempel-Spalten existieren, füge sie hinzu
        if (!in_array('created_at', $columns) && !in_array('created', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
        }
        
        if (!in_array('updated_at', $columns) && !in_array('updated', $columns) && !in_array('modified', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // Stelle sicher, dass alle anderen benötigten Spalten existieren
        if (!in_array('title', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL AFTER `name`");
            // Fülle title mit name für bestehende Einträge
            $wpdb->query("UPDATE $table_name SET title = name WHERE title IS NULL OR title = ''");
        }
        
        if (!in_array('styles', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `styles` longtext AFTER `config`");
        }
        
        if (!in_array('features', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `features` longtext AFTER `styles`");
        }
        
        if (!in_array('status', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`");
        }
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
    public static function get_all_blocks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 100
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
        $sql .= " LIMIT " . intval($args['limit']);
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        // JSON dekodieren
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
    public static function save_block($data) {
        global $wpdb;
        
        // Daten vorbereiten
        $block_data = array(
            'name' => sanitize_text_field($data['name']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'config' => json_encode($data['config'] ?? array()),
            'styles' => json_encode($data['styles'] ?? array()),
            'features' => json_encode($data['features'] ?? array()),
            'status' => $data['status'] ?? 'active',
            'updated_at' => current_time('mysql')
        );
        
        // Prüfe ob Block existiert
        if (!empty($data['id'])) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $block_data,
                array('id' => intval($data['id']))
            );
        } else {
            // Insert
            $block_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(CBD_TABLE_BLOCKS, $block_data);
            
            if ($result) {
                $data['id'] = $wpdb->insert_id;
            }
        }
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return $data['id'];
    }
    
    /**
     * Delete block
     */
    public static function delete_block($block_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => intval($block_id))
        );
        
        return $result !== false;
    }
    
    /**
     * Check if block name exists
     */
    public static function block_name_exists($name, $exclude_id = null) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s",
            $name
        );
        
        if ($exclude_id) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        return (int) $wpdb->get_var($sql) > 0;
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
            array('id' => intval($block_id))
        );
    }
    
    /**
     * Drop tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS " . CBD_TABLE_BLOCKS);
    }
}