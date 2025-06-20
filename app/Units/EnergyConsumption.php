<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class EnergyConsumption extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'kilowatt-hour',
			fn($v) => $v,
			fn($v) => $v,
			['kWh']
		));

		static::addUnit(new UnitOfMeasure(
			'watt-hour',
			fn($v) => $v / 1000,
			fn($v) => $v * 1000,
			['Wh']
		));

		static::addUnit(new UnitOfMeasure(
			'megajoule',
			fn($v) => $v * 0.277778,
			fn($v) => $v / 0.277778,
			['MJ']
		));
	}
}
