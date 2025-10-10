<?php

namespace DaveRandom\CallbackValidator\Type;

use function array_shift;
use function assert;
use function count;

final class UnionType implements BaseType{
	/**
	 * @param BaseType[] $types
	 */
	public function __construct(
		public readonly array $types,
	){}

	public function stringify(int $depth = 0) : string{
		if(count($this->types) === 2){
			//simplify nullable types with ?Type
			foreach($this->types as $k => $type){
				if($type instanceof NamedType && $type->type === BuiltInType::NULL){
					$types = $this->types;
					unset($types[$k]);
					$remaining = array_shift($types);
					assert($remaining !== null);
					return '?' . $remaining->stringify($depth + 1);
				}
			}
		}

		$result = implode('|', array_map(fn($type) => $type->stringify($depth + 1), $this->types));
		return $depth === 0 ? $result : "($result)";
	}
}
