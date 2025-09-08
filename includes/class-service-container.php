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
            'config' => $config
        );
        
        // Store config separately for easy access
        $this->configs[$name] = $config;
    }
    
    /**
     * Get a service instance
     * 
     * @param string $name Service name
     * @return mixed Service instance
     * @throws Exception If service not found
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new Exception(sprintf('Service "%s" not found', $name));
        }
        
        $service_config = $this->services[$name];
        
        // Return cached instance for singletons
        if ($service_config['singleton'] && isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        $instance = $this->create_instance($name);
        
        // Cache singleton instances
        if ($service_config['singleton']) {
            $this->instances[$name] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Create service instance
     * 
     * @param string $name Service name
     * @return mixed Service instance
     */
    private function create_instance($name) {
        $service_config = $this->services[$name];
        $factory = $service_config['factory'];
        $config = $service_config['config'];
        
        if (is_callable($factory)) {
            // Factory function
            return call_user_func($factory, $this, $config);
        } elseif (is_string($factory)) {
            // Class name
            if (class_exists($factory)) {
                // Use reflection to handle constructor dependencies
                return $this->create_with_dependencies($factory, $config);
            }
        }
        
        throw new Exception(sprintf('Cannot create service "%s"', $name));
    }
    
    /**
     * Create instance with dependency resolution
     * 
     * @param string $class_name Class name
     * @param array $config Configuration
     * @return object Instance
     */
    private function create_with_dependencies($class_name, $config = array()) {
        $reflection = new ReflectionClass($class_name);
        
        if (!$reflection->getConstructor()) {
            return new $class_name();
        }
        
        $parameters = $reflection->getConstructor()->getParameters();
        $dependencies = array();
        
        foreach ($parameters as $parameter) {
            $param_type = $parameter->getType();
            
            if ($param_type && !$param_type->isBuiltin()) {
                $param_class = $param_type->getName();
                
                // Try to resolve from container first
                $service_name = $this->get_service_name_for_class($param_class);
                if ($service_name && $this->has($service_name)) {
                    $dependencies[] = $this->get($service_name);
                } elseif (class_exists($param_class)) {
                    // Create dependency recursively
                    $dependencies[] = $this->create_with_dependencies($param_class);
                } else {
                    throw new Exception(sprintf('Cannot resolve dependency "%s" for "%s"', $param_class, $class_name));
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new Exception(sprintf('Cannot resolve parameter "%s" for "%s"', $parameter->getName(), $class_name));
            }
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Get service name for class
     * 
     * @param string $class_name Class name
     * @return string|null Service name
     */
    private function get_service_name_for_class($class_name) {
        foreach ($this->services as $name => $config) {
            if (is_string($config['factory']) && $config['factory'] === $class_name) {
                return $name;
            }
        }
        return null;
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
     * Remove service
     * 
     * @param string $name Service name
     */
    public function remove($name) {
        unset($this->services[$name]);
        unset($this->instances[$name]);
        unset($this->configs[$name]);
    }
    
    /**
     * Get service configuration
     * 
     * @param string $name Service name
     * @return array Configuration
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
        $this->register('api_manager', function($container, $config) {
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
            throw new Exception(sprintf('Factory for service "%s" must be callable', $name));
        }
        
        $this->register($name, $factory, $config, $singleton);
    }
    
    /**
     * Get all registered services
     * 
     * @return array Service names
     */
    public function get_services() {
        return array_keys($this->services);
    }
    
    /**
     * Get all instantiated services
     * 
     * @return array Instance names
     */
    public function get_instances() {
        return array_keys($this->instances);
    }
    
    /**
     * Clear all instances (useful for testing)
     */
    public function clear_instances() {
        $this->instances = array();
    }
    
    /**
     * Clone prevention
     */
    private function __clone() {}
    
    /**
     * Unserialization prevention
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize service container');
    }
    
    /**
     * Get container debug information
     * 
     * @return array Debug info
     */
    public function get_debug_info() {
        $debug_info = array(
            'services_count' => count($this->services),
            'instances_count' => count($this->instances),
            'services' => array(),
            'instances' => array_keys($this->instances)
        );
        
        foreach ($this->services as $name => $config) {
            $debug_info['services'][$name] = array(
                'singleton' => $config['singleton'],
                'factory_type' => is_callable($config['factory']) ? 'callable' : 'class',
                'instantiated' => isset($this->instances[$name])
            );
        }
        
        return $debug_info;
    }
}