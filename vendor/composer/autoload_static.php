<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9da934a9423c6df2e90dae99265f6022
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Combodo\\iTop\\Anonymizer\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Combodo\\iTop\\Anonymizer\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Combodo\\iTop\\Anonymizer\\Controller\\AjaxAnonymizerController' => __DIR__ . '/../..' . '/src/Controller/AjaxAnonymizerController.php',
        'Combodo\\iTop\\Anonymizer\\Controller\\ConfigAnonymizerController' => __DIR__ . '/../..' . '/src/Controller/ConfigAnonymizerController.php',
        'Combodo\\iTop\\Anonymizer\\Helper\\AnonymizerException' => __DIR__ . '/../..' . '/src/Helper/AnonymizerException.php',
        'Combodo\\iTop\\Anonymizer\\Helper\\AnonymizerHelper' => __DIR__ . '/../..' . '/src/Helper/AnonymizerHelper.php',
        'Combodo\\iTop\\Anonymizer\\Helper\\AnonymizerLog' => __DIR__ . '/../..' . '/src/Helper/AnonymizerLog.php',
        'Combodo\\iTop\\Anonymizer\\Service\\AnonymizerService' => __DIR__ . '/../..' . '/src/Service/AnonymizerService.php',
        'Combodo\\iTop\\Anonymizer\\Service\\CleanupService' => __DIR__ . '/../..' . '/src/Service/CleanupService.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9da934a9423c6df2e90dae99265f6022::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9da934a9423c6df2e90dae99265f6022::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9da934a9423c6df2e90dae99265f6022::$classMap;

        }, null, ClassLoader::class);
    }
}
