<?php
/**
 * Container Block Designer - Datenbank-Klasse
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasse für Datenbank-Operationen
 */
class CBD_Database {
    
    /**
     * Datenbank-Version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Datenbank-Tabellen erstellen
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabelle für Container-Blöcke
        $sql = "CREATE TABLE IF NOT EXISTS " . CBD_TABLE_BLOCKS . " (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
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
        
        // Datenbank-Version speichern
        update_option('cbd_db_version', self::DB_VERSION);
    }
    
    /**
     * Tabellen löschen
     */
    public static function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS " . CBD_TABLE_BLOCKS);
        
        delete_option('cbd_db_version');
    }
    
    /**
     * Datenbank-Upgrade prüfen
     */
    public static function check_db_upgrade() {
        $current_version = get_option('cbd_db_version', '0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Block speichern
     */
    public static function save_block($data) {
        global $wpdb;
        
        $block_data = array(
            'name' => sanitize_text_field($data['name']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'config' => wp_json_encode($data['config'] ?? array()),
            'styles' => wp_json_encode($data['styles'] ?? array()),
            'features' => wp_json_encode($data['features'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'updated' => current_time('mysql'),
        );
        
        if (!empty($data['id'])) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $block_data,
                array('id' => intval($data['id']))
            );
        } else {
            // Insert
            $block_data['created'] = current_time('mysql');
            $result = $wpdb->insert(CBD_TABLE_BLOCKS, $block_data);
        }
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return !empty($data['id']) ? intval($data['id']) : $wpdb->insert_id;
    }
    
    /**
     * Block abrufen
     */
    public static function get_block($id) {
        global $wpdb;
        
        $block = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if ($block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['styles'] = json_decode($block['styles'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $block;
    }
    
    /**
     * Block nach Name abrufen
     */
    public static function get_block_by_name($name) {
        global $wpdb;
        
        $block = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s",
                $name
            ),
            ARRAY_A
        );
        
        if ($block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['styles'] = json_decode($block['styles'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $block;
    }
    
    /**
     * Alle Blöcke abrufen
     */
    public static function get_blocks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0,
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
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        foreach ($blocks as &$block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['styles'] = json_decode($block['styles'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $blocks;
    }
    
    /**
     * Block löschen
     */
    public static function delete_block($id) {
        global $wpdb;
        
        return $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => intval($id))
        );
    }
    
    /**
     * Block-Status ändern
     */
    public static function update_block_status($id, $status) {
        global $wpdb;
        
        return $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'status' => sanitize_text_field($status),
                'updated' => current_time('mysql'),
            ),
            array('id' => intval($id))
        );
    }
    
    /**
     * Blocks duplizieren
     */
    public static function duplicate_block($id) {
        global $wpdb;
        
        $block = self::get_block($id);
        
        if (!$block) {
            return false;
        }
        
        // Neuen Namen generieren
        $base_name = preg_replace('/-copy-\d+$/', '', $block['name']);
        $new_name = $base_name . '-copy';
        $counter = 1;
        
        while (self::get_block_by_name($new_name)) {
            $new_name = $base_name . '-copy-' . $counter;
            $counter++;
        }
        
        // Block-Daten vorbereiten
        unset($block['id']);
        $block['name'] = $new_name;
        $block['title'] = $block['title'] . ' (Kopie)';
        $block['status'] = 'inactive';
        
        return self::save_block($block);
    }
}