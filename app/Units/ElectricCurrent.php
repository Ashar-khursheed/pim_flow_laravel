<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class ElectricCurrent extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('ampere', ['A'], fn($v) => $v, fn($v) => $v);
		static::addUnit('milliampere', ['mA'], fn($v) => $v / 1000, fn($v) => $v * 1000);
	}
}
