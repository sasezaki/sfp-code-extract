<?php

namespace Sfp\Code\Extract;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\InterfaceGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Reflection\MethodReflection;

class ExtractClass
{
    private const CLASS_SUFFIX = 'Interface';

    private $namespaceName;
    private $proto;

    public function __construct(string $namespaceName, array $proto)
    {
        $this->namespaceName = $namespaceName;
        $this->proto = $proto;
    }

    /**
     * @throws ReflectionException
     */
    public function getInterfaceGenerator(ReflectionClass $class) : InterfaceGenerator {
        $interfaceGenerator = new InterfaceGenerator;
        $interfaceGenerator->setNamespaceName($this->namespaceName);
        $interfaceGenerator->setName($class->getName() . self::CLASS_SUFFIX);

        $parentClass = $class->getParentClass();
        if ($parentClass) {
            $this->implementParentAsInterface($parentClass, $interfaceGenerator);
        }

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($parentClass && $this->isParentMethod($method, $parentClass)) {
                continue;
            }
            $this->addMethod($interfaceGenerator, $method);
        }

        return $interfaceGenerator;
    }

    private function formatClassName(ReflectionClass $class) {
        return sprintf('%s\\%sInterface', $this->namespaceName, $class->getName());
    }

    private function implementParentAsInterface(ReflectionClass $parentClass, InterfaceGenerator $interfaceGenerator) : void
    {
        $parentInterface = $this->formatClassName($parentClass);
        $interfaceGenerator->setImplementedInterfaces([$parentInterface]);
    }


    private function isParentMethod(ReflectionMethod $method, ReflectionClass $parentClass) : bool {
        $parentMethodNames = [];
        foreach ($parentClass->getMethods(ReflectionMethod::IS_PUBLIC) as $parentMethod) {
            $parentMethodNames[] = $parentMethod->getName();
        }
        if ($parentClass && in_array($method->getName(), $parentMethodNames)) {
            return true;
        }

        return false;
    }

    /**
     * @throws ReflectionException
     */
    private function addMethod(InterfaceGenerator $interfaceGenerator, ReflectionMethod $method)
    {
        $class =  $method->getDeclaringClass();
        $proto = $this->proto;

        $methodReflection = new MethodReflection($class->getName(), $method->getName());
        $methodGenerator = MethodGenerator::fromReflection($methodReflection);
        if (NULL !== $methodReflection->getReturnType()) {
            $methodGenerator->setReturnType($methodReflection->getReturnType());
        } else {

            if (isset($proto[$class->getName()][$method->getName()]['return'])) {
                $returnType = $proto[$class->getName()][$method->getName()]['return'];

                if (isset($proto[$class->getName()][$method->getName()]['returnArray']) && $proto[$class->getName()][$method->getName()]['returnArray']) {
                    $methodGenerator->setReturnType('array');
                    $docBlockGenerator = new DocBlockGenerator();
                    $docBlockGenerator->setTag(new GenericTag('return', $returnType.'[]'));
                    if (isset($proto[$class->getName()][$method->getName()]['comment'])) {
                        $docBlockGenerator->setShortDescription($proto[$class->getName()][$method->getName()]['comment']);
                    }

                    $methodGenerator->setDocBlock($docBlockGenerator);
                    goto add_method;
                }

                if ($returnType !== 'void') {
                    if ($returnType === 'stdclass') {
                        if (!$interfaceGenerator->hasUse(stdClass::class)) {
                            $interfaceGenerator->addUse(stdClass::class);
                        }
                        $returnType = stdClass::class;
                    }
                    if ($returnType === 'mixed') {
                        goto set_docblock;
                    }
                    $methodGenerator->setReturnType($returnType);
                } else {
                    if ($method->getName() !== '__construct') {
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
}