<?php
/**
 * Container Block Designer — Serverseite des Inline-Verweises
 *
 * Der Inline-Verweis ist ein TEXTFORMAT (`cbd/block-reference-inline`), kein
 * Block: Redakteurinnen markieren Text mitten in einem Absatz und wählen einen
 * Container-Block als Ziel. Gespeichert wird ein gewöhnliches `<a>` mit der
 * Klasse `cbd-block-reference-inline` und vier `data-`Attributen.
 *
 * WARUM EINE EIGENE KLASSE (und nicht CBD_Block_Reference)
 * Mit dem Block „Block-Referenz" hat der Inline-Verweis nichts gemeinsam außer
 * der geteilten Modal-Implementierung in `blocks/block-reference/view.js`. Der
 * Block hängt an `block.json`, `render.php` und einem Render-Callback; ein
 * Textformat an nichts davon.
 *
 * WAS DIESE KLASSE TUT — drei Dinge
 *   1. `inhalt_auffrischen()` auf `the_content`, Priorität 12: setzt
 *      `data-display-mode`, `data-same-page` und `aria-haspopup` und rechnet
 *      `href` neu.
 *   2. Bindet `view.js` und den Blockstil ein, sobald ein Verweis im Inhalt
 *      steht — `viewScript` aus `block.json` allein lädt beides nur, wenn der
 *      BLOCK auf der Seite vorkommt.
 *   3. Registriert das Editor-Script `format.js` (das die Auswahl-Oberfläche
 *      trägt).
 *
 * WARUM DREI ATTRIBUTE SERVERSEITIG UND NICHT MITGESPEICHERT
 * Ein Textformat friert seine Attribute beim Bearbeiten ein; `render.php`
 * rechnet dagegen bei jedem Aufruf neu. Frisch berechnet bleibt der Verweis
 * nach einer Slug-Änderung heil, und `data-same-page` kann nach dem Kopieren
 * eines Absatzes nicht auf den falschen Zwilling zeigen (`copy_block()` vergibt
 * `stableId` NICHT neu — dieselbe Kennung kann auf zwei Seiten liegen).
 * `aria-haspopup` steht außerdem nicht in der ARIA-Whitelist von kses und würde
 * einem Block-Redakteur beim Speichern entfernt.
 *
 * DIESER FILTER SCHREIBT IN GESPEICHERTEN SEITENINHALT — das schwerste Risiko
 * des Vorhabens. Drei Wächter, alle im Prüfharnisch
 * `tools/test-inline-reference.php` festgenagelt:
 *   1. Sofortiger Rückzug, wenn die Klassenzeichenkette gar nicht im Inhalt
 *      vorkommt. Das ist der weitaus häufigste Fall und damit der billigste.
 *   2. `WP_HTML_Tag_Processor` statt regulärer Ausdrücke. Fehlt die Klasse
 *      (WordPress vor 6.2), kommt der Inhalt unverändert zurück — der Verweis
 *      bleibt dann ein gewöhnlicher Link zur Zielseite (fortschreitende
 *      Verbesserung, kein Fehler). Dasselbe Muster wie
 *      `CBD_Blocks_REST_API::extract_stable_id()`.
 *   3. Wurde kein einziger Verweis bearbeitet, geht der URSPRÜNGLICHE String
 *      zurück, nicht `get_updated_html()`. Damit ist die Rückgabe in allen
 *      unbeteiligten Fällen zeichengleich.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.92
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Inline_Reference {

    /**
     * Die CSS-Klasse des Inline-Verweises.
     *
     * BEWUSST NICHT `cbd-block-reference-link` (die Klasse des Blocks): Jene
     * trägt in `blocks/block-reference/style.css` `display: block` samt
     * Karten-Layout und `transform` beim Überfahren — mitten in einem Absatz
     * zerreißt das den Textfluss.
     *
     * Diese Zeichenkette steht ein zweites Mal in `format.js` (AP-4.2) als
     * `className` der Formatregistrierung und ein drittes Mal im Klick-Selektor
     * von `view.js`. Wird sie hier geändert, müssen beide mitgezogen werden.
     */
    const KLASSE = 'cbd-block-reference-inline';

    /** Handle des Editor-Scripts, das das Textformat registriert. */
    const FORMAT_HANDLE = 'cbd-block-reference-format';

    /** Pfad des Editor-Scripts, relativ zum Plugin-Verzeichnis. */
    const FORMAT_SCRIPT = 'blocks/block-reference/format.js';

    /**
     * Priorität des `the_content`-Filters.
     *
     * 12 liegt NACH dem LaTeX-Sicherheitsnetz (`CBD_LaTeX_Parser`, Priorität
     * 11) und weit VOR der Glossar-Autoverlinkung des Themes (10000). Letztere
     * überspringt `<a>`-Elemente korrekt — deshalb trägt das Textformat
     * `tagName: 'a'` und nicht `span`: In einem `<span>` dürfte das Glossar ein
     * `<a class="glossar-term">` HINEINsetzen, Klick und Tooltip würden
     * konkurrieren.
     */
    const FILTER_PRIORITAET = 12;

    /** Blockname, dessen View-Script und Stile der Inline-Verweis mitbenutzt. */
    const BLOCK_NAME = 'cbd/block-reference';

    /**
     * Die Nachbarklasse, von der zwei Dinge geborgt werden.
     *
     * `CBD_Block_Reference` registriert BEIDES, was der Inline-Verweis
     * mitbenutzt: den gemeinsamen Auswahlbaustein (Konstante
     * `AUSWAHL_HANDLE`, gelesen in `auswahl_handle()`) und das Handle des
     * Frontend-Scripts (`view_script_handle()`, gelesen in
     * `frontend_einbinden()`). Als Konstante, damit der Klassenname nicht über
     * die Datei verstreut steht.
     */
    const REFERENZ_KLASSE = 'CBD_Block_Reference';

    /**
     * Hooks anmelden.
     *
     * Aufgerufen aus `ContainerBlockDesigner::init()` (Hook `init`,
     * Priorität 0). Beide hier registrierten Hooks feuern später — der
     * Inhaltsfilter beim Rendern, `enqueue_block_editor_assets` beim Laden des
     * Editors.
     *
     * @return void
     */
    public static function init() {
        add_filter('the_content', array(__CLASS__, 'inhalt_auffrischen'), self::FILTER_PRIORITAET);
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'register_format_script'));
    }

    // =====================================================================
    // Editor: das Textformat-Script
    // =====================================================================

    /**
     * Handle des gemeinsamen Auswahlbausteins ermitteln.
     *
     * Der Wert kommt über die KONSTANTE der anderen Klasse, nicht als
     * wiederholte Zeichenkette — sonst liefen beide Stellen irgendwann
     * auseinander. Fehlt die Klasse oder die Konstante (etwa weil eine ältere
     * Fassung von `class-cbd-block-reference.php` installiert ist), gibt es
     * `null` statt eines Fatal Errors; die Registrierung entfällt dann
     * vollständig. Ein Format-Script ohne seinen Auswahlbaustein hätte im
     * Editor keinen Dialog und wäre schlechter als keines.
     *
     * @param string $klasse Nur für den Prüfharnisch überschreibbar.
     * @return string|null
     */
    public static function auswahl_handle($klasse = self::REFERENZ_KLASSE) {
        if (!is_string($klasse) || '' === $klasse || !class_exists($klasse)) {
            return null;
        }

        if (!defined($klasse . '::AUSWAHL_HANDLE')) {
            return null;
        }

        $wert = constant($klasse . '::AUSWAHL_HANDLE');

        return (is_string($wert) && '' !== $wert) ? $wert : null;
    }

    /**
     * Registrierungsdaten des Format-Scripts berechnen.
     *
     * Als eigene Methode, damit der Prüfharnisch die Entscheidung ohne
     * WordPress-Warteschlange prüfen kann.
     *
     * WARUM DIE ABHÄNGIGKEITEN VON HAND STEHEN
     * Das Plugin hat KEINEN Build-Schritt, also gibt es keine
     * `index.asset.php`. Ohne die Liste registrierte WordPress das Script ohne
     * Abhängigkeiten, und `wp.richText` wäre beim Ausführen womöglich noch
     * nicht geladen. `wp-rich-text` muss ausdrücklich dabeistehen, auch wenn es
     * über `wp-block-editor` meist schon mitkommt — genau an dieser Auslassung
     * hat das Plugin schon einmal gelitten
     * (`class-cbd-block-reference.php:151-160`).
     *
     * @param string $relativ Pfad des Scripts, relativ zum Plugin-Verzeichnis.
     * @return array|null `null`, wenn nicht registriert werden kann.
     */
    public static function format_script_daten($relativ = self::FORMAT_SCRIPT) {
        if (!is_string($relativ) || '' === $relativ) {
            return null;
        }

        $pfad = CBD_PLUGIN_DIR . $relativ;

        // Solange `format.js` fehlt (Stand nach AP-3.3), passiert hier nichts.
        // AP-4.2 legt die Datei an — mehr ist dann nicht zu tun.
        if (!file_exists($pfad)) {
            return null;
        }

        $auswahl = self::auswahl_handle();
        if (null === $auswahl) {
            return null;
        }

        // Cache-Busting über filemtime(): Die Datei ändert sich auch zwischen
        // zwei Plugin-Versionen, CBD_VERSION allein genügt nicht.
        $version = defined('CBD_VERSION') ? CBD_VERSION : '';
        $mtime = filemtime($pfad);
        if ($mtime) {
            $version = $version . '.' . $mtime;
        }

        return array(
            'handle' => self::FORMAT_HANDLE,
            'src'    => CBD_PLUGIN_URL . $relativ,
            'deps'   => array(
                'wp-rich-text',     // registerFormatType, applyFormat, getActiveFormat
                'wp-block-editor',  // RichTextToolbarButton
                'wp-components',    // Modal, Button, Notice, Spinner
                'wp-element',       // createElement, Fragment, useState
                'wp-i18n',          // __, sprintf
                'wp-api-fetch',     // von block-auswahl.js gebraucht
                $auswahl,           // window.cbdBlockAuswahl (Vertrag C)
            ),
            'ver'    => $version,
        );
    }

    /**
     * Das Format-Script anmelden und einreihen.
     *
     * ANMELDEN GENÜGT HIER NICHT — anders als beim Editor-Script des Blocks:
     * Jenes nennt `block.json` unter `editorScript`, WordPress reiht es
     * deshalb selbst ein, sobald der Block im Editor gebraucht wird. Ein
     * TEXTFORMAT hängt an keinem Block; niemand würde das Handle je einreihen.
     * Deshalb beides in einem Schritt.
     *
     * @return void
     */
    public static function register_format_script() {
        if (wp_script_is(self::FORMAT_HANDLE, 'registered')) {
            wp_enqueue_script(self::FORMAT_HANDLE);
            return;
        }

        $daten = self::format_script_daten();
        if (null === $daten) {
            return;
        }

        wp_register_script(
            $daten['handle'],
            $daten['src'],
            $daten['deps'],
            $daten['ver'],
            true
        );

        wp_enqueue_script($daten['handle']);
    }

    // =====================================================================
    // Frontend: der Inhaltsfilter
    // =====================================================================

    /**
     * Inline-Verweise im ausgegebenen Inhalt auffrischen.
     *
     * Reihenfolge der Wächter ist Teil des Vertrags — siehe Kopfkommentar.
     *
     * @param string $content Beitragsinhalt.
     * @return string
     */
    public static function inhalt_auffrischen($content) {
        // Wächter 1: Nicht-Zeichenketten und leerer Inhalt gehen unverändert
        // durch. `the_content` bekommt praktisch immer einen String, aber ein
        // fremder Filter auf niedrigerer Priorität könnte etwas anderes
        // zurückgegeben haben.
        if (!is_string($content) || '' === $content) {
            return $content;
        }

        // Wächter 2: Der häufigste Fall — die Klasse kommt gar nicht vor.
        // Ein einziges strpos() über den Inhalt, danach ist Schluss.
        if (false === strpos($content, self::KLASSE)) {
            return $content;
        }

        // Wächter 3: Ohne den Tag-Processor wird NICHT auf einen regulären
        // Ausdruck ausgewichen. Der Verweis bleibt dann ein gewöhnlicher Link.
        if (!class_exists('WP_HTML_Tag_Processor')) {
            return $content;
        }

        $tags = new WP_HTML_Tag_Processor($content);
        $bearbeitet = 0;

        while ($tags->next_tag(array('tag_name' => 'A', 'class_name' => self::KLASSE))) {
            $ziel = self::ziel_post_id($tags->get_attribute('data-target-post'));

            // Ohne brauchbares Ziel bleibt das Element vollständig unverändert.
            if ($ziel < 1) {
                continue;
            }

            $tags->set_attribute('data-display-mode', 'modal');

            // `data-same-page` entscheidet im Modal über DOM-Klon statt
            // Nachladen. Es wird GENAU DANN gesetzt, wenn Verweis und Ziel
            // wirklich dieselbe Seite teilen — und andernfalls entfernt, auch
            // wenn es (etwa durch einen kopierten Absatz) gespeichert war.
            if (self::aktuelle_post_id() === $ziel) {
                $tags->set_attribute('data-same-page', 'true');
            } else {
                $tags->remove_attribute('data-same-page');
            }

            $tags->set_attribute('aria-haspopup', 'dialog');

            $href = self::ziel_href(
                $ziel,
                $tags->get_attribute('data-target-anchor'),
                $tags->get_attribute('data-target-stable-id')
            );

            // Liefert get_permalink() nichts Brauchbares, bleibt der
            // GESPEICHERTE href stehen. Er ist die fortschreitende
            // Verbesserung für den Fall ohne JavaScript; ihn zu leeren wäre
            // schlechter als ein möglicherweise veralteter Link.
            if (null !== $href) {
                $tags->set_attribute('href', $href);
            }

            $bearbeitet++;
        }

        // Wächter 3 (Fortsetzung): Wurde nichts bearbeitet, geht der
        // URSPRÜNGLICHE String zurück. `get_updated_html()` wäre hier zwar
        // ebenfalls unverändert, aber diese Zeile macht die Zusicherung
        // „zeichengleich" unabhängig von der Implementierung des
        // Tag-Processors.
        if ($bearbeitet < 1) {
            return $content;
        }

        self::frontend_einbinden();

        return $tags->get_updated_html();
    }

    /**
     * `data-target-post` als positive Ganzzahl lesen.
     *
     * Streng: Nur eine reine Ziffernfolge zählt. `"45abc"` wäre über `(int)`
     * zu 45 geworden — ein Wert, den niemand geschrieben hat. `ctype_digit()`
     * lehnt `+45`, ` 45 ` und `4e2` ab, die `FILTER_VALIDATE_INT` teilweise
     * akzeptieren würde — die beiden Prüfungen ergänzen sich, die eine
     * ersetzt die andere nicht.
     *
     * WARUM filter_var() UND NICHT (int) FÜR DEN NUMERISCHEN TEIL (AP-3.fix2)
     * Eine überlange Ziffernfolge besteht `ctype_digit()` trotzdem — sie
     * enthält ja nur Ziffern. `(int)` würde sie ab PHP 8.1 mit der Warnung
     * „not representable as an int, cast occurred" auf `PHP_INT_MAX`
     * abbilden, statt sie abzulehnen: ein Wert, den niemand geschrieben hat,
     * gälte als gültige Beitrags-ID, und jedes Vorkommen schriebe eine
     * harmlose Warnung ins Debug-Log. `filter_var()` liefert für Werte
     * außerhalb des Wertebereichs stattdessen `false`, ohne zu warnen — die
     * Ziffernfolge fällt damit auf `0` zurück. Dieselbe Regel mit derselben
     * Begründung steht bereits in `class-cbd-design-transfer.php`
     * (`md_read_value()`, Zeile ~911-915); diese Stelle hatte sie nicht
     * übernommen.
     *
     * @param mixed $roh Rückgabe von `get_attribute()` (string, true oder null).
     * @return int 0, wenn nicht lesbar.
     */
    private static function ziel_post_id($roh) {
        if (!is_string($roh)) {
            return 0;
        }

        $roh = trim($roh);
        if ('' === $roh || !ctype_digit($roh)) {
            return 0;
        }

        $id = filter_var($roh, FILTER_VALIDATE_INT);

        return (false !== $id && $id > 0) ? $id : 0;
    }

    /**
     * ID der Seite, die gerade ausgegeben wird.
     *
     * `get_the_ID()` liefert außerhalb des Loops `false` — daraus wird 0, und
     * 0 stimmt mit keiner Ziel-ID überein. Im Zweifel gilt also „andere
     * Seite", und das Modal lädt nach statt zu klonen. Der teurere Weg ist
     * hier der richtige: Ein falscher DOM-Klon zeigte still den falschen Block.
     *
     * @return int
     */
    private static function aktuelle_post_id() {
        if (!function_exists('get_the_ID')) {
            return 0;
        }

        $id = get_the_ID();

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * Ziel-URL frisch berechnen.
     *
     * Aufbau wie in `blocks/block-reference/render.php`: Mit Anker das
     * Fragment, ohne Anker der Parameter `cbd-ref` (den `view.js` beim Laden
     * auswertet). `add_query_arg()` statt eigener Verkettung, damit auch ein
     * Permalink mit vorhandener Abfrage (`?p=45`, Installationen ohne hübsche
     * Permalinks) richtig ergänzt wird.
     *
     * @param int   $ziel   Ziel-Beitrag.
     * @param mixed $anker  `data-target-anchor` aus dem Markup.
     * @param mixed $stable `data-target-stable-id` aus dem Markup.
     * @return string|null `null`, wenn kein Permalink zu bekommen ist.
     */
    private static function ziel_href($ziel, $anker, $stable) {
        if (!function_exists('get_permalink')) {
            return null;
        }

        $permalink = get_permalink($ziel);
        if (!is_string($permalink) || '' === $permalink) {
            return null;
        }

        $anker = is_string($anker) ? trim($anker) : '';
        if ('' !== $anker) {
            return self::url_bereinigen($permalink . '#' . $anker);
        }

        $stable = is_string($stable) ? trim($stable) : '';
        if ('' === $stable) {
            return self::url_bereinigen($permalink);
        }

        return self::url_bereinigen(add_query_arg('cbd-ref', $stable, $permalink));
    }

    /**
     * URL entschärfen.
     *
     * Anker und Bezeichner stammen aus gespeichertem Markup. `esc_url_raw()`
     * entfernt Steuerzeichen und maskiert Leerzeichen — dasselbe, was
     * `render.php` über `esc_url()` an der Ausgabe leistet. Das Escaping für
     * das Attribut selbst übernimmt danach `WP_HTML_Tag_Processor`.
     *
     * @param string $url
     * @return string
     */
    private static function url_bereinigen($url) {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($url);
        }

        return $url;
    }

    // =====================================================================
    // Frontend: Script und Stil des Modals
    // =====================================================================

    /**
     * `view.js` und den Blockstil einbinden.
     *
     * WARUM AUS DEM INHALTSFILTER UND NICHT AUS `wp_enqueue_scripts`
     * `block.json` deklariert `viewScript`; WordPress lädt es nur, wenn der
     * BLOCK auf der Seite steht. Eine Seite mit ausschließlich Inline-Verweisen
     * bekäme also kein Modal — der Fehler, der beim Testen auf einer Seite, die
     * auch einen Blockreferenz-Block enthält, garantiert nicht auffällt. Der
     * Inhaltsfilter hat den Inhalt schon in der Hand und weiß bereits, dass ein
     * Verweis darin vorkommt; ein zweiter Scan auf `wp_enqueue_scripts` wäre
     * dieselbe Frage doppelt gestellt und verfehlte Auszüge, Widgets und
     * Archive.
     *
     * DIESER WEG TRÄGT NUR, WEIL `view.js` EIN FOOTER-SCRIPT IST.
     * `CBD_Block_Reference` registriert es mit `$in_footer = true`, und
     * `the_content` läuft vor `wp_footer`. Würde die Registrierung künftig auf
     * `$in_footer = false` umgestellt, käme dieses Enqueue zu spät und der
     * Inline-Verweis wäre still gelähmt (er blieb ein gewöhnlicher Link).
     *
     * Die Daten für das Script (REST-Wurzel, Nonce, Texte) hängt
     * `CBD_Block_Reference::localize_view_script()` bereits auf `init` an das
     * registrierte Handle; sie werden ausgegeben, sobald das Handle in die
     * Warteschlange kommt. Hier ist dafür nichts zu tun.
     *
     * @return void
     */
    private static function frontend_einbinden() {
        $block_typ = self::block_typ();

        // --- Script ---
        if (class_exists(self::REFERENZ_KLASSE)
            && method_exists(self::REFERENZ_KLASSE, 'view_script_handle')) {
            $handle = call_user_func(array(self::REFERENZ_KLASSE, 'view_script_handle'), $block_typ);

            if (is_string($handle) && '' !== $handle && wp_script_is($handle, 'registered')) {
                wp_enqueue_script($handle);
            }
        }

        // --- Stil ---
        // Geraten wird hier nichts: Ohne registrierten Blocktyp gibt es keine
        // Handle-Liste, und ein erfundener Handle erzeugte nur eine Warnung.
        if (!is_object($block_typ) || empty($block_typ->style_handles)) {
            return;
        }

        $stile = $block_typ->style_handles;
        if (!is_array($stile)) {
            return;
        }

        foreach ($stile as $stil) {
            if (is_string($stil) && '' !== $stil && wp_style_is($stil, 'registered')) {
                wp_enqueue_style($stil);
            }
        }
    }

    /**
     * Registrierten Blocktyp `cbd/block-reference` holen.
     *
     * @return object|null
     */
    private static function block_typ() {
        if (!class_exists('WP_Block_Type_Registry')) {
            return null;
        }

        $registry = WP_Block_Type_Registry::get_instance();
        if (!is_object($registry) || !method_exists($registry, 'get_registered')) {
            return null;
        }

        $typ = $registry->get_registered(self::BLOCK_NAME);

        return is_object($typ) ? $typ : null;
    }
}
