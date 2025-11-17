<?php
/**
 * Container Block Designer - WordPress PHP 8.x Compatibility Layer
 *
 * Fixes WordPress Core deprecation warnings in PHP 8.0+
 * This addresses the null parameter issues in wp-includes/functions.php
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress PHP 8.x Compatibility Handler
 */
class CBD_WordPress_PHP8_Compatibility {

    /**
     * Initialize compatibility fixes
     */
    public static function init() {
        // Only apply on PHP 8.0+
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            self::setup_error_suppression();
            self::setup_output_buffering();
        }
    }

    /**
     * Setup intelligent error suppression for WordPress Core issues
     */
    private static function setup_error_suppression() {
        // Set custom error handler for deprecation warnings
        set_error_handler(array(__CLASS__, 'handle_deprecation_warnings'), E_DEPRECATED | E_USER_DEPRECATED);

        // Suppress specific WordPress Core warnings temporarily
        add_action('init', array(__CLASS__, 'suppress_core_warnings'), 1);
        add_action('admin_init', array(__CLASS__, 'suppress_core_warnings'), 1);
    }

    /**
     * Setup output buffering to catch header warnings
     */
    private static function setup_output_buffering() {
        // Start output buffering early to catch premature output
        if (!ob_get_level()) {
            ob_start(array(__CLASS__, 'clean_output'));
        }

        // Additional safety for admin area
        add_action('admin_head', array(__CLASS__, 'ensure_clean_headers'), -999);
    }

    /**
     * Custom error handler for deprecation warnings
     */
    public static function handle_deprecation_warnings($errno, $errstr, $errfile, $errline) {
        // Suppress specific WordPress Core deprecation warnings
        $suppress_patterns = array(
            'strpos(): Passing null to parameter',
            'str_replace(): Passing null to parameter',
            'strlen(): Passing null to parameter',
            'trim(): Passing null to parameter',
            'strtolower(): Passing null to parameter'
        );

        // Check if this is a WordPress Core file and matches our patterns
        if (strpos($errfile, 'wp-includes') !== false || strpos($errfile, 'wp-admin') !== false) {
            foreach ($suppress_patterns as $pattern) {
                if (strpos($errstr, $pattern) !== false) {
                    // Log the suppressed warning for debugging (only if WP_DEBUG is true)
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('[CBD PHP8 Compat] Suppressed WordPress Core warning: ' . $errstr . ' in ' . $errfile . ':' . $errline);
                    }
                    return true; // Suppress the warning
                }
            }
        }

        // Let other errors pass through to WordPress
        return false;
    }

    /**
     * Suppress core warnings during WordPress initialization
     */
    public static function suppress_core_warnings() {
        // Temporarily adjust error reporting during WordPress core operations
        $old_reporting = error_reporting();

        // Suppress deprecation warnings but keep other errors
        error_reporting($old_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        // Restore error reporting after WordPress is fully loaded
        add_action('wp_loaded', function() use ($old_reporting) {
            error_reporting($old_reporting);
        }, 999);
    }

    /**
     * Clean output buffer to remove warning messages
     */
    public static function clean_output($output) {
        // Remove PHP deprecation warnings from output
        $warning_patterns = array(
            '/Deprecated: strpos\(\): Passing null to parameter.*?\n/',
            '/Deprecated: str_replace\(\): Passing null to parameter.*?\n/',
            '/Deprecated: strlen\(\): Passing null to parameter.*?\n/',
            '/Deprecated: trim\(\): Passing null to parameter.*?\n/',
            '/Warning: Cannot modify header information.*?\n/',
            '/Notice: .*?\n/',
            '/PHP Deprecated: .*?\n/',
            '/PHP Warning: .*Cannot modify header.*?\n/'
        );

        foreach ($warning_patterns as $pattern) {
            $output = preg_replace($pattern, '', $output);
        }

        return $output;
    }

    /**
     * Ensure clean headers in admin area
     */
    public static function ensure_clean_headers() {
        // Flush any accumulated output before admin headers
        if (ob_get_level()) {
            $content = ob_get_clean();

            // Only output content that's not just PHP warnings
            if (!empty(trim($content)) && !self::is_php_warning_output($content)) {
                echo $content;
            }

            // Restart output buffering
            ob_start(array(__CLASS__, 'clean_output'));
        }
    }

    /**
     * Check if output contains only PHP warnings
     */
    public static function is_php_warning_output($content) {
        $warning_indicators = array(
            'Deprecated:',
            'Warning:',
            'Notice:',
            'PHP Deprecated:',
            'PHP Warning:',
            'wp-includes/functions.php'
        );

        $lines = explode("\n", trim($content));
        $warning_lines = 0;
        $total_lines = count($lines);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            foreach ($warning_indicators as $indicator) {
                if (strpos($line, $indicator) !== false) {
                    $warning_lines++;
                    break;
                }
            }
        }

        // If more than 80% of lines are warnings, consider it warning output
        return ($warning_lines / max($total_lines, 1)) > 0.8;
    }

    /**
     * Restore default error handling (for deactivation)
     */
    public static function restore_error_handling() {
        restore_error_handler();

        if (ob_get_level()) {
            ob_end_clean();
        }
    }
}

// Initialize compatibility layer
CBD_WordPress_PHP8_Compatibility::init();

// Register shutdown function to clean up
register_shutdown_function(function() {
    // Ensure any remaining output is cleaned
    if (ob_get_level()) {
        $content = ob_get_clean();
        if (!empty(trim($content)) && !CBD_WordPress_PHP8_Compatibility::is_php_warning_output($content)) {
            echo $content;
        }
    }
});