<?php
/**
 * Container Block Designer - Migration Tool
 * Adds stable IDs to existing container blocks
 *
 * @package ContainerBlockDesigner
 * @since 2.9.73
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CBD_Migration
 * Handles migration of container blocks to add stable IDs
 */
class CBD_Migration {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_cbd_scan_blocks', array($this, 'ajax_scan_blocks'));
        add_action('wp_ajax_cbd_migrate_blocks', array($this, 'ajax_migrate_blocks'));
        add_action('wp_ajax_cbd_cleanup_legacy_markings', array($this, 'ajax_cleanup_legacy_markings'));
    }

    /**
     * Scan all posts/pages for container blocks without stable IDs
     */
    public function ajax_scan_blocks() {
        // Check permissions
        if (!current_user_can('cbd_admin_blocks') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        check_ajax_referer('cbd-migration-nonce', 'nonce');

        $results = $this->scan_all_posts();

        wp_send_json_success($results);
    }

    /**
     * Scan all posts for container blocks
     */
    private function scan_all_posts() {
        global $wpdb;

        // Get all posts/pages that might have container blocks
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post', 'page')
             AND post_content LIKE '%wp:container-block-designer/container%'
             ORDER BY post_type, post_title"
        );

        $total_posts = 0;
        $total_blocks = 0;
        $blocks_without_stable_id = 0;
        $affected_posts = array();

        foreach ($posts as $post) {
            $blocks = parse_blocks($post->post_content);
            $post_blocks = $this->find_container_blocks($blocks);

            if (empty($post_blocks)) {
                continue;
            }

            $total_posts++;
            $post_block_count = 0;
            $post_needs_migration = false;

            foreach ($post_blocks as $block_info) {
                $total_blocks++;
                $post_block_count++;

                if (empty($block_info['attrs']['stableId'])) {
                    $blocks_without_stable_id++;
                    $post_needs_migration = true;
                }
            }

            if ($post_needs_migration) {
                $affected_posts[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'url' => get_edit_post_link($post->ID, 'raw'),
                    'blocks' => $post_block_count
                );
            }
        }

        return array(
            'total_posts' => $total_posts,
            'total_blocks' => $total_blocks,
            'blocks_without_stable_id' => $blocks_without_stable_id,
            'blocks_with_stable_id' => $total_blocks - $blocks_without_stable_id,
            'affected_posts' => $affected_posts
        );
    }

    /**
     * Find all container blocks recursively
     */
    private function find_container_blocks($blocks, $parent_path = array()) {
        $found = array();
        $index = 0;

        foreach ($blocks as $block) {
            $current_path = array_merge($parent_path, array($index));

            if ($block['blockName'] === 'container-block-designer/container') {
                $found[] = array(
                    'block' => $block,
                    'path' => $current_path,
                    'attrs' => isset($block['attrs']) ? $block['attrs'] : array()
                );
            }

            // Recursively check inner blocks
            if (!empty($block['innerBlocks'])) {
                $inner_found = $this->find_container_blocks($block['innerBlocks'], $current_path);
                $found = array_merge($found, $inner_found);
            }

            $index++;
        }

        return $found;
    }

    /**
     * Migrate blocks - add stable IDs and update markings
     */
    public function ajax_migrate_blocks() {
        // Check permissions
        if (!current_user_can('cbd_admin_blocks') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        check_ajax_referer('cbd-migration-nonce', 'nonce');

        // Set time limit for long operations
        set_time_limit(300); // 5 minutes

        $results = $this->migrate_all_blocks();

        wp_send_json_success($results);
    }

    /**
     * Migrate all blocks - add stable IDs
     */
    private function migrate_all_blocks() {
        global $wpdb;

        // Get all posts/pages that might have container blocks
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post', 'page')
             AND post_content LIKE '%wp:container-block-designer/container%'
             ORDER BY post_type, post_title"
        );

        $updated_posts = 0;
        $updated_blocks = 0;
        $updated_markings = 0;
        $errors = array();

        foreach ($posts as $post) {
            $result = $this->migrate_post_blocks($post);

            if ($result['success']) {
                if ($result['blocks_updated'] > 0) {
                    $updated_posts++;
                    $updated_blocks += $result['blocks_updated'];
                }
                $updated_markings += $result['markings_updated'];
            } else {
                $errors[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'error' => $result['error']
                );
            }
        }

        return array(
            'updated_posts' => $updated_posts,
            'updated_blocks' => $updated_blocks,
            'updated_markings' => $updated_markings,
            'errors' => $errors
        );
    }

    /**
     * Migrate blocks in a single post
     */
    private function migrate_post_blocks($post) {
        global $wpdb;

        $blocks = parse_blocks($post->post_content);
        $container_blocks = $this->find_container_blocks($blocks);

        if (empty($container_blocks)) {
            return array(
                'success' => true,
                'blocks_updated' => 0,
                'markings_updated' => 0
            );
        }

        // Track old -> new ID mappings for updating markings
        $id_mappings = array();
        $blocks_updated = 0;

        // Generate stable IDs for blocks that don't have them
        foreach ($container_blocks as $block_info) {
            if (empty($block_info['attrs']['stableId'])) {
                // Generate old legacy ID for mapping
                $selected_block = $block_info['attrs']['selectedBlock'] ?? '';
                $inner_content = $this->get_block_inner_content($block_info['block']);
                $old_legacy_id = 'cbd-legacy-' . md5($post->ID . '|' . $selected_block . '|' . substr(md5($inner_content), 0, 8));

                // Generate new stable ID
                $new_stable_id = 'cbd-' . time() . '-' . wp_generate_password(8, false, false);

                // Store mapping
                $id_mappings[$old_legacy_id] = $new_stable_id;

                // Add stable ID to block
                $this->add_stable_id_to_block($blocks, $block_info['path'], $new_stable_id);
                $blocks_updated++;
            }
        }

        // Update post content if any blocks were updated
        if ($blocks_updated > 0) {
            $new_content = serialize_blocks($blocks);

            $result = $wpdb->update(
                $wpdb->posts,
                array('post_content' => $new_content),
                array('ID' => $post->ID),
                array('%s'),
                array('%d')
            );

            if ($result === false) {
                return array(
                    'success' => false,
                    'error' => 'Fehler beim Aktualisieren der Post-Daten: ' . $wpdb->last_error
                );
            }

            // Clear cache
            clean_post_cache($post->ID);
        }

        // Update markings in database
        $markings_updated = 0;
        if (!empty($id_mappings)) {
            foreach ($id_mappings as $old_id => $new_id) {
                $result = $wpdb->update(
                    CBD_TABLE_DRAWINGS,
                    array('container_id' => $new_id),
                    array(
                        'page_id' => $post->ID,
                        'container_id' => $old_id
                    ),
                    array('%s'),
                    array('%d', '%s')
                );

                if ($result !== false && $result > 0) {
                    $markings_updated += $result;
                }
            }
        }

        return array(
            'success' => true,
            'blocks_updated' => $blocks_updated,
            'markings_updated' => $markings_updated
        );
    }

    /**
     * AJAX: Cleanup legacy markings
     * Deletes all markings with cbd-legacy- IDs from cbd_drawings table
     */
    public function ajax_cleanup_legacy_markings() {
        // Check permissions
        if (!current_user_can('cbd_admin_blocks') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        check_ajax_referer('cbd-migration-nonce', 'nonce');

        global $wpdb;

        // Find all legacy markings
        $legacy_markings = $wpdb->get_results(
            "SELECT id, page_id, container_id, class_id
             FROM " . CBD_TABLE_DRAWINGS . "
             WHERE container_id LIKE 'cbd-legacy-%'",
            ARRAY_A
        );

        $total_found = count($legacy_markings);

        if ($total_found === 0) {
            wp_send_json_success(array(
                'deleted_count' => 0,
                'message' => 'Keine Legacy-Markierungen gefunden.',
                'markings' => array()
            ));
        }

        // Group by page for display
        $pages_affected = array();
        foreach ($legacy_markings as $marking) {
            $page_id = $marking['page_id'];
            if (!isset($pages_affected[$page_id])) {
                $post = get_post($page_id);
                $pages_affected[$page_id] = array(
                    'page_id' => $page_id,
                    'page_title' => $post ? $post->post_title : 'Unbekannt',
                    'count' => 0,
                    'containers' => array()
                );
            }
            $pages_affected[$page_id]['count']++;
            $pages_affected[$page_id]['containers'][] = array(
                'container_id' => $marking['container_id'],
                'class_id' => $marking['class_id']
            );
        }

        // Delete all legacy markings
        $deleted = $wpdb->query(
            "DELETE FROM " . CBD_TABLE_DRAWINGS . "
             WHERE container_id LIKE 'cbd-legacy-%'"
        );

        wp_send_json_success(array(
            'deleted_count' => $deleted !== false ? $deleted : 0,
            'total_found' => $total_found,
            'pages_affected' => array_values($pages_affected),
            'message' => $deleted . ' Legacy-Markierung(en) erfolgreich gelöscht.'
        ));
    }

    /**
     * Add stable ID to a block at a specific path
     */
    private function add_stable_id_to_block(&$blocks, $path, $stable_id) {
        $current = &$blocks;

        foreach ($path as $i => $index) {
            if ($i === count($path) - 1) {
                // Last element - add stableId to attrs
                if (!isset($current[$index]['attrs'])) {
                    $current[$index]['attrs'] = array();
                }
                $current[$index]['attrs']['stableId'] = $stable_id;
            } else {
                // Navigate to inner blocks
                if (isset($current[$index]['innerBlocks'])) {
                    $current = &$current[$index]['innerBlocks'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Get inner content of a block for hashing
     */
    private function get_block_inner_content($block) {
        if (empty($block['innerBlocks'])) {
            return '';
        }

        return serialize_blocks($block['innerBlocks']);
    }
}
