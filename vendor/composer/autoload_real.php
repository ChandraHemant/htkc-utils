<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitc250a2b784b396e53b41f7b1b7b7497c
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInitc250a2b784b396e53b41f7b1b7b7497c', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitc250a2b784b396e53b41f7b1b7b7497c', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitc250a2b784b396e53b41f7b1b7b7497c::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
