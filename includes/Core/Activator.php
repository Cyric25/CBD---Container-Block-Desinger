<?php
/**
 * Container Block Designer - Activator Stub
 * Emergency fix for missing Activator class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dummy Activator class to prevent fatal errors
 */
class CBD_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Plugin activation is handled in main plugin file
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Plugin deactivation is handled in main plugin file
        return true;
    }
}