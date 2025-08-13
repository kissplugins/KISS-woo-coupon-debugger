<?php
/**
 * PSR-4 Autoloader for KISS Woo Coupon Debugger
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger;

/**
 * PSR-4 Autoloader implementation
 */
class Autoloader {

    /**
     * Namespace prefix to directory mapping
     *
     * @var array
     */
    private static $prefixes = [];

    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register(): void {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Add a namespace prefix and directory mapping
     *
     * @param string $prefix The namespace prefix
     * @param string $base_dir The base directory for the namespace
     * @return void
     */
    public static function addNamespace(string $prefix, string $base_dir): void {
        // Normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';
        
        // Normalize the base directory with a trailing separator
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';
        
        // Initialize the namespace prefix array
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }
        
        // Retain the base directory for the namespace prefix
        array_push(self::$prefixes[$prefix], $base_dir);
    }

    /**
     * Load the class file for a given class name
     *
     * @param string $class The fully-qualified class name
     * @return mixed The mapped file name on success, or boolean false on failure
     */
    public static function loadClass(string $class) {
        // The current namespace prefix
        $prefix = $class;
        
        // Work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {
            // Retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);
            
            // The rest is the relative class name
            $relative_class = substr($class, $pos + 1);
            
            // Try to load a mapped file for the prefix and relative class
            $mapped_file = self::loadMappedFile($prefix, $relative_class);
            if ($mapped_file) {
                return $mapped_file;
            }
            
            // Remove the trailing namespace separator for the next iteration
            $prefix = rtrim($prefix, '\\');
        }
        
        // Never found a mapped file
        return false;
    }

    /**
     * Load the mapped file for a namespace prefix and relative class
     *
     * @param string $prefix The namespace prefix
     * @param string $relative_class The relative class name
     * @return mixed Boolean false if no mapped file can be loaded, or the
     * name of the mapped file that was loaded
     */
    protected static function loadMappedFile(string $prefix, string $relative_class) {
        // Are there any base directories for this namespace prefix?
        if (!isset(self::$prefixes[$prefix])) {
            return false;
        }
        
        // Look through base directories for this namespace prefix
        foreach (self::$prefixes[$prefix] as $base_dir) {
            // Replace the namespace prefix with the base directory,
            // replace namespace separators with directory separators
            // in the relative class name, append with .php
            $file = $base_dir
                  . str_replace('\\', '/', $relative_class)
                  . '.php';
            
            // If the mapped file exists, require it
            if (self::requireFile($file)) {
                // Yes, we're done
                return $file;
            }

            // Temporary compatibility: handle legacy filename for AdminUI
            // If looking for Admin/AdminUI.php but only AdminInterface.php exists
            if (substr($file, -strlen('Admin/AdminUI.php')) === 'Admin/AdminUI.php') {
                $alt = substr($file, 0, -strlen('Admin/AdminUI.php')) . 'Admin/AdminInterface.php';
                if (self::requireFile($alt)) {
                    return $alt;
                }
            }
        }
        
        // Never found it
        return false;
    }

    /**
     * If a file exists, require it from the file system
     *
     * @param string $file The file to require
     * @return bool True if the file exists, false if not
     */
    protected static function requireFile(string $file): bool {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}
