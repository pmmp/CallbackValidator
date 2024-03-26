<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\Type\BaseType;
use DaveRandom\CallbackValidator\Type\BuiltInType;
use DaveRandom\CallbackValidator\Type\IntersectionType;
use DaveRandom\CallbackValidator\Type\NamedType;
use DaveRandom\CallbackValidator\Type\UnionType;

final class MatchTester{
	/**
	 * Thou shalt not instantiate
	 */
	private function __construct(){}

	public static function isCovariant(?BaseType $acceptingType, ?BaseType $givenType) : bool{
		// If the super type is unspecified, anything is a match
		if($acceptingType === null || ($acceptingType instanceof NamedType && $acceptingType->type === BuiltInType::VOID)){
			return true;
		}

		// If the sub type is unspecified, nothing is a match
		if($givenType === null){
			return false;
		}

		//composite type acceptance:
		//super named type -> named, union (all parts must be accepted by super), intersection (at least 1 part must be accepted by super)
		//super union -> named (must be accepted by at least 1 super), union (all parts must be accepted by at least 1 super), intersection (at least 1 part must be accepted by at least 1 super?)
		//super intersection -> named (must be accepted by all supers), union (all parts must be accepted by all supers), intersection (all parts must be accepted by all supers)

		//given union is handled the same no matter what the accepting type is
		//ensure all parts are covariant with the accepting type
		if($givenType instanceof UnionType){
			foreach($givenType->types as $type){
				if(!self::isCovariant($acceptingType, $type)){
					return false;
				}
			}

			return true;
		}

		if($acceptingType instanceof NamedType){
			//at least 1 part of a given intersection must be covariant with the accepting type
			//given intersection can only be compared with a named type to validate variance - the parts cannot
			//be individually tested against a composite type
			if($givenType instanceof IntersectionType){
				foreach($givenType->types as $type){
					if(self::isCovariant($acceptingType, $type)){
						return true;
					}
				}

				return false;
			}

			if($givenType instanceof NamedType){
				$acceptingTypeName = $acceptingType->type;
				$givenTypeName = $givenType->type;
				if($acceptingTypeName === $givenTypeName){
					// Exact match
					return true;
				}

				if($acceptingTypeName === BuiltInType::MIXED && $givenTypeName !== BuiltInType::VOID){
					//anything is covariant with mixed except void
					return true;
				}

				if($acceptingTypeName === BuiltInType::FLOAT && $givenTypeName === BuiltInType::INT){
					//int is covariant with float even in strict mode
					return true;
				}

				// Check iterable
				if($acceptingTypeName === BuiltInType::ITERABLE){
					return $givenTypeName === BuiltInType::ARRAY
						|| $givenTypeName === \Traversable::class
						|| \is_subclass_of($givenTypeName, \Traversable::class);
				}

				// Check callable
				if($acceptingTypeName === BuiltInType::CALLABLE){
					return $givenTypeName === \Closure::class
						|| \method_exists($givenTypeName, '__invoke')
						|| \is_subclass_of($givenTypeName, \Closure::class);
				}

				if($acceptingTypeName === BuiltInType::OBJECT){
					//a class type is covariant with object
					return !$givenTypeName instanceof BuiltInType;
				}

				return is_string($givenTypeName) && is_string($acceptingTypeName) && \is_subclass_of($givenTypeName, $acceptingTypeName);
			}

			throw new \AssertionError("Unhandled reflection type " . get_class($givenType));
		}

		if($acceptingType instanceof UnionType){
			//accepting union - the given type must be covariant with at least 1 part
			foreach($acceptingType->types as $type){
				if(self::isCovariant($type, $givenType)){
					return true;
				}
			}

			return false;
		}

		if($acceptingType instanceof IntersectionType){
			//accepting intersection - the given type must be covariant with all parts
			foreach($acceptingType->types as $type){
				if(!self::isCovariant($type, $givenType)){
					return false;
				}
			}

			return true;
		}

		return false;
	}
}
