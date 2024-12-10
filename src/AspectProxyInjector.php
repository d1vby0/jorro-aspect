<?php

namespace Jorro\Aspect;

use Jorro\Aspect\Attributes\Aspect;
use Jorro\Reflective\Attributes\ResolveBy;
use Jorro\Reflective\Attributes\ResolveFirst;
use Jorro\Reflective\ReflectiveInjector;

class AspectProxyInjector extends ReflectiveInjector
{
    protected array $proxies = [];
    protected array $advices = [];
    protected array $aspectOrders = [];

    /**
     * Aspectクラスの登録
     *
     * @param string $aspectClass Aspectクラス名
     * @return void
     */
    public function registerAspect(string $aspectClass): void
    {
        if (!isset($this->proxies[$aspectClass])) {
            $reflection = new \ReflectionClass($aspectClass);
            if (empty($aspect = $reflection->getAttributes(Aspect::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null)) {
                return;
            }
            $aspect = $aspect->newInstance();
            $this->proxies[$aspectClass] = false;
            $this->advices[$aspectClass] = [];
            $this->aspectOrders[$aspectClass] = $aspect->order;
            asort($this->aspectOrders);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Advice::class, \ReflectionAttribute::IS_INSTANCEOF) as $advice) {
                    $advice = $advice->newInstance();
                    $advice->aspectClass = $aspectClass;
                    $advice->aspectMethod = $method->getName();
                    $this->advices[$aspectClass][] = $advice;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $class, ...$values): object
    {
        if (!isset($this->proxies[$class])) {
            $this->proxies[$class] = $this->buildProxy($class);
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($proxyClass = $this->proxies[$class]) {
            return ($constructor) ? new $proxyClass(
                $this, ... $this->resolveParameters($constructor, ...$values)
            ) : new $proxyClass($this);
        } else {
            return ($constructor) ? new $class(
                ...
                $this->resolveParameters($constructor, ...$values)
            ) : new $class();
        }
    }

    protected function buildProxyMethods(\ReflectionClass $reflection): array
    {
        $proxyMethods = [];
        $class = $reflection->getName();
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $builder = new AspectProxyMethodBuilder($reflection, $reflectionMethod);
            foreach (array_keys($this->aspectOrders) as $aspectClass) {
                if ($aspectClass == $class) {
                    continue;
                }
                foreach ($this->advices[$aspectClass] as $advice) {
                    /** @var $advice \Jorro\Aspect\Advice */
                    $builder->allocate($advice);
                }
            }
            if ($builder->hasAdvices()) {
                $proxyMethods[] = $builder;
            }
        }

        return $proxyMethods;
    }

    /**
     * @param string $class
     * @return string|bool
     */
    protected function buildProxy(string $class): string|bool
    {
        $reflection = new \ReflectionClass($class);
        $proxyMethods = $this->buildProxyMethods($reflection);
        if (empty($proxyMethods)) {
            return false;
        }
        try {
            $self = '_s';
            while ($reflection->getProperty($self)) {
                $self = '_s' . uniqid();
            }
        } catch (\ReflectionException $e) {
        }


        $md5 = md5($class);
        $proxyClass = $class . '_' . $md5;
        $parentConstractor = '';
        if ($constructor = $reflection->getConstructor()) {
            $parentConstractor = "parent::__construct(...\$v);";
        }
        $code = '';
        if ($namespace = $reflection->getNamespaceName()) {
            $code .= 'namespace ' . $reflection->getNamespaceName() . ';';
        }
        $code .= "class " . basename($class) . '_' . $md5 . " extends \\{$class} implements \Jorro\Aspect\AspectProxyInterface {public function __construct(private \${$self},...\$v){{$parentConstractor}}";
        foreach ($proxyMethods as $proxyMethod) {
            $code .= $proxyMethod->build($self);
        }
        $code .= '}';
        try {
            file_put_contents('hai.php', '<?php ' . $code);
            eval($code);
        } catch (\Throwable $t) {
            echo $t->getMessage();
            exit;
        }

        return $proxyClass;
    }

    public function callInterceptor(string $aspectClass, string $aspectMethod, JoinPointInterface $joinPoint, array $args = []): mixed
    {
        $reflectionMethod = new \ReflectionMethod($aspectClass, $aspectMethod);
        if ($reflectionMethod->getNumberOfParameters() > 1) {
            $args = array_merge($args, $this->fillParameters($reflectionMethod, $joinPoint->getArgs(true)));
        }

        return $this->get($aspectClass)->$aspectMethod($joinPoint, ... $args);
    }

    public function fillParameters(\ReflectionMethod $reflectionMethod, $values): array
    {
        $parameters = [];
        foreach ($reflectionMethod->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (key_exists($name, $values)) {
                $parameters[$name] = $values[$name];
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $parameters[$name] = $parameter->getDefaultValue();
            }
        }
        return $parameters;
    }
}