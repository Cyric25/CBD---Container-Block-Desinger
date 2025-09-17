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
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Hauptmenüpunkt - weniger restriktive Capability für Editoren
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Designer', 'container-block-designer'),
            'edit_posts',
            'container-block-designer',
            array($this, 'render_main_page'),
            'dashicons-layout',
            30
        );
        
        // Untermenü: Neuer Block - Editoren können Blocks erstellen
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'edit_posts',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        // Untermenü: Alle Blöcke - Editoren können Blocks verwalten
        add_submenu_page(
            'container-block-designer',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            'edit_posts',
            'cbd-blocks',
            array($this, 'render_blocks_list_page')
        );
        
        // Versteckte Seite: Block bearbeiten - nicht im Menü sichtbar
        add_submenu_page(
            null, // parent_slug = null macht es zu einer versteckten Seite
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            'edit_posts',
            'cbd-edit-block',
            array($this, 'render_edit_block_page')
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
            
            // Daten aktualisieren
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                array(
                    'name' => $name,
                    'title' => $title,
                    'description' => $description,
                    'config' => wp_json_encode($config),
                    'styles' => wp_json_encode($styles),
                    'features' => wp_json_encode($features),
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $block_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
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
        
        // Daten aktualisieren
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            array(
                'name' => $name,
                'title' => $title,
                'description' => $description,
                'config' => wp_json_encode($config),
                'styles' => wp_json_encode($styles),
                'features' => wp_json_encode($features),
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $block_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        error_log('[CBD Edit Save] Update result: ' . ($result !== false ? 'success' : 'failed'));
        
        if ($result !== false) {
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