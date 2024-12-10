<?php

namespace Jorro\Aspect;

use Jorro\Aspect\Attributes\After;
use Jorro\Aspect\Attributes\AfterReturning;
use Jorro\Aspect\Attributes\AfterThrowing;
use Jorro\Aspect\Attributes\Around;
use Jorro\Aspect\Attributes\Before;

class AspectProxyMethodBuilder
{
    protected bool $valid = true;
    protected string $class;
    protected string $method;
    protected array $advices = [];
    protected array $parameterNames;
    protected array $parameterTypes;
    protected array $returnTypes;

    public function __construct(protected \ReflectionClass $reflectionClass, protected \ReflectionMethod $reflectionMethod)
    {
        $this->class = $this->reflectionClass->getName();
        $this->method = $this->reflectionMethod->getName();
        if ($this->method === '__construct') {
            $this->valid = false;

            return;
        }
        if ($reflectionMethod->getModifiers() != \ReflectionMethod::IS_PUBLIC) {
            $this->valid = false;

            return;
        }
        foreach ($this->reflectionMethod->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                $this->valid = false;
            }
            $this->parameterNames[] = $parameter->getName();
        }
        $this->returnTypes = $this->getTypes($this->reflectionMethod->getReturnType());
    }

    protected function getTypes(?\ReflectionType $types): array
    {
        if (is_null($types)) {
            return [];
        }
        if ($types instanceof \ReflectionNamedType) {
            return [$types->getName()];
        } else {
            $result = [];
            foreach ($types->getTypes() as $type) {
                $result[] = $type->getName();
            }

            return $result;
        }
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function match(Advice $advice): bool
    {
        return $advice->pointCut->match($this->class, $this->method, $this);
    }

    public function allocate(Advice $advice): bool
    {
        if ($this->match($advice)) {
            $this->advices[] = $advice;

            return true;
        }

        return false;
    }

    public function hasAdvices(): bool
    {
        return !empty($this->advices);
    }


    protected function getAdviceType($advice): string
    {
        return match (true) {
            ($advice instanceof Around) => 'around',
            ($advice instanceof After) => 'after',
            ($advice instanceof Before) => 'before',
            ($advice instanceof AfterThrowing) => 'throwing',
            ($advice instanceof AfterReturning) => 'returning',
        };
    }

    public function build(string $injector): string
    {
        foreach ($this->advices as $advice) {
            $before = '';
            $after = '';
            $throwing = '';
            $returning = '';
            $around = '';
            $adviceType = $this->getAdviceType($advice);
            $joinPoint = null;
            if ($adviceType == 'around') {
                $around .= '$f[]=new \Fiber(function() use ($v,$p){';
                $around .= "\$this->{$injector}->callInterceptor('{$advice->aspectClass}','{$advice->aspectMethod}',\$p);";
                $around .= '});';
                continue;
            }
            $call = "\$this->{$injector}->callInterceptor('{$advice->aspectClass}','{$advice->aspectMethod}',\$j);";
            if ($adviceType == 'before') {
                $before .= $call;
            } else {
                $$adviceType = $call . $$adviceType;
            }
            $joinPoint ??= "\$j=new \Jorro\Aspect\JoinPoint(\$this,'{$this->method}',\$v);";
        }

        $returnTypes = implode('|', $this->returnTypes);
        $returnValue = (($returnTypes) && ($returnTypes != 'void'));
        $code = 'public function ' . $this->method . '(...$v)' . (($returnTypes) ? ':' . $returnTypes : '') . '{';

        $code .= $joinPoint;
        if ($around) {
            $code .= "\$p=new \Jorro\Aspect\ProceedingJoinPoint(\$this,'{$this->method}',\$v);";
            $code .= '$f=[];';
            $code .= $around;
            $code .= 'foreach($f as $_f){$_f->start();}';
        }
        $code .= $before;
        $code .= 'try {';

        if ($returnValue) {
            $code .= '$r=';
        }
        $code .= "parent::{$this->method}(...\$v);";
        $code .= $returning;
        if ($returnValue) {
            $code .= 'return $r;';
        }
        $code .= '} catch (\Throwable $t) {';
        $code .= $throwing;
        $code .= 'throw $t;';
        $code .= '} finally {';
        $code .= $after;
        if ($around) {
            $code .= 'if (isset($t)) {';
            $code .= 'foreach($f as $_f){$_f->isSuspended() && $_f->throw($t);}';
            $code .= '} else {';
            $code .= 'foreach($f as $_f){$_f->isSuspended() && $_f->resume(' . (($returnValue) ? '$r' : '') . ');}';
            $code .= '}';
        }
        $code .= '}';
        $code .= '}';

        return $code;
    }


    public function matchClass(string $classPattern): bool
    {
        return preg_match('/' . $classPattern . '/i', $this->class);
    }

    public function matchMethod(string $methodPattern): bool
    {
        return preg_match('/' . $methodPattern . '/i', $this->method);
    }

    public function isMethod($method): bool
    {
        return $this->method == $method;
    }

    public function isClass($class): bool
    {
        return $this->class == $class;
    }

    public function isInstanceOf($class): bool
    {
        return !is_subclass_of($class, $this->class);
    }

    public function hasReturnType(array $types): bool
    {
        return empty(array_diff($types, $this->returnTypes));
    }

    public function hasParameterType(array $types): bool
    {
        foreach ($this->reflectionMethod->getParameters() as $index => $parameter) {
            $type = array_shift($types);
            if (is_null($type)) {
                return false;
            }
            if ($type == '..') {
                return true;
            }
            if ($type == '*') {
                continue;
            }
            $this->parameterTypes[$index] ??= $this->getTypes($parameter->getType());
            if (!empty(array_diff(explode('|', $type), $this->parameterTypes[$index]))) {
                return false;
            }
        }
        if (!empty($types)) {
            return false;
        }

        return true;
    }

    public function hasParameterName(array $names): bool
    {
        return empty(array_diff($names, $this->parameterNames));
    }

}
