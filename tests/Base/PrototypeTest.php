<?php

namespace DaveRandom\CallbackValidator\Test\Base;

use DaveRandom\CallbackValidator\Prototype;
use DaveRandom\CallbackValidator\Test\Base\Fixtures\Interface1;
use DaveRandom\CallbackValidator\Test\Base\Fixtures\Interface2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrototypeTest extends TestCase{

	public static function returnCovarianceProvider() : \Generator{
		//anything is covariant with void in a return type, at least for the purposes of callbacks
		yield [function() : void{}, function(){}, true, "given returns anything or nothing, which is allowed by a void type (returning something won't break anything)"];
		yield [function() : void{}, function() : void{}, true, "same type"];
		yield [function(){}, function() : void{}, true, "unspecified type allows not returning anything (the same as void)"];

		yield [function() : int{ die(); }, function(){}, false, "given function might return nothing, which is not allowed by an int type"];
		yield [function() : mixed{ die(); }, function(){}, false, "given function might return nothing, which is not allowed by a mixed type"];

		yield [function() : int|string{ die(); }, function() : int{ die(); }, true, "given function returns a type which is covariant with required"];
		yield [function() : int{ die(); }, function() : int|string{ die(); }, false, "given function returns a type which is not covariant with required"];
		yield [function() : float{ die(); }, function() : int{ die(); }, true, "int is covariant with float"];

		yield [function() : Interface1{ die(); }, function() : Interface1&Interface2{ die(); }, true, "covariant intersection type"];
		yield [function() : Interface1&Interface2{ die(); }, function() : Interface1{ die(); }, false, "given type not covariant with required intersection"];

		yield [function() : mixed{ die(); }, function() : int{ die(); }, true, "int is covariant with mixed"];
		yield [function() : mixed{ die(); }, function() : int|string{ die(); }, true, "int|string is covariant with mixed"];
		yield [function() : mixed{ die(); }, function() : Interface1&Interface2{ die(); }, true, "intersection is covariant with mixed"];
		yield [function() : mixed{ die(); }, function() : void{}, false, "void is not covariant with mixed"];
	}

	public static function paramContravarianceProvider() : \Generator{
		yield [function(string $a) : void{}, function($a) : void{}, true, "given function accepts more types than required"];
		yield [function($a) : void{}, function(string $a) : void{}, false, "given function must accept at least the types required (implicit mixed in this case)"];

		//number of parameters must be <= required
		yield [function(int $a) : void{}, function(int $a) : void{}, true, "same number of parameters"];
		yield [function(int $a, int $b) : void{}, function(int $a) : void{}, true, "given function accepts fewer parameters than required"];
		yield [function(int $a) : void{}, function(int $a, int $b) : void{}, false, "given function requires too many parameters"];
		yield [function(int $a) : void{}, function(int $a, int $b = 0) : void{}, true, "given function's extra parameters are optional"];

		//parameter types must be covariant
		yield [function(int $a) : void{}, function(string $a) : void{}, false, "given function's accepted types are not covariant with required"];
		yield [function(int $a) : void{}, function(int|string $a) : void{}, true, "given function accepts a union which is covariant with required"];
		yield [function(int|string $a) : void{}, function(int $a) : void{}, false, "given function's union is not contravariant with required"];

		yield [function(Interface1&Interface2 $a) : void{}, function(Interface1 $a) : void{}, true, "parameter is contravariant with given intersection"];
		yield [function(Interface1&Interface2 $a) : void{}, function(Interface1&Interface2 $a) : void{}, true, "same type"];
		yield [function(Interface1 $a) : void{}, function(Interface1&Interface2 $a) : void{}, false, "intersection given is not contravariant with required"];

		yield [function(int $a) : void{}, function(float $a) : void{}, true, "float is contravariant with int"];

		yield [function(int $a = 0) : void{}, function(int $a) : void{}, false, "required parameter cannot satisfy optional"];
		yield [function(int $a = 0) : void{}, function(int ...$a) : void{}, true, "variadic parameter can satisfy optional"];
		yield [function(int ...$a) : void{}, function(int $a) : void{}, false, "required parameter cannot satisfy variadic"];
		yield [function(int ...$a) : void{}, function(int $a = 0) : void{}, true, "optional parameter can satisfy variadic"];

		yield [function(int $a) : void{}, function(int ...$a) : void{}, true, "variadic can satisfy required"];
		yield [function(int $a) : void{}, function(int $a = 0) : void{}, true, "optional can satisfy required"];

		yield [function(iterable $a) : void{}, function(array $a) : void{}, false, "given function must accept any type of iterable"];

		//null is handled in a weird way by PHP, so we need to cover the edge cases
		yield [function(int|null $a) : void{}, function(int $a) : void{}, false, "given function does not accept null"];
		yield [function(int $a) : void{}, function(int|null $a) : void{}, true, "given function accepts null, which is not required by the signature"];

		yield [function(int $a) : void{}, function(mixed $a) : void{}, true, "mixed is contravariant with int"];
		yield [function(int|string $a) : void{}, function(mixed $a) : void{}, true, "mixed is contravariant with int|string"];
		yield [function(Interface1&Interface2 $a) : void{}, function(mixed $a) : void{}, true, "mixed is contravariant with intersection"];

		yield [function(mixed $a) : void{}, function($a) : void{}, true, "unspecified parameter type is equivalent to mixed"];

		//TODO: this variance ought to work on parameters but not return types
		//this will require extra information in MatchTester to work correctly
		//yield [function($a) : void{}, function(mixed $a) : void{}, true, "mixed is equivalent to unspecified parameter type"];
	}

	#[DataProvider('returnCovarianceProvider')]
	#[DataProvider('paramContravarianceProvider')]
	public function testCompatibility(\Closure $required, \Closure $given, bool $matches, string $reason) : void{
		$required = Prototype::fromClosure($required);
		$given = Prototype::fromClosure($given);

		$serializedRequire = (string) $required;
		$serializedGiven = (string) $given;
		self::assertSame($required->isSatisfiedBy($given), $matches, $reason . " ($serializedRequire, $serializedGiven)");
	}
}
