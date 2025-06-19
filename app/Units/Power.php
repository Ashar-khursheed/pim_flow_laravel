<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class Power extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('watt', ['W'], fn($v) => $v, fn($v) => $v);
		static::addUnit('kilowatt', ['kW'], fn($v) => $v * 1000, fn($v) => $v / 1000);
		static::addUnit('milliwatt', ['mW'], fn($v) => $v / 1000, fn($v) => $v * 1000);
		static::addUnit('horsepower', ['HP'], fn($v) => $v * 745.699872, fn($v) => $v / 745.699872);
	}
}
