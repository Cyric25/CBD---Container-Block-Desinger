<?php
/**
 * Container Block Designer — einzelnen Container-Block als HTML ausliefern
 *
 *     GET /wp-json/cbd/v1/block-html?post_id=<id>&stable_id=<sid>[&classroom=<id>&token=<t>]
 *
 * Gebraucht wird das vom Block „Block-Referenz": Liegt der referenzierte
 * Container auf einer ANDEREN Seite, kann das Modal ihn nicht aus dem DOM
 * klonen und lädt ihn hier nach.
 *
 * WARUM EINE EIGENE KLASSE (und nicht CBD_Blocks_REST_API)
 * Dort gilt „nur Redakteure" (`current_user_can('edit_posts')`), hier gilt
 * „jeder, aber nur was er sehen darf". Zwei Routen mit gegensätzlichen
 * Annahmen in einer Datei laden zum Fehler ein — irgendwann bekommt eine
 * Route den falschen `permission_callback`.
 *
 * WARUM `permission_callback => '__return_true'`
 * Schülerinnen und Schüler melden sich nie an; sie kommen über das
 * Klassenpasswort. Ein Capability-Callback schlösse sie aus. DIE GESAMTE
 * AUTORISIERUNG LIEGT DAMIT IM CALLBACK — siehe `liefere_block_html()`.
 *
 * DIE SPERRE DES THEMES GEHT AN DIESEM ENDPUNKT VORBEI
 * Das Theme sperrt einzelne Seiten („nur für Lehrpersonen", Meta
 * `_simple_clean_nur_lehrpersonen`) an vier Stellen: `template_redirect`
 * (Priorität 20), `redirect_canonical`, `pre_get_posts` und `rest_pre_dispatch`
 * für `/wp/v2/pages/<id>`. KEINE davon greift bei einer eigenen `cbd/v1`-Route.
 * Das Theme beschreibt genau diesen Fehlertyp in `includes/sichtbarkeit.php`:
 * „`rest_page_query` filtert nur Sammlungen … die Sperre wäre mit einer
 * einzigen URL auszuhebeln." Diese Klasse setzt die Sperre deshalb selbst
 * durch, über `simple_clean_seite_sichtbar()`.
 *
 * GRUNDSATZ: STANDARD IST ABLEHNUNG. Fällt eine Prüfung aus (Theme fehlt,
 * Klasse unbekannt, Rendern wirft), wird abgelehnt, nicht durchgelassen.
 *
 * ABLEHNUNG UND NICHTEXISTENZ ANTWORTEN ZEICHENGLEICH. Sonst ließen sich
 * durch Durchprobieren von IDs die vorhandenen Lösungsseiten kartieren —
 * genau diesen Fehler hat das Theme in `simple_clean_lehrerseite_kanonisch()`
 * schon einmal behoben.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.89
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Content_API {

    /**
     * REST-Namensraum. Derselbe wie bei CBD_Blocks_REST_API — die Trennung
     * liegt in der Route, nicht im Namensraum.
     */
    const REST_NAMESPACE = 'cbd/v1';

    /** Die Route. */
    const REST_ROUTE = '/block-html';

    /**
     * Der EINZIGE Fehlercode dieses Endpunkts.
     *
     * Es gibt bewusst keine sprechenden Codes („Seite gesperrt", „Block
     * unbekannt"): Jeder Unterschied wäre ein Kartierungswerkzeug.
     */
    const FEHLERCODE = 'cbd_block_not_available';

    /** HTTP-Status jeder Ablehnung. */
    const FEHLERSTATUS = 404;

    /** Namensraum der Container-Blöcke. */
    const BLOCK_PRAEFIX = 'container-block-designer/';

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
     * `CBD_Classroom_Gate::sitzung()` sie unverändert aus `$_GET` lesen kann.
     * Eine zweite Fassung der Token-Prüfung wird hier bewusst NICHT gebaut —
     * eine zweite Stelle, die Tokens deutet, ist genau die Art Fehler, gegen
     * die `tools/test-classroom-gate.php` wacht.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'liefere_block_html'),
            // Die gesamte Autorisierung steckt im Callback. Siehe Kopfkommentar.
            'permission_callback' => '__return_true',
            'args' => array(
                'post_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Seite, auf der der Block liegt. Die Rechtepruefung haengt hieran.',
                ),
                'stable_id' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Stabiler Bezeichner des Container-Blocks.',
                ),
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
            ),
        ));
    }

    /**
     * Der Endpunkt. Autorisierung und Auslieferung in einem.
     *
     * DIE KETTE, IN DIESER REIHENFOLGE — jeder Fehlschlag endet sofort in der
     * einheitlichen Ablehnung:
     *
     *   1. `nocache_headers()`, IMMER und als Erstes. Dieselbe URL liefert für
     *      Lehrperson, Klassensitzung und Anonymen unterschiedliche Inhalte;
     *      ein Cache dürfte sie nie verwechseln. (Die REST-Schnittstelle
     *      sendet die Kopfzeilen von sich aus nur für Angemeldete — der
     *      Filter `rest_send_nocache_headers` hat `is_user_logged_in()` als
     *      Vorgabe. Für Nichtangemeldete gäbe es sie also sonst nicht.)
     *   2. Beitrag: vorhanden, Typ `page`/`post`, Status `publish`.
     *   3. Kein Passwortschutz.
     *   4. Sichtbarkeit über `simple_clean_seite_sichtbar()` (Theme).
     *   5. Auf gesperrter Seite: gültige Klassensitzung.
     *   6. Auf gesperrter Seite: dieser eine Block ist für die Klasse
     *      freigegeben.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function liefere_block_html($request) {
        // ---- (1) Kein Zwischenspeicher, in JEDEM Antwortpfad ----------------
        // Steht ganz oben und ohne Bedingung, damit kein späteres `return`
        // daran vorbeikommt. (Bewusst KEINE `function_exists()`-Hülle: Die
        // Hülle gilt Theme-Funktionen; `nocache_headers()` ist WordPress-Kern
        // und steht lange vor `rest_api_init` bereit.)
        nocache_headers();

        // Ohne die geteilten Helfer lässt sich weder der Bezeichner deuten
        // noch die Freigabe prüfen. Standard ist Ablehnung.
        if (!class_exists('CBD_Classroom_Gate')
            || !method_exists('CBD_Classroom_Gate', 'block_erlaubt')
            || !class_exists('CBD_Classroom')
            || !method_exists('CBD_Classroom', 'basis_container_id')) {
            return self::ablehnen();
        }

        if (!is_object($request) || !method_exists($request, 'get_param')) {
            return self::ablehnen();
        }

        // `is_scalar()` fängt `?stable_id[]=x` ab. Die Schema-Prüfung der
        // REST-Schnittstelle würde das schon mit 400 abweisen; ohne die Hülle
        // stünde bei einem Direktaufruf aber eine PHP-Warnung im Fehlerlog.
        $roh_post_id   = $request->get_param('post_id');
        $roh_stable_id = $request->get_param('stable_id');

        $post_id   = is_scalar($roh_post_id) ? absint($roh_post_id) : 0;
        $stable_id = is_scalar($roh_stable_id) ? sanitize_text_field((string) $roh_stable_id) : '';

        if ($post_id <= 0 || '' === $stable_id) {
            return self::ablehnen();
        }

        // ---- (2) Der Beitrag ------------------------------------------------
        $post = get_post($post_id);

        if (!is_object($post)
            || !isset($post->post_type, $post->post_status)
            || !in_array($post->post_type, array('page', 'post'), true)
            || 'publish' !== $post->post_status) {
            return self::ablehnen();
        }

        // ---- (3) Passwortschutz ---------------------------------------------
        if (post_password_required($post)) {
            return self::ablehnen();
        }

        // ---- (4) Die Sperre des Themes --------------------------------------
        // `simple_clean_seite_sichtbar()` ist die GESAMTENTSCHEIDUNG: Sie
        // deckt die Lehrperson, die Sperre samt Vererbung auf den Unterbaum
        // und den Freigabe-Filter ab. NICHT `simple_clean_seite_nur_lehrpersonen()`
        // verwenden — die kennt den Klassen-Durchlass nicht und sperrte
        // Schülerinnen und Schüler aus.
        //
        // Die `function_exists()`-Hülle ist Pflicht (Konvention aus
        // `class-cbd-classroom-gate.php`): Fehlt das Theme, gibt es gar keine
        // Sperre — dann darf der Endpunkt nicht am fehlenden Aufruf sterben.
        if (function_exists('simple_clean_seite_sichtbar')
            && !simple_clean_seite_sichtbar($post_id)) {
            return self::ablehnen();
        }

        // Der angefragte Bezeichner in Basisform. `basis_container_id()` ist
        // die einzige Stelle im Plugin, die das Suffix `:pN` mehrseitiger
        // Tafelbilder deuten darf.
        $ziel_id = CBD_Classroom::basis_container_id($stable_id);
        if ('' === $ziel_id) {
            return self::ablehnen();
        }

        // Die Liste, gegen die gesucht wird. Ohne Sperre ist das genau der
        // angefragte Bezeichner.
        $erlaubte = array($ziel_id);

        // ---- (5) + (6) Klassensitzung und blockweise Freigabe ---------------
        if (self::seite_gesperrt($post_id)) {
            // Hierher kommt nur, wer Schritt 4 bestanden hat — also gibt es
            // eine Sitzung, die die SEITE freigibt. Geprüft wird jetzt der
            // einzelne BLOCK. Beides bleibt getrennt: Die Seitenfreigabe sagt
            // nur, dass irgendein Container behandelt ist.
            $sitzung = CBD_Classroom_Gate::sitzung();

            if (!is_array($sitzung) || empty($sitzung['class_id'])) {
                return self::ablehnen();
            }

            $freigegeben = CBD_Classroom::behandelte_container(
                (int) $sitzung['class_id'],
                $post_id
            );

            // Standard ist Ablehnung: Was nicht in der Liste steht, gibt es
            // für diese Klasse nicht.
            if (empty($freigegeben) || !in_array($ziel_id, (array) $freigegeben, true)) {
                return self::ablehnen();
            }
        }

        // ---- Block suchen ---------------------------------------------------
        $treffer = self::finde_block(parse_blocks($post->post_content), $erlaubte);

        if (null === $treffer) {
            return self::ablehnen();
        }

        // Doppelt gemoppelt, mit Absicht: `block_erlaubt()` prüft den
        // Namensraum bereits. Würde diese Prüfung dort je gelockert, bliebe
        // sie hier stehen — dieser Endpunkt liefert ausschließlich Container.
        if (empty($treffer['blockName'])
            || 0 !== strpos((string) $treffer['blockName'], self::BLOCK_PRAEFIX)) {
            return self::ablehnen();
        }

        // ---- Rendern --------------------------------------------------------
        $html = self::rendere($treffer, $post);

        if ('' === trim($html)) {
            return self::ablehnen();
        }

        // Der Titel kommt AUS DEN BLOCKATTRIBUTEN. Niemals der Seitentitel und
        // niemals der Permalink — beide verrieten den Namen einer gesperrten
        // Seite, und genau den verbirgt die Hinweisseite des Themes.
        return new WP_REST_Response(
            array(
                'html'  => $html,
                'title' => self::block_titel($treffer),
            ),
            200
        );
    }

    // =========================================================================
    // BAUSTEINE
    // =========================================================================

    /**
     * Die einheitliche Ablehnung.
     *
     * Zeichengleich für JEDEN Fehlschlag: Seite existiert nicht, ist kein
     * `publish`, ist passwortgeschützt, ist gesperrt, Block existiert nicht,
     * Block ist für die Klasse nicht freigegeben.
     *
     * Bewusst eine `WP_REST_Response` statt eines `WP_Error`: Ein `WP_Error`
     * bekommt von der REST-Schnittstelle zusätzlich `data.status` und
     * gegebenenfalls `additional_errors` angehängt. Der Rumpf ist so exakt
     * das, was AP-2.5 erwartet.
     *
     * @return WP_REST_Response
     */
    private static function ablehnen() {
        return new WP_REST_Response(
            array(
                'code'    => self::FEHLERCODE,
                'message' => __('Der Block ist nicht verfügbar.', 'container-block-designer'),
            ),
            self::FEHLERSTATUS
        );
    }

    /**
     * Ist diese Seite für den aktuellen Besucher eine gesperrte Seite?
     *
     * „Gesperrt" heißt hier: Das Theme kennt die Sperre, sie greift für diese
     * Seite, und der Besucher ist keine Lehrperson. Fehlt das Theme, gibt es
     * keine Sperre — dann ist auch nichts blockweise freizugeben.
     *
     * @param int $post_id
     * @return bool
     */
    private static function seite_gesperrt($post_id) {
        if (!function_exists('simple_clean_seite_nur_lehrpersonen')) {
            return false;
        }

        if (!simple_clean_seite_nur_lehrpersonen($post_id)) {
            return false;
        }

        // Angemeldete Lehrpersonen brauchen keinen Klassen-Durchlass; für sie
        // hat Schritt 4 bereits abschließend entschieden.
        if (function_exists('simple_clean_ist_lehrperson') && simple_clean_ist_lehrperson()) {
            return false;
        }

        return true;
    }

    /**
     * Den Container-Block mit passendem Bezeichner suchen — rekursiv.
     *
     * DIE ERKENNUNG LEISTET `CBD_Classroom_Gate::block_erlaubt()`, nicht diese
     * Methode. Dort steckt bereits alles Nötige: das Blockattribut `stableId`,
     * der Rückfall auf die im gespeicherten Markup hinterlegte Kennung
     * (Altbestände tragen sie nur dort), die Beschränkung auf den Namensraum
     * `container-block-designer/` und die Basisform über
     * `CBD_Classroom::basis_container_id()`.
     *
     * Eine eigene Fassung wäre die VIERTE Kopie dieser Regel im Plugin
     * (`class-cbd-block-registration.php`, `class-cbd-classroom-gate.php`,
     * `class-cbd-blocks-rest-api.php`). Deshalb der Aufruf statt der Kopie.
     * `tools/test-block-content-api.php` wacht darüber, dass in dieser Datei
     * kein Attributname und kein `preg_match` auftaucht.
     *
     * @param array    $bloecke  Einträge aus parse_blocks()
     * @param string[] $erlaubte Basis-Bezeichner, die gesucht werden
     * @return array|null Der gefundene Block oder null
     */
    public static function finde_block($bloecke, $erlaubte) {
        if (empty($erlaubte) || !is_array($bloecke)) {
            return null;
        }

        foreach ($bloecke as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (CBD_Classroom_Gate::block_erlaubt($block, $erlaubte)) {
                return $block;
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $treffer = self::finde_block($block['innerBlocks'], $erlaubte);
                if (null !== $treffer) {
                    return $treffer;
                }
            }
        }

        return null;
    }

    /**
     * Den Titel des Blocks aus seinen Attributen.
     *
     * @param array $block
     * @return string Titel oder leerer String
     */
    private static function block_titel($block) {
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();

        $titel = '';
        if (isset($attrs['blockTitle']) && is_string($attrs['blockTitle'])) {
            $titel = $attrs['blockTitle'];
        } elseif (isset($attrs['title']) && is_string($attrs['title'])) {
            $titel = $attrs['title'];
        }

        return sanitize_text_field($titel);
    }

    /**
     * Den gefundenen Block rendern.
     *
     * `render_block($block)` — so, wie es `CBD_Classroom_Gate::inhalt_reduzieren()`
     * tut. NICHT `do_blocks()` auf den ganzen Beitrag (rendert zu viel) und
     * NICHT `serialize_blocks()` mit eigener Ausgabe (der Whitespace-Unterschied
     * zwischen JavaScript- und PHP-Serializer ist im Projekt dokumentiert).
     *
     * WARUM DER GLOBALE `$post` GESETZT WIRD: Der Renderer des Containers
     * fragt `get_the_ID()` — unter anderem für `pageId` im
     * Interactivity-Kontext und für die Zeichnungs-Persistenz. Ohne
     * Beitragskontext stünde dort 0, und der Block im Modal spräche über die
     * falsche Seite. Der vorherige Wert wird in jedem Fall wiederhergestellt.
     *
     * Ein `Throwable` beim Rendern führt zur Ablehnung, nicht zu einer
     * halbfertigen Ausgabe.
     *
     * @param array   $block
     * @param WP_Post $ziel_post
     * @return string
     */
    private static function rendere($block, $ziel_post) {
        $vorher     = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
        $hatte_post = array_key_exists('post', $GLOBALS);

        $GLOBALS['post'] = $ziel_post;

        try {
            $html = render_block($block);
        } catch (Throwable $e) {
            error_log('[CBD Block Content API] Rendern fehlgeschlagen: ' . $e->getMessage());
            $html = '';
        } finally {
            if ($hatte_post) {
                $GLOBALS['post'] = $vorher;
            } else {
                unset($GLOBALS['post']);
            }
        }

        return is_string($html) ? $html : '';
    }
}
