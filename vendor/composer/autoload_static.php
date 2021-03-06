<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb18a7aa5833f2deb27f40896fe17d8bb
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Codechap\\Gmaven2\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Codechap\\Gmaven2\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb18a7aa5833f2deb27f40896fe17d8bb::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb18a7aa5833f2deb27f40896fe17d8bb::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb18a7aa5833f2deb27f40896fe17d8bb::$classMap;

        }, null, ClassLoader::class);
    }
}
