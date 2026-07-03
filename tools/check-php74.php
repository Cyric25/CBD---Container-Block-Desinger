<?php
/**
 * PHP 7.4 Syntax-Check für das Container Block Designer Plugin.
 *
 * Warum: Lokal läuft oft PHP 8.x, wodurch `php -l` 8.0-only-Syntax NICHT
 * erkennt. Die Produktions-/Testumgebung läuft aber auf PHP 7.4.33. Dieser
 * Check parst alle Plugin-Dateien gezielt gegen PHP 7.4 (via nikic/php-parser,
 * liegt bereits in vendor/) und meldet 8.0-only-Syntax als Fehler.
 *
 * Aufruf:  php tools/check-php74.php [PLUGIN_ROOT]
 * Exit:    0 = alles 7.4-kompatibel
 *          1 = 7.4-Syntaxfehler gefunden (ZIP-Bau abbrechen)
 *          2 = Parser nicht verfügbar (vendor/ fehlt) – nur Warnung
 *
 * @package ContainerBlockDesigner
 */

// functions.php (via composer files-autoload) bricht ohne ABSPATH sofort ab –
// hier nur ein Platzhalter, damit ein evtl. Autoload nicht die CLI beendet.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/cbd-check/');
}

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$parserBase = $root . '/vendor/nikic/php-parser/lib/PhpParser';
if (!is_dir($parserBase)) {
    fwrite(STDERR, "[7.4-Check] nikic/php-parser nicht gefunden ({$parserBase}). Check übersprungen.\n");
    exit(2);
}

// php-parser direkt laden (KEIN composer files-autoload -> würde functions.php triggern)
spl_autoload_register(function ($class) use ($parserBase) {
    $prefix = 'PhpParser\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $parserBase . '/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!class_exists('PhpParser\\ParserFactory')) {
    fwrite(STDERR, "[7.4-Check] ParserFactory nicht ladbar. Check übersprungen.\n");
    exit(2);
}

$factory = new PhpParser\ParserFactory();
try {
    if (method_exists($factory, 'createForVersion') && class_exists('PhpParser\\PhpVersion')) {
        $parser = $factory->createForVersion(PhpParser\PhpVersion::fromString('7.4'));
    } else {
        // Ältere php-parser-API (v4): PREFER_PHP7 lehnt 8.0-only-Syntax ab
        $parser = $factory->create(PhpParser\ParserFactory::PREFER_PHP7);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, '[7.4-Check] Parser-Init fehlgeschlagen: ' . $e->getMessage() . " Check übersprungen.\n");
    exit(2);
}

// Speicherlimit anheben (große Bibliotheks-Dateien wie TCPDF/mPDF)
@ini_set('memory_limit', '1024M');

$skipDirs = array(
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'nikic' . DIRECTORY_SEPARATOR, // der Parser selbst
);

// Dev-only vendor-Pakete (laufen NICHT in Produktion, werden aus dem ZIP
// ausgeschlossen) – deren 8.x-Anforderung ist unkritisch.
$devVendor = array(
    'phpunit', 'php-code-coverage', 'php-file-iterator', 'php-invoker',
    'php-text-template', 'php-timer', 'phpcodesniffer', 'php_codesniffer',
    'squizlabs', 'wp-coding-standards', 'wpcs', 'phar-io', 'theseer',
    'sebastian', 'doctrine', 'dealerdirect', 'myclabs',
);

$errors = 0;
$checked = 0;
$skippedBig = 0;
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($rii as $file) {
    $p = $file->getPathname();
    if (substr($p, -4) !== '.php') {
        continue;
    }
    $norm = str_replace('\\', '/', $p);
    $skip = false;
    foreach ($skipDirs as $s) {
        if (strpos($p, $s) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }
    // Dev-only vendor-Pakete überspringen (Laufzeit-vendor WIRD geprüft)
    foreach ($devVendor as $d) {
        if (strpos($norm, '/' . $d . '/') !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }
    // Sehr große Dateien (z. B. TCPDF-Fontdaten) überspringen – reine Daten,
    // kein 8.0-Syntaxrisiko, würden nur den Parser-Speicher sprengen.
    if (@filesize($p) > 400000) {
        $skippedBig++;
        continue;
    }

    $checked++;
    try {
        $parser->parse(file_get_contents($p));
    } catch (PhpParser\Error $e) {
        $errors++;
        $rel = ltrim(str_replace($root, '', $p), '/\\');
        fwrite(STDERR, "[7.4-Check] FEHLER: {$rel} -> " . $e->getMessage() . "\n");
    }
}

if ($errors > 0) {
    fwrite(STDERR, "\n[7.4-Check] {$errors} Datei(en) mit PHP-8.0-only-Syntax (nicht 7.4-kompatibel).\n");
    exit(1);
}

echo "[7.4-Check] OK: {$checked} Dateien sind PHP-7.4-kompatibel"
    . ($skippedBig > 0 ? " ({$skippedBig} große Datei(en) übersprungen)" : "") . ".\n";
exit(0);
