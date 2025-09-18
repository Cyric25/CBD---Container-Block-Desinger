<?php
/**
 * Debug Script für Benutzerrollen
 * Temporäres Script zum Testen der Rollen-Erstellung
 */

// WordPress laden
require_once('../../../wp-load.php');

echo "<h1>Container Block Designer - Rollen Debug</h1>";

// Aktueller Benutzer
$current_user = wp_get_current_user();
echo "<h2>Aktueller Benutzer</h2>";
echo "<p><strong>ID:</strong> " . $current_user->ID . "</p>";
echo "<p><strong>Login:</strong> " . $current_user->user_login . "</p>";
echo "<p><strong>Rollen:</strong> " . implode(', ', $current_user->roles) . "</p>";

// Capabilities prüfen
echo "<h2>Capabilities</h2>";
echo "<p><strong>cbd_edit_blocks:</strong> " . (current_user_can('cbd_edit_blocks') ? 'JA' : 'NEIN') . "</p>";
echo "<p><strong>cbd_admin_blocks:</strong> " . (current_user_can('cbd_admin_blocks') ? 'JA' : 'NEIN') . "</p>";
echo "<p><strong>manage_options:</strong> " . (current_user_can('manage_options') ? 'JA' : 'NEIN') . "</p>";

// Alle verfügbaren Rollen anzeigen
echo "<h2>Alle Benutzerrollen im System</h2>";
global $wp_roles;
foreach ($wp_roles->roles as $role_name => $role_info) {
    echo "<h3>$role_name: " . $role_info['name'] . "</h3>";

    $capabilities = $role_info['capabilities'];
    $cbd_caps = array_filter($capabilities, function($key) {
        return strpos($key, 'cbd_') === 0;
    }, ARRAY_FILTER_USE_KEY);

    if (!empty($cbd_caps)) {
        echo "<p><strong>Container Block Capabilities:</strong></p>";
        echo "<ul>";
        foreach ($cbd_caps as $cap => $has_cap) {
            echo "<li>$cap: " . ($has_cap ? 'JA' : 'NEIN') . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p><em>Keine Container Block Capabilities</em></p>";
    }
}

// Rollen reparieren
if (isset($_GET['fix_roles'])) {
    echo "<h2>Repariere Rollen...</h2>";

    // Rolle erstellen/aktualisieren
    include_once('install.php');
    $result = cbd_create_user_roles();

    if ($result) {
        echo "<p style='color: green;'>✅ Block-Redakteur Rolle wurde erfolgreich erstellt/aktualisiert!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Rolle existierte bereits oder wurde aktualisiert.</p>";
    }

    echo "<p><a href='?'>Neu laden ohne Reparatur</a></p>";
} else {
    echo "<p><a href='?fix_roles=1' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>🔧 Rollen reparieren</a></p>";
}

// Block-Redakteur Rolle speziell prüfen
$block_redakteur_role = get_role('block_redakteur');
if ($block_redakteur_role) {
    echo "<h2>Block-Redakteur Rolle Details</h2>";
    echo "<p><strong>Rolle existiert:</strong> JA</p>";
    echo "<p><strong>Capabilities:</strong></p>";
    echo "<ul>";
    foreach ($block_redakteur_role->capabilities as $cap => $has_cap) {
        echo "<li>$cap: " . ($has_cap ? 'JA' : 'NEIN') . "</li>";
    }
    echo "</ul>";
} else {
    echo "<h2 style='color: red;'>❌ Block-Redakteur Rolle existiert NICHT!</h2>";
}
?>