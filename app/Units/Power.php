<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class Power extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'watt',
			fn($v) => $v,
			fn($v) => $v,
			['W']
		));

		static::addUnit(new UnitOfMeasure(
			'kilowatt',
			fn($v) => $v / 1000,
			fn($v) => $v * 1000,
			['kW']
		));

		static::addUnit(new UnitOfMeasure(
			'milliwatt',
			fn($v) => $v / 1_000_000,
			fn($v) => $v * 1_000_000,
			['mW']
		));

		static::addUnit(new UnitOfMeasure(
			'horsepower',
			fn($v) => $v / 745.699872,
			fn($v) => $v * 745.699872,
			['HP']
		));
	}
}
