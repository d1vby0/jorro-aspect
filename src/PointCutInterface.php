<?php

namespace Jorro\Aspect;

interface PointCutInterface
{
    public function match(string $class, string $name): bool;
}