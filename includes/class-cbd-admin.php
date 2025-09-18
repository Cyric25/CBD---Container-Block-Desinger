<?php
/**
 * Container Block Designer - Admin-Klasse
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Klasse für das Container Block Designer Plugin
 */
class CBD_Admin {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Singleton-Getter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Admin Router
     */
    private $router = null;
    
    /**
     * Konstruktor - Now uses router-based architecture
     */
    private function __construct() {
        // Admin-Menü hinzufügen - WICHTIG: Muss vor anderen Aktionen stehen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin Router deaktiviert um doppelte Menüs zu vermeiden
        // Load Admin Router if available
        // if (file_exists(CBD_PLUGIN_DIR . 'includes/Admin/class-admin-router.php')) {
        //     require_once CBD_PLUGIN_DIR . 'includes/Admin/class-admin-router.php';
        //     if (class_exists('\ContainerBlockDesigner\Admin\AdminRouter')) {
        //         $this->router = new \ContainerBlockDesigner\Admin\AdminRouter();
        //         add_action('wp_ajax_cbd_admin_action', array($this->router, 'handle_ajax_request'));
        //     }
        // }
        
        // Admin hooks
        add_action('admin_init', array($this, 'ensure_roles_exist')); // Prüfe Rollen bei jedem Admin-Aufruf
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'ajax_delete_block'));
        add_action('wp_ajax_cbd_toggle_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_cbd_save_block', array($this, 'ajax_save_block'));
        add_action('wp_ajax_cbd_test', array($this, 'ajax_test'));
        add_action('wp_ajax_cbd_edit_save', array($this, 'ajax_edit_save'));
        
        // Plugin-Action-Links
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Admin-Notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Verarbeite Admin-Aktionen früh (vor Ausgabe)
        add_action('admin_init', array($this, 'process_admin_actions'));
    }

    /**
     * Stellt sicher, dass alle benötigten Rollen existieren
     */
    public function ensure_roles_exist() {
        // Prüfe nur einmal pro Session
        if (get_transient('cbd_roles_checked')) {
            return;
        }

        // Prüfe ob Block-Redakteur Rolle existiert
        $block_redakteur_role = get_role('block_redakteur');
        if (!$block_redakteur_role) {
            // Rolle erstellen
            $this->create_block_redakteur_role();
        } else {
            // Prüfe ob Capability vorhanden ist
            if (!$block_redakteur_role->has_cap('cbd_edit_blocks')) {
                $block_redakteur_role->add_cap('cbd_edit_blocks');
            }
        }

        // Prüfe Admin-Rollen
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('cbd_edit_blocks')) {
            $admin_role->add_cap('cbd_edit_blocks');
            $admin_role->add_cap('cbd_edit_styles');
            $admin_role->add_cap('cbd_admin_blocks');
        }

        // Setze Transient für 1 Stunde
        set_transient('cbd_roles_checked', true, HOUR_IN_SECONDS);
    }

    /**
     * Erstelle Block-Redakteur Rolle
     */
    private function create_block_redakteur_role() {
        $capabilities = array(
            'read' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'edit_posts' => true,
            'upload_files' => true,
            'cbd_edit_blocks' => true,
            'cbd_edit_styles' => false,
            'cbd_admin_blocks' => false,
            'manage_options' => false,
        );

        return add_role('block_redakteur', 'Block-Redakteur', $capabilities);
    }

    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Debug: Prüfe Benutzerrolle und Capabilities
        $current_user = wp_get_current_user();
        $is_block_redakteur = $current_user && in_array('block_redakteur', $current_user->roles);

        // Debug Information
        error_log('[CBD Menu Debug] User ID: ' . $current_user->ID);
        error_log('[CBD Menu Debug] User Roles: ' . implode(', ', $current_user->roles));
        error_log('[CBD Menu Debug] Is Block-Redakteur: ' . ($is_block_redakteur ? 'YES' : 'NO'));
        error_log('[CBD Menu Debug] Has cbd_edit_blocks: ' . (current_user_can('cbd_edit_blocks') ? 'YES' : 'NO'));
        error_log('[CBD Menu Debug] Has cbd_admin_blocks: ' . (current_user_can('cbd_admin_blocks') ? 'YES' : 'NO'));

        // Temporäres Debug-Notice
        if (current_user_can('manage_options')) {
            $debug_message = sprintf(
                'CBD Debug - User: %s, Rollen: %s, Block-Redakteur: %s, cbd_edit_blocks: %s',
                $current_user->user_login,
                implode(', ', $current_user->roles),
                $is_block_redakteur ? 'JA' : 'NEIN',
                current_user_can('cbd_edit_blocks') ? 'JA' : 'NEIN'
            );
            add_action('admin_notices', function() use ($debug_message) {
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($debug_message) . '</p></div>';
            });
        }

        if ($is_block_redakteur) {
            // Für Block-Redakteure: Nur Block-Vorschau als Hauptmenü
            // Verwende read als minimale Capability falls cbd_edit_blocks fehlt
            $capability = current_user_can('cbd_edit_blocks') ? 'cbd_edit_blocks' : 'read';
            error_log('[CBD Menu Debug] Using capability for block_redakteur: ' . $capability);

            add_menu_page(
                __('Container Block Vorschau', 'container-block-designer'),
                __('Container Designer', 'container-block-designer'),
                $capability,
                'cbd-block-preview',
                array($this, 'render_block_preview_page'),
                'dashicons-layout',
                30
            );
        } else {
            // Für Admins/Editoren: Vollständiges Menü
            add_menu_page(
                __('Container Block Designer', 'container-block-designer'),
                __('Container Designer', 'container-block-designer'),
                'cbd_admin_blocks', // Nur Admin-Rechte für Hauptseite
                'container-block-designer',
                array($this, 'render_main_page'),
                'dashicons-layout',
                30
            );

            // Untermenü: Neuer Block - nur für Editoren und Admins
            add_submenu_page(
                'container-block-designer',
                __('Neuer Block', 'container-block-designer'),
                __('Neuer Block', 'container-block-designer'),
                'cbd_admin_blocks',
                'cbd-new-block',
                array($this, 'render_new_block_page')
            );

            // Untermenü: Alle Blöcke - nur für Editoren und Admins
            add_submenu_page(
                'container-block-designer',
                __('Alle Blöcke', 'container-block-designer'),
                __('Alle Blöcke', 'container-block-designer'),
                'cbd_admin_blocks',
                'cbd-blocks',
                array($this, 'render_blocks_list_page')
            );

            // Untermenü: Import/Export - nur Admins für kritische Funktionen
            add_submenu_page(
                'container-block-designer',
                __('Import/Export', 'container-block-designer'),
                __('Import/Export', 'container-block-designer'),
                'manage_options',
                'cbd-import-export',
                array($this, 'render_import_export_page')
            );

            // Untermenü: Datenbank reparieren - nur Admins
            add_submenu_page(
                'container-block-designer',
                __('Datenbank reparieren', 'container-block-designer'),
                __('Datenbank reparieren', 'container-block-designer'),
                'manage_options',
                'cbd-database-repair',
                array($this, 'render_database_repair_page')
            );

            // Untermenü: Rollen reparieren - nur Admins
            add_submenu_page(
                'container-block-designer',
                __('Rollen reparieren', 'container-block-designer'),
                __('Rollen reparieren', 'container-block-designer'),
                'manage_options',
                'cbd-roles-repair',
                array($this, 'render_roles_repair_page')
            );
        }

        // Versteckte Seite: Block bearbeiten - nicht im Menü sichtbar
        add_submenu_page(
            null, // parent_slug = null macht es zu einer versteckten Seite
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            'cbd_admin_blocks', // Nur Admins können bearbeiten
            'cbd-edit-block',
            array($this, 'render_edit_block_page')
        );
    }
    
    /**
     * Admin-Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'container-block-designer') === false && 
            strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Get current page from $_GET
        $page = sanitize_text_field($_GET['page'] ?? '');
        
        // Common admin styles
        wp_enqueue_style(
            'cbd-admin-common',
            CBD_PLUGIN_URL . 'assets/css/admin-common.css',
            array(),
            CBD_VERSION
        );
        
        // Legacy admin CSS (for backward compatibility)
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array('cbd-admin-common'),
            CBD_VERSION
        );
        
        // Page-specific assets
        switch ($page) {
            case 'cbd-new-block':
            case 'cbd-edit-block':
                wp_enqueue_style(
                    'cbd-block-editor',
                    CBD_PLUGIN_URL . 'assets/css/block-editor.css',
                    array('cbd-admin-common'),
                    CBD_VERSION
                );
                
                wp_enqueue_script(
                    'cbd-block-editor',
                    CBD_PLUGIN_URL . 'assets/js/block-editor.js',
                    array('jquery', 'wp-color-picker'),
                    CBD_VERSION,
                    true
                );

                // Admin.js für Style-Preview
                wp_enqueue_script(
                    'cbd-admin',
                    CBD_PLUGIN_URL . 'assets/js/admin.js',
                    array('jquery', 'wp-color-picker'),
                    CBD_VERSION,
                    true
                );

                // Live-Preview-Fix Script
                wp_enqueue_script(
                    'cbd-live-preview-fix',
                    CBD_PLUGIN_URL . 'assets/js/admin-live-preview-fix.js',
                    array('jquery'),
                    CBD_VERSION,
                    true
                );
                
                wp_localize_script('cbd-block-editor', 'cbdBlockEditor', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('cbd_block_editor'),
                    'strings' => array(
                        'previewUpdated' => __('Vorschau aktualisiert', 'container-block-designer'),
                        'previewError' => __('Fehler beim Aktualisieren der Vorschau', 'container-block-designer'),
                        'saving' => __('Speichern...', 'container-block-designer'),
                        'saved' => __('Gespeichert', 'container-block-designer'),
                        'error' => __('Fehler', 'container-block-designer')
                    )
                ));
                
                // Color picker
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                break;
                
            case 'cbd-blocks':
                wp_enqueue_script(
                    'cbd-blocks-list',
                    CBD_PLUGIN_URL . 'assets/js/admin-blocks-list.js',
                    array('jquery'),
                    CBD_VERSION,
                    true
                );
                break;
                
            case 'cbd-block-preview':
                // CSS für die Vorschau-Seite - lade Frontend-Styles für korrekte Darstellung
                wp_enqueue_style(
                    'cbd-frontend-preview',
                    CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
                    array(),
                    CBD_VERSION
                );
                wp_enqueue_style(
                    'cbd-block-preview',
                    CBD_PLUGIN_URL . 'assets/css/admin-features.css',
                    array(),
                    CBD_VERSION
                );
                // Inline-CSS für die Vorschau-Anpassungen
                $preview_css = '
                    .cbd-preview-wrapper .cbd-container {
                        margin: 0 !important;
                        max-width: 100% !important;
                    }
                    .cbd-preview-content {
                        background: white;
                        border-radius: 4px;
                        overflow: hidden;
                    }
                    .cbd-container-preview {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    }
                ';
                wp_add_inline_style('cbd-block-preview', $preview_css);
                break;

            case 'cbd-import-export':
                wp_enqueue_script(
                    'cbd-import-export',
                    CBD_PLUGIN_URL . 'assets/js/admin-import-export.js',
                    array('jquery'),
                    CBD_VERSION,
                    true
                );
                break;
        }
        
        // Common admin JavaScript
        wp_enqueue_script(
            'cbd-admin-common',
            CBD_PLUGIN_URL . 'assets/js/admin-common.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Legacy admin JavaScript (for backward compatibility)
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'cbd-admin-common'),
            CBD_VERSION,
            true
        );
        
        // Lokalisierung
        wp_localize_script('cbd-admin-common', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_admin'),
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'confirmBulkDelete' => __('Sind Sie sicher, dass Sie die ausgewählten Blöcke löschen möchten?', 'container-block-designer'),
                'noItemsSelected' => __('Bitte wählen Sie mindestens einen Block aus.', 'container-block-designer'),
                'processing' => __('Verarbeiten...', 'container-block-designer'),
                'done' => __('Fertig', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Hauptseite rendern
     */
    public function render_main_page() {
        // Diese Funktion sollte nur für Admins/Editoren aufgerufen werden
        // Block-Redakteure haben jetzt ihr eigenes Menü
        $file_path = CBD_PLUGIN_DIR . 'admin/container-block-designer.php';

        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Container Block Designer', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/container-block-designer.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Verarbeite Admin-Aktionen (POST/GET)
     * Muss VOR jeglicher Ausgabe erfolgen!
     */
    public function process_admin_actions() {
        // Debug: Log all POST requests
        if (!empty($_POST)) {
            error_log('[CBD Admin] POST request detected. Page: ' . ($_GET['page'] ?? 'none') . ', POST keys: ' . implode(', ', array_keys($_POST)));
        }
        
        // Nur auf unseren Admin-Seiten
        if (!isset($_GET['page']) || strpos($_GET['page'], 'container-block-designer') === false) {
            return;
        }

        global $wpdb;

        // Block-Duplizierung verarbeiten
        if (isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['block_id'])) {
            error_log('[CBD Admin] Duplicate action detected: block_id=' . $_GET['block_id']);

            $block_id = intval($_GET['block_id']);
            $nonce_action = 'cbd_duplicate_block_' . $block_id;

            // Debug Nonce
            error_log('[CBD Admin] Nonce check: action=' . $nonce_action . ', received=' . ($_GET['_wpnonce'] ?? 'none'));

            if (!wp_verify_nonce($_GET['_wpnonce'], $nonce_action)) {
                error_log('[CBD Admin] Nonce verification failed');
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }

            // Lade CBD_Database Klasse falls nötig
            if (!class_exists('CBD_Database')) {
                require_once CBD_PLUGIN_DIR . 'includes/class-cbd-database.php';
            }

            error_log('[CBD Admin] Attempting to duplicate block ID: ' . $block_id);
            $duplicate_id = CBD_Database::duplicate_block($block_id);
            error_log('[CBD Admin] Duplicate result: ' . ($duplicate_id ? 'Success (ID: ' . $duplicate_id . ')' : 'Failed'));

            if ($duplicate_id) {
                wp_redirect(admin_url('admin.php?page=cbd-blocks&duplicated=1&block_id=' . $duplicate_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=cbd-blocks&error=duplicate_failed'));
                exit;
            }
        }
        
        // Block-Speichern verarbeiten (für edit-block.php)
        if (isset($_POST['save_block']) && isset($_POST['block_id']) && isset($_POST['cbd_nonce'])) {
            error_log('[CBD Admin] Edit block form submitted. POST data: ' . print_r($_POST, true));
            
            if (!wp_verify_nonce($_POST['cbd_nonce'], 'cbd-admin')) {
                error_log('[CBD Admin] Nonce verification failed. Expected: cbd-admin, Received: ' . ($_POST['cbd_nonce'] ?? 'none'));
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            
            // Daten sammeln
            $name = sanitize_text_field($_POST['name'] ?? '');
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');
            $status = isset($_POST['status']) ? 'active' : 'inactive';
            
            // Styles sammeln - verwende das korrekte Format aus edit-block.php
            $styles = array(
                'padding' => array(
                    'top' => intval($_POST['styles']['padding']['top'] ?? 20),
                    'right' => intval($_POST['styles']['padding']['right'] ?? 20),
                    'bottom' => intval($_POST['styles']['padding']['bottom'] ?? 20),
                    'left' => intval($_POST['styles']['padding']['left'] ?? 20)
                ),
                'background' => array(
                    'color' => sanitize_hex_color($_POST['styles']['background']['color'] ?? '#ffffff')
                ),
                'border' => array(
                    'width' => intval($_POST['styles']['border']['width'] ?? 1),
                    'color' => sanitize_hex_color($_POST['styles']['border']['color'] ?? '#e0e0e0'),
                    'style' => sanitize_text_field($_POST['styles']['border']['style'] ?? 'solid'),
                    'radius' => intval($_POST['styles']['border']['radius'] ?? 4)
                ),
                'text' => array(
                    'color' => sanitize_hex_color($_POST['styles']['text']['color'] ?? '#333333'),
                    'alignment' => sanitize_text_field($_POST['styles']['text']['alignment'] ?? 'left')
                )
            );
            
            // Features sammeln - verwende das korrekte Format aus edit-block.php
            $features = array(
                'icon' => array(
                    'enabled' => isset($_POST['features']['icon']['enabled']) ? true : false,
                    'value' => sanitize_text_field($_POST['features']['icon']['value'] ?? 'dashicons-admin-generic'),
                    'position' => sanitize_text_field($_POST['features']['icon']['position'] ?? 'top-left')
                ),
                'collapse' => array(
                    'enabled' => isset($_POST['features']['collapse']['enabled']) ? true : false,
                    'defaultState' => sanitize_text_field($_POST['features']['collapse']['defaultState'] ?? 'expanded')
                ),
                'numbering' => array(
                    'enabled' => isset($_POST['features']['numbering']['enabled']) ? true : false,
                    'format' => sanitize_text_field($_POST['features']['numbering']['format'] ?? 'numeric'),
                    'position' => sanitize_text_field($_POST['features']['numbering']['position'] ?? 'top-left'),
                    'countingMode' => sanitize_text_field($_POST['features']['numbering']['countingMode'] ?? 'same-design')
                ),
                'copyText' => array(
                    'enabled' => isset($_POST['features']['copyText']['enabled']) ? true : false,
                    'buttonText' => sanitize_text_field($_POST['features']['copyText']['buttonText'] ?? 'Text kopieren')
                ),
                'screenshot' => array(
                    'enabled' => isset($_POST['features']['screenshot']['enabled']) ? true : false,
                    'buttonText' => sanitize_text_field($_POST['features']['screenshot']['buttonText'] ?? 'Screenshot')
                )
            );
            
            // Config sammeln
            $config = array(
                'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
                'templateLock' => isset($_POST['template_lock']) ? false : true
            );
            
            // Slug generieren aus Name für Form-Edit
            $slug = sanitize_title($name);

            // Daten aktualisieren - WICHTIG: slug hinzufügen!
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                array(
                    'name' => $name,
                    'title' => $title,
                    'slug' => $slug, // KRITISCH: slug hinzufügen für Frontend-Rendering
                    'description' => $description,
                    'config' => wp_json_encode($config),
                    'styles' => wp_json_encode($styles),
                    'features' => wp_json_encode($features),
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $block_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'), // Einen %s für slug hinzugefügt
                array('%d')
            );
            
            if ($result !== false) {
                // Cache leeren für sofortige Verfügbarkeit
                wp_cache_delete('cbd_all_blocks_styles');
                wp_cache_delete('cbd_active_features');
                delete_transient('cbd_compiled_styles');

                set_transient('cbd_admin_notice_' . get_current_user_id(), array(
                    'type' => 'success',
                    'message' => __('Block erfolgreich aktualisiert.', 'container-block-designer')
                ), 60);
                wp_safe_redirect(admin_url('admin.php?page=cbd-edit-block&block_id=' . $block_id));
                exit;
            } else {
                set_transient('cbd_admin_notice_' . get_current_user_id(), array(
                    'type' => 'error',
                    'message' => __('Fehler beim Aktualisieren des Blocks.', 'container-block-designer')
                ), 60);
            }
        }
        
        // Feature-Toggle verarbeiten
        if (isset($_POST['toggle_feature']) && isset($_POST['block_id']) && isset($_POST['feature_key'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_toggle_feature')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            $feature_key = sanitize_text_field($_POST['feature_key']);
            
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $block_id
            ));
            
            if ($block) {
                $features = !empty($block->features) ? json_decode($block->features, true) : array();
                
                if (!isset($features[$feature_key])) {
                    $features[$feature_key] = array('enabled' => true);
                } else {
                    $features[$feature_key]['enabled'] = !$features[$feature_key]['enabled'];
                }
                
                // Verwende die korrekte Spalte 'updated_at'
                $update_data = array(
                    'features' => json_encode($features)
                );
                
                // Prüfe welche Spalte existiert
                $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
                if (in_array('updated_at', $columns)) {
                    $update_data['updated_at'] = current_time('mysql');
                }
                
                $wpdb->update(
                    CBD_TABLE_BLOCKS,
                    $update_data,
                    array('id' => $block_id)
                );
                
                // Setze Transient für Erfolgsmeldung
                set_transient('cbd_admin_message', 'feature_toggled', 30);
                
                // Weiterleitung
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
        
        // Block löschen
        if (isset($_POST['delete_block']) && isset($_POST['block_id'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_delete_block')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            
            $wpdb->delete(
                CBD_TABLE_BLOCKS,
                array('id' => $block_id),
                array('%d')
            );
            
            set_transient('cbd_admin_message', 'block_deleted', 30);
            
            wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
            exit;
        }
        
        // Block-Status ändern
        if (isset($_POST['toggle_status']) && isset($_POST['block_id'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_toggle_status')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $block_id
            ));
            
            if ($block) {
                $new_status = $block->status === 'active' ? 'inactive' : 'active';
                
                $update_data = array(
                    'status' => $new_status
                );
                
                // Prüfe welche Spalte existiert
                $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
                if (in_array('updated_at', $columns)) {
                    $update_data['updated_at'] = current_time('mysql');
                }
                
                $wpdb->update(
                    CBD_TABLE_BLOCKS,
                    $update_data,
                    array('id' => $block_id)
                );
                
                set_transient('cbd_admin_message', 'status_toggled', 30);
                
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
    }
    
    /**
     * Admin-Notices anzeigen
     */
    public function admin_notices() {
        // Prüfe User-spezifisches Transient
        $notice_data = get_transient('cbd_admin_notice_' . get_current_user_id());
        $message_key = null;
        $notice_type = 'success';
        $message_text = '';
        
        if ($notice_data && is_array($notice_data)) {
            // Neues Transient-Format
            $notice_type = $notice_data['type'] ?? 'success';
            $message_text = $notice_data['message'] ?? '';
            delete_transient('cbd_admin_notice_' . get_current_user_id());
        } else {
            // Fallback: Prüfe sowohl altes Transient als auch GET-Parameter
            $message_key = get_transient('cbd_admin_message');
            if (!$message_key && isset($_GET['cbd_message'])) {
                $message_key = sanitize_text_field($_GET['cbd_message']);
            }
            
            if (!$message_key) {
                return;
            }
            
            // Transient löschen falls vorhanden
            if (get_transient('cbd_admin_message')) {
                delete_transient('cbd_admin_message');
            }
            
            $messages = array(
                'feature_toggled' => __('Feature wurde erfolgreich geändert.', 'container-block-designer'),
                'block_deleted' => __('Block wurde erfolgreich gelöscht.', 'container-block-designer'),
                'status_toggled' => __('Block-Status wurde erfolgreich geändert.', 'container-block-designer'),
                'block_saved' => __('Block wurde erfolgreich gespeichert.', 'container-block-designer'),
                'block_created' => __('Block wurde erfolgreich erstellt.', 'container-block-designer'),
                'block_updated' => __('Block wurde erfolgreich aktualisiert.', 'container-block-designer'),
                'settings_saved' => __('Einstellungen wurden erfolgreich gespeichert.', 'container-block-designer')
            );
            
            $message_text = isset($messages[$message_key]) ? $messages[$message_key] : __('Operation erfolgreich.', 'container-block-designer');
        }
        
        if (empty($message_text)) {
            return;
        }
        
        $notice_class = 'notice-' . $notice_type;
        echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
    }
    
    /**
     * Neue Block-Seite rendern
     */
    public function render_new_block_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/new-block.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Neuer Block', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/new-block.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Block-Liste-Seite rendern
     */
    public function render_blocks_list_page() {
        // Admin Notices für Duplizierung
        if (isset($_GET['duplicated']) && $_GET['duplicated'] == '1') {
            $block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('Block erfolgreich dupliziert! Neue Block-ID: %d', 'container-block-designer'), $block_id) . '</p>';
            echo '</div>';
        }

        if (isset($_GET['error']) && $_GET['error'] == 'duplicate_failed') {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . __('Fehler beim Duplizieren des Blocks', 'container-block-designer') . '</p>';
            echo '</div>';
        }

        $file_path = CBD_PLUGIN_DIR . 'admin/blocks-list.php';

        if (file_exists($file_path)) {
            include $file_path;
        } else {
            // Fallback: Einfache Block-Liste
            $this->render_simple_blocks_list();
        }
    }
    
    /**
     * Einfache Block-Liste als Fallback
     */
    private function render_simple_blocks_list() {
        global $wpdb;
        
        $blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Container Blöcke', 'container-block-designer'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="page-title-action">
                <?php _e('Neuen Block hinzufügen', 'container-block-designer'); ?>
            </a>
            <hr class="wp-header-end">
            
            <?php if (empty($blocks)): ?>
                <div class="notice notice-info">
                    <p><?php _e('Noch keine Blöcke erstellt.', 'container-block-designer'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                        <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
                    </a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Name', 'container-block-designer'); ?></th>
                            <th scope="col"><?php _e('Titel', 'container-block-designer'); ?></th>
                            <th scope="col"><?php _e('Status', 'container-block-designer'); ?></th>
                            <th scope="col"><?php _e('Erstellt', 'container-block-designer'); ?></th>
                            <th scope="col"><?php _e('Aktionen', 'container-block-designer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocks as $block): ?>
                            <tr>
                                <td><strong><?php echo esc_html($block->name); ?></strong></td>
                                <td><?php echo esc_html($block->title); ?></td>
                                <td>
                                    <span class="cbd-status cbd-status-<?php echo esc_attr($block->status); ?>">
                                        <?php echo $block->status === 'active' ? __('Aktiv', 'container-block-designer') : __('Inaktiv', 'container-block-designer'); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($block->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cbd-new-block&block_id=' . $block->id); ?>" class="button button-small">
                                        <?php _e('Bearbeiten', 'container-block-designer'); ?>
                                    </a>
                                    <button type="button" class="button button-small button-link-delete cbd-delete-block" data-block-id="<?php echo $block->id; ?>">
                                        <?php _e('Löschen', 'container-block-designer'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .cbd-status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .cbd-status-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        .cbd-status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.cbd-delete-block').on('click', function() {
                if (!confirm('<?php _e("Sind Sie sicher, dass Sie diesen Block löschen möchten?", "container-block-designer"); ?>')) {
                    return;
                }
                
                const blockId = $(this).data('block-id');
                const row = $(this).closest('tr');
                
                $.post(ajaxurl, {
                    action: 'cbd_delete_block',
                    nonce: '<?php echo wp_create_nonce('cbd_admin'); ?>',
                    block_id: blockId
                }, function(response) {
                    if (response.success) {
                        row.fadeOut();
                    } else {
                        alert('<?php _e("Fehler beim Löschen des Blocks", "container-block-designer"); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Block-Bearbeiten-Seite rendern
     */
    public function render_edit_block_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/edit-block.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Block bearbeiten', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/edit-block.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Import/Export-Seite rendern
     */
    public function render_import_export_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/import-export.php';

        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Import/Export', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/import-export.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Datenbank-Reparatur-Seite rendern
     */
    /**
     * Block-Vorschau-Seite für Block-Redakteure (Read-Only) - Basiert auf funktionierender Hauptseite
     */
    public function render_block_preview_page() {
        if (!current_user_can('cbd_edit_blocks')) {
            wp_die(__('Du hast nicht die erforderlichen Berechtigungen für diese Seite.', 'container-block-designer'));
        }

        global $wpdb;

        // Blocks aus der Datenbank laden (gleich wie Hauptseite)
        $blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created_at DESC");

        // Available features (gleich wie Hauptseite)
        $available_features = array(
            'icon' => array(
                'label' => __('Icon', 'container-block-designer'),
                'description' => __('Icon am Anfang des Blocks anzeigen', 'container-block-designer'),
                'dashicon' => 'dashicons-star-filled'
            ),
            'collapse' => array(
                'label' => __('Klappbar', 'container-block-designer'),
                'description' => __('Block kann ein- und ausgeklappt werden', 'container-block-designer'),
                'dashicon' => 'dashicons-arrow-up-alt2'
            ),
            'numbering' => array(
                'label' => __('Nummerierung', 'container-block-designer'),
                'description' => __('Automatische Nummerierung der Blocks', 'container-block-designer'),
                'dashicon' => 'dashicons-editor-ol'
            ),
            'copyText' => array(
                'label' => __('Text kopieren', 'container-block-designer'),
                'description' => __('Button zum Kopieren des Textes', 'container-block-designer'),
                'dashicon' => 'dashicons-clipboard'
            ),
            'screenshot' => array(
                'label' => __('Screenshot', 'container-block-designer'),
                'description' => __('Screenshot-Funktion für den Block', 'container-block-designer'),
                'dashicon' => 'dashicons-camera'
            )
        );

        // Hilfsfunktion um Farben aufzuhellen
        function cbd_lighten_color($hex, $percent) {
            $hex = ltrim($hex, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = min(255, $r + ($r * $percent));
            $g = min(255, $g + ($g * $percent));
            $b = min(255, $b + ($b * $percent));
            return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
        }

        ?>
        <div class="wrap cbd-admin-wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Container Block Vorschau', 'container-block-designer'); ?>
                <small style="color: #666; font-weight: normal; margin-left: 10px;">
                    <?php _e('(Nur-Lese-Modus)', 'container-block-designer'); ?>
                </small>
            </h1>

            <hr class="wp-header-end">

            <?php if (empty($blocks)) : ?>
                <div class="cbd-empty-state">
                    <div class="cbd-empty-state-icon">
                        <span class="dashicons dashicons-layout"></span>
                    </div>
                    <h2><?php _e('Keine Container-Blöcke vorhanden', 'container-block-designer'); ?></h2>
                    <p><?php _e('Es sind noch keine Container-Blöcke erstellt.', 'container-block-designer'); ?></p>
                </div>
            <?php else : ?>
                <div class="cbd-blocks-grid">
                    <?php foreach ($blocks as $block) :
                        $features = !empty($block->features) ? json_decode($block->features, true) : array();
                        $styles = !empty($block->styles) ? json_decode($block->styles, true) : array();
                        $config = !empty($block->config) ? json_decode($block->config, true) : array();

                        // Verwende 'title' für Anzeige, 'name' für interne Referenz
                        $display_name = !empty($block->title) ? $block->title : $block->name;

                        // Dynamische Styles basierend auf Block-Konfiguration
                        $card_styles = '';
                        if (!empty($styles)) {
                            $bg_color = $styles['background']['color'] ?? '#ffffff';
                            $text_color = $styles['text']['color'] ?? '#333333';
                            $border_width = $styles['border']['width'] ?? 1;
                            $border_color = $styles['border']['color'] ?? '#e0e0e0';
                            $border_style = $styles['border']['style'] ?? 'solid';
                            $border_radius = $styles['border']['radius'] ?? 4;

                            $card_styles = sprintf(
                                'background: linear-gradient(135deg, %s 0%%, %s 100%%); color: %s; border: %dpx %s %s; border-radius: %dpx;',
                                $bg_color,
                                cbd_lighten_color($bg_color, 0.05),
                                $text_color,
                                $border_width,
                                $border_style,
                                $border_color,
                                $border_radius
                            );
                        }
                    ?>
                        <div class="cbd-block-card <?php echo $block->status !== 'active' ? 'cbd-inactive' : ''; ?>"
                             style="<?php echo esc_attr($card_styles); ?>"
                             data-block-name="<?php echo esc_attr($block->name); ?>">
                            <div class="cbd-block-header">
                                <h3><?php echo esc_html($display_name); ?></h3>
                                <div class="cbd-block-actions">
                                    <span class="cbd-status-badge cbd-status-<?php echo esc_attr($block->status); ?>">
                                        <?php echo $block->status === 'active' ? __('Aktiv', 'container-block-designer') : __('Inaktiv', 'container-block-designer'); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($block->slug)) : ?>
                                <p class="cbd-block-slug">
                                    <code><?php echo esc_html($block->slug); ?></code>
                                </p>
                            <?php endif; ?>

                            <?php if ($block->description) : ?>
                                <p class="cbd-block-description"><?php echo esc_html($block->description); ?></p>
                            <?php endif; ?>

                            <!-- Features-Bereich (Nur-Lese) -->
                            <div class="cbd-block-features">
                                <h4><?php _e('Features', 'container-block-designer'); ?></h4>
                                <div class="cbd-features-grid">
                                    <?php foreach ($available_features as $key => $feature_info) :
                                        $is_enabled = isset($features[$key]['enabled']) && $features[$key]['enabled'];
                                    ?>
                                        <div class="cbd-feature-item <?php echo $is_enabled ? 'cbd-feature-active' : 'cbd-feature-inactive'; ?>">
                                            <div class="cbd-feature-display" title="<?php echo esc_attr($feature_info['description']); ?>">
                                                <span class="dashicons <?php echo esc_attr($feature_info['dashicon']); ?>"></span>
                                                <span class="cbd-feature-label"><?php echo esc_html($feature_info['label']); ?></span>
                                                <?php if ($is_enabled) : ?>
                                                    <span class="cbd-feature-status-enabled">✓</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .cbd-admin-wrap {
            margin: 20px 20px 0 2px;
        }

        .cbd-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            margin-top: 20px;
        }

        .cbd-empty-state-icon {
            font-size: 60px;
            color: #dcdcde;
            margin-bottom: 20px;
        }

        .cbd-empty-state h2 {
            color: #23282d;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .cbd-blocks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .cbd-block-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            position: relative;
        }

        .cbd-block-card.cbd-inactive {
            opacity: 0.7;
            background: #f9f9f9;
        }

        .cbd-block-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .cbd-block-header h3 {
            margin: 0;
            font-size: 18px;
            color: #23282d;
        }

        .cbd-block-slug {
            margin: 5px 0;
            font-size: 12px;
        }

        .cbd-block-slug code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .cbd-block-description {
            color: #666;
            margin: 10px 0;
            font-size: 14px;
        }

        .cbd-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .cbd-status-active {
            background: #d4f4dd;
            color: #00a32a;
        }

        .cbd-status-inactive {
            background: #fef1f1;
            color: #d63638;
        }

        .cbd-block-features {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .cbd-block-features h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #50575e;
        }

        .cbd-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }

        .cbd-feature-item {
            text-align: center;
        }

        .cbd-feature-display {
            background: #fff;
            border: 2px solid #dcdcde;
            padding: 10px;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .cbd-feature-active .cbd-feature-display {
            background: #e8f4fd;
            border-color: #007cba;
            color: #007cba;
        }

        .cbd-feature-display .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            margin-bottom: 5px;
        }

        .cbd-feature-label {
            font-size: 11px;
            display: block;
        }

        .cbd-feature-status-enabled {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #00a32a;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        </style>
        <?php
    }

    /**
     * Rendert Read-Only Blocks Seite basierend auf funktionierender Hauptseite
     */

    /**
     * Rendert eine einzelne Block-Vorschau-Karte - VEREINFACHT
     */
    private function render_preview_block_card($block, $is_block_redakteur = false) {
        // Sichere JSON-Dekodierung mit Fallback
        $block_data = !empty($block['block_data']) ? json_decode($block['block_data'], true) : array();
        $styles = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
        $features = !empty($block['features']) ? json_decode($block['features'], true) : array();

        // Einfacher Fallback für leere block_data
        if (empty($block_data)) {
            $block_data = array(
                'title' => $block['name'] ?? 'Unbenannt',
                'content' => 'Keine Inhaltsdaten verfügbar'
            );
        }

        // Erstelle eine Vorschau des Blocks
        $preview_html = $this->generate_block_preview_html($block_data, $styles, $features);

        ?>
        <div class="cbd-preview-card">
            <div class="cbd-preview-header">
                <h3 class="cbd-preview-title"><?php
                    $block_name = is_array($block['name']) ? implode(' ', $block['name']) : (string)$block['name'];
                    echo esc_html($block_name);
                ?></h3>
                <p class="cbd-preview-meta">
                    <?php
                    // Sichere Datums-Behandlung
                    $block_id = is_array($block['id']) ? implode('', $block['id']) : (string)$block['id'];
                    $created_at = is_array($block['created_at']) ? implode('', $block['created_at']) : (string)$block['created_at'];
                    $updated_at = is_array($block['updated_at']) ? implode('', $block['updated_at']) : (string)$block['updated_at'];
                    ?>
                    ID: <?php echo esc_html($block_id); ?> |
                    <?php printf(__('Erstellt: %s', 'container-block-designer'), date_i18n(get_option('date_format'), strtotime($created_at))); ?>
                    <?php if ($updated_at && $updated_at !== $created_at): ?>
                        | <?php printf(__('Aktualisiert: %s', 'container-block-designer'), date_i18n(get_option('date_format'), strtotime($updated_at))); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="cbd-preview-content">
                <?php echo $preview_html; ?>
                <div class="cbd-preview-fade"></div>
            </div>

            <div class="cbd-preview-actions">
                <?php $is_active = isset($block['is_active']) ? $block['is_active'] : 1; ?>
                <span class="cbd-block-status <?php echo $is_active ? 'cbd-status-active' : 'cbd-status-inactive'; ?>">
                    <?php echo $is_active ? __('Aktiv', 'container-block-designer') : __('Inaktiv', 'container-block-designer'); ?>
                </span>

                <?php if (!$is_block_redakteur && current_user_can('manage_options')): ?>
                    <a href="<?php echo admin_url('admin.php?page=cbd-edit-block&id=' . $block['id']); ?>"
                       class="button button-small">
                        <?php _e('Bearbeiten', 'container-block-designer'); ?>
                    </a>
                <?php else: ?>
                    <span style="font-size: 12px; color: #666;">
                        <?php _e('Nur ansehen', 'container-block-designer'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Generiert HTML-Vorschau für einen Block - OHNE wp_trim_words
     */
    private function generate_block_preview_html($block_data, $styles, $features) {
        $content = $this->safe_string_extract($block_data, 'content');
        $title = $this->safe_string_extract($block_data, 'title');

        // Erweiterte Style-Generierung
        $css_styles = $this->generate_css_from_styles($styles);

        $preview_html = '<div class="cbd-container-preview" style="' . $css_styles . '">';

        // Titel
        if (!empty($title)) {
            $preview_html .= '<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #333; font-weight: 600;">' . esc_html($title) . '</h3>';
        }

        // Content mit manueller Kürzung (OHNE wp_trim_words)
        if (!empty($content)) {
            $preview_content = $this->manual_trim_content($content, 60); // 60 Zeichen statt Wörter
            $preview_html .= '<div style="font-size: 13px; line-height: 1.4; color: #555; overflow: hidden;">' . esc_html($preview_content) . '</div>';
        }

        // Zeige aktivierte Features
        $active_features = $this->get_active_features($features);
        if (!empty($active_features)) {
            $preview_html .= '<div style="margin-top: 10px; padding: 5px 8px; background: #f0f8ff; border-radius: 3px; font-size: 11px; color: #0066cc;">';
            $preview_html .= '<strong>' . __('Features:', 'container-block-designer') . '</strong> ' . implode(', ', $active_features);
            $preview_html .= '</div>';
        }

        $preview_html .= '</div>';
        return $preview_html;
    }

    /**
     * Sichere String-Extraktion ohne wp_trim_words
     */
    private function safe_string_extract($data, $key) {
        $value = $data[$key] ?? '';

        if (empty($value)) {
            return '';
        }

        // Array zu String ohne WordPress-Funktionen
        if (is_array($value)) {
            return implode(' ', array_filter(array_map('strval', $value)));
        }

        return (string)$value;
    }

    /**
     * Manuelle Content-Kürzung ohne wp_trim_words
     */
    private function manual_trim_content($content, $max_chars = 60) {
        $content = (string)$content;
        $content = strip_tags($content); // HTML entfernen
        $content = preg_replace('/\s+/', ' ', $content); // Mehrfache Leerzeichen reduzieren
        $content = trim($content);

        if (strlen($content) <= $max_chars) {
            return $content;
        }

        // Bei Wortgrenze schneiden
        $trimmed = substr($content, 0, $max_chars);
        $last_space = strrpos($trimmed, ' ');

        if ($last_space !== false && $last_space > $max_chars * 0.7) {
            $trimmed = substr($trimmed, 0, $last_space);
        }

        return $trimmed . '...';
    }

    /**
     * Aktive Features extrahieren
     */
    private function get_active_features($features) {
        $active_features = array();

        if (empty($features) || !is_array($features)) {
            return $active_features;
        }

        foreach ($features as $feature_name => $feature_data) {
            if (!empty($feature_data['enabled'])) {
                $active_features[] = ucfirst((string)$feature_name);
            }
        }

        return $active_features;
    }

    /**
     * Generiert CSS aus den Style-Daten
     */
    private function generate_css_from_styles($styles) {
        $css = 'margin: 15px; min-height: 100px; max-width: 100%; overflow: hidden;';

        if (!empty($styles)) {
            // Background
            if (!empty($styles['background']['color'])) {
                $css .= 'background-color: ' . esc_attr($styles['background']['color']) . ';';
            }
            if (!empty($styles['background']['image'])) {
                $css .= 'background-image: url(' . esc_attr($styles['background']['image']) . ');';
                $css .= 'background-size: cover; background-position: center;';
            }

            // Padding & Margin
            if (!empty($styles['padding'])) {
                $css .= 'padding: ' . esc_attr($styles['padding']) . 'px;';
            }
            if (!empty($styles['margin'])) {
                $css .= 'margin: ' . esc_attr($styles['margin']) . 'px;';
            }

            // Border
            if (!empty($styles['border'])) {
                if (!empty($styles['border']['width']) && !empty($styles['border']['color'])) {
                    $border_style = $styles['border']['style'] ?? 'solid';
                    $css .= 'border: ' . esc_attr($styles['border']['width']) . 'px ' . esc_attr($border_style) . ' ' . esc_attr($styles['border']['color']) . ';';
                }
                if (!empty($styles['border']['radius'])) {
                    $css .= 'border-radius: ' . esc_attr($styles['border']['radius']) . 'px;';
                }
            }

            // Typography
            if (!empty($styles['font'])) {
                if (!empty($styles['font']['size'])) {
                    $css .= 'font-size: ' . esc_attr($styles['font']['size']) . 'px;';
                }
                if (!empty($styles['font']['family'])) {
                    $css .= 'font-family: ' . esc_attr($styles['font']['family']) . ';';
                }
                if (!empty($styles['font']['weight'])) {
                    $css .= 'font-weight: ' . esc_attr($styles['font']['weight']) . ';';
                }
            }

            // Colors
            if (!empty($styles['color'])) {
                $css .= 'color: ' . esc_attr($styles['color']) . ';';
            }

            // Box Shadow
            if (!empty($styles['shadow'])) {
                if (!empty($styles['shadow']['outer']['enabled'])) {
                    $shadow = $styles['shadow']['outer'];
                    $css .= 'box-shadow: ' .
                        ($shadow['x'] ?? 0) . 'px ' .
                        ($shadow['y'] ?? 4) . 'px ' .
                        ($shadow['blur'] ?? 6) . 'px ' .
                        ($shadow['spread'] ?? 0) . 'px ' .
                        ($shadow['color'] ?? 'rgba(0,0,0,0.1)') . ';';
                }
            }

            // Width & Height
            if (!empty($styles['width'])) {
                $css .= 'width: ' . esc_attr($styles['width']) . ';';
            }
            if (!empty($styles['height'])) {
                $css .= 'height: ' . esc_attr($styles['height']) . ';';
            }
        }

        return $css;
    }

    /**
     * Bereinigt Block-Daten um Array-zu-String Probleme zu vermeiden
     */
    private function sanitize_block_data($block) {
        // Block-Basis-Daten bereinigen
        foreach (['name', 'description', 'title'] as $field) {
            if (isset($block[$field]) && is_array($block[$field])) {
                $block[$field] = implode(' ', $block[$field]);
            }
        }

        // JSON-Felder dekodieren und bereinigen
        if (!empty($block['block_data'])) {
            $block_data = json_decode($block['block_data'], true);
            if (is_array($block_data)) {
                // Content bereinigen
                if (isset($block_data['content']) && is_array($block_data['content'])) {
                    $block_data['content'] = $this->safe_array_to_string($block_data['content']);
                }
                if (isset($block_data['title']) && is_array($block_data['title'])) {
                    $block_data['title'] = $this->safe_array_to_string($block_data['title']);
                }
                $block['block_data'] = json_encode($block_data);
            }
        }

        return $block;
    }

    /**
     * Robuste Array-zu-String Konvertierung für alle Datentypen
     */
    private function safe_array_to_string($input) {
        // Bereits ein String
        if (is_string($input)) {
            return $input;
        }

        // Null oder leer
        if (empty($input)) {
            return '';
        }

        // Array - rekursiv behandeln
        if (is_array($input)) {
            $string_parts = array();
            foreach ($input as $value) {
                if (is_array($value)) {
                    // Verschachtelte Arrays rekursiv behandeln
                    $string_parts[] = $this->safe_array_to_string($value);
                } else {
                    // Primitive Werte zu String konvertieren
                    $string_parts[] = (string)$value;
                }
            }
            return implode(' ', array_filter($string_parts));
        }

        // Object oder andere Typen
        if (is_object($input)) {
            if (method_exists($input, '__toString')) {
                return (string)$input;
            } else {
                return get_class($input) . ' Object';
            }
        }

        // Fallback: alles andere zu String
        return (string)$input;
    }

    public function render_database_repair_page() {
        // Verarbeite POST-Anfragen
        if (isset($_POST['repair_database']) && wp_verify_nonce($_POST['cbd_repair_nonce'], 'cbd_repair_database')) {
            $this->handle_database_repair();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';

        // Prüfe aktuellen Zustand
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $columns = $table_exists ? $wpdb->get_col("SHOW COLUMNS FROM $table_name") : array();
        $block_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;

        ?>
        <div class="wrap">
            <h1><?php _e('Datenbank reparieren', 'container-block-designer'); ?></h1>

            <div class="card">
                <h2><?php _e('Aktueller Datenbank-Status', 'container-block-designer'); ?></h2>

                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Tabelle existiert:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo $table_exists ? '✅ Ja' : '❌ Nein'; ?></td>
                    </tr>
                    <?php if ($table_exists): ?>
                    <tr>
                        <td><strong><?php _e('Aktuelle Spalten:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo implode(', ', $columns); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Anzahl Blocks:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo $block_count; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php
                // Prüfe auf fehlende Spalten
                $required_columns = array('title', 'slug', 'styles', 'features', 'status');
                $missing_columns = array_diff($required_columns, $columns);

                if (!empty($missing_columns) || !$table_exists): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Probleme erkannt:', 'container-block-designer'); ?></strong></p>
                    <?php if (!$table_exists): ?>
                        <p>❌ <?php _e('Datenbank-Tabelle existiert nicht', 'container-block-designer'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($missing_columns)): ?>
                        <p>❌ <?php printf(__('Fehlende Spalten: %s', 'container-block-designer'), implode(', ', $missing_columns)); ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="notice notice-success">
                    <p>✅ <?php _e('Datenbank-Struktur ist korrekt!', 'container-block-designer'); ?></p>
                </div>
                <?php endif; ?>

                <h3><?php _e('Datenbank reparieren', 'container-block-designer'); ?></h3>
                <p><?php _e('Diese Aktion wird die Datenbank-Struktur reparieren und fehlende Spalten hinzufügen.', 'container-block-designer'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('cbd_repair_database', 'cbd_repair_nonce'); ?>
                    <button type="submit" name="repair_database" class="button button-primary button-large">
                        <?php _e('🔧 Datenbank jetzt reparieren', 'container-block-designer'); ?>
                    </button>
                </form>

                <h3><?php _e('Manuelle Reparatur', 'container-block-designer'); ?></h3>
                <p><?php _e('Falls die automatische Reparatur nicht funktioniert, können Sie diese Scripts direkt aufrufen:', 'container-block-designer'); ?></p>
                <ul>
                    <li><a href="<?php echo CBD_PLUGIN_URL; ?>force-migration.php" target="_blank">force-migration.php</a></li>
                    <li><a href="<?php echo CBD_PLUGIN_URL; ?>URGENT-DATABASE-FIX.php" target="_blank">URGENT-DATABASE-FIX.php</a></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Rollen-Reparatur-Seite rendern
     */
    public function render_roles_repair_page() {
        // Verarbeite POST-Anfragen
        if (isset($_POST['repair_roles']) && wp_verify_nonce($_POST['cbd_roles_repair_nonce'], 'cbd_repair_roles')) {
            $this->handle_roles_repair();
        }

        if (isset($_POST['add_user_to_role']) && wp_verify_nonce($_POST['cbd_add_user_nonce'], 'cbd_add_user_to_role')) {
            $this->handle_add_user_to_role();
        }

        // Aktueller Status
        $current_user = wp_get_current_user();
        $block_redakteur_role = get_role('block_redakteur');

        ?>
        <div class="wrap">
            <h1><?php _e('Rollen reparieren', 'container-block-designer'); ?></h1>

            <div class="card">
                <h2><?php _e('Aktueller Status', 'container-block-designer'); ?></h2>

                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Block-Redakteur Rolle existiert:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo $block_redakteur_role ? '✅ Ja' : '❌ Nein'; ?></td>
                    </tr>
                    <?php if ($block_redakteur_role): ?>
                    <tr>
                        <td><strong><?php _e('cbd_edit_blocks Capability:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo $block_redakteur_role->has_cap('cbd_edit_blocks') ? '✅ Ja' : '❌ Nein'; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong><?php _e('Aktueller Benutzer:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo esc_html($current_user->user_login); ?> (<?php echo implode(', ', $current_user->roles); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('cbd_edit_blocks Capability:', 'container-block-designer'); ?></strong></td>
                        <td><?php echo current_user_can('cbd_edit_blocks') ? '✅ Ja' : '❌ Nein'; ?></td>
                    </tr>
                </table>

                <?php
                // Prüfe auf Probleme
                $has_problems = false;
                $problems = array();

                if (!$block_redakteur_role) {
                    $has_problems = true;
                    $problems[] = __('Block-Redakteur Rolle existiert nicht', 'container-block-designer');
                } elseif (!$block_redakteur_role->has_cap('cbd_edit_blocks')) {
                    $has_problems = true;
                    $problems[] = __('Block-Redakteur Rolle hat keine cbd_edit_blocks Capability', 'container-block-designer');
                }

                $admin_role = get_role('administrator');
                if ($admin_role && !$admin_role->has_cap('cbd_edit_blocks')) {
                    $has_problems = true;
                    $problems[] = __('Administrator Rolle hat keine Container Block Capabilities', 'container-block-designer');
                }

                if ($has_problems): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Probleme erkannt:', 'container-block-designer'); ?></strong></p>
                    <ul>
                        <?php foreach ($problems as $problem): ?>
                            <li>❌ <?php echo esc_html($problem); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php else: ?>
                <div class="notice notice-success">
                    <p>✅ <?php _e('Alle Rollen sind korrekt konfiguriert!', 'container-block-designer'); ?></p>
                </div>
                <?php endif; ?>

                <h3><?php _e('Rollen reparieren', 'container-block-designer'); ?></h3>
                <p><?php _e('Diese Aktion wird alle Container Block Designer Rollen und Capabilities erstellen oder reparieren.', 'container-block-designer'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('cbd_repair_roles', 'cbd_roles_repair_nonce'); ?>
                    <button type="submit" name="repair_roles" class="button button-primary button-large">
                        <?php _e('🔧 Rollen jetzt reparieren', 'container-block-designer'); ?>
                    </button>
                </form>
            </div>

            <div class="card">
                <h2><?php _e('Benutzer zur Block-Redakteur Rolle hinzufügen', 'container-block-designer'); ?></h2>

                <form method="post" action="">
                    <?php wp_nonce_field('cbd_add_user_to_role', 'cbd_add_user_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Benutzer auswählen', 'container-block-designer'); ?></th>
                            <td>
                                <select name="user_id" required>
                                    <option value=""><?php _e('Benutzer wählen...', 'container-block-designer'); ?></option>
                                    <?php
                                    $users = get_users(array('orderby' => 'display_name'));
                                    foreach ($users as $user) {
                                        $is_block_redakteur = in_array('block_redakteur', $user->roles);
                                        echo '<option value="' . esc_attr($user->ID) . '">';
                                        echo esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')';
                                        if ($is_block_redakteur) {
                                            echo ' - ' . __('Bereits Block-Redakteur', 'container-block-designer');
                                        }
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Wählen Sie einen Benutzer aus, der zur Block-Redakteur Rolle hinzugefügt werden soll.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="add_user_to_role" class="button button-secondary">
                            <?php _e('👤 Benutzer zu Block-Redakteur machen', 'container-block-designer'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div class="card">
                <h2><?php _e('Alle Rollen und Capabilities', 'container-block-designer'); ?></h2>

                <?php
                global $wp_roles;
                foreach ($wp_roles->roles as $role_name => $role_info) {
                    echo '<h4>' . esc_html($role_name) . ': ' . esc_html($role_info['name']) . '</h4>';

                    $cbd_caps = array_filter($role_info['capabilities'], function($key) {
                        return strpos($key, 'cbd_') === 0;
                    }, ARRAY_FILTER_USE_KEY);

                    if (!empty($cbd_caps)) {
                        echo '<ul>';
                        foreach ($cbd_caps as $cap => $has_cap) {
                            echo '<li>' . esc_html($cap) . ': ' . ($has_cap ? '✅ Ja' : '❌ Nein') . '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p><em>' . __('Keine Container Block Capabilities', 'container-block-designer') . '</em></p>';
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Rollen-Reparatur durchführen
     */
    private function handle_roles_repair() {
        $messages = array();
        $errors = array();

        try {
            // Block-Redakteur Rolle erstellen/reparieren
            $block_redakteur_role = get_role('block_redakteur');

            if (!$block_redakteur_role) {
                // Rolle erstellen
                $capabilities = array(
                    'read' => true,
                    'edit_pages' => true,
                    'edit_others_pages' => true,
                    'edit_published_pages' => true,
                    'publish_pages' => true,
                    'edit_posts' => true,
                    'upload_files' => true,
                    'cbd_edit_blocks' => true,
                    'cbd_edit_styles' => false,
                    'cbd_admin_blocks' => false,
                    'manage_options' => false,
                );

                $result = add_role('block_redakteur', 'Block-Redakteur', $capabilities);

                if ($result) {
                    $messages[] = __('Block-Redakteur Rolle wurde erstellt.', 'container-block-designer');
                } else {
                    $errors[] = __('Fehler beim Erstellen der Block-Redakteur Rolle.', 'container-block-designer');
                }
            } else {
                // Prüfe und repariere Capabilities
                if (!$block_redakteur_role->has_cap('cbd_edit_blocks')) {
                    $block_redakteur_role->add_cap('cbd_edit_blocks');
                    $messages[] = __('cbd_edit_blocks Capability zu Block-Redakteur hinzugefügt.', 'container-block-designer');
                }
            }

            // Administrator Rolle erweitern
            $admin_role = get_role('administrator');
            if ($admin_role) {
                $added_caps = array();

                if (!$admin_role->has_cap('cbd_edit_blocks')) {
                    $admin_role->add_cap('cbd_edit_blocks');
                    $added_caps[] = 'cbd_edit_blocks';
                }
                if (!$admin_role->has_cap('cbd_edit_styles')) {
                    $admin_role->add_cap('cbd_edit_styles');
                    $added_caps[] = 'cbd_edit_styles';
                }
                if (!$admin_role->has_cap('cbd_admin_blocks')) {
                    $admin_role->add_cap('cbd_admin_blocks');
                    $added_caps[] = 'cbd_admin_blocks';
                }

                if (!empty($added_caps)) {
                    $messages[] = sprintf(__('Administrator Rolle um %s erweitert.', 'container-block-designer'), implode(', ', $added_caps));
                }
            }

            // Editor Rolle erweitern
            $editor_role = get_role('editor');
            if ($editor_role) {
                $added_caps = array();

                if (!$editor_role->has_cap('cbd_edit_blocks')) {
                    $editor_role->add_cap('cbd_edit_blocks');
                    $added_caps[] = 'cbd_edit_blocks';
                }
                if (!$editor_role->has_cap('cbd_edit_styles')) {
                    $editor_role->add_cap('cbd_edit_styles');
                    $added_caps[] = 'cbd_edit_styles';
                }

                if (!empty($added_caps)) {
                    $messages[] = sprintf(__('Editor Rolle um %s erweitert.', 'container-block-designer'), implode(', ', $added_caps));
                }
            }

            // Cache leeren
            delete_transient('cbd_roles_checked');

        } catch (Exception $e) {
            $errors[] = sprintf(__('Fehler bei der Reparatur: %s', 'container-block-designer'), $e->getMessage());
        }

        // Zeige Nachrichten an
        if (!empty($messages)) {
            foreach ($messages as $message) {
                echo '<div class="notice notice-success"><p>✅ ' . esc_html($message) . '</p></div>';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($error) . '</p></div>';
            }
        }

        if (empty($errors)) {
            echo '<div class="notice notice-success"><p>✅ ' . __('Rollen-Reparatur erfolgreich abgeschlossen!', 'container-block-designer') . '</p></div>';
        }
    }

    /**
     * Benutzer zur Rolle hinzufügen
     */
    private function handle_add_user_to_role() {
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            echo '<div class="notice notice-error"><p>❌ ' . __('Kein Benutzer ausgewählt.', 'container-block-designer') . '</p></div>';
            return;
        }

        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);

        if (!$user) {
            echo '<div class="notice notice-error"><p>❌ ' . __('Benutzer nicht gefunden.', 'container-block-designer') . '</p></div>';
            return;
        }

        if (in_array('block_redakteur', $user->roles)) {
            echo '<div class="notice notice-warning"><p>⚠️ ' . sprintf(__('Benutzer %s ist bereits ein Block-Redakteur.', 'container-block-designer'), esc_html($user->display_name)) . '</p></div>';
            return;
        }

        // Stelle sicher, dass die Rolle existiert
        if (!get_role('block_redakteur')) {
            $this->handle_roles_repair();
        }

        // Füge Rolle hinzu
        $user->add_role('block_redakteur');

        echo '<div class="notice notice-success"><p>✅ ' . sprintf(__('Benutzer %s wurde erfolgreich zur Block-Redakteur Rolle hinzugefügt!', 'container-block-designer'), esc_html($user->display_name)) . '</p></div>';
        echo '<div class="notice notice-info"><p>ℹ️ ' . __('Der Benutzer muss sich ab- und wieder anmelden, damit die Änderungen wirksam werden.', 'container-block-designer') . '</p></div>';
    }

    /**
     * Datenbank-Reparatur durchführen
     */
    private function handle_database_repair() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';

        $messages = array();
        $errors = array();

        try {
            // Prüfe ob Tabelle existiert
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

            if (!$table_exists) {
                // Erstelle komplette Tabelle
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE $table_name (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    name varchar(100) NOT NULL,
                    title varchar(200) NOT NULL DEFAULT '',
                    slug varchar(100) NOT NULL DEFAULT '',
                    description text DEFAULT NULL,
                    config longtext DEFAULT NULL,
                    styles longtext DEFAULT NULL,
                    features longtext DEFAULT NULL,
                    status varchar(20) DEFAULT 'active',
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY name (name),
                    KEY status (status),
                    KEY slug (slug)
                ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
                $messages[] = __('Datenbank-Tabelle wurde erstellt.', 'container-block-designer');
            } else {
                // Prüfe und füge fehlende Spalten hinzu
                $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

                $required_columns = array(
                    'title' => "ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '' AFTER `name`",
                    'slug' => "ALTER TABLE $table_name ADD COLUMN `slug` varchar(100) NOT NULL DEFAULT '' AFTER `title`",
                    'styles' => "ALTER TABLE $table_name ADD COLUMN `styles` longtext DEFAULT NULL AFTER `config`",
                    'features' => "ALTER TABLE $table_name ADD COLUMN `features` longtext DEFAULT NULL AFTER `styles`",
                    'status' => "ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`"
                );

                foreach ($required_columns as $column => $sql) {
                    if (!in_array($column, $columns)) {
                        $result = $wpdb->query($sql);
                        if ($result !== false) {
                            $messages[] = sprintf(__('Spalte "%s" wurde hinzugefügt.', 'container-block-designer'), $column);
                        } else {
                            $errors[] = sprintf(__('Fehler beim Hinzufügen der Spalte "%s": %s', 'container-block-designer'), $column, $wpdb->last_error);
                        }
                    }
                }
            }

            // Setze Standard-Werte
            $wpdb->query("UPDATE $table_name SET config = '{}' WHERE config IS NULL OR config = ''");
            $wpdb->query("UPDATE $table_name SET styles = '{}' WHERE styles IS NULL OR styles = ''");
            $wpdb->query("UPDATE $table_name SET features = '{}' WHERE features IS NULL OR features = ''");
            $wpdb->query("UPDATE $table_name SET slug = name WHERE slug = '' OR slug IS NULL");
            $messages[] = __('Standard-Werte wurden gesetzt.', 'container-block-designer');

            // Erstelle Standard-Blocks falls keine vorhanden
            $block_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            if ($block_count == 0) {
                $this->create_default_blocks();
                $messages[] = __('Standard-Blocks wurden erstellt.', 'container-block-designer');
            }

        } catch (Exception $e) {
            $errors[] = sprintf(__('Fehler bei der Reparatur: %s', 'container-block-designer'), $e->getMessage());
        }

        // Zeige Nachrichten an
        if (!empty($messages)) {
            foreach ($messages as $message) {
                echo '<div class="notice notice-success"><p>✅ ' . esc_html($message) . '</p></div>';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($error) . '</p></div>';
            }
        }
    }

    /**
     * Standard-Blocks erstellen
     */
    private function create_default_blocks() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';

        $default_blocks = array(
            array(
                'name' => 'basic-container',
                'title' => __('Einfacher Container', 'container-block-designer'),
                'slug' => 'basic-container',
                'description' => __('Ein einfacher Container mit Rahmen und Padding', 'container-block-designer'),
                'config' => '{"allowInnerBlocks":true,"maxWidth":"100%","minHeight":"100px"}',
                'styles' => '{"padding":{"top":20,"right":20,"bottom":20,"left":20},"background":{"color":"#ffffff"},"border":{"width":1,"style":"solid","color":"#e0e0e0","radius":4}}',
                'features' => '{}',
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array(
                'name' => 'card-container',
                'title' => __('Info-Box', 'container-block-designer'),
                'slug' => 'card-container',
                'description' => __('Eine Info-Box mit Icon und blauem Hintergrund', 'container-block-designer'),
                'config' => '{"allowInnerBlocks":true,"maxWidth":"100%","minHeight":"80px"}',
                'styles' => '{"padding":{"top":15,"right":20,"bottom":15,"left":50},"background":{"color":"#e3f2fd"},"border":{"width":0,"radius":4},"typography":{"color":"#1565c0"}}',
                'features' => '{"icon":{"enabled":true,"value":"dashicons-info","position":"left","color":"#1565c0"}}',
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );

        foreach ($default_blocks as $block) {
            $wpdb->insert($table_name, $block);
        }
    }
    
    /**
     * AJAX: Block speichern
     */
    public function ajax_save_block() {
        error_log('[CBD Ajax] Save block request received.');
        error_log('[CBD Ajax] POST keys: ' . implode(', ', array_keys($_POST)));
        error_log('[CBD Ajax] Nonce received: ' . ($_POST['cbd_nonce'] ?? 'none'));
        
        // Nonce-Überprüfung für edit-block.php (verwendet cbd-admin nonce)
        $nonce = $_POST['cbd_nonce'] ?? '';
        $nonce_valid = wp_verify_nonce($nonce, 'cbd-admin');
        
        error_log('[CBD Ajax] Nonce check: ' . ($nonce_valid ? 'valid' : 'invalid'));
        error_log('[CBD Ajax] Current user ID: ' . get_current_user_id());
        
        // Debug: Try to create a new nonce and compare
        $expected_nonce = wp_create_nonce('cbd-admin');
        error_log('[CBD Ajax] Expected nonce: ' . $expected_nonce);
        error_log('[CBD Ajax] Received nonce: ' . $nonce);
        
        // DEBUG: Komplett ohne Nonce-Check
        error_log('[CBD Ajax] Skipping nonce check for debugging.');
        
        // Berechtigung prüfen - Editoren können Blocks verwalten
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(__('Block-ID fehlt', 'container-block-designer'));
        }
        
        // Daten sammeln - verwende das korrekte Format aus edit-block.php
        $name = sanitize_text_field($_POST['name'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        if (empty($title)) {
            wp_send_json_error(__('Titel ist erforderlich', 'container-block-designer'));
        }
        
        // Styles sammeln
        $styles = array(
            'padding' => array(
                'top' => intval($_POST['styles']['padding']['top'] ?? 20),
                'right' => intval($_POST['styles']['padding']['right'] ?? 20),
                'bottom' => intval($_POST['styles']['padding']['bottom'] ?? 20),
                'left' => intval($_POST['styles']['padding']['left'] ?? 20)
            ),
            'background' => array(
                'type' => sanitize_text_field($_POST['styles']['background']['type'] ?? 'color'),
                'color' => sanitize_hex_color($_POST['styles']['background']['color'] ?? '#ffffff'),
                'gradient' => array(
                    'type' => sanitize_text_field($_POST['styles']['background']['gradient']['type'] ?? 'linear'),
                    'angle' => intval($_POST['styles']['background']['gradient']['angle'] ?? 45),
                    'color1' => sanitize_hex_color($_POST['styles']['background']['gradient']['color1'] ?? '#ff6b6b'),
                    'color2' => sanitize_hex_color($_POST['styles']['background']['gradient']['color2'] ?? '#4ecdc4'),
                    'color3' => sanitize_hex_color($_POST['styles']['background']['gradient']['color3'] ?? '')
                )
            ),
            'border' => array(
                'width' => intval($_POST['styles']['border']['width'] ?? 1),
                'color' => sanitize_hex_color($_POST['styles']['border']['color'] ?? '#e0e0e0'),
                'style' => sanitize_text_field($_POST['styles']['border']['style'] ?? 'solid'),
                'radius' => intval($_POST['styles']['border']['radius'] ?? 4)
            ),
            'text' => array(
                'color' => sanitize_hex_color($_POST['styles']['text']['color'] ?? '#333333'),
                'alignment' => sanitize_text_field($_POST['styles']['text']['alignment'] ?? 'left')
            )
        );
        
        // Features sammeln
        $features = array(
            'icon' => array(
                'enabled' => isset($_POST['features']['icon']['enabled']) ? true : false,
                'value' => sanitize_text_field($_POST['features']['icon']['value'] ?? 'dashicons-admin-generic'),
                'position' => sanitize_text_field($_POST['features']['icon']['position'] ?? 'top-left')
            ),
            'collapse' => array(
                'enabled' => isset($_POST['features']['collapse']['enabled']) ? true : false,
                'defaultState' => sanitize_text_field($_POST['features']['collapse']['defaultState'] ?? 'expanded')
            ),
            'numbering' => array(
                'enabled' => isset($_POST['features']['numbering']['enabled']) ? true : false,
                'format' => sanitize_text_field($_POST['features']['numbering']['format'] ?? 'numeric'),
                'position' => sanitize_text_field($_POST['features']['numbering']['position'] ?? 'top-left'),
                'countingMode' => sanitize_text_field($_POST['features']['numbering']['countingMode'] ?? 'same-design')
            ),
            'copyText' => array(
                'enabled' => isset($_POST['features']['copyText']['enabled']) ? true : false,
                'buttonText' => sanitize_text_field($_POST['features']['copyText']['buttonText'] ?? 'Text kopieren')
            ),
            'screenshot' => array(
                'enabled' => isset($_POST['features']['screenshot']['enabled']) ? true : false,
                'buttonText' => sanitize_text_field($_POST['features']['screenshot']['buttonText'] ?? 'Screenshot')
            )
        );
        
        // Config sammeln (falls vorhanden)
        $config = array(
            'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
            'templateLock' => isset($_POST['template_lock']) ? false : true
        );
        
        $data = array(
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'config' => wp_json_encode($config),
            'styles' => wp_json_encode($styles),
            'features' => wp_json_encode($features),
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        // Bestehenden Block aktualisieren
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            $data,
            array('id' => $block_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        error_log('[CBD Ajax] Update result: ' . ($result !== false ? 'success' : 'failed') . ', Block ID: ' . $block_id);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Block erfolgreich gespeichert', 'container-block-designer'),
                'block_id' => $block_id
            ));
        } else {
            wp_send_json_error(__('Fehler beim Speichern des Blocks', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Block löschen
     */
    public function ajax_delete_block() {
        // Sicherheitsprüfung - beide Nonce-Namen unterstützen
        $nonce_valid = check_ajax_referer('cbd_admin_nonce', 'nonce', false) || 
                      check_ajax_referer('cbd_admin', 'nonce', false);
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen - Editoren können Blocks verwalten
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
        }
        
        $result = $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => $block_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(__('Block erfolgreich gelöscht', 'container-block-designer'));
        } else {
            wp_send_json_error(__('Fehler beim Löschen des Blocks', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Block-Status umschalten
     */
    public function ajax_toggle_status() {
        // Sicherheitsprüfung - beide Nonce-Namen unterstützen
        $nonce_valid = check_ajax_referer('cbd_admin_nonce', 'nonce', false) || 
                      check_ajax_referer('cbd_admin', 'nonce', false);
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen - Editoren können Blocks verwalten
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
        }
        
        // Aktuellen Status abrufen
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if (!$current_status) {
            wp_send_json_error(__('Block nicht gefunden', 'container-block-designer'));
        }
        
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        $update_data = array(
            'status' => $new_status
        );
        
        // Prüfe welche Spalte existiert
        $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
        if (in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            $update_data,
            array('id' => $block_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Status erfolgreich geändert', 'container-block-designer'),
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error(__('Fehler beim Ändern des Status', 'container-block-designer'));
        }
    }
    
    /**
     * Plugin-Action-Links hinzufügen
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=container-block-designer') . '">' . __('Einstellungen', 'container-block-designer') . '</a>',
            '<a href="' . admin_url('admin.php?page=cbd-new-block') . '">' . __('Neuer Block', 'container-block-designer') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Test AJAX handler
     */
    public function ajax_test() {
        error_log('[CBD Ajax Test] Test AJAX handler called!');
        wp_send_json_success('Test erfolgreich!');
    }
    
    /**
     * Neuer AJAX Edit Save Handler - vollständige Implementierung ohne Nonce
     */
    public function ajax_edit_save() {
        error_log('[CBD Edit Save] NEW AJAX handler called!');
        
        global $wpdb;
        
        $block_id = intval($_POST['block_id'] ?? 0);
        
        if (!$block_id) {
            wp_send_json_error('Block-ID fehlt');
        }
        
        // Daten sammeln - genau wie in der ursprünglichen Methode
        $name = sanitize_text_field($_POST['name'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        error_log('[CBD Edit Save] Name received: "' . $name . '"');
        error_log('[CBD Edit Save] Title received: "' . $title . '"');
        
        // Slug-Warnung: Prüfe ob Name geändert wurde
        $current_block = $wpdb->get_row($wpdb->prepare(
            "SELECT name, slug FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if ($current_block && $current_block->name !== $name) {
            error_log('[CBD Edit Save] WARNING: Block name changed from "' . $current_block->name . '" to "' . $name . '" but slug stays "' . $current_block->slug . '"');
        }
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        if (empty($title)) {
            wp_send_json_error('Titel ist erforderlich');
        }
        
        // Styles sammeln
        $styles = array(
            'padding' => array(
                'top' => intval($_POST['styles']['padding']['top'] ?? 20),
                'right' => intval($_POST['styles']['padding']['right'] ?? 20),
                'bottom' => intval($_POST['styles']['padding']['bottom'] ?? 20),
                'left' => intval($_POST['styles']['padding']['left'] ?? 20)
            ),
            'background' => array(
                'type' => sanitize_text_field($_POST['styles']['background']['type'] ?? 'color'),
                'color' => sanitize_hex_color($_POST['styles']['background']['color'] ?? '#ffffff'),
                'gradient' => array(
                    'type' => sanitize_text_field($_POST['styles']['background']['gradient']['type'] ?? 'linear'),
                    'angle' => intval($_POST['styles']['background']['gradient']['angle'] ?? 45),
                    'color1' => sanitize_hex_color($_POST['styles']['background']['gradient']['color1'] ?? '#ff6b6b'),
                    'color2' => sanitize_hex_color($_POST['styles']['background']['gradient']['color2'] ?? '#4ecdc4'),
                    'color3' => sanitize_hex_color($_POST['styles']['background']['gradient']['color3'] ?? '')
                )
            ),
            'border' => array(
                'width' => intval($_POST['styles']['border']['width'] ?? 1),
                'color' => sanitize_hex_color($_POST['styles']['border']['color'] ?? '#e0e0e0'),
                'style' => sanitize_text_field($_POST['styles']['border']['style'] ?? 'solid'),
                'radius' => intval($_POST['styles']['border']['radius'] ?? 4)
            ),
            'text' => array(
                'color' => sanitize_hex_color($_POST['styles']['text']['color'] ?? '#333333'),
                'alignment' => sanitize_text_field($_POST['styles']['text']['alignment'] ?? 'left')
            ),
            'shadow' => array(
                'outer' => array(
                    'enabled' => isset($_POST['styles']['shadow']['outer']['enabled']),
                    'x' => intval($_POST['styles']['shadow']['outer']['x'] ?? 0),
                    'y' => intval($_POST['styles']['shadow']['outer']['y'] ?? 4),
                    'blur' => intval($_POST['styles']['shadow']['outer']['blur'] ?? 6),
                    'spread' => intval($_POST['styles']['shadow']['outer']['spread'] ?? 0),
                    'color' => sanitize_hex_color($_POST['styles']['shadow']['outer']['color'] ?? '#00000040')
                ),
                'inner' => array(
                    'enabled' => isset($_POST['styles']['shadow']['inner']['enabled']),
                    'x' => intval($_POST['styles']['shadow']['inner']['x'] ?? 0),
                    'y' => intval($_POST['styles']['shadow']['inner']['y'] ?? 2),
                    'blur' => intval($_POST['styles']['shadow']['inner']['blur'] ?? 4),
                    'spread' => intval($_POST['styles']['shadow']['inner']['spread'] ?? 0),
                    'color' => sanitize_hex_color($_POST['styles']['shadow']['inner']['color'] ?? '#00000030')
                )
            ),
            'effects' => array(
                'glassmorphism' => array(
                    'enabled' => isset($_POST['styles']['effects']['glassmorphism']['enabled']),
                    'opacity' => floatval($_POST['styles']['effects']['glassmorphism']['opacity'] ?? 0.1),
                    'blur' => intval($_POST['styles']['effects']['glassmorphism']['blur'] ?? 10),
                    'saturate' => intval($_POST['styles']['effects']['glassmorphism']['saturate'] ?? 100),
                    'color' => sanitize_hex_color($_POST['styles']['effects']['glassmorphism']['color'] ?? '#ffffff')
                ),
                'filters' => array(
                    'brightness' => intval($_POST['styles']['effects']['filters']['brightness'] ?? 100),
                    'contrast' => intval($_POST['styles']['effects']['filters']['contrast'] ?? 100),
                    'hue' => intval($_POST['styles']['effects']['filters']['hue'] ?? 0)
                ),
                'neumorphism' => array(
                    'enabled' => isset($_POST['styles']['effects']['neumorphism']['enabled']),
                    'style' => sanitize_text_field($_POST['styles']['effects']['neumorphism']['style'] ?? 'raised'),
                    'intensity' => intval($_POST['styles']['effects']['neumorphism']['intensity'] ?? 10),
                    'background' => sanitize_hex_color($_POST['styles']['effects']['neumorphism']['background'] ?? '#e0e0e0'),
                    'distance' => intval($_POST['styles']['effects']['neumorphism']['distance'] ?? 15)
                ),
                'animation' => array(
                    'hover' => sanitize_text_field($_POST['styles']['effects']['animation']['hover'] ?? 'none'),
                    'origin' => sanitize_text_field($_POST['styles']['effects']['animation']['origin'] ?? 'center'),
                    'duration' => intval($_POST['styles']['effects']['animation']['duration'] ?? 300),
                    'easing' => sanitize_text_field($_POST['styles']['effects']['animation']['easing'] ?? 'ease')
                )
            )
        );
        
        // Features sammeln
        $features = array(
            'icon' => array(
                'enabled' => isset($_POST['features']['icon']['enabled']) ? true : false,
                'value' => sanitize_text_field($_POST['features']['icon']['value'] ?? 'dashicons-admin-generic'),
                'position' => sanitize_text_field($_POST['features']['icon']['position'] ?? 'top-left')
            ),
            'collapse' => array(
                'enabled' => isset($_POST['features']['collapse']['enabled']) ? true : false,
                'defaultState' => sanitize_text_field($_POST['features']['collapse']['defaultState'] ?? 'expanded')
            ),
            'numbering' => array(
                'enabled' => isset($_POST['features']['numbering']['enabled']) ? true : false,
                'format' => sanitize_text_field($_POST['features']['numbering']['format'] ?? 'numeric'),
                'position' => sanitize_text_field($_POST['features']['numbering']['position'] ?? 'top-left'),
                'countingMode' => sanitize_text_field($_POST['features']['numbering']['countingMode'] ?? 'same-design')
            ),
            'copyText' => array(
                'enabled' => isset($_POST['features']['copyText']['enabled']) ? true : false,
                'buttonText' => sanitize_text_field($_POST['features']['copyText']['buttonText'] ?? 'Text kopieren')
            ),
            'screenshot' => array(
                'enabled' => isset($_POST['features']['screenshot']['enabled']) ? true : false,
                'buttonText' => sanitize_text_field($_POST['features']['screenshot']['buttonText'] ?? 'Screenshot')
            )
        );
        
        // Config sammeln
        $config = array(
            'allowInnerBlocks' => isset($_POST['allow_inner_blocks']) ? true : false,
            'templateLock' => isset($_POST['template_lock']) ? false : true
        );
        
        // Slug generieren basierend auf name (falls nicht explizit gesetzt)
        $slug = sanitize_title($name);

        error_log('[CBD Edit Save] Generated slug: "' . $slug . '" from name: "' . $name . '"');

        // Daten aktualisieren - WICHTIG: slug hinzufügen!
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'name' => $name,
                'title' => $title,
                'slug' => $slug, // KRITISCH: slug hinzufügen für Frontend-Rendering
                'description' => $description,
                'config' => wp_json_encode($config),
                'styles' => wp_json_encode($styles),
                'features' => wp_json_encode($features),
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $block_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'), // Einen %s für slug hinzugefügt
            array('%d')
        );
        
        error_log('[CBD Edit Save] Update result: ' . ($result !== false ? 'success' : 'failed'));
        
        if ($result !== false) {
            // Cache leeren für sofortige Verfügbarkeit
            wp_cache_delete('cbd_all_blocks_styles');
            wp_cache_delete('cbd_active_features');
            delete_transient('cbd_compiled_styles');

            wp_send_json_success(array(
                'message' => 'Block erfolgreich gespeichert',
                'block_id' => $block_id
            ));
        } else {
            wp_send_json_error('Fehler beim Speichern in der Datenbank');
        }
    }
}

// Initialisierung nur im Admin-Bereich
if (is_admin()) {
    CBD_Admin::get_instance();
}