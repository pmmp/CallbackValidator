<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\Type\BaseType;
use DaveRandom\CallbackValidator\Type\BuiltInType;
use DaveRandom\CallbackValidator\Type\IntersectionType;
use DaveRandom\CallbackValidator\Type\NamedType;
use DaveRandom\CallbackValidator\Type\UnionType;
use function array_map;
use function get_class;

final class CallbackType{
	private ReturnInfo $returnInfo;

	/** @var ParameterInfo[] */
	private array $parameters;

	private int $requiredParameterCount;

	/**
	 * Given a callable, create the appropriate reflection
	 *
	 * This will accept things the PHP would fail to invoke due to scoping, but we can reflect them anyway. Do not add
	 * a callable type-hint or this behaviour will break!
	 *
	 * @param callable $target
	 *
	 * @return \ReflectionFunction|\ReflectionMethod
	 * @throws \ReflectionException
	 */
	private static function reflectCallable($target){
		if($target instanceof \Closure){
			return new \ReflectionFunction($target);
		}

		if(\is_array($target) && isset($target[0], $target[1])){
			return new \ReflectionMethod($target[0], $target[1]);
		}

		if(\is_object($target) && \method_exists($target, '__invoke')){
			return new \ReflectionMethod($target, '__invoke');
		}

		if(\is_string($target)){
			return \strpos($target, '::') !== false
				? new \ReflectionMethod($target)
				: new \ReflectionFunction($target);
		}

		throw new \UnexpectedValueException("Unknown callable type");
	}

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

	/**
	 * @param callable $callable
	 *
	 * @throws InvalidCallbackException
	 */
	public static function createFromCallable($callable) : CallbackType{
		try{
			$reflection = self::reflectCallable($callable);
		}catch(\ReflectionException $e){
			throw new InvalidCallbackException('Failed to reflect the supplied callable', 0, $e);
		}

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

		return new CallbackType($returnType, ...$parameters);
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
	 * @param callable $callable
	 * @throws InvalidCallbackException
	 */
	public function isSatisfiedBy($callable) : bool{
		$other = self::createFromCallable($callable);

		if(!$this->returnInfo->isSatisfiedBy($other->returnInfo)){
			return false;
		}

		if($other->requiredParameterCount > $this->requiredParameterCount){
			return false;
		}

		$last = null;

		foreach($other->parameters as $position => $parameter){
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
