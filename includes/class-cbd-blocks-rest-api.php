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
     * Innerhalb einer Anfrage gehaltene Ergebnisse von `get_seitenbaum()`,
     * ein Eintrag je Parametervariante (siehe `get_seitenbaum()`). Bewusst
     * eine Klasseneigenschaft statt einer Funktions-static: nur so laesst
     * sie sich fuer Tests zuruecksetzen (`seitenbaum_cache_vergessen()`).
     *
     * Seit dem opt-in Parameter `entwuerfe` (Vorhaben „Seitenimporter-
     * Kaskaden-Zielauswahl") ein Array statt eines einzelnen Werts: Zwei
     * Aufrufe mit unterschiedlichem Parameterwert innerhalb derselben
     * Anfrage muessen getrennt gecacht werden, sonst liefert der zweite
     * Aufruf faelschlich das gecachte Ergebnis des ersten.
     *
     * @var array<string, WP_REST_Response>
     */
    private static $seitenbaum_cache = array();

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
        // orderby ist 'title' (AP-3.fix3, Befund S5). AP-3.1 hatte hier
        // testweise zuerst nach menu_order und danach nach title sortiert,
        // mit der Begruendung "damit die Reihenfolge innerhalb einer
        // Hierarchieebene nicht willkuerlich ist" - das war ein Denkfehler
        // des Plans: Die Ebenenreihenfolge liefert Vertrag B ueber `kinder`,
        // ebenen() in block-auswahl.js benutzt die Reihenfolge dieser
        // flachen Liste fuer Seiten gar nicht. Wirksam wurde die
        // menu_order-Sortierung ausschliesslich in der flachen
        // Suchtrefferliste (block-auswahl.js) und verschlechterte sie dort
        // gegenueber rein alphabetisch (erst alle menu_order=0 alphabetisch,
        // dann alle menu_order=1, quer ueber Beitraege/Seiten/Ebenen). Bitte
        // nicht ein drittes Mal auf diese zweiteilige Sortierung aendern.
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
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
     * Anfrage wird das Ergebnis je Parametervariante in einer
     * Klasseneigenschaft gehalten (mehrfache Aufrufe derselben Variante
     * kosten dann keine weitere Abfrage).
     *
     * Seit dem Vorhaben „Seitenimporter-Kaskaden-Zielauswahl": optionaler
     * Query-Parameter `entwuerfe`. Nur der Wert `'1'` schliesst zusaetzlich
     * Seiten mit `post_status = 'draft'` ein - jeder andere Wert (inkl.
     * fehlendem Parameter) verhaelt sich exakt wie bisher (nur `publish`).
     * Das ist bewusst additiv und rein opt-in: `assets/js/block-auswahl.js`
     * ruft dieselbe Route ohne diesen Parameter auf und muss weiterhin nur
     * veroeffentlichte Seiten bekommen - keine Aenderung fuer diesen
     * bestehenden Konsumenten.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_seitenbaum($request) {
        $mit_entwuerfe = ('1' === (string) $request->get_param('entwuerfe'));
        $cache_key = $mit_entwuerfe ? 'mit_entwuerfe' : 'ohne_entwuerfe';

        if (isset(self::$seitenbaum_cache[$cache_key])) {
            return self::$seitenbaum_cache[$cache_key];
        }

        global $wpdb;

        // Nur fuenf Spalten, kein post_content. Die WHERE-Klausel
        // beschraenkt bereits auf post_type = 'page' — Beitraege sind nicht
        // hierarchisch (post_parent wird fuer sie von WordPress nicht
        // gepflegt) und gehoeren laut Vertrag B nicht in diesen Baum. Die
        // Spalte post_type wird trotzdem mitgelesen, weil `baue_seitenbaum()`
        // sie zusaetzlich als Verteidigung in der Tiefe prueft, statt sich
        // allein auf die WHERE-Klausel zu verlassen.
        $status_klausel = $mit_entwuerfe
            ? "post_status IN ('publish', 'draft')"
            : "post_status = 'publish'";

        $zeilen = $wpdb->get_results(
            "SELECT ID, post_parent, post_title, menu_order, post_type
             FROM {$wpdb->posts}
             WHERE post_type = 'page' AND {$status_klausel}
             ORDER BY menu_order ASC, post_title ASC"
        );

        $baum = self::baue_seitenbaum($zeilen);

        // Vertrag B / AP-3.fix3, Befund S1: `knoten` und `kinder` muessen als
        // JSON-OBJEKT herausgehen, auch wenn ihre Schluessel zufaellig
        // 0..n-1 lauten - z. B. eine rein flache Seitenmenge (nur Wurzeln),
        // dann hat `kinder` ausschliesslich den Schluessel 0.
        // json_encode() eines PHP-Arrays mit sequentiellen Schluesseln
        // 0..n-1 ergibt sonst eine JSON-LISTE statt eines Objekts, und
        // block-auswahl.js (normalisiereBaum) verwirft eine solche Liste
        // stillschweigend und ersetzt sie durch {}.
        //
        // Der Cast steht bewusst HIER und nicht in baue_seitenbaum() selbst:
        // Diese Methode bleibt reine, mit PHP-Arrays arbeitende Aufbaulogik
        // (siehe ihr eigener Docblock) - der weit ueberwiegende Teil von
        // tools/test-seitenbaum.php prueft sie direkt per Array-Zugriff
        // (z. B. $baum['knoten'][12]['tiefe']). Ein Objekt an dieser Stelle
        // liesse sich so nicht mehr indizieren. Der Cast betrifft nur die
        // tatsaechlich nach aussen gehende JSON-Antwort dieser Methode.
        $baum['knoten'] = (object) $baum['knoten'];
        $baum['kinder'] = (object) $baum['kinder'];

        self::$seitenbaum_cache[$cache_key] = new WP_REST_Response($baum, 200);

        return self::$seitenbaum_cache[$cache_key];
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

        // `gesperrt` je ueberlebenden Knoten.
        //
        // update_meta_cache() steht NICHT hier, sondern erst im Stufe-2-Zweig
        // unten (AP-3.fix3, Befund S2). Vorher stand der Aufruf unbedingt an
        // dieser Stelle mit dem Kommentar "fuer Stufe 1 kostet er nichts" -
        // das war falsch: Es ist ein SELECT ... WHERE post_id IN (...) ueber
        // ALLE ueberlebenden Seiten-IDs, das saemtliche Postmeta in den
        // Objektcache laedt, waehrend Stufe 1
        // (simple_clean_gesperrte_seiten_mit_unterbaum()) ueberhaupt keine
        // Meta liest - sie bekommt ihre fertige Karte vom Theme. Nicht O(n)
        // Abfragen, aber unnoetig O(n) Datenvolumen bei jedem Editor-Aufruf.
        // Dreistufige Kette (AP-3.fix1), jede Stufe hinter function_exists().
        //
        // Stufe 2 allein (simple_clean_seite_nur_lehrpersonen() JE SEITE)
        // ruft intern get_post_ancestors() auf, sobald ueberhaupt eine Seite
        // gesperrt ist (Theme/includes/sichtbarkeit.php:229-233). Die rohe
        // $wpdb-Abfrage oben fuellt den WordPress-Post-Cache nicht — auf
        // einer Installation mit 258 Seiten und mindestens einer gesperrten
        // Seite waeren das bis zu mehrere hundert Einzelabfragen.
        //
        // Stufe 1 nutzt stattdessen die vom Theme bereits memoisierte
        // Nachschlagekarte simple_clean_gesperrte_seiten_mit_unterbaum()
        // (Theme/includes/sichtbarkeit.php:142-190): liefert ALLE gesperrten
        // Seiten EINSCHLIESSLICH ihres gesamten Unterbaums in hoechstens
        // zwei Abfragen insgesamt, unabhaengig von der Seitenzahl, und
        // entfaellt komplett, wenn nichts gesperrt ist. Ein `isset()` auf
        // dieser Karte ist inhaltlich dasselbe wie
        // simple_clean_seite_nur_lehrpersonen($id) je Seite.
        //
        // Stufe 2 bleibt als Rueckfall erhalten fuer ein Theme aelteren
        // Stands, das die Karten-Funktion noch nicht kennt (identisches
        // Verhalten zu AP-3.1). Stufe 3: kein Theme vorhanden, jedes
        // `gesperrt` bleibt false (Vorgabewert oben).
        if (function_exists('simple_clean_gesperrte_seiten_mit_unterbaum')) {
            $gesperrte_karte = simple_clean_gesperrte_seiten_mit_unterbaum();
            foreach ($knoten as $id => &$eintrag) {
                $eintrag['gesperrt'] = isset($gesperrte_karte[$id]);
            }
            unset($eintrag);
        } elseif (function_exists('simple_clean_seite_nur_lehrpersonen')) {
            // update_meta_cache() NUR HIER (Stufe 2, AP-3.fix3 Befund S2):
            // Nur diese Stufe liest ueberhaupt Post-Meta
            // (simple_clean_seite_nur_lehrpersonen() je Seite). VOR der
            // Schleife, sonst entsteht eine Abfrage je Seite (N+1). Stufe 1
            // oben braucht das nicht - sie nutzt die bereits fertige Karte
            // des Themes.
            $alle_ids = array_keys($knoten);
            if (function_exists('update_meta_cache') && !empty($alle_ids)) {
                update_meta_cache('post', $alle_ids);
            }
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
        self::$seitenbaum_cache = array();
    }
}
