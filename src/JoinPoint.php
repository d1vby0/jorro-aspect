<?php

namespace Jorro\Aspect;

class JoinPoint implements JoinPointInterface
{
    public function __construct(protected object $proxy, protected string $method, protected array $values)
    {
    }

    public function getArgs(bool $considerDefault = false): array
    {
        $result = [];
        $index = 0;
        foreach ($this->getTarget()->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (key_exists($name, $this->values)) {
                $result[$name] = $this->values[$name];
            } elseif (key_exists($index, $this->values)) {
                $result[$name] = $this->values[$index];
                $index++;
            } elseif($considerDefault) {
                if($parameter->isDefaultValueAvailable()) {
                    $result[$name] = $parameter->getDefaultValue();
                }
            }
        }

        return $result;;
    }

    public function getTarget(): \ReflectionMethod
    {
        static $refletionMethod = null;
        return $refletionMethod ??= new \ReflectionMethod(new \ReflectionClass($this->proxy)->getParentClass()->getname(), $this->method);
    }

    public function getProxy(): object
    {
        return $this->proxy;
    }

}