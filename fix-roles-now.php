<?php
/**
 * Sofortige Reparatur der Block-Redakteur Rolle
 * Diese Datei einmal ausführen, dann kann sie gelöscht werden
 */

// WordPress laden
require_once('../../../wp-load.php');

echo "<h1>🔧 Container Block Designer - Rollen Reparatur</h1>";

// Aktueller Status prüfen
$current_user = wp_get_current_user();
echo "<h2>Aktueller Benutzer</h2>";
echo "<p><strong>ID:</strong> " . $current_user->ID . "</p>";
echo "<p><strong>Login:</strong> " . $current_user->user_login . "</p>";
echo "<p><strong>Rollen:</strong> " . implode(', ', $current_user->roles) . "</p>";

// Prüfen ob Block-Redakteur Rolle existiert
$block_redakteur_role = get_role('block_redakteur');
if ($block_redakteur_role) {
    echo "<p style='color: orange;'>⚠️ Block-Redakteur Rolle existiert bereits</p>";

    // Prüfe Capabilities
    $has_cbd_edit = $block_redakteur_role->has_cap('cbd_edit_blocks');
    echo "<p>cbd_edit_blocks Capability: " . ($has_cbd_edit ? 'JA' : 'NEIN') . "</p>";

    if (!$has_cbd_edit) {
        $block_redakteur_role->add_cap('cbd_edit_blocks');
        echo "<p style='color: green;'>✅ cbd_edit_blocks Capability hinzugefügt</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Block-Redakteur Rolle existiert NICHT - erstelle sie jetzt...</p>";

    // Block-Redakteur Rolle erstellen
    $capabilities = array(
        'read' => true,
        'edit_pages' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'publish_pages' => true,
        'edit_posts' => true,
        'upload_files' => true,
        'cbd_edit_blocks' => true,         // WICHTIG: Hauptcapability für Menü
        'cbd_edit_styles' => false,
        'cbd_admin_blocks' => false,
        'manage_options' => false,
        'edit_theme_options' => false,
    );

    $result = add_role(
        'block_redakteur',
        'Block-Redakteur',
        $capabilities
    );

    if ($result) {
        echo "<p style='color: green;'>✅ Block-Redakteur Rolle wurde erfolgreich erstellt!</p>";
    } else {
        echo "<p style='color: red;'>❌ Fehler beim Erstellen der Rolle!</p>";
    }
}

// Administrator und Editor Rollen erweitern
echo "<h2>Erweitere bestehende Rollen</h2>";

$admin_role = get_role('administrator');
if ($admin_role) {
    $admin_role->add_cap('cbd_edit_blocks');
    $admin_role->add_cap('cbd_edit_styles');
    $admin_role->add_cap('cbd_admin_blocks');
    echo "<p>✅ Administrator Rolle erweitert</p>";
}

$editor_role = get_role('editor');
if ($editor_role) {
    $editor_role->add_cap('cbd_edit_blocks');
    $editor_role->add_cap('cbd_edit_styles');
    echo "<p>✅ Editor Rolle erweitert</p>";
}

// Benutzer zur Block-Redakteur Rolle hinzufügen (falls gewünscht)
if (isset($_GET['add_current_user'])) {
    $current_user->add_role('block_redakteur');
    echo "<p style='color: green;'>✅ Aktuelle Benutzer wurde zur Block-Redakteur Rolle hinzugefügt!</p>";
    echo "<p><strong>WICHTIG:</strong> Melden Sie sich ab und wieder an, damit die Änderungen wirksam werden!</p>";
}

// Capability Tests
echo "<h2>Capability Tests</h2>";
echo "<p><strong>current_user_can('cbd_edit_blocks'):</strong> " . (current_user_can('cbd_edit_blocks') ? 'JA' : 'NEIN') . "</p>";
echo "<p><strong>current_user_can('cbd_admin_blocks'):</strong> " . (current_user_can('cbd_admin_blocks') ? 'JA' : 'NEIN') . "</p>";

// Alle Rollen anzeigen
echo "<h2>Alle Benutzerrollen</h2>";
global $wp_roles;
foreach ($wp_roles->roles as $role_name => $role_info) {
    echo "<h3>$role_name: " . $role_info['name'] . "</h3>";

    $cbd_caps = array_filter($role_info['capabilities'], function($key) {
        return strpos($key, 'cbd_') === 0;
    }, ARRAY_FILTER_USE_KEY);

    if (!empty($cbd_caps)) {
        echo "<ul>";
        foreach ($cbd_caps as $cap => $has_cap) {
            echo "<li>$cap: " . ($has_cap ? 'JA' : 'NEIN') . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p><em>Keine CBD Capabilities</em></p>";
    }
}

// Aktionen
echo "<h2>Aktionen</h2>";
if (!in_array('block_redakteur', $current_user->roles)) {
    echo "<p><a href='?add_current_user=1' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>👤 Aktuellen Benutzer zu Block-Redakteur machen</a></p>";
}

echo "<p><a href='?' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>🔄 Seite neu laden</a></p>";

echo "<h3>Fertig!</h3>";
echo "<p>Gehen Sie jetzt zum WordPress Admin zurück und prüfen Sie, ob das Menü sichtbar ist.</p>";
?>