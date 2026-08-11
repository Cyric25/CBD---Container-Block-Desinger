<?php
/**
 * Container Block Designer — Klassen-Durchlass für gesperrte Seiten
 *
 * Das Theme „FOS Online Schulbuch" kann Seiten sperren („nur für
 * Lehrpersonen", Meta `_simple_clean_nur_lehrpersonen`). Für nicht
 * angemeldete Besucher verschwinden sie überall und liefern beim Aufruf eine
 * Hinweisseite mit HTTP 403.
 *
 * Diese Klasse öffnet diese Sperre in genau einem Fall: Es liegt eine gültige
 * Klassensitzung vor UND die Seite enthält Container, die für diese Klasse als
 * „behandelt" markiert sind.
 *
 * DIE NAHT ZUM THEME
 * Das Theme fragt per Filter nach:
 *
 *     apply_filters('simple_clean_lehrerseite_freigeben', false, $post_id)
 *
 * Der Standardwert ist `false`. Diese Klasse ist die einzige Stelle, die ihn
 * öffnet. Fehlt das Plugin, ist es abgeschaltet oder greift der Filter nicht,
 * bleibt die Seite gesperrt — ein Fehler in der Naht zeigt zu wenig, nie zu
 * viel.
 *
 * Umgekehrt gilt: Fehlt das Theme, gibt es gar keine Sperre. Alle Zugriffe auf
 * Theme-Funktionen sind deshalb mit `function_exists()` abgesichert.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.87
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Classroom_Gate {

    /**
     * Singleton-Instanz
     *
     * @var CBD_Classroom_Gate|null
     */
    private static $instance = null;

    /**
     * Gemerkte Sitzung für die Dauer eines Aufrufs.
     * false = noch nicht ermittelt, null = keine gültige Sitzung.
     *
     * @var array|null|false
     */
    private static $sitzung = false;

    /**
     * @return CBD_Classroom_Gate
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Der Filter wird IMMER registriert, auch wenn das Klassensystem
        // abgeschaltet ist. Die Prüfung steckt in sitzung(); so gibt es nur
        // einen Ort, an dem über die Freigabe entschieden wird.
        add_filter('simple_clean_lehrerseite_freigeben', array($this, 'seite_freigeben'), 10, 2);

        // Priorität 8 ist wesentlich: do_blocks() hängt auf 9. Die Reduktion
        // muss greifen, solange der Inhalt noch Blockmarkup ist.
        add_filter('the_content', array($this, 'inhalt_reduzieren'), 8);
    }

    /**
     * Gemerkte Sitzung verwerfen (Tests, langlaufende Prozesse).
     *
     * @return void
     */
    public static function sitzung_vergessen() {
        self::$sitzung = false;
    }

    /**
     * Die gültige Klassensitzung des aktuellen Aufrufs — oder null.
     *
     * Wie die Sitzung entsteht: Der Schüler meldet sich über den Shortcode
     * `[cbd_classroom]` mit dem Klassenpasswort an
     * (`CBD_Classroom::ajax_student_auth()`). Erfolgreich legt das ein Token
     * als Transient `cbd_classroom_<token>` für 24 Stunden ab. Danach hängen
     * alle Links `?classroom=<id>&token=<token>` an.
     *
     * MASSGEBLICH IST DER TRANSIENT, NICHT DER URL-PARAMETER. Stimmt die
     * `class_id` im Transient nicht mit `?classroom=` überein, gilt die
     * Sitzung als ungültig — sonst könnte man mit einem gültigen Token einer
     * beliebigen Klasse Freigaben einer anderen erschleichen.
     *
     * @return array|null array('class_id' => int, 'class_name' => string)
     */
    public static function sitzung() {
        if (false !== self::$sitzung) {
            return self::$sitzung;
        }

        self::$sitzung = null;

        if (!class_exists('CBD_Classroom') || !CBD_Classroom::is_enabled()) {
            return self::$sitzung;
        }

        $classroom = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
        $token     = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if ($classroom <= 0 || '' === $token) {
            return self::$sitzung;
        }

        $daten = get_transient('cbd_classroom_' . $token);

        if (!is_array($daten) || !isset($daten['class_id'])) {
            return self::$sitzung;
        }

        if ((int) $daten['class_id'] !== $classroom) {
            return self::$sitzung;
        }

        self::$sitzung = array(
            'class_id'   => (int) $daten['class_id'],
            'class_name' => isset($daten['class_name']) ? (string) $daten['class_name'] : '',
        );

        return self::$sitzung;
    }

    /**
     * Bedient den Theme-Filter `simple_clean_lehrerseite_freigeben`.
     *
     * Gibt NUR dann `true` zurück, wenn beide Bedingungen erfüllt sind:
     * gültige Klassensitzung UND mindestens ein für diese Klasse behandelter
     * Container auf dieser Seite.
     *
     * @param bool $frei    bisheriger Wert (Standard des Themes: false)
     * @param int  $post_id
     * @return bool
     */
    public function seite_freigeben($frei, $post_id) {
        // Hat jemand anderes die Seite schon freigegeben, nicht widersprechen.
        if ($frei) {
            return true;
        }

        $sitzung = self::sitzung();
        if (null === $sitzung) {
            return false;
        }

        $behandelt = CBD_Classroom::behandelte_container($sitzung['class_id'], (int) $post_id);

        return !empty($behandelt);
    }

    // =========================================================================
    // SERVERSEITIGE REDUKTION (AP-2.3)
    // =========================================================================

    /**
     * Auf einer gesperrten Seite nur die freigegebenen Container ausgeben.
     *
     * WARUM SERVERSEITIG: Der bestehende Filter
     * `assets/js/classroom-page-filter.js` versteckt nicht behandelte
     * Container nur im Browser (`$container.hide()`). Der vollständige Inhalt
     * steht weiterhin im HTML und ist über „Seitenquelltext anzeigen" lesbar.
     * Für eine Lösungsseite ist das kein Schutz.
     *
     * DER GELTUNGSBEREICH IST ENG UND MUSS ES BLEIBEN. Alle vier Bedingungen
     * müssen erfüllt sein:
     *   1. Ausgabe des Hauptinhalts einer einzelnen Seite
     *   2. Besucher ist NICHT angemeldet
     *   3. die Seite ist gesperrt
     *   4. es liegt eine gültige Klassensitzung vor
     *
     * Fehlt eine davon, wird der Inhalt unverändert durchgereicht. Eine
     * Reduktion auf einer nicht gesperrten Seite wäre ein schwerer Fehler —
     * sie ließe Inhalte im laufenden Betrieb verschwinden. Die Bedingungen
     * sind deshalb mit UND verknüpft; wer hier eine Oder-Verknüpfung einbaut,
     * bricht genau das.
     *
     * @param string $content
     * @return string
     */
    public function inhalt_reduzieren($content) {
        // (1) Nur der Hauptinhalt einer einzelnen Seite.
        if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // (2) Angemeldete sehen alles — sie behalten auch die bisherige
        //     clientseitige Vorschau des Klassenmodus.
        if (!function_exists('simple_clean_ist_lehrperson') || simple_clean_ist_lehrperson()) {
            return $content;
        }

        // (3) Nur auf gesperrten Seiten. Fehlt das Theme, gibt es keine
        //     Sperre und damit nichts zu reduzieren.
        $post_id = (int) get_the_ID();
        if ($post_id <= 0
            || !function_exists('simple_clean_seite_nur_lehrpersonen')
            || !simple_clean_seite_nur_lehrpersonen($post_id)) {
            return $content;
        }

        // (4) Nur mit gültiger Klassensitzung.
        $sitzung = self::sitzung();
        if (null === $sitzung) {
            return $content;
        }

        $freigegeben = CBD_Classroom::behandelte_container($sitzung['class_id'], $post_id);

        $bloecke = parse_blocks($content);
        $ausgabe = '';

        foreach ($bloecke as $block) {
            if (!self::block_erlaubt($block, $freigegeben)) {
                continue;
            }
            // Erlaubte Blöcke gehen unverändert durch den normalen
            // Renderpfad. Bewusst KEIN serialize_blocks() — der
            // Whitespace-Unterschied zwischen JavaScript- und PHP-Serializer
            // (siehe CLAUDE.md, „Block-Serializer") bleibt so ohne Bedeutung.
            $ausgabe .= render_block($block);
        }

        if ('' === trim($ausgabe)) {
            return '<p class="cbd-klassenansicht-leer">'
                . esc_html__('Für diese Klasse ist auf dieser Seite noch nichts freigegeben.', 'container-block-designer')
                . '</p>';
        }

        return $ausgabe;
    }

    /**
     * Darf dieser Block auf einer reduzierten Seite stehen bleiben?
     *
     * STANDARD IST ABLEHNUNG. Was kein Container-Block mit freigegebener
     * `stableId` ist, entfällt — auch freistehende Absätze und Überschriften.
     * Auf einer Lösungsseite ist alles Lösung, solange nichts anderes gesagt
     * wurde.
     *
     * @param array    $block       Eintrag aus parse_blocks()
     * @param string[] $freigegeben Basis-Bezeichner der behandelten Container
     * @return bool
     */
    public static function block_erlaubt($block, $freigegeben) {
        if (empty($freigegeben) || empty($block['blockName'])) {
            return false;
        }

        // Nur Container des CDB-Plugins. Ein fremder Blocktyp mit zufällig
        // passender Kennung zählt nicht.
        if (0 !== strpos($block['blockName'], 'container-block-designer/')) {
            return false;
        }

        $stable_id = '';
        if (!empty($block['attrs']['stableId'])) {
            $stable_id = (string) $block['attrs']['stableId'];
        }

        // RÜCKFALL FÜR ALTBESTÄNDE — nicht entfernen. Ältere Container tragen
        // die Kennung nur im gespeicherten HTML, nicht in den Attributen.
        // Dasselbe tut CBD_Block_Registration::render_block() (dort ab
        // Zeile ~899). Ohne diesen Rückfall verschwänden korrekt markierte
        // Blöcke stillschweigend aus der Klassenansicht.
        if ('' === $stable_id) {
            $html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
            if ('' === $html && !empty($block['innerContent'])) {
                $html = implode('', array_filter((array) $block['innerContent'], 'is_string'));
            }
            if (preg_match('/data-stable-id="([^"]+)"/', $html, $treffer)) {
                $stable_id = $treffer[1];
            }
        }

        if ('' === $stable_id) {
            return false;
        }

        $basis = CBD_Classroom::basis_container_id($stable_id);

        return in_array($basis, (array) $freigegeben, true);
    }
}

// Initialize singleton
CBD_Classroom_Gate::get_instance();
