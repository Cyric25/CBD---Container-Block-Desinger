<?php
/**
 * REST API for CBD Blocks
 * Provides endpoints to fetch all Container Block Designer blocks
 *
 * @package ContainerBlockDesigner
 * @since 2.8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Blocks_REST_API {

    /**
     * Initialize the REST API routes
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('cbd/v1', '/blocks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_cbd_blocks'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /**
     * Permission check - allow logged-in users with edit_posts capability
     */
    public static function check_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * Get all CBD blocks from all posts/pages
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_cbd_blocks($request) {
        $blocks = [];

        // Query all posts and pages
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        foreach ($posts as $post) {
            // Parse blocks from post content
            $parsed_blocks = parse_blocks($post->post_content);

            // Recursively find CBD blocks
            $cbd_blocks = self::find_cbd_blocks_recursive($parsed_blocks, $post);

            if (!empty($cbd_blocks)) {
                $blocks = array_merge($blocks, $cbd_blocks);
            }
        }

        return new WP_REST_Response($blocks, 200);
    }

    /**
     * Recursively find CBD blocks in parsed block structure
     *
     * @param array $blocks Parsed blocks
     * @param WP_Post $post The post object
     * @return array Found CBD blocks
     */
    private static function find_cbd_blocks_recursive($blocks, $post) {
        $cbd_blocks = [];

        foreach ($blocks as $block) {
            // Check if this is a CBD block
            if (self::is_cbd_block($block)) {
                $cbd_block_data = self::extract_cbd_block_data($block, $post);
                if ($cbd_block_data) {
                    $cbd_blocks[] = $cbd_block_data;
                }
            }

            // Check inner blocks recursively
            if (!empty($block['innerBlocks'])) {
                $inner_cbd_blocks = self::find_cbd_blocks_recursive($block['innerBlocks'], $post);
                $cbd_blocks = array_merge($cbd_blocks, $inner_cbd_blocks);
            }
        }

        return $cbd_blocks;
    }

    /**
     * Check if a block is a CBD block
     *
     * @param array $block Parsed block
     * @return bool
     */
    private static function is_cbd_block($block) {
        // Check if block name starts with 'container-block-designer/'
        return isset($block['blockName']) && strpos($block['blockName'], 'container-block-designer/') === 0;
    }

    /**
     * Extract CBD block data
     *
     * @param array $block Parsed block
     * @param WP_Post $post The post object
     * @return array|null Block data or null
     */
    private static function extract_cbd_block_data($block, $post) {
        $attrs = $block['attrs'] ?? [];

        // Get block title (may be in different attribute names)
        $block_title = $attrs['blockTitle'] ?? $attrs['title'] ?? '';

        // Try to extract from innerHTML if no title attribute
        if (empty($block_title) && !empty($block['innerHTML'])) {
            // Parse HTML to find title in header
            preg_match('/<div[^>]*class="[^"]*cbd-block-title[^"]*"[^>]*>(.*?)<\/div>/is', $block['innerHTML'], $matches);
            if (!empty($matches[1])) {
                $block_title = strip_tags($matches[1]);
            }
        }

        // Use block name as fallback if still no title
        if (empty($block_title)) {
            $block_name_parts = explode('/', $block['blockName']);
            $block_title = end($block_name_parts);
        }

        // Der massgebliche Bezeichner ist die stableId — sie existiert an
        // jedem Container, waehrend ein HTML-Anker optional bleibt.
        $stable_id = self::extract_stable_id($block);

        // Ohne stableId ist der Block nicht adressierbar; er entfaellt.
        if ('' === $stable_id) {
            return null;
        }

        // Legacy-Schluessel `blockId` — bleibt nur aus Rueckwaertskompatibilitaet
        // erhalten. Neue Aufrufer verwenden `stableId`.
        $block_id = $attrs['id'] ?? $attrs['blockId'] ?? '';
        if (empty($block_id) && !empty($block['innerHTML'])) {
            preg_match('/id="([^"]+)"/', $block['innerHTML'], $matches);
            if (!empty($matches[1])) {
                $block_id = $matches[1];
            }
        }

        // HTML-Anker (optional, vom Redakteur gesetzt). render.php baut
        // daraus das Sprungfragment.
        $anchor = isset($attrs['anchor']) ? (string) $attrs['anchor'] : '';

        return [
            'stableId' => $stable_id,
            'anchor' => $anchor,
            'blockId' => $block_id,
            'blockTitle' => $block_title,
            'postId' => $post->ID,
            'postTitle' => $post->post_title,
            'postUrl' => get_permalink($post->ID),
            'blockType' => $block['blockName'],
        ];
    }

    /**
     * Stabilen Bezeichner eines Container-Blocks ermitteln
     *
     * Reihenfolge wie in CBD_Classroom_Gate::block_erlaubt(): zuerst das
     * Blockattribut `stableId`, danach als RUECKFALL FUER ALTBESTAENDE das
     * gespeicherte HTML — aeltere Container tragen die Kennung nur dort.
     *
     * Das HTML wird bewusst ueber WP_HTML_Tag_Processor gelesen statt ueber
     * einen weiteren regulaeren Ausdruck: das Muster
     * `data-stable-id="([^"]+)"` steht bereits an zwei Stellen im Plugin
     * (class-cbd-classroom-gate.php, class-cbd-block-registration.php). Eine
     * dritte Kopie haette dieselbe Regel an drei Orten gepflegt. Fehlt die
     * Klasse (WordPress vor 6.2), entfaellt lediglich der Rueckfall; Bloecke
     * mit Attribut werden weiterhin gefunden.
     *
     * @param array $block Eintrag aus parse_blocks()
     * @return string Stabiler Bezeichner oder '' wenn keiner ermittelbar ist
     */
    private static function extract_stable_id($block) {
        if (!empty($block['attrs']['stableId'])) {
            return (string) $block['attrs']['stableId'];
        }

        $html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
        if ('' === $html && !empty($block['innerContent'])) {
            $html = implode('', array_filter((array) $block['innerContent'], 'is_string'));
        }

        if ('' === $html || !class_exists('WP_HTML_Tag_Processor')) {
            return '';
        }

        $tags = new WP_HTML_Tag_Processor($html);
        while ($tags->next_tag()) {
            $wert = $tags->get_attribute('data-stable-id');
            if (is_string($wert) && '' !== $wert) {
                return $wert;
            }
        }

        return '';
    }
}
