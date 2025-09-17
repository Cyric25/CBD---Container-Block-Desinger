<?php
/**
 * Container Block Designer - User Capabilities Helper
 *
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prüft ob der aktuelle Benutzer Container-Block-Styles bearbeiten kann
 *
 * @return bool True wenn Benutzer Styles bearbeiten kann
 */
function cbd_user_can_edit_styles() {
    // Neue Custom Capability prüfen
    if (current_user_can('cbd_edit_styles')) {
        return true;
    }

    // Fallback: Admins können immer Styles bearbeiten
    if (current_user_can('manage_options')) {
        return true;
    }

    // Fallback: Block-Redakteure können auch Styles bearbeiten
    if (current_user_can('block_redakteur')) {
        return true;
    }

    // Zusätzlich: Prüfe ob Benutzer explizit die Block-Redakteur Rolle hat
    $user = wp_get_current_user();
    if ($user && in_array('block_redakteur', $user->roles)) {
        return true;
    }

    return false;
}

/**
 * Prüft ob der aktuelle Benutzer Container-Blocks verwenden kann
 *
 * @return bool True wenn Benutzer Blocks verwenden kann
 */
function cbd_user_can_use_blocks() {
    // Neue Custom Capability prüfen
    if (current_user_can('cbd_edit_blocks')) {
        return true;
    }

    // Fallback: Block-Redakteure können immer Blocks verwenden
    if (current_user_can('block_redakteur')) {
        return true;
    }

    // Zusätzlich: Prüfe ob Benutzer explizit die Block-Redakteur Rolle hat
    $user = wp_get_current_user();
    if ($user && in_array('block_redakteur', $user->roles)) {
        return true;
    }

    // Fallback: Alle anderen mit edit_posts können Blocks verwenden
    return current_user_can('edit_posts');
}

/**
 * Prüft ob der aktuelle Benutzer Container-Block-Admin-Funktionen verwenden kann
 *
 * @return bool True wenn Benutzer Admin-Funktionen verwenden kann
 */
function cbd_user_can_admin_blocks() {
    // Neue Custom Capability prüfen
    if (current_user_can('cbd_admin_blocks')) {
        return true;
    }

    // Fallback: Nur echte Admins für Import/Export und Settings
    return current_user_can('manage_options');
}