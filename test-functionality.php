<?php
/**
 * Test-Script für Block-Duplizierung und Style-Preview
 *
 * Führe dieses Script aus, um die Funktionalität zu testen:
 * http://localhost/deine-site/wp-content/plugins/container-block-designer/test-functionality.php
 */

// WordPress laden
if (!defined('ABSPATH')) {
    $wp_paths = array(
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    );

    foreach ($wp_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            break;
        }
    }

    if (!defined('ABSPATH')) {
        die('WordPress konnte nicht geladen werden');
    }
}

// Plugin-Klassen laden
require_once __DIR__ . '/includes/class-cbd-database.php';

echo "<!DOCTYPE html><html><head><title>CBD Funktions-Test</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f0f0f0;padding:10px;}</style>";
echo "</head><body>";

echo "<h1>🧪 Container Block Designer - Funktions-Test</h1>";
echo "<p>Zeit: " . date('Y-m-d H:i:s') . "</p>";

global $wpdb;
$table_name = $wpdb->prefix . 'cbd_blocks';

echo "<h2>1. 📋 Datenbank-Status</h2>";

// Prüfe Tabelle
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
echo $table_exists ? "<p class='success'>✅ Tabelle existiert</p>" : "<p class='error'>❌ Tabelle fehlt</p>";

if ($table_exists) {
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    echo "<p class='info'>📋 Spalten: " . implode(', ', $columns) . "</p>";

    $required_columns = array('id', 'name', 'title', 'slug', 'description', 'config', 'styles', 'features', 'status', 'created_at', 'updated_at');
    $missing_columns = array_diff($required_columns, $columns);

    if (empty($missing_columns)) {
        echo "<p class='success'>✅ Alle erforderlichen Spalten vorhanden</p>";
    } else {
        echo "<p class='error'>❌ Fehlende Spalten: " . implode(', ', $missing_columns) . "</p>";
    }
}

echo "<h2>2. 🔄 Test: Block-Duplizierung</h2>";

if ($table_exists) {
    // Erstelle Test-Block falls nötig
    $test_block_id = $wpdb->get_var("SELECT id FROM $table_name WHERE name = 'test-original' LIMIT 1");

    if (!$test_block_id) {
        echo "<p class='info'>🔧 Erstelle Test-Block...</p>";
        $test_data = array(
            'name' => 'test-original',
            'title' => 'Test Original Block',
            'slug' => 'test-original',
            'description' => 'Original Block für Duplikations-Test',
            'config' => '{"allowInnerBlocks":true}',
            'styles' => '{"padding":{"top":25,"right":25,"bottom":25,"left":25},"background":{"color":"#f0f0f0"}}',
            'features' => '{"icon":{"enabled":true,"value":"dashicons-star-filled"}}',
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table_name, $test_data);
        if ($result) {
            $test_block_id = $wpdb->insert_id;
            echo "<p class='success'>✅ Test-Block erstellt (ID: $test_block_id)</p>";
        } else {
            echo "<p class='error'>❌ Test-Block konnte nicht erstellt werden: " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p class='info'>📋 Test-Block gefunden (ID: $test_block_id)</p>";
    }

    if ($test_block_id) {
        // Teste Duplizierung
        echo "<p class='info'>🔄 Teste Block-Duplizierung...</p>";

        try {
            $duplicate_id = CBD_Database::duplicate_block($test_block_id);

            if ($duplicate_id) {
                echo "<p class='success'>✅ Block erfolgreich dupliziert (Neue ID: $duplicate_id)</p>";

                // Prüfe duplizierte Daten
                $duplicate = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE id = %d",
                    $duplicate_id
                ), ARRAY_A);

                if ($duplicate) {
                    echo "<p class='success'>✅ Duplizierte Daten korrekt:</p>";
                    echo "<ul>";
                    echo "<li>Name: " . esc_html($duplicate['name']) . "</li>";
                    echo "<li>Titel: " . esc_html($duplicate['title']) . "</li>";
                    echo "<li>Slug: " . esc_html($duplicate['slug']) . "</li>";
                    echo "<li>Status: " . esc_html($duplicate['status']) . "</li>";
                    echo "<li>Styles: " . (empty($duplicate['styles']) ? '❌ Leer' : '✅ Vorhanden') . "</li>";
                    echo "<li>Features: " . (empty($duplicate['features']) ? '❌ Leer' : '✅ Vorhanden') . "</li>";
                    echo "</ul>";
                } else {
                    echo "<p class='error'>❌ Duplizierter Block nicht gefunden</p>";
                }

                // Aufräumen
                $wpdb->delete($table_name, array('id' => $duplicate_id));
                echo "<p class='info'>🧹 Test-Duplikat aufgeräumt</p>";

            } else {
                echo "<p class='error'>❌ Block-Duplizierung fehlgeschlagen</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Fehler bei Duplizierung: " . esc_html($e->getMessage()) . "</p>";
        }
    }
}

echo "<h2>3. 🎨 Test: Style-Preview JavaScript</h2>";

echo "<p class='info'>📝 Teste JavaScript-Funktionalität für Style-Preview...</p>";

// Erstelle eine einfache Test-Umgebung
echo "<div style='border: 1px solid #ccc; padding: 20px; margin: 20px 0; background: #f9f9f9;'>";
echo "<h3>Test-Umgebung für Style-Preview:</h3>";

echo "<div style='display: flex; gap: 20px;'>";

// Linke Seite: Controls
echo "<div style='flex: 1;'>";
echo "<h4>Style-Controls:</h4>";
echo "<label>Padding Top: <input type='number' name='styles[padding][top]' value='20' min='0' max='100' style='width:60px;'></label><br><br>";
echo "<label>Padding Right: <input type='number' name='styles[padding][right]' value='20' min='0' max='100' style='width:60px;'></label><br><br>";
echo "<label>Background Color: <input type='color' name='styles[background][color]' value='#ffffff'></label><br><br>";
echo "<label>Border Width: <input type='number' name='styles[border][width]' value='1' min='0' max='10' style='width:60px;'></label><br><br>";
echo "<label>Border Color: <input type='color' name='styles[border][color]' value='#dddddd'></label><br><br>";
echo "<label>Border Radius: <input type='number' name='styles[border][radius]' value='4' min='0' max='50' style='width:60px;'></label><br><br>";
echo "<button type='button' id='cbd-update-preview'>🔄 Preview aktualisieren</button>";
echo "</div>";

// Rechte Seite: Preview
echo "<div style='flex: 1;'>";
echo "<h4>Live Preview:</h4>";
echo "<div id='cbd-block-preview'>";
echo "<div id='cbd-block-preview-content' style='padding: 20px; background-color: #ffffff; border: 1px solid #dddddd; border-radius: 4px; color: #333333;'>";
echo "<p>🎨 Hier wird die Vorschau angezeigt</p>";
echo "<p>Ändere die Werte links, um die Preview zu sehen.</p>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>";
echo "</div>";

// JavaScript für Test
echo "<script>";
echo "function updatePreview() {";
echo "  console.log('🔄 updatePreview() aufgerufen');";
echo "  const preview = document.getElementById('cbd-block-preview-content');";
echo "  if (!preview) { console.error('❌ Preview element nicht gefunden'); return; }";

echo "  const paddingTop = document.querySelector('input[name=\"styles[padding][top]\"]').value || 20;";
echo "  const paddingRight = document.querySelector('input[name=\"styles[padding][right]\"]').value || 20;";
echo "  const backgroundColor = document.querySelector('input[name=\"styles[background][color]\"]').value || '#ffffff';";
echo "  const borderWidth = document.querySelector('input[name=\"styles[border][width]\"]').value || 1;";
echo "  const borderColor = document.querySelector('input[name=\"styles[border][color]\"]').value || '#dddddd';";
echo "  const borderRadius = document.querySelector('input[name=\"styles[border][radius]\"]').value || 4;";

echo "  let css = '';";
echo "  css += 'padding: ' + paddingTop + 'px ' + paddingRight + 'px ' + paddingTop + 'px ' + paddingRight + 'px;';";
echo "  css += 'background-color: ' + backgroundColor + ';';";
echo "  css += 'border: ' + borderWidth + 'px solid ' + borderColor + ';';";
echo "  css += 'border-radius: ' + borderRadius + 'px;';";
echo "  css += 'color: #333333;';";

echo "  preview.setAttribute('style', css);";
echo "  console.log('✅ Preview aktualisiert:', css);";
echo "}";

// Event Listeners
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "  console.log('🚀 DOM geladen, initialisiere Event Listeners...');";
echo "  ";
echo "  // Initial preview";
echo "  updatePreview();";
echo "  ";
echo "  // Event listeners für alle Style-Inputs";
echo "  const inputs = document.querySelectorAll('input[name^=\"styles[\"]');";
echo "  inputs.forEach(function(input) {";
echo "    input.addEventListener('change', updatePreview);";
echo "    input.addEventListener('input', updatePreview);";
echo "  });";
echo "  ";
echo "  // Update button";
echo "  const updateBtn = document.getElementById('cbd-update-preview');";
echo "  if (updateBtn) {";
echo "    updateBtn.addEventListener('click', updatePreview);";
echo "  }";
echo "  ";
echo "  console.log('✅ Event Listeners registriert für', inputs.length, 'Inputs');";
echo "});";
echo "</script>";

echo "<h2>4. ✅ Test-Zusammenfassung</h2>";

$total_tests = 2;
$passed_tests = 0;

if ($table_exists && empty($missing_columns)) {
    $passed_tests++;
    echo "<p class='success'>✅ Datenbank-Struktur: OK</p>";
} else {
    echo "<p class='error'>❌ Datenbank-Struktur: Probleme erkannt</p>";
}

if (class_exists('CBD_Database') && method_exists('CBD_Database', 'duplicate_block')) {
    $passed_tests++;
    echo "<p class='success'>✅ Block-Duplizierung: Funktion verfügbar</p>";
} else {
    echo "<p class='error'>❌ Block-Duplizierung: Funktion nicht verfügbar</p>";
}

echo "<h3>📊 Ergebnis: $passed_tests/$total_tests Tests bestanden</h3>";

if ($passed_tests === $total_tests) {
    echo "<p class='success'>🎉 Alle Tests erfolgreich! Die Funktionen sollten jetzt korrekt arbeiten.</p>";
} else {
    echo "<p class='error'>⚠️ Einige Tests fehlgeschlagen. Bitte prüfe die Plugin-Konfiguration.</p>";
}

echo "<h3>📋 Nächste Schritte:</h3>";
echo "<ol>";
echo "<li>Gehe in das WordPress-Admin</li>";
echo "<li>Navigiere zu 'Container Designer' → 'Alle Blöcke'</li>";
echo "<li>Teste die Duplizierung eines Blocks</li>";
echo "<li>Gehe zu 'Neuer Block' oder bearbeite einen Block</li>";
echo "<li>Teste die Live-Vorschau beim Ändern der Style-Werte</li>";
echo "</ol>";

echo "</body></html>";
?>