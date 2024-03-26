<?php

namespace DaveRandom\CallbackValidator\Type;

/**
 * Similar to ReflectionNamedType
 */
final class NamedType implements BaseType{

	public readonly string|BuiltInType $type;

	public function __construct(
		string|BuiltInType $type,
	){
		//try to convert a string to a BuiltInTypes enum
		if(is_string($type)){
			$builtInType = BuiltInType::tryFrom($type);
			$this->type = $builtInType ?? $type;
		}else{
			$this->type = $type;
		}
	}

	public function stringify(int $depth = 0) : string{
		return $this->type instanceof BuiltInType ? $this->type->value : $this->type;
	}
}
