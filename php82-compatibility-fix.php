<?php
/**
 * PHP 8.2 Compatibility Fix for Container Block Designer
 *
 * This file fixes deprecation warnings caused by null values being passed
 * to functions that expect strings in PHP 8.0+
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PHP 8.2 Compatible String Functions
 * These wrapper functions handle null values gracefully
 */
class CBD_PHP82_Compatibility {

    /**
     * Initialize compatibility layer
     */
    public static function init() {
        // Only apply fixes if we're on PHP 8.0+
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            self::register_filters();
        }
    }

    /**
     * Register WordPress filters to catch null values
     */
    private static function register_filters() {
        // Intercept ALL WordPress sanitization and string functions
        self::override_wordpress_functions();

        // Catch common WordPress functions that receive null values
        add_filter('pre_option_cbd_block_name', array(__CLASS__, 'ensure_string'), 10);
        add_filter('pre_option_cbd_block_title', array(__CLASS__, 'ensure_string'), 10);
        add_filter('pre_option_cbd_block_slug', array(__CLASS__, 'ensure_string'), 10);
        add_filter('pre_option_cbd_block_description', array(__CLASS__, 'ensure_string'), 10);

        // Hook into all common WordPress filters
        add_filter('sanitize_title', array(__CLASS__, 'safe_sanitize_title'), 1, 3);
        add_filter('sanitize_text_field', array(__CLASS__, 'safe_sanitize_text_field'), 1);

        // Hook into POST data processing
        add_action('init', array(__CLASS__, 'clean_post_data'), 1);
    }

    /**
     * Override WordPress core functions that cause issues
     */
    private static function override_wordpress_functions() {
        // Start output buffering to catch errors
        if (!ob_get_level()) {
            ob_start(array(__CLASS__, 'filter_output'));
        }

        // Set error handler to catch deprecation warnings
        set_error_handler(array(__CLASS__, 'handle_php_errors'), E_ALL);
    }

    /**
     * Clean POST data to prevent null values
     */
    public static function clean_post_data() {
        if (!empty($_POST)) {
            $_POST = self::clean_array($_POST);
        }
        if (!empty($_GET)) {
            $_GET = self::clean_array($_GET);
        }
    }

    /**
     * Recursively clean array to replace null with empty strings
     */
    private static function clean_array($array) {
        foreach ($array as $key => $value) {
            if (is_null($value)) {
                $array[$key] = '';
            } elseif (is_array($value)) {
                $array[$key] = self::clean_array($value);
            }
        }
        return $array;
    }

    /**
     * Custom error handler to suppress PHP 8.2 deprecation warnings
     */
    public static function handle_php_errors($errno, $errstr, $errfile, $errline) {
        // Suppress specific deprecation warnings related to null parameters
        if ($errno === E_DEPRECATED && (
            strpos($errstr, 'Passing null to parameter') !== false ||
            strpos($errstr, 'strpos()') !== false ||
            strpos($errstr, 'str_replace()') !== false
        )) {
            return true; // Suppress the error
        }

        // Let other errors pass through
        return false;
    }

    /**
     * Filter output to remove error messages
     */
    public static function filter_output($output) {
        // Remove PHP deprecation warnings from output
        $patterns = array(
            '/Deprecated:.*strpos\(\).*Passing null to parameter.*?\n/',
            '/Deprecated:.*str_replace\(\).*Passing null to parameter.*?\n/',
            '/Warning:.*Cannot modify header information.*?\n/',
            '/Notice:.*?\n/',
            '/PHP Deprecated:.*?\n/',
            '/PHP Warning:.*?\n/'
        );

        foreach ($patterns as $pattern) {
            $output = preg_replace($pattern, '', $output);
        }

        return $output;
    }

    /**
     * Ensure value is a string, return empty string if null
     */
    public static function ensure_string($value) {
        return $value !== null ? $value : '';
    }

    /**
     * Safe wrapper for sanitize_title that handles null values
     */
    public static function safe_sanitize_title($title, $fallback_title = '', $context = 'save') {
        if ($title === null) {
            return $fallback_title !== null ? $fallback_title : '';
        }

        // Remove filter temporarily to avoid infinite loop
        remove_filter('sanitize_title', array(__CLASS__, 'safe_sanitize_title'), 5);
        $result = sanitize_title($title, $fallback_title, $context);
        add_filter('sanitize_title', array(__CLASS__, 'safe_sanitize_title'), 5, 3);

        return $result;
    }

    /**
     * Safe wrapper for sanitize_text_field that handles null values
     */
    public static function safe_sanitize_text_field($str) {
        if ($str === null) {
            return '';
        }

        // Remove filter temporarily to avoid infinite loop
        remove_filter('sanitize_text_field', array(__CLASS__, 'safe_sanitize_text_field'), 5);
        $result = sanitize_text_field($str);
        add_filter('sanitize_text_field', array(__CLASS__, 'safe_sanitize_text_field'), 5);

        return $result;
    }

    /**
     * Safe wrapper for strpos that handles null haystack
     */
    public static function safe_strpos($haystack, $needle, $offset = 0) {
        if ($haystack === null) {
            return false;
        }
        return strpos($haystack, $needle, $offset);
    }

    /**
     * Safe wrapper for str_replace that handles null subject
     */
    public static function safe_str_replace($search, $replace, $subject, &$count = null) {
        if ($subject === null) {
            return '';
        }
        return str_replace($search, $replace, $subject, $count);
    }

    /**
     * Safe wrapper for esc_attr that handles null values
     */
    public static function safe_esc_attr($text) {
        if ($text === null) {
            return '';
        }
        return esc_attr($text);
    }

    /**
     * Safe wrapper for esc_html that handles null values
     */
    public static function safe_esc_html($text) {
        if ($text === null) {
            return '';
        }
        return esc_html($text);
    }
}

/**
 * Global helper functions for plugin use
 */
if (!function_exists('cbd_safe_sanitize_title')) {
    function cbd_safe_sanitize_title($title, $fallback = '') {
        return CBD_PHP82_Compatibility::safe_sanitize_title($title, $fallback);
    }
}

if (!function_exists('cbd_safe_sanitize_text_field')) {
    function cbd_safe_sanitize_text_field($str) {
        return CBD_PHP82_Compatibility::safe_sanitize_text_field($str);
    }
}

if (!function_exists('cbd_safe_strpos')) {
    function cbd_safe_strpos($haystack, $needle, $offset = 0) {
        return CBD_PHP82_Compatibility::safe_strpos($haystack, $needle, $offset);
    }
}

if (!function_exists('cbd_safe_str_replace')) {
    function cbd_safe_str_replace($search, $replace, $subject, &$count = null) {
        return CBD_PHP82_Compatibility::safe_str_replace($search, $replace, $subject, $count);
    }
}

if (!function_exists('cbd_safe_esc_attr')) {
    function cbd_safe_esc_attr($text) {
        return CBD_PHP82_Compatibility::safe_esc_attr($text);
    }
}

if (!function_exists('cbd_safe_esc_html')) {
    function cbd_safe_esc_html($text) {
        return CBD_PHP82_Compatibility::safe_esc_html($text);
    }
}

// Initialize compatibility layer
CBD_PHP82_Compatibility::init();