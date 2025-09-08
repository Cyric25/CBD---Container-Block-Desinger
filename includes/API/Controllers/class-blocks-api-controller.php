<?php
/**
 * Container Block Designer - Blocks API Controller
 * 
 * @package ContainerBlockDesigner\API\Controllers
 * @since 2.6.0
 */

namespace ContainerBlockDesigner\API\Controllers;

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blocks API Controller
 * Handles all REST API endpoints for blocks
 */
class BlocksApiController extends \WP_REST_Controller {
    
    /**
     * Namespace for API
     */
    protected $namespace = 'cbd/v1';
    
    /**
     * Resource name
     */
    protected $rest_base = 'blocks';
    
    /**
     * Register the routes
     */
    public function register_routes() {
        // GET /cbd/v1/blocks
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => $this->get_collection_params()
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::CREATABLE)
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));
        
        // GET /cbd/v1/blocks/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(
                    'id' => array(
                        'description' => __('Block ID', 'container-block-designer'),
                        'type' => 'integer',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    )
                )
            ),
            array(
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE)
            ),
            array(
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check')
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));
        
        // POST /cbd/v1/blocks/{id}/duplicate
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array($this, 'duplicate_item'),
            'permission_callback' => array($this, 'create_item_permissions_check'),
            'args' => array(
                'id' => array(
                    'description' => __('Block ID to duplicate', 'container-block-designer'),
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));
        
        // POST /cbd/v1/blocks/{id}/toggle-status
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/toggle-status', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array($this, 'toggle_status'),
            'permission_callback' => array($this, 'update_item_permissions_check'),
            'args' => array(
                'id' => array(
                    'description' => __('Block ID', 'container-block-designer'),
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'status' => array(
                    'description' => __('New status', 'container-block-designer'),
                    'type' => 'string',
                    'enum' => array('active', 'inactive'),
                    'required' => false
                )
            )
        ));
    }
    
    /**
     * Get a collection of blocks
     */
    public function get_items($request) {
        $args = array(
            'status' => $request->get_param('status'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'limit' => $request->get_param('per_page'),
            'offset' => $request->get_param('offset')
        );
        
        // Handle search
        $search = $request->get_param('search');
        if (!empty($search)) {
            $blocks = \CBD_Database::search_blocks($search);
        } else {
            $blocks = \CBD_Database::get_blocks($args);
        }
        
        $data = array();
        foreach ($blocks as $block) {
            $data[] = $this->prepare_item_for_response($block, $request);
        }
        
        $response = rest_ensure_response($data);
        
        // Add pagination headers
        $total_blocks = \CBD_Database::get_block_count($args['status']);
        $max_pages = ceil($total_blocks / (int)$request->get_param('per_page'));
        
        $response->header('X-WP-Total', (int)$total_blocks);
        $response->header('X-WP-TotalPages', (int)$max_pages);
        
        return $response;
    }
    
    /**
     * Get a single block
     */
    public function get_item($request) {
        $id = (int) $request->get_param('id');
        $block = \CBD_Database::get_block($id);
        
        if (!$block) {
            return new \WP_Error('cbd_block_not_found', __('Block nicht gefunden.', 'container-block-designer'), array('status' => 404));
        }
        
        return $this->prepare_item_for_response($block, $request);
    }
    
    /**
     * Create a new block
     */
    public function create_item($request) {
        $prepared_block = $this->prepare_item_for_database($request);
        
        if (is_wp_error($prepared_block)) {
            return $prepared_block;
        }
        
        $block_id = \CBD_Database::save_block($prepared_block);
        
        if (!$block_id) {
            return new \WP_Error('cbd_block_create_failed', __('Fehler beim Erstellen des Blocks.', 'container-block-designer'), array('status' => 500));
        }
        
        $block = \CBD_Database::get_block($block_id);
        $response = $this->prepare_item_for_response($block, $request);
        $response->set_status(201);
        
        return $response;
    }
    
    /**
     * Update a block
     */
    public function update_item($request) {
        $id = (int) $request->get_param('id');
        $existing_block = \CBD_Database::get_block($id);
        
        if (!$existing_block) {
            return new \WP_Error('cbd_block_not_found', __('Block nicht gefunden.', 'container-block-designer'), array('status' => 404));
        }
        
        $prepared_block = $this->prepare_item_for_database($request);
        
        if (is_wp_error($prepared_block)) {
            return $prepared_block;
        }
        
        $result = \CBD_Database::save_block($prepared_block, $id);
        
        if (!$result) {
            return new \WP_Error('cbd_block_update_failed', __('Fehler beim Aktualisieren des Blocks.', 'container-block-designer'), array('status' => 500));
        }
        
        $block = \CBD_Database::get_block($id);
        return $this->prepare_item_for_response($block, $request);
    }
    
    /**
     * Delete a block
     */
    public function delete_item($request) {
        $id = (int) $request->get_param('id');
        $block = \CBD_Database::get_block($id);
        
        if (!$block) {
            return new \WP_Error('cbd_block_not_found', __('Block nicht gefunden.', 'container-block-designer'), array('status' => 404));
        }
        
        $result = \CBD_Database::delete_block($id);
        
        if (!$result) {
            return new \WP_Error('cbd_block_delete_failed', __('Fehler beim Löschen des Blocks.', 'container-block-designer'), array('status' => 500));
        }
        
        $response = $this->prepare_item_for_response($block, $request);
        $response->set_data(array(
            'deleted' => true,
            'previous' => $response->get_data()
        ));
        
        return $response;
    }
    
    /**
     * Duplicate a block
     */
    public function duplicate_item($request) {
        $id = (int) $request->get_param('id');
        $existing_block = \CBD_Database::get_block($id);
        
        if (!$existing_block) {
            return new \WP_Error('cbd_block_not_found', __('Block nicht gefunden.', 'container-block-designer'), array('status' => 404));
        }
        
        $new_block_id = \CBD_Database::duplicate_block($id);
        
        if (!$new_block_id) {
            return new \WP_Error('cbd_block_duplicate_failed', __('Fehler beim Duplizieren des Blocks.', 'container-block-designer'), array('status' => 500));
        }
        
        $new_block = \CBD_Database::get_block($new_block_id);
        $response = $this->prepare_item_for_response($new_block, $request);
        $response->set_status(201);
        
        return $response;
    }
    
    /**
     * Toggle block status
     */
    public function toggle_status($request) {
        $id = (int) $request->get_param('id');
        $new_status = $request->get_param('status');
        
        $block = \CBD_Database::get_block($id);
        
        if (!$block) {
            return new \WP_Error('cbd_block_not_found', __('Block nicht gefunden.', 'container-block-designer'), array('status' => 404));
        }
        
        // If no status provided, toggle current status
        if (empty($new_status)) {
            $new_status = $block['status'] === 'active' ? 'inactive' : 'active';
        }
        
        $result = \CBD_Database::update_block_status($id, $new_status);
        
        if ($result === false) {
            return new \WP_Error('cbd_status_update_failed', __('Fehler beim Aktualisieren des Status.', 'container-block-designer'), array('status' => 500));
        }
        
        $updated_block = \CBD_Database::get_block($id);
        return $this->prepare_item_for_response($updated_block, $request);
    }
    
    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request) {
        $data = array(
            'id' => (int) $item['id'],
            'name' => $item['name'],
            'title' => $item['title'],
            'description' => $item['description'],
            'config' => $item['config'],
            'styles' => $item['styles'],
            'features' => $item['features'],
            'status' => $item['status'],
            'created_at' => mysql2date('c', $item['created_at']),
            'updated_at' => mysql2date('c', $item['updated_at'])
        );
        
        $response = rest_ensure_response($data);
        
        // Add links
        $response->add_links($this->prepare_links($item));
        
        return $response;
    }
    
    /**
     * Prepare item for database
     */
    protected function prepare_item_for_database($request) {
        $prepared_block = array();
        
        // Required fields
        if ($request->has_param('name')) {
            $name = sanitize_text_field($request->get_param('name'));
            if (empty($name)) {
                return new \WP_Error('cbd_invalid_name', __('Name ist erforderlich.', 'container-block-designer'), array('status' => 400));
            }
            $prepared_block['name'] = $name;
        }
        
        if ($request->has_param('title')) {
            $title = sanitize_text_field($request->get_param('title'));
            if (empty($title)) {
                return new \WP_Error('cbd_invalid_title', __('Titel ist erforderlich.', 'container-block-designer'), array('status' => 400));
            }
            $prepared_block['title'] = $title;
        }
        
        // Optional fields
        if ($request->has_param('description')) {
            $prepared_block['description'] = sanitize_textarea_field($request->get_param('description'));
        }
        
        // WICHTIG: Korrigierte ternäre Operatoren mit Klammern
        if ($request->has_param('config')) {
            $config = $request->get_param('config');
            $prepared_block['config'] = is_array($config) ? $config : (json_decode($config, true) ?: array());
        }
        
        if ($request->has_param('styles')) {
            $styles = $request->get_param('styles');
            $prepared_block['styles'] = is_array($styles) ? $styles : (json_decode($styles, true) ?: array());
        }
        
        if ($request->has_param('features')) {
            $features = $request->get_param('features');
            $prepared_block['features'] = is_array($features) ? $features : (json_decode($features, true) ?: array());
        }
        
        if ($request->has_param('status')) {
            $status = sanitize_text_field($request->get_param('status'));
            if (!in_array($status, array('active', 'inactive'))) {
                return new \WP_Error('cbd_invalid_status', __('Ungültiger Status.', 'container-block-designer'), array('status' => 400));
            }
            $prepared_block['status'] = $status;
        }
        
        return $prepared_block;
    }
    
    /**
     * Prepare links for response
     */
    protected function prepare_links($item) {
        $base = sprintf('%s/%s', $this->namespace, $this->rest_base);
        
        $links = array(
            'self' => array(
                'href' => rest_url(trailingslashit($base) . $item['id'])
            ),
            'collection' => array(
                'href' => rest_url($base)
            )
        );
        
        return $links;
    }
    
    /**
     * Permission checks
     */
    public function get_items_permissions_check($request) {
        return true; // Public endpoint
    }
    
    public function get_item_permissions_check($request) {
        return true; // Public endpoint
    }
    
    public function create_item_permissions_check($request) {
        return current_user_can('manage_options');
    }
    
    public function update_item_permissions_check($request) {
        return current_user_can('manage_options');
    }
    
    public function delete_item_permissions_check($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        return array(
            'status' => array(
                'description' => __('Block Status', 'container-block-designer'),
                'type' => 'string',
                'enum' => array('active', 'inactive'),
                'default' => 'active'
            ),
            'search' => array(
                'description' => __('Suche in Name, Titel und Beschreibung', 'container-block-designer'),
                'type' => 'string'
            ),
            'orderby' => array(
                'description' => __('Sortierung', 'container-block-designer'),
                'type' => 'string',
                'enum' => array('id', 'name', 'title', 'created_at', 'updated_at'),
                'default' => 'name'
            ),
            'order' => array(
                'description' => __('Sortierreihenfolge', 'container-block-designer'),
                'type' => 'string',
                'enum' => array('ASC', 'DESC'),
                'default' => 'ASC'
            ),
            'per_page' => array(
                'description' => __('Anzahl pro Seite', 'container-block-designer'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ),
            'offset' => array(
                'description' => __('Offset für Paginierung', 'container-block-designer'),
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0
            )
        );
    }
    
    /**
     * Get item schema
     */
    public function get_item_schema() {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'container-block',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Block ID', 'container-block-designer'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                ),
                'name' => array(
                    'description' => __('Block Name', 'container-block-designer'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'required' => true
                ),
                'title' => array(
                    'description' => __('Block Titel', 'container-block-designer'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'required' => true
                ),
                'description' => array(
                    'description' => __('Block Beschreibung', 'container-block-designer'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                ),
                'config' => array(
                    'description' => __('Block Konfiguration', 'container-block-designer'),
                    'type' => 'object',
                    'context' => array('view', 'edit')
                ),
                'styles' => array(
                    'description' => __('Block Styles', 'container-block-designer'),
                    'type' => 'object',
                    'context' => array('view', 'edit')
                ),
                'features' => array(
                    'description' => __('Block Features', 'container-block-designer'),
                    'type' => 'object',
                    'context' => array('view', 'edit')
                ),
                'status' => array(
                    'description' => __('Block Status', 'container-block-designer'),
                    'type' => 'string',
                    'enum' => array('active', 'inactive'),
                    'context' => array('view', 'edit')
                ),
                'created_at' => array(
                    'description' => __('Erstellungsdatum', 'container-block-designer'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                ),
                'updated_at' => array(
                    'description' => __('Aktualisierungsdatum', 'container-block-designer'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                )
            )
        );
        
        return $this->add_additional_fields_schema($schema);
    }
}