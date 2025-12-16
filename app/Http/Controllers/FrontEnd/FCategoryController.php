<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use App\Models\Category;
use App\Models\Product;
use App\Models\SeoManagement;

class FCategoryController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend-categories",
	 *     tags={"Frontend-Category"},
	 *     summary="Get product categories with hierarchical structure",
	 *     description="Retrieves published product categories in a parent-child hierarchical structure. Supports filtering by parent category (via ID or slug), limiting child categories per parent, and optionally including the parent category itself in the response. Categories are returned with translations, SEO URLs, and product counts.",
	 *     @OA\Parameter(name="parent_id", in="query", description="Filter categories by parent ID.", @OA\Schema(type="integer", example=1)),
	 *     @OA\Parameter(name="limit", in="query", description="Maximum number of child categories to load per parent category.", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="slug", in="query", description="Filter by parent category slug instead of ID.", @OA\Schema(type="string", example="kitchen-equipment")),
	 *     @OA\Parameter(name="with_parent", in="query", description="Whether to include the parent category details in the response along with its children", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order"})),
	 *     @OA\Response(response=200, description="Categories retrieved successfully.", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index(Request $request)
	{
		$parentID = $request->get('parent_id');
		$limit = $request->get('limit');
		$withParent = $request->boolean('with_parent');
		$slug = $request->get('slug');
		$sortBy = $request->get('sort_by');

		if ($slug) {
			$seoRecord = SeoManagement::where('url', $slug)->where('relational_type', 'Category')->first(['relational_id']);
			if (!$seoRecord) {
				return response()->json([
					'success' => false,
					'message' => 'Category slug not found'
				]);
			}

			/* Check if category exists and is published in one query */
			$categoryExists = Category::where('id', $seoRecord->relational_id)->where('status', 'published')->exists();
			if (!$categoryExists) {
				return response()->json([
					'success' => false,
					'message' => 'Category not found or not published'
				]);
			}

			$parentID = $seoRecord->relational_id;
		}

		$records = Category::select([
			'id', 'name', 'parent_id', 'image', 'order', 'last_child'
		])
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'publishedChildren' => function($query) use ($limit) {
				if ($limit) {
					$query->take($limit);
				}
			}
		])
		->withCount('products')
		->where('status', 'published');

		if ($parentID) {
			$records->where(function ($query) use ($parentID, $withParent) {
				$query->where('parent_id', $parentID);
				if ($withParent) {
					$query->orWhere('id', $parentID);
				}
			});
		} else {
			$records->where('parent_id', 0);
		}

		if ($sortBy) {
			$records = $records->orderBy($sortBy);
		}

		$records = $records->get();

		$records->transform(function ($category) {
			return $this->transformCategoryRecursive($category);
		});

		return response()->json([
			'success' => true,
			'message' => 'Categories retrieved successfully.',
			'data' => $records
		]);

		// $cacheKey = $parentID ? "categories_index_$parentID" : "categories_index_all";

		// $categoriesMenus = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($records) {
		// 	return $records->get();
		// });

		// return response()->json($records)->header('Cache-Control', 'public, max-age=86400');
	}

	/* Recursively transform category structure */
	private function transformCategoryRecursive($category)
	{
		/* Transform name to locale object */
		$categoryLocaleNames = [];
		if ($category->translations) {
			foreach ($category->translations as $translation) {
				$categoryLocaleNames[$translation->locale] = $translation->name_tr;
			}
		}
		$category->name = $categoryLocaleNames;

		/* Add slug from seoUrl */
		$category->slug = optional($category->seoUrl)->url ?? null;

		/* Transform last_children if exists */
		if (!empty($category->last_child)) {
			/* Convert comma-separated IDs to array */
			$lastChildIds = array_map('intval', explode(',', $category->last_child));

			/* Fetch the last children categories */
			$lastChildren = Category::whereIn('id', $lastChildIds)
			->with(['seoUrl:id,relational_id,relational_type,url', 'translations'])
			->where('status', 'published')
			->select(['id', 'name', 'parent_id', 'image', 'order'])
			->get()
			->map(function ($child) {
				/* Build locale names for child */
				$childLocaleNames = [];
				if ($child->translations) {
					foreach ($child->translations as $translation) {
						$childLocaleNames[$translation->locale] = $translation->name_tr;
					}
				}

				return [
					'id' => $child->id,
					'name' => $childLocaleNames,
					'slug' => $child->seoUrl?->url ?? null,
					'parent_id' => $child->parent_id,
					'image' => $child->image,
					'order' => $child->order,
				];
			});

			$category->last_children = $lastChildren;
		} else {
			$category->last_children = [];
		}

		/* Transform children recursively */
		if ($category->publishedChildren && count($category->publishedChildren) > 0) {
			$category->children = $category->publishedChildren->map(function ($child) {
				return $this->transformCategoryRecursive($child);
			});
		} else {
			$category->children = [];
		}

		/* Remove unwanted attributes */
		unset($category->translations);
		unset($category->seoUrl);
		unset($category->last_child);
		unset($category->publishedChildren);

		return $category;
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend-categories/with-parents",
	 *     tags={"Frontend-Category"},
	 *     summary="Fetch all categories with parents",
	 *     @OA\Parameter(name="category_id", in="query", description="Filter categories by category ID.", @OA\Schema(type="integer", example=1)),
	 *     @OA\Parameter(name="category_type", in="query", description="Filter by category type.", @OA\Schema(type="string", example="home")),
	 *     @OA\Response(response=200, description="All categories fetched successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function fetchCategoriesWithParents(Request $request)
	{
		$categoryID = $request->get('category_id');
		$categoryType = $request->get('category_type');

		$categories = Category::select(['id', 'name', 'parent_id', 'image'])
		->where('status', 'published')
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'parentRecursive'
		])
		->withCount('products');

		if ($categoryID ) {
			$categories = $categories->where('id', $categoryID);
		}

		if ($categoryType == 'home') {
			$homeCategories = home_categories();
			$orderField = 'FIELD(name, ' . implode(',', array_fill(0, count($homeCategories), '?')) . ')';
			$categories = $categories->whereDoesntHave('children')
			->whereIn('name', $homeCategories)
			->orderByRaw($orderField, $homeCategories);
		} else {
			$categories = $categories->orderBy('id');
		}

		$categories = $categories->get();

		$categories->transform(function ($category) {
			$parents = [];
			$current = $category->parentRecursive;

			while ($current) {
				$localeNames = [];
				if ($current->translations) {
					foreach ($current->translations as $translation) {
						$localeNames[$translation->locale] = $translation->name_tr;
					}
				}
				$parents[] = [
					'id' => $current->id,
					'name' => $localeNames,
					'slug' => optional($current->seoUrl)->url ?? null,
				];
				$current = $current->parentRecursive ?? null;
			}

			if ($category->translations) {
				foreach ($category->translations as $translation) {
					$categoryLocaleNames[$translation->locale] = $translation->name_tr;
				}
			}

			$category->slug = optional($category->seoUrl)->url ?? null;
			$category->name = $categoryLocaleNames;
			$category->parents = array_reverse($parents);

			unset($category->seoUrl);
			unset($category->parentRecursive);
			unset($category->translations);

			return $category;
		});

		return response()->json([
			'success' => true,
			'message' => 'Categories retrieved successfully.',
			'data' => $categories
		]);
	}

	public function getAllGuestFeaturedProductsByCategory(Request $request)
	{
		$categories = Category::whereHas('products', function ($query) {
			$query->where('is_featured', 1)->where('status', 'published');
		}, '>=', 5)
			->whereDoesntHave('children')
			->with([
				'products' => function ($query) {
					$query->where('is_featured', 1)
					->where('status', 'published')
						->select('id', 'name', 'sku', 'currency_id', 'units_sold'); // Select only necessary fields
					}
				])
			->take(5)
			->get();

		// Subquery for best price and delivery days
			$subQuery = Product::select('sku')
			->groupBy('sku');

		// Process categories and products
			$categories = $categories->map(function ($category) use ($subQuery) {
				$featuredProducts = $category->products->take(10);

			// Fetch all product details in one query
				$productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
					$join->on('ec_products.sku', '=', 'best_products.sku');
				})
				->whereIn('ec_products.id', $featuredProducts->pluck('id'))
				->with([
					'reviews',
					'currency',
					'productSuppliers',
					'vendors',
					'seoUrl',
					'productAttributes' => function ($query) {
						$query->whereHas('attributeDetails', function ($q) {
							$q->whereIn('name', ['Units per Case', 'Pack Type']);
						});
					},
				]) // Eager load relationships
				->get()
				->keyBy('id'); // Use keyBy to quickly fetch by ID later
				return [
					'category_name' => $category->name,
					'featured_products' => $featuredProducts->map(function ($product) use ($productDetails) {
						$details = $productDetails[$product->id] ?? null;
						if (!$details)
						return null; // Skip if no details found

					$totalReviews = $details->reviews->count();
					$avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
					$currencyTitle = $details->currency->symbol;

					// Process images efficiently
					$imageUrls = is_string($details->images)
					? json_decode($details->images, true)
					: (array) $details->images;
					$cleanedAlt = is_string($details->alt_tags)
					? json_decode($details->alt_tags, true)
					: (array) $details->alt_tags;

					$sellingType = null;

					if ($details->sellingUnitAttribute && $details->sellingUnitAttribute->attribute_value) {
						$fullValue = $details->sellingUnitAttribute->attribute_value;

						$attributeUnit = strpos($fullValue, '/') !== false
						? trim(explode('/', $fullValue)[1])
						: $fullValue;

						$sellingType = [
							'attribute_value' => $details->sellingUnitAttribute->attribute_value,
							'attribute_value_unit' => $attributeUnit,
						];
					}
					$firstSupplier = $details->productSuppliers->first();
					$leftStock = ($firstSupplier->quantity ?? 0) - ($details->units_sold ?? 0);

					// Calculate per unit price
					$unitsPerCase = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
					$packType = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

					$basePrice = null;
					if ($firstSupplier) {
						$basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
					}

					// $basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
					$perUnitPrice = null;

					if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
						$unitValue = (float) $unitsPerCase->attribute_value;
						if ($unitValue > 0) {
							$calculated = round($basePrice / $unitValue, 2);
							$perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
						}
					}

					$details->per_unit_price = $perUnitPrice;



					return [
						'id' => $details->id,
						'name' => $details->name,
						'sku' => $details->sku,
						'category_url' => $details->category_url(),
						'parent_category_url' => $details->parent_category_url(),
						'url' => $details->seoUrl->url ?? null,
						'vendor_sku' => $firstSupplier->vendor_sku ?? null,
						'price' => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						"sale_price" => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : null,
						'total_reviews' => $totalReviews,
						'avg_rating' => $avgRating,
						'left_stock' => $leftStock,
						'currency' => $currencyTitle,
						'images' => $imageUrls,
						'alt_tags' => $cleanedAlt,
						"original_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						'front_sale_price' => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : (float) $details->price,
						"best_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						"selling_type" => $sellingType,
						"per_unit_price" => $details->per_unit_price,
						'vendor_id' => $firstSupplier->vendor_id ?? null,
						'map' => $firstSupplier ? (float) $firstSupplier->map : null,
						'inventory' => $firstSupplier->inventory ?? null,
						'in_stock' => $firstSupplier->in_stock ?? null,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'return_policy' => $firstSupplier->return_policy ?? null,
						'free_shipping' => $firstSupplier->free_shipping ?? null,
						'warranty_information' => $firstSupplier->warranty_information ?? null,
						'min_quantity' => $firstSupplier->min_quantity ?? 0,
						'is_fixed' => $firstSupplier->is_fixed ?? 0,
						'quote_available' => $details->quote_available ?? null,
						'isRequired' => $details->is_required,
					];
				})->filter()->values(), // Remove null values and reset array keys
			];//
		});

		return response()->json([//
			'success' => true,
			'data' => $categories,
		])->header('Cache-Control', 'public, max-age=86400');
	}
}
