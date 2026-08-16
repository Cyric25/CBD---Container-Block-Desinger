<?php
/**
 * CBD Block Reference
 * Registers and handles the block-reference block
 *
 * @package ContainerBlockDesigner
 * @since 2.8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Reference {

    /**
     * Handle des Editor-Scripts
     *
     * Dasselbe Handle steht in blocks/block-reference/block.json unter
     * `editorScript`. Beide Stellen muessen zusammen geaendert werden.
     */
    const EDITOR_HANDLE = 'cbd-block-reference-editor';

    /**
     * Rueckfall-Handle des Frontend-Scripts.
     *
     * Aus `viewScript` in block.json bildet WordPress das Handle
     * `<blockname mit Bindestrichen>-view-script`. Ermittelt wird es
     * trotzdem lieber aus dem registrierten Blocktyp — die Regel ist
     * WordPress-intern und koennte sich aendern.
     */
    const VIEW_HANDLE_FALLBACK = 'cbd-block-reference-view-script';

    /** Name des JavaScript-Objekts mit den Frontend-Daten. */
    const VIEW_DATA_OBJECT = 'cbdBlockReference';

    /**
     * Initialize the block
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_block']);
    }

    /**
     * Register the block-reference block
     */
    public static function register_block() {
        // Check if block.json exists
        $block_json_path = CBD_PLUGIN_DIR . 'blocks/block-reference/block.json';

        if (!file_exists($block_json_path)) {
            return;
        }

        // Editor-Script VOR der Blockregistrierung anmelden — block.json
        // verweist nur noch auf das Handle.
        self::register_editor_script();

        // Register block from block.json
        $block_type = register_block_type($block_json_path, [
            'render_callback' => [__CLASS__, 'render_block'],
        ]);

        // Daten fuer das Modal an das Frontend-Script haengen. Die Daten
        // werden nur ausgegeben, wenn das Script auch eingebunden wird —
        // `viewScript` laedt WordPress erst, wenn der Block auf der Seite
        // vorkommt.
        self::localize_view_script($block_type);
    }

    /**
     * Handle des Frontend-Scripts ermitteln.
     *
     * @param WP_Block_Type|false|null $block_type Rueckgabe von register_block_type()
     * @return string
     */
    public static function view_script_handle($block_type = null) {
        if (is_object($block_type)) {
            // WordPress 6.1+: Liste von Handles
            if (isset($block_type->view_script_handles) && is_array($block_type->view_script_handles)) {
                $handles = $block_type->view_script_handles;
                if (!empty($handles)) {
                    $erstes = reset($handles);
                    if (is_string($erstes) && '' !== $erstes) {
                        return $erstes;
                    }
                }
            }

            // WordPress 6.0: Einzelwert
            if (isset($block_type->view_script)
                && is_string($block_type->view_script)
                && '' !== $block_type->view_script) {
                return $block_type->view_script;
            }
        }

        return self::VIEW_HANDLE_FALLBACK;
    }

    /**
     * Die REST-Wurzel und die Texte an das Frontend-Script haengen.
     *
     * DIE URL WIRD NICHT IM JAVASCRIPT GEBAUT. Auf manchen Installationen
     * liefert der huebsche Pfad `/wp-json/…` einen Apache-404; dort
     * funktioniert nur `?rest_route=/cbd/v1/block-html`. Welche Form gilt,
     * weiss allein `rest_url()`. Ein fest verdrahteter Pfad im JavaScript
     * bricht auf genau diesen Installationen.
     *
     * DER NONCE IST KEINE AUTORISIERUNG. Er sorgt nur dafuer, dass
     * angemeldete Nutzer bei REST-Aufrufen als angemeldet erkannt werden
     * (ohne ihn gilt die Cookie-Anfrage als anonym). Die gesamte
     * Rechtepruefung leistet CBD_Block_Content_API. Fuer nicht Angemeldete
     * bleibt das Feld leer — `wp_create_nonce()` liefert dort fuer alle
     * denselben Wert und waere ohnehin wertlos.
     *
     * @param WP_Block_Type|false|null $block_type
     * @return void
     */
    public static function localize_view_script($block_type = null) {
        $handle = self::view_script_handle($block_type);

        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        // Der Pfad wird aus den Konstanten des Endpunkts gebildet, nicht
        // abgeschrieben — sonst laufen Route und Aufruf irgendwann
        // auseinander. Fehlt die Klasse (Datei nicht geladen), bleibt der
        // literale Pfad als Rueckfall.
        $route = 'cbd/v1/block-html';
        if (class_exists('CBD_Block_Content_API')) {
            $route = CBD_Block_Content_API::REST_NAMESPACE . CBD_Block_Content_API::REST_ROUTE;
        }

        $daten = array(
            'restUrl' => esc_url_raw(rest_url($route)),
            'nonce'   => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'texte'   => array(
                'laden'            => __('Inhalt wird geladen …', 'container-block-designer'),
                'nichtVerfuegbar'  => __('Dieser Block ist nicht verfügbar.', 'container-block-designer'),
                'schliessen'       => __('Schließen', 'container-block-designer'),
                'titelVorgabe'     => __('Block', 'container-block-designer'),
            ),
        );

        wp_localize_script($handle, self::VIEW_DATA_OBJECT, $daten);
    }

    /**
     * Editor-Script von Hand registrieren
     *
     * Das Plugin hat KEINEN Build-Schritt, also gibt es keine
     * index.asset.php. Wuerde block.json weiterhin `file:./index.js`
     * angeben, registrierte WordPress das Script ohne Abhaengigkeiten und
     * `wp.blocks` waere beim Ausfuehren womoeglich noch nicht geladen.
     * Muster uebernommen von `cbd-block-editor` in
     * CBD_Block_Registration::enqueue_block_editor_assets().
     */
    public static function register_editor_script() {
        if (wp_script_is(self::EDITOR_HANDLE, 'registered')) {
            return;
        }

        $relativ = 'blocks/block-reference/index.js';
        $pfad = CBD_PLUGIN_DIR . $relativ;

        if (!file_exists($pfad)) {
            return;
        }

        // Cache-Busting ueber filemtime(): Die Datei aendert sich auch
        // zwischen zwei Plugin-Versionen, CBD_VERSION allein genuegt nicht.
        $version = defined('CBD_VERSION') ? CBD_VERSION : '';
        $mtime = filemtime($pfad);
        if ($mtime) {
            $version = $version . '.' . $mtime;
        }

        wp_register_script(
            self::EDITOR_HANDLE,
            CBD_PLUGIN_URL . $relativ,
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch'),
            $version,
            true
        );
    }

    /**
     * Render the block on the frontend
     *
     * @param array $attributes Block attributes
     * @param string $content Block inner content
     * @param WP_Block $block Block instance
     * @return string Rendered HTML
     */
    public static function render_block($attributes, $content, $block) {
        // Include the render template
        $render_file = CBD_PLUGIN_DIR . 'blocks/block-reference/render.php';

        if (!file_exists($render_file)) {
            return '';
        }

        // Start output buffering
        ob_start();

        // Include the template
        include $render_file;

        // Return the buffered content
        return ob_get_clean();
    }
}
