<?php
/**
 * Container Block Designer - Admin-Bereich
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Klasse
 */
class CBD_Admin {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Singleton-Instanz abrufen
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Hauptmenü
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_main_page'),
            'dashicons-layout',
            30
        );
        
        // Untermenüs
        add_submenu_page(
            'container-block-designer',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Import/Export', 'container-block-designer'),
            __('Import/Export', 'container-block-designer'),
            'manage_options',
            'cbd-import-export',
            array($this, 'render_import_export_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Einstellungen', 'container-block-designer'),
            __('Einstellungen', 'container-block-designer'),
            'manage_options',
            'cbd-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Hauptseite rendern
     */
    public function render_main_page() {
        // Blöcke aus der Datenbank abrufen
        global $wpdb;
        $blocks = $wpdb->get_results(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created DESC",
            ARRAY_A
        );
        
        // Template einbinden
        $this->include_admin_template('main-page', array('blocks' => $blocks));
    }
    
    /**
     * Neue Block-Seite rendern
     */
    public function render_new_block_page() {
        // Prüfen ob Edit-Modus
        $block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
        $block = null;
        
        if ($block_id) {
            global $wpdb;
            $block = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                    $block_id
                ),
                ARRAY_A
            );
            
            if ($block) {
                $block['config'] = json_decode($block['config'], true) ?: array();
                $block['styles'] = json_decode($block['styles'], true) ?: array();
                $block['features'] = json_decode($block['features'], true) ?: array();
            }
        }
        
        // Template einbinden
        $this->include_admin_template('new-block', array('block' => $block));
    }
    
    /**
     * Import/Export-Seite rendern
     */
    public function render_import_export_page() {
        $this->include_admin_template('import-export');
    }
    
    /**
     * Einstellungen-Seite rendern
     */
    public function render_settings_page() {
        $this->include_admin_template('settings');
    }
    
    /**
     * Admin-Template einbinden
     */
    private function include_admin_template($template, $data = array()) {
        // Daten extrahieren
        if (!empty($data)) {
            extract($data);
        }
        
        // Template-Pfad
        $template_file = CBD_PLUGIN_DIR . 'admin/' . $template . '.php';
        
        // Prüfen ob Template existiert
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback: Einfache Ausgabe wenn Template nicht gefunden
            $this->render_template_not_found($template);
        }
    }
    
    /**
     * Template nicht gefunden - Fallback
     */
    private function render_template_not_found($template) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Container Block Designer', 'container-block-designer'); ?></h1>
            
            <?php if ($template === 'main-page'): ?>
                <?php $this->render_blocks_list_fallback(); ?>
            <?php elseif ($template === 'new-block'): ?>
                <?php $this->render_new_block_fallback(); ?>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php printf(__('Template "%s" wurde nicht gefunden.', 'container-block-designer'), esc_html($template)); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Fallback für Blocks-Liste
     */
    private function render_blocks_list_fallback() {
        global $wpdb;
        $blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY created DESC", ARRAY_A);
        ?>
        <div class="cbd-blocks-list">
            <div class="tablenav top">
                <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                    <?php _e('Neuer Block', 'container-block-designer'); ?>
                </a>
            </div>
            
            <?php if (empty($blocks)): ?>
                <div class="cbd-no-blocks">
                    <p><?php _e('Keine Blöcke gefunden.', 'container-block-designer'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=cbd-new-block'); ?>" class="button button-primary">
                        <?php _e('Ersten Block erstellen', 'container-block-designer'); ?>
                    </a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'container-block-designer'); ?></th>
                            <th><?php _e('Beschreibung', 'container-block-designer'); ?></th>
                            <th><?php _e('Status', 'container-block-designer'); ?></th>
                            <th><?php _e('Erstellt', 'container-block-designer'); ?></th>
                            <th><?php _e('Aktionen', 'container-block-designer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocks as $block): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($block['title'] ?? $block['name']); ?></strong>
                                </td>
                                <td><?php echo esc_html($block['description'] ?? ''); ?></td>
                                <td>
                                    <span class="cbd-status cbd-status-<?php echo esc_attr($block['status']); ?>">
                                        <?php echo esc_html($block['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($block['created']); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cbd-new-block&block_id=' . $block['id']); ?>" 
                                       class="button button-small">
                                        <?php _e('Bearbeiten', 'container-block-designer'); ?>
                                    </a>
                                    <button class="button button-small cbd-delete-block" 
                                            data-block-id="<?php echo esc_attr($block['id']); ?>">
                                        <?php _e('Löschen', 'container-block-designer'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Fallback für Neuer Block
     */
    private function render_new_block_fallback() {
        $block_id = isset($_GET['block_id']) ? intval($_GET['block_id']) : 0;
        $block = null;
        
        if ($block_id) {
            global $wpdb;
            $block = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d", $block_id),
                ARRAY_A
            );
        }
        ?>
        <div class="cbd-block-form">
            <form method="post" id="cbd-block-form">
                <?php wp_nonce_field('cbd-save-block', 'cbd_nonce'); ?>
                <input type="hidden" name="block_id" value="<?php echo esc_attr($block_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="block-name"><?php _e('Block Name', 'container-block-designer'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="block-name" 
                                   name="block_name" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($block['name'] ?? ''); ?>" 
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="block-title"><?php _e('Block Titel', 'container-block-designer'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="block-title" 
                                   name="block_title" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($block['title'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="block-description"><?php _e('Beschreibung', 'container-block-designer'); ?></label>
                        </th>
                        <td>
                            <textarea id="block-description" 
                                      name="block_description" 
                                      class="large-text" 
                                      rows="3"><?php echo esc_textarea($block['description'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $block_id ? __('Block aktualisieren', 'container-block-designer') : __('Block erstellen', 'container-block-designer'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=container-block-designer'); ?>" class="button">
                        <?php _e('Abbrechen', 'container-block-designer'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Admin-Benachrichtigungen
     */
    public function admin_notices() {
        // Aktivierungs-Nachricht
        if (isset($_GET['cbd_activated']) && $_GET['cbd_activated'] == '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Container Block Designer wurde erfolgreich aktiviert!', 'container-block-designer'); ?></p>
            </div>
            <?php
        }
        
        // Erfolgs-Nachricht
        if (isset($_GET['cbd_message']) && $_GET['cbd_message'] == 'saved') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Block wurde erfolgreich gespeichert!', 'container-block-designer'); ?></p>
            </div>
            <?php
        }
        
        // Fehler-Nachricht
        if (isset($_GET['cbd_error'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($_GET['cbd_error']); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Plugin-Action-Links hinzufügen
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=container-block-designer') . '">' . __('Einstellungen', 'container-block-designer') . '</a>',
            '<a href="' . admin_url('admin.php?page=cbd-new-block') . '">' . __('Neuer Block', 'container-block-designer') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
}