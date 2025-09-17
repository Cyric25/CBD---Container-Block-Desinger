<?php
/**
 * Sofortige Erstellung der Block-Redakteur Rolle
 * Diese Datei einmal aufrufen, dann löschen
 */

// WordPress laden
require_once('../../../wp-load.php');

// Prüfen ob bereits existiert
if (get_role('block_redakteur')) {
    echo "Rolle 'Block-Redakteur' existiert bereits - wird aktualisiert!<br>";

    // Alte Rolle entfernen
    remove_role('block_redakteur');
    echo "Alte Rolle entfernt.<br>";
}
// Rolle erstellen - MIT CUSTOM CAPABILITIES
$capabilities = array(
    'read' => true,                    // Grundrecht zum Lesen
    'edit_pages' => true,              // Seiten bearbeiten
    'edit_others_pages' => true,       // Fremde Seiten bearbeiten
    'edit_published_pages' => true,    // Veröffentlichte Seiten bearbeiten
    'publish_pages' => true,           // Seiten veröffentlichen
    'delete_pages' => false,           // NICHT löschen
    'delete_others_pages' => false,    // Keine fremden Seiten löschen
    'delete_published_pages' => false, // Keine veröffentlichten Seiten löschen

    // WordPress Editor verwenden (minimal für Block Editor)
    'edit_posts' => true,              // NÖTIG für Block-Editor
    'edit_others_posts' => false,      // Keine fremden Posts
    'edit_published_posts' => false,   // Keine veröffentlichten Posts
    'publish_posts' => false,          // Keine Posts veröffentlichen
    'delete_posts' => false,           // Keine Posts löschen

    // Custom Container Block Designer Capabilities
    'cbd_edit_blocks' => true,         // Container-Blocks im Editor verwenden
    'cbd_edit_styles' => false,        // KEINE Style-Bearbeitung (nur vordefinierte nutzen)
    'cbd_admin_blocks' => false,       // KEINE Admin-Funktionen (Settings/Import/Erstellen)

    // Standard Admin-Rechte
    'manage_options' => false,         // KEINE WordPress Admin-Rechte

    // Upload-Rechte für Medien in Blocks
    'upload_files' => true,

    // WordPress Editor verwenden
    'edit_theme_options' => false,     // Keine Theme-Bearbeitung
);

    // Rolle hinzufügen
    $result = add_role(
        'block_redakteur',
        'Block-Redakteur',
        $capabilities
    );

    if ($result) {
        echo "✅ Rolle 'Block-Redakteur' wurde erfolgreich erstellt!<br>";
        echo "<h3>Capabilities:</h3>";
        echo "<pre>";
        print_r($capabilities);
        echo "</pre>";
    } else {
        echo "❌ Fehler beim Erstellen der Rolle!<br>";
    }

// Administrator und Editor Rollen um Custom Capabilities erweitern
echo "<h3>Erweitere bestehende Rollen:</h3>";

// Administrator erweitern
$admin_role = get_role('administrator');
if ($admin_role) {
    $admin_role->add_cap('cbd_edit_blocks');
    $admin_role->add_cap('cbd_edit_styles');
    $admin_role->add_cap('cbd_admin_blocks');
    echo "✅ Administrator Rolle um Container-Block Capabilities erweitert.<br>";
} else {
    echo "❌ Administrator Rolle nicht gefunden!<br>";
}

// Editor erweitern
$editor_role = get_role('editor');
if ($editor_role) {
    $editor_role->add_cap('cbd_edit_blocks');
    $editor_role->add_cap('cbd_edit_styles');
    // Editor bekommt KEINE Admin-Rechte
    echo "✅ Editor Rolle um Container-Block Capabilities erweitert (ohne Admin-Rechte).<br>";
} else {
    echo "❌ Editor Rolle nicht gefunden!<br>";
}
}

// Alle verfügbaren Rollen anzeigen
echo "<h3>Alle Benutzerrollen:</h3>";
global $wp_roles;
echo "<pre>";
foreach ($wp_roles->roles as $role_name => $role_info) {
    echo "$role_name: " . $role_info['name'] . "\n";
}
echo "</pre>";
?>