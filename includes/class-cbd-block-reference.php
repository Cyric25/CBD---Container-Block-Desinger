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
     * Handle des Editor-Scripts
     *
     * Dasselbe Handle steht in blocks/block-reference/block.json unter
     * `editorScript`. Beide Stellen muessen zusammen geaendert werden.
     */
    const EDITOR_HANDLE = 'cbd-block-reference-editor';

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

        // Editor-Script VOR der Blockregistrierung anmelden — block.json
        // verweist nur noch auf das Handle.
        self::register_editor_script();

        // Register block from block.json
        register_block_type($block_json_path, [
            'render_callback' => [__CLASS__, 'render_block'],
        ]);
    }

    /**
     * Editor-Script von Hand registrieren
     *
     * Das Plugin hat KEINEN Build-Schritt, also gibt es keine
     * index.asset.php. Wuerde block.json weiterhin `file:./index.js`
     * angeben, registrierte WordPress das Script ohne Abhaengigkeiten und
     * `wp.blocks` waere beim Ausfuehren womoeglich noch nicht geladen.
     * Muster uebernommen von `cbd-block-editor` in
     * CBD_Block_Registration::enqueue_block_editor_assets().
     */
    public static function register_editor_script() {
        if (wp_script_is(self::EDITOR_HANDLE, 'registered')) {
            return;
        }

        $relativ = 'blocks/block-reference/index.js';
        $pfad = CBD_PLUGIN_DIR . $relativ;

        if (!file_exists($pfad)) {
            return;
        }

        // Cache-Busting ueber filemtime(): Die Datei aendert sich auch
        // zwischen zwei Plugin-Versionen, CBD_VERSION allein genuegt nicht.
        $version = defined('CBD_VERSION') ? CBD_VERSION : '';
        $mtime = filemtime($pfad);
        if ($mtime) {
            $version = $version . '.' . $mtime;
        }

        wp_register_script(
            self::EDITOR_HANDLE,
            CBD_PLUGIN_URL . $relativ,
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch'),
            $version,
            true
        );
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
