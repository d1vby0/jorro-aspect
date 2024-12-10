<?php

namespace Jorro\Aspect;

use Fiber;

class ProceedingJoinPoint extends JoinPoint implements ProceedingJoinPointInterface
{
    public function proceed(): mixed
    {
        return Fiber::suspend();
    }
}