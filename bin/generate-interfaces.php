#!/usr/bin/php
<?php

use Sfp\Code\Extract\ExtensionSource;
use Sfp\Code\Extract\ExtractClass;

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

$extractClass = new ExtractClass(NAMESPACE_NAME, $proto);

/** @var ReflectionClass $class */
foreach ($ref->getClasses() as $class) {
    $interfaceGenerator = $extractClass->getInterfaceGenerator($class);


    $file = $build_dir . DIRECTORY_SEPARATOR . $interfaceGenerator->getName() . '.php';
    touch($file);
    file_put_contents($file, '<?php' . "\n");
    file_put_contents($file, $interfaceGenerator->generate(), FILE_APPEND);
}

