<?php
/**
 * Container Block Designer - Fragenwand (klassenspezifische offene Fragen)
 *
 * Datenschicht für die Fragenwand: Lehrpersonen legen je Klasse Notizen an,
 * haken sie ab, bearbeiten und löschen sie. Der lesende Zugriff für Schüler
 * in einer laufenden Klassensitzung läuft NICHT über diese AJAX-Actions,
 * sondern über den REST-Endpunkt `cbd/v1/fragenwand` weiter unten (AP-2.3).
 *
 * Sicherheitsmuster (identisch zu CBD_Classroom::ajax_save_drawing()):
 *   check_ajax_referer('cbd_classroom_nonce', 'nonce')
 *   -> current_user_can('cbd_edit_blocks')
 *   -> Parametervalidierung
 *   -> Zugriffsprüfung auf die Klasse
 *   -> Datenbankoperation
 *
 * ZWEI LESEWEGE, EINE REIHENFOLGE. `ajax_fragenwand_get_notes()` (Lehrperson,
 * angemeldet) und `rest_get_notes_for_student()` (Schüler, Klassensitzung)
 * lesen dieselbe Tabelle mit demselben `ORDER BY ist_erledigt ASC,
 * created_at ASC, id ASC`. Wer eine der beiden Sortierungen ändert, muss die
 * andere mitziehen — sonst sähen Lehrperson und Klasse dieselbe Wand in
 * unterschiedlicher Reihenfolge.
 *
 * SEIT AP-3.1 kommt eine dritte, mit der Datenschicht nicht verwandte Aufgabe
 * dazu: `register_editor_format()` meldet auf `enqueue_block_editor_assets`
 * das Textformat „Fragenwand-Verweis" (`assets/js/fragenwand-format.js`) an.
 * Es steht hier und nicht in einer eigenen Klasse, weil der Plan die Fragenwand
 * bewusst in EINER Klasse bündelt und der Editor-Teil nur aus einer
 * Script-Registrierung besteht.
 *
 * SEIT AP-3.2 eine vierte: `enqueue_frontend_assets()` reiht auf
 * `wp_enqueue_scripts` das Gegenstück im Frontend ein
 * (`assets/js/fragenwand-frontend.js`) — unbedingt auf jeder Seite, weil der
 * Trigger ab Phase 4 auch außerhalb von `post_content` erscheinen kann.
 *
 * SEIT AP-3.fix1 trägt dieselbe Methode zusätzlich die LEHRER-ERKENNUNG des
 * Frontends: Für angemeldete Lehrpersonen mit `cbd_edit_blocks` kommen drei
 * Schlüssel (`classes`, `ajaxUrl`, `nonce`) ins Datenobjekt. Vorher hing die
 * Erkennung an `window.cbdClassroomData` — das nur auf Seiten MIT
 * Container-Block ausgegeben wird. Begründung im Docblock von
 * `lehrer_daten()`.
 *
 * SEIT AP-4.2 eine fünfte, mit den vorherigen vier nicht verwandte Aufgabe:
 * `page_index_eintrag()` hängt sich am leeren Theme-Filter
 * `simple_clean_page_index_zusatzeintraege`
 * (`Theme/includes/page-index.php`, AP-4.1) ein und liefert im Klassenmodus
 * (Plausibilitätsprüfung `?classroom=`, siehe deren Docblock) den
 * Fragenwand-Button ganz oben im Theme-Inhaltsverzeichnis
 * (`fos/inhaltsverzeichnis`). Der EINZIGE Theme-Kontakt dieses gesamten
 * Vorhabens — in die andere Richtung als die drei bestehenden Nähten
 * (`CBD_Classroom_Gate`, `CBD_Block_Content_API`, `CBD_Blocks_REST_API`),
 * die jeweils eine Theme-Funktion AUFRUFEN: Hier bietet das Plugin selbst
 * einen Filter an, den das Theme bedient.
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 2 (AP-2.2/AP-2.3) — CBD_VERSION bei Anlage 3.1.106
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fragenwand-Datenschicht (AJAX für Lehrpersonen)
 */
class CBD_Fragenwand {

    /**
     * REST-Namensraum. Derselbe wie bei CBD_Block_Content_API — die Trennung
     * liegt in der Route, nicht im Namensraum.
     */
    const REST_NAMESPACE = 'cbd/v1';

    /** Die Route für den Schüler-Lesezugriff. */
    const REST_ROUTE = '/fragenwand';

    /**
     * Der EINZIGE Fehlercode dieses Endpunkts.
     *
     * Bewusst keine sprechenden Codes („Klasse unbekannt", „Sitzung
     * abgelaufen"): Jeder Unterschied wäre ein Werkzeug, um durch
     * Durchprobieren herauszufinden, welche Klassen es gibt. Vorbild ist
     * `CBD_Block_Content_API::FEHLERCODE`.
     */
    const REST_FEHLERCODE = 'cbd_fragenwand_not_available';

    /** HTTP-Status jeder Ablehnung. */
    const REST_FEHLERSTATUS = 404;

    /**
     * Handle des Editor-Scripts für das Textformat „Fragenwand-Verweis".
     *
     * @since AP-3.1
     */
    const FORMAT_HANDLE = 'cbd-fragenwand-format';

    /**
     * Pfad des Format-Scripts, relativ zum Plugin-Verzeichnis.
     *
     * @since AP-3.1
     */
    const FORMAT_SCRIPT = 'assets/js/fragenwand-format.js';

    /**
     * Handle des winzigen Editor-Stylesheets für den Verweis-Look
     * (a.cbd-fragenwand-verweis) — NICHT dasselbe Stylesheet wie im
     * Frontend, siehe Docblock von register_editor_format().
     *
     * @since AP-3.4
     */
    const EDITOR_STYLE_HANDLE = 'cbd-fragenwand-editor';

    /**
     * Pfad des Editor-Stylesheets, relativ zum Plugin-Verzeichnis.
     *
     * @since AP-3.4
     */
    const EDITOR_STYLE_FILE = 'assets/css/fragenwand-editor.css';

    /**
     * Handle des Frontend-Scripts (Modal, Trigger, Datenabruf).
     *
     * @since AP-3.2
     */
    const FRONTEND_HANDLE = 'cbd-fragenwand-frontend';

    /**
     * Pfad des Frontend-Scripts, relativ zum Plugin-Verzeichnis.
     *
     * @since AP-3.2
     */
    const FRONTEND_SCRIPT = 'assets/js/fragenwand-frontend.js';

    /**
     * Name des per wp_localize_script() erzeugten Datenobjekts.
     *
     * @since AP-3.2
     */
    const FRONTEND_DATA_OBJECT = 'cbdFragenwandFrontend';

    /**
     * Handle des Frontend-Stylesheets (Post-it-Optik, Ausgrauen, Darkmode
     * automatisch ueber Theme-Variablen).
     *
     * @since AP-3.4
     */
    const STYLE_HANDLE = 'cbd-fragenwand';

    /**
     * Pfad des Frontend-Stylesheets, relativ zum Plugin-Verzeichnis.
     *
     * @since AP-3.4
     */
    const STYLE_FILE = 'assets/css/fragenwand.css';

    /**
     * Singleton instance
     *
     * @var CBD_Fragenwand|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return CBD_Fragenwand
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - registriert die AJAX-Actions der Lehrpersonen und die
     * REST-Route für Schüler in einer laufenden Klassensitzung.
     *
     * Bewusst KEIN wp_ajax_nopriv_*-Gegenstück: Diese fünf Endpunkte sind
     * ausschließlich für angemeldete Lehrpersonen mit `cbd_edit_blocks`.
     * Der Schüler-Lesezugriff ist ein eigener REST-Endpunkt (AP-2.3) — er
     * braucht kein Login, weil Schüler sich nie anmelden, sondern über das
     * Klassenpasswort kommen.
     */
    private function __construct() {
        add_action('wp_ajax_cbd_fragenwand_get_notes', array($this, 'ajax_fragenwand_get_notes'));
        add_action('wp_ajax_cbd_fragenwand_add_note', array($this, 'ajax_fragenwand_add_note'));
        add_action('wp_ajax_cbd_fragenwand_toggle_note', array($this, 'ajax_fragenwand_toggle_note'));
        add_action('wp_ajax_cbd_fragenwand_edit_note', array($this, 'ajax_fragenwand_edit_note'));
        add_action('wp_ajax_cbd_fragenwand_delete_note', array($this, 'ajax_fragenwand_delete_note'));

        add_action('rest_api_init', array($this, 'register_rest_route'));

        // AP-3.1: Das Textformat „Fragenwand-Verweis" im Block-Editor.
        // `enqueue_block_editor_assets` feuert AUSSCHLIESSLICH im Editor —
        // das Script gelangt damit nie ins Frontend.
        add_action('enqueue_block_editor_assets', array($this, 'register_editor_format'));

        // AP-3.4: Editor-Optik in das iFrame der Editor-Leinwand einschleusen.
        // `enqueue_block_editor_assets` allein genügt dafür NICHT — Begründung
        // im Docblock von `inject_editor_style_into_iframe()`.
        add_filter('block_editor_settings_all', array($this, 'inject_editor_style_into_iframe'));

        // AP-3.2: Das Gegenstück im Frontend — Modal, Trigger, Datenabruf.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AP-4.2: Der einzige Theme-Kontakt dieses Vorhabens. Das Theme
        // definiert und ruft den Filter (`Theme/includes/page-index.php`,
        // AP-4.1) unabhängig davon, ob dieses Plugin existiert — Standardwert
        // dort ist der leere String. `add_filter()` selbst ist immer
        // gefahrlos, auch wenn der zugehörige `apply_filters()`-Aufruf im
        // Theme fehlt oder ein anderes Theme aktiv ist. KEIN
        // `function_exists()`-Guard nötig für das Einhängen selbst — nur der
        // AUFRUF einer Theme-Funktion durch das Plugin bräuchte das (siehe
        // die drei bestehenden Beispiele dafür in `CBD_Classroom_Gate` und
        // `CBD_Block_Content_API`), nicht das Anbieten eines eigenen Filters.
        add_filter('simple_clean_page_index_zusatzeintraege', array($this, 'page_index_eintrag'));
    }

    // =========================================================================
    // EDITOR: DAS TEXTFORMAT „FRAGENWAND-VERWEIS" (AP-3.1)
    // =========================================================================

    /**
     * Das Format-Script anmelden und einreihen.
     *
     * ANMELDEN GENÜGT HIER NICHT — anders als beim Editor-Script eines Blocks:
     * Jenes nennt `block.json` unter `editorScript`, WordPress reiht es
     * deshalb selbst ein, sobald der Block im Editor gebraucht wird. Ein
     * TEXTFORMAT hängt an keinem Block; niemand würde das Handle je
     * einreihen. Deshalb beides in einem Schritt. (Vorbild:
     * `CBD_Inline_Reference::register_format_script()`.)
     *
     * WARUM DIE ABHÄNGIGKEITEN VON HAND STEHEN: Das Plugin hat KEINEN
     * Build-Schritt, also gibt es keine `index.asset.php`. Ohne die Liste
     * registrierte WordPress das Script ohne Abhängigkeiten, und
     * `wp.richText` wäre beim Ausführen womöglich noch nicht geladen.
     * `wp-rich-text` muss ausdrücklich dabeistehen, auch wenn `wp-block-editor`
     * es in der Praxis mitbringt — genau an dieser Auslassung hat das Plugin
     * schon einmal gelitten (`class-cbd-block-reference.php:155-158`).
     * Dasselbe gilt für `wp-data`: `fragenwand-format.js` ruft
     * `wp.data.dispatch('core/notices')` auf, wenn auf der Markierung bereits
     * ein `core/link` liegt. Auf die zufällige Mitlieferung durch
     * `wp-block-editor` zu bauen ist genau die Fehlerfamilie, die der obige
     * Kommentar beschreibt (Vorbild: AP-4.fix1 des Vorhabens
     * „Inline-Blockreferenz").
     *
     * BEWUSST NICHT in der Liste: `wp-components` (kein Modal, kein Button —
     * dieses Format hat keinen Auswahl-Dialog) und der Auswahlbaustein
     * `cbd-block-auswahl` (es gibt genau EINE Fragenwand je Klasse, also
     * nichts auszuwählen).
     *
     * SEIT AP-3.4 reiht dieselbe Methode zusätzlich ein winziges eigenes
     * Stylesheet ein (`assets/css/fragenwand-editor.css`) — ohne irgendeine
     * Kennzeichnung wäre der eingefügte Verweis (`<a
     * class="cbd-fragenwand-verweis" href="#">`) im Editor nicht von
     * normalem Text zu unterscheiden. Bewusst NICHT dasselbe Stylesheet wie
     * im Frontend (`assets/css/fragenwand.css`, siehe
     * `enqueue_frontend_assets()`): Das Frontend-CSS ist auf das Modal
     * zugeschnitten (Overlay, Post-it-Karten, Klassenauswahl) und wäre im
     * Editor totes Gewicht.
     *
     * DAS EINREIHEN HIER GENÜGT ALLEIN NICHT: Es stylt nur das äußere
     * Admin-Dokument, nicht das `<iframe>`, in dem der Block-Editor seit
     * WordPress 5.9 den Beitragsinhalt rendert — und genau dort steht der
     * Verweis. Die eigentliche, wirksame Einschleusung übernimmt
     * `inject_editor_style_into_iframe()` über den Filter
     * `block_editor_settings_all` (siehe dessen Docblock). Diese Methode
     * bleibt trotzdem bestehen, falls dieselbe Klasse je an einer Stelle
     * außerhalb des iFrames gebraucht wird.
     *
     * @since AP-3.1, Editor-Stylesheet ergänzt in AP-3.4
     * @return void
     */
    public function register_editor_format() {
        // Mehrfaches Einreihen ist harmlos, aber ein zweites
        // `wp_register_script()` auf dasselbe Handle wäre wirkungslos und
        // verschleierte einen Fehler — deshalb dieselbe Weiche wie im Vorbild.
        if (wp_script_is(self::FORMAT_HANDLE, 'registered')) {
            wp_enqueue_script(self::FORMAT_HANDLE);
        } else {
            $pfad = CBD_PLUGIN_DIR . self::FORMAT_SCRIPT;

            // Fehlt die Datei (unvollständiges Update), wird gar nichts
            // registriert. Ein Handle ohne Datei ergäbe im Editor einen 404.
            if (file_exists($pfad)) {
                wp_register_script(
                    self::FORMAT_HANDLE,
                    CBD_PLUGIN_URL . self::FORMAT_SCRIPT,
                    array(
                        'wp-rich-text',    // registerFormatType, applyFormat, removeFormat
                        'wp-block-editor', // RichTextToolbarButton
                        'wp-element',      // createElement
                        'wp-i18n',         // __
                        'wp-data',         // wp.data.dispatch('core/notices') bei core/link-Konflikt
                    ),
                    defined('CBD_VERSION') ? CBD_VERSION : false,
                    true
                );

                wp_enqueue_script(self::FORMAT_HANDLE);
            }
        }

        // AP-3.4: Editor-Stylesheet, unabhängig vom Script-Zweig oben
        // eingereiht — ein fehlendes/bereits registriertes Format-Script
        // soll die Optik-Kennzeichnung nicht mitreißen.
        if (wp_style_is(self::EDITOR_STYLE_HANDLE, 'registered')) {
            wp_enqueue_style(self::EDITOR_STYLE_HANDLE);
            return;
        }

        $stil_pfad = CBD_PLUGIN_DIR . self::EDITOR_STYLE_FILE;

        if (!file_exists($stil_pfad)) {
            return;
        }

        wp_register_style(
            self::EDITOR_STYLE_HANDLE,
            CBD_PLUGIN_URL . self::EDITOR_STYLE_FILE,
            array(),
            defined('CBD_VERSION') ? CBD_VERSION : false
        );

        wp_enqueue_style(self::EDITOR_STYLE_HANDLE);
    }

    /**
     * Die Editor-Optik zusätzlich in das iFrame der Editor-Leinwand einschleusen.
     *
     * WARUM `wp_enqueue_style()` IN `register_editor_format()` ALLEIN NICHT
     * GENÜGT (live gefunden bei der AP-3.4-Prüfung, siehe Übergabenotiz):
     * Seit WordPress 5.9 rendert der Block-Editor den Beitragsinhalt in einem
     * eigenen `<iframe>` („editor-canvas"), damit die Bearbeitungsansicht das
     * echte Frontend-CSS/Theme widerspiegelt. Ein per
     * `enqueue_block_editor_assets` → `wp_enqueue_style()` eingereihtes
     * Stylesheet landet nur im ÄUSSEREN Admin-Dokument, NICHT in diesem
     * iFrame — Selektoren, die auf Inhalt IM Beitrag zielen (wie
     * `a.cbd-fragenwand-verweis`), zeigen dort schlicht keine Wirkung. Live
     * nachgemessen: Ohne diese Methode blieb der eingefügte Verweis im
     * Editor optisch nicht von core/link (Wordpress-Standardblau, durchgezogene
     * Unterstreichung) unterscheidbar — ein Verstoß gegen das
     * AP-3.4-Akzeptanzkriterium.
     *
     * DER WEG, DEN WORDPRESS FÜR GENAU DIESEN FALL VORSIEHT: Der Filter
     * `block_editor_settings_all` füllt `$settings['styles']` — ein Array,
     * das der Editor SOWOHL in die äußere Admin-Seite ALS AUCH in das
     * iFrame kopiert (siehe `@wordpress/block-editor`, `Iframe`-Komponente).
     * Ein Eintrag mit dem Schlüssel `css` (statt `href`) bettet die
     * Zeichenkette direkt als `<style>` ein — kein zweiter HTTP-Request,
     * keine zusätzliche Handle-Verwaltung nötig für eine derart kleine Datei.
     *
     * Dieselbe kleine Datei wie in `register_editor_format()`
     * (`assets/css/fragenwand-editor.css`) wird hier ein zweites Mal
     * verwendet — nicht dupliziert, nur zusätzlich als Rohtext eingelesen.
     * Fehlt die Datei, bleibt `$settings` unverändert (kein Fatal Error,
     * nur weiterhin unauffälliger Editor-Link wie vor AP-3.4).
     *
     * @since AP-3.4
     * @param array $settings Editor-Einstellungen aus `get_block_editor_settings()`.
     * @return array
     */
    public function inject_editor_style_into_iframe(array $settings): array {
        $pfad = CBD_PLUGIN_DIR . self::EDITOR_STYLE_FILE;

        if (!file_exists($pfad)) {
            return $settings;
        }

        $css = file_get_contents($pfad);

        if (false === $css || '' === trim($css)) {
            return $settings;
        }

        if (!isset($settings['styles']) || !is_array($settings['styles'])) {
            $settings['styles'] = array();
        }

        $settings['styles'][] = array('css' => $css);

        return $settings;
    }

    // =========================================================================
    // FRONTEND: MODAL, TRIGGER, DATENABRUF (AP-3.2)
    // =========================================================================

    /**
     * Das Frontend-Script einreihen und mit den Serverdaten versorgen.
     *
     * EIGENER NAME, ABER GLEICHER WORTLAUT wie `CBD_Classroom::enqueue_frontend_assets()`
     * — die beiden Klassen sind unabhängig, jede hängt ihre eigene Methode an
     * `wp_enqueue_scripts`. Eine Verwechslung ist ausgeschlossen, weil nie eine
     * Klasse die andere aufruft.
     *
     * UNBEDINGT AUF JEDER FRONTEND-SEITE, ohne `has_block()`- oder
     * Inhalts-Prüfung: Der Trigger (`<a class="cbd-fragenwand-verweis">`) kann
     * im `post_content` stehen — ab Phase 4 aber auch im Inhaltsverzeichnis des
     * Themes, also in Markup, das gar nicht durch `post_content` läuft. Eine
     * Inhalts-Prüfung verpasste genau diesen Fall (Architekturentscheidung in
     * Abschnitt 4 des Plans). Die Datei ist klein und im Footer.
     *
     * DIE REST-ADRESSE KOMMT VON HIER, NIE AUS DEM JAVASCRIPT. Auf
     * Installationen ohne hübsche Permalinks liefert `/wp-json/…` einen
     * Apache-404; dort trägt nur `?rest_route=/cbd/v1/fragenwand`. Welche Form
     * gilt, weiß allein `rest_url()`. Der Pfad wird aus den Konstanten des
     * Endpunkts gebildet und nicht abgeschrieben — sonst laufen Route und
     * Aufruf irgendwann auseinander. (Vorbild:
     * `CBD_Block_Reference::localize_view_script()`.)
     *
     * KEIN NONCE FÜR DEN SCHÜLER-WEG: Der REST-Aufruf dieses Scripts geht an
     * eine Route mit `permission_callback => '__return_true'`, deren gesamte
     * Autorisierung an der Klassensitzung hängt (AP-2.3). Ein `wp_rest`-Nonce
     * wäre für Schüler ohnehin wertlos (nicht angemeldet) und würde nur den
     * Eindruck einer Absicherung erwecken, die er nicht leistet.
     *
     * FÜR DEN LEHRER-WEG SCHON — und zwar seit AP-3.fix1 aus DIESEM Objekt,
     * nicht mehr aus `window.cbdClassroomData`. Begründung im Docblock von
     * `lehrer_daten()` weiter unten.
     *
     * SEIT AP-3.4 reiht dieselbe Methode zusätzlich `assets/css/fragenwand.css`
     * ein (Post-it-Optik, Ausgrauen erledigter Notizen, automatische
     * Darkmode-Fähigkeit über die projektweiten Theme-Variablen — kein
     * eigener `[data-theme="dark"]`-Block nötig). Unbedingt, aus demselben
     * Grund wie das Script oben: Der Trigger kann auch außerhalb von
     * `post_content` erscheinen, eine Inhalts-Prüfung würde ihn verpassen.
     *
     * @since AP-3.2, erweitert in AP-3.fix1 und AP-3.4
     * @return void
     */
    public function enqueue_frontend_assets() {
        // AP-3.4: Stylesheet zuerst, unabhängig vom Script-Zweig unten —
        // fehlt eine der beiden Dateien, soll das die andere nicht mitreißen.
        if (file_exists(CBD_PLUGIN_DIR . self::STYLE_FILE)) {
            wp_enqueue_style(
                self::STYLE_HANDLE,
                CBD_PLUGIN_URL . self::STYLE_FILE,
                array(),
                defined('CBD_VERSION') ? CBD_VERSION : false
            );
        }

        // Fehlt die Datei (unvollständiges Update), wird nichts eingereiht —
        // ein Handle ohne Datei ergäbe einen 404 im Seitenkopf. Dieselbe
        // Weiche wie in register_editor_format().
        if (!file_exists(CBD_PLUGIN_DIR . self::FRONTEND_SCRIPT)) {
            return;
        }

        wp_enqueue_script(
            self::FRONTEND_HANDLE,
            CBD_PLUGIN_URL . self::FRONTEND_SCRIPT,
            array(),
            defined('CBD_VERSION') ? CBD_VERSION : false,
            true
        );

        $daten = array(
            'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE)),
            'texte'   => array(
                'titel'        => __('Fragenwand', 'container-block-designer'),
                'schliessen'   => __('Schließen', 'container-block-designer'),
                'laden'        => __('Fragenwand wird geladen …', 'container-block-designer'),
                // ZEICHENGLEICH FÜR JEDEN FEHLSCHLAG DES ENDPUNKTS. Der
                // REST-Endpunkt antwortet bewusst auf fehlende Sitzung,
                // abgelaufenes Token und unpassendes `?classroom=` identisch
                // (AP-2.3). Eine im Frontend feiner aufgeschlüsselte Meldung
                // machte diese Absicht zunichte.
                'keineSitzung' => __('Keine aktive Klassensitzung.', 'container-block-designer'),
                'fehler'       => __('Die Fragenwand konnte nicht geladen werden.', 'container-block-designer'),
                'leer'         => __('Auf dieser Fragenwand steht noch nichts.', 'container-block-designer'),

                // Vorhaben „Schüler-Fragen": Beschriftung des Eingabefelds im
                // SCHÜLER-Modus. Bewusst hier und nicht nur in der
                // JS-Rückfalltabelle — anders als die Lehrer-Oberfläche (siehe
                // Einschränkung 5 aus AP-3.rev) ist das eine Fläche, die
                // Schüler sehen, und die soll übersetzbar sein.
                'schuelerFrage'      => __('Deine Frage …', 'container-block-designer'),
                'schuelerAbsenden'   => __('Frage stellen', 'container-block-designer'),
                'schuelerHinweis'    => __('Deine Frage erscheint anonym auf der Fragenwand. Ändern oder zurücknehmen kannst du sie danach nicht mehr.', 'container-block-designer'),
                'schuelerFehler'     => __('Die Frage konnte nicht gesendet werden.', 'container-block-designer'),

                // Die nicht-farbliche Kennzeichnung an der Notiz selbst. Sie
                // erscheint in BEIDEN Ansichten (Lehrer wie Schüler) und ist
                // der Teil der Herkunftsangabe, der auch ohne
                // Farbunterscheidung trägt — siehe Begründung in
                // `baueNotiz()` in `fragenwand-frontend.js`.
                'herkunftSchueler'   => __('Schülerfrage', 'container-block-designer'),
            ),
        );

        // AP-3.fix1: Nur für Lehrpersonen kommen drei weitere Schlüssel dazu.
        // Für alle anderen fehlt `classes` vollständig — genau daran erkennt
        // `fragenwand-frontend.js` den Schüler-/Besucherweg.
        $daten = array_merge($daten, $this->lehrer_daten());

        wp_localize_script(self::FRONTEND_HANDLE, self::FRONTEND_DATA_OBJECT, $daten);
    }

    /**
     * Die Lehrer-Zusätze für das Frontend-Datenobjekt (leer für alle anderen).
     *
     * WARUM ES DIESE METHODE ÜBERHAUPT GIBT (AP-3.fix1). Bis AP-3.3 erkannte
     * `fragenwand-frontend.js` eine Lehrperson an der bloßen Existenz von
     * `window.cbdClassroomData`. Dieses Objekt schreibt aber
     * `CBD_Block_Registration::enqueue_block_assets()` NUR auf Seiten, die
     * mindestens einen Container-Block enthalten (`frontend_has_container_block()`).
     * Eine Seite mit ausschließlich einem Fragenwand-Verweis im Fließtext —
     * ab Phase 4 auch der Eintrag im Inhaltsverzeichnis des Themes, der auf
     * JEDER Seite erscheinen soll — hat oft keinen Container-Block. Dort fiel
     * eine eingeloggte Lehrperson in den Schülerpfad und sah „Keine aktive
     * Klassensitzung." statt der Klassenauswahl.
     *
     * `class-cbd-block-registration.php` bleibt deshalb UNVERÄNDERT: Sein
     * Gate ist für Tafelmodus und „Behandelt"-Knopf richtig — die ergeben nur
     * auf Seiten mit Container-Blöcken Sinn. Die Fragenwand bekommt statt
     * dessen hier eine EIGENE, zweite Datenquelle. `window.cbdClassroomData`
     * wird weder entfernt noch verändert.
     *
     * DREI SCHLÜSSEL, NICHT EINER. Der Plan (AP-3.fix1, Schritt 1) nennt nur
     * `classes`. `ajaxUrl` und `nonce` müssen aber mit, sonst wäre der Fix auf
     * halbem Weg stehen geblieben: Die Klassenauswahl erschiene zwar, aber der
     * anschließende AJAX-Aufruf `cbd_fragenwand_get_notes` läse Adresse und
     * Nonce weiterhin aus `window.cbdClassroomData` — das auf genau diesen
     * Seiten fehlt. Die Wand bliebe leer. Beide Werte sind zeichengleich mit
     * denen aus `class-cbd-block-registration.php:665-666` (dieselbe
     * `admin-ajax.php`, derselbe Nonce-Name `cbd_classroom_nonce`, den
     * `guard_lehrperson()` erwartet); es entsteht also keine zweite,
     * abweichende Fassung einer Regel, nur eine zweite Ausgabestelle.
     *
     * KEINE PRÜFUNG AUF `CBD_Classroom::is_enabled()`. `CBD_Fragenwand`
     * registriert seine Hooks bewusst unbedingt, auch bei abgeschaltetem
     * Klassenmodus (dokumentierte Entscheidung aus AP-2.rev, Einschränkung 3).
     * Eine Prüfung hier machte die Lehrer-Erkennung von einem Schalter
     * abhängig, den die Endpunkte selbst nicht kennen.
     *
     * @since AP-3.fix1
     * @return array Leeres Array für alle, die keine Lehrperson sind.
     */
    private function lehrer_daten(): array {
        if (!is_user_logged_in() || !current_user_can('cbd_edit_blocks')) {
            return array();
        }

        return array(
            // Die Klassenliste kommt aus der Bestandsklasse. REIHENFOLGE DER
            // PRÜFUNGEN: `class_exists()` MUSS vor `method_exists()` und dem
            // Aufruf stehen — `CBD_Fragenwand` soll unabhängig von
            // `CBD_Classroom` existieren (Architekturentscheidung), ein
            // fehlendes Gegenüber darf keinen Fatal Error ergeben, sondern nur
            // eine leere Liste. `lehrerFlow()` zeigt dann „Keine Klassen
            // vorhanden." — eine Lehrperson ohne Klassen ist immer noch eine
            // Lehrperson, das Feld selbst ist das Signal.
            'classes' => $this->teacher_classes(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cbd_classroom_nonce'),
        );
    }

    /**
     * Die Klassen der angemeldeten Lehrperson — oder ein leeres Array.
     *
     * @since AP-3.fix1
     * @return array
     */
    private function teacher_classes(): array {
        if (!class_exists('CBD_Classroom') || !method_exists('CBD_Classroom', 'get_teacher_classes')) {
            return array();
        }

        $klassen = CBD_Classroom::get_teacher_classes();

        // `$wpdb->get_results()` kann bei einem Datenbankfehler `null`
        // liefern. `wp_json_encode(null)` ergäbe im Browser `classes: null` —
        // und damit ausgerechnet den Wert, den `istLehrkraft()` als „keine
        // Lehrperson" liest.
        return is_array($klassen) ? $klassen : array();
    }

    // =========================================================================
    // THEME-INTEGRATION: INHALTSVERZEICHNIS-EINTRAG (AP-4.2)
    // =========================================================================

    /**
     * Liefert den Fragenwand-Eintrag für das Theme-Inhaltsverzeichnis.
     *
     * Callback des Filters `simple_clean_page_index_zusatzeintraege`
     * (`Theme/includes/page-index.php`, AP-4.1). Das Theme kennt den Zweck
     * dieses Markups nicht — es wendet den Filter nur an und hängt einen
     * nicht-leeren String-Rückgabewert ganz oben in die
     * Inhaltsverzeichnis-Liste. Dieses Plugin ist die EINZIGE Stelle, die
     * diesen Filter aktuell bedient.
     *
     * ERKENNUNG BEWUSST NUR ÜBER `?classroom=`, KEINE ECHTE
     * SITZUNGSPRÜFUNG. Dieselbe schwache, rein clientseitig gedachte
     * Plausibilitätsprüfung wie in `CBD_Classroom::enqueue_frontend_assets()`
     * — ein positiver Treffer entscheidet nur, ob der Button überhaupt
     * gerendert wird, NICHT, ob der Zugriff erlaubt ist. Die eigentliche
     * Autorisierung passiert unverändert beim Klick, über denselben
     * REST-Endpunkt `cbd/v1/fragenwand` (AP-2.3/`rest_get_notes_for_student()`),
     * der die Sitzung serverseitig gegen den Transient
     * `cbd_classroom_<token>` prüft. Ein gefälschter `?classroom=`-Wert ohne
     * gültiges Token zeigt also höchstens einen Button, der beim Klick die
     * einheitliche Ablehnung „Keine aktive Klassensitzung." liefert — kein
     * Sicherheitsrisiko, nur ein optischer Fehlalarm, den es bewusst in Kauf
     * zu nehmen gilt (Vorbild für dieselbe Abwägung: der Docblock von
     * `CBD_Classroom::enqueue_frontend_assets()`, Zeile ~1180-1181).
     *
     * `<button>` STATT `<a>`: Anders als der Fließtext-Verweis aus AP-3.1
     * (dort `<a href="#">` als strukturell sinnvoller Rückfall ohne
     * JavaScript) hat dieser Eintrag im Inhaltsverzeichnis kein sinnvolles
     * Sprungziel — ein `<button type="button">` macht ihn schon ohne
     * JavaScript-Kontext als Aktion erkennbar, nicht als (kaputten) Link.
     * Die Klick-Delegation aus AP-3.2 (`fragenwand-frontend.js`,
     * `e.target.closest('.cbd-fragenwand-verweis')`) fängt Klicks auf DIESE
     * Klasse unabhängig vom Tag-Namen ab — `closest()` funktioniert für
     * `<button>` genauso wie für `<a>`, es entsteht also keine zweite
     * Trigger-Erkennung.
     *
     * KEIN `function_exists()`-Guard nötig, um DIESEN Filter einzuhängen
     * (siehe Docblock des Konstruktor-Aufrufs) — anders als die drei
     * bestehenden Stellen, an denen dieses Plugin eine THEME-Funktion
     * AUFRUFT (`CBD_Classroom_Gate`, `CBD_Block_Content_API`), bietet dieses
     * Plugin hier selbst einen Filter an, den das Theme optional bedient.
     *
     * @since AP-4.2
     * @param string $vorhandenes Bisheriger Filterwert (Standard `''`, oder
     *                            Markup eines anderen, früher eingehängten
     *                            Filters — wird unverändert angehängt, nicht
     *                            ersetzt).
     * @return string
     */
    public function page_index_eintrag($vorhandenes = '') {
        $classroom_id = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;

        if ($classroom_id <= 0) {
            return $vorhandenes;
        }

        $markup = '<div class="page-index__zusatz page-index__zusatz--fragenwand">'
            . '<button type="button" class="cbd-fragenwand-verweis page-index__fragenwand-link">'
            . esc_html__('Fragenwand öffnen', 'container-block-designer')
            . '</button>'
            . '</div>';

        return $vorhandenes . $markup;
    }

    // =========================================================================
    // ZUGRIFFSPRÜFUNG
    // =========================================================================

    /**
     * Prüfen ob der aktuelle Lehrer Zugriff auf eine Klasse hat (Besitzer oder Abonnent)
     *
     * Bewusste, kleine Duplikation von CBD_Classroom::can_access_class():
     * die dortige Methode ist `private` und wird nicht public gemacht, um die
     * bestehende, produktiv genutzte Klasse nicht zu verändern.
     *
     * @param int $class_id
     * @return bool
     */
    private function can_access_class(int $class_id): bool {
        $teacher_id = get_current_user_id();
        global $wpdb;
        $is_owner = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, $teacher_id
        ));
        if ($is_owner) return true;

        $subscribed = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();
        return in_array($class_id, $subscribed_ids, true);
    }

    /**
     * Gemeinsamer Guard-Vorlauf: Nonce + Capability.
     *
     * Steht in jeder der fünf Methoden VOR jeder Parameterverarbeitung.
     * `check_ajax_referer()` und `wp_send_json_error()` beenden die Anfrage
     * selbst, die Methode kehrt in diesen Fällen nicht zurück.
     *
     * @return void
     */
    private function guard_lehrperson() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }
    }

    /**
     * Die Klasse einer Notiz ermitteln UND den Zugriff darauf prüfen.
     *
     * SICHERHEITSKERN DIESES ARBEITSPAKETS: Für Operationen auf EINER Notiz
     * (abhaken/bearbeiten/löschen) wird die `class_id` IMMER aus der
     * Datenbankzeile der Notiz gelesen, NIEMALS aus $_POST['class_id'].
     * Andernfalls könnte ein manipulierter Parameter die Berechtigungsprüfung
     * gegen eine eigene Klasse laufen lassen, während `note_id` zu einer
     * fremden Klasse gehört (Confused-Deputy).
     *
     * Beendet die Anfrage bei fehlender Notiz oder fehlendem Zugriff.
     *
     * @param int $note_id
     * @return int class_id der Notiz
     */
    private function require_note_access(int $note_id): int {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT class_id FROM ' . CBD_TABLE_NOTES . ' WHERE id = %d',
            $note_id
        ));

        if (!$row) {
            wp_send_json_error(array('message' => 'Notiz nicht gefunden.'));
        }

        if (!$this->can_access_class((int) $row->class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        return (int) $row->class_id;
    }

    // =========================================================================
    // AJAX: NOTIZEN LESEN / ANLEGEN (klassenbezogen)
    // =========================================================================

    /**
     * AJAX: Alle Notizen einer Klasse lesen.
     *
     * Reihenfolge fest: offene zuerst (älteste zuerst), erledigte danach.
     */
    public function ajax_fragenwand_get_notes() {
        $this->guard_lehrperson();

        $class_id = intval($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, `text`, ist_erledigt, ist_schueler_frage FROM ' . CBD_TABLE_NOTES .
            ' WHERE class_id = %d ORDER BY ist_erledigt ASC, created_at ASC, id ASC',
            $class_id
        ));

        $notes = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                // Typen normalisieren: $wpdb liefert alles als String zurück.
                // Ein String "0" ist in JavaScript truthy — eine abgehakte
                // Notiz wäre sonst im Frontend nicht von einer offenen zu
                // unterscheiden.
                $notes[] = array(
                    'id'                 => (int) $row->id,
                    'text'               => (string) $row->text,
                    'ist_erledigt'       => (bool) intval($row->ist_erledigt),
                    // Vorhaben „Schüler-Fragen": reines HERKUNFTS-Flag für die
                    // farbliche Kennzeichnung. Es sagt NICHT, WER gefragt hat —
                    // das wird bewusst nirgends gespeichert.
                    'ist_schueler_frage' => (bool) intval($row->ist_schueler_frage),
                );
            }
        }

        wp_send_json_success(array('notes' => $notes));
    }

    /**
     * AJAX: Neue Notiz in einer Klasse anlegen.
     */
    public function ajax_fragenwand_add_note() {
        $this->guard_lehrperson();

        $class_id = intval($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // wp_unslash() vor der Bereinigung: $_POST kommt in WordPress
        // maskiert an, ohne das Entfernen landete aus "Schüler's Frage"
        // ein "Schüler\'s Frage" in der Datenbank.
        $text = sanitize_textarea_field(wp_unslash($_POST['text'] ?? ''));
        if ('' === $text) {
            wp_send_json_error(array('message' => 'Text darf nicht leer sein.'));
        }
        // AP-2.fix1 (Befund AP-2.rev, Schweregrad mittel): Ohne Längenbegrenzung
        // konnte eine sehr lange Notiz je nach sql_mode entweder abgelehnt oder
        // stillschweigend abgeschnitten werden. Eine feste Obergrenze verhindert
        // beides und gibt eine klare Fehlermeldung statt eines DB-Fehlers.
        if (mb_strlen($text) > 5000) {
            wp_send_json_error(array('message' => 'Text ist zu lang (maximal 5000 Zeichen).'));
        }

        global $wpdb;

        $eingefuegt = $wpdb->insert(CBD_TABLE_NOTES, array(
            'class_id'     => $class_id,
            'teacher_id'   => get_current_user_id(),
            'text'         => $text,
            'ist_erledigt' => 0,
        ));

        // AP-2.fix1: $wpdb->insert() liefert false bei einem DB-Fehler. Ohne
        // diese Prüfung meldete der Endpunkt live nachgewiesen "success" mit
        // "id":0, obwohl gar keine Zeile entstanden war (Phantom-Notiz).
        if (false === $eingefuegt) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success(array('id' => (int) $wpdb->insert_id));
    }

    // =========================================================================
    // AJAX: EINZELNE NOTIZ (class_id IMMER aus der Datenbankzeile)
    // =========================================================================

    /**
     * AJAX: Notiz abhaken bzw. wieder öffnen (Umschalter).
     */
    public function ajax_fragenwand_toggle_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        global $wpdb;

        $ergebnis = $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET ist_erledigt = 1 - ist_erledigt, updated_at = %s WHERE id = %d',
            current_time('mysql'), $note_id
        ));

        // AP-2.fix1: $wpdb->query() liefert false bei einem DB-Fehler
        // (0 betroffene Zeilen ist dagegen kein Fehler, kann aber praktisch
        // nicht auftreten, da require_note_access() die Existenz bereits
        // geprüft hat).
        if (false === $ergebnis) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Text einer Notiz ändern.
     */
    public function ajax_fragenwand_edit_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        $text = sanitize_textarea_field(wp_unslash($_POST['text'] ?? ''));
        if ('' === $text) {
            wp_send_json_error(array('message' => 'Text darf nicht leer sein.'));
        }
        if (mb_strlen($text) > 5000) {
            wp_send_json_error(array('message' => 'Text ist zu lang (maximal 5000 Zeichen).'));
        }

        global $wpdb;

        $ergebnis = $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET `text` = %s, updated_at = %s WHERE id = %d',
            $text, current_time('mysql'), $note_id
        ));

        if (false === $ergebnis) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Notiz löschen.
     */
    public function ajax_fragenwand_delete_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        global $wpdb;

        $geloescht = $wpdb->delete(CBD_TABLE_NOTES, array('id' => $note_id));

        if (false === $geloescht) {
            wp_send_json_error(array('message' => 'Löschen fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    // =========================================================================
    // REST: SCHÜLER-LESEZUGRIFF (cbd/v1/fragenwand)
    // =========================================================================

    /**
     * Die REST-Route für Schüler anmelden (Hook `rest_api_init`).
     *
     * BEWUSST OHNE `args`-Deklaration. `classroom` und `token` werden nicht
     * als REST-Parameter deklariert, sondern von
     * `CBD_Classroom_Gate::sitzung()` unverändert aus `$_GET` gelesen. Eine
     * Deklaration mit `'type' => 'integer'` hätte bei einem unsinnigen Wert
     * (`?classroom=abc`) eine HTTP-400-Antwort `rest_invalid_param` erzeugt —
     * also eine ANDERE Antwort als die einheitliche 404-Ablehnung. Genau
     * diese Unterscheidbarkeit soll es hier nicht geben. Eine zweite Fassung
     * der Token-Prüfung entsteht dabei nicht: gedeutet wird das Token
     * ausschließlich im Gate.
     *
     * Der Methodenname deckt sich mit der WordPress-Funktion
     * `register_rest_route()`, die hier aufgerufen wird — PHP unterscheidet
     * Methoden und globale Funktionen sauber, ein Konflikt entsteht nicht.
     *
     * @return void
     */
    public function register_rest_route() {
        // ZWEI ENDPUNKT-DEFINITIONEN AUF DERSELBEN ROUTE, nicht zwei Routen:
        // `register_rest_route()` nimmt ein Array von Definitionen entgegen und
        // wählt anhand der HTTP-Methode aus. Der Schüler-Schreibweg
        // (Vorhaben „Schüler-Fragen") ist inhaltlich dieselbe Fragenwand
        // derselben Sitzung — eine zweite Route wäre eine zweite Adresse für
        // dieselbe Sache, und `baueAbrufUrl()` im Frontend müsste dann zwei
        // Basen kennen statt einer.
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            array(
                'methods'  => 'GET',
                'callback' => array($this, 'rest_get_notes_for_student'),
                // Die gesamte Autorisierung steckt im Callback: Schüler sind nie
                // angemeldet, ein Capability-Callback schlösse sie aus. Vorbild:
                // CBD_Block_Content_API.
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'  => 'POST',
                'callback' => array($this, 'rest_add_note_from_student'),
                // Gleiche Begründung wie beim GET: Die Prüfkette steckt
                // vollständig im Callback (Sitzung → Text → Klassenschalter).
                'permission_callback' => '__return_true',
                // BEWUSST OHNE `args`-Deklaration, aus demselben Grund wie oben:
                // Eine Typ-/Pflichtangabe an `text` erzeugte eine abweichende
                // HTTP-400-Antwort (`rest_missing_callback_param`), BEVOR der
                // Callback die Sitzung geprüft hat. Wer keine gültige Sitzung
                // hat, soll aber immer dieselbe 404 sehen — sonst ließe sich am
                // Antwortunterschied ablesen, dass die Route überhaupt etwas
                // annimmt. Die Prüfung des Textes passiert deshalb im Callback,
                // NACH der Sitzungsprüfung.
            ),
        ));
    }

    /**
     * GET /wp-json/cbd/v1/fragenwand?classroom=<id>&token=<token>
     *
     * Liefert die Notizen DER KLASSE, DIE ZUR SITZUNG GEHÖRT — nie die einer
     * anderen. Die Klassen-ID stammt ausschließlich aus der vom Gate
     * geprüften Sitzung, niemals aus einem Request-Parameter: `?classroom=`
     * allein ist nur ein Anspruch, geprüft wird er gegen den Transient
     * `cbd_classroom_<token>`.
     *
     * DIE KETTE, IN DIESER REIHENFOLGE — jeder Fehlschlag endet sofort in
     * derselben Ablehnung:
     *
     *   1. `nocache_headers()`, IMMER und als Erstes, ohne Bedingung.
     *      Dieselbe URL liefert je nach Sitzung völlig andere Inhalte; ein
     *      Cache dürfte sie nie verwechseln. (Die REST-Schnittstelle sendet
     *      die Kopfzeilen von sich aus nur für Angemeldete — der Filter
     *      `rest_send_nocache_headers` hat `is_user_logged_in()` als Vorgabe.
     *      Für Schüler gäbe es sie also sonst gar nicht.)
     *   2. Der geteilte Helfer `CBD_Classroom_Gate::sitzung()` muss
     *      existieren — sonst Ablehnung statt eines Fatal Errors.
     *   3. Gültige Klassensitzung.
     *   4. Plausible `class_id` aus der Sitzung.
     *
     * KEINE ZUSÄTZLICHE FREIGABEPRÜFUNG JE NOTIZ. Anders als bei
     * `cbd/v1/block-html` (dort muss der einzelne Container für die Klasse
     * „behandelt" sein) gibt es für die Fragenwand kein Äquivalent zu einem
     * freigegebenen Objekt: Es existiert genau EINE Wand je Klasse, und jede
     * ihrer Notizen ist für deren Schüler bestimmt. Beim Lesen von
     * `CBD_Classroom_Gate::sitzung()` fand sich kein Hinweis, der dagegen
     * spräche — die Methode prüft ausschließlich die Sitzung selbst und trifft
     * keine Aussage über einzelne Objekte.
     *
     * @param WP_REST_Request $request Wird nicht ausgewertet — die Sitzung
     *                                 kommt aus dem Gate, nicht aus dem
     *                                 Request.
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_notes_for_student($request) {
        // ---- (1) Kein Zwischenspeicher, in JEDEM Antwortpfad ----------------
        // Steht ganz oben und ohne Bedingung, damit kein späteres `return`
        // daran vorbeikommt.
        nocache_headers();

        // ---- (2) Der geteilte Helfer muss da sein ---------------------------
        if (!class_exists('CBD_Classroom_Gate')
            || !method_exists('CBD_Classroom_Gate', 'sitzung')) {
            return $this->fragenwand_ablehnung();
        }

        // ---- (3) Gültige Klassensitzung -------------------------------------
        // Die Methode ist parameterlos und liest `?classroom=`/`?token=`
        // selbst aus $_GET. Rückgabe: array('class_id' => int,
        // 'class_name' => string) — oder null, wenn das Klassensystem
        // abgeschaltet ist, Parameter fehlen, der Transient
        // `cbd_classroom_<token>` fehlt/abgelaufen ist oder die `class_id`
        // darin nicht zu `?classroom=` passt.
        $sitzung = CBD_Classroom_Gate::sitzung();

        if (!is_array($sitzung) || !isset($sitzung['class_id'])) {
            return $this->fragenwand_ablehnung();
        }

        // ---- (4) Plausible Klassen-ID ---------------------------------------
        $class_id = intval($sitzung['class_id']);
        if ($class_id <= 0) {
            return $this->fragenwand_ablehnung();
        }

        // ---- Notizen lesen ---------------------------------------------------
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, `text`, ist_erledigt, ist_schueler_frage FROM ' . CBD_TABLE_NOTES .
            ' WHERE class_id = %d ORDER BY ist_erledigt ASC, created_at ASC, id ASC',
            $class_id
        ));

        $notes = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                // Minimalprinzip: NUR diese vier Felder. Kein `class_id`,
                // kein `teacher_id`, keine Zeitstempel — nichts davon braucht
                // die Leseansicht, und jedes zusätzliche Feld wäre eine
                // Auskunft, um die niemand gebeten hat.
                //
                // `ist_schueler_frage` ist seit dem Vorhaben „Schüler-Fragen"
                // dabei und verrät nichts Zusätzliches über Personen: Es sagt
                // nur, DASS die Frage von einem Schüler kam, nie von welchem —
                // die Spalte enthält keine Identität, weil beim Einfügen keine
                // gespeichert wird.
                //
                // Typen wie in ajax_fragenwand_get_notes(): $wpdb liefert
                // alles als String, und ein String "0" ist in JavaScript
                // truthy — eine offene Notiz erschiene sonst als abgehakt.
                $notes[] = array(
                    'id'                 => (int) $row->id,
                    'text'               => (string) $row->text,
                    'ist_erledigt'       => (bool) intval($row->ist_erledigt),
                    'ist_schueler_frage' => (bool) intval($row->ist_schueler_frage),
                );
            }
        }

        // EINMALIG JE ANTWORT, nicht je Notiz: Der Schalter gilt für die ganze
        // Klasse. Das Frontend blendet daran sein Eingabefeld ein oder aus.
        // ES IST KEINE ZUGANGSPRÜFUNG — die steckt in
        // `rest_add_note_from_student()` und läuft bei jedem Absenden erneut.
        // Ein Browser, der dieses Feld ignoriert und trotzdem sendet, wird dort
        // abgewiesen.
        return rest_ensure_response(array(
            'notes'                 => $notes,
            'schuelerFragenErlaubt' => $this->schueler_fragen_erlaubt($class_id),
        ));
    }

    /**
     * Darf in dieser Klasse ein Schüler selbst Fragen einreichen?
     *
     * Liest ausschließlich `wp_cbd_classes.schueler_fragen_erlaubt`. Die
     * `$class_id` MUSS aus einer geprüften Sitzung stammen, nie aus dem Request
     * — beide Aufrufer halten sich daran (`rest_get_notes_for_student()` und
     * `rest_add_note_from_student()` holen sie aus
     * `CBD_Classroom_Gate::sitzung()`).
     *
     * Eine unbekannte Klasse, ein Datenbankfehler oder eine fehlende Spalte
     * ergeben `false` — im Zweifel also „nicht erlaubt", nie „erlaubt".
     *
     * @since Vorhaben „Schüler-Fragen"
     * @param int $class_id
     * @return bool
     */
    private function schueler_fragen_erlaubt(int $class_id): bool {
        global $wpdb;

        if ($class_id <= 0) {
            return false;
        }

        $wert = $wpdb->get_var($wpdb->prepare(
            'SELECT schueler_fragen_erlaubt FROM ' . CBD_TABLE_CLASSES . ' WHERE id = %d',
            $class_id
        ));

        return (bool) intval($wert);
    }

    /**
     * POST /wp-json/cbd/v1/fragenwand?classroom=<id>&token=<token>
     *
     * Der EINZIGE Schreibweg für Schüler — und er kann ausschließlich
     * HINZUFÜGEN. Abhaken, Bearbeiten und Löschen bleiben den fünf
     * Lehrer-AJAX-Actions vorbehalten, auch für Fragen, die von Schülern kamen.
     *
     * ANONYM, UND ZWAR STRUKTURELL: Es wird nirgends festgehalten, welcher
     * Schüler eine Frage gestellt hat — `teacher_id` bekommt 0, es gibt kein
     * zweites Feld dafür, und die Sitzung selbst kennt nur die Klasse, keine
     * Person. Genau deshalb kann ein Schüler seine eigene Frage später auch
     * nicht wiederfinden, ändern oder zurücknehmen: Es gibt schlicht kein
     * Merkmal, an dem „meine" Frage erkennbar wäre. Das ist Absicht, kein
     * fehlendes Feature.
     *
     * DIE KETTE, IN DIESER REIHENFOLGE:
     *
     *   1. `nocache_headers()`, IMMER und als Erstes, ohne Bedingung — wie beim
     *      Lese-Endpunkt.
     *   2. Gate-Klasse vorhanden und gültige Klassensitzung. Jeder Fehlschlag
     *      hier endet in DERSELBEN einheitlichen 404 wie beim Lesen
     *      (`fragenwand_ablehnung()`): Wer keine Sitzung hat, darf am
     *      Antwortverhalten nicht ablesen können, ob es die Klasse gibt.
     *   3. Text lesen und prüfen (leer / über 5000 Zeichen).
     *   4. Klassenschalter `schueler_fragen_erlaubt` prüfen.
     *   5. Einfügen, Rückgabewert prüfen (Muster aus AP-2.fix1).
     *
     * WARUM SCHRITT 3 UND 4 SPRECHENDE FEHLER LIEFERN DÜRFEN, SCHRITT 2 ABER
     * NICHT: Wer bis dahin gekommen ist, hat bereits eine gültige Sitzung
     * DIESER Klasse — „Text fehlt" oder „für diese Klasse nicht aktiviert"
     * verrät ihm also nichts, was er nicht ohnehin schon wüsste. Die
     * einheitliche 404 aus AP-2.3 soll das Durchprobieren fremder Klassen-IDs
     * und Tokens verhindern; diese beiden Meldungen helfen dabei nicht.
     *
     * SCHRITT 3 STEHT VOR SCHRITT 4, weil ein leerer Text auch dann ein
     * Eingabefehler ist, wenn die Klasse den Schalter gesetzt hat — und die
     * Reihenfolge so unabhängig davon bleibt, welche Klasse gerade dran ist.
     *
     * @since Vorhaben „Schüler-Fragen"
     * @param WP_REST_Request $request Nur der Textkörper wird ausgewertet; die
     *                                 Sitzung kommt aus dem Gate.
     * @return WP_REST_Response|WP_Error
     */
    public function rest_add_note_from_student($request) {
        // ---- (1) Kein Zwischenspeicher, in JEDEM Antwortpfad ----------------
        nocache_headers();

        // ---- (2) Gültige Klassensitzung -------------------------------------
        if (!class_exists('CBD_Classroom_Gate')
            || !method_exists('CBD_Classroom_Gate', 'sitzung')) {
            return $this->fragenwand_ablehnung();
        }

        $sitzung = CBD_Classroom_Gate::sitzung();

        if (!is_array($sitzung) || !isset($sitzung['class_id'])) {
            return $this->fragenwand_ablehnung();
        }

        $class_id = intval($sitzung['class_id']);
        if ($class_id <= 0) {
            return $this->fragenwand_ablehnung();
        }

        // ---- (3) Text lesen und prüfen --------------------------------------
        // BEWUSST OHNE `wp_unslash()` — anders als `ajax_fragenwand_add_note()`
        // ein paar Zeilen weiter oben, und das ist kein Versehen: Jene Methode
        // liest roh aus `$_POST`, das WordPress vor jedem Request maskiert
        // (`wp_magic_quotes()`), dort MUSS die Maskierung weg. Hier kommt der
        // Wert aus `WP_REST_Request`, und die REST-Schicht hat das bereits
        // erledigt — `WP_REST_Server::serve_request()` ruft
        // `set_body_params(wp_unslash($_POST))` auf, und ein JSON-Rumpf wird
        // ohnehin nie maskiert. Ein zweites `wp_unslash()` würde deshalb
        // ECHTE Backslashes aus dem Fragetext entfernen (aus „C:\Pfad" würde
        // „C:Pfad").
        //
        // Die 5000-Zeichen-Grenze ist zeichengleich mit dem Lehrer-Weg
        // (AP-2.fix1) — es gibt eine Regel für die Textlänge einer Notiz,
        // nicht zwei.
        $text = sanitize_textarea_field((string) $request->get_param('text'));

        if ('' === $text) {
            return new WP_Error(
                'cbd_fragenwand_text_fehlt',
                __('Text darf nicht leer sein.', 'container-block-designer'),
                array('status' => 400)
            );
        }

        if (mb_strlen($text) > 5000) {
            return new WP_Error(
                'cbd_fragenwand_text_zu_lang',
                __('Text ist zu lang (maximal 5000 Zeichen).', 'container-block-designer'),
                array('status' => 400)
            );
        }

        // ---- (4) Ist der Schalter für DIESE Klasse gesetzt? -----------------
        if (!$this->schueler_fragen_erlaubt($class_id)) {
            return new WP_Error(
                'cbd_fragenwand_nicht_aktiviert',
                __('Für diese Klasse ist das Stellen von Fragen nicht aktiviert.', 'container-block-designer'),
                array('status' => 403)
            );
        }

        // ---- (5) Einfügen ---------------------------------------------------
        global $wpdb;

        $eingefuegt = $wpdb->insert(CBD_TABLE_NOTES, array(
            'class_id'           => $class_id,
            // 0, NICHT die ID irgendeines Nutzers: Es gibt hier keinen
            // angemeldeten Nutzer, und es soll auch keiner hineingeschrieben
            // werden. NULL wäre gleichwertig gemeint, die Spalte ist aber
            // `NOT NULL` — 0 ist der Wert, den sie tragen kann und der „niemand"
            // bedeutet.
            'teacher_id'         => 0,
            'text'               => $text,
            'ist_erledigt'       => 0,
            'ist_schueler_frage' => 1,
        ));

        // Muster aus AP-2.fix1: Ohne diese Prüfung meldete ein fehlgeschlagener
        // Insert trotzdem Erfolg — mit „id": 0 (Phantom-Notiz).
        if (false === $eingefuegt) {
            return new WP_Error(
                'cbd_fragenwand_speichern_fehlgeschlagen',
                __('Speichern fehlgeschlagen.', 'container-block-designer'),
                array('status' => 500)
            );
        }

        // Nur die ID zurück — das Frontend lädt die Liste danach ohnehin neu
        // und bekommt die Notiz dabei an ihrer sortierten Stelle.
        return rest_ensure_response(array('id' => (int) $wpdb->insert_id));
    }

    /**
     * Die einheitliche Ablehnung dieses Endpunkts.
     *
     * Zeichengleich für JEDEN Fehlschlag: Gate-Klasse fehlt, keine Sitzung,
     * abgelaufenes oder gefälschtes Token, `?classroom=` passt nicht zum
     * Token, unplausible Klassen-ID. Es darf sich nicht ablesen lassen, WORAN
     * es gelegen hat — sonst wäre der Endpunkt ein Prüfstand für geratene
     * Klassen-IDs und Tokens.
     *
     * @return WP_Error
     */
    private function fragenwand_ablehnung() {
        return new WP_Error(
            self::REST_FEHLERCODE,
            __('Die Fragenwand ist nicht verfügbar.', 'container-block-designer'),
            array('status' => self::REST_FEHLERSTATUS)
        );
    }
}

// Initialize singleton
CBD_Fragenwand::get_instance();
