<?php
/**
 * ERWEITERTE Debug-Analyse für Duplizierungs-Problem
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

echo "<!DOCTYPE html><html><head><title>CBD Duplicate Debug</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .debug{background:#f0f0f0;padding:10px;margin:10px 0;} pre{background:#e0e0e0;padding:10px;}</style>";
echo "</head><body>";

echo "<h1>🔍 Erweiterte Duplizierungs-Debug-Analyse</h1>";

$table_name = CBD_TABLE_BLOCKS;

// 1. Prüfe Tabellen-Struktur
echo "<h2>1. 📋 Tabellen-Struktur</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo "<p class='error'>❌ Tabelle $table_name existiert nicht!</p>";
    echo "</body></html>";
    exit;
}

$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
echo "<div class='debug'>";
echo "<h3>Spalten:</h3>";
foreach ($columns as $column) {
    $key_info = '';
    if ($column->Key === 'PRI') $key_info = ' (PRIMARY KEY)';
    if ($column->Key === 'UNI') $key_info = ' (UNIQUE)';
    echo "<p>• {$column->Field}: {$column->Type}{$key_info}</p>";
}
echo "</div>";

// 2. Prüfe Indizes
echo "<h2>2. 🔑 Indizes und Constraints</h2>";
$indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
echo "<div class='debug'>";
foreach ($indexes as $index) {
    echo "<p>• Index: {$index->Key_name} -> {$index->Column_name} " . ($index->Non_unique == 0 ? '(UNIQUE)' : '') . "</p>";
}
echo "</div>";

// 3. Aktuelle Blocks anzeigen
echo "<h2>3. 📋 Aktuelle Blocks</h2>";
$blocks = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id");

if (empty($blocks)) {
    echo "<p class='error'>❌ Keine Blocks gefunden!</p>";
} else {
    echo "<div class='debug'>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Title</th><th>Status</th><th>Created</th></tr>";
    foreach ($blocks as $block) {
        echo "<tr>";
        echo "<td>" . $block->id . "</td>";
        echo "<td>" . esc_html($block->name ?? 'NULL') . "</td>";
        echo "<td>" . esc_html($block->slug ?? 'NULL') . "</td>";
        echo "<td>" . esc_html($block->title ?? 'NULL') . "</td>";
        echo "<td>" . esc_html($block->status ?? 'NULL') . "</td>";
        echo "<td>" . esc_html($block->created_at ?? $block->created ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

// 4. Teste die Admin-URL und Nonce
if (!empty($blocks)) {
    $test_block = $blocks[0];
    echo "<h2>4. 🔗 Admin-URL Test</h2>";

    $nonce_action = 'cbd_duplicate_block_' . $test_block->id;
    $nonce = wp_create_nonce($nonce_action);
    $duplicate_url = admin_url('admin.php?page=container-block-designer&action=duplicate&block_id=' . $test_block->id . '&_wpnonce=' . $nonce);

    echo "<div class='debug'>";
    echo "<p><strong>Test-Block ID:</strong> " . $test_block->id . "</p>";
    echo "<p><strong>Nonce Action:</strong> " . $nonce_action . "</p>";
    echo "<p><strong>Generated Nonce:</strong> " . $nonce . "</p>";
    echo "<p><strong>Duplicate URL:</strong></p>";
    echo "<code style='word-break: break-all;'>" . esc_html($duplicate_url) . "</code>";
    echo "<p><a href='" . esc_url($duplicate_url) . "' target='_blank'>🔗 URL testen</a></p>";
    echo "</div>";
}

// 5. Teste die CBD_Database Klasse
echo "<h2>5. 🧪 CBD_Database Klasse Test</h2>";

// Prüfe ob Klasse existiert
if (!class_exists('CBD_Database')) {
    echo "<p class='error'>❌ CBD_Database Klasse nicht gefunden! Lade sie...</p>";

    if (file_exists(__DIR__ . '/includes/class-cbd-database.php')) {
        require_once __DIR__ . '/includes/class-cbd-database.php';
        echo "<p class='success'>✅ CBD_Database Klasse geladen</p>";
    } else {
        echo "<p class='error'>❌ CBD_Database Datei nicht gefunden!</p>";
    }
}

if (class_exists('CBD_Database')) {
    echo "<p class='success'>✅ CBD_Database Klasse verfügbar</p>";

    // Teste Methoden
    $methods = get_class_methods('CBD_Database');
    echo "<div class='debug'>";
    echo "<h3>Verfügbare Methoden:</h3>";
    foreach ($methods as $method) {
        echo "<p>• " . $method . "</p>";
    }
    echo "</div>";

    // Teste block_name_exists
    if (!empty($blocks)) {
        $test_name = $blocks[0]->name;
        echo "<h3>block_name_exists Test:</h3>";
        try {
            $exists = CBD_Database::block_name_exists($test_name);
            echo "<p>Name '$test_name' existiert: " . ($exists ? "<span class='success'>✅ Ja</span>" : "<span class='error'>❌ Nein</span>") . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Fehler: " . esc_html($e->getMessage()) . "</p>";
        }
    }

    // Teste block_slug_exists
    if (!empty($blocks)) {
        $test_slug = $blocks[0]->slug ?? $blocks[0]->name;
        echo "<h3>block_slug_exists Test:</h3>";
        try {
            $slug_exists = CBD_Database::block_slug_exists($test_slug);
            echo "<p>Slug '$test_slug' existiert: " . ($slug_exists ? "<span class='success'>✅ Ja</span>" : "<span class='error'>❌ Nein</span>") . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Fehler: " . esc_html($e->getMessage()) . "</p>";
        }
    }
}

// 6. Teste direkte Duplizierung
if (!empty($blocks) && class_exists('CBD_Database')) {
    echo "<h2>6. 🔄 Direkte Duplizierung Test</h2>";

    if (isset($_GET['test_duplicate'])) {
        $block_id = intval($_GET['test_duplicate']);
        echo "<h3>Teste Duplizierung von Block ID: $block_id</h3>";

        echo "<div class='debug'>";
        echo "<h4>Schritt-für-Schritt Debug:</h4>";

        // 1. Original Block laden
        echo "<p>1. Lade Original Block...</p>";
        $original = CBD_Database::get_block($block_id);
        if (!$original) {
            echo "<p class='error'>❌ Original Block nicht gefunden!</p>";
        } else {
            echo "<p class='success'>✅ Original Block geladen</p>";
            echo "<pre>" . print_r($original, true) . "</pre>";
        }

        // 2. Teste Duplizierung
        if ($original) {
            echo "<p>2. Teste duplicate_block Funktion...</p>";
            try {
                $duplicate_id = CBD_Database::duplicate_block($block_id);

                if ($duplicate_id) {
                    echo "<p class='success'>✅ Duplizierung erfolgreich! Neue ID: $duplicate_id</p>";

                    // Lade duplizierten Block
                    $duplicate = CBD_Database::get_block($duplicate_id);
                    if ($duplicate) {
                        echo "<h4>Duplizierter Block:</h4>";
                        echo "<pre>" . print_r($duplicate, true) . "</pre>";
                    }
                } else {
                    echo "<p class='error'>❌ Duplizierung fehlgeschlagen (Rückgabewert: false)</p>";
                    echo "<p class='error'>Letzter MySQL-Fehler: " . $wpdb->last_error . "</p>";
                }

            } catch (Exception $e) {
                echo "<p class='error'>❌ Exception: " . esc_html($e->getMessage()) . "</p>";
            }
        }
        echo "</div>";

    } else {
        $test_block = $blocks[0];
        echo "<p><a href='?test_duplicate=" . $test_block->id . "'>🧪 Teste Duplizierung von Block ID " . $test_block->id . "</a></p>";
    }
}

// 7. Prüfe WordPress-Hooks
echo "<h2>7. 🪝 WordPress Hooks</h2>";
echo "<div class='debug'>";
echo "<p>Current user can manage_options: " . (current_user_can('manage_options') ? '✅ Ja' : '❌ Nein') . "</p>";
echo "<p>Current page: " . ($_GET['page'] ?? 'Keine') . "</p>";
echo "<p>Action parameter: " . ($_GET['action'] ?? 'Keine') . "</p>";
echo "<p>Block ID parameter: " . ($_GET['block_id'] ?? 'Keine') . "</p>";
echo "<p>Nonce parameter: " . ($_GET['_wpnonce'] ?? 'Keine') . "</p>";

// Prüfe ob admin_init Hook ausgeführt wird
global $wp_filter;
if (isset($wp_filter['admin_init'])) {
    echo "<p>admin_init Hooks registriert: " . count($wp_filter['admin_init']->callbacks) . "</p>";
} else {
    echo "<p class='error'>❌ Keine admin_init Hooks gefunden</p>";
}
echo "</div>";

echo "<h2>📋 Zusammenfassung</h2>";
echo "<p class='info'>Dieses Debug-Script analysiert alle Aspekte der Block-Duplizierung.</p>";
echo "<p class='info'>Prüfe jeden Abschnitt auf Fehler oder fehlende Komponenten.</p>";

echo "</body></html>";
?>