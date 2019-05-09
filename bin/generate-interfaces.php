#!/usr/bin/php
<?php

use Sfp\Code\Extract\ExtensionSource;
use Sfp\Code\Extract\ExtractExtension;

require_once __DIR__ . '/../vendor/autoload.php';

$ref = new ReflectionExtension('Reflection');
$proto = ExtensionSource::analyseProto(__DIR__ . '/../php_reflection.c');
$build_dir = dirname(__DIR__) . '/src-build2';

if (!is_dir($build_dir)) {
    mkdir($build_dir);
} else {
    `rm -f {$build_dir}/*`;
}

const NAMESPACE_NAME = 'Sfp\\Code\\Reflection\\Interfaces';
const CLASS_SUFFIX = 'Interface';

$ignoreClasses = [Reflection::class];
$extractClass = new ExtractExtension($ref,NAMESPACE_NAME, $proto);

foreach ($extractClass->getInterfaceGenerators() as $class => $interfaceGenerator) {
    if (in_array($class->getName(), $ignoreClasses)) {
        continue;
    }
    $file = $build_dir . DIRECTORY_SEPARATOR . $interfaceGenerator->getName() . '.php';
    touch($file);
    file_put_contents($file, '<?php' . "\n");
    file_put_contents($file, $interfaceGenerator->generate(), FILE_APPEND);
}

