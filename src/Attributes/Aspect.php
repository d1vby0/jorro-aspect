<?php

namespace Jorro\Aspect\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Aspect
{
    public function __construct(public int $order = 0)
    {
    }
}