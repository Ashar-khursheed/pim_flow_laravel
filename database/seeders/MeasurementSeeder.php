<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MeasurementSeeder extends Seeder
{
	public function run(): void
	{

		$types = [
			'Length', 'Mass', 'Volume', 'Temperature',
			'Time', 'Speed', 'Area', 'Energy', 'Pressure', 'Force',
			'Power', 'Energy Consumption', 'Electrical Potential',
            'Electric Current', 'Frequency', 'Battery Capacity', 'Rotational Speed',
			'Noise Level', 'Humidity', 'Density', 'Nutrition Facts',
		];

		foreach ($types as $type) {
			DB::table('measurement_types')->updateOrInsert(
				['name' => $type],
				['updated_at' => now(), 'created_at' => now()]
			);
		}


		$createdBy = 1; // adjust to actual user ID
		$typeMap = DB::table('measurement_types')->pluck('id', 'name');

		$units = [
			'Length' => [
				['meter', 'm'],
				['kilometer', 'km'],
				['centimeter', 'cm'],
				['millimeter', 'mm'],
				['micrometer', 'µm'],
				['nanometer', 'nm'],
				['mile', 'mi'],
				['yard', 'yd'],
				['foot', 'ft'],
				['inch', 'in'],
			],
			'Mass' => [
				['kilogram', 'kg'],
				['gram', 'g'],
				['milligram', 'mg'],
				['metric tonne', 't'],
				['pound', 'lb'],
				['ounce', 'oz'],
			],
			'Volume' => [
				['liter', 'l'],
				['milliliter', 'ml'],
				['cubic meter', 'm³'],
				['gallon (US)', 'gal'],
				['quart', 'qt'],
				['pint', 'pt'],
				['cup', 'cup'],
				['fluid ounce', 'fl oz'],
				['tablespoon', 'tbsp'],
				['teaspoon', 'tsp'],
			],
			'Temperature' => [
				['celsius', 'C'],
				['kelvin', 'K'],
				['fahrenheit', 'F'],
			],
			'Time' => [
				['second', 's'],
				['minute', 'min'],
				['hour', 'h'],
				['day', 'day'],
			],
			'Speed' => [
				['meter per second', 'm/s'],
				['kilometer per hour', 'km/h'],
				['mile per hour', 'mph'],
				['knot', 'kn'],
			],
			'Area' => [
				['square meter', 'm²'],
				['square kilometer', 'km²'],
				['square centimeter', 'cm²'],
				['square millimeter', 'mm²'],
				['hectare', 'ha'],
				['acre', 'ac'],
				['square foot', 'ft²'],
				['square yard', 'yd²'],
				['square inch', 'in²'],
			],
			'Energy' => [
				['joule', 'J'],
				['kilojoule', 'kJ'],
				['calorie', 'cal'],
				['kilocalorie', 'kcal'],
				['watt hour', 'Wh'],
				['kilowatt hour', 'kWh'],
			],
			'Pressure' => [
				['pascal', 'Pa'],
				['kilopascal', 'kPa'],
				['bar', 'bar'],
				['psi', 'psi'],
				['atmosphere', 'atm'],
				['torr', 'torr'],
			],
			'Force' => [
				['newton', 'N'],
				['dyne', 'dyn'],
				['pound-force', 'lbf'],
			],
			'Power' => [
				['watt', 'W'],
				['kilowatt', 'kW'],
				['milliwatt', 'mW'],
				['horsepower', 'HP'],
			],
			'Energy Consumption' => [
				['kilowatt-hour', 'kWh'],
				['watt-hour', 'Wh'],
				['british thermal unit', 'BTU'],
			],
			'Electrical Potential' => [
				['volt', 'V'],
			],
			'Electric Current' => [
				['ampere', 'A'],
			],
			'Frequency' => [
				['hertz', 'Hz'],
			],
			'Battery Capacity' => [
				['ampere-hour', 'Ah'],
				['milliampere-hour', 'mAh'],
			],
			'Rotational Speed' => [
				['revolutions per minute', 'RPM'],
			],
			'Noise Level' => [
				['decibel', 'dB'],
				['A-weighted decibel', 'dB(A)'],
				['C-weighted decibel', 'dB(C)'],
				['sound pressure level', 'dB SPL'],
			],
			'Humidity' => [
				['relative humidity', '% RH'],
			],
			'Density' => [
				['gram per cubic centimeter', 'g/cm³'],
				['kilogram per cubic meter', 'kg/m³'],
			],
			'Nutrition Facts' => [
				['kilocalorie', 'kcal'],
				['calorie', 'cal'],
				['kilojoule', 'kJ'],
				['gram', 'g'],
				['milligram', 'mg'],
				['microgram', 'µg'],
				['kilogram', 'kg'],
				['ounce', 'oz'],
				['pound', 'lb'],
				['millilitre', 'mL'],
				['litre', 'L'],
				['percent', '%'],
			],

		];

		foreach ($units as $typeName => $unitList) {
			$typeId = $typeMap[$typeName] ?? null;
			if (!$typeId) continue;

			foreach ($unitList as [$name, $symbol]) {
				DB::table('measurement_units')->updateOrInsert(
					[
						'measurement_type_id' => $typeId,
						'symbol' => $symbol
					],
					[
						'name' => $name,
						'created_by' => $createdBy,
						'updated_at' => now(),
						'created_at' => now(),
					]
				);
			}
		}
	}
}
