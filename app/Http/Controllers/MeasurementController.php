<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\MeasurementType;
use App\Models\MeasurementUnit;

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
	 *     path="/api/measurement-units/{type_id}",
	 *     summary="Get measurement units by type",
	 *     description="Returns a list of measurement units grouped or filtered by type",
	 *     tags={"Measurement"},
	 *     @OA\Parameter(
	 *         name="type_id",
	 *         in="path",
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
}
