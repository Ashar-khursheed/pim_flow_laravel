<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class NutritionEnergy extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('kilocalorie', ['kcal'], fn($v) => $v, fn($v) => $v);
		static::addUnit('kilojoule', ['kJ'], fn($v) => $v / 4.184, fn($v) => $v * 4.184); // 1 kcal = 4.184 kJ
	}
}
