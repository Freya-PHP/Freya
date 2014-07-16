<?php

/**
 * @class			ClassLoader
 * @namespace		Freya\Helpers
 * @description
 * 
 * A wrapper class to an autoload function following the PSR-4 standard.
 */

namespace Freya\Helpers;

class ClassLoader
{
    private $prefixes = array();

    public function addPrefix($prefix, $baseDir)
    {
        $prefix  = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        $this->prefixes[] = array($prefix, $baseDir);
    }

    public function findFile($class)
    {
        $class = ltrim($class, '\\');

        foreach ($this->prefixes as $current) {
            list($currentPrefix, $currentBaseDir) = $current;
            if (strpos($class, $currentPrefix) === 0) {
                $classWithoutPrefix = substr($class, strlen($currentPrefix));
                $file = $currentBaseDir . str_replace('\\', DIRECTORY_SEPARATOR, $classWithoutPrefix) . '.php';
                if (file_exists($file)) {
                    return $file;
                }
            }
        }
    }

    public function loadClass($class)
    {
        $file = $this->findFile($class);
        if (null !== $file) {
            require $file;

            return true;
        }

        return false;
    }

    public function register()
    {
        spl_autoload_register(array($this, 'loadClass'));
    }

    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }
}