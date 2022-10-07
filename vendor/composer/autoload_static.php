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
        'Combodo\\iTop\\Anonymizer\\Action\\AbstractAnonymizationAction' => __DIR__ . '/../..' . '/src/Action/AbstractAnonymizationAction.php',
        'Combodo\\iTop\\Anonymizer\\Action\\AnonymizationActionFactory' => __DIR__ . '/../..' . '/src/Action/AnonymizationActionFactory.php',
        'Combodo\\iTop\\Anonymizer\\Action\\AnonymizePerson' => __DIR__ . '/../..' . '/src/Action/AnonymizePerson.php',
        'Combodo\\iTop\\Anonymizer\\Action\\CleanupCaseLogs' => __DIR__ . '/../..' . '/src/Action/CleanupCaseLogs.php',
        'Combodo\\iTop\\Anonymizer\\Action\\CleanupEmailNotification' => __DIR__ . '/../..' . '/src/Action/CleanupEmailNotification.php',
        'Combodo\\iTop\\Anonymizer\\Action\\CleanupOnMention' => __DIR__ . '/../..' . '/src/Action/CleanupOnMention.php',
        'Combodo\\iTop\\Anonymizer\\Action\\CleanupUsers' => __DIR__ . '/../..' . '/src/Action/CleanupUsers.php',
        'Combodo\\iTop\\Anonymizer\\Action\\PurgePersonHistory' => __DIR__ . '/../..' . '/src/Action/PurgePersonHistory.php',
        'Combodo\\iTop\\Anonymizer\\Action\\ResetPersonFields' => __DIR__ . '/../..' . '/src/Action/ResetPersonFields.php',
        'Combodo\\iTop\\Anonymizer\\Action\\iAnonymizationAction' => __DIR__ . '/../..' . '/src/Action/iAnonymizationAction.php',
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
