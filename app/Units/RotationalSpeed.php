<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class RotationalSpeed extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('revolutions per minute', ['rpm'], fn($v) => $v, fn($v) => $v);
		static::addUnit('revolutions per second', ['rps'], fn($v) => $v * 60, fn($v) => $v / 60);
	}
}
