<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class BatteryCapacity extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('ampere-hour', ['Ah'], fn($v) => $v, fn($v) => $v);
		static::addUnit('milliampere-hour', ['mAh'], fn($v) => $v / 1000, fn($v) => $v * 1000);
	}
}
