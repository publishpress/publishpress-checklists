<?php

namespace PublishPress\Checklists\Core;

\defined('ABSPATH') || exit;

class Autoloader
{
    private static $namespaces = [];

    /**
     * Register the autoloader with spl_autoload_register
     *
     * @return void
     */
    public static function register()
    {
        spl_autoload_register(array(new self(), 'autoload'));
    }

    public static function addNamespace($namespace, $path)
    {
        self::$namespaces[$namespace] = $path;
    }

    /**
     * Autoload function that loads classes based on the namespace and class name
     *
     * @param string $class The fully-qualified class name
     *
     * @return void
     */
    public static function autoload($class)
    {
        $class_path = '';
        $relative_class = '';

        foreach (self::$namespaces as $prefix => $path) {
            // does the class use the namespace prefix?
            $len = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                // no, move to the next registered namespace
                continue;
            }

            $class_path = $path;
            $relative_class = substr($class, $len);
            break;
        }

        // no, move to the next registered autoloader
        if (empty($class_path)) {
            return;
        }

        // replace the namespace prefix with the base directory, replace namespace separators with directory separators
        $file = $class_path . str_replace('\\', '/', $relative_class) . '.php';

        // if the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}
