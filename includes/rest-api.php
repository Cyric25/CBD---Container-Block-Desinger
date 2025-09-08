<?php
/**
 * Container Block Designer - REST API (Legacy Wrapper)
 * Now uses API Manager for structured endpoint management
 * Version: 2.6.0
 * 
 * @package ContainerBlockDesigner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load API Manager if not already loaded
if (!class_exists('\ContainerBlockDesigner\API\APIManager')) {
    require_once CBD_PLUGIN_DIR . 'includes/API/class-api-manager.php';
}

// Initialize API Manager
new \ContainerBlockDesigner\API\APIManager();

/**
 * Legacy function for backwards compatibility
 * This function is kept empty as the API Manager handles all routes now
 */
function cbd_register_rest_routes() {
    // This function is now handled by API Manager
    // Kept for backwards compatibility only
    // All REST routes are registered via the APIManager class
}

/**
 * Legacy callback functions for backwards compatibility
 * These functions redirect to the new API controller methods
 */

/**
 * Get all blocks (legacy)
 */
function cbd_rest_get_blocks($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->get_items($request);
}

/**
 * Get single block (legacy)
 */
function cbd_rest_get_block($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->get_item($request);
}

/**
 * Create block (legacy)
 */
function cbd_rest_create_block($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->create_item($request);
}

/**
 * Update block (legacy)
 */
function cbd_rest_update_block($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->update_item($request);
}

/**
 * Delete block (legacy)
 */
function cbd_rest_delete_block($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->delete_item($request);
}

/**
 * Duplicate block (legacy)
 */
function cbd_rest_duplicate_block($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->duplicate_item($request);
}

/**
 * Toggle block status (legacy)
 */
function cbd_rest_toggle_block_status($request) {
    // Create controller instance
    require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
    $controller = new \ContainerBlockDesigner\API\Controllers\BlocksApiController();
    return $controller->toggle_status($request);
}