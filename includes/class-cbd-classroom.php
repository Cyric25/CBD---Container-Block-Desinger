<?php
/**
 * Container Block Designer - Classroom System (Klassen-System)
 *
 * Handles class management, drawing persistence, and student access.
 * This feature is optionally activatable via plugin settings.
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classroom System Class
 *
 * Provides:
 * - Teacher: Class CRUD (create, read, update, delete)
 * - Teacher: Server-side drawing save/load per class
 * - Teacher: Mark blocks as "behandelt" (covered)
 * - Student: Password-based access via shortcode
 */
class CBD_Classroom {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option key for enabling/disabling the classroom system
     */
    const OPTION_ENABLED = 'cbd_classroom_enabled';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks only if classroom system is enabled
     */
    private function __construct() {
        // Always register the settings hook (so the toggle is available)
        add_action('admin_init', array($this, 'register_settings'));

        // Only register classroom functionality if enabled
        if (!self::is_enabled()) {
            return;
        }

        // AJAX handlers for teachers (logged-in users)
        add_action('wp_ajax_cbd_save_class', array($this, 'ajax_save_class'));
        add_action('wp_ajax_cbd_delete_class', array($this, 'ajax_delete_class'));
        add_action('wp_ajax_cbd_get_classes', array($this, 'ajax_get_classes'));
        add_action('wp_ajax_cbd_save_drawing', array($this, 'ajax_save_drawing'));
        add_action('wp_ajax_cbd_load_drawing', array($this, 'ajax_load_drawing'));
        add_action('wp_ajax_cbd_toggle_behandelt', array($this, 'ajax_toggle_behandelt'));

        // AJAX handlers for students (no login required)
        add_action('wp_ajax_nopriv_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_nopriv_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_nopriv_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));
        // Also allow logged-in users to use student endpoints
        add_action('wp_ajax_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));

        // Shortcode for student access
        add_shortcode('cbd_classroom', array($this, 'render_classroom_shortcode'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Check if the classroom system is enabled
     */
    public static function is_enabled() {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    /**
     * Register settings for the classroom toggle
     */
    public function register_settings() {
        register_setting('cbd_settings', self::OPTION_ENABLED, array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
    }

    // =========================================================================
    // TEACHER: Class CRUD
    // =========================================================================

    /**
     * AJAX: Save (create or update) a class
     */
    public function ajax_save_class() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_CLASSES;

        $class_id = intval($_POST['class_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $page_ids = array_map('intval', (array) ($_POST['page_ids'] ?? array()));

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Klassenname ist erforderlich.'));
        }

        if ($class_id > 0) {
            // Update existing class
            $update_data = array(
                'name' => $name,
                'updated_at' => current_time('mysql')
            );

            // Only update password if a new one was provided
            if (!empty($password)) {
                $update_data['password'] = wp_hash_password($password);
            }

            $wpdb->update($table, $update_data, array('id' => $class_id, 'teacher_id' => get_current_user_id()));
        } else {
            // Create new class
            if (empty($password)) {
                wp_send_json_error(array('message' => 'Passwort ist erforderlich.'));
            }

            $wpdb->insert($table, array(
                'name' => $name,
                'password' => wp_hash_password($password),
                'teacher_id' => get_current_user_id(),
                'status' => 'active'
            ));
            $class_id = $wpdb->insert_id;
        }

        if (!$class_id) {
            wp_send_json_error(array('message' => 'Fehler beim Speichern.'));
        }

        // Update page assignments
        $pages_table = CBD_TABLE_CLASS_PAGES;
        $wpdb->delete($pages_table, array('class_id' => $class_id));

        foreach ($page_ids as $sort => $page_id) {
            if ($page_id > 0) {
                $wpdb->insert($pages_table, array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'sort_order' => $sort
                ));
            }
        }

        wp_send_json_success(array(
            'message' => 'Klasse gespeichert.',
            'class_id' => $class_id
        ));
    }

    /**
     * AJAX: Delete a class
     */
    public function ajax_delete_class() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $class_id = intval($_POST['class_id'] ?? 0);

        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Ungueltige Klassen-ID.'));
        }

        // Verify ownership
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, get_current_user_id()
        ));

        if (!$class) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Delete related data
        $wpdb->delete(CBD_TABLE_CLASS_PAGES, array('class_id' => $class_id));
        $wpdb->delete(CBD_TABLE_DRAWINGS, array('class_id' => $class_id));
        $wpdb->delete(CBD_TABLE_CLASSES, array('id' => $class_id));

        wp_send_json_success(array('message' => 'Klasse geloescht.'));
    }

    /**
     * AJAX: Get all classes for the current teacher
     */
    public function ajax_get_classes() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $teacher_id = get_current_user_id();

        $classes = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, status, created_at, updated_at FROM " . CBD_TABLE_CLASSES . " WHERE teacher_id = %d ORDER BY name ASC",
            $teacher_id
        ));

        // Attach page assignments to each class
        foreach ($classes as &$class) {
            $class->pages = $wpdb->get_results($wpdb->prepare(
                "SELECT cp.page_id, cp.sort_order, p.post_title
                 FROM " . CBD_TABLE_CLASS_PAGES . " cp
                 LEFT JOIN {$wpdb->posts} p ON cp.page_id = p.ID
                 WHERE cp.class_id = %d
                 ORDER BY cp.sort_order ASC",
                $class->id
            ));
        }

        wp_send_json_success($classes);
    }

    // =========================================================================
    // TEACHER: Drawing Save/Load
    // =========================================================================

    /**
     * AJAX: Save a drawing to the server
     */
    public function ajax_save_drawing() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');
        $drawing_data = $_POST['drawing_data'] ?? '';

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Verify teacher owns this class
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, get_current_user_id()
        ));

        if (!$class) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Upsert: Insert or update drawing
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        if ($existing) {
            $wpdb->update($table, array(
                'drawing_data' => $drawing_data,
                'updated_at' => current_time('mysql')
            ), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'drawing_data' => $drawing_data
            ));
        }

        wp_send_json_success(array('message' => 'Zeichnung gespeichert.'));
    }

    /**
     * AJAX: Load a drawing from the server
     */
    public function ajax_load_drawing() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        $drawing = $wpdb->get_row($wpdb->prepare(
            "SELECT drawing_data, is_behandelt FROM " . CBD_TABLE_DRAWINGS . " WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        wp_send_json_success(array(
            'drawing_data' => $drawing ? $drawing->drawing_data : null,
            'is_behandelt' => $drawing ? (bool) $drawing->is_behandelt : false
        ));
    }

    // =========================================================================
    // TEACHER: Behandelt (Covered) Toggle
    // =========================================================================

    /**
     * AJAX: Toggle "behandelt" status for a block
     */
    public function ajax_toggle_behandelt() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Verify teacher owns this class
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, get_current_user_id()
        ));

        if (!$class) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Get or create drawing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        if ($existing) {
            $new_status = $existing->is_behandelt ? 0 : 1;
            $wpdb->update($table, array(
                'is_behandelt' => $new_status,
                'updated_at' => current_time('mysql')
            ), array('id' => $existing->id));
        } else {
            $new_status = 1;
            $wpdb->insert($table, array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ));
        }

        wp_send_json_success(array(
            'is_behandelt' => (bool) $new_status,
            'message' => $new_status ? 'Als behandelt markiert.' : 'Markierung entfernt.'
        ));
    }

    // =========================================================================
    // STUDENT: Authentication & Data Access
    // =========================================================================

    /**
     * AJAX: Student authentication via class password
     */
    public function ajax_student_auth() {
        // Rate limiting check
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_key = 'cbd_auth_attempts_' . md5($ip);
        $attempts = (int) get_transient($rate_key);

        if ($attempts >= 10) {
            wp_send_json_error(array('message' => 'Zu viele Versuche. Bitte spaeter erneut versuchen.'));
        }

        $class_id = intval($_POST['class_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($class_id <= 0 || empty($password)) {
            wp_send_json_error(array('message' => 'Klasse und Passwort erforderlich.'));
        }

        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, password FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND status = 'active'",
            $class_id
        ));

        if (!$class || !wp_check_password($password, $class->password)) {
            // Increment rate limit
            set_transient($rate_key, $attempts + 1, 300); // 5 minutes
            wp_send_json_error(array('message' => 'Falsches Passwort.'));
        }

        // Generate session token
        $token = wp_generate_password(64, false);
        $token_key = 'cbd_student_token_' . md5($token);

        // Store token as transient (24 hours)
        set_transient($token_key, array(
            'class_id' => $class_id,
            'class_name' => $class->name,
            'created' => time()
        ), DAY_IN_SECONDS);

        wp_send_json_success(array(
            'token' => $token,
            'class_name' => $class->name,
            'class_id' => $class_id
        ));
    }

    /**
     * AJAX: Get class data for authenticated student
     */
    public function ajax_student_get_data() {
        $token = sanitize_text_field($_POST['token'] ?? '');

        if (empty($token)) {
            wp_send_json_error(array('message' => 'Nicht authentifiziert.'));
        }

        $token_key = 'cbd_student_token_' . md5($token);
        $session = get_transient($token_key);

        if (!$session) {
            wp_send_json_error(array('message' => 'Sitzung abgelaufen. Bitte erneut einloggen.'));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // Get pages for this class
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT cp.page_id, cp.sort_order, p.post_title, p.post_content
             FROM " . CBD_TABLE_CLASS_PAGES . " cp
             LEFT JOIN {$wpdb->posts} p ON cp.page_id = p.ID
             WHERE cp.class_id = %d AND p.post_status = 'publish'
             ORDER BY cp.sort_order ASC",
            $class_id
        ));

        // Get drawings and behandelt status for this class
        $drawings = $wpdb->get_results($wpdb->prepare(
            "SELECT page_id, container_id, drawing_data, is_behandelt
             FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d",
            $class_id
        ));

        // Organize drawings by page_id -> container_id
        $drawings_map = array();
        foreach ($drawings as $drawing) {
            if (!isset($drawings_map[$drawing->page_id])) {
                $drawings_map[$drawing->page_id] = array();
            }
            $drawings_map[$drawing->page_id][$drawing->container_id] = array(
                'drawing_data' => $drawing->drawing_data,
                'is_behandelt' => (bool) $drawing->is_behandelt
            );
        }

        // Render page content through WordPress filters
        $rendered_pages = array();
        foreach ($pages as $page) {
            $rendered_content = apply_filters('the_content', $page->post_content);
            $rendered_pages[] = array(
                'page_id' => $page->page_id,
                'title' => $page->post_title,
                'content' => $rendered_content,
                'drawings' => $drawings_map[$page->page_id] ?? array()
            );
        }

        wp_send_json_success(array(
            'class_name' => $session['class_name'],
            'pages' => $rendered_pages
        ));
    }

    /**
     * AJAX: Get list of public classes (names only, no passwords)
     */
    public function ajax_get_public_classes() {
        global $wpdb;

        $classes = $wpdb->get_results(
            "SELECT id, name FROM " . CBD_TABLE_CLASSES . " WHERE status = 'active' ORDER BY name ASC"
        );

        wp_send_json_success($classes);
    }

    // =========================================================================
    // SHORTCODE: Student Access Page
    // =========================================================================

    /**
     * Render the [cbd_classroom] shortcode
     */
    public function render_classroom_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);

        // Enqueue frontend assets
        wp_enqueue_style(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
            array(),
            CBD_VERSION
        );

        wp_enqueue_script(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/js/classroom-frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        wp_localize_script('cbd-classroom-frontend', 'cbdClassroomFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'selectClass' => __('Klasse auswaehlen', 'container-block-designer'),
                'enterPassword' => __('Passwort eingeben', 'container-block-designer'),
                'login' => __('Einloggen', 'container-block-designer'),
                'logout' => __('Abmelden', 'container-block-designer'),
                'wrongPassword' => __('Falsches Passwort.', 'container-block-designer'),
                'sessionExpired' => __('Sitzung abgelaufen.', 'container-block-designer'),
                'loading' => __('Lade...', 'container-block-designer'),
                'treated' => __('Behandelt', 'container-block-designer'),
                'notTreated' => __('Nicht behandelt', 'container-block-designer')
            )
        ));

        ob_start();
        ?>
        <div id="cbd-classroom-app" class="cbd-classroom-container">
            <div class="cbd-classroom-auth" id="cbd-classroom-auth">
                <h2><?php _e('Klassen-Zugang', 'container-block-designer'); ?></h2>
                <div class="cbd-classroom-form">
                    <div class="cbd-classroom-field">
                        <label for="cbd-class-select"><?php _e('Klasse:', 'container-block-designer'); ?></label>
                        <select id="cbd-class-select">
                            <option value=""><?php _e('-- Klasse waehlen --', 'container-block-designer'); ?></option>
                        </select>
                    </div>
                    <div class="cbd-classroom-field">
                        <label for="cbd-class-password"><?php _e('Passwort:', 'container-block-designer'); ?></label>
                        <input type="password" id="cbd-class-password" placeholder="<?php esc_attr_e('Passwort eingeben', 'container-block-designer'); ?>">
                    </div>
                    <button type="button" id="cbd-class-login" class="button button-primary">
                        <?php _e('Einloggen', 'container-block-designer'); ?>
                    </button>
                    <div class="cbd-classroom-error" id="cbd-classroom-error" style="display:none;"></div>
                </div>
            </div>
            <div class="cbd-classroom-content" id="cbd-classroom-content" style="display:none;">
                <div class="cbd-classroom-header">
                    <span class="cbd-classroom-class-name" id="cbd-classroom-class-name"></span>
                    <button type="button" id="cbd-class-logout" class="button"><?php _e('Abmelden', 'container-block-designer'); ?></button>
                </div>
                <div class="cbd-classroom-pages" id="cbd-classroom-pages"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // HELPER: Get classes for current teacher (used by board-mode)
    // =========================================================================

    /**
     * Enqueue frontend assets for classroom shortcode
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Only enqueue if shortcode is present on the page
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbd_classroom')) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
            array(),
            CBD_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/js/classroom-frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(
            'cbd-classroom-frontend',
            'cbdClassroomFrontend',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pageId' => get_the_ID()
            )
        );
    }

    /**
     * Get classes for the current logged-in teacher
     * Used for board-mode class selector
     */
    public static function get_teacher_classes() {
        if (!is_user_logged_in() || !current_user_can('cbd_edit_blocks')) {
            return array();
        }

        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM " . CBD_TABLE_CLASSES . " WHERE teacher_id = %d AND status = 'active' ORDER BY name ASC",
            get_current_user_id()
        ));
    }
}

// Initialize singleton
CBD_Classroom::get_instance();
