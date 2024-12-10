<?php

namespace Jorro\Aspect\Attributes;

use Jorro\Aspect\Advice;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AfterReturning extends Advice
{
    public function __construct(string $pointCut, public ?string $returning = null)
    {
        parent::__construct($pointCut);
    }
}