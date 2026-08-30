<?php
/**
 * Container Block Designer — Klassenpuls: Signaturen für die Live-Aktualisierung
 *
 *     GET /wp-json/cbd/v1/klassenpuls?classroom=<id>&token=<t>[&page_id=<id>]
 *
 * Stufe 1 der zweistufigen Abfrage aus `PLAN-Klassenmodus-Live.md`: Der Browser
 * eines Schülers fragt diese Route im eingestellten Takt (Vorgabe 10 s) ab und
 * bekommt vier kurze Signaturen zurück. Ändert sich eine davon, holt Stufe 2
 * die eigentlichen Daten — über die BESTEHENDEN Endpunkte
 * (`cbd_get_page_classroom_data`, `cbd_student_get_data`,
 * `GET cbd/v1/fragenwand`), nicht über diesen hier.
 *
 * WARUM NUR ZAHLEN UND SIGNATUREN, NIEMALS INHALTE
 * Diese Route wird häufig und von jedem Schülerbrowser aufgerufen. Selbst eine
 * fehlerhafte Prüfung gäbe hier keinen Lösungstext preis, weil es nichts
 * herzugeben gibt: Die Antwort besteht aus vier md5-Kurzformen und einer Zahl.
 * Die Inhalte holen die Bestandsendpunkte mit ihren geprüften Ketten.
 *
 * WARUM `permission_callback => '__return_true'`
 * Zeichengleich zu `cbd/v1/block-html` (`class-cbd-block-content-api.php`) und
 * `cbd/v1/fragenwand` (`class-cbd-fragenwand.php`): Schülerinnen und Schüler
 * melden sich nie an, sie kommen über das Klassenpasswort. Ein
 * Capability-Callback schlösse genau die Zielgruppe aus. DIE GESAMTE
 * AUTORISIERUNG LIEGT DAMIT IM CALLBACK — siehe `liefere_puls()`.
 *
 * DIE TOKEN-DEUTUNG GIBT ES GENAU EINMAL
 * `CBD_Classroom_Gate::sitzung()` ist die einzige Stelle im Plugin, die
 * `?classroom=` und `?token=` gegen den gespeicherten Sitzungseintrag prüft.
 * Diese Datei schreibt dafür KEINE zweite Fassung und liest den Sitzungsspeicher
 * auch nicht selbst aus. `tools/test-klassenpuls.php` wacht darüber (Gruppe D).
 * Die `class_id` stammt ausschließlich aus der so geprüften Sitzung, nie aus
 * einem Request-Parameter: `?classroom=` allein ist nur eine Behauptung.
 *
 * GRUNDSATZ: STANDARD IST ABLEHNUNG. Fällt eine Prüfung aus (Gate-Klasse fehlt,
 * Sitzung ungültig, Tabellenkonstante unbekannt), wird abgelehnt, nicht
 * durchgelassen.
 *
 * ABLEHNUNG UND NICHTEXISTENZ ANTWORTEN ZEICHENGLEICH — ein Fehlercode, eine
 * Meldung, ein Status (404). Unterschiedliche Antworten wären ein Werkzeug, um
 * durch Durchprobieren Klassen-IDs oder Tokens zu erraten. Denselben Grundsatz
 * setzen die beiden oben genannten Bestandsendpunkte bereits um.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.118
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Klassenpuls {

    /**
     * REST-Namensraum. Derselbe wie bei den übrigen `cbd/v1`-Routen — die
     * Trennung liegt in der Route, nicht im Namensraum.
     */
    const REST_NAMESPACE = 'cbd/v1';

    /** Die Route. */
    const REST_ROUTE = '/klassenpuls';

    /**
     * Der EINZIGE Fehlercode dieses Endpunkts.
     *
     * Es gibt bewusst keine sprechenden Codes („Sitzung abgelaufen", „Klasse
     * unbekannt"): Jeder Unterschied wäre ein Kartierungswerkzeug.
     */
    const FEHLERCODE = 'cbd_puls_not_available';

    /** HTTP-Status jeder Ablehnung. */
    const FEHLERSTATUS = 404;

    /** Takt in Sekunden, wenn die Option fehlt oder unlesbar ist. */
    const TAKT_VORGABE = 10;

    /**
     * Kleinster zulässiger Takt in Sekunden.
     *
     * NICHT zu verwechseln mit 0: Der Wert 0 bedeutet „abgeschaltet" und liegt
     * bewusst AUSSERHALB dieses Bereichs (siehe `takt()`).
     */
    const TAKT_MIN = 5;

    /** Größter zulässiger Takt in Sekunden (5 Minuten). */
    const TAKT_MAX = 300;

    /** Name der Option, die den Takt einstellt. */
    const OPTION_TAKT = 'cbd_klassenpuls_takt';

    /**
     * Route auf `rest_api_init` anmelden.
     *
     * @return void
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Route registrieren.
     *
     * `classroom` und `token` sind echte Query-Parameter, damit
     * `CBD_Classroom_Gate::sitzung()` sie unverändert aus `$_GET` lesen kann —
     * genau wie bei `cbd/v1/block-html`. Eine zweite Fassung der Token-Prüfung
     * entsteht hier bewusst NICHT.
     *
     * KEINER der drei Parameter ist `required`. Ein `required => true` ließe
     * WordPress bei einer fehlenden Angabe mit `rest_missing_callback_param`
     * (HTTP 400) antworten, BEVOR der Callback überhaupt läuft — die Antwort
     * wiche also von der einheitlichen 404 ab. Fehlende Angaben sollen durch
     * dieselbe Tür gehen wie falsche: Ohne gültige Sitzung lehnt der Callback
     * ab, gleichgültig woran es lag. (Dieselbe Überlegung, aus der
     * `cbd/v1/fragenwand` ganz auf `args` verzichtet; hier bleiben die
     * Deklarationen, weil der Plan sie ausdrücklich vorsieht und sie die
     * Bereinigung der Werte übernehmen.)
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'liefere_puls'),
            // Die gesamte Autorisierung steckt im Callback. Siehe Kopfkommentar.
            'permission_callback' => '__return_true',
            'args' => array(
                'classroom' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Klassen-ID einer laufenden Klassensitzung.',
                ),
                'token' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Token der Klassensitzung.',
                ),
                'page_id' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Seite, fuer die Seiten- und Tafelsignatur gebildet werden.',
                ),
            ),
        ));
    }

    /**
     * Der Endpunkt. Autorisierung und Signaturbildung in einem.
     *
     * DIE KETTE, IN DIESER REIHENFOLGE — jeder Fehlschlag endet sofort in der
     * einheitlichen Ablehnung:
     *
     *   1. `nocache_headers()`, IMMER und als Erstes, ohne Bedingung. Dieselbe
     *      URL liefert je nach Sitzung völlig andere Signaturen; ein Cache
     *      dürfte sie nie verwechseln. (Die REST-Schnittstelle sendet die
     *      Kopfzeilen von sich aus nur für Angemeldete — der Filter
     *      `rest_send_nocache_headers` hat `is_user_logged_in()` als Vorgabe.
     *      Für Schüler gäbe es sie also sonst gar nicht.) Steht ganz oben,
     *      damit kein späteres `return` daran vorbeikommt.
     *   2. Die geteilten Helfer und Tabellenkonstanten müssen existieren —
     *      sonst Ablehnung statt eines Fatal Errors.
     *   3. Gültige Klassensitzung über `CBD_Classroom_Gate::sitzung()`.
     *   4. Plausible `class_id` aus der Sitzung.
     *
     * EIN ABGESCHALTETER PULS IST KEIN ABLEHNUNGSGRUND. Steht die Option auf 0,
     * gibt es trotzdem eine gültige Antwort — nur mit `takt: 0`. Der Browser
     * stellt daraufhin das Abfragen ein. Eine Ablehnung wäre hier falsch: Sie
     * ließe einen abgeschalteten Puls wie eine abgelaufene Sitzung aussehen,
     * und der Taktgeber würde die Sitzung endgültig verwerfen.
     *
     * @param WP_REST_Request $request Nur `page_id` wird ausgewertet. Klasse
     *                                 und Token kommen aus dem Gate, nicht aus
     *                                 dem Request.
     * @return WP_REST_Response
     */
    public static function liefere_puls($request) {
        // ---- (1) Kein Zwischenspeicher, in JEDEM Antwortpfad ----------------
        nocache_headers();

        // ---- (2) Voraussetzungen ------------------------------------------
        // Ohne das Gate gibt es keine geprüfte Sitzung — und ohne geprüfte
        // Sitzung keine Klassen-ID. Standard ist Ablehnung.
        if (!class_exists('CBD_Classroom_Gate')
            || !method_exists('CBD_Classroom_Gate', 'sitzung')
            || !class_exists('CBD_Classroom')) {
            return self::ablehnen();
        }

        // Die beiden immer benötigten Tabellen. Fehlten die Konstanten, stünde
        // in PHP 7.4 eine Warnung im Fehlerlog und ein unsinniger Tabellenname
        // in der Abfrage; ab PHP 8 wäre es ein Fatal Error. Beides ist
        // schlechter als eine saubere Ablehnung.
        if (!defined('CBD_TABLE_DRAWINGS') || !defined('CBD_TABLE_CLASS_PAGES')) {
            return self::ablehnen();
        }

        // ---- (3) Gültige Klassensitzung ------------------------------------
        // Die Methode ist parameterlos und liest `?classroom=`/`?token=` selbst
        // aus $_GET. Rückgabe: array('class_id' => int, 'class_name' => string)
        // — oder null, wenn das Klassensystem abgeschaltet ist, Parameter
        // fehlen, der Sitzungseintrag fehlt/abgelaufen ist oder die dort
        // hinterlegte Klasse nicht zu `?classroom=` passt.
        $sitzung = CBD_Classroom_Gate::sitzung();

        if (!is_array($sitzung) || !isset($sitzung['class_id'])) {
            return self::ablehnen();
        }

        // ---- (4) Plausible Klassen-ID --------------------------------------
        $class_id = (int) $sitzung['class_id'];
        if ($class_id <= 0) {
            return self::ablehnen();
        }

        // ---- Seitenbezug: eine Behauptung des Browsers, kein Recht ---------
        // `page_id` darf ungeprüft aus dem Request kommen: Sie schränkt die
        // Abfrage nur ZUSÄTZLICH ein (`WHERE class_id = %d AND page_id = %d`).
        // Die Klassengrenze zieht in jedem Fall die geprüfte `class_id`, und
        // herausfallen kann dabei nur eine Zahl, nie ein Inhalt.
        $page_id = 0;
        if (is_object($request) && method_exists($request, 'get_param')) {
            $roh_page_id = $request->get_param('page_id');
            // `is_scalar()` fängt `?page_id[]=1` ab — ohne die Hülle stünde bei
            // einem Direktaufruf eine PHP-Warnung im Fehlerlog.
            $page_id = is_scalar($roh_page_id) ? absint($roh_page_id) : 0;
        }

        // ---- Signaturen ----------------------------------------------------
        $antwort = array(
            'klasse'     => self::signatur_klasse($class_id),
            'fragenwand' => self::signatur_fragenwand($class_id),
        );

        if ($page_id > 0) {
            $seitenwerte        = self::signaturen_seite($class_id, $page_id);
            $antwort['seite']   = $seitenwerte['seite'];
            $antwort['tafel']   = $seitenwerte['tafel'];
        }

        // Der Takt kommt IMMER mit, auch wenn er 0 ist. Der Browser richtet
        // sich nach diesem Feld statt nach einer eigenen Konstante — ändert der
        // Betrieb die Option, folgt er beim nächsten Durchlauf.
        $antwort['takt'] = self::takt();

        return rest_ensure_response($antwort);
    }

    /**
     * Signaturen für eine einzelne Seite: Freigaben und Tafelbild, getrennt.
     *
     * EINE Abfrage, ZWEI Signaturen — und das ist der Kern dieses APs:
     * `seite` bewegt sich NUR bei einer Freigabe oder deren Rücknahme, `tafel`
     * bei jedem Schreibvorgang (auch beim bloßen Weiterzeichnen). Beides in
     * einer Signatur zusammenzufassen hieße, bei jedem Strich der Lehrperson
     * die Freigabe-Reaktion auszulösen — auf reduzierten Seiten also ein
     * Neuladen.
     *
     * `SUM(id * is_behandelt)` ist eine Prüfsumme über die MENGE der
     * freigegebenen Zeilen: Jeder einzelne Umschalter verändert sie zwangsläufig,
     * weil genau ein Summand hinzukommt oder wegfällt. Zusammen mit `COUNT(*)`
     * und `SUM(is_behandelt)` ist eine Kollision praktisch ausgeschlossen; ihre
     * Fehlerrichtung wäre zudem harmlos (die Änderung erschiene erst beim
     * nächsten Umschalten, es würde nie zu viel gezeigt).
     *
     * Bewusst NICHT über eine SQL-Funktion, die die Bezeichner zu einer
     * Zeichenkette verkettet: Deren Längengrenze (Vorgabe 1024 Byte) schneidet
     * ab etwa 44 Containern je Seite STILLSCHWEIGEND ab, friert die Signatur
     * ein und verschluckt damit jede weitere Änderung. Begründung ausführlich in
     * `PLAN-Klassenmodus-Live.md`, Abschnitt 4.
     *
     * Der Index `UNIQUE KEY class_page_container (class_id, page_id,
     * container_id)` trägt die `WHERE`-Klausel.
     *
     * @param int $class_id Aus der geprüften Sitzung.
     * @param int $page_id  Aus dem Request; grenzt nur zusätzlich ein.
     * @return array array('seite' => string, 'tafel' => string)
     */
    private static function signaturen_seite($class_id, $page_id) {
        global $wpdb;

        $zeile = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS anzahl,'
            . ' COALESCE(SUM(is_behandelt), 0) AS frei,'
            . ' COALESCE(SUM(id * is_behandelt), 0) AS pruefsumme,'
            . " COALESCE(MAX(updated_at), '') AS zuletzt"
            . ' FROM ' . CBD_TABLE_DRAWINGS
            . ' WHERE class_id = %d AND page_id = %d',
            $class_id,
            $page_id
        ));

        // Kein Ergebnis (Abfragefehler) verhält sich wie „nichts vorhanden".
        // Das ist die stille Richtung: Es wird nichts zusätzlich angezeigt.
        $anzahl     = is_object($zeile) ? (int) $zeile->anzahl : 0;
        $frei       = is_object($zeile) ? (int) $zeile->frei : 0;
        $pruefsumme = is_object($zeile) ? (int) $zeile->pruefsumme : 0;
        $zuletzt    = is_object($zeile) ? (string) $zeile->zuletzt : '';

        return array(
            'seite' => self::baue_signatur(array($anzahl, $frei, $pruefsumme)),
            'tafel' => self::baue_signatur(array($zuletzt)),
        );
    }

    /**
     * Signatur über den Zustand der ganzen Klasse.
     *
     * ZWEI Abfragen, eine Signatur. Die erste erfasst, welche Seiten überhaupt
     * freigegebene Container tragen; die zweite fängt Umsortierungen und von
     * Hand entfernte Seiten ab, die sich in der ersten nicht zeigen würden —
     * eine Seite ohne freigegebenen Container taucht dort nämlich gar nicht auf.
     *
     * @param int $class_id Aus der geprüften Sitzung.
     * @return string
     */
    private static function signatur_klasse($class_id) {
        global $wpdb;

        $freigaben = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(DISTINCT page_id) AS seiten,'
            . ' COALESCE(SUM(id), 0) AS pruefsumme'
            . ' FROM ' . CBD_TABLE_DRAWINGS
            . ' WHERE class_id = %d AND is_behandelt = 1',
            $class_id
        ));

        $zuordnung = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS anzahl,'
            . ' COALESCE(SUM(page_id * (sort_order + 1)), 0) AS ordnung'
            . ' FROM ' . CBD_TABLE_CLASS_PAGES
            . ' WHERE class_id = %d',
            $class_id
        ));

        $seiten     = is_object($freigaben) ? (int) $freigaben->seiten : 0;
        $pruefsumme = is_object($freigaben) ? (int) $freigaben->pruefsumme : 0;
        $anzahl     = is_object($zuordnung) ? (int) $zuordnung->anzahl : 0;
        $ordnung    = is_object($zuordnung) ? (int) $zuordnung->ordnung : 0;

        return self::baue_signatur(array($seiten, $pruefsumme, $anzahl, $ordnung));
    }

    /**
     * Signatur über die Fragenwand der Klasse.
     *
     * `MAX(updated_at)` fängt Textänderungen ab, die weder die Anzahl noch die
     * Prüfsumme bewegen würden — eine bearbeitete Notiz behält ihre `id`.
     *
     * ALTE INSTALLATION OHNE FRAGENWAND: Fehlt die Tabellenkonstante, wird
     * NICHT abgelehnt und auch keine Abfrage abgesetzt (die liefe sonst gegen
     * eine nicht existierende Tabelle und schriebe eine SQL-Fehlermeldung ins
     * Log). Stattdessen gibt es eine feste, gültige Signatur, die sich nie
     * ändert — für den Browser heißt das schlicht „an der Fragenwand tut sich
     * nichts".
     *
     * @param int $class_id Aus der geprüften Sitzung.
     * @return string
     */
    private static function signatur_fragenwand($class_id) {
        if (!defined('CBD_TABLE_NOTES')) {
            return self::baue_signatur(array(''));
        }

        global $wpdb;

        $zeile = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS anzahl,'
            . ' COALESCE(SUM(ist_erledigt), 0) AS erledigt,'
            . ' COALESCE(SUM(id), 0) AS pruefsumme,'
            . " COALESCE(MAX(updated_at), '') AS zuletzt"
            . ' FROM ' . CBD_TABLE_NOTES
            . ' WHERE class_id = %d',
            $class_id
        ));

        $anzahl     = is_object($zeile) ? (int) $zeile->anzahl : 0;
        $erledigt   = is_object($zeile) ? (int) $zeile->erledigt : 0;
        $pruefsumme = is_object($zeile) ? (int) $zeile->pruefsumme : 0;
        $zuletzt    = is_object($zeile) ? (string) $zeile->zuletzt : '';

        return self::baue_signatur(array($anzahl, $erledigt, $pruefsumme, $zuletzt));
    }

    /**
     * Der Takt in Sekunden — DIE EINZIGE STELLE, die die Option auslegt.
     *
     * AP-1.5 und AP-1.6 rufen diese Methode auf, statt die Grenzen zu
     * wiederholen. Stünden sie an zwei Stellen, liefen sie früher oder später
     * auseinander, und der Browser täktete anders als der Server annimmt.
     *
     * | Option                | Ergebnis                     |
     * |-----------------------|------------------------------|
     * | nicht gesetzt         | 10 (`TAKT_VORGABE`)          |
     * | `'quatsch'`, `array()`| 10 (`TAKT_VORGABE`)          |
     * | `'0'`, `'-5'`         | 0 — abgeschaltet             |
     * | `'2'`                 | 5 (`TAKT_MIN`)               |
     * | `'20'`                | 20                           |
     * | `'9999'`              | 300 (`TAKT_MAX`)             |
     *
     * DIE 0 IST KEIN GEKLEMMTER WERT, SONDERN EIN EIGENER ZUSTAND: Alles
     * Negative fällt auf 0 („aus"), NICHT auf `TAKT_MIN`. Wer die Notbremse
     * zieht, meint sie auch — ein stillschweigendes Hochsetzen auf 5 Sekunden
     * wäre das Gegenteil dessen, was er wollte. Ein unlesbarer Wert dagegen ist
     * keine Absichtserklärung und fällt auf die Vorgabe zurück, nicht auf „aus":
     * Ein Tippfehler in der Option soll die Funktion nicht abschalten.
     *
     * @return int 0 oder ein Wert zwischen `TAKT_MIN` und `TAKT_MAX`.
     */
    public static function takt() {
        $roh = get_option(self::OPTION_TAKT, null);

        // `is_numeric()` allein genügt nicht: Für ein Array wäre es zwar
        // `false`, aber ein Objekt mit `__toString()` käme sonst durch.
        if (is_array($roh) || is_object($roh) || !is_numeric($roh)) {
            return self::TAKT_VORGABE;
        }

        $wert = (int) round((float) $roh);

        if ($wert <= 0) {
            return 0;
        }

        if ($wert < self::TAKT_MIN) {
            return self::TAKT_MIN;
        }

        if ($wert > self::TAKT_MAX) {
            return self::TAKT_MAX;
        }

        return $wert;
    }

    /**
     * Aus skalaren Werten einen kurzen, stabilen Bezeichner bilden.
     *
     * Die reine Kernfunktion dieses Endpunkts: keine Datenbank, kein
     * WordPress, kein Zustand. Gleiche Eingabe ergibt immer dieselbe Ausgabe,
     * und die REIHENFOLGE ZÄHLT — `array(1, 2)` und `array(2, 1)` sind
     * verschiedene Zustände und bekommen verschiedene Signaturen.
     *
     * `null` wird VOR dem Verketten zu `''` normalisiert. `implode()` täte das
     * von sich aus, ab PHP 8.1 aber mit einer Verfallswarnung — und die
     * Zusicherung, dass „kein Wert" und „leerer Wert" dieselbe Signatur
     * ergeben, soll ausdrücklich im Code stehen, nicht aus einer Nebenwirkung
     * folgen.
     *
     * ZWÖLF ZEICHEN sind bewusst kurz: Die Signatur wird alle paar Sekunden
     * übertragen und ist kein Sicherheitsmerkmal, sondern ein Änderungsmelder.
     * Sie wird nie gegen etwas geprüft, das ein Angreifer wählen könnte — er
     * gewönne durch eine Kollision auch nichts, sie würde nur eine Änderung
     * verschlucken.
     *
     * @param array $werte Skalare Werte; `null` gilt als `''`.
     * @return string Genau 12 Zeichen aus [0-9a-f].
     */
    public static function baue_signatur($werte) {
        if (!is_array($werte)) {
            $werte = array($werte);
        }

        $teile = array();

        foreach ($werte as $wert) {
            if (null === $wert) {
                $teile[] = '';
                continue;
            }

            if (is_bool($wert)) {
                // `(string) false` wäre '' und damit von null nicht zu
                // unterscheiden — hier soll false ein eigener Wert bleiben.
                $teile[] = $wert ? '1' : '0';
                continue;
            }

            if (is_array($wert) || is_object($wert)) {
                // Kein Aufrufer übergibt so etwas; ohne diese Hülle gäbe es
                // aber eine PHP-Warnung statt eines brauchbaren Ergebnisses.
                $teile[] = '';
                continue;
            }

            $teile[] = (string) $wert;
        }

        return substr(md5(implode('|', $teile)), 0, 12);
    }

    /**
     * Die einheitliche Ablehnung dieses Endpunkts.
     *
     * Zeichengleich für JEDEN Fehlschlag: Gate-Klasse fehlt, Tabellenkonstante
     * fehlt, keine Sitzung, abgelaufenes oder gefälschtes Token, `?classroom=`
     * passt nicht zur Sitzung, unplausible Klassen-ID. Es darf sich nicht
     * ablesen lassen, WORAN es gelegen hat — sonst wäre der Endpunkt ein
     * Prüfstand für geratene Klassen-IDs und Tokens.
     *
     * Als `WP_REST_Response` mit `code`/`message` statt als `WP_Error`, damit
     * die Antwort zeichengleich zu `cbd/v1/block-html` ausfällt
     * (`CBD_Block_Content_API::ablehnen()`) — beide Endpunkte sollen von außen
     * nicht unterscheidbar sein.
     *
     * @return WP_REST_Response
     */
    private static function ablehnen() {
        return new WP_REST_Response(
            array(
                'code'    => self::FEHLERCODE,
                'message' => __('Der Klassenpuls ist nicht verfügbar.', 'container-block-designer'),
            ),
            self::FEHLERSTATUS
        );
    }
}
