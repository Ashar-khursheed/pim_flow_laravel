<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class BatteryCapacity extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'ampere-hour',
			fn($v) => $v,
			fn($v) => $v,
			['Ah']
		));

		static::addUnit(new UnitOfMeasure(
			'milliampere-hour',
			fn($v) => $v / 1000,
			fn($v) => $v * 1000,
			['mAh']
		));
	}
}
