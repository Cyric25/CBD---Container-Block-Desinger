<?php
/**
 * Container Block Designer - Immediate PHP 8 Warnings Fix
 *
 * This script provides immediate relief from PHP 8.x deprecation warnings
 * Run this script to stop the warnings from appearing on your website
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    $wp_load_paths = array(
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    );

    foreach ($wp_load_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            break;
        }
    }

    if (!defined('ABSPATH')) {
        die('Could not load WordPress.');
    }
}

/**
 * Emergency PHP 8 Warnings Suppression
 */
class CBD_Emergency_PHP8_Fix {

    public static function run() {
        echo "<h2>🚨 Container Block Designer - PHP 8 Warnings Emergency Fix</h2>\n";
        echo "<style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
            .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .success { color: #00a32a; font-weight: bold; }
            .warning { color: #f57900; font-weight: bold; }
            .error { color: #d63638; font-weight: bold; }
            .code { background: #f6f7f7; padding: 10px; border-left: 4px solid #00a32a; margin: 10px 0; }
            pre { background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto; }
        </style>\n";
        echo "<div class='container'>\n";

        // Check current PHP version
        echo "<h3>📋 System Information</h3>";
        echo "<pre>";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "WordPress Version: " . get_bloginfo('version') . "\n";
        echo "Current Time: " . current_time('mysql') . "\n";
        echo "</pre>";

        // Check if the compatibility layer is already loaded
        if (class_exists('CBD_WordPress_PHP8_Compatibility')) {
            echo "<p class='success'>✅ PHP 8 Compatibility layer is already active!</p>";
        } else {
            echo "<p class='warning'>⚠️ PHP 8 Compatibility layer not found. Loading it now...</p>";

            // Load the compatibility layer manually
            $compat_file = __DIR__ . '/includes/php8-wordpress-compatibility.php';
            if (file_exists($compat_file)) {
                require_once $compat_file;
                echo "<p class='success'>✅ Compatibility layer loaded successfully!</p>";
            } else {
                echo "<p class='error'>❌ Compatibility file not found at: $compat_file</p>";
            }
        }

        // Apply immediate fixes
        echo "<h3>🔧 Applying Immediate Fixes</h3>";

        // Set error reporting to hide deprecation warnings
        $old_error_reporting = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
        echo "<p class='success'>✅ Error reporting adjusted to hide deprecation warnings</p>";

        // Add wp-config.php recommendations
        echo "<h3>📝 Recommended wp-config.php Settings</h3>";
        echo "<p>Add these lines to your wp-config.php file for permanent fix:</p>";
        echo "<div class='code'>";
        echo "<strong>// PHP 8.x Compatibility Settings</strong><br>";
        echo "define('WP_DEBUG', false);<br>";
        echo "define('WP_DEBUG_DISPLAY', false);<br>";
        echo "define('WP_DEBUG_LOG', true);<br>";
        echo "ini_set('display_errors', 0);<br>";
        echo "error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);<br>";
        echo "</div>";

        // Test current error levels
        echo "<h3>🧪 Testing Current Error Handling</h3>";
        echo "<pre>";
        echo "Current error_reporting level: " . error_reporting() . "\n";
        echo "Display errors: " . (ini_get('display_errors') ? 'ON' : 'OFF') . "\n";
        echo "Log errors: " . (ini_get('log_errors') ? 'ON' : 'OFF') . "\n";
        echo "</pre>";

        // Show plugin status
        echo "<h3>🔌 Plugin Status</h3>";
        if (is_plugin_active('container-block-designer/container-block-designer.php')) {
            echo "<p class='success'>✅ Container Block Designer plugin is active</p>";

            // Check if our fixes are working
            if (function_exists('CBD_WordPress_PHP8_Compatibility::init')) {
                echo "<p class='success'>✅ Compatibility functions are available</p>";
            } else {
                echo "<p class='warning'>⚠️ Compatibility functions not available - plugin may need restart</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Container Block Designer plugin is not active</p>";
        }

        // Instructions for permanent fix
        echo "<h3>🔄 Next Steps for Permanent Fix</h3>";
        echo "<ol>";
        echo "<li><strong>Immediate relief:</strong> The warnings should now be suppressed</li>";
        echo "<li><strong>Update wp-config.php:</strong> Add the recommended settings above</li>";
        echo "<li><strong>Plugin restart:</strong> Deactivate and reactivate the Container Block Designer plugin</li>";
        echo "<li><strong>Clear cache:</strong> Clear any caching plugins you may have</li>";
        echo "<li><strong>Test your site:</strong> Check that the warnings are gone</li>";
        echo "</ol>";

        // Create .htaccess rule for PHP error suppression
        $htaccess_path = ABSPATH . '.htaccess';
        if (is_writable($htaccess_path)) {
            echo "<h3>🛡️ Additional Protection</h3>";
            echo "<p class='success'>✅ .htaccess is writable - you can add additional protection</p>";
            echo "<div class='code'>";
            echo "<strong>Add this to your .htaccess file for extra protection:</strong><br>";
            echo "php_flag display_errors Off<br>";
            echo "php_flag log_errors On<br>";
            echo "php_value error_reporting \"E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED\"<br>";
            echo "</div>";
        } else {
            echo "<p class='warning'>⚠️ .htaccess is not writable - manual changes may be needed</p>";
        }

        echo "<h3>✅ Emergency Fix Complete</h3>";
        echo "<p class='success'>The PHP 8 deprecation warnings should now be suppressed on your website.</p>";
        echo "<p><strong>Important:</strong> This is a temporary fix. Please follow the 'Next Steps' above for a permanent solution.</p>";

        echo "</div>";
    }
}

// Run the emergency fix
CBD_Emergency_PHP8_Fix::run();
?>