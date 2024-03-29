<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use function array_map;
use function get_class;

final class Prototype{
	private function __construct(){
		//NOOP
	}

	private static function parameterSatisfiedBy(\ReflectionParameter $prototype, \ReflectionParameter $given) : bool{
		return
			//TODO: we should probably check the name as well
			$prototype->isPassedByReference() === $given->isPassedByReference() &&
			//contravariance can be tested as covariance by swapping the types
			!MatchTester::isCovariant($given->getType(), $prototype->getType());
	}

	public static function isSatisfiedBy(\Closure $prototype, \Closure $callable) : bool{
		$prototypeReflect = new \ReflectionFunction($prototype);
		$callableReflect = new \ReflectionFunction($callable);

		if($callableReflect->getNumberOfRequiredParameters() > $prototypeReflect->getNumberOfRequiredParameters()){
			return false;
		}

		$prototypeReturn = $prototypeReflect->getReturnType();
		$callableReturn = $callableReflect->getReturnType();

		if(
			$callableReflect->returnsReference() !== $prototypeReflect->returnsReference() ||
			!MatchTester::isCovariant($prototypeReturn, $callableReturn)
		){
			return false;
		}

		$last = null;

		$prototypeParameters = $prototypeReflect->getParameters();
		foreach($callableReflect->getParameters() as $position => $callableParameter){
			// Parameters that exist in the prototype must always be satisfied directly
			if(isset($prototypeParameters[$position])){
				$prototypeParameter = $prototypeParameters[$position];
				if(self::parameterSatisfiedBy($prototypeParameter, $callableParameter)){
					return false;
				}

				$last = $prototypeParameter;
				continue;
			}

			// Candidates can accept additional args that are not in the prototype as long as they are not mandatory
			if(!$callableParameter->isOptional() && !$callableParameter->isVariadic()){
				return false;
			}

			// If the last arg of the prototype is variadic, any additional args the candidate accepts must satisfy it
			if($last !== null && $last->isVariadic() && !self::parameterSatisfiedBy($last, $callableParameter)){
				return false;
			}
		}

		return true;
	}

	public static function print(\Closure $closure) : string{
		$reflect = new \ReflectionFunction($closure);
		$string = 'function ';

		if($reflect->returnsReference()){
			$string .= '& ';
		}

		$string .= '( ';

		$i = $o = 0;
		$parameters = $reflect->getParameters();
		$l = count($parameters) - 1;
		for(; $i < $l; $i++){
			$parameter = $parameters[$i];
			$parameterType = $parameter->getType();

			if($parameterType !== null){
				$string .= self::printType($parameterType) . ' ';
			}

			if($parameter->isPassedByReference()){
				$string .= '&';
			}

			if($parameter->isVariadic()){
				$string .= '...';
			}

			$string .= '$' . $parameter->getName();

			if($o === 0 && !($parameters[$i + 1]->isOptional())){
				$string .= ', ';
				continue;
			}

			$string .= ' [, ';
			$o++;
		}

		if(isset($parameters[$l])){
			$string .= $parameters[$i] . ' ';
		}

		if($o !== 0){
			$string .= str_repeat(']', $o) . ' ';
		}

		$string .= ')';

		$returnType = $reflect->getReturnType();
		if($returnType !== null){
			$string .= ' : ' . self::printType($returnType);
		}

		return $string;
	}

	/**
	 * @param \ReflectionType[] $types
	 */
	private static function printTypes(string $symbol, array $types) : string{
		return implode($symbol, array_map(fn(\ReflectionType $type) => self::printType($type), $types));
	}

	private static function printType(\ReflectionType $type) : string{
		if($type instanceof \ReflectionNamedType){
			return $type->getName();
		}
		if($type instanceof \ReflectionUnionType){
			return self::printTypes('|', $type->getTypes());
		}
		if($type instanceof \ReflectionIntersectionType){
			return self::printTypes('&', $type->getTypes());
		}

		throw new \AssertionError("Unhandled reflection type " . get_class($type));
	}
}
