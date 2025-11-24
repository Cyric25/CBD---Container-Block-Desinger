<?php
/**
 * CBD Block Reference
 * Registers and handles the block-reference block
 *
 * @package ContainerBlockDesigner
 * @since 2.8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Reference {

    /**
     * Initialize the block
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_block']);
    }

    /**
     * Register the block-reference block
     */
    public static function register_block() {
        // Check if block.json exists
        $block_json_path = CBD_PLUGIN_DIR . 'blocks/block-reference/block.json';

        if (!file_exists($block_json_path)) {
            return;
        }

        // Register block from block.json
        register_block_type($block_json_path, [
            'render_callback' => [__CLASS__, 'render_block'],
        ]);
    }

    /**
     * Render the block on the frontend
     *
     * @param array $attributes Block attributes
     * @param string $content Block inner content
     * @param WP_Block $block Block instance
     * @return string Rendered HTML
     */
    public static function render_block($attributes, $content, $block) {
        // Include the render template
        $render_file = CBD_PLUGIN_DIR . 'blocks/block-reference/render.php';

        if (!file_exists($render_file)) {
            return '';
        }

        // Start output buffering
        ob_start();

        // Include the template
        include $render_file;

        // Return the buffered content
        return ob_get_clean();
    }
}
