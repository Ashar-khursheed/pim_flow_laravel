<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class TemporaryCategoryController extends BaseController
{	 
	/**
	 * @OA\Get(
	 *     path="/api/temporaryCategories/{id}",
	 *     summary="Get Temporary category details",
	 *     description="Returns details of a specific category",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Category ID",
	 *         @OA\Schema(
	 *             type="integer"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="category", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id): JsonResponse
	{
		if (!auth()->user()->can('show category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			$category = Category::findOrFail($id);

			// Load children if any
			$category->load('children');

			$lastChildIds = !empty($category->last_child)
					? array_map('intval', explode(',', $category->last_child))
					: [];
		 
				if (!empty($lastChildIds)) {
					$category->last_children = Category::whereIn('id', $lastChildIds)
						->get(['id', 'name', 'slug']);
				} else {
					$category->last_children = collect();
				}

			return response()->json([
				'success' => true,
				'category' => $category
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		}
	}

	/**
	 * Update the specified category in storage. 
	 * @OA\Post(
	 *     path="/api/temporaryCategories/{id}",
	 *     summary="Update existing category (uses POST due to image upload)",
	 *     description="Updates an existing category with the given details. Uses POST instead of PUT because of file uploads.",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer"),
	 *         description="Category ID"
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name"},
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="parent_id", type="integer", example=0),                   
	 *                 @OA\Property(property="slug", type="string", example="electronics"),
	 * 				   @OA\Property(property="last_child", type="string", example="635,665,686")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category updated successfully"),
	 *             @OA\Property(property="category", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Validation error"),
	 *             @OA\Property(property="errors", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{  
		if (!auth()->user()->can('update category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$category = Category::findOrFail($id);

		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:191',
			'parent_id' => [
				'nullable',
				'integer',
				function ($attribute, $value, $fail) use ($id) {
					if ($value != 0 && !Category::where('id', $value)->where('id', '!=', $id)->exists()) {
						$fail('The selected parent category is invalid.');
					}
				}
			],
			'slug' => 'nullable|string|max:191|unique:categories,slug,' . $category->id,
			'last_child' => 'nullable|string|regex:/^(\d+)(,\d+)*$/',
		]);

		$disk = 's3';

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$data = $validator->validated();

			if (empty($data['slug'])) {
				$data['slug'] = Str::slug($data['name']);
			}


			$category->update($data);

			Cache::forget('all_categories');

			return response()->json([
				'success' => true,
				'message' => 'Category updated successfully',
				'category' => $category
			], 200);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'Failed to update category',
				'error' => $e->getMessage()
			], 500);
		}
	}
 
 
 
}