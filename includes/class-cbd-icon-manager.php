<?php
/**
 * Icon-Verwaltung im Admin
 *
 * Verarbeitet Upload und Löschen eigener SVG-Kacheln. Zielverzeichnis ist
 * immer das Override-Verzeichnis in uploads/cbd-icons/ — die mit dem Plugin
 * ausgelieferten Icons unter assets/icons/ werden nie verändert oder
 * gelöscht. Ein hochgeladenes Icon überdeckt das gleichnamige Plugin-Icon;
 * löscht man es wieder, kommt das Original zurück.
 *
 * Sicherheitskette pro Upload:
 *   1. Capability cbd_admin_blocks
 *   2. Nonce
 *   3. Endung/MIME muss SVG sein
 *   4. Dateiname wird auf [a-z0-9_-] normalisiert (kein Traversal)
 *   5. Inhalt läuft durch CBD_SVG_Sanitizer (Whitelist)
 *   6. Geschrieben wird nur das Ergebnis des Sanitizers, nie das Original
 *
 * @package ContainerBlockDesigner
 * @since 3.1.77
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_Icon_Manager {

    const CAPABILITY  = 'cbd_admin_blocks';
    const PAGE_SLUG   = 'cbd-icons';
    const MAX_UPLOADS = 200;

    /**
     * Hooks registrieren.
     */
    public static function init() {
        add_action('admin_post_cbd_icon_upload', array(__CLASS__, 'handle_upload'));
        add_action('admin_post_cbd_icon_delete', array(__CLASS__, 'handle_delete'));
        add_action('admin_post_cbd_icon_flush', array(__CLASS__, 'handle_flush'));
    }

    /**
     * URL der Verwaltungsseite, optional mit Rückmeldung.
     *
     * @param array $args
     * @return string
     */
    public static function page_url($args = array()) {
        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);

        return empty($args) ? $url : add_query_arg($args, $url);
    }

    /**
     * Gemeinsame Vorprüfung für alle Aktionen.
     *
     * @param string $nonce_action
     */
    private static function guard($nonce_action) {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__('Du hast keine Berechtigung, Icons zu verwalten.', 'container-block-designer'),
                403
            );
        }

        check_admin_referer($nonce_action);
    }

    /**
     * Upload verarbeiten.
     */
    public static function handle_upload() {
        self::guard('cbd_icon_upload');

        $group = isset($_POST['cbd_icon_group']) ? sanitize_key(wp_unslash($_POST['cbd_icon_group'])) : '';

        if (!isset(CBD_Icon_Library::GROUPS[$group])) {
            self::redirect(array('cbd_error' => 'group'));
        }

        if (empty($_FILES['cbd_icon_files']) || empty($_FILES['cbd_icon_files']['name'])) {
            self::redirect(array('cbd_error' => 'nofile'));
        }

        $target_dir = self::ensure_group_dir($group);
        if ('' === $target_dir) {
            self::redirect(array('cbd_error' => 'dir'));
        }

        $files = self::normalize_files($_FILES['cbd_icon_files']);

        if (count($files) > self::MAX_UPLOADS) {
            self::redirect(array('cbd_error' => 'toomany'));
        }

        // Optionaler Wunschname — nur sinnvoll bei genau einer Datei
        $forced_name = '';
        if (1 === count($files) && !empty($_POST['cbd_icon_name'])) {
            $forced_name = self::sanitize_icon_name(wp_unslash($_POST['cbd_icon_name']));
        }

        $saved    = 0;
        $skipped  = array();
        $cleaned  = array();

        foreach ($files as $file) {
            $result = self::store_file($file, $target_dir, $forced_name);

            if (true === $result['ok']) {
                $saved++;
                if (!empty($result['removed'])) {
                    $cleaned[] = $result['name'] . ': ' . implode(', ', $result['removed']);
                }
            } else {
                $skipped[] = $result['name'] . ': ' . $result['error'];
            }
        }

        CBD_Icon_Library::flush_cache();

        // Meldungen als Transient — sie können lang werden und haben in der
        // URL nichts verloren.
        set_transient(self::report_key(), array(
            'saved'   => $saved,
            'skipped' => $skipped,
            'cleaned' => $cleaned,
        ), 60);

        self::redirect(array('cbd_uploaded' => $saved));
    }

    /**
     * Einzelne Datei prüfen, säubern und ablegen.
     *
     * @param array  $file        Eintrag aus $_FILES
     * @param string $target_dir  Zielverzeichnis (existiert bereits)
     * @param string $forced_name Optionaler Wunschname ohne Endung
     * @return array ['ok' => bool, 'name' => string, 'error' => string, 'removed' => array]
     */
    private static function store_file($file, $target_dir, $forced_name = '') {
        $display = isset($file['name']) ? sanitize_text_field($file['name']) : '?';
        $fail    = function ($message) use ($display) {
            return array('ok' => false, 'name' => $display, 'error' => $message, 'removed' => array());
        };

        if (!isset($file['error']) || UPLOAD_ERR_OK !== $file['error']) {
            return $fail(self::upload_error_message(isset($file['error']) ? $file['error'] : -1));
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return $fail(__('kein regulärer Upload', 'container-block-designer'));
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ('svg' !== $ext) {
            return $fail(__('nur SVG-Dateien werden angenommen', 'container-block-designer'));
        }

        $name = ('' !== $forced_name)
            ? $forced_name
            : self::sanitize_icon_name(pathinfo($file['name'], PATHINFO_FILENAME));

        if ('' === $name) {
            return $fail(__('unbrauchbarer Dateiname', 'container-block-designer'));
        }

        $contents = file_get_contents($file['tmp_name']);
        if (false === $contents) {
            return $fail(__('Datei nicht lesbar', 'container-block-designer'));
        }

        $clean = CBD_SVG_Sanitizer::sanitize($contents);
        if (null === $clean['svg']) {
            return $fail($clean['error']);
        }

        // Immer das gesäuberte Ergebnis schreiben, nie die Originaldatei
        // verschieben — sonst landet ungeprüfter Inhalt im Web-Verzeichnis.
        $path    = trailingslashit($target_dir) . $name . '.svg';
        $written = file_put_contents($path, $clean['svg']);

        if (false === $written) {
            return $fail(__('Schreiben fehlgeschlagen', 'container-block-designer'));
        }

        @chmod($path, 0644);

        return array('ok' => true, 'name' => $name, 'error' => '', 'removed' => $clean['removed']);
    }

    /**
     * Ein hochgeladenes Icon löschen (nur im Override-Verzeichnis).
     */
    public static function handle_delete() {
        self::guard('cbd_icon_delete');

        $value  = isset($_POST['cbd_icon_value']) ? wp_unslash($_POST['cbd_icon_value']) : '';
        $parsed = CBD_Icon_Library::parse_value($value);

        if (null === $parsed) {
            self::redirect(array('cbd_error' => 'value'));
        }

        $override = CBD_Icon_Library::get_override_dir();
        if ('' === $override) {
            self::redirect(array('cbd_error' => 'dir'));
        }

        $path = $override . $parsed['group'] . '/' . $parsed['name'] . '.svg';

        // Doppelter Boden: der aufgelöste Pfad muss im Override-Verzeichnis
        // liegen. parse_value() filtert bereits, aber Löschen ist
        // unwiderruflich — hier lieber einmal zu viel geprüft.
        $real_path = realpath($path);
        $real_base = realpath($override);

        if (false === $real_path || false === $real_base || 0 !== strpos($real_path, $real_base)) {
            self::redirect(array('cbd_error' => 'notfound'));
        }

        if (!@unlink($real_path)) {
            self::redirect(array('cbd_error' => 'delete'));
        }

        CBD_Icon_Library::flush_cache();
        self::redirect(array('cbd_deleted' => 1));
    }

    /**
     * Cache manuell leeren (z. B. nach einem Upload per FTP).
     */
    public static function handle_flush() {
        self::guard('cbd_icon_flush');

        CBD_Icon_Library::flush_cache();
        self::redirect(array('cbd_flushed' => 1));
    }

    /**
     * Zielverzeichnis anlegen und absichern.
     *
     * @param string $group
     * @return string Absoluter Pfad oder Leerstring
     */
    private static function ensure_group_dir($group) {
        $base = CBD_Icon_Library::get_override_dir();

        if ('' === $base || !isset(CBD_Icon_Library::GROUPS[$group])) {
            return '';
        }

        $dir = $base . $group;

        if (!wp_mkdir_p($dir)) {
            return '';
        }

        self::harden_directory($base);

        return $dir;
    }

    /**
     * Verhindert, dass im Icon-Verzeichnis versehentlich Ausführbares landet.
     *
     * Geschrieben werden ohnehin nur .svg-Dateien mit normalisiertem Namen;
     * das hier ist die zweite Verteidigungslinie für den Fall, dass jemand
     * anderes (Backup, FTP, anderes Plugin) etwas ablegt.
     *
     * @param string $base
     */
    private static function harden_directory($base) {
        $htaccess = trailingslashit($base) . '.htaccess';

        if (file_exists($htaccess)) {
            return;
        }

        $rules = "# Von Container Block Designer erzeugt.\n"
            . "# Nur Bilddateien ausliefern, niemals Skripte ausfuehren.\n"
            . "<FilesMatch \"\\.(?i:php|phtml|phar|php[0-9]|cgi|pl|py|sh|htm|html)$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n";

        @file_put_contents($htaccess, $rules);

        $index = trailingslashit($base) . 'index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
    }

    /**
     * $_FILES eines multiple-Inputs in eine Liste einzelner Dateien umbauen.
     *
     * @param array $files
     * @return array
     */
    private static function normalize_files($files) {
        $out = array();

        if (!isset($files['name'])) {
            return $out;
        }

        if (!is_array($files['name'])) {
            return array($files);
        }

        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ('' === $files['name'][$i]) {
                continue;
            }

            $out[] = array(
                'name'     => $files['name'][$i],
                'type'     => isset($files['type'][$i]) ? $files['type'][$i] : '',
                'tmp_name' => isset($files['tmp_name'][$i]) ? $files['tmp_name'][$i] : '',
                'error'    => isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_NO_FILE,
                'size'     => isset($files['size'][$i]) ? $files['size'][$i] : 0,
            );
        }

        return $out;
    }

    /**
     * Dateinamen auf das Muster reduzieren, das CBD_Icon_Library akzeptiert.
     *
     * Umlaute werden transliteriert, alles Übrige auf [a-z0-9_-] gestutzt —
     * damit kann der Name weder aus dem Verzeichnis ausbrechen noch später
     * die URL-Bildung stören.
     *
     * @param string $raw
     * @return string
     */
    public static function sanitize_icon_name($raw) {
        $name = strtolower(remove_accents((string) $raw));
        $name = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $name);
        $name = preg_replace('/[^a-z0-9_-]+/', '-', $name);
        $name = trim($name, '-_');
        $name = preg_replace('/-{2,}/', '-', $name);

        // Nach dem trim() beginnt der Name zwangsläufig alphanumerisch —
        // genau das verlangt CBD_Icon_Library::parse_value().
        return substr($name, 0, 64);
    }

    /**
     * Lesbare Meldung zu einem PHP-Upload-Fehlercode.
     *
     * @param int $code
     * @return string
     */
    private static function upload_error_message($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('Datei überschreitet die Upload-Größe des Servers', 'container-block-designer');
            case UPLOAD_ERR_PARTIAL:
                return __('Upload wurde abgebrochen', 'container-block-designer');
            case UPLOAD_ERR_NO_FILE:
                return __('keine Datei übertragen', 'container-block-designer');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return __('Server konnte die Datei nicht zwischenspeichern', 'container-block-designer');
            default:
                return __('unbekannter Upload-Fehler', 'container-block-designer');
        }
    }

    /**
     * Transient-Key des Upload-Berichts (pro Benutzer).
     *
     * @return string
     */
    public static function report_key() {
        return 'cbd_icon_report_' . get_current_user_id();
    }

    /**
     * Zurück zur Verwaltungsseite.
     *
     * @param array $args
     */
    private static function redirect($args) {
        wp_safe_redirect(self::page_url($args));
        exit;
    }
}
