<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class CategoryController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/categories",
	 *     summary="Get Category List",
	 *     description="Fetches a list of categories. If 'type' is set to 'Parent', only parent categories will be returned. If 'parent_id' is provided, it fetches all child categories of the given parent.",
	 *     tags={"Categories"},
	 *     @OA\Parameter(name="type", in="query", description="Filter categories by type.", @OA\Schema(type="string", enum={"All", "Super Parent", "Leaf Child"}, default="All")),
	 *     @OA\Parameter(name="parent_id", in="query", description="Fetch all child categories of a given parent_id", example=1, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"published", "draft", "pending"})),
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$searchableColumns = ['id', 'name'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Category::query();

		/* Filter by type */
		if ($request->type == 'Super Parent') {
			$recordsQuery = $recordsQuery->where('parent_id', 0);
		} elseif ($request->type == 'Leaf Child') {
			$recordsQuery = $recordsQuery->whereDoesntHave('children');
		} elseif ($request->has('parent_id') && is_numeric($request->parent_id)) {
			$recordsQuery = $recordsQuery->where('parent_id', (int) $request->parent_id);
		}

		/* Filter by status */
		if ($request->has('status') && in_array($request->status, ['published', 'draft', 'pending'])) {
			$recordsQuery = $recordsQuery->where('status', $request->status);
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
			}

			/* Apply sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');

			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id',
				'name',
				'parent_id',
				'description',
				'status',
				'order',
				'image',
				'is_featured',
				'icon',
				'icon_image',
				'slug',
				'last_child'
			]);
			$records->transform(function ($record) {

				if ($record->image) {
					if (strpos($record->image, 'http') === 0) {
						$record->image = $record->image;
					} else {
						$record->image = asset('storage/' . $record->image);
					}
				}
				if ($record->icon_image) {
					if (strpos($record->icon_image, 'http') === 0) {
						$record->icon_image = $record->icon_image;
					} else {
						$record->icon_image = asset('storage/' . $record->icon_image);
					}
				}
				$lastChildIds = !empty($record->last_child)
				? array_map('intval', explode(',', $record->last_child))
				: [];

				if (!empty($lastChildIds)) {
					$record->last_children = Category::whereIn('id', $lastChildIds)
					->get(['id', 'name', 'slug']);
				} else {
					$record->last_children = collect();
				}
				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id',
				'name',
				'parent_id',
				'order',
				'last_child'
			]);

			$records->transform(function ($record) {
				$lastChildIds = !empty($record->last_child)
				? array_map('intval', explode(',', $record->last_child))
				: [];

				if (!empty($lastChildIds)) {
					$record->last_children = Category::whereIn('id', $lastChildIds)
					->get(['id', 'name', 'slug']);
				} else {
					$record->last_children = collect();
				}
				return $record;
			});
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Category List',
			'categories' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/allcategories",
	 *     summary="Get All Categories",
	 *     description="Fetches a hierarchical list of categories. Each category includes its child categories recursively.",
	 *     tags={"Categories"},
	 *     @OA\Response(response=200, description="Successful operation", @OA\MediaType(mediaType="application/json")),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Unauthenticated.")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function allcategories(): JsonResponse
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$categories = Cache::remember('all_categories', 3600, function () {
			return Category::where('parent_id', 0)
			->with(['childrenRecursive'])
			->orderBy('order', 'asc')
			->get(['id', 'name', 'slug', 'order', 'parent_id']);
		});

		return response()->json([
			'success' => true,
			'message' => 'All Categories List',
			'categories' => $categories
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/categories",
	 *     summary="Create new category",
	 *     description="Creates a new category with the given details",
	 *     tags={"Categories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name"},
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="parent_id", type="integer", example=0),
	 *                 @OA\Property(property="description", type="string", example="Electronic products category"),
	 *                 @OA\Property(property="status", type="string", example="published", enum={"published", "draft", "pending"}),
	 *                 @OA\Property(property="order", type="integer", example=0),
	 *                 @OA\Property(property="is_featured", type="integer"),
	 *                 @OA\Property(property="icon", type="string"),
	 *                 @OA\Property(property="icon_image", type="string", format="binary"),
	 *                 @OA\Property(property="slug", type="string", example="electronics"),
	 *  			   @OA\Property(property="last_child", type="string", example="635,665,686"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Category created successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category created successfully"),
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
	public function store(Request $request): JsonResponse
	{
		if (!auth()->user()->can('add category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:191',
			'parent_id' => [
				'nullable',
				'integer',
				function ($attribute, $value, $fail) {
					if ($value != 0 && !Category::where('id', $value)->exists()) {
						$fail('The selected parent category is invalid.');
					}
				}
			],
			'description' => 'nullable|string',
			'status' => 'required|string|in:published,draft,pending',
			'order' => 'nullable|integer',
			'image' => 'nullable|image|mimes:jpeg,png,webp,jpg,gif|max:2048',
			'is_featured' => 'nullable|boolean',
			'website_ids' => 'nullable|string|max:255',
			'icon' => 'nullable|string|max:191',
			'icon_image' => 'nullable|file|image|mimes:webp,jpeg,png,jpg,gif|max:2048',
			'slug' => 'nullable|string|max:191|unique:categories,slug',
			'last_child' => 'nullable|string|regex:/^(\d+)(,\d+)*$/',
		]);

		$disk = 's3'; // or use config

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$data = $validator->validated();

			// Generate a slug if one isn't provided
			if (empty($data['slug'])) {
				$data['slug'] = Str::slug($data['name']);
			}



			if ($request->hasFile('image')) {
				$path = $request->file('image')->store('categories', $disk);
				$data['image'] = Storage::disk($disk)->url($path); // This returns full S3 URL
			}

			if ($request->hasFile('icon_image')) {
				$path = $request->file('icon_image')->store('categories/icons', $disk);
				$data['icon_image'] = Storage::disk($disk)->url($path); // No extra prefix
			}

			// Set default order as the last position if not specified
			if (!isset($data['order'])) {
				$parentId = $data['parent_id'] ?? 0;
				$lastOrder = Category::where('parent_id', $parentId)->max('order');
				$data['order'] = $lastOrder ? $lastOrder + 1 : 1;
			}

			if ($data['status'] == 'published') {
				return response()->json([
					'success' => false,
					'message' => 'At least 1 products must be assigned to the product family before it can be published.'
				]);
			}

			// Create the category
			$category = Category::create($data);

			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$category->translateOrNew('en')->name_tr = $request->name;
			}

			$category->save();

			// Clear cache
			Cache::forget('all_categories');


			return response()->json([
				'success' => true,
				'message' => 'Category created successfully',
				'category' => $category
			], 201);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'Failed to create category',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/categories/{id}",
	 *     summary="Get category details",
	 *     description="Returns details of a specific category",
	 *     tags={"Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Category ID",
	 *         @OA\Schema(
	 *             type="integer"
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Successful operation", @OA\MediaType(mediaType="application/json")),
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

			// Transform image paths to full URLs
			if ($category->image) {
				$category->image = $category->image;
			}

			if ($category->icon_image) {
				$category->icon_image = $category->icon_image;
			}

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
				'category' => $category->load('translations')
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
	 *
	 * Note: Although this is an update operation, the HTTP method is POST due to multipart/form-data limitations.
	 *
	 * @OA\Post(
	 *     path="/api/categories/{id}",
	 *     summary="Update existing category (uses POST due to image upload)",
	 *     description="Updates an existing category with the given details. Uses POST instead of PUT because of file uploads.",
	 *     tags={"Categories"},
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
	 *                 @OA\Property(property="description", type="string", example="Electronic products category"),
	 *                 @OA\Property(property="status", type="string", example="published", enum={"published", "draft", "pending"}),
	 *                 @OA\Property(property="order", type="integer", example=0),
	 *                 @OA\Property(property="is_featured", type="integer"),
	 *                 @OA\Property(property="icon", type="string"),
	 *                 @OA\Property(property="icon_image", type="string", format="binary"),
	 *                 @OA\Property(property="slug", type="string", example="electronics"),
	 *  			   @OA\Property(property="last_child", type="string", example="635,665,686"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Category updated successfully", @OA\MediaType(mediaType="application/json")),
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
	public function update(Request $request, $id): JsonResponse
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
			'description' => 'nullable|string',
			'status' => 'required|string|in:published,draft,pending',
			'order' => 'nullable|integer',
			'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
			'is_featured' => 'nullable|boolean',
			'website_ids' => 'nullable|string|max:255',
			'icon' => 'nullable|string|max:191',
			'icon_image' => 'nullable|file|image|mimes:webp,jpeg,png,jpg,gif|max:2048',
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

			if ($request->hasFile('image')) {
				$path = $request->file('image')->store('categories', $disk);
				$data['image'] = Storage::disk($disk)->url($path);
			}

			if ($request->hasFile('icon_image')) {
				$path = $request->file('icon_image')->store('categories/icons', $disk);
				$data['icon_image'] = Storage::disk($disk)->url($path);
			}

			if (!isset($data['order'])) {
				$parentId = $data['parent_id'] ?? 0;
				$lastOrder = Category::where('parent_id', $parentId)->where('id', '!=', $category->id)->max('order');
				$data['order'] = $lastOrder ? $lastOrder + 1 : 1;
			}

			if (
				$data['status'] === 'published' &&
				$category->children->isEmpty() &&
				$category->products()->count() === 0
			) {
				return response()->json([
					'success' => false,
					'message' => 'At least 1 products must be assigned to the product family before it can be published.'
				]);
			}

			$category->update($data);

			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$category->translateOrNew('en')->name_tr = $data['name'];
			}
			$category->save();

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

	/**
	 * @OA\Delete(
	 *     path="/api/categories/{id}",
	 *     summary="Delete category",
	 *     description="Deletes a category and optionally moves its children to its parent or deletes them",
	 *     tags={"Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Category ID",
	 *         @OA\Schema(
	 *             type="integer"
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="delete_children",
	 *         in="query",
	 *         required=false,
	 *         description="Whether to delete all children (true) or move them to parent (false)",
	 *         @OA\Schema(
	 *             type="boolean",
	 *             default=false
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category deleted successfully")
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
	public function destroy(Request $request, $id): JsonResponse
	{
		if (!auth()->user()->can('delete category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			$category = Category::findOrFail($id);
			$deleteChildren = $request->boolean('delete_children', false);

			// Begin transaction
			\DB::beginTransaction();

			// Handle child categories
			if ($deleteChildren) {
				// Delete all children recursively
				$this->deleteChildrenRecursively($category);
			} else {
				// Move children to parent
				$newParentId = $category->parent_id;
				Category::where('parent_id', $id)->update(['parent_id' => $newParentId]);
			}

			// Delete images
			if ($category->image) {
				Storage::disk('public')->delete($category->image);
			}

			if ($category->icon_image) {
				Storage::disk('public')->delete($category->icon_image);
			}

			// Delete the category
			if (method_exists($category, 'translations')) {
				$category->translations()->delete();
			}
			$category->delete();

			// Commit transaction
			\DB::commit();

			// Clear cache
			Cache::forget('all_categories');

			return response()->json([
				'success' => true,
				'message' => 'Category deleted successfully'
			]);

		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		} catch (\Exception $e) {
			// Rollback transaction on error
			\DB::rollBack();


			return response()->json([
				'success' => false,
				'message' => 'Failed to delete category',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Helper method to recursively delete children categories
	 */
	private function deleteChildrenRecursively(Category $category)
	{
		foreach ($category->children as $child) {
			$this->deleteChildrenRecursively($child);

			// Delete images
			if ($child->image) {
				Storage::disk('public')->delete($child->image);
			}

			if ($child->icon_image) {
				Storage::disk('public')->delete($child->icon_image);
			}

			$child->delete();
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/reorder",
	 *     summary="Reorder categories",
	 *     description="Updates the order of categories for drag and drop functionality",
	 *     tags={"Reorder"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(
	 *                 type="object",
	 *                 required={"id", "position", "parentId"},
	 *                 @OA\Property(property="id", type="integer", example=40),
	 *                 @OA\Property(property="title", type="string", example="Cooking Equipment (9)"),
	 *                 @OA\Property(property="position", type="integer", example=0),
	 *                 @OA\Property(property="parentId", type="integer", example=1)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Categories reordered successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Categories reordered successfully")
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
	public function reorder(Request $request): JsonResponse
	{
		// Validate the entire array of categories
		$validator = Validator::make($request->all(), [
			'*.id' => 'required|integer|exists:categories,id',
			'*.position' => 'required|integer|min:0',
			'*.parentId' => 'required|integer',
			'*.title' => 'sometimes|string'
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			\DB::beginTransaction();

			// Process each category in the array
			foreach ($request->all() as $categoryData) {
				$category = Category::findOrFail($categoryData['id']);

				$updateData = [
					'order' => $categoryData['position'],
					'parent_id' => $categoryData['parentId']
				];

				// Update title if it exists and is different
				if (isset($categoryData['title'])) {
					// Extract the base title without the count in parentheses
					$titleParts = explode(' (', $categoryData['title']);
					$baseTitle = $titleParts[0];

					$updateData['name'] = $baseTitle;
				}

				$category->update($updateData);
			}

			\DB::commit();

			// Clear cache
			Cache::forget('all_categories');

			return response()->json([
				'success' => true,
				'message' => 'Categories reordered successfully'
			]);

		} catch (\Exception $e) {
			\DB::rollBack();


			return response()->json([
				'success' => false,
				'message' => 'Failed to reorder categories',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Move a category up in order.
	 *
	 * @OA\Post(
	 *     path="/api/categories/{id}/move-up",
	 *     summary="Move category up",
	 *     description="Moves a category up in order within its parent",
	 *     tags={"Categories"},
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
	 *         description="Category moved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category moved up successfully")
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
	public function moveUp($id): JsonResponse
	{
		try {
			$category = Category::findOrFail($id);
			$parentId = $category->parent_id;

			// Find the category directly above this one
			$aboveCategory = Category::where('parent_id', $parentId)
			->where('order', '<', $category->order)
			->orderBy('order', 'desc')
			->first();

			if ($aboveCategory) {
				\DB::beginTransaction();

				// Swap orders
				$tempOrder = $aboveCategory->order;
				$aboveCategory->order = $category->order;
				$category->order = $tempOrder;

				$aboveCategory->save();
				$category->save();

				\DB::commit();

				// Clear cache
				Cache::forget('all_categories');

				return response()->json([
					'success' => true,
					'message' => 'Category moved up successfully'
				]);
			}

			return response()->json([
				'success' => false,
				'message' => 'Category is already at the top'
			]);

		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		} catch (\Exception $e) {
			// Rollback transaction if it was started
			if (\DB::transactionLevel() > 0) {
				\DB::rollBack();
			}


			return response()->json([
				'success' => false,
				'message' => 'Failed to move category up',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Move a category down in the order.
	 *
	 * @OA\Post(
	 *     path="/api/categories/{id}/move-down",
	 *     summary="Move category down",
	 *     description="Moves a category down one position in the ordering",
	 *     tags={"Categories"},
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
	 *         description="Category moved down successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category moved down successfully")
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
	public function moveDown($id): JsonResponse
	{
		try {
			$category = Category::findOrFail($id);
			$parentId = $category->parent_id;

			// Find the category directly below this one
			$belowCategory = Category::where('parent_id', $parentId)
			->where('order', '>', $category->order)
			->orderBy('order', 'asc')
			->first();

			if ($belowCategory) {
				\DB::beginTransaction();

				// Swap orders
				$tempOrder = $belowCategory->order;
				$belowCategory->order = $category->order;
				$category->order = $tempOrder;

				$belowCategory->save();
				$category->save();

				\DB::commit();

				// Clear cache
				Cache::forget('all_categories');

				return response()->json([
					'success' => true,
					'message' => 'Category moved down successfully'
				]);
			}

			return response()->json([
				'success' => false,
				'message' => 'Category is already at the bottom'
			]);

		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		} catch (\Exception $e) {
			// Rollback transaction if it was started
			if (\DB::transactionLevel() > 0) {
				\DB::rollBack();
			}


			return response()->json([
				'success' => false,
				'message' => 'Failed to move category down',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/allLastChild",
	 *     summary="Get All Last Child Categories",
	 *     description="Fetches a hierarchical list of categories. Each category includes its child categories recursively.",
	 *     tags={"Categories"},
	 *     @OA\Response(response=200, description="Successful operation", @OA\MediaType(mediaType="application/json")),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Unauthenticated.")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function allLastChildCategories()
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$categories = Cache::remember('all_last_child_categories', 3600, function () {
			return Category::whereDoesntHave('children')
				->with(['parent.parent.parent']) // Load parent hierarchy
				->orderBy('order', 'asc')
				->get(['id', 'name', 'slug', 'order', 'parent_id'])
				->map(function ($category) {
					// Build full category path
					$path = $this->getCategoryPath($category);
					return [
						'id' => $category->id,
						'name' => $category->name,
						'slug' => $category->slug,
						'order' => $category->order,
						'parent_id' => $category->parent_id,
						'full_path' => $path,
					];
				});
			});

		return response()->json([
			'success' => true,
			'message' => 'All Last Child Categories List',
			'categories' => $categories
		]);
	}

	// Helper method to build category path
	private function getCategoryPath($category)
	{
		$path = [$category->name];
		$parent = $category->parent;

		while ($parent) {
			array_unshift($path, $parent->name);
			$parent = $parent->parent;
		}

		return implode(' > ', $path);
	}

	/**
	 * @OA\Post(
	 *     path="/api/categories/generate-translation",
	 *     summary="Generate or update category translation",
	 *     description="This endpoint generates or updates translations for a category and its values.",
	 *     tags={"Categories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"id", "locale", "name"},
	 *             @OA\Property(property="id", type="integer", example=1, description="ID of the attribute to translate"),
	 *             @OA\Property(property="locale", type="string", example="ar", description="Locale code for translation (e.g. ar)"),
	 *             @OA\Property(property="name", type="string", example="", description="Translated name of the attribute"),
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function generateTranslation(Request $request)
	{
		/* Validate request data */
		$validated = $request->validate([
			'id' => 'required|exists:categories,id',
			'locale' => 'required|string|in:ar',
			'name' => 'required|string',
		]);

		$category = Category::find($validated['id']);

		DB::beginTransaction();
		try {
			$locale = $validated['locale'];

			/* Update category translation */
			$category->translateOrNew($locale)->name_tr = $validated['name'];
			$category->save();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("Translations updated successfully."),
				'data' => $category,
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