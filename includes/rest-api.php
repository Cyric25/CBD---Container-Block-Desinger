<?php
/**
 * Container Block Designer - REST API
 * Version: 2.3.0
 * 
 * Diese Datei stellt die REST API Endpunkte für den Block Editor bereit
 * 
 * @package ContainerBlockDesigner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes
 */
add_action('rest_api_init', 'cbd_register_rest_routes');

function cbd_register_rest_routes() {
    // Get all blocks
    register_rest_route('cbd/v1', '/blocks', array(
        'methods' => 'GET',
        'callback' => 'cbd_rest_get_blocks',
        'permission_callback' => '__return_true', // Public endpoint for reading
        'args' => array(
            'status' => array(
                'default' => 'active',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
    
    // Get single block
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'cbd_rest_get_block',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    
    // Create block
    register_rest_route('cbd/v1', '/blocks', array(
        'methods' => 'POST',
        'callback' => 'cbd_rest_create_block',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ));
    
    // Update block
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'cbd_rest_update_block',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    
    // Delete block
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'cbd_rest_delete_block',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    
    // Toggle block status
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)/toggle-status', array(
        'methods' => 'POST',
        'callback' => 'cbd_rest_toggle_status',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    
    // Duplicate block
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)/duplicate', array(
        'methods' => 'POST',
        'callback' => 'cbd_rest_duplicate_block',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    
    // Save block features
    register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)/features', array(
        'methods' => 'POST',
        'callback' => 'cbd_rest_save_features',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}

/**
 * REST: Get all blocks
 */
function cbd_rest_get_blocks($request) {
    global $wpdb;
    
    $status = $request->get_param('status');
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Get blocks', array('status' => $status));
    }
    
    if ($status && $status !== 'all') {
        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE status = %s ORDER BY name",
            $status
        ));
    } else {
        $blocks = $wpdb->get_results(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY name"
        );
    }
    
    // Parse JSON fields
    foreach ($blocks as &$block) {
        $block->config = json_decode($block->config, true);
        $block->features = json_decode($block->features, true);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $blocks
    ), 200);
}

/**
 * REST: Get single block
 */
function cbd_rest_get_block($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Get block', array('id' => $id));
    }
    
    $block = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ));
    
    if (!$block) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Parse JSON fields
    $block->config = json_decode($block->config, true);
    $block->features = json_decode($block->features, true);
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $block
    ), 200);
}

/**
 * REST: Create block
 */
function cbd_rest_create_block($request) {
    global $wpdb;
    
    $params = $request->get_json_params();
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Create block', $params);
    }
    
    // Validate required fields
    if (empty($params['name']) || empty($params['slug'])) {
        return new WP_Error('missing_fields', __('Name und Slug sind erforderlich', 'container-block-designer'), array('status' => 400));
    }
    
    // Check if slug exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s",
        $params['slug']
    ));
    
    if ($exists > 0) {
        return new WP_Error('slug_exists', __('Ein Block mit diesem Slug existiert bereits', 'container-block-designer'), array('status' => 400));
    }
    
    // Prepare data
    $data = array(
        'name' => sanitize_text_field($params['name']),
        'slug' => sanitize_title($params['slug']),
        'description' => sanitize_textarea_field($params['description'] ?? ''),
        'config' => json_encode($params['config'] ?? array()),
        'features' => json_encode($params['features'] ?? array()),
        'status' => sanitize_text_field($params['status'] ?? 'active'),
        'created' => current_time('mysql'),
        'modified' => current_time('mysql')
    );
    
    // Insert block
    $result = $wpdb->insert(CBD_TABLE_BLOCKS, $data);
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Erstellen des Blocks', 'container-block-designer'), array('status' => 500));
    }
    
    $block_id = $wpdb->insert_id;
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'id' => $block_id,
            'message' => __('Block erfolgreich erstellt', 'container-block-designer')
        )
    ), 201);
}

/**
 * REST: Update block
 */
function cbd_rest_update_block($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    $params = $request->get_json_params();
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Update block', array_merge(array('id' => $id), $params));
    }
    
    // Check if block exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ));
    
    if (!$exists) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Prepare update data
    $data = array();
    
    if (isset($params['name'])) {
        $data['name'] = sanitize_text_field($params['name']);
    }
    
    if (isset($params['slug'])) {
        // Check if new slug already exists (excluding current block)
        $slug_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s AND id != %d",
            $params['slug'], $id
        ));
        
        if ($slug_exists > 0) {
            return new WP_Error('slug_exists', __('Ein anderer Block mit diesem Slug existiert bereits', 'container-block-designer'), array('status' => 400));
        }
        
        $data['slug'] = sanitize_title($params['slug']);
    }
    
    if (isset($params['description'])) {
        $data['description'] = sanitize_textarea_field($params['description']);
    }
    
    if (isset($params['config'])) {
        $data['config'] = json_encode($params['config']);
    }
    
    if (isset($params['features'])) {
        $data['features'] = json_encode($params['features']);
    }
    
    if (isset($params['status'])) {
        $data['status'] = sanitize_text_field($params['status']);
    }
    
    $data['modified'] = current_time('mysql');
    
    // Update block
    $result = $wpdb->update(
        CBD_TABLE_BLOCKS,
        $data,
        array('id' => $id)
    );
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Aktualisieren des Blocks', 'container-block-designer'), array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'message' => __('Block erfolgreich aktualisiert', 'container-block-designer')
        )
    ), 200);
}

/**
 * REST: Delete block
 */
function cbd_rest_delete_block($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Delete block', array('id' => $id));
    }
    
    // Check if block exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ));
    
    if (!$exists) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Delete block
    $result = $wpdb->delete(
        CBD_TABLE_BLOCKS,
        array('id' => $id)
    );
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Löschen des Blocks', 'container-block-designer'), array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'message' => __('Block erfolgreich gelöscht', 'container-block-designer')
        )
    ), 200);
}

/**
 * REST: Toggle block status
 */
function cbd_rest_toggle_status($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Toggle status', array('id' => $id));
    }
    
    // Get current status
    $current_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ));
    
    if (!$current_status) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Toggle status
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
    $result = $wpdb->update(
        CBD_TABLE_BLOCKS,
        array(
            'status' => $new_status,
            'modified' => current_time('mysql')
        ),
        array('id' => $id)
    );
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Aktualisieren des Status', 'container-block-designer'), array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'new_status' => $new_status,
            'message' => sprintf(__('Block ist jetzt %s', 'container-block-designer'), $new_status)
        )
    ), 200);
}

/**
 * REST: Duplicate block
 */
function cbd_rest_duplicate_block($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Duplicate block', array('id' => $id));
    }
    
    // Get original block
    $original = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ), ARRAY_A);
    
    if (!$original) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Create unique slug
    $counter = 1;
    $new_slug = $original['slug'] . '-kopie';
    
    while ($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s",
        $new_slug . ($counter > 1 ? '-' . $counter : '')
    )) > 0) {
        $counter++;
    }
    
    if ($counter > 1) {
        $new_slug = $new_slug . '-' . $counter;
    }
    
    // Create duplicate
    unset($original['id']);
    $original['name'] = $original['name'] . ' (Kopie' . ($counter > 1 ? ' ' . $counter : '') . ')';
    $original['slug'] = $new_slug;
    $original['created'] = current_time('mysql');
    $original['modified'] = current_time('mysql');
    
    $result = $wpdb->insert(CBD_TABLE_BLOCKS, $original);
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Duplizieren des Blocks', 'container-block-designer'), array('status' => 500));
    }
    
    $new_id = $wpdb->insert_id;
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'id' => $new_id,
            'message' => __('Block erfolgreich dupliziert', 'container-block-designer')
        )
    ), 201);
}

/**
 * REST: Save block features
 */
function cbd_rest_save_features($request) {
    global $wpdb;
    
    $id = $request->get_param('id');
    $features = $request->get_json_params();
    
    // Log API call
    if (function_exists('cbd_log')) {
        cbd_log('REST API: Save features', array('id' => $id, 'features' => $features));
    }
    
    // Check if block exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $id
    ));
    
    if (!$exists) {
        return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
    }
    
    // Update features
    $result = $wpdb->update(
        CBD_TABLE_BLOCKS,
        array(
            'features' => json_encode($features),
            'modified' => current_time('mysql')
        ),
        array('id' => $id)
    );
    
    if ($result === false) {
        return new WP_Error('db_error', __('Fehler beim Speichern der Features', 'container-block-designer'), array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'message' => __('Features erfolgreich gespeichert', 'container-block-designer')
        )
    ), 200);
}