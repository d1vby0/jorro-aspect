<?php

namespace Jorro\Aspect;

interface ProceedingJoinPointInterface extends JoinPointInterface
{
    public function proceed(): mixed;
}