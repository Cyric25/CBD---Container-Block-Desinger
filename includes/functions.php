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
 * Kanonische Capability-Definition der Rolle "Block-Redakteur".
 * EINZIGE Quelle der Wahrheit – von allen Erstellungs-/Reparaturpfaden genutzt,
 * damit die Rolle unabhängig vom Codepfad immer dieselben Rechte erhält.
 *
 * @return array
 */
if (!function_exists('cbd_block_redakteur_capabilities')) {
    function cbd_block_redakteur_capabilities() {
        return array(
            // Seiten
            'read' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => false,
            'delete_others_pages' => false,
            'delete_published_pages' => false,

            // Beiträge (minimal – nötig für den Block-Editor)
            'edit_posts' => true,
            'edit_others_posts' => false,
            'edit_published_posts' => false,
            'publish_posts' => false,
            'delete_posts' => false,

            // Container Block Designer
            'cbd_edit_blocks' => true,   // Container-Blöcke im Editor verwenden
            'cbd_edit_styles' => false,  // KEINE Style-Bearbeitung
            'cbd_admin_blocks' => false, // KEINE Admin-Funktionen

            // Sonstiges
            'manage_options' => false,
            'upload_files' => true,
            'edit_theme_options' => false,
        );
    }
}

/**
 * Get service from the service container
 *
 * @param string $service_name Service name to retrieve
 * @return mixed|null Service instance or null if not found
 */
if (!function_exists('cbd_get_service')) {
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
}

/**
 * Check if a service exists in the container
 *
 * @param string $service_name Service name to check
 * @return bool True if service exists
 */
if (!function_exists('cbd_has_service')) {
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
}
