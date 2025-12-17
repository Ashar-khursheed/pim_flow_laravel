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

	/**
	 * @OA\Get(
	 *     path="/api/frontend-categories/featured-products",
	 *     tags={"Frontend-Category"},
	 *     summary="Get categories with featured products",
	 *     description="Retrieves leaf categories (categories without children) that have at least 5 featured products. Returns up to 5 categories ordered by featured product count. Each category includes its top featured products with product details.",
	 *     @OA\Response(response=200, description="Featured categories retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function getFeaturedCategoryProducts(Request $request)
	{
		$productsPerCategory = $request->get('products_limit', 10);
		$categoriesLimit = $request->get('categories_limit', 5);
		$minProducts = $request->get('min_products', 5);

		$featuredCategories = Category::select(['id', 'parent_id', 'name'])
		->where('status', 'published')
		->where('is_featured', 1)
		->whereDoesntHave('children')
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'featuredProducts' => function($query) use ($productsPerCategory) {
				$query->select(['id', 'name', 'sku', 'currency_id', 'units_sold', 'alt_tags', 'quote_available'])
				->with([
					'translations',
					'seoUrl:id,relational_id,relational_type,url',
					'productSuppliers:id,product_id,vendor_id,vendor_sku,cost_per_item,sale_price,price,inventory,in_stock,min_quantity,is_fixed,delivery_days,return_policy,free_shipping,shipping_charge,warranty_information',
					'reviews:id,product_id,star',
					'currency:id,title,symbol',
					'sellingUnitAttribute'
				])
				->withCount('reviews')
				->withAvg('reviews', 'star')
				->orderByDesc('units_sold')
				->limit($productsPerCategory);
			}
		])
		->has('featuredProducts', '>=', $minProducts)
		->take($categoriesLimit)
		->get();

		/* Transform categories */
		$featuredCategories->transform(function ($category) {
			/* Transform category name to locale object */
			$categoryLocaleNames = [];
			if ($category->translations) {
				foreach ($category->translations as $translation) {
					$categoryLocaleNames[$translation->locale] = $translation->name_tr;
				}
			}
			$category->name = $categoryLocaleNames;

			/* Get category URLs */
			$categoryMostParentURL = optional($category->mostParent)->seoUrl->url ?? null;
			$categoryURL = optional($category->seoUrl)->url ?? null;

			/* Transform featured products */
			$category->featuredProducts->each(function ($product) use ($categoryMostParentURL, $categoryURL) {
				/* Transform product name and images to locale objects */
				$productLocaleNames = [];
				$productLocaleImages = [];

				if ($product->translations) {
					foreach ($product->translations as $translation) {
						$productLocaleNames[$translation->locale] = $translation->name_tr;
						$productLocaleImages[$translation->locale] = is_array($translation->images_tr) ? $translation->images_tr : json_decode($translation->images_tr, true);
					}
				}

				$product->name = $productLocaleNames;
				$product->images = $productLocaleImages;
				$product->parent_category_url = $categoryMostParentURL;
				$product->category_url = $categoryURL;
				$product->url = optional($product->seoUrl)->url ?? null;
				$product->quote_available = $product->quote_available ?? 0;

				/* Currency data */
				$product->currency_name = optional($product->currency)->title ?? null;
				$product->currency_symbol = optional($product->currency)->symbol ?? null;

				/* Reviews data */
				$product->total_reviews = $product->reviews_count ?? 0;
				$product->avg_rating = round($product->reviews_avg_star ?? 0, 1);

				/* Alt tags */
				$product->alt_tags = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true) ?? [];

				/* Selling unit data */
				if ($product->sellingUnitAttribute) {
					$attributeValue = $product->sellingUnitAttribute->attribute_value;
					$product->selling_type_value = $attributeValue;
					$product->selling_type_unit = strpos($attributeValue, '/') !== false ? trim(explode('/', $attributeValue)[1]) : $attributeValue;
				} else {
					$product->selling_type_value = null;
					$product->selling_type_unit = null;
				}
				$product->is_required = $product->is_required;
				/* Transform product suppliers */
				if ($product->productSuppliers) {
					$product->productSuppliers->each(function ($productSupplier) {
						unset($productSupplier->id);
						unset($productSupplier->product_id);
					});
				}

				/* Remove unwanted attributes from product */
				unset($product->translations);
				unset($product->seoUrl);
				unset($product->currency);
				unset($product->currency_id);
				unset($product->reviews);
				unset($product->reviews_count);
				unset($product->reviews_avg_star);
				unset($product->sellingUnitAttribute);
				unset($product->pivot);
			});

			/* Remove unwanted attributes from category */
			unset($category->translations);
			unset($category->seoUrl);
			unset($category->parent);
			unset($category->parent_id);

			return $category;
		});

		return response()->json([
			'success' => true,
			'message' => 'Featured categories retrieved successfully',
			'data' => $featuredCategories
		]);
	}
}
