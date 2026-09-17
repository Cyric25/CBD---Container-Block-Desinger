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
     * Unterordner der Pulsdateien unterhalb von `wp_upload_dir()['basedir']`
     * bzw. `['baseurl']` (Vorhaben „Schneller Klassenpuls", AP-1.2).
     *
     * Bewusst OHNE führenden und ohne abschließenden Schrägstrich — die
     * Trennzeichen setzen `pulsdatei_pfad()` und `pulsdatei_url()` selbst.
     */
    const UNTERORDNER = 'container-block-designer/klassenpuls';

    /**
     * Höchstzahl an Seiteneinträgen in einer Pulsdatei.
     *
     * Wird sie überschritten, lässt `baue_pulsdaten()` (AP-1.3) die Abbildung
     * `seiten` GANZ weg und setzt stattdessen `seiten_unvollstaendig` —
     * **niemals kürzen**. Ein still abgeschnittener Datensatz fröre die
     * Signatur ein und verschluckte jede weitere Änderung; genau gegen diese
     * Fehlerklasse hat sich das Vorgänger-Vorhaben schon bei jener SQL-Funktion
     * entschieden, die Bezeichner zu einer Zeichenkette verkettet: Deren
     * Längengrenze schneidet stillschweigend ab.
     */
    const SEITEN_OBERGRENZE = 400;

    /**
     * Name des taeglichen Aufraeum-Termins (AP-1.7).
     *
     * Steht als Konstante, weil er an drei Stellen gebraucht wird: beim
     * Anmelden der Aktion, beim Einplanen und beim Abmelden in der
     * Deaktivierungsroutine des Plugins.
     */
    const CRON_HAKEN = 'cbd_klassenpuls_aufraeumen';

    /** Hoechstalter einer liegengebliebenen Zwischendatei in Sekunden. */
    const TMP_HOECHSTALTER = 3600;

    /**
     * Takt der REST-Route, sobald die Pulsdatei gelesen wird — in Sekunden.
     *
     * Die Route hört damit auf, der Taktgeber zu sein, und wird zum
     * HERZSCHLAG: Sie prüft die Sitzung, nennt Takt und Dateiadresse und
     * repariert eine veraltete Datei. Den schnellen Takt macht die Datei.
     */
    const HERZSCHLAG = 60;

    /** Takt des Dateiabrufs in Sekunden, wenn die Option fehlt. */
    const TAKT_DATEI_VORGABE = 2;

    /** Kleinster zulässiger Dateitakt. `0` heißt „aus" und liegt darunter. */
    const TAKT_DATEI_MIN = 1;

    /** Größter zulässiger Dateitakt. */
    const TAKT_DATEI_MAX = 60;

    /** Name der Option, die den Dateitakt einstellt. */
    const OPTION_TAKT_DATEI = 'cbd_klassenpuls_takt_datei';

    /**
     * Klassen, deren Pulsdatei in dieser Anfrage neu geschrieben werden muss.
     *
     * Schlüssel sind die Klassen-IDs — so verschwinden Dubletten von selbst,
     * wenn dieselbe Klasse in einer Anfrage mehrfach gemeldet wird.
     *
     * @var array
     */
    private static $offene_klassen = array();

    /**
     * Ob die `shutdown`-Aktion in dieser Anfrage schon angemeldet wurde.
     *
     * Ohne diese Sperre hinge an jeder gemeldeten Änderung ein weiterer
     * Rückruf, und `schreibe_offene()` liefe mehrfach.
     *
     * @var bool
     */
    private static $shutdown_angemeldet = false;

    /**
     * Route auf `rest_api_init` anmelden.
     *
     * @return void
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));

        // Der einzige Zuhörer auf die Aktion, die die Schreibstellen in
        // `class-cbd-classroom.php` und `class-cbd-fragenwand.php` auslösen
        // (AP-1.5). Die Kopplung ist dadurch einseitig: Jene Dateien müssen
        // diese Klasse nicht kennen, und eine Aktion ohne Zuhörer ist
        // wirkungslos statt fehlerhaft.
        add_action('cbd_klassenmodus_geaendert', array(__CLASS__, 'merke_aenderung'), 10, 1);

        // Taeglicher Aufraeumdurchlauf (AP-1.7). Der Termin wird hier
        // eingeplant statt beim Aktivieren des Plugins: Der
        // Aktivierungshaken dieses Plugins feuert nachweislich nie (die
        // Hauptklasse entsteht erst auf `plugins_loaded`, und
        // `activate_plugin()` bindet die Datei erst danach ein -- siehe
        // `CLAUDE.md`, Abschnitt zur Datenbankreparatur). `wp_next_scheduled()`
        // verhindert, dass bei jedem Seitenaufruf ein weiterer Termin entsteht.
        add_action(self::CRON_HAKEN, array(__CLASS__, 'raeume_auf'));

        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled(self::CRON_HAKEN)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HAKEN);
            }
        }
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

        // ---- Herzschlag: Reparatur, nicht Routine (AP-1.6) -----------------
        // Geschrieben wird NUR, wenn die Datei fehlt oder veraltet ist.
        // Bedingungsloses Schreiben wäre ein Wettlauf gegen die
        // Schreibstellen — Begründung in `datei_veraltet()`.
        if (self::takt() > 0 && self::datei_veraltet($class_id)) {
            self::schreibe_pulsdatei($class_id);
        }

        // Die Adresse geht NUR an einen Browser, der bis hierher gekommen
        // ist, also die Sitzungsprüfung bestanden hat. Der Ablehnungspfad
        // (`ablehnen()`) nennt sie nie — dort steht ausschließlich der eine
        // Fehlercode, zeichengleich für jeden Ablehnungsgrund.
        $adresse = '';
        $pfad    = '';

        if (self::takt() > 0) {
            $adresse = self::pulsdatei_url($class_id);
            $pfad    = self::pulsdatei_pfad($class_id);
        }

        $antwort['datei'] = ('' !== $adresse && '' !== $pfad && file_exists($pfad))
            ? $adresse
            : null;

        // Bei abgeschaltetem Puls ist auch der schnelle Takt gegenstandslos:
        // Der Browser soll dann gar nichts abfragen, weder Route noch Datei.
        $antwort['takt_datei'] = self::takt() > 0 ? self::takt_datei() : 0;
        $antwort['herzschlag'] = self::HERZSCHLAG;

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
    // ---------------------------------------------------------------------
    // Herzschlag: Dateitakt und Reparatur (AP-1.6)
    // ---------------------------------------------------------------------

    /**
     * Takt des Dateiabrufs in Sekunden, `0` heißt abgeschaltet.
     *
     * Aufgebaut wie `takt()` und aus demselben Grund die EINZIGE Stelle, die
     * die Option `cbd_klassenpuls_takt_datei` auslegt.
     *
     * VERHÄLTNIS ZUR NOTBREMSE: Diese Option kann `cbd_klassenpuls_takt`
     * nicht aushebeln. Steht der Takt auf 0, wird `klassenpuls.js` gar nicht
     * erst eingereiht und es entsteht keine Pulsdatei — der Dateitakt ist
     * dann gegenstandslos, egal was hier steht. Umgekehrt schaltet `0` hier
     * nur den schnellen Weg ab; die Route tickt weiter wie vor diesem
     * Vorhaben.
     *
     * | Optionswert           | Ergebnis                     |
     * |-----------------------|------------------------------|
     * | nicht gesetzt         | 2 (`TAKT_DATEI_VORGABE`)     |
     * | `'quatsch'`, `array()`| 2 (`TAKT_DATEI_VORGABE`)     |
     * | `'0'`, `'-5'`         | 0 (aus)                      |
     * | `'99'`                | 60 (`TAKT_DATEI_MAX`)        |
     *
     * @return int 0 oder ein Wert zwischen `TAKT_DATEI_MIN` und `TAKT_DATEI_MAX`.
     */
    public static function takt_datei() {
        $roh = get_option(self::OPTION_TAKT_DATEI, null);

        if (is_array($roh) || is_object($roh) || !is_numeric($roh)) {
            return self::TAKT_DATEI_VORGABE;
        }

        $wert = (int) round((float) $roh);

        if ($wert <= 0) {
            return 0;
        }

        if ($wert < self::TAKT_DATEI_MIN) {
            return self::TAKT_DATEI_MIN;
        }

        if ($wert > self::TAKT_DATEI_MAX) {
            return self::TAKT_DATEI_MAX;
        }

        return $wert;
    }

    /**
     * Ist die Pulsdatei einer Klasse fehlend oder veraltet?
     *
     * DAS IST DIE BEDINGUNG, DIE DEN HERZSCHLAG ZUR REPARATUR MACHT — und
     * sie ist der Grund, warum er die Datei nicht bei jedem Aufruf neu
     * schreibt.
     *
     * Ohne sie könnte ein Herzschlag, der die Datenbank kurz VOR einem
     * gleichzeitigen Umschalten gelesen hat, die bereits frischere Datei mit
     * veralteten Werten überschreiben. Der Browser sähe dann eine Signatur,
     * die zurückspringt, und meldete eine Änderung, die es nicht gab. Mit der
     * Bedingung ist die Herzschlag-Schreibung eine reine Reparatur: Wird eine
     * Schreibstelle übersehen oder umgangen (direkter Eingriff in die
     * Datenbank, Migration), altert die Datei — und genau dann wird sie
     * erneuert.
     *
     * Die Schwelle ist `2 × HERZSCHLAG`: Ein einzelner ausgefallener
     * Herzschlag löst noch keine Reparatur aus.
     *
     * `clearstatcache()` ist Pflicht — ohne sie liefert PHP innerhalb
     * derselben Anfrage einen zwischengespeicherten Zeitstempel, und eine
     * gerade geschriebene Datei sähe weiterhin alt aus.
     *
     * @param int $class_id Klassen-ID.
     * @return bool
     */
    private static function datei_veraltet($class_id) {
        $pfad = self::pulsdatei_pfad($class_id);

        if ('' === $pfad) {
            return false;
        }

        clearstatcache(true, $pfad);

        if (!file_exists($pfad)) {
            return true;
        }

        $stand = @filemtime($pfad);

        if (false === $stand) {
            return true;
        }

        return (time() - $stand) > (2 * self::HERZSCHLAG);
    }

    // ---------------------------------------------------------------------
    // Die Pulsdatei: Inhalt (AP-1.3)
    // ---------------------------------------------------------------------

    /**
     * Dieselben zwei Signaturen wie `signaturen_seite()`, aber für ALLE Seiten
     * einer Klasse in EINER Abfrage.
     *
     * Die Route braucht sie je Seite einzeln, die Pulsdatei für die ganze
     * Klasse. Statt `signaturen_seite()` in einer Schleife aufzurufen (eine
     * Abfrage je Seite), gruppiert diese Methode dieselben Aggregate über
     * `page_id`.
     *
     * ES ENTSTEHT AUSDRÜCKLICH KEINE ZWEITE SIGNATURLOGIK. Die Werte laufen
     * durch dieselbe Methode `baue_signatur()` und in derselben Reihenfolge
     * wie in `signaturen_seite()`:
     *
     *     seite = baue_signatur([anzahl, frei, pruefsumme])
     *     tafel = baue_signatur([zuletzt])
     *
     * **Diese Reihenfolge ist Vertrag, nicht Geschmackssache.** Weicht sie ab,
     * liefern Pulsdatei und Route für denselben Zustand verschiedene
     * Signaturen — der Browser sähe bei jedem Herzschlag eine Änderung, die es
     * nicht gibt, und holte endlos Inhalte nach. Wer eine der beiden Methoden
     * ändert, muss die andere mitziehen.
     *
     * Bewusst NICHT über eine SQL-Funktion, die die Bezeichner zu einer
     * Zeichenkette verkettet: Deren Längengrenze (Vorgabe 1024 Byte) schneidet
     * ab etwa 44 Containern je Seite STILLSCHWEIGEND ab und fröre die Signatur
     * ein. `COUNT()`, `SUM()` und `MAX()` haben diese Grenze nicht.
     *
     * Der Index `UNIQUE KEY class_page_container (class_id, page_id,
     * container_id)` trägt die `WHERE`-Klausel und die Gruppierung.
     *
     * @param int $class_id Aus der geprüften Sitzung.
     * @return array array((string) page_id => array(seite, tafel)); leer bei
     *               Abfragefehler — die stille Richtung, es wird nichts
     *               zusätzlich angezeigt.
     */
    private static function signaturen_alle_seiten($class_id) {
        global $wpdb;

        $class_id = (int) $class_id;

        if ($class_id <= 0 || !defined('CBD_TABLE_DRAWINGS')) {
            return array();
        }

        $zeilen = $wpdb->get_results($wpdb->prepare(
            'SELECT page_id,'
            . ' COUNT(*) AS anzahl,'
            . ' COALESCE(SUM(is_behandelt), 0) AS frei,'
            . ' COALESCE(SUM(id * is_behandelt), 0) AS pruefsumme,'
            . " COALESCE(MAX(updated_at), '') AS zuletzt"
            . ' FROM ' . CBD_TABLE_DRAWINGS
            . ' WHERE class_id = %d'
            . ' GROUP BY page_id',
            $class_id
        ));

        if (!is_array($zeilen)) {
            return array();
        }

        $seiten = array();

        foreach ($zeilen as $zeile) {
            if (!is_object($zeile) || !isset($zeile->page_id)) {
                continue;
            }

            $page_id = (int) $zeile->page_id;

            if ($page_id <= 0) {
                continue;
            }

            $anzahl     = isset($zeile->anzahl) ? (int) $zeile->anzahl : 0;
            $frei       = isset($zeile->frei) ? (int) $zeile->frei : 0;
            $pruefsumme = isset($zeile->pruefsumme) ? (int) $zeile->pruefsumme : 0;
            $zuletzt    = isset($zeile->zuletzt) ? (string) $zeile->zuletzt : '';

            // Schlüssel als Zeichenkette: Der Browser liest die Abbildung mit
            // `daten.seiten[String(seiteId)]`, und JSON-Objektschlüssel sind
            // ohnehin immer Zeichenketten.
            $seiten[(string) $page_id] = array(
                self::baue_signatur(array($anzahl, $frei, $pruefsumme)),
                self::baue_signatur(array($zuletzt)),
            );
        }

        return $seiten;
    }

    /**
     * Der vollständige Inhalt einer Pulsdatei.
     *
     * `$seiten` wird ÜBERGEBEN statt intern geholt. Das ist Absicht: So lässt
     * sich die Obergrenze unten ohne Datenbank prüfen (`tools/test-klassenpuls.php`,
     * F3/F4), und der Aufrufer entscheidet, wann die teurere Abfrage läuft.
     *
     * DIE OBERGRENZE: WEGLASSEN STATT KÜRZEN.
     * Trägt eine Klasse mehr als `SEITEN_OBERGRENZE` Seiten mit Zeichnungs-
     * datensätzen, entfällt der Schlüssel `seiten` VOLLSTÄNDIG und es kommt
     * `seiten_unvollstaendig` hinzu. Der Browser holt `seite` und `tafel` dann
     * über die Route, wie vor diesem Vorhaben.
     *
     * Eine gekürzte Abbildung wäre der schlechtere Weg: Sie sähe gültig aus,
     * verschwiege aber Seiten, und für genau diese käme nie wieder eine
     * Aktualisierung an. Ein fehlender Schlüssel ist laut, eine gekürzte Liste
     * ist still — und stille Fehler sind in diesem Bereich schon zweimal teuer
     * geworden.
     *
     * @param int   $class_id Aus der geprüften Sitzung.
     * @param array $seiten   Ergebnis von `signaturen_alle_seiten()`.
     * @return array Der Dateiinhalt, fertig für `wp_json_encode()`.
     */
    public static function baue_pulsdaten($class_id, $seiten) {
        $class_id = (int) $class_id;

        if (!is_array($seiten)) {
            $seiten = array();
        }

        $daten = array(
            'klasse'     => self::signatur_klasse($class_id),
            'fragenwand' => self::signatur_fragenwand($class_id),
            'takt'       => self::takt(),
            'stand'      => time(),
        );

        if (count($seiten) > self::SEITEN_OBERGRENZE) {
            $daten['seiten_unvollstaendig'] = true;
        } else {
            $daten['seiten'] = $seiten;
        }

        return $daten;
    }

    // ---------------------------------------------------------------------
    // Die Pulsdatei: Aufräumen (AP-1.7)
    // ---------------------------------------------------------------------

    /**
     * Verwaiste Pulsdateien entfernen.
     *
     * Eine Pulsdatei kann verwaisen, wenn eine Klasse außerhalb von
     * `CBD_Classroom::ajax_delete_class()` verschwindet — durch einen direkten
     * Eingriff in die Datenbank, eine Migration, oder weil eine ältere
     * Plugin-Fassung sie ohne Aufräumen gelöscht hat. Verwaiste Dateien sind
     * harmlos (sie enthalten nur Prüfsummen), aber ihre Adresse bleibt gültig
     * und sie sammeln sich an.
     *
     * ZWEI LÖSCHGRÜNDE, beide nötig:
     *
     * 1. Die Klassen-ID im Dateinamen existiert nicht mehr.
     * 2. Der Dateiname passt nicht zu dem, was `pulsdatei_name()` für diese
     *    Klasse heute bilden würde. Das trifft Dateien aus einer früheren
     *    Salt-Generation: Wurde `wp_salt('auth')` gewechselt, sind ihre
     *    Adressen ohnehin tot — niemand kann sie mehr erfahren, denn die
     *    Route nennt nur den aktuellen Namen.
     *
     * DIE KLASSENLISTE WIRD EINMAL GEHOLT, nicht einmal je Datei. Bei einer
     * Installation mit vielen Klassen wäre eine Abfrage je Datei genau die
     * N+1-Falle, die der Klassenpuls an anderer Stelle bewusst vermeidet.
     *
     * Zusätzlich verschwinden Zwischendateien, die älter als
     * `TMP_HOECHSTALTER` sind — Reste abgebrochener Schreibvorgänge. Frische
     * bleiben unangetastet: Sie könnten zu einer gerade laufenden Anfrage
     * gehören.
     *
     * Läuft täglich über den Termin `CRON_HAKEN` (siehe `init()`) und
     * zusätzlich bei jedem Aufruf von Hand. Fehlschläge beim Löschen werden
     * stillschweigend übergangen — ein nicht gelöschter Rest ist kein Grund,
     * eine Aufräumrunde abzubrechen.
     *
     * @return int Zahl der entfernten Dateien.
     */
    public static function raeume_auf() {
        $basis = self::basisverzeichnis();

        if (null === $basis) {
            return 0;
        }

        $verzeichnis = $basis['pfad'] . '/' . self::UNTERORDNER;

        if (!is_dir($verzeichnis)) {
            return 0;
        }

        $entfernt = 0;

        // --- Zwischendateien ------------------------------------------------
        $reste = glob($verzeichnis . '/*.tmp');
        $grenze = time() - self::TMP_HOECHSTALTER;

        if (is_array($reste)) {
            foreach ($reste as $rest) {
                clearstatcache(true, $rest);
                $alter = @filemtime($rest);

                if (false !== $alter && $alter < $grenze && @unlink($rest)) {
                    $entfernt++;
                }
            }
        }

        // --- Pulsdateien ----------------------------------------------------
        $dateien = glob($verzeichnis . '/puls-*.json');

        if (!is_array($dateien) || array() === $dateien) {
            return $entfernt;
        }

        // Die eine Abfrage: alle vorhandenen Klassen-IDs auf einmal.
        $vorhanden = null;

        if (defined('CBD_TABLE_CLASSES')) {
            global $wpdb;
            $ids = $wpdb->get_col('SELECT id FROM ' . CBD_TABLE_CLASSES);

            if (is_array($ids)) {
                $vorhanden = array();
                foreach ($ids as $id) {
                    $vorhanden[(int) $id] = true;
                }
            }
        }

        foreach ($dateien as $datei) {
            $name = basename($datei);

            if (!preg_match('/^puls-(\d+)-[0-9a-f]{16}\.json$/', $name, $treffer)) {
                // Nicht unser Namensschema — nicht anfassen.
                continue;
            }

            $class_id = (int) $treffer[1];

            $verwaist = false;

            // Grund 1: Klasse existiert nicht mehr. Nur prüfbar, wenn die
            // Liste geladen werden konnte — sonst lieber behalten.
            if (is_array($vorhanden) && !isset($vorhanden[$class_id])) {
                $verwaist = true;
            }

            // Grund 2: Name passt nicht zum heutigen Salt.
            if (!$verwaist && $name !== self::pulsdatei_name($class_id)) {
                $verwaist = true;
            }

            if ($verwaist && @unlink($datei)) {
                $entfernt++;
            }
        }

        return $entfernt;
    }

    // ---------------------------------------------------------------------
    // Die Pulsdatei: Schreiben, Löschen, Sammeln (AP-1.4)
    // ---------------------------------------------------------------------

    /**
     * Die Pulsdatei einer Klasse neu schreiben.
     *
     * DREI EIGENSCHAFTEN, DIE NICHT VERHANDELBAR SIND:
     *
     * 1. ATOMAR. Geschrieben wird erst in eine Zwischendatei im selben
     *    Verzeichnis, dann per `rename()` an den Zielnamen. Auf demselben
     *    Dateisystem ist das ein einziger Schritt — ein Leser bekommt
     *    entweder die alte oder die neue Datei, nie eine halb geschriebene.
     *    Die Prozess-ID im Namen der Zwischendatei verhindert, dass zwei
     *    gleichzeitige Anfragen einander ins Gehege kommen.
     *
     * 2. LAUTLOS. Ein Fehlschlag wirft nie, erzeugt keine PHP-Warnung und
     *    lässt den auslösenden Vorgang nicht scheitern. Der ist in der Regel
     *    `ajax_toggle_behandelt()` — der Klick der Lehrperson vor der Klasse.
     *    Eine fehlgeschlagene Schreibung holt der Herzschlag nach (AP-1.6);
     *    ein fehlgeschlagenes Freigeben wäre dagegen sichtbar und ärgerlich.
     *
     * 3. KEINE ZWISCHENDATEI BLEIBT LIEGEN. Jeder Rückgabepfad nach dem
     *    Anlegen räumt sie weg. Prüfung F7 des Harnischs wacht darüber.
     *
     * DIE NOTBREMSE GILT AUCH HIER: Steht `cbd_klassenpuls_takt` auf 0, wird
     * nichts geschrieben. Bei 0 reiht `CBD_Classroom::enqueue_frontend_assets()`
     * den Taktgeber auf keiner Seite ein — es liest also niemand. Dateien zu
     * schreiben, die niemand liest, wäre stiller Ballast.
     *
     * @param int $class_id Klassen-ID.
     * @return bool true nur bei tatsächlich geschriebener Datei.
     */
    public static function schreibe_pulsdatei($class_id) {
        $class_id = (int) $class_id;

        if ($class_id <= 0) {
            return false;
        }

        // Notbremse: abgeschaltet heißt auch „nichts schreiben".
        if (self::takt() <= 0) {
            return false;
        }

        $ziel = self::pulsdatei_pfad($class_id);

        if ('' === $ziel) {
            return false;
        }

        if (!self::verzeichnis_sicherstellen()) {
            return false;
        }

        $daten = self::baue_pulsdaten($class_id, self::signaturen_alle_seiten($class_id));

        $json = function_exists('wp_json_encode')
            ? wp_json_encode($daten)
            : json_encode($daten);

        if (!is_string($json) || '' === $json) {
            return false;
        }

        $zwischen = $ziel . '.' . getmypid() . '.tmp';

        if (false === @file_put_contents($zwischen, $json, LOCK_EX)) {
            @unlink($zwischen);
            return false;
        }

        if (!@rename($zwischen, $ziel)) {
            @unlink($zwischen);
            return false;
        }

        return true;
    }

    /**
     * Die Pulsdatei einer Klasse entfernen.
     *
     * Gerufen beim Löschen einer Klasse (AP-1.5) und vom Aufräumdurchlauf
     * (AP-1.7). Eine fehlende Datei ist KEIN Fehler: Der Rückgabewert ist dann
     * schlicht `false`, und es entsteht keine PHP-Warnung — `file_exists()`
     * fragt vorher, `@` fängt den Rest.
     *
     * @param int $class_id Klassen-ID.
     * @return bool true nur, wenn wirklich eine Datei gelöscht wurde.
     */
    public static function loesche_pulsdatei($class_id) {
        $pfad = self::pulsdatei_pfad($class_id);

        if ('' === $pfad || !file_exists($pfad)) {
            return false;
        }

        return (bool) @unlink($pfad);
    }

    /**
     * Eine geänderte Klasse vormerken.
     *
     * Zuhörer der Aktion `cbd_klassenmodus_geaendert` (siehe `init()`), die
     * die acht Schreibstellen in `class-cbd-classroom.php` und
     * `class-cbd-fragenwand.php` auslösen (AP-1.5).
     *
     * WARUM NICHT SOFORT SCHREIBEN: Eine Anfrage kann mehrere Änderungen
     * melden — etwa wenn ein Vorgang Freigabe und Tafelbild zugleich berührt.
     * Jede einzeln zu schreiben hieße, dieselbe Datei mehrfach neu aufzubauen
     * und dafür jedes Mal die Datenbank zu befragen. Gesammelt wird daraus
     * genau ein Schreibvorgang je Klasse, und der läuft auf `shutdown`, also
     * NACH der Antwort an den Browser — der Klick der Lehrperson wird dadurch
     * nicht langsamer.
     *
     * @param int $class_id Klassen-ID.
     * @return void
     */
    public static function merke_aenderung($class_id) {
        $class_id = (int) $class_id;

        if ($class_id <= 0) {
            return;
        }

        self::$offene_klassen[$class_id] = true;

        if (self::$shutdown_angemeldet) {
            return;
        }

        self::$shutdown_angemeldet = true;

        // Priorität 20: nach den üblichen Aufräumarbeiten anderer Zuhörer.
        add_action('shutdown', array(__CLASS__, 'schreibe_offene'), 20);
    }

    /**
     * Die vorgemerkten Klassen abarbeiten.
     *
     * DIE LISTE WIRD GELEERT, BEVOR GESCHRIEBEN WIRD. Löste ein Schreibvorgang
     * seinerseits die Aktion aus, liefe die Schleife sonst endlos.
     *
     * Öffentlich, weil `add_action()` einen erreichbaren Rückruf braucht.
     *
     * @return void
     */
    public static function schreibe_offene() {
        $klassen = array_keys(self::$offene_klassen);

        self::$offene_klassen = array();

        foreach ($klassen as $class_id) {
            self::schreibe_pulsdatei($class_id);
        }
    }

    // ---------------------------------------------------------------------
    // Die Pulsdatei: Adresse, Pfad und Verzeichnis (AP-1.2)
    // ---------------------------------------------------------------------

    /**
     * Dateiname der Pulsdatei einer Klasse.
     *
     * Form: `puls-<class_id>-<16 Hex-Zeichen>.json`
     *
     * WARUM EIN HMAC IM NAMEN — DIE ENTSCHEIDENDE STELLE DIESES APs
     * Die Pulsdatei wird von Apache bzw. nginx ausgeliefert, ohne dass PHP
     * läuft. Sie kann also gar nichts prüfen — und sie MUSS auch nichts
     * prüfen: Ihr Inhalt sind ausschließlich Prüfsummen, niemals Inhalte.
     * Unerratbar wird die Adresse durch einen Anteil, der aus einem
     * Servergeheimnis abgeleitet ist; erfahren kann sie ein Browser nur aus
     * der Antwort von `liefere_puls()`, also erst NACHDEM
     * `CBD_Classroom_Gate::sitzung()` ihn durchgelassen hat.
     *
     * Damit entsteht KEIN zweiter Weg zur Token-Deutung — die härteste Regel
     * dieses Plugins bleibt unangetastet. `tools/test-klassenpuls.php`
     * (Gruppe D) wacht darüber: Diese Datei darf weder selbst in den
     * Sitzungsspeicher greifen noch die Anfrageparameter am Gate vorbei lesen.
     *
     * ACHTUNG BEIM KOMMENTIEREN DIESER DATEI: Die Wächter der Gruppe D sind
     * textbasiert und lesen den Quelltext samt Docblocks. Die von ihnen
     * verbotenen Zeichenfolgen dürfen deshalb auch in Kommentaren nicht
     * wörtlich vorkommen — sonst schlägt der Wächter an, obwohl der Code in
     * Ordnung ist. (Einzige Ausnahme ist D7, der seit AP-1.2 auf dem
     * kommentarfreien Quelltext arbeitet.)
     *
     * BEWUSST KEIN `sanitize_file_name()`: Die Zeichenmenge ist durch die
     * Konstruktion bereits auf Ziffern, Bindestriche und Hex beschränkt. Eine
     * zusätzliche Bereinigung könnte den Namen nur verändern, ohne ihn
     * sicherer zu machen — und ein verändeter Name fände seine Datei nicht.
     *
     * BEKANNTE, BEWUSST AKZEPTIERTE EINSCHRÄNKUNG: Wer die Adresse einmal
     * hatte, kann sie weiter abrufen, auch nach Ablauf seiner Sitzung. Er
     * sieht dann Prüfsummen, die sich ändern, aber keinen Inhalt. Eine
     * Rotation von `wp_salt('auth')` entwertet alle Adressen auf einen Schlag.
     *
     * @param int $class_id Klassen-ID.
     * @return string Dateiname, oder `''` bei unplausibler Klassen-ID.
     */
    public static function pulsdatei_name($class_id) {
        $class_id = (int) $class_id;

        if ($class_id <= 0) {
            return '';
        }

        $hmac = hash_hmac('sha256', 'klassenpuls|' . $class_id, wp_salt('auth'));

        return 'puls-' . $class_id . '-' . substr($hmac, 0, 16) . '.json';
    }

    /**
     * Pfad und Adresse des Uploads-Verzeichnisses, beide normalisiert.
     *
     * STANDARD IST ABLEHNUNG: Fehlt eine der beiden Angaben, ist sie keine
     * nicht-leere Zeichenkette, oder meldet `wp_upload_dir()` selbst einen
     * Fehler, kommt `null` zurück. Es wird NICHT geraten und NICHT selbst ein
     * Pfad zusammengesetzt — eine Pulsdatei an einer geratenen Stelle wäre
     * schlimmer als gar keine.
     *
     * @return array|null array('pfad' => string, 'url' => string) oder null.
     */
    private static function basisverzeichnis() {
        if (!function_exists('wp_upload_dir')) {
            return null;
        }

        $verzeichnis = wp_upload_dir();

        if (!is_array($verzeichnis) || !empty($verzeichnis['error'])) {
            return null;
        }

        if (!isset($verzeichnis['basedir'], $verzeichnis['baseurl'])) {
            return null;
        }

        if (!is_string($verzeichnis['basedir']) || '' === $verzeichnis['basedir']) {
            return null;
        }

        if (!is_string($verzeichnis['baseurl']) || '' === $verzeichnis['baseurl']) {
            return null;
        }

        return array(
            // Trennzeichen vereinheitlichen: Unter Windows liefert
            // `wp_upload_dir()` gemischte Pfade. Für den Dateizugriff ist das
            // gleichgültig, für den Vergleich in Prüfungen nicht.
            'pfad' => rtrim(str_replace('\\', '/', $verzeichnis['basedir']), '/'),
            'url'  => rtrim($verzeichnis['baseurl'], '/'),
        );
    }

    /**
     * Dateisystempfad der Pulsdatei einer Klasse.
     *
     * @param int $class_id Klassen-ID.
     * @return string Pfad, oder `''` wenn Name oder Basisverzeichnis fehlen.
     */
    public static function pulsdatei_pfad($class_id) {
        $name = self::pulsdatei_name($class_id);

        if ('' === $name) {
            return '';
        }

        $basis = self::basisverzeichnis();

        if (null === $basis) {
            return '';
        }

        return $basis['pfad'] . '/' . self::UNTERORDNER . '/' . $name;
    }

    /**
     * Öffentliche Adresse der Pulsdatei einer Klasse.
     *
     * DIE ADRESSE WIRD AUS `baseurl` GEBILDET, NIEMALS AUS DEM DATEIPFAD.
     * Ein `str_replace()` von `basedir` gegen `baseurl` auf dem Ergebnis von
     * `pulsdatei_pfad()` wäre naheliegend und falsch: Unter Windows geriete
     * dabei ein Backslash in die URL, und der Browser fragte eine Adresse ab,
     * die es nicht gibt. `tools/test-klassenpuls.php` (E7) prüft genau das.
     *
     * @param int $class_id Klassen-ID.
     * @return string Adresse, oder `''` wenn Name oder Basisverzeichnis fehlen.
     */
    public static function pulsdatei_url($class_id) {
        $name = self::pulsdatei_name($class_id);

        if ('' === $name) {
            return '';
        }

        $basis = self::basisverzeichnis();

        if (null === $basis) {
            return '';
        }

        return $basis['url'] . '/' . self::UNTERORDNER . '/' . $name;
    }

    /**
     * Das Pulsverzeichnis anlegen und absichern.
     *
     * Legt bei Bedarf das Verzeichnis an und schreibt einmalig eine
     * `.htaccess` sowie eine leere `index.php`. Beide nur, wenn sie noch
     * nicht existieren — ein Überschreiben bei jedem Aufruf wäre unnötige
     * Schreiblast und überschriebe eine vom Betrieb angepasste Datei.
     *
     * DIE `.htaccess` IST EINE ZWEITE ABSICHERUNG, KEIN TRAGENDER SCHUTZ.
     * Sie wirkt: Die Produktivinstallation meldet sich zwar als `nginx`, aber
     * dahinter arbeitet ein Apache, der die Datei liest — dort sind auch die
     * IP-Beschränkungen der Website umgesetzt. Produktiv ist sie trotzdem nur
     * die zweite Verteidigungslinie, weil die Verzeichnisauflistung dort
     * ohnehin abgeschaltet ist; auf dem lokalen Testserver ist sie die
     * einzige. Beleg: `docs/voraussetzungen-kas.md`, Zusatzfrage zur
     * `.htaccess`.
     *
     * (Eine frühere Fassung dieses Kommentars behauptete das Gegenteil — die
     * Datei sei wirkungslos, weil nginx sie nicht auswerte. Das war aus dem
     * `Server:`-Antwortkopf geschlossen und falsch: Der Kopf nennt die
     * äußerste Schicht, nicht die verarbeitende.)
     *
     * **Der Schutz der Pulsdateien ruht deshalb NICHT auf dieser Datei**,
     * sondern auf dem unerratbaren HMAC-Anteil im Dateinamen (siehe
     * `pulsdatei_name()`) und darauf, dass der Inhalt ausschließlich
     * Prüfsummen sind. Wer die `.htaccess` künftig entfernt, schwächt nichts
     * Tragendes; wer sich auf sie verlässt, irrt.
     *
     * Alle Schreibvorgänge sind mit `@` unterdrückt und über den Rückgabewert
     * geprüft: Ein Fehlschlag wirft nie, sondern liefert `false`. Der Aufrufer
     * (`schreibe_pulsdatei()`, AP-1.4) bricht dann lautlos ab — der Herzschlag
     * holt die Datei später nach.
     *
     * @return bool true, wenn das Verzeichnis danach existiert und beschreibbar ist.
     */
    private static function verzeichnis_sicherstellen() {
        $basis = self::basisverzeichnis();

        if (null === $basis) {
            return false;
        }

        $verzeichnis = $basis['pfad'] . '/' . self::UNTERORDNER;

        if (!is_dir($verzeichnis)) {
            if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($verzeichnis)) {
                return false;
            }
        }

        $htaccess = $verzeichnis . '/.htaccess';

        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n");
        }

        $index = $verzeichnis . '/index.php';

        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }

        return is_dir($verzeichnis) && is_writable($verzeichnis);
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
     *
     * PFLEGEHINWEIS (Befund G1 aus AP-1.rev): Dieser Docblock war seit
     * AP-1.2 rund 750 Zeilen von seiner Methode getrennt — neue
     * Abschnitte waren zwischen Beschreibung und Rumpf geraten, und er
     * schien den Abschnitt zu beschreiben, vor dem er zufällig lag. Wer
     * ganze Abschnitte einfügt, prüft deshalb, ob die Einfügestelle
     * zwischen einem Docblock und seiner Methode liegt.
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
