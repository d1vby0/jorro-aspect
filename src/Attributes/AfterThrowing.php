<?php

namespace Jorro\Aspect\Attributes;

use Jorro\Aspect\Advice;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AfterThrowing extends Advice
{
    public function __construct(string $pointCut, public ?string $throwing = null)
    {
        parent::__construct($pointCut);
    }
}