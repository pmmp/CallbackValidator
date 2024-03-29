<?php declare(strict_types=1);

namespace DaveRandom\CallbackValidator;

use DaveRandom\CallbackValidator\BuiltInType;

final class MatchTester{
	/**
	 * Thou shalt not instantiate
	 */
	private function __construct(){}

	public static function isCovariant(?\ReflectionType $acceptingType, ?\ReflectionType $givenType) : bool{
		// If the super type is unspecified, anything is a match
		if($acceptingType === null || ($acceptingType instanceof \ReflectionNamedType && $acceptingType->getName() === BuiltInType::VOID->value)){
			return true;
		}

		// If the sub type is unspecified, nothing is a match
		if($givenType === null){
			return false;
		}

		// given type cannot accept null if the accepting type does not
		// we don't want to consider allowsNull except at the top level
		if(!$acceptingType->allowsNull() && $givenType->allowsNull()){
			return false;
		}

		return self::isCompositeTypeCovariant($acceptingType, $givenType);
	}

	private static function isCompositeTypeCovariant(\ReflectionType $acceptingType, \ReflectionType $givenType) : bool{
		//composite type acceptance:
		//super named type -> named, union (all parts must be accepted by super), intersection (at least 1 part must be accepted by super)
		//super union -> named (must be accepted by at least 1 super), union (all parts must be accepted by at least 1 super), intersection (at least 1 part must be accepted by at least 1 super?)
		//super intersection -> named (must be accepted by all supers), union (all parts must be accepted by all supers), intersection (all parts must be accepted by all supers)

		//given union is handled the same no matter what the accepting type is
		//ensure all parts are covariant with the accepting type
		if($givenType instanceof \ReflectionUnionType){
			foreach($givenType->getTypes() as $type){
				if(!self::isCompositeTypeCovariant($acceptingType, $type)){
					return false;
				}
			}

			return true;
		}

		if($acceptingType instanceof \ReflectionNamedType){
			//at least 1 part of a given intersection must be covariant with the accepting type
			//given intersection can only be compared with a named type to validate variance - the parts cannot
			//be individually tested against a composite type
			if($givenType instanceof \ReflectionIntersectionType){
				foreach($givenType->getTypes() as $type){
					if(self::isCompositeTypeCovariant($acceptingType, $type)){
						return true;
					}
				}

				return false;
			}

			if($givenType instanceof \ReflectionNamedType){
				$acceptingTypeName = $acceptingType->getName();
				$givenTypeName = $givenType->getName();
				if($acceptingTypeName === $givenTypeName){
					// Exact match
					return true;
				}

				if($acceptingTypeName === BuiltInType::MIXED->value && $givenTypeName !== BuiltInType::VOID->value){
					//anything is covariant with mixed except void
					return true;
				}

				if($acceptingTypeName === BuiltInType::FLOAT->value && $givenTypeName === BuiltInType::INT->value){
					//int is covariant with float even in strict mode
					return true;
				}

				// Check iterable
				if($acceptingTypeName === BuiltInType::ITERABLE->value){
					return $givenTypeName === BuiltInType::ARRAY->value
						|| $givenTypeName === \Traversable::class
						|| \is_subclass_of($givenTypeName, \Traversable::class);
				}

				// Check callable
				if($acceptingTypeName === BuiltInType::CALLABLE->value){
					return $givenTypeName === \Closure::class
						|| \method_exists($givenTypeName, '__invoke')
						|| \is_subclass_of($givenTypeName, \Closure::class);
				}

				if($acceptingTypeName === BuiltInType::OBJECT->value){
					//a class type is covariant with object
					return BuiltInType::tryFrom($givenTypeName) === null;
				}

				return is_string($givenTypeName) && is_string($acceptingTypeName) && \is_subclass_of($givenTypeName, $acceptingTypeName);
			}

			throw new \AssertionError("Unhandled reflection type " . get_class($givenType));
		}

		if($acceptingType instanceof \ReflectionUnionType){
			//accepting union - the given type must be covariant with at least 1 part
			foreach($acceptingType->getTypes() as $type){
				if(self::isCompositeTypeCovariant($type, $givenType)){
					return true;
				}
			}

			return false;
		}

		if($acceptingType instanceof \ReflectionIntersectionType){
			//accepting intersection - the given type must be covariant with all parts
			foreach($acceptingType->getTypes() as $type){
				if(!self::isCompositeTypeCovariant($type, $givenType)){
					return false;
				}
			}

			return true;
		}

		return false;
	}
}
