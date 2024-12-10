<?php

namespace Jorro\Aspect;

interface JoinPointInterface
{
    public function getArgs(): array;

    public function getTarget(): \ReflectionMethod;

    public function getProxy(): object;
}