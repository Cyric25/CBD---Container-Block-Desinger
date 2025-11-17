<?php
/**
 * Container Block Designer - PSR-4 Autoloader
 * Fallback autoloader when Composer is not available
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 Autoloader Class
 * Provides autoloading functionality when Composer is not available
 */
class CBD_Autoloader {
    
    /**
     * Namespace mappings
     */
    private $namespaces = array();
    
    /**
     * Class mappings
     */
    private $classes = array();
    
    /**
     * Register autoloader
     */
    public function register() {
        spl_autoload_register(array($this, 'load_class'));
    }
    
    /**
     * Unregister autoloader
     */
    public function unregister() {
        spl_autoload_unregister(array($this, 'load_class'));
    }
    
    /**
     * Add namespace mapping
     */
    public function add_namespace($namespace, $path) {
        $namespace = trim($namespace, '\\') . '\\';
        $path = rtrim($path, '/') . '/';
        
        if (!isset($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = array();
        }
        
        $this->namespaces[$namespace][] = $path;
    }
    
    /**
     * Add class mapping
     */
    public function add_class($class, $file) {
        $this->classes[$class] = $file;
    }
    
    /**
     * Load class file
     */
    public function load_class($class) {
        // Try direct class mapping first
        if (isset($this->classes[$class])) {
            if (file_exists($this->classes[$class])) {
                require_once $this->classes[$class];
                return true;
            }
        }
        
        // Try namespace mapping
        foreach ($this->namespaces as $namespace => $paths) {
            if (strpos($class, $namespace) === 0) {
                $relative_class = substr($class, strlen($namespace));
                $file_name = $this->get_file_name($relative_class);
                
                foreach ($paths as $path) {
                    $full_path = $path . $file_name;
                    
                    if (file_exists($full_path)) {
                        require_once $full_path;
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Convert class name to file name
     */
    private function get_file_name($class) {
        // Handle subdirectories
        $parts = explode('\\', $class);
        $file_parts = array();
        
        foreach ($parts as $part) {
            // Convert PascalCase to kebab-case with class- prefix for files
            $file_part = $this->pascal_to_kebab($part);
            
            // Last part gets class- prefix
            if ($part === end($parts)) {
                $file_part = 'class-' . $file_part;
            }
            
            $file_parts[] = $file_part;
        }
        
        return implode('/', $file_parts) . '.php';
    }
    
    /**
     * Convert PascalCase to kebab-case
     */
    private function pascal_to_kebab($string) {
        // Insert hyphens before capital letters (except the first)
        $string = preg_replace('/([A-Z])/', '-$1', $string);
        
        // Convert to lowercase and remove leading hyphen
        return strtolower(ltrim($string, '-'));
    }
    
    /**
     * Initialize default mappings
     */
    public function init_default_mappings() {
        $base_path = CBD_PLUGIN_DIR . 'includes/';
        
        // Add namespace mappings
        $this->add_namespace('ContainerBlockDesigner', $base_path);
        
        // Add specific class mappings for legacy classes
        $legacy_classes = array(
            'CBD_Database' => $base_path . 'class-cbd-database.php',
            'CBD_Admin' => $base_path . 'class-cbd-admin.php',
            'CBD_Ajax_Handler' => $base_path . 'class-cbd-ajax-handler.php',
            'CBD_Style_Loader' => $base_path . 'class-cbd-style-loader.php',
            'CBD_Block_Registration' => $base_path . 'class-cbd-block-registration.php',
            'CBD_Frontend_Renderer' => $base_path . 'class-cbd-frontend-renderer.php',
            'CBD_Unified_Frontend_Renderer' => $base_path . 'class-unified-frontend-renderer.php',
            'CBD_Consolidated_Frontend' => $base_path . 'class-consolidated-frontend.php',
            'CBD_Schema_Manager' => $base_path . 'Database/class-schema-manager.php'
        );
        
        foreach ($legacy_classes as $class => $file) {
            $this->add_class($class, $file);
        }
    }
    
    /**
     * Get loaded classes
     */
    public function get_loaded_classes() {
        return get_declared_classes();
    }
    
    /**
     * Check if class exists or can be loaded
     */
    public function can_load_class($class) {
        if (class_exists($class, false)) {
            return true;
        }
        
        return $this->load_class($class);
    }
    
    /**
     * Get debug info
     */
    public function get_debug_info() {
        return array(
            'namespaces' => $this->namespaces,
            'classes' => $this->classes,
            'loaded_classes' => count(get_declared_classes())
        );
    }
}