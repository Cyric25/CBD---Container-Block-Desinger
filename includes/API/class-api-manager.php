<?php
/**
 * Container Block Designer - API Manager
 * Central manager for all REST API functionality
 * 
 * @package ContainerBlockDesigner\API
 * @since 2.6.0
 */

namespace ContainerBlockDesigner\API;

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Manager Class
 * Manages all REST API controllers and endpoints
 */
class APIManager {
    
    /**
     * API Controllers
     */
    private $controllers = array();
    
    /**
     * Initialize API Manager
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'register_fields'));
    }
    
    /**
     * Register all API routes
     */
    public function register_routes() {
        $this->load_controllers();
        
        foreach ($this->controllers as $controller) {
            $controller->register_routes();
        }
    }
    
    /**
     * Load API controllers
     */
    private function load_controllers() {
        // Load Blocks API Controller
        require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-blocks-api-controller.php';
        $this->controllers[] = new Controllers\BlocksApiController();
        
        // Add more controllers here as needed
        // require_once CBD_PLUGIN_DIR . 'includes/API/Controllers/class-settings-api-controller.php';
        // $this->controllers[] = new Controllers\SettingsApiController();
    }
    
    /**
     * Register custom fields for API responses
     */
    public function register_fields() {
        // Add block usage count
        register_rest_field('cbd-block', 'usage_count', array(
            'get_callback' => array($this, 'get_block_usage_count'),
            'schema' => array(
                'description' => __('Anzahl der Verwendungen dieses Blocks', 'container-block-designer'),
                'type' => 'integer',
                'context' => array('view')
            )
        ));
        
        // Add block preview URL
        register_rest_field('cbd-block', 'preview_url', array(
            'get_callback' => array($this, 'get_block_preview_url'),
            'schema' => array(
                'description' => __('URL zur Block-Vorschau', 'container-block-designer'),
                'type' => 'string',
                'format' => 'uri',
                'context' => array('view')
            )
        ));
    }
    
    /**
     * Get block usage count
     */
    public function get_block_usage_count($block) {
        global $wpdb;
        
        // This would require tracking block usage
        // For now, return a placeholder
        return 0;
    }
    
    /**
     * Get block preview URL
     */
    public function get_block_preview_url($block) {
        return add_query_arg(array(
            'cbd_preview' => 1,
            'block_id' => $block['id']
        ), home_url());
    }
    
    /**
     * Add CORS headers for API requests
     */
    public function add_cors_headers() {
        // Only add CORS headers if needed
        if (!apply_filters('cbd_api_add_cors_headers', false)) {
            return;
        }
        
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', array($this, 'send_cors_headers'));
        }, 15);
    }
    
    /**
     * Send CORS headers
     */
    public function send_cors_headers($value) {
        $origin = get_http_origin();
        
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Credentials: true');
        }
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            exit;
        }
        
        return $value;
    }
    
    /**
     * Register API authentication methods
     */
    public function register_auth_methods() {
        // Add custom authentication if needed
        add_filter('determine_current_user', array($this, 'determine_current_user'), 20);
    }
    
    /**
     * Determine current user for API requests
     */
    public function determine_current_user($user_id) {
        // Custom authentication logic could go here
        // For now, use WordPress default
        return $user_id;
    }
    
    /**
     * Add rate limiting
     */
    public function add_rate_limiting() {
        add_filter('rest_request_before_callbacks', array($this, 'rate_limit_check'), 10, 3);
    }
    
    /**
     * Check rate limits
     */
    public function rate_limit_check($response, $handler, $request) {
        // Simple rate limiting based on IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'cbd_api_rate_limit_' . md5($ip);
        $requests = get_transient($key) ?: 0;
        
        $limit = apply_filters('cbd_api_rate_limit', 100); // 100 requests per hour
        
        if ($requests >= $limit) {
            return new \WP_Error(
                'cbd_rate_limit_exceeded',
                __('Rate limit exceeded. Try again later.', 'container-block-designer'),
                array('status' => 429)
            );
        }
        
        // Increment counter
        set_transient($key, $requests + 1, HOUR_IN_SECONDS);
        
        return $response;
    }
    
    /**
     * Get API health status
     */
    public function get_api_health() {
        return array(
            'status' => 'healthy',
            'version' => CBD_VERSION,
            'endpoints' => $this->get_registered_endpoints(),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Get all registered endpoints
     */
    private function get_registered_endpoints() {
        $endpoints = array();
        
        foreach ($this->controllers as $controller) {
            if (method_exists($controller, 'get_registered_routes')) {
                $endpoints = array_merge($endpoints, $controller->get_registered_routes());
            }
        }
        
        return $endpoints;
    }
    
    /**
     * Validate API request
     */
    public function validate_request($request) {
        // Validate request format
        if (!$request instanceof \WP_REST_Request) {
            return new \WP_Error('cbd_invalid_request', __('Ungültige Anfrage.', 'container-block-designer'));
        }
        
        // Validate content type for POST/PUT requests
        $method = $request->get_method();
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $content_type = $request->get_content_type();
            if (!in_array($content_type['value'], array('application/json', 'application/x-www-form-urlencoded'))) {
                return new \WP_Error('cbd_invalid_content_type', __('Ungültiger Content-Type.', 'container-block-designer'));
            }
        }
        
        return true;
    }
    
    /**
     * Log API requests for debugging
     */
    public function log_api_request($request) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'method' => $request->get_method(),
            'route' => $request->get_route(),
            'params' => $request->get_params(),
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        );
        
        error_log('CBD API Request: ' . wp_json_encode($log_data));
    }
}