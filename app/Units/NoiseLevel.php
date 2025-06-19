<?php

namespace App\Units;

use PhpUnitsOfMeasure\AbstractPhysicalQuantity;

class NoiseLevel extends AbstractPhysicalQuantity
{
	protected static $unitDefinitions;

	protected static function initialize()
	{
		static::addUnit('decibel', ['dB'], fn($v) => $v, fn($v) => $v);
		static::addUnit('A-weighted decibel', ['dB(A)', 'dBA'], fn($v) => $v, fn($v) => $v);
		static::addUnit('C-weighted decibel', ['dB(C)', 'dBC'], fn($v) => $v, fn($v) => $v);
		static::addUnit('sound pressure level', ['dB SPL', 'SPL'], fn($v) => $v, fn($v) => $v);
	}
}
