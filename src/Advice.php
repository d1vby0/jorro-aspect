<?php

namespace Jorro\Aspect;

abstract class Advice
{
    public readonly PointCut $pointCut;
    public string $aspectClass;
    public string $aspectMethod;

    public function __construct(string $pointCut)
    {
        $this->pointCut = new PointCut($pointCut);
    }
}