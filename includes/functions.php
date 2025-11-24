<?php
/**
 * Global Helper Functions for Container Block Designer
 *
 * @package ContainerBlockDesigner
 * @since 2.8.3
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get service from the service container
 *
 * @param string $service_name Service name to retrieve
 * @return mixed|null Service instance or null if not found
 */
function cbd_get_service($service_name) {
    $plugin = ContainerBlockDesigner::get_instance();
    if ($plugin && method_exists($plugin, 'get_container')) {
        $container = $plugin->get_container();
        if ($container && method_exists($container, 'get')) {
            return $container->get($service_name);
        }
    }
    return null;
}

/**
 * Check if a service exists in the container
 *
 * @param string $service_name Service name to check
 * @return bool True if service exists
 */
function cbd_has_service($service_name) {
    $plugin = ContainerBlockDesigner::get_instance();
    if ($plugin && method_exists($plugin, 'get_container')) {
        $container = $plugin->get_container();
        if ($container && method_exists($container, 'has')) {
            return $container->has($service_name);
        }
    }
    return false;
}
