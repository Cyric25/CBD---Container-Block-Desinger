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

/**
 * Parst und sanitisiert die Feature-Einstellungen aus $_POST.
 *
 * Einzige Quelle der Wahrheit für Feature-Keys, Felder und Defaults (AP13,
 * VERBESSERUNGSPLAN.md). Vorher existierte dieses Parsing vierfach parallel
 * (class-cbd-admin.php 3x, admin/new-block.php 1x) und driftete auseinander
 * (Ursache des collapse/collapsible-Bugs).
 *
 * Kanonische Keys: icon, collapse, numbering, copyText, screenshot, boardMode.
 *
 * @param array $post Der komplette $_POST-Array
 * @return array Kanonische Feature-Struktur (für wp_json_encode in DB-Spalte features)
 */
/**
 * Sanitisiert den Icon-Wert aus $_POST.
 *
 * Der Wert ist entweder ein Legacy-Dashicons-Klassenname ("dashicons-star")
 * oder ein JSON-Objekt {"type":"custom","value":"kategorien/experimente"}.
 * Beide Formen müssen den Weg in die DB unbeschadet überstehen:
 *
 *   1. wp_unslash() — WordPress slasht $_POST; ohne das wird das JSON ungültig
 *   2. Typ gegen die Whitelist prüfen (CBD_Icon_Library::TYPES)
 *   3. kanonisch neu kodieren, statt den Eingabestring durchzureichen
 *
 * @param mixed $raw Rohwert aus $_POST
 * @return string Speicherfertiger Wert
 */
if (!function_exists('cbd_sanitize_icon_value')) {
    function cbd_sanitize_icon_value($raw) {
        $raw = wp_unslash((string) $raw);

        $decoded = json_decode($raw, true);

        if (is_array($decoded) && isset($decoded['type'], $decoded['value'])) {
            $types = class_exists('CBD_Icon_Library')
                ? CBD_Icon_Library::TYPES
                : array('dashicons', 'fontawesome', 'material', 'lucide', 'custom', 'emoji');

            if (!in_array($decoded['type'], $types, true)) {
                return 'dashicons-admin-generic';
            }

            // Emoji darf nicht durch sanitize_text_field, das würde je nach
            // Zeichen Teile der Sequenz zerlegen; alle anderen Typen schon.
            $value = ('emoji' === $decoded['type'])
                ? wp_kses((string) $decoded['value'], array())
                : sanitize_text_field((string) $decoded['value']);

            if ('' === $value) {
                return 'dashicons-admin-generic';
            }

            return wp_json_encode(array('type' => $decoded['type'], 'value' => $value));
        }

        // Legacy-Pfad: Dashicons-Klassenname
        $class = sanitize_html_class($raw);

        return ('' !== $class) ? $class : 'dashicons-admin-generic';
    }
}

if (!function_exists('cbd_parse_features_from_post')) {
    function cbd_parse_features_from_post($post) {
        $f = (isset($post['features']) && is_array($post['features'])) ? $post['features'] : array();

        return array(
            'icon' => array(
                'enabled' => isset($f['icon']['enabled']),
                // NICHT sanitize_text_field() allein: der Wert ist bei allen
                // Bibliotheken außer Dashicons ein JSON-Objekt, und WordPress
                // versieht $_POST mit Slashes. Ohne wp_unslash() landete
                // {\"type\":\"custom\",…} in der DB -> json_decode scheitert
                // -> Fallback auf einen Dashicon-Klassennamen aus dem rohen
                // JSON -> gar kein Icon sichtbar (Fix v3.1.78).
                'value' => cbd_sanitize_icon_value($f['icon']['value'] ?? 'dashicons-admin-generic'),
                'position' => sanitize_text_field($f['icon']['position'] ?? 'top-left')
            ),
            'collapse' => array(
                'enabled' => isset($f['collapse']['enabled']),
                'defaultState' => sanitize_text_field($f['collapse']['defaultState'] ?? 'expanded')
            ),
            'numbering' => array(
                'enabled' => isset($f['numbering']['enabled']),
                'format' => sanitize_text_field($f['numbering']['format'] ?? 'numeric'),
                'position' => sanitize_text_field($f['numbering']['position'] ?? 'top-left'),
                'countingMode' => sanitize_text_field($f['numbering']['countingMode'] ?? 'same-design')
            ),
            'copyText' => array(
                'enabled' => isset($f['copyText']['enabled']),
                'buttonText' => sanitize_text_field($f['copyText']['buttonText'] ?? 'Text kopieren')
            ),
            'screenshot' => array(
                'enabled' => isset($f['screenshot']['enabled']),
                'buttonText' => sanitize_text_field($f['screenshot']['buttonText'] ?? 'Screenshot')
            ),
            'boardMode' => array(
                'enabled' => isset($f['boardMode']['enabled']),
                'boardColor' => sanitize_hex_color($f['boardMode']['boardColor'] ?? '#ffffff') ?: '#ffffff'
            )
        );
    }
}
