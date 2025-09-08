<?php
/**
 * Container Block Designer - Global Functions
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get block by ID
 * 
 * @param int $block_id Block ID
 * @return array|null Block data or null if not found
 */
function cbd_get_block($block_id) {
    return CBD_Database::get_block($block_id);
}

/**
 * Get block by name
 * 
 * @param string $block_name Block name
 * @return array|null Block data or null if not found
 */
function cbd_get_block_by_name($block_name) {
    return CBD_Database::get_block_by_name($block_name);
}

/**
 * Get all blocks
 * 
 * @param array $args Query arguments
 * @return array Array of blocks
 */
function cbd_get_blocks($args = array()) {
    return CBD_Database::get_blocks($args);
}

/**
 * Check if current page has container blocks
 * 
 * @return bool True if page has container blocks
 */
function cbd_has_container_blocks() {
    global $post;
    
    if (!$post || !has_blocks($post->post_content)) {
        return false;
    }
    
    $blocks = parse_blocks($post->post_content);
    return cbd_search_for_container_blocks($blocks);
}

/**
 * Search for container blocks recursively
 * 
 * @param array $blocks Blocks to search
 * @return bool True if container blocks found
 */
function cbd_search_for_container_blocks($blocks) {
    foreach ($blocks as $block) {
        if (in_array($block['blockName'], array('container-block-designer/container', 'cbd/container-block'))) {
            return true;
        }
        
        if (!empty($block['innerBlocks'])) {
            if (cbd_search_for_container_blocks($block['innerBlocks'])) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Get plugin version
 * 
 * @return string Plugin version
 */
function cbd_get_version() {
    return defined('CBD_VERSION') ? CBD_VERSION : '2.6.0';
}

/**
 * Get plugin URL
 * 
 * @param string $path Optional path to append
 * @return string Plugin URL
 */
function cbd_get_plugin_url($path = '') {
    $url = defined('CBD_PLUGIN_URL') ? CBD_PLUGIN_URL : plugin_dir_url(__DIR__);
    
    if ($path) {
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');
    }
    
    return $url;
}

/**
 * Get plugin directory path
 * 
 * @param string $path Optional path to append
 * @return string Plugin directory path
 */
function cbd_get_plugin_dir($path = '') {
    $dir = defined('CBD_PLUGIN_DIR') ? CBD_PLUGIN_DIR : plugin_dir_path(__DIR__);
    
    if ($path) {
        $dir = rtrim($dir, '/') . '/' . ltrim($path, '/');
    }
    
    return $dir;
}

/**
 * Log debug message
 * 
 * @param mixed $message Message to log
 * @param string $level Log level (info, warning, error)
 */
function cbd_log($message, $level = 'info') {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $timestamp = current_time('mysql');
    $log_message = sprintf('[%s] CBD %s: %s', $timestamp, strtoupper($level), 
        is_string($message) ? $message : wp_json_encode($message));
    
    error_log($log_message);
}

/**
 * Check if user can manage blocks
 * 
 * @param int $user_id Optional user ID (defaults to current user)
 * @return bool True if user can manage blocks
 */
function cbd_user_can_manage_blocks($user_id = null) {
    if ($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        return user_can($user, 'manage_options');
    }
    
    return current_user_can('manage_options');
}

/**
 * Sanitize block name
 * 
 * @param string $name Block name to sanitize
 * @return string Sanitized block name
 */
function cbd_sanitize_block_name($name) {
    $name = sanitize_text_field($name);
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
    $name = strtolower($name);
    
    return $name;
}

/**
 * Validate JSON string
 * 
 * @param string $json JSON string to validate
 * @return bool True if valid JSON
 */
function cbd_is_valid_json($json) {
    if (!is_string($json)) {
        return false;
    }
    
    json_decode($json);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Get allowed HTML tags for block content
 * 
 * @return array Allowed HTML tags
 */
function cbd_get_allowed_html() {
    $allowed_tags = get_option('cbd_allowed_html_tags', 'div,span,p,h1,h2,h3,h4,h5,h6,a,img,ul,ol,li');
    $tags = array_map('trim', explode(',', $allowed_tags));
    
    $allowed_html = array();
    foreach ($tags as $tag) {
        $allowed_html[$tag] = array(
            'class' => array(),
            'id' => array(),
            'style' => array(),
            'data-*' => array()
        );
        
        // Special attributes for specific tags
        if ($tag === 'a') {
            $allowed_html[$tag]['href'] = array();
            $allowed_html[$tag]['target'] = array();
            $allowed_html[$tag]['rel'] = array();
        }
        
        if ($tag === 'img') {
            $allowed_html[$tag]['src'] = array();
            $allowed_html[$tag]['alt'] = array();
            $allowed_html[$tag]['width'] = array();
            $allowed_html[$tag]['height'] = array();
        }
    }
    
    return $allowed_html;
}

/**
 * Get block render context
 * 
 * @return array Current render context
 */
function cbd_get_render_context() {
    return array(
        'is_admin' => is_admin(),
        'is_editor' => defined('REST_REQUEST') && REST_REQUEST,
        'is_preview' => is_preview(),
        'post_id' => get_the_ID(),
        'user_id' => get_current_user_id()
    );
}

/**
 * Format bytes to human readable format
 * 
 * @param int $size Size in bytes
 * @return string Formatted size
 */
function cbd_format_bytes($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, 2) . ' ' . $units[$i];
}

/**
 * Clear all plugin caches
 */
function cbd_clear_all_caches() {
    // Clear WordPress object cache
    wp_cache_flush();
    
    // Clear transients
    delete_transient('cbd_blocks_cache');
    delete_transient('cbd_styles_cache');
    delete_transient('cbd_features_cache');
    
    // Clear any other plugin-specific caches
    do_action('cbd_clear_caches');
    
    cbd_log('All caches cleared');
}

/**
 * Get system info for debugging
 * 
 * @return array System information
 */
function cbd_get_system_info() {
    global $wpdb;
    
    return array(
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'plugin_version' => cbd_get_version(),
        'mysql_version' => $wpdb->db_version(),
        'active_theme' => get_option('stylesheet'),
        'active_plugins' => get_option('active_plugins'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    );
}