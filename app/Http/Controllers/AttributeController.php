<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attribute;

class AttributeController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */

	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 */
	/**
	 * @OA\Post(
	 *     path="/api/attributes",
	 *     summary="Create a new attribute",
	 *     description="Creates a new attribute with name, code, and type.",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type"},
	 *             @OA\Property(property="name", type="string", example="Color"),
	 *             @OA\Property(property="code", type="string", example="color"),
	 *             @OA\Property(property="type", type="string", example="text")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name",
			'code' => "required|unique:attributes,code",
			'type' => "required"
		]);

		$attribute = new Attribute();
		$attribute->name = $request->name;
		$attribute->code = $request->code;
		$attribute->type = $request->type;
		$attribute->created_at = now();
		$attribute->updated_at = now();
		$attribute->save();

		return response()->json([
			'success' => true,
			'message' => 'Attribute created successfully',
			'user' => $attribute
		]);
	}

	/**
	 * Display the specified resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/attributes/{attribute_id}",
	 *     summary="Get attribute details",
	 *     description="Fetches attribute details based on the given attribute ID.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="attribute_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($attributeId)
	{
		$attribute = Attribute::find($attributeId);
		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => 'Attribute does not exist.'
			]);
		}

		$attribute->validations = json_decode($attribute->validations);

		return response()->json([
			'success' => true,
			'message' => 'Attribute detail',
			'attribute' => $attribute
		]);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Attribute $attribute)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	/**
	 * @OA\Put(
	 *     path="/api/attributes/{id}",
	 *     summary="Update an existing attribute",
	 *     description="Updates an attribute's details, including name, code, type, required status, and validations.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type", "is_required", "validations"},
	 *             @OA\Property(property="name", type="string", example="Size"),
	 *             @OA\Property(property="code", type="string", example="size"),
	 *             @OA\Property(property="type", type="string", example="dropdown"),
	 *             @OA\Property(property="is_required", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="validations",
	 *                 type="object",
	 *                 @OA\Property(property="min", type="integer", example=250),
	 *                 @OA\Property(property="max", type="integer", example=260),
	 *                 @OA\Property(property="required", type="boolean", example=true)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Attribute updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Attribute updated successfully"),
	 *             @OA\Property(
	 *                 property="attribute",
	 *                 type="object",
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="name", type="string", example="Size"),
	 *                 @OA\Property(property="code", type="string", example="size"),
	 *                 @OA\Property(property="type", type="string", example="dropdown"),
	 *                 @OA\Property(property="is_required", type="boolean", example=true),
	 *                 @OA\Property(
	 *                     property="validations",
	 *                     type="array",
	 *                     @OA\Items(type="string"),
	 *                     example={"required", "max:255"}
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function update(Request $request, $attributeId)
	{
		$attribute = Attribute::find($attributeId);
		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => 'Attribute does not exist.'
			]);
		}

		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name,".$attributeId,
			'code' => "required|unique:attributes,code,".$attributeId,
			'type' => "required"
		]);

		$input = $request->all();

		if ($input['validations']) {
			$attribute->validations = json_encode($input['validations']);
			unset($input['validations']); /* Remove processed field */
		}

		/* Assign remaining valid fields to the attribute */
		foreach ($input as $key => $value) {
			$attribute->$key = $value;
		}

		/* Save the attribute */
		$attribute->save();

		/* Return success response */
		return response()->json([
			'success' => true,
			'message' => 'Attribute updated successfully.',
			'attribute' => $attribute->toArray()
		]);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Attribute $attribute)
	{
		//
	}
}
