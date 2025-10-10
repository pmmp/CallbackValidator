<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\Type\BaseType;

final class ReturnInfo{
	public function __construct(
		public readonly ?BaseType $type,
		public readonly bool $byReference = false
	){}

	public function isSatisfiedBy(ReturnInfo $other) : bool{
		return $this->byReference === $other->byReference && MatchTester::isCovariant($this->type, $other->type);
	}

	/**
	 * @return string
	 */
	public function __toString(){
		return $this->type?->stringify() ?? '';
	}
}
