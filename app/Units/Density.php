<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class Density extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('kilogram per cubic meter', ['kg/m³'], fn($v) => $v, fn($v) => $v);
		static::addUnit('gram per cubic centimeter', ['g/cm³'], fn($v) => $v * 1000, fn($v) => $v / 1000);
	}
}
