<?php

namespace Jorro\Aspect;

class PointCut implements PointCutInterface
{
    protected ?array $returnTypes = null;
    protected ?string $classPattern = null;
    protected ?string $methodPattern = null;
    protected ?string $class = null;
    protected ?string $method = null;
    protected ?bool $instanceOf = null;
    protected ?array $parameterNames = null;
    protected ?array $parameterTypes = null;

    public function __construct(string $pointCut)
    {
        $this->parse($pointCut);
    }

    /**
     * execution(returnType|returnType, [classPattern::]namePattern(parameterType,paramterType,..))
     * target(class[::name])
     * within(class[::name])
     * args(parameterName,parameterName,)
     * @param string $patternString
     * @return void
     */
    protected function parse(string $pointCut): void
    {
        foreach (explode('&&', $pointCut) as $formula) {
            try {
                $section = trim($formula);
                if (!preg_match('/(?<disignator>execution|target|within|args)\((?<parameter>.+)\)/', $formula, $matches)) {
                    throw new \Exception("Ignored unsupported PointCut formula '{$formula}'");
                }
                $disignator = $matches['disignator'];
                $parameter = $matches['parameter'];
                switch ($disignator) {
                    case 'execution':
                        if (!preg_match('/(?<return>.+) (?<name>.+)\((?<parameter>.+)?\)$/', $parameter, $matches)) {
                            throw new \Exception("Ignored unsupported PointCut formula '{$formula}'");
                        }
                        $this->returnTypes = $matches['return'] == '*' ? null : explode('|', $matches['return']);


                        [$this->classPattern, $this->methodPattern] = $this->parseName($matches['name'], false);
                        $this->parameterTypes = $matches['parameter'] == '..' ? null : explode(',', $matches['parameter']);
                        break;
                    case 'target':
                    case 'within':
                        if (!is_null($this->target)) {
                            throw new \Exception("Ignored unspported PointCut multiple formula '{$formula}'");
                        }
                        [$this->class, $this->methodPattern] = $this->parseName($parameter, true);
                        break;
                    case 'args':
                        $this->parameterNames = explode(',', $parameter);
                        break;
                }
            } catch (\Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }
    }

    protected function parseName(string $name, bool $requiredClass): array
    {
        if (!str_contains($name, '::')) {
            $result = ($requiredClass) ? [$name, ''] : ['', $name];
        } else {
            $result = explode('::', $name);
        }

        return array_map(function ($value) {
            return $value == '*' ? '' : str_replace('*', '.+', $value);
        }, $result);
    }

    public function match(string $class, string $name, ?AspectProxyMethodBuilder $builder = null): bool
    {
        if (($this->class) && ((!$this->instanceOf) && (!$builder->isDeclaringClass($this->class)) || (($this->instanceOf) && (!$builder->isDeclaringInstanceOf($this->class))))) {
            return false;
        }
        if (($this->method) && (!$builder->isName($this->method))) {
            return false;
        }
        if (($this->classPattern) && (!$builder->isMatchClass($this->classPattern))) {
            return false;
        }
        if (($this->methodPattern) && (!$builder->isMatchName($this->methodPattern))) {
            return false;
        }
        if (($this->returnTypes) && (!$builder->hasReturnType($this->parameterTypes))) {
            return false;
        }
        if ((!is_null($this->parameterTypes)) && (!$builder->hasParameterType($this->parameterTypes))) {
            return false;
        }
        if (($this->parameterNames) && (!$builder->hasParameterName($this->parameterNames))) {
            return false;
        }

        return true;
    }

}