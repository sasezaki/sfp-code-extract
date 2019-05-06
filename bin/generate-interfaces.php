#!/usr/bin/php
<?php

use Sfp\Code\Extract\ExtensionSource;
use Zend\Code\Generator\InterfaceGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Reflection\MethodReflection;

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

/** @var ReflectionClass $class */
foreach ($ref->getClasses() as $class) {
    $interfaceGenerator = getInterfaceGenerator($class, $proto);

    $file = $build_dir . DIRECTORY_SEPARATOR . $interfaceGenerator->getName() . '.php';
    touch($file);
    file_put_contents($file, '<?php' . "\n");
    file_put_contents($file, $interfaceGenerator->generate(), FILE_APPEND);
}

function getInterfaceGenerator(ReflectionClass $class, array $proto) : InterfaceGenerator {
    $interfaceGenerator = new InterfaceGenerator;
    $interfaceGenerator->setNamespaceName(NAMESPACE_NAME);
    $interfaceGenerator->setName($class->getName() . CLASS_SUFFIX);

    $parentClass = $class->getParentClass();
    if ($parentClass) {
        $parentInterface = sprintf('%s\\%sInterface', NAMESPACE_NAME, $parentClass->getName());
        $interfaceGenerator->setImplementedInterfaces([$parentInterface]);
    }

    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $parentMethodNames = [];
        if ($parentClass) {
            foreach ($parentClass->getMethods(ReflectionMethod::IS_PUBLIC) as $parentMethod) {
                $parentMethodNames[] = $parentMethod->getName();
            }
            if ($parentClass && in_array($method->getName(), $parentMethodNames)) {
                continue;
            }
        }

        $methodReflection = new Zend\Code\Reflection\MethodReflection($class->getName(), $method->getName());
        $methodGenerator = MethodGenerator::fromReflection($methodReflection);
        if (NULL !== $methodReflection->getReturnType()) {
            $methodGenerator->setReturnType($methodReflection->getReturnType());
        } else {

            if (isset($proto[$class->getName()][$method->getName()]['return'])) {
                $returnType = $proto[$class->getName()][$method->getName()]['return'];

                if (isset($proto[$class->getName()][$method->getName()]['returnArray']) && $proto[$class->getName()][$method->getName()]['returnArray']) {
                    $methodGenerator->setReturnType('array');
                    $docBlockGenerator = new \Zend\Code\Generator\DocBlockGenerator();
                    $docBlockGenerator->setTag(new \Zend\Code\Generator\DocBlock\Tag\GenericTag('return', $returnType.'[]'));
                    if (isset($proto[$class->getName()][$method->getName()]['comment'])) {
                        $docBlockGenerator->setShortDescription($proto[$class->getName()][$method->getName()]['comment']);
                    }

                    $methodGenerator->setDocBlock($docBlockGenerator);
                    goto add_method;
                }

                if ($returnType !== 'void') {
                    if ($returnType === 'stdclass') {
                        $returnType = '\\stdClass';
                    }
                    if ($returnType === 'mixed') {
                        goto set_docblock;
                    }
                    $methodGenerator->setReturnType($returnType);
                } else {
                    // is magic method ?
                    if (0 !== stripos($method->getName(), '__')) {
                        $methodGenerator->setReturnType('void');
                    }
                }
            }
        }

        set_docblock:

        if (isset($proto[$class->getName()][$method->getName()]['comment'])) {
            $methodGenerator->setDocBlock($proto[$class->getName()][$method->getName()]['comment']);
        }

        add_method:
        $interfaceGenerator->addMethodFromGenerator($methodGenerator);
    }

    return $interfaceGenerator;
}