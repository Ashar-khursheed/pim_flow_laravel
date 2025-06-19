<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class ElectricPotential extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('volt', ['V'], fn($v) => $v, fn($v) => $v);
		static::addUnit('millivolt', ['mV'], fn($v) => $v / 1000, fn($v) => $v * 1000);
	}
}
