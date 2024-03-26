<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator\Type;

enum BuiltInType: string{
	case STRING = 'string';
	case INT = 'int';
	case FLOAT = 'float';
	case BOOL = 'bool';
	case ARRAY = 'array';
	case VOID = 'void';
	case CALLABLE = 'callable';
	case ITERABLE = 'iterable';
	case OBJECT = 'object';
	case MIXED = 'mixed';
	case NULL = 'null';
}
