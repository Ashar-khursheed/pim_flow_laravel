<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\MeasurementType;
use App\Models\MeasurementUnit;
use App\Models\Category;

class MeasurementController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/measurement-types",
	 *     summary="Get all measurement types",
	 *     description="Returns a list of measurement types",
	 *     tags={"Measurement"},
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getMeasurementTypes(Request $request)
	{
		$records = MeasurementType::orderBy('name', 'asc')->get([
			'id', 'name'
		]);
		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/measurement-units",
	 *     summary="Get measurement units by type",
	 *     description="Returns a list of measurement units grouped or filtered by type",
	 *     tags={"Measurement"},
	 *     @OA\Parameter(
	 *         name="type_id",
	 *         in="query",
	 *         description="ID of the measurement type",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getMeasurementUnitsByType(Request $request)
	{
		$request->validate([
			'type_id' => 'required|exists:measurement_types,id',
		]);

		$records = MeasurementUnit::where('measurement_type_id', $request->type_id)->orderBy('name', 'asc')->get([
			'id', 'name'
		]);
		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/measurement-type-categories",
	 *     summary="Get categories by measurement type",
	 *     description="Returns a list of categories by measurement type",
	 *     tags={"Measurement"},
	 *     @OA\Parameter(
	 *         name="type_id",
	 *         in="query",
	 *         description="ID of the measurement type",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getCategoriesByMeasurementType(Request $request)
	{
		$request->validate([
			'type_id' => 'required|exists:measurement_types,id',
		]);

		$typeId = $request->type_id;

		$categories = Category::whereHas('categoryAttributeGroups.groupsAttributes.measurementUnits.type', function ($query) use ($typeId) {
			$query->where('id', $typeId);
		})
		->doesntHave('children')
		->select('id', 'name')
		->distinct()
		->orderBy('name', 'asc')
		->get();

		return response()->json([
			'success' => true,
			'data' => $categories
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/measurement-units/save-translation",
	 *     summary="Generate or update measurement unit translation",
	 *     description="This endpoint generates or updates translations for a measurement unit.",
	 *     tags={"Measurement"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"locale"},
	 *             @OA\Property(property="id", type="integer", example=1, description="ID of the measurement type"),
	 *             @OA\Property(property="locale", type="string", example="ar", description="Locale code for translation"),
	 *             @OA\Property(
	 *                 property="measurement_units",
	 *                 type="object",
	 *                 example={"1": "صغير", "2": "متوسط", "3": "كبير"},
	 *                 description="Key-value pairs of measurement unit translations (key = measurement_unit_id)"
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function saveTranslation(Request $request)
	{
		/* Validate request data */
		$validated = $request->validate([
			'id' => 'required|exists:measurement_types,id',
			'locale' => 'required|string|in:ar',
			'measurement_units' => 'required|array',
			'measurement_units.*' => 'nullable|string'
		]);

		DB::beginTransaction();

		try {
			$measurementType = MeasurementType::find($validated['id']);
			$locale = $validated['locale'];

			foreach ($validated['measurement_units'] as $unitId => $translatedValue) {

				$measurementUnit = MeasurementUnit::find($unitId);

				if (!$measurementUnit) {
					continue; // Skip invalid IDs
				}

				// Save translation
				$measurementUnit->translateOrNew($locale)->name_tr = $translatedValue;
				$measurementUnit->save();
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("Translations updated successfully."),
				'data' => $measurementType->fresh()->load('units.translations'),
			]);

		} catch (\Exception $e) {

			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
