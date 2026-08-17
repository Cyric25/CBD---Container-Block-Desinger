<?php
/**
 * REST API for CBD Blocks
 * Provides endpoints to fetch all Container Block Designer blocks
 *
 * @package ContainerBlockDesigner
 * @since 2.8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Blocks_REST_API {

    /**
     * Innerhalb einer Anfrage gehaltenes Ergebnis von `get_seitenbaum()`.
     * Bewusst eine Klasseneigenschaft statt einer Funktions-static: nur so
     * laesst sie sich fuer Tests zuruecksetzen (`seitenbaum_cache_vergessen()`).
     *
     * @var WP_REST_Response|null
     */
    private static $seitenbaum_cache = null;

    /**
     * Initialize the REST API routes
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('cbd/v1', '/blocks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_cbd_blocks'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        // Seit AP-3.1 (Vertrag B): dasselbe Sicherheitsmodell wie /blocks
        // (current_user_can('edit_posts')), daher derselbe Permission-Callback.
        register_rest_route('cbd/v1', '/seitenbaum', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_seitenbaum'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /**
     * Permission check - allow logged-in users with edit_posts capability
     */
    public static function check_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * Get all CBD blocks from all posts/pages
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_cbd_blocks($request) {
        $blocks = [];

        // Query all posts and pages
        // orderby seit AP-3.1: menu_order zuerst, damit die Reihenfolge
        // innerhalb einer Hierarchieebene der Redaktionsreihenfolge folgt
        // statt rein alphabetisch ueber Beitraege und Seiten gemischt zu sein.
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ]);

        foreach ($posts as $post) {
            // Parse blocks from post content
            $parsed_blocks = parse_blocks($post->post_content);

            // Recursively find CBD blocks
            $cbd_blocks = self::find_cbd_blocks_recursive($parsed_blocks, $post);

            if (!empty($cbd_blocks)) {
                $blocks = array_merge($blocks, $cbd_blocks);
            }
        }

        return new WP_REST_Response($blocks, 200);
    }

    /**
     * Recursively find CBD blocks in parsed block structure
     *
     * @param array $blocks Parsed blocks
     * @param WP_Post $post The post object
     * @return array Found CBD blocks
     */
    private static function find_cbd_blocks_recursive($blocks, $post) {
        $cbd_blocks = [];

        foreach ($blocks as $block) {
            // Check if this is a CBD block
            if (self::is_cbd_block($block)) {
                $cbd_block_data = self::extract_cbd_block_data($block, $post);
                if ($cbd_block_data) {
                    $cbd_blocks[] = $cbd_block_data;
                }
            }

            // Check inner blocks recursively
            if (!empty($block['innerBlocks'])) {
                $inner_cbd_blocks = self::find_cbd_blocks_recursive($block['innerBlocks'], $post);
                $cbd_blocks = array_merge($cbd_blocks, $inner_cbd_blocks);
            }
        }

        return $cbd_blocks;
    }

    /**
     * Check if a block is a CBD block
     *
     * @param array $block Parsed block
     * @return bool
     */
    private static function is_cbd_block($block) {
        // Check if block name starts with 'container-block-designer/'
        return isset($block['blockName']) && strpos($block['blockName'], 'container-block-designer/') === 0;
    }

    /**
     * Extract CBD block data
     *
     * @param array $block Parsed block
     * @param WP_Post $post The post object
     * @return array|null Block data or null
     */
    private static function extract_cbd_block_data($block, $post) {
        $attrs = $block['attrs'] ?? [];

        // Get block title (may be in different attribute names)
        $block_title = $attrs['blockTitle'] ?? $attrs['title'] ?? '';

        // Try to extract from innerHTML if no title attribute
        if (empty($block_title) && !empty($block['innerHTML'])) {
            // Parse HTML to find title in header
            preg_match('/<div[^>]*class="[^"]*cbd-block-title[^"]*"[^>]*>(.*?)<\/div>/is', $block['innerHTML'], $matches);
            if (!empty($matches[1])) {
                $block_title = strip_tags($matches[1]);
            }
        }

        // Use block name as fallback if still no title
        if (empty($block_title)) {
            $block_name_parts = explode('/', $block['blockName']);
            $block_title = end($block_name_parts);
        }

        // Der massgebliche Bezeichner ist die stableId — sie existiert an
        // jedem Container, waehrend ein HTML-Anker optional bleibt.
        $stable_id = self::extract_stable_id($block);

        // Ohne stableId ist der Block nicht adressierbar; er entfaellt.
        if ('' === $stable_id) {
            return null;
        }

        // Legacy-Schluessel `blockId` — bleibt nur aus Rueckwaertskompatibilitaet
        // erhalten. Neue Aufrufer verwenden `stableId`.
        $block_id = $attrs['id'] ?? $attrs['blockId'] ?? '';
        if (empty($block_id) && !empty($block['innerHTML'])) {
            preg_match('/id="([^"]+)"/', $block['innerHTML'], $matches);
            if (!empty($matches[1])) {
                $block_id = $matches[1];
            }
        }

        // HTML-Anker (optional, vom Redakteur gesetzt). render.php baut
        // daraus das Sprungfragment.
        $anchor = isset($attrs['anchor']) ? (string) $attrs['anchor'] : '';

        return [
            'stableId' => $stable_id,
            'anchor' => $anchor,
            'blockId' => $block_id,
            'blockTitle' => $block_title,
            'postId' => $post->ID,
            'postTitle' => $post->post_title,
            'postUrl' => get_permalink($post->ID),
            'blockType' => $block['blockName'],

            // Seit AP-3.1 (Vertrag A): fuer die hierarchische Zielauswahl.
            // Kommen unveraendert aus dem bereits geladenen $post-Objekt,
            // keine Zusatzabfrage noetig.
            'postParent' => (int) $post->post_parent,
            'menuOrder' => (int) $post->menu_order,
            'postType' => (string) $post->post_type,
        ];
    }

    /**
     * Stabilen Bezeichner eines Container-Blocks ermitteln
     *
     * Reihenfolge wie in CBD_Classroom_Gate::block_erlaubt(): zuerst das
     * Blockattribut `stableId`, danach als RUECKFALL FUER ALTBESTAENDE das
     * gespeicherte HTML — aeltere Container tragen die Kennung nur dort.
     *
     * Das HTML wird bewusst ueber WP_HTML_Tag_Processor gelesen statt ueber
     * einen weiteren regulaeren Ausdruck: das Muster
     * `data-stable-id="([^"]+)"` steht bereits an zwei Stellen im Plugin
     * (class-cbd-classroom-gate.php, class-cbd-block-registration.php). Eine
     * dritte Kopie haette dieselbe Regel an drei Orten gepflegt. Fehlt die
     * Klasse (WordPress vor 6.2), entfaellt lediglich der Rueckfall; Bloecke
     * mit Attribut werden weiterhin gefunden.
     *
     * @param array $block Eintrag aus parse_blocks()
     * @return string Stabiler Bezeichner oder '' wenn keiner ermittelbar ist
     */
    private static function extract_stable_id($block) {
        if (!empty($block['attrs']['stableId'])) {
            return (string) $block['attrs']['stableId'];
        }

        $html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
        if ('' === $html && !empty($block['innerContent'])) {
            $html = implode('', array_filter((array) $block['innerContent'], 'is_string'));
        }

        if ('' === $html || !class_exists('WP_HTML_Tag_Processor')) {
            return '';
        }

        $tags = new WP_HTML_Tag_Processor($html);
        while ($tags->next_tag()) {
            $wert = $tags->get_attribute('data-stable-id');
            if (is_string($wert) && '' !== $wert) {
                return $wert;
            }
        }

        return '';
    }

    /**
     * REST-Callback fuer `GET cbd/v1/seitenbaum` (AP-3.1, Vertrag B).
     *
     * Liefert alle veroeffentlichten Seiten als Baum, unabhaengig davon, ob
     * eine Seite einen Container-Block enthaelt — anders als `/blocks`
     * (die dortige Liste hat Luecken, sobald eine Zwischenseite ohne
     * Container-Block steht; die Elternkette wuerde reissen). Beitraege
     * (post_type "post") sind nicht hierarchisch und stehen deshalb nicht in
     * diesem Baum (siehe `baue_seitenbaum()`).
     *
     * Geladen wird mit rohem $wpdb und fuenf Spalten, KEIN post_content
     * (Vorbild: Theme/includes/page-index.php:146-151). Innerhalb einer
     * Anfrage wird das Ergebnis in einer Klasseneigenschaft gehalten
     * (mehrfache Aufrufe kosten dann keine weitere Abfrage).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_seitenbaum($request) {
        if (null !== self::$seitenbaum_cache) {
            return self::$seitenbaum_cache;
        }

        global $wpdb;

        // Nur fuenf Spalten, kein post_content. Die WHERE-Klausel
        // beschraenkt bereits auf post_type = 'page' — Beitraege sind nicht
        // hierarchisch (post_parent wird fuer sie von WordPress nicht
        // gepflegt) und gehoeren laut Vertrag B nicht in diesen Baum. Die
        // Spalte post_type wird trotzdem mitgelesen, weil `baue_seitenbaum()`
        // sie zusaetzlich als Verteidigung in der Tiefe prueft, statt sich
        // allein auf die WHERE-Klausel zu verlassen.
        $zeilen = $wpdb->get_results(
            "SELECT ID, post_parent, post_title, menu_order, post_type
             FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
             ORDER BY menu_order ASC, post_title ASC"
        );

        self::$seitenbaum_cache = new WP_REST_Response(self::baue_seitenbaum($zeilen), 200);

        return self::$seitenbaum_cache;
    }

    /**
     * Baut aus einer flachen Zeilenliste (wie von der SQL-Abfrage in
     * `get_seitenbaum()` geliefert) den Seitenbaum gemaess Vertrag B.
     *
     * Reine Aufbaulogik, absichtlich von der SQL-Abfrage getrennt: so laesst
     * sie sich in `tools/test-seitenbaum.php` ohne Datenbank pruefen — eine
     * Zeile ist hier alles, das mindestens `ID`, `post_parent`, `post_title`,
     * `menu_order` und `post_type` als Eigenschaften traegt (ein `stdClass`
     * oder ein WP_Post genuegt).
     *
     * Fachliches Vorbild ist `Theme/includes/page-index.php:135-249`
     * (`simple_clean_page_index_daten()`): Breitensuche ab Wurzel 0 loest
     * drei Probleme auf einmal — Tiefen ergeben sich ohne erneute Aufloesung
     * der Elternkette, verwaiste Knoten (Elternteil nicht in der Zeilenliste,
     * z. B. weil er ein Entwurf ist) fallen samt Unterbaum heraus, und ein
     * Zyklus (A→B→A) ist von der Wurzel aus grundsaetzlich unerreichbar.
     *
     * @param array $zeilen Zeilenobjekte mit ID, post_parent, post_title,
     *                       menu_order, post_type.
     * @return array{knoten: array, kinder: array, wurzeln: array}
     */
    public static function baue_seitenbaum($zeilen) {
        $roh    = [];
        $kinder = [];

        foreach ((array) $zeilen as $zeile) {
            // Verteidigung in der Tiefe: Beitraege sind nicht hierarchisch
            // und gehoeren laut Vertrag B nicht in den Baum. Die SQL-Abfrage
            // filtert bereits auf post_type = 'page' — diese Pruefung greift
            // zusaetzlich, falls jemand kuenftig eine andere Zeilenquelle an
            // baue_seitenbaum() uebergibt.
            $typ = isset($zeile->post_type) ? (string) $zeile->post_type : 'page';
            if ('page' !== $typ) {
                continue;
            }

            $id     = (int) $zeile->ID;
            $parent = (int) $zeile->post_parent;

            $roh[$id] = [
                'id' => $id,
                'parent' => $parent,
                'titel' => (string) $zeile->post_title,
                'menuOrder' => (int) $zeile->menu_order,
                'typ' => $typ,
            ];
            // Reihenfolge der SQL-Sortierung (menu_order, dann post_title)
            // bleibt erhalten.
            $kinder[$parent][] = $id;
        }

        // Breitensuche ab Wurzel 0 — siehe Methodenkommentar oben.
        $knoten    = [];
        $besucht   = [];
        $schlange  = [];
        $zeiger    = 0;
        $max_tiefe = 20;

        $wurzeln_roh = isset($kinder[0]) ? $kinder[0] : [];
        foreach ($wurzeln_roh as $id) {
            $schlange[] = [$id, 0];
        }

        while ($zeiger < count($schlange)) {
            list($id, $tiefe) = $schlange[$zeiger];
            $zeiger++;

            if (isset($besucht[$id]) || !isset($roh[$id])) {
                continue;
            }
            if ($tiefe > $max_tiefe) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'CBD Seitenbaum: Verschachtelung tiefer als %d Ebenen bei Seite %d - Zweig ausgelassen.',
                        $max_tiefe,
                        $id
                    ));
                }
                continue;
            }

            $besucht[$id] = true;

            $knoten[$id] = [
                'id' => $id,
                'parent' => $roh[$id]['parent'],
                'titel' => $roh[$id]['titel'],
                'menuOrder' => $roh[$id]['menuOrder'],
                'tiefe' => $tiefe,
                'typ' => $roh[$id]['typ'],
                'gesperrt' => false,
            ];

            if (isset($kinder[$id])) {
                foreach ($kinder[$id] as $kind_id) {
                    $schlange[] = [$kind_id, $tiefe + 1];
                }
            }
        }

        // `gesperrt` je ueberlebenden Knoten. update_meta_cache() VOR der
        // Schleife, sonst entsteht eine Abfrage je Seite (N+1). Fehlt die
        // Theme-Funktion, bleibt jedes `gesperrt` false — kein Fatal Error.
        $alle_ids = array_keys($knoten);
        if (function_exists('update_meta_cache') && !empty($alle_ids)) {
            update_meta_cache('post', $alle_ids);
        }

        if (function_exists('simple_clean_seite_nur_lehrpersonen')) {
            foreach ($knoten as $id => &$eintrag) {
                $eintrag['gesperrt'] = (bool) simple_clean_seite_nur_lehrpersonen($id);
            }
            unset($eintrag);
        }

        // Kinderliste neu aufbauen — nur mit den Knoten, die den Durchlauf
        // ueberstanden haben. Die Reihenfolge bleibt korrekt, weil die
        // Breitensuche Geschwister eines Elternteils zusammenhaengend und in
        // der urspruenglichen Sortierung abarbeitet (siehe Vorbild).
        $kinder_gefiltert = [];
        foreach ($knoten as $id => $eintrag) {
            $kinder_gefiltert[$eintrag['parent']][] = $id;
        }

        $wurzeln = isset($kinder_gefiltert[0]) ? $kinder_gefiltert[0] : [];

        return [
            'knoten' => $knoten,
            'kinder' => $kinder_gefiltert,
            'wurzeln' => $wurzeln,
        ];
    }

    /**
     * Nur fuer Tests: verwirft die gehaltene Antwort von `get_seitenbaum()`.
     * Ohne diesen Reset koennte ein Prueflauf innerhalb desselben
     * PHP-Prozesses nicht mehrere Baum-Fixtures nacheinander gegen den
     * REST-Callback pruefen — die zweite Anfrage saehe die Antwort der
     * ersten. Nach dem Vorbild von `CBD_Classroom_Gate::sitzung_vergessen()`.
     */
    public static function seitenbaum_cache_vergessen() {
        self::$seitenbaum_cache = null;
    }
}
