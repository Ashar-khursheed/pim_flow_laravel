<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class ElectricCurrent extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'ampere',
			fn($v) => $v,
			fn($v) => $v,
			['A']
		));

		static::addUnit(new UnitOfMeasure(
			'milliampere',
			fn($v) => $v / 1000,
			fn($v) => $v * 1000,
			['mA']
		));
	}
}
