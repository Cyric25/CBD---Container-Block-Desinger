<?php
/**
 * Container Block Designer - Service Container
 * Simple dependency injection container
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Container Class
 * Provides dependency injection and service management
 */
class CBD_Service_Container {
    
    /**
     * Container instance
     */
    private static $instance = null;
    
    /**
     * Services registry
     */
    private $services = array();
    
    /**
     * Service instances
     */
    private $instances = array();
    
    /**
     * Service configurations
     */
    private $configs = array();
    
    /**
     * Get container instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->register_core_services();
    }
    
    /**
     * Register a service
     * 
     * @param string $name Service name
     * @param callable|string $factory Factory function or class name
     * @param array $config Service configuration
     * @param bool $singleton Whether service should be singleton
     */
    public function register($name, $factory, $config = array(), $singleton = true) {
        $this->services[$name] = array(
            'factory' => $factory,
            'singleton' => $singleton,
            'created' => false
        );
        $this->configs[$name] = $config;
    }
    
    /**
     * Get service
     * 
     * @param string $name Service name
     * @return mixed Service instance
     * @throws Exception If service not found
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new Exception("Service '{$name}' not found in container");
        }
        
        $service = $this->services[$name];
        
        // Return existing instance for singletons
        if ($service['singleton'] && isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        // Create new instance
        $instance = $this->create_instance($name);
        
        // Store singleton instances
        if ($service['singleton']) {
            $this->instances[$name] = $instance;
            $this->services[$name]['created'] = true;
        }
        
        return $instance;
    }
    
    /**
     * Create service instance
     * 
     * @param string $name Service name
     * @return mixed Service instance
     * @throws Exception If creation fails
     */
    private function create_instance($name) {
        $service = $this->services[$name];
        $factory = $service['factory'];
        $config = isset($this->configs[$name]) ? $this->configs[$name] : array();
        
        try {
            if (is_callable($factory)) {
                // Factory function
                return call_user_func($factory, $this, $config);
            } elseif (is_string($factory) && class_exists($factory)) {
                // Class name - instantiate with config
                if (empty($config)) {
                    return new $factory();
                } else {
                    return new $factory($config);
                }
            } else {
                throw new Exception("Invalid factory for service '{$name}'");
            }
        } catch (Exception $e) {
            throw new Exception("Failed to create service '{$name}': " . $e->getMessage());
        }
    }
    
    /**
     * Check if service exists
     * 
     * @param string $name Service name
     * @return bool
     */
    public function has($name) {
        return isset($this->services[$name]);
    }
    
    /**
     * Get service configuration
     * 
     * @param string $name Service name
     * @return array Service configuration
     */
    public function get_config($name) {
        return isset($this->configs[$name]) ? $this->configs[$name] : array();
    }
    
    /**
     * Register core plugin services
     */
    private function register_core_services() {
        // Database service - Uses existing singleton
        $this->register('database', function($container, $config) {
            // Database class doesn't use singleton pattern, safe to instantiate
            return new CBD_Database();
        });
        
        // Schema manager - Static class, create wrapper
        $this->register('schema_manager', function($container, $config) {
            return new class() {
                public function create_tables() {
                    return CBD_Schema_Manager::create_tables();
                }
                public function run_migrations() {
                    return CBD_Schema_Manager::run_migrations();
                }
            };
        });
        
        // Style loader - Use existing singleton
        $this->register('style_loader', function($container, $config) {
            return CBD_Style_Loader::get_instance();
        });
        
        // Block registration - Use existing singleton  
        $this->register('block_registration', function($container, $config) {
            return CBD_Block_Registration::get_instance();
        });
        
        // Frontend renderer - Create new instance
        $this->register('frontend_renderer', function($container, $config) {
            return new CBD_Unified_Frontend_Renderer();
        });
        
        // AJAX handler - Create new instance
        $this->register('ajax_handler', function($container, $config) {
            return new CBD_Ajax_Handler();
        });
        
        // Admin router - Create new instance
        $this->register('admin_router', function($container, $config) {
            return new \ContainerBlockDesigner\Admin\AdminRouter();
        });
        
        // API manager - Create new instance
        // WICHTIG: Datei muss zuerst geladen werden!
        $this->register('api_manager', function($container, $config) {
            // Stelle sicher, dass die API Manager Datei geladen ist
            if (!class_exists('\ContainerBlockDesigner\API\APIManager')) {
                require_once CBD_PLUGIN_DIR . 'includes/API/class-api-manager.php';
            }
            return new \ContainerBlockDesigner\API\APIManager();
        });
        
        // Consolidated frontend - Use existing singleton
        $this->register('consolidated_frontend', function($container, $config) {
            return CBD_Consolidated_Frontend::get_instance();
        });
        
        // Admin - Use existing singleton
        $this->register('admin', function($container, $config) {
            return CBD_Admin::get_instance();
        });
    }
    
    /**
     * Register service by class name with automatic resolution
     * 
     * @param string $name Service name
     * @param string $class_name Class name
     * @param array $config Configuration
     * @param bool $singleton Singleton flag
     */
    public function register_class($name, $class_name, $config = array(), $singleton = true) {
        $this->register($name, $class_name, $config, $singleton);
    }
    
    /**
     * Register service by factory function
     * 
     * @param string $name Service name
     * @param callable $factory Factory function
     * @param array $config Configuration
     * @param bool $singleton Singleton flag
     */
    public function register_factory($name, $factory, $config = array(), $singleton = true) {
        if (!is_callable($factory)) {
            throw new Exception("Factory must be callable for service '{$name}'");
        }
        $this->register($name, $factory, $config, $singleton);
    }
}