<?php

namespace Jorro\Aspect\Attributes;

use Jorro\Aspect\Advice;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Around extends Advice
{
}