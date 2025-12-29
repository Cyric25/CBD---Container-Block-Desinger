<?php
/**
 * Container Block Designer - Manuelle Migration zu 2.9.0
 *
 * WICHTIG: Dieses Script nach erfolgreicher Ausführung LÖSCHEN!
 *
 * Verwendung:
 * 1. Navigiere im Browser zu: /wp-content/plugins/container-block-designer/run-migration-2-9-0.php
 * 2. Warte auf Bestätigung
 * 3. Lösche diese Datei danach!
 */

// WordPress laden
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

if (!file_exists($wp_load_path)) {
    die('WordPress konnte nicht geladen werden. Bitte Pfad überprüfen.');
}

require_once($wp_load_path);

// Sicherheit: Nur Admins dürfen das ausführen
if (!current_user_can('manage_options')) {
    die('Keine Berechtigung. Bitte als Administrator einloggen.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>CDB Migration 2.9.0</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .success {
            background: #d4f4dd;
            border-left: 4px solid #1e8e3e;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error {
            background: #fce8e6;
            border-left: 4px solid #d33b27;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info {
            background: #e5f5fa;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning {
            background: #fff8e5;
            border-left: 4px solid #f39c12;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        .step {
            margin: 15px 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Container Block Designer - Migration 2.9.0</h1>

        <div class="info">
            <strong>Diese Migration fügt hinzu:</strong>
            <ul>
                <li>Datenbank-Feld <code>is_default</code> zur Tabelle <code>wp_cbd_blocks</code></li>
                <li>Index für bessere Performance</li>
                <li>Unterstützung für Standard-Block-Auswahl</li>
            </ul>
        </div>

        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';

        echo "<h2>📊 Migrations-Status</h2>";

        // Schritt 1: Tabelle existiert?
        echo "<div class='step'>";
        echo "<strong>Schritt 1:</strong> Prüfe ob Tabelle existiert...<br>";

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            echo "<div class='error'>❌ Tabelle <code>$table_name</code> existiert nicht!</div>";
            echo "<p>Bitte installiere das Plugin erst korrekt.</p>";
            echo "</div></div></body></html>";
            exit;
        }

        echo "<div class='success'>✅ Tabelle <code>$table_name</code> gefunden</div>";
        echo "</div>";

        // Schritt 2: Spalten prüfen
        echo "<div class='step'>";
        echo "<strong>Schritt 2:</strong> Prüfe vorhandene Spalten...<br>";

        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        echo "<div class='info'>Gefundene Spalten: " . implode(', ', $columns) . "</div>";
        echo "</div>";

        // Schritt 3: is_default Feld hinzufügen
        echo "<div class='step'>";
        echo "<strong>Schritt 3:</strong> Füge <code>is_default</code> Feld hinzu...<br>";

        if (in_array('is_default', $columns)) {
            echo "<div class='info'>ℹ️ Feld <code>is_default</code> existiert bereits. Migration wurde bereits durchgeführt.</div>";
        } else {
            // Feld hinzufügen
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `is_default` tinyint(1) DEFAULT 0 AFTER `status`");

            if ($result === false) {
                echo "<div class='error'>❌ Fehler beim Hinzufügen des Feldes: " . $wpdb->last_error . "</div>";
            } else {
                echo "<div class='success'>✅ Feld <code>is_default</code> erfolgreich hinzugefügt</div>";
            }
        }
        echo "</div>";

        // Schritt 4: Index hinzufügen
        echo "<div class='step'>";
        echo "<strong>Schritt 4:</strong> Füge Index hinzu...<br>";

        // Prüfe ob Index existiert
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'is_default'");

        if (!empty($indexes)) {
            echo "<div class='info'>ℹ️ Index für <code>is_default</code> existiert bereits.</div>";
        } else {
            $result = $wpdb->query("ALTER TABLE $table_name ADD KEY `is_default` (`is_default`)");

            if ($result === false) {
                echo "<div class='error'>❌ Fehler beim Hinzufügen des Index: " . $wpdb->last_error . "</div>";
            } else {
                echo "<div class='success'>✅ Index erfolgreich hinzugefügt</div>";
            }
        }
        echo "</div>";

        // Schritt 5: DB-Version aktualisieren
        echo "<div class='step'>";
        echo "<strong>Schritt 5:</strong> Aktualisiere Datenbank-Version...<br>";

        update_option('cbd_db_version', '2.9.0');

        echo "<div class='success'>✅ Datenbank-Version auf 2.9.0 gesetzt</div>";
        echo "</div>";

        // Finale Überprüfung
        echo "<h2>✅ Finale Überprüfung</h2>";

        $columns_after = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (in_array('is_default', $columns_after)) {
            echo "<div class='success'>";
            echo "<h3>🎉 Migration erfolgreich abgeschlossen!</h3>";
            echo "<p>Das Feld <code>is_default</code> ist jetzt in der Datenbank vorhanden.</p>";
            echo "<p>Du kannst jetzt Standard-Blocks festlegen.</p>";
            echo "</div>";

            echo "<div class='warning'>";
            echo "⚠️ <strong>WICHTIG:</strong> Bitte lösche diese Datei jetzt:<br>";
            echo "<code>wp-content/plugins/container-block-designer/run-migration-2-9-0.php</code>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "❌ Migration fehlgeschlagen. Bitte kontaktiere Support.";
            echo "</div>";
        }

        // Aktuelle Spalten anzeigen
        echo "<h3>📋 Aktuelle Tabellen-Struktur:</h3>";
        echo "<div class='info'>";
        echo "<strong>Spalten:</strong><br>";
        foreach ($columns_after as $col) {
            echo "• " . esc_html($col) . "<br>";
        }
        echo "</div>";

        // Anzahl Blocks
        $block_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "<div class='info'>";
        echo "<strong>Anzahl Blocks in Datenbank:</strong> " . $block_count;
        echo "</div>";
        ?>

    </div>
</body>
</html>
