<?php
/**
 * DETAILLIERTER Duplizierungs-Test mit vollständiger Fehleranalyse
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

// Plugin-Konstanten
global $wpdb;
if (!defined('CBD_TABLE_BLOCKS')) {
    define('CBD_TABLE_BLOCKS', $wpdb->prefix . 'cbd_blocks');
}

// Plugin-Klassen laden
require_once __DIR__ . '/includes/class-cbd-database.php';

echo "<!DOCTYPE html><html><head><title>CBD Detailed Duplicate Test</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .debug{background:#f5f5f5;padding:15px;margin:10px 0;border-left:5px solid #ccc;} pre{background:#e0e0e0;padding:10px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔍 Detaillierter Duplizierungs-Test</h1>";

$table_name = CBD_TABLE_BLOCKS;

// 1. Prüfe Tabellen-Struktur
echo "<h2>1. 📋 Tabellen-Struktur Validierung</h2>";

$columns_result = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
$columns = array();
foreach ($columns_result as $col) {
    $columns[$col->Field] = $col;
}

$required_columns = array('id', 'name', 'title', 'slug', 'description', 'config', 'styles', 'features', 'status', 'created_at', 'updated_at');
echo "<div class='debug'>";
foreach ($required_columns as $req_col) {
    if (isset($columns[$req_col])) {
        echo "<p class='success'>✅ $req_col: {$columns[$req_col]->Type}</p>";
    } else {
        echo "<p class='error'>❌ $req_col: FEHLT!</p>";
    }
}
echo "</div>";

// 2. Wähle Test-Block
$blocks = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id LIMIT 5");

if (empty($blocks)) {
    echo "<h2>❌ Keine Blocks gefunden!</h2>";
    echo "<p>Erstelle zuerst einen Test-Block:</p>";

    // Erstelle Test-Block
    $test_block_data = array(
        'name' => 'test-block-' . time(),
        'title' => 'Test Block für Duplizierung',
        'slug' => 'test-block-' . time(),
        'description' => 'Ein Test-Block für die Duplizierungs-Funktion',
        'config' => '{"allowInnerBlocks":true}',
        'styles' => '{"padding":{"top":20,"right":20,"bottom":20,"left":20}}',
        'features' => '{"icon":{"enabled":true}}',
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );

    $insert_result = $wpdb->insert($table_name, $test_block_data);
    if ($insert_result) {
        echo "<p class='success'>✅ Test-Block erstellt mit ID: " . $wpdb->insert_id . "</p>";
        echo "<p><a href='?'>🔄 Seite neu laden</a></p>";
    } else {
        echo "<p class='error'>❌ Test-Block konnte nicht erstellt werden: " . $wpdb->last_error . "</p>";
    }
    echo "</body></html>";
    exit;
}

echo "<h2>2. 📋 Verfügbare Test-Blocks</h2>";
echo "<div class='debug'>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Title</th><th>Test Duplizierung</th></tr>";
foreach ($blocks as $block) {
    echo "<tr>";
    echo "<td>" . $block->id . "</td>";
    echo "<td>" . esc_html($block->name) . "</td>";
    echo "<td>" . esc_html($block->slug ?? 'NULL') . "</td>";
    echo "<td>" . esc_html($block->title) . "</td>";
    echo "<td><a href='?test_block_id=" . $block->id . "'>🧪 Teste</a></td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 3. Führe detaillierten Duplizierungs-Test durch
if (isset($_GET['test_block_id'])) {
    $test_block_id = intval($_GET['test_block_id']);
    echo "<h2>3. 🔬 Detaillierter Duplizierungs-Test für Block ID: $test_block_id</h2>";

    // Schritt 1: Original Block laden
    echo "<h3>Schritt 1: Original Block laden</h3>";
    echo "<div class='debug'>";
    $original = CBD_Database::get_block($test_block_id);
    if ($original) {
        echo "<p class='success'>✅ Original Block geladen</p>";
        echo "<pre>" . print_r($original, true) . "</pre>";
    } else {
        echo "<p class='error'>❌ Original Block nicht gefunden</p>";
        echo "</body></html>";
        exit;
    }
    echo "</div>";

    // Schritt 2: Unique Name/Slug Generation testen
    echo "<h3>Schritt 2: Unique Name/Slug Generation Test</h3>";
    echo "<div class='debug'>";

    $base_name = $original['name'] . '_copy';
    $base_slug = ($original['slug'] ?? $original['name']) . '_copy';

    echo "<p>Base Name: $base_name</p>";
    echo "<p>Base Slug: $base_slug</p>";

    $name_exists = CBD_Database::block_name_exists($base_name);
    $slug_exists = CBD_Database::block_slug_exists($base_slug);

    echo "<p>Name '$base_name' existiert: " . ($name_exists ? '<span class="error">❌ Ja</span>' : '<span class="success">✅ Nein</span>') . "</p>";
    echo "<p>Slug '$base_slug' existiert: " . ($slug_exists ? '<span class="error">❌ Ja</span>' : '<span class="success">✅ Nein</span>') . "</p>";

    // Teste Counter-Logic
    $counter = 1;
    $test_name = $base_name;
    $test_slug = $base_slug;

    while (CBD_Database::block_name_exists($test_name) || CBD_Database::block_slug_exists($test_slug)) {
        $counter++;
        $test_name = $original['name'] . '_copy_' . $counter;
        $test_slug = ($original['slug'] ?? $original['name']) . '_copy_' . $counter;

        if ($counter > 10) {
            echo "<p class='error'>❌ Counter-Loop läuft endlos!</p>";
            break;
        }
    }

    echo "<p>Finaler Name nach Counter: $test_name</p>";
    echo "<p>Finaler Slug nach Counter: $test_slug</p>";
    echo "</div>";

    // Schritt 3: Duplizierungs-Daten vorbereiten
    echo "<h3>Schritt 3: Duplizierungs-Daten vorbereiten</h3>";
    echo "<div class='debug'>";

    $duplicate_data = array(
        'name' => $test_name,
        'title' => $original['title'] . ' (Kopie)',
        'slug' => $test_slug,
        'description' => $original['description'],
        'config' => $original['config'],
        'styles' => $original['styles'],
        'features' => $original['features'],
        'status' => 'inactive'
    );

    echo "<p>Duplicate Data:</p>";
    echo "<pre>" . print_r($duplicate_data, true) . "</pre>";
    echo "</div>";

    // Schritt 4: save_block Test
    echo "<h3>Schritt 4: save_block Test</h3>";
    echo "<div class='debug'>";

    try {
        $save_result = CBD_Database::save_block($duplicate_data);

        if ($save_result) {
            echo "<p class='success'>✅ save_block erfolgreich! Neue ID: $save_result</p>";

            // Verifikation: Lade den gespeicherten Block
            $saved_block = CBD_Database::get_block($save_result);
            if ($saved_block) {
                echo "<p class='success'>✅ Gespeicherter Block erfolgreich geladen</p>";
                echo "<pre>" . print_r($saved_block, true) . "</pre>";

                // Cleanup: Lösche Test-Duplikat
                $wpdb->delete($table_name, array('id' => $save_result));
                echo "<p class='info'>🧹 Test-Duplikat aufgeräumt</p>";
            } else {
                echo "<p class='error'>❌ Gespeicherter Block konnte nicht geladen werden</p>";
            }
        } else {
            echo "<p class='error'>❌ save_block fehlgeschlagen</p>";
            echo "<p class='error'>MySQL Error: " . $wpdb->last_error . "</p>";
            echo "<p class='error'>Last Query: " . $wpdb->last_query . "</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception in save_block: " . esc_html($e->getMessage()) . "</p>";
    }
    echo "</div>";

    // Schritt 5: Vollständiger duplicate_block Test
    echo "<h3>Schritt 5: Vollständiger duplicate_block Test</h3>";
    echo "<div class='debug'>";

    try {
        $duplicate_id = CBD_Database::duplicate_block($test_block_id);

        if ($duplicate_id) {
            echo "<p class='success'>✅ duplicate_block erfolgreich! Neue ID: $duplicate_id</p>";

            // Verifikation
            $final_duplicate = CBD_Database::get_block($duplicate_id);
            if ($final_duplicate) {
                echo "<p class='success'>✅ Finales Duplikat erfolgreich geladen</p>";
                echo "<pre>" . print_r($final_duplicate, true) . "</pre>";

                // Cleanup
                $wpdb->delete($table_name, array('id' => $duplicate_id));
                echo "<p class='info'>🧹 Finales Test-Duplikat aufgeräumt</p>";
            }
        } else {
            echo "<p class='error'>❌ duplicate_block fehlgeschlagen</p>";
            echo "<p class='error'>MySQL Error: " . $wpdb->last_error . "</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception in duplicate_block: " . esc_html($e->getMessage()) . "</p>";
    }
    echo "</div>";

    echo "<h3>📊 Test Zusammenfassung</h3>";
    echo "<p><a href='?'>🔄 Neuen Test starten</a></p>";
}

echo "</body></html>";
?>