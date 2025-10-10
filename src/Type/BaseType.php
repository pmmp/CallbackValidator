<?php

namespace DaveRandom\CallbackValidator\Type;

/**
 * This hierarchy mostly mirrors ReflectionType hierarchy, since we can't directly instantiate it
 * Sometimes it's desirable to validate a callable against a manually constructed signature without using a closure
 * (usually in cases where the types accepted are not known at compile time)
 */
interface BaseType{
	public function stringify(int $depth = 0) : string;
}
