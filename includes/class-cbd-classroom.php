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
        add_action('wp_ajax_cbd_set_behandelt', array($this, 'ajax_set_behandelt'));
        add_action('wp_ajax_cbd_get_block_status', array($this, 'ajax_get_block_status'));

        // AJAX handlers for students (no login required)
        add_action('wp_ajax_nopriv_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_nopriv_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_nopriv_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));
        add_action('wp_ajax_nopriv_cbd_get_page_classroom_data', array($this, 'ajax_get_page_classroom_data'));
        add_action('wp_ajax_nopriv_cbd_cleanup_invalid_containers', array($this, 'ajax_cleanup_invalid_containers'));
        // Also allow logged-in users to use student endpoints
        add_action('wp_ajax_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));
        add_action('wp_ajax_cbd_get_page_classroom_data', array($this, 'ajax_get_page_classroom_data'));
        add_action('wp_ajax_cbd_cleanup_invalid_containers', array($this, 'ajax_cleanup_invalid_containers'));

        // Debug endpoint
        add_action('wp_ajax_cbd_debug_page_status', array($this, 'ajax_debug_page_status'));
        add_action('wp_ajax_nopriv_cbd_debug_page_status', array($this, 'ajax_debug_page_status'));

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
        // Leerer Canvas wird als leerer String gesendet -> NULL in DB speichern
        $drawing_data = !empty($_POST['drawing_data']) ? $_POST['drawing_data'] : null;

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
        error_log('[CBD Classroom] toggle_behandelt - Parameters: class_id=' . $class_id . ', page_id=' . $page_id . ', container_id=' . $container_id);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        error_log('[CBD Classroom] Existing drawing: ' . ($existing ? 'YES (id=' . $existing->id . ', is_behandelt=' . $existing->is_behandelt . ')' : 'NO'));

        if ($existing) {
            $new_status = $existing->is_behandelt ? 0 : 1;
            error_log('[CBD Classroom] UPDATING existing drawing to status: ' . $new_status);

            $result = $wpdb->update($table, array(
                'is_behandelt' => $new_status,
                'updated_at' => current_time('mysql')
            ), array('id' => $existing->id));

            if ($result === false) {
                error_log('[CBD Classroom] UPDATE FAILED! Error: ' . $wpdb->last_error);
            } else {
                error_log('[CBD Classroom] UPDATE successful. Rows affected: ' . $result);
            }
        } else {
            $new_status = 1;
            error_log('[CBD Classroom] INSERTING new drawing with status: ' . $new_status);
            error_log('[CBD Classroom] Insert data: ' . print_r(array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ), true));

            $result = $wpdb->insert($table, array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ));

            if ($result === false) {
                error_log('[CBD Classroom] INSERT FAILED! Error: ' . $wpdb->last_error);
                error_log('[CBD Classroom] Last query: ' . $wpdb->last_query);
            } else {
                error_log('[CBD Classroom] INSERT successful. Insert ID: ' . $wpdb->insert_id);
            }
        }

        // Auto-assign page to class when first block is marked as behandelt
        if ($new_status == 1) {
            error_log('[CBD Classroom] toggle_behandelt - New status is 1, checking if page should be auto-added');
            $page_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d AND page_id = %d",
                $class_id, $page_id
            ));

            error_log('[CBD Classroom] Page exists in class_pages: ' . ($page_exists ? 'YES (ID: ' . $page_exists . ')' : 'NO'));

            if (!$page_exists) {
                // Get max sort_order for this class
                $max_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(sort_order) FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d",
                    $class_id
                ));

                $result = $wpdb->insert(CBD_TABLE_CLASS_PAGES, array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'sort_order' => ($max_order !== null) ? ($max_order + 1) : 0
                ));

                if ($result) {
                    error_log('[CBD Classroom] Successfully added page ' . $page_id . ' to class_pages for class ' . $class_id);
                } else {
                    error_log('[CBD Classroom] FAILED to add page to class_pages. Error: ' . $wpdb->last_error);
                }
            }
        }

        // Verify the drawing was created/updated
        $verify = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM " . CBD_TABLE_DRAWINGS . " WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        wp_send_json_success(array(
            'is_behandelt' => (bool) $new_status,
            'message' => $new_status ? 'Als behandelt markiert.' : 'Markierung entfernt.',
            'debug' => array(
                'drawing_id' => $verify ? $verify->id : null,
                'drawing_status' => $verify ? (bool) $verify->is_behandelt : null,
                'db_insert_result' => isset($result) ? $result : null,
                'db_last_error' => $wpdb->last_error ? $wpdb->last_error : null,
                'insert_id' => $wpdb->insert_id > 0 ? $wpdb->insert_id : null
            )
        ));
    }

    /**
     * AJAX: Set "behandelt" status to 1 (only sets, never clears)
     * Called automatically when a teacher draws for a class.
     */
    public function ajax_set_behandelt() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id    = intval($_POST['class_id'] ?? 0);
        $page_id     = intval($_POST['page_id'] ?? 0);
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

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        if ($existing) {
            if (!$existing->is_behandelt) {
                $wpdb->update($table, array(
                    'is_behandelt' => 1,
                    'updated_at'   => current_time('mysql')
                ), array('id' => $existing->id));
            }
        } else {
            $wpdb->insert($table, array(
                'class_id'     => $class_id,
                'teacher_id'   => get_current_user_id(),
                'page_id'      => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ));
        }

        // Auto-assign page to class if not already assigned
        $page_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        if (!$page_exists) {
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d",
                $class_id
            ));
            $wpdb->insert(CBD_TABLE_CLASS_PAGES, array(
                'class_id'   => $class_id,
                'page_id'    => $page_id,
                'sort_order' => ($max_order !== null) ? ($max_order + 1) : 0
            ));
        }

        wp_send_json_success(array('message' => 'Als behandelt markiert.'));
    }

    /**
     * AJAX: Get behandelt status for a block across all teacher's classes
     */
    public function ajax_get_block_status() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Get all classes for this teacher
        $classes = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM " . CBD_TABLE_CLASSES . "
             WHERE teacher_id = %d AND status = 'active'
             ORDER BY name ASC",
            get_current_user_id()
        ));

        $status_data = array();

        foreach ($classes as $class) {
            // Check if this block is marked as behandelt for this class
            $is_behandelt = $wpdb->get_var($wpdb->prepare(
                "SELECT is_behandelt FROM " . CBD_TABLE_DRAWINGS . "
                 WHERE class_id = %d AND page_id = %d AND container_id = %s",
                $class->id, $page_id, $container_id
            ));

            $status_data[] = array(
                'id' => $class->id,
                'name' => $class->name,
                'is_behandelt' => (bool) $is_behandelt
            );
        }

        wp_send_json_success(array('classes' => $status_data));
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
        $token_key = 'cbd_classroom_' . $token;

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

        $token_key = 'cbd_classroom_' . $token;
        $session = get_transient($token_key);

        if (!$session) {
            wp_send_json_error(array('message' => 'Sitzung abgelaufen. Bitte erneut einloggen.'));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // NEW APPROACH: Return page list instead of rendering full content
        // Pages will be loaded individually when user clicks on them

        // STEP 1: Get ALL pages assigned to this class
        error_log('[CBD Classroom] ajax_student_get_data - Class ID: ' . $class_id);

        $all_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT cp.page_id, p.post_title, p.post_parent
             FROM " . CBD_TABLE_CLASS_PAGES . " cp
             INNER JOIN {$wpdb->posts} p ON cp.page_id = p.ID
             WHERE cp.class_id = %d AND p.post_status = 'publish'
             ORDER BY cp.sort_order ASC",
            $class_id
        ));

        error_log('[CBD Classroom] Found ' . count($all_pages) . ' total pages in class');

        // STEP 2: Get list of page IDs that have behandelt blocks
        $treated_page_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT page_id FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND is_behandelt = 1",
            $class_id
        ));

        error_log('[CBD Classroom] Found ' . count($treated_page_ids) . ' pages with behandelt blocks');

        // STEP 3: Build hierarchy and determine which pages to show
        $pages_to_show = array();
        $parent_ids_to_show = array(); // Parents of treated pages

        foreach ($all_pages as $page) {
            $is_treated = in_array($page->page_id, $treated_page_ids);

            if ($is_treated) {
                // This page has behandelt blocks - show it with link
                $pages_to_show[$page->page_id] = array(
                    'page_id' => $page->page_id,
                    'title' => $page->post_title,
                    'parent_id' => (int) $page->post_parent,
                    'is_treated' => true,
                    'treated_count' => $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM " . CBD_TABLE_DRAWINGS . "
                         WHERE class_id = %d AND page_id = %d AND is_behandelt = 1",
                        $class_id, $page->page_id
                    )),
                    'url' => add_query_arg(array(
                        'classroom' => $class_id,
                        'token' => $token
                    ), get_permalink($page->page_id))
                );

                // Mark parent chain to show (grayed out)
                $current_parent_id = $page->post_parent;
                while ($current_parent_id > 0) {
                    $parent_ids_to_show[$current_parent_id] = true;
                    $parent_post = get_post($current_parent_id);
                    $current_parent_id = $parent_post ? $parent_post->post_parent : 0;
                }
            }
        }

        // Add parent pages (grayed out, no link)
        foreach ($parent_ids_to_show as $parent_id => $value) {
            if (!isset($pages_to_show[$parent_id])) {
                $parent_post = get_post($parent_id);
                if ($parent_post) {
                    $pages_to_show[$parent_id] = array(
                        'page_id' => $parent_id,
                        'title' => $parent_post->post_title,
                        'parent_id' => (int) $parent_post->post_parent,
                        'is_treated' => false,
                        'treated_count' => 0,
                        'url' => null, // No URL = grayed out
                        'is_parent_only' => true
                    );
                }
            }
        }

        // STEP 4: Build hierarchical structure with unlimited depth
        // First, organize pages by parent_id for quick lookup
        $children_by_parent = array();
        foreach ($pages_to_show as $page_data) {
            $parent_id = $page_data['parent_id'];
            if (!isset($children_by_parent[$parent_id])) {
                $children_by_parent[$parent_id] = array();
            }
            $children_by_parent[$parent_id][] = $page_data;
        }

        // Recursive function to build tree
        $build_tree = function($parent_id, $level = 0) use (&$build_tree, $children_by_parent, $pages_to_show) {
            $result = array();

            if (!isset($children_by_parent[$parent_id])) {
                return $result;
            }

            foreach ($children_by_parent[$parent_id] as $page) {
                // Add current page with level info
                $page['level'] = $level;
                $result[] = $page;

                // Recursively add children
                $children = $build_tree($page['page_id'], $level + 1);
                $result = array_merge($result, $children);
            }

            return $result;
        };

        // Build flat list starting from top-level pages (parent_id = 0)
        $flat_pages = $build_tree(0, 0);

        error_log('[CBD Classroom] Built flat list with ' . count($flat_pages) . ' pages');

        // Convert flat list to format expected by frontend
        $grouped_pages = array();
        foreach ($flat_pages as $page) {
            $grouped_pages[] = array(
                'type' => 'page',
                'page' => $page
            );
        }

        wp_send_json_success(array(
            'class_name' => $session['class_name'],
            'pages' => $grouped_pages
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

        // Check if we're in classroom mode via URL parameter
        $classroom_id = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
        $is_classroom_page = $classroom_id > 0 && is_singular();

        // Check if shortcode is present
        $has_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'cbd_classroom');

        // If neither shortcode nor classroom parameter, do nothing
        if (!$has_shortcode && !$is_classroom_page) {
            return;
        }

        // If this is a normal page in classroom filter mode (not the shortcode page)
        if ($is_classroom_page && !$has_shortcode) {
            // Enqueue ONLY the classroom page filter script
            wp_enqueue_script(
                'cbd-classroom-page-filter',
                CBD_PLUGIN_URL . 'assets/js/classroom-page-filter.js',
                array('jquery'),
                CBD_VERSION,
                true
            );

            // Localize with page data
            wp_localize_script(
                'cbd-classroom-page-filter',
                'cbdClassroomPageData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'pageId' => get_the_ID()
                )
            );

            // Enqueue classroom CSS for badges and overlays
            wp_enqueue_style(
                'cbd-classroom-frontend',
                CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
                array(),
                CBD_VERSION
            );

            return; // Don't load all the other assets
        }

        // If we get here, we have the shortcode - enqueue all assets

        // ========================================================================
        // CORE CBD SCRIPTS (needed for container block features)
        // ========================================================================

        // Interactivity API Store (ESM Module) - WordPress 6.5+
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                'cbd-interactivity-store',
                CBD_PLUGIN_URL . 'assets/js/interactivity-store.js',
                array('@wordpress/interactivity'),
                CBD_VERSION
            );
            wp_enqueue_script_module('cbd-interactivity-store');
        }

        // jQuery-based fallback (ALWAYS enqueue for reliability)
        wp_enqueue_script(
            'cbd-interactivity-fallback',
            CBD_PLUGIN_URL . 'assets/js/interactivity-fallback.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        // Frontend CSS
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION
        );

        wp_enqueue_style(
            'cbd-interactivity-api',
            CBD_PLUGIN_URL . 'assets/css/interactivity-api.css',
            array('cbd-frontend-clean'),
            CBD_VERSION
        );

        // Dashicons for frontend icons
        wp_enqueue_style('dashicons');

        // Icon Libraries (Font Awesome, Material Icons, Lucide)
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            array(),
            '6.5.1'
        );

        wp_enqueue_style(
            'material-icons',
            'https://fonts.googleapis.com/icon?family=Material+Icons',
            array(),
            null
        );

        wp_enqueue_style(
            'lucide-icons',
            'https://unpkg.com/lucide-static@latest/font/lucide.css',
            array(),
            null
        );

        // html2canvas for screenshot functionality
        wp_enqueue_script(
            'html2canvas',
            CBD_PLUGIN_URL . 'assets/lib/html2canvas.min.js',
            array(),
            '1.4.1',
            true
        );

        // jsPDF library
        wp_enqueue_script(
            'jspdf',
            'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );

        // html2pdf.js loader
        wp_enqueue_script(
            'cbd-html2pdf-loader',
            CBD_PLUGIN_URL . 'assets/js/html2pdf-loader.js',
            array('html2canvas', 'jspdf'),
            CBD_VERSION,
            true
        );

        // PDF server-side generation
        wp_enqueue_script(
            'cbd-pdf-server-side',
            CBD_PLUGIN_URL . 'assets/js/pdf-server-side.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        wp_localize_script(
            'cbd-pdf-server-side',
            'cbdPDFData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-pdf-nonce')
            )
        );

        // Floating PDF Export Button
        wp_enqueue_script(
            'cbd-floating-pdf-button',
            CBD_PLUGIN_URL . 'assets/js/floating-pdf-button.js',
            array('jquery', 'cbd-html2pdf-loader'),
            CBD_VERSION,
            true
        );

        // ========================================================================
        // CLASSROOM-SPECIFIC SCRIPTS
        // ========================================================================

        // Classroom frontend CSS
        wp_enqueue_style(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
            array(),
            CBD_VERSION
        );

        // Classroom frontend JS
        wp_enqueue_script(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/js/classroom-frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        // Localize classroom script with data
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

    // =========================================================================
    // NEW: AJAX endpoint for individual page classroom data
    // =========================================================================

    /**
     * AJAX: Get classroom data for a specific page
     * Used when loading normal WordPress pages with ?classroom parameter
     */
    public function ajax_get_page_classroom_data() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $page_id = intval($_POST['page_id'] ?? 0);

        if (empty($token) || $page_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Verify token
        $transient_key = 'cbd_classroom_' . $token;
        $session = get_transient($transient_key);

        // Debug logging
        error_log('[CBD Classroom] ajax_get_page_classroom_data called');
        error_log('[CBD Classroom] Token: ' . substr($token, 0, 20) . '...');
        error_log('[CBD Classroom] Transient key: ' . $transient_key);
        error_log('[CBD Classroom] Session found: ' . ($session ? 'YES' : 'NO'));
        if ($session) {
            error_log('[CBD Classroom] Session data: ' . print_r($session, true));
        }

        if (!$session || !isset($session['class_id'])) {
            wp_send_json_error(array(
                'message' => 'Ungültiger oder abgelaufener Token. Bitte loggen Sie sich erneut ein.',
                'debug' => array(
                    'token_length' => strlen($token),
                    'session_exists' => $session ? true : false
                )
            ));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // Get class name
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM " . CBD_TABLE_CLASSES . " WHERE id = %d",
            $class_id
        ));

        // Get drawings and behandelt status for this page
        error_log('[CBD Classroom] ajax_get_page_classroom_data - Querying page_id: ' . $page_id . ', class_id: ' . $class_id);

        $drawings = $wpdb->get_results($wpdb->prepare(
            "SELECT container_id, drawing_data, is_behandelt
             FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        error_log('[CBD Classroom] ajax_get_page_classroom_data - Found ' . count($drawings) . ' drawings');
        if (count($drawings) > 0) {
            foreach ($drawings as $d) {
                error_log('[CBD Classroom] Drawing: container_id=' . $d->container_id . ', is_behandelt=' . $d->is_behandelt);
            }
        }

        // Organize drawings by container_id
        $drawings_map = array();
        $treated_containers = array();

        foreach ($drawings as $drawing) {
            $drawings_map[$drawing->container_id] = array(
                'drawing_data' => $drawing->drawing_data,
                'is_behandelt' => (bool) $drawing->is_behandelt
            );

            if ($drawing->is_behandelt) {
                $treated_containers[] = $drawing->container_id;
            }
        }

        error_log('[CBD Classroom] ajax_get_page_classroom_data - Treated containers: ' . implode(', ', $treated_containers));

        wp_send_json_success(array(
            'class_name' => $class ? $class->name : '',
            'treated_containers' => $treated_containers,
            'drawings' => $drawings_map
        ));
    }

    /**
     * AJAX: Cleanup invalid container references (containers that no longer exist on page)
     */
    public function ajax_cleanup_invalid_containers() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $page_id = intval($_POST['page_id'] ?? 0);
        $invalid_containers = isset($_POST['invalid_containers']) ? (array) $_POST['invalid_containers'] : array();

        if (empty($token) || $page_id <= 0 || empty($invalid_containers)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // This endpoint should ONLY be accessible to teachers, not students
        // Require proper WordPress capabilities
        if (!current_user_can('cbd_edit_blocks') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung für diese Aktion.'));
        }

        // Verify token (for context, but not for authorization)
        $transient_key = 'cbd_classroom_' . $token;
        $session = get_transient($transient_key);

        if (!$session || !isset($session['class_id'])) {
            wp_send_json_error(array('message' => 'Ungültiger oder abgelaufener Token.'));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // Sanitize container IDs
        $invalid_containers = array_map('sanitize_text_field', $invalid_containers);

        error_log('[CBD Classroom] ajax_cleanup_invalid_containers - Removing ' . count($invalid_containers) . ' invalid containers from page ' . $page_id);

        // Delete invalid container references
        $deleted_count = 0;
        foreach ($invalid_containers as $container_id) {
            $result = $wpdb->delete(
                CBD_TABLE_DRAWINGS,
                array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'container_id' => $container_id
                ),
                array('%d', '%d', '%s')
            );

            if ($result !== false) {
                $deleted_count += $result;
                error_log('[CBD Classroom] Deleted drawing: class_id=' . $class_id . ', page_id=' . $page_id . ', container_id=' . $container_id);
            }
        }

        error_log('[CBD Classroom] Cleanup complete - Deleted ' . $deleted_count . ' entries');

        // Check if any treated containers remain for this page
        $remaining_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d AND is_behandelt = 1",
            $class_id, $page_id
        ));

        error_log('[CBD Classroom] Remaining treated containers: ' . $remaining_count);

        wp_send_json_success(array(
            'deleted_count' => $deleted_count,
            'remaining_count' => intval($remaining_count),
            'message' => $deleted_count . ' veraltete Container-Referenz(en) entfernt.'
        ));
    }

    /**
     * AJAX: Debug endpoint to check database status for a page
     */
    public function ajax_debug_page_status() {
        $page_id = intval($_POST['page_id'] ?? 0);
        $class_id = intval($_POST['class_id'] ?? 0);

        if ($page_id <= 0 || $class_id <= 0) {
            wp_send_json_error(array('message' => 'page_id and class_id required'));
        }

        global $wpdb;

        // Check class_pages
        $in_class_pages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        // Check drawings
        $drawings = $wpdb->get_results($wpdb->prepare(
            "SELECT container_id, is_behandelt FROM " . CBD_TABLE_DRAWINGS . " WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        // Check if page is published
        $page_status = get_post_status($page_id);

        wp_send_json_success(array(
            'page_id' => $page_id,
            'class_id' => $class_id,
            'in_class_pages' => (bool) $in_class_pages,
            'page_status' => $page_status,
            'drawings_count' => count($drawings),
            'drawings' => $drawings
        ));
    }
}

// Initialize singleton
CBD_Classroom::get_instance();
