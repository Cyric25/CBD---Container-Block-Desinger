<?php
/**
 * PHP 8.2 Headers Fix for Container Block Designer
 *
 * This file prevents the "headers already sent" error by ensuring
 * no output is generated before headers are sent.
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output Buffer Handler for Header Protection
 */
class CBD_Headers_Fix {

    /**
     * Initialize header protection
     */
    public static function init() {
        // Start output buffering early to catch any premature output
        if (!ob_get_level()) {
            ob_start();
        }

        // Hook into WordPress init to ensure clean start
        add_action('init', array(__CLASS__, 'clean_headers'), -999);
        add_action('admin_init', array(__CLASS__, 'clean_admin_headers'), -999);

        // Clean any error output before headers
        add_action('wp_loaded', array(__CLASS__, 'flush_early_output'), -1);
    }

    /**
     * Clean headers during init
     */
    public static function clean_headers() {
        // Suppress PHP deprecation warnings that cause header issues
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $old_error_reporting = error_reporting();
            error_reporting($old_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

            // Restore after WordPress is fully loaded
            add_action('wp_loaded', function() use ($old_error_reporting) {
                error_reporting($old_error_reporting);
            }, 999);
        }
    }

    /**
     * Clean admin headers
     */
    public static function clean_admin_headers() {
        if (is_admin()) {
            // Flush any accumulated output before admin headers
            if (ob_get_level()) {
                $content = ob_get_clean();
                // Only keep content that's not just whitespace or PHP warnings
                if (!empty(trim($content)) && !self::is_php_warning($content)) {
                    echo $content;
                }
                ob_start();
            }
        }
    }

    /**
     * Flush early output that might interfere with headers
     */
    public static function flush_early_output() {
        if (ob_get_level()) {
            $content = ob_get_clean();
            // Filter out PHP deprecation warnings and empty content
            if (!empty(trim($content)) && !self::is_php_warning($content)) {
                echo $content;
            }
        }
    }

    /**
     * Check if content is a PHP warning/error that should be suppressed
     */
    private static function is_php_warning($content) {
        $warning_patterns = array(
            '/Deprecated:.*strpos\(\).*Passing null to parameter/',
            '/Deprecated:.*str_replace\(\).*Passing null to parameter/',
            '/Warning:.*Cannot modify header information/',
            '/Notice:.*/',
            '/Warning:.*headers already sent/',
            '/PHP Deprecated:.*/',
            '/PHP Notice:.*/',
            '/PHP Warning:.*/'
        );

        foreach ($warning_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}

// Initialize headers fix immediately
CBD_Headers_Fix::init();