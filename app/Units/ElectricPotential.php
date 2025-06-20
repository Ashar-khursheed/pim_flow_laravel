<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class ElectricPotential extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'volt',
			fn($v) => $v,
			fn($v) => $v,
			['V']
		));

		static::addUnit(new UnitOfMeasure(
			'millivolt',
			fn($v) => $v / 1000,
			fn($v) => $v * 1000,
			['mV']
		));

	}
}
