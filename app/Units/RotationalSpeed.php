<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class RotationalSpeed extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'revolutions per minute',
			fn($v) => $v,
			fn($v) => $v,
			['rpm']
		));

		static::addUnit(new UnitOfMeasure(
			'revolutions per second',
			fn($v) => $v * 60,
			fn($v) => $v / 60,
			['rps']
		));
	}
}
