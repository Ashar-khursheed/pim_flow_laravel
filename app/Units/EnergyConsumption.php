<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class EnergyConsumption extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('kilowatt-hour', ['kWh'], fn($v) => $v, fn($v) => $v);
		static::addUnit('watt-hour', ['Wh'], fn($v) => $v / 1000, fn($v) => $v * 1000);
		static::addUnit('megajoule', ['MJ'], fn($v) => $v * 0.277778, fn($v) => $v / 0.277778); // 1 MJ = 0.277778 kWh
	}
}
