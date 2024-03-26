<?php declare(strict_types = 1);

namespace DaveRandom\CallbackValidator\Test\Base\Fixtures;

class ClassImplementingToString
{
    public function __toString() { return ''; }
}
