<?php
/**
 * Direct Content Processor
 * Bypasses WordPress block processing for HTML content
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_Direct_Content_Processor {

    /**
     * Process raw content directly from database/post
     */
    public static function process_post_content($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $raw_content = $post->post_content;

        // Find all CBD container blocks in raw content
        if (preg_match_all('/<!-- wp:cbd\/container-block\s*({[^}]*})?\s*-->(.+?)<!-- \/wp:cbd\/container-block -->/s', $raw_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $full_block = $match[0];
                $attributes_json = $match[1] ?? '{}';
                $block_content = $match[2] ?? '';

                // Parse attributes
                $attributes = json_decode($attributes_json, true) ?: array();

                // Process the inner content
                $processed_content = self::process_block_content($block_content, $attributes);

                // Replace in post content
                $raw_content = str_replace($full_block, $processed_content, $raw_content);
            }
        }

        return $raw_content;
    }

    /**
     * Process individual block content
     */
    private static function process_block_content($content, $attributes) {
        // Clean up content - remove WordPress comments
        $content = preg_replace('/<!-- \/wp:[^>]+ -->/s', '', $content);
        $content = preg_replace('/<!-- wp:[^>]+ -->/s', '', $content);
        $content = trim($content);

        // Generate container HTML
        $container_id = 'cbd-direct-' . uniqid();

        $html = '<div class="cbd-container cbd-direct-processed" id="' . esc_attr($container_id) . '">';

        // Add debug info
        $html .= '<!-- DIRECT PROCESSOR USED - BYPASSING WORDPRESS -->';
        $html .= '<!-- CONTENT LENGTH: ' . strlen($content) . ' -->';
        $html .= '<!-- CONTENT TYPE: ' . self::detect_content_type($content) . ' -->';

        // Selection menu
        $html .= '<div class="cbd-selection-menu" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">';
        $html .= '<button class="cbd-menu-toggle" type="button">⚙️ Menu</button>';
        $html .= '<div class="cbd-dropdown-menu" style="position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ccc; min-width: 150px; display: none;">';
        $html .= '<button class="cbd-dropdown-item cbd-collapse-toggle">📁 Einklappen</button>';
        $html .= '<button class="cbd-dropdown-item cbd-copy-text">📋 Kopieren</button>';
        $html .= '</div></div>';

        // Content area
        $html .= '<div class="cbd-content">';
        $html .= '<div class="cbd-container-block">';
        $html .= '<div class="cbd-container-content">';

        // Process content based on type
        $content_type = self::detect_content_type($content);

        switch ($content_type) {
            case 'mixed_html_css_js':
                $html .= $content; // Use as-is for mixed content
                break;

            case 'css_only':
                $html .= '<style>' . $content . '</style>';
                break;

            case 'js_only':
                $html .= '<script>' . $content . '</script>';
                break;

            case 'html_only':
                $html .= $content;
                break;

            default:
                // Try to parse as mixed content
                $html .= self::parse_mixed_content($content);
                break;
        }

        $html .= '</div></div></div></div>';

        return $html;
    }

    /**
     * Detect content type
     */
    private static function detect_content_type($content) {
        $has_html = preg_match('/<[^>]+>/', $content);
        $has_css = preg_match('/\/\*.*?\*\/|\.[\w-]+\s*{/', $content);
        $has_js = preg_match('/function\s+\w+\s*\(|document\.|const\s+|let\s+|var\s+/', $content);

        if ($has_html && $has_css && $has_js) {
            return 'mixed_html_css_js';
        } elseif ($has_css && !$has_html && !$has_js) {
            return 'css_only';
        } elseif ($has_js && !$has_html && !$has_css) {
            return 'js_only';
        } elseif ($has_html) {
            return 'html_only';
        } else {
            return 'unknown';
        }
    }

    /**
     * Parse mixed content (CSS + HTML + JS)
     */
    private static function parse_mixed_content($content) {
        // Split content into CSS, HTML, and JS parts
        $css_pattern = '/\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/|\.[\w-]+[^{]*{[^}]*}/s';
        $js_pattern = '/(?:function\s+\w+\s*\([^)]*\)\s*{[^}]*}|document\.[^;]+;|const\s+[^;]+;|let\s+[^;]+;|var\s+[^;]+;)/s';

        $css_parts = array();
        $js_parts = array();
        $remaining_content = $content;

        // Extract CSS
        if (preg_match_all($css_pattern, $content, $css_matches)) {
            foreach ($css_matches[0] as $css_match) {
                $css_parts[] = $css_match;
                $remaining_content = str_replace($css_match, '', $remaining_content);
            }
        }

        // Extract JS
        if (preg_match_all($js_pattern, $remaining_content, $js_matches)) {
            foreach ($js_matches[0] as $js_match) {
                $js_parts[] = $js_match;
                $remaining_content = str_replace($js_match, '', $remaining_content);
            }
        }

        // Build output
        $output = '';

        // Add CSS
        if (!empty($css_parts)) {
            $output .= '<style>' . implode("\n", $css_parts) . '</style>';
        }

        // Add remaining HTML
        $output .= trim($remaining_content);

        // Add JS
        if (!empty($js_parts)) {
            $output .= '<script>' . implode("\n", $js_parts) . '</script>';
        }

        return $output;
    }

    /**
     * Hook into content filter
     */
    public static function init() {
        add_filter('the_content', array(__CLASS__, 'filter_content'), 999);
    }

    /**
     * Filter the_content to process CBD blocks directly
     */
    public static function filter_content($content) {
        global $post;

        if (!$post || !has_blocks($content)) {
            return $content;
        }

        // Process CBD blocks directly
        if (strpos($content, 'cbd/container-block') !== false || strpos($content, 'container-block-designer/container') !== false) {
            return self::process_post_content($post->ID) ?: $content;
        }

        return $content;
    }
}

// Initialize
CBD_Direct_Content_Processor::init();