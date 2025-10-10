<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\Type\BaseType;

final class ParameterInfo{
	public function __construct(
		public readonly string $name,
		public readonly ?BaseType $type,
		public readonly bool $byReference,
		public readonly bool $isOptional,
		public readonly bool $isVariadic
	){}

	public function isSatisfiedBy(ParameterInfo $other) : bool{
		//contravariance can be tested as covariance by swapping the types
		return $this->byReference === $other->byReference && MatchTester::isCovariant($other->type, $this->type);
	}

	/**
	 * @return string
	 */
	public function __toString() : string{
		$string = '';

		if($this->type !== null){
			$string .= $this->type->stringify() . ' ';
		}

		if($this->byReference){
			$string .= '&';
		}

		if($this->isVariadic){
			$string .= '...';
		}

		$string .= '$' . $this->name;
		return $string;
	}
}
