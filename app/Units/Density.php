<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class Density extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'kilogram per cubic meter',
			fn($v) => $v,
			fn($v) => $v,
			['kg/m³']
		));

		static::addUnit(new UnitOfMeasure(
			'gram per cubic centimeter',
			fn($v) => $v * 1000,
			fn($v) => $v / 1000,
			['g/cm³']
		));
	}
}
