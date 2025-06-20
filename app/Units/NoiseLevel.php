<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;
use PhpUnitsOfMeasure\UnitOfMeasure;

class NoiseLevel extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit(new UnitOfMeasure(
			'decibel',
			fn($v) => $v,
			fn($v) => $v,
			['dB']
		));

		static::addUnit(new UnitOfMeasure(
			'A-weighted decibel',
			fn($v) => $v,
			fn($v) => $v,
			['dB(A)', 'dBA']
		));

		static::addUnit(new UnitOfMeasure(
			'C-weighted decibel',
			fn($v) => $v,
			fn($v) => $v,
			['dB(C)', 'dBC']
		));

		static::addUnit(new UnitOfMeasure(
			'sound pressure level',
			fn($v) => $v,
			fn($v) => $v,
			['dB SPL', 'SPL']
		));
	}
}
