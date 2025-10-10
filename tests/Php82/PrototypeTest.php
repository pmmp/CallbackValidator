<?php

namespace DaveRandom\CallbackValidator\Test\Php82;

use DaveRandom\CallbackValidator\Prototype;
use DaveRandom\CallbackValidator\Test\Base\Fixtures\Interface1;
use DaveRandom\CallbackValidator\Test\Base\Fixtures\Interface2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrototypeTest extends TestCase{

	public static function returnCovarianceProvider() : \Generator{
		//DNF types - PHP 8.2+ only
		yield [function() : (Interface1&Interface2)|string{ die(); }, function() : Interface1{ die(); }, false, "given type not covariant with any part of required union"];
		yield [function() : (Interface1&Interface2)|string{ die(); }, function() : Interface1&Interface2{ die(); }, true, "given type covariant with at least 1 part of required union"];
	}

	public static function paramContravarianceProvider() : \Generator{
		//DNF types - PHP 8.2+ only
		yield [function((Interface1&Interface2)|string $a) : void{}, function(Interface1&Interface2 $a) : void{}, false, "given type must accept string"];
		yield [function(Interface1&Interface2 $a) : void{}, function((Interface1&Interface2)|string $a) : void{}, true, "given type accepts string, which is not required by the signature"];

		yield [function((Interface1&Interface2)|string $a) : void{}, function(mixed $a) : void{}, true, "mixed is contravariant with DNF type"];
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
