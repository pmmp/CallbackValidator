<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\Type\BaseType;
use DaveRandom\CallbackValidator\Type\BuiltInType;
use DaveRandom\CallbackValidator\Type\IntersectionType;
use DaveRandom\CallbackValidator\Type\NamedType;
use DaveRandom\CallbackValidator\Type\UnionType;
use function array_map;
use function get_class;

final class Prototype{
	private ReturnInfo $returnInfo;

	/** @var ParameterInfo[] */
	private array $parameters;

	private int $requiredParameterCount;

	/**
	 * @param \ReflectionType[] $types
	 *
	 * @return BaseType[]
	 */
	private static function convertReflectionTypeArray(array $types) : array{
		return array_map(fn(\ReflectionType $innerType) => self::convertReflectionTypeInner($innerType), $types);
	}

	private static function convertReflectionTypeInner(\ReflectionType $type) : BaseType{
		return match (true) {
			$type instanceof \ReflectionNamedType =>
				//simple nullable types are still represented by named types despite technically being unions of Type|null
				//convert these to unions so MatchTester can handle them properly
				//mixed types are considered nullable by the reflection API
				$type->allowsNull() && $type->getName() !== BuiltInType::MIXED->value ?
					new UnionType([new NamedType($type->getName()), new NamedType(BuiltInType::NULL)]) :
					new NamedType($type->getName()),
			$type instanceof \ReflectionUnionType => new UnionType(self::convertReflectionTypeArray($type->getTypes())),
			$type instanceof \ReflectionIntersectionType => new IntersectionType(self::convertReflectionTypeArray($type->getTypes())),
			default => throw new \AssertionError("Unhandled reflection type " . get_class($type))
		};
	}

	private static function convertReflectionType(?\ReflectionType $type) : ?BaseType{
		return $type === null ? null : self::convertReflectionTypeInner($type);
	}

	public static function fromClosure(\Closure $callable) : Prototype{
		$reflection = new \ReflectionFunction($callable);

		$returnType = new ReturnInfo(self::convertReflectionType($reflection->getReturnType()), $reflection->returnsReference());

		$parameters = [];

		foreach($reflection->getParameters() as $parameterReflection){
			$parameters[] = new ParameterInfo(
				$parameterReflection->getName(),
				self::convertReflectionType($parameterReflection->getType()),
				$parameterReflection->isPassedByReference(),
				$parameterReflection->isOptional(),
				$parameterReflection->isVariadic()
			);
		}

		return new Prototype($returnType, ...$parameters);
	}

	public function __construct(ReturnInfo $returnType, ParameterInfo ...$parameters){
		$this->returnInfo = $returnType;
		$this->parameters = $parameters;
		$this->requiredParameterCount = 0;
		foreach($parameters as $parameter){
			if(!$parameter->isOptional && !$parameter->isVariadic){
				$this->requiredParameterCount++;
			}
		}
	}

	/**
	 * @return ParameterInfo[]
	 */
	public function getParameterInfo() : array{
		return $this->parameters;
	}

	public function getRequiredParameterCount() : int{
		return $this->requiredParameterCount;
	}

	public function getReturnInfo() : ReturnInfo{
		return $this->returnInfo;
	}

	public function isSatisfiedBy(Prototype $callable) : bool{
		if(!$this->returnInfo->isSatisfiedBy($callable->returnInfo)){
			return false;
		}

		if($callable->requiredParameterCount > $this->requiredParameterCount){
			return false;
		}

		$last = null;

		foreach($callable->parameters as $position => $parameter){
			// Parameters that exist in the prototype must always be satisfied directly
			if(isset($this->parameters[$position])){
				if(!$this->parameters[$position]->isSatisfiedBy($parameter)){
					return false;
				}

				$last = $this->parameters[$position];
				continue;
			}

			// Candidates can accept additional args that are not in the prototype as long as they are not mandatory
			if(!$parameter->isOptional && !$parameter->isVariadic){
				return false;
			}

			// If the last arg of the prototype is variadic, any additional args the candidate accepts must satisfy it
			if($last !== null && $last->isVariadic && !$last->isSatisfiedBy($parameter)){
				return false;
			}
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function __toString() : string{
		$string = 'function ';

		if($this->returnInfo->byReference){
			$string .= '& ';
		}

		$string .= '( ';

		$i = $o = 0;
		$l = count($this->parameters) - 1;
		for(; $i < $l; $i++){
			$string .= $this->parameters[$i];

			if($o === 0 && !($this->parameters[$i + 1]->isOptional)){
				$string .= ', ';
				continue;
			}

			$string .= ' [, ';
			$o++;
		}

		if(isset($this->parameters[$l])){
			$string .= $this->parameters[$i] . ' ';
		}

		if($o !== 0){
			$string .= str_repeat(']', $o) . ' ';
		}

		$string .= ')';

		if($this->returnInfo->type !== null){
			$string .= ' : ' . $this->returnInfo->type->stringify();
		}

		return $string;
	}
}
