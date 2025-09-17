<?php
/**
 * Debug-Script für Block-Duplizierung
 *
 * Teste die Duplizierungs-Funktion direkt
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

echo "<!DOCTYPE html><html><head><title>CBD Duplicate Test</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f0f0f0;padding:10px;}</style>";
echo "</head><body>";

echo "<h1>🧪 Block-Duplizierung Test</h1>";

$table_name = CBD_TABLE_BLOCKS;

// Zeige aktuelle Blocks
echo "<h2>📋 Aktuelle Blocks in der Datenbank:</h2>";
$blocks = $wpdb->get_results("SELECT id, name, slug, title FROM $table_name ORDER BY id");

if (empty($blocks)) {
    echo "<p class='error'>❌ Keine Blocks gefunden!</p>";
    echo "</body></html>";
    exit;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Title</th><th>Action</th></tr>";
foreach ($blocks as $block) {
    echo "<tr>";
    echo "<td>" . $block->id . "</td>";
    echo "<td>" . esc_html($block->name) . "</td>";
    echo "<td>" . esc_html($block->slug ?? 'NULL') . "</td>";
    echo "<td>" . esc_html($block->title) . "</td>";
    echo "<td><a href='?duplicate=" . $block->id . "'>Duplizieren</a></td>";
    echo "</tr>";
}
echo "</table>";

// Teste Duplizierung
if (isset($_GET['duplicate'])) {
    $block_id = intval($_GET['duplicate']);
    echo "<h2>🔄 Teste Duplizierung von Block ID: $block_id</h2>";

    try {
        $duplicate_id = CBD_Database::duplicate_block($block_id);

        if ($duplicate_id) {
            echo "<p class='success'>✅ Block erfolgreich dupliziert! Neue ID: $duplicate_id</p>";

            // Zeige Details des duplizierten Blocks
            $duplicate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $duplicate_id
            ));

            if ($duplicate) {
                echo "<h3>Details des duplizierten Blocks:</h3>";
                echo "<ul>";
                echo "<li><strong>ID:</strong> " . $duplicate->id . "</li>";
                echo "<li><strong>Name:</strong> " . esc_html($duplicate->name) . "</li>";
                echo "<li><strong>Slug:</strong> " . esc_html($duplicate->slug ?? 'NULL') . "</li>";
                echo "<li><strong>Titel:</strong> " . esc_html($duplicate->title) . "</li>";
                echo "<li><strong>Status:</strong> " . esc_html($duplicate->status) . "</li>";
                echo "<li><strong>Styles:</strong> " . (empty($duplicate->styles) ? '❌ Leer' : '✅ Vorhanden') . "</li>";
                echo "<li><strong>Features:</strong> " . (empty($duplicate->features) ? '❌ Leer' : '✅ Vorhanden') . "</li>";
                echo "</ul>";
            }

            // Prüfe auf Unique-Constraint-Probleme
            echo "<h3>🔍 Unique-Constraint-Prüfung:</h3>";
            $name_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE name = %s",
                $duplicate->name
            ));
            $slug_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE slug = %s",
                $duplicate->slug
            ));

            echo "<p>Name-Duplikate: " . ($name_count > 1 ? "<span class='error'>❌ $name_count</span>" : "<span class='success'>✅ Eindeutig</span>") . "</p>";
            echo "<p>Slug-Duplikate: " . ($slug_count > 1 ? "<span class='error'>❌ $slug_count</span>" : "<span class='success'>✅ Eindeutig</span>") . "</p>";

            echo "<p><a href='?'>🔄 Erneut testen</a></p>";

        } else {
            echo "<p class='error'>❌ Duplizierung fehlgeschlagen!</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Fehler bei Duplizierung: " . esc_html($e->getMessage()) . "</p>";
    }
}

echo "<h2>🧪 Funktions-Tests:</h2>";

// Teste block_name_exists
echo "<h3>block_name_exists() Test:</h3>";
if (!empty($blocks)) {
    $test_name = $blocks[0]->name;
    $exists = CBD_Database::block_name_exists($test_name);
    echo "<p>Name '$test_name' existiert: " . ($exists ? "<span class='success'>✅ Ja</span>" : "<span class='error'>❌ Nein</span>") . "</p>";

    $fake_name = 'non-existent-block-name-' . time();
    $fake_exists = CBD_Database::block_name_exists($fake_name);
    echo "<p>Name '$fake_name' existiert: " . ($fake_exists ? "<span class='error'>❌ Ja (Fehler!)</span>" : "<span class='success'>✅ Nein</span>") . "</p>";
}

// Teste block_slug_exists
echo "<h3>block_slug_exists() Test:</h3>";
if (!empty($blocks)) {
    $test_slug = $blocks[0]->slug ?? $blocks[0]->name;
    $slug_exists = CBD_Database::block_slug_exists($test_slug);
    echo "<p>Slug '$test_slug' existiert: " . ($slug_exists ? "<span class='success'>✅ Ja</span>" : "<span class='error'>❌ Nein</span>") . "</p>";

    $fake_slug = 'non-existent-slug-' . time();
    $fake_slug_exists = CBD_Database::block_slug_exists($fake_slug);
    echo "<p>Slug '$fake_slug' existiert: " . ($fake_slug_exists ? "<span class='error'>❌ Ja (Fehler!)</span>" : "<span class='success'>✅ Nein</span>") . "</p>";
}

echo "<h2>📊 Zusammenfassung:</h2>";
echo "<p class='info'>Dieses Script testet die Block-Duplizierung und prüft auf Unique-Constraint-Probleme.</p>";
echo "<p class='info'>Klicke auf 'Duplizieren' bei einem Block, um die Funktionalität zu testen.</p>";

echo "</body></html>";
?>