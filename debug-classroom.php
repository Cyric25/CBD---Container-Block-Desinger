<?php
/**
 * Debug Script für Classroom System
 * Upload to WordPress root and run: yoursite.com/debug-classroom.php
 */

// Load WordPress
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Zugriff verweigert. Bitte als Administrator anmelden.');
}

echo '<h1>Classroom System Debug</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
</style>';

global $wpdb;

// 1. Check if tables exist
echo '<h2>1. Datenbank-Tabellen prüfen</h2>';
$tables_to_check = array(
    'cbd_classes' => $wpdb->prefix . 'cbd_classes',
    'cbd_class_pages' => $wpdb->prefix . 'cbd_class_pages',
    'cbd_drawings' => $wpdb->prefix . 'cbd_drawings'
);

foreach ($tables_to_check as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "<p class='success'>✓ Tabelle '$table' existiert</p>";

        // Show structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
        echo "<details><summary>Struktur anzeigen</summary><pre>";
        print_r($columns);
        echo "</pre></details>";
    } else {
        echo "<p class='error'>✗ Tabelle '$table' fehlt!</p>";
    }
}

// 2. Check DB version
echo '<h2>2. Datenbank-Version</h2>';
$db_version = get_option('cbd_db_version', 'nicht gesetzt');
echo "<p class='info'>Aktuelle DB-Version: <strong>$db_version</strong></p>";
echo "<p class='info'>Soll-Version: <strong>3.0.0</strong></p>";

// 3. Check if classroom is enabled
echo '<h2>3. Klassen-System Status</h2>';
$classroom_enabled = get_option('cbd_classroom_enabled', 0);
echo $classroom_enabled ?
    "<p class='success'>✓ Klassen-System ist aktiviert</p>" :
    "<p class='error'>✗ Klassen-System ist deaktiviert</p>";

// 4. Check user capabilities
echo '<h2>4. Benutzer-Berechtigungen</h2>';
$user = wp_get_current_user();
echo "<p>Eingeloggt als: <strong>{$user->user_login}</strong> (ID: {$user->ID})</p>";
echo "<p>Rollen: " . implode(', ', $user->roles) . "</p>";

$caps_to_check = array('cbd_edit_blocks', 'cbd_admin_blocks', 'manage_options');
foreach ($caps_to_check as $cap) {
    $has_cap = current_user_can($cap);
    echo $has_cap ?
        "<p class='success'>✓ Hat Capability: $cap</p>" :
        "<p class='error'>✗ Fehlt Capability: $cap</p>";
}

// 5. Check if CBD_Classroom class exists
echo '<h2>5. PHP-Klassen geladen</h2>';
if (class_exists('CBD_Classroom')) {
    echo "<p class='success'>✓ CBD_Classroom Klasse ist geladen</p>";

    // Check if enabled
    if (method_exists('CBD_Classroom', 'is_enabled')) {
        $is_enabled = CBD_Classroom::is_enabled();
        echo $is_enabled ?
            "<p class='success'>✓ CBD_Classroom::is_enabled() = true</p>" :
            "<p class='error'>✗ CBD_Classroom::is_enabled() = false</p>";
    }
} else {
    echo "<p class='error'>✗ CBD_Classroom Klasse nicht gefunden</p>";
}

// 6. Run migration if needed
echo '<h2>6. Migration ausführen</h2>';
if ($db_version !== '3.0.0') {
    echo "<p class='info'>Führe Migration zu Version 3.0.0 aus...</p>";

    if (class_exists('CBD_Schema_Manager')) {
        try {
            // Force migration - Schema Manager verwendet statische Methoden
            // create_tables() ruft intern auch run_migrations() auf
            delete_option('cbd_db_version');
            CBD_Schema_Manager::create_tables();

            $new_version = get_option('cbd_db_version');
            echo "<p class='success'>✓ Migration abgeschlossen. Neue Version: $new_version</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ Fehler: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>✗ CBD_Schema_Manager nicht gefunden</p>";
    }
} else {
    echo "<p class='success'>✓ Datenbank ist bereits auf Version 3.0.0</p>";
}

echo '<hr>';
echo '<p><strong>Debug abgeschlossen.</strong></p>';
echo '<p><a href="/wp-admin/admin.php?page=cbd-classroom">→ Zur Klassen-Verwaltung</a></p>';
