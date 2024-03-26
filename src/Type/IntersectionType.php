<?php

namespace DaveRandom\CallbackValidator\Type;

final class IntersectionType implements BaseType{
	/**
	 * @param BaseType[] $types
	 */
	public function __construct(
		public readonly array $types,
	){}

	public function stringify(int $depth = 0) : string{
		$result = implode('&', array_map(fn($type) => $type->stringify($depth + 1), $this->types));
		return $depth === 0 ? $result : "($result)";
	}
}
