<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FrontEnd\HorecaPage;
use App\Models\SeoManagement;

use App\Traits\TransformProduct;

class HorecaPageController extends BaseController
{
	use TransformProduct;

	/**
	 * @OA\Get(
	 *     path="/api/frontend/horeca-pages/{id}",
	 *     summary="Get Horeca page details",
	 *     tags={"FrontEnd-Horeca Pages"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Horeca Page ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function show($id)
	{
		try {
			/* Find the horeca page with relationships */
			$page = HorecaPage::with([
				'categories:id,name,image',
				'categories.seoUrl:id,relational_id,relational_type,url',
				'productTypes:id,horeca_page_id,type,description,order',
				'productTypes.products' => function($query) {
					$query->where('status', 'published')
						->with([
							'reviews',
							'currency',
							'productSuppliers' => function($q) {
								$q->cheapest();
							},
							'productSuppliers.vendor.country:id,name',
							'productSuppliers.vendor.city:id,name',
							'sellingUnitAttribute',
							'ingredientsAttribute',
							'seoUrl',
							'productAttributes.attributeDetails'
						]);
				}
			])->find($id);

			/* Check if page exists */
			if (!$page) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page not found'
				], 404);
			}

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					$category->slug = optional($category->seoUrl)->url ?? null;
					unset($category->pivot);
					unset($category->seoUrl);
					return $category;
				});
			}

			/* Transform product types and their products */
			if ($page->productTypes) {
				$page->productTypes->transform(function ($productType) {
					if ($productType->products) {
						$productType->products = $productType->products->map(function ($product) {
							return $this->transformDetailedProduct($product);
						});
					}
					return $productType;
				});
			}

			return response()->json([
				'success' => true,
				'message' => 'Horeca page retrieved successfully',
				'data' => $page
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/horeca-pages-by-slug/{slug}",
	 *     summary="Get Horeca page details by slug",
	 *     tags={"FrontEnd-Horeca Pages"},
	 *     @OA\Parameter(
	 *         name="slug",
	 *         in="path",
	 *         description="Horeca Page SEO Slug",
	 *         required=true,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function showBySlug($slug)
	{
		try {
			// Find seo record matching slug and relational_type 'Page' (as requested)
			$seoRecord = SeoManagement::where('url', $slug)
				->where('relational_type', 'Page')
				->first();

			if (!$seoRecord) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page slug not found'
				], 404);
			}

			/* Find the horeca page with relationships */
			$page = HorecaPage::with([
				'categories:id,name,image',
				'categories.seoUrl:id,relational_id,relational_type,url',
				'productTypes:id,horeca_page_id,type,description,order',
				'productTypes.products' => function($query) {
					$query->where('status', 'published')
						->with([
							'reviews',
							'currency',
							'productSuppliers' => function($q) {
								$q->cheapest();
							},
							'productSuppliers.vendor.country:id,name',
							'productSuppliers.vendor.city:id,name',
							'sellingUnitAttribute',
							'ingredientsAttribute',
							'seoUrl',
							'productAttributes.attributeDetails'
						]);
				}
			])->find($seoRecord->relational_id);

			/* Check if page exists */
			if (!$page) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page not found'
				], 404);
			}

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					$category->slug = optional($category->seoUrl)->url ?? null;
					unset($category->pivot);
					unset($category->seoUrl);
					return $category;
				});
			}

			/* Transform product types and their products */
			if ($page->productTypes) {
				$page->productTypes->transform(function ($productType) {
					if ($productType->products) {
						$productType->products = $productType->products->map(function ($product) {
							return $this->transformDetailedProduct($product);
						});
					}
					return $productType;
				});
			}

			return response()->json([
				'success' => true,
				'message' => 'Horeca page retrieved successfully',
				'data' => $page
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Transform a product into the format requested by the user, matching getCategoryWiseRandomProducts exactly.
	 */
	private function transformDetailedProduct($product)
	{
		// Process images
		$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
		$cleanedImages = collect($imageArray)->map(function ($item) {
			if (is_string($item) && str_starts_with($item, '[')) {
				$decoded = json_decode($item, true);
				return is_array($decoded) ? $decoded : [$item];
			}
			return [$item];
		})->flatten()->filter()->values();

		// Process alt tags
		$AltArray = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true);
		$cleanedAlt = collect($AltArray)->map(function ($item) {
			if (is_string($item) && str_starts_with($item, '[')) {
				$decoded = json_decode($item, true);
				return is_array($decoded) ? $decoded : [$item];
			}
			return [$item];
		})->flatten()->filter()->values();

		// Selling type
		$sellingType = null;
		if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
			$fullValue = $product->sellingUnitAttribute->attribute_value;
			if (strpos($fullValue, '/') !== false) {
				$parts = explode('/', $fullValue);
				$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
			} else {
				$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
			}
		}

		// Calculate per unit price
		// Using productAttributes as the source for per_unit_price_attributes
		$unitsPerCase = collect($product->productAttributes)->first(fn($attr) => optional($attr->attributeDetails)->name === 'Units per Case');
		$packType = collect($product->productAttributes)->first(fn($attr) => optional($attr->attributeDetails)->name === 'Pack Type');

		$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
		$perUnitPrice = null;
		if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
			$unitValue = (float) $unitsPerCase->attribute_value;
			if ($unitValue > 0) {
				$calculated = round($basePrice / $unitValue, 2);
				$perUnitPrice = $calculated . '/' . (optional($packType)->attribute_value ?? '');
			}
		}
		$product->per_unit_price = $perUnitPrice;

		$firstSupplier = $product->productSuppliers->first();

		$price = $firstSupplier ? (float) $firstSupplier->price : null;
		$salePrice = $firstSupplier ? (float) $firstSupplier->sale_price : null;
		$vendorSku = $firstSupplier?->vendor_sku;
		$vendorId = $firstSupplier?->vendor_id;

		return [
			"id" => $product->id,
			"name" => $product->name,
			'category_url' => $product->category_url(),
			'parent_category_url' => $product->parent_category_url(),
			"sku" => $product->sku,
			"url" => $product->seoUrl->url ?? null,
			"total_reviews" => $product->reviews ? $product->reviews->count() : 0,
			"avg_rating" => ($product->reviews && $product->reviews->count() > 0) ? $product->reviews->avg('star') : null,
			"left_stock" => $product->left_stock ?? 0,
			"currency" => optional($product->currency)->symbol ?? '$',
			"images" => $cleanedImages,
			"alt_tags" => $cleanedAlt,
			"vendor_sku" => $vendorSku,

			'vendor_country' => optional(optional($firstSupplier)->vendor)->country->name ?? null,
			'vendor_city' => optional(optional($firstSupplier)->vendor)->city->name ?? null,
			'vendor_address' => optional(optional($firstSupplier)->vendor)->address ?? null,
			'vendor_zipcode' => optional(optional($firstSupplier)->vendor)->zipcode ?? null,

			"price" => $price ?? 0,
			"sale_price" => $salePrice ?? 0,
			"original_price" => $price ?? 0,
			"front_sale_price" => $salePrice ?: $price ?? 0,
			"best_price" => $price ?? 0,
			"selling_type" => $sellingType,
			"per_unit_price" => $product->per_unit_price,
			"vendor_id" => $vendorId,
			"map" => $firstSupplier ? (float) $firstSupplier->map : 0,
			"inventory" => $firstSupplier->inventory ?? null,
			"in_stock" => $firstSupplier->in_stock ?? null,
			"delivery_days" => $firstSupplier->delivery_days ?? null,
			"return_policy" => $firstSupplier->return_policy ?? null,
			"free_shipping" => $firstSupplier->free_shipping ?? null,
			"warranty_information" => $firstSupplier->warranty_information ?? null,
			'min_quantity' => $firstSupplier->min_quantity ?? 0,
			'is_fixed' => $firstSupplier->is_fixed ?? 0,
			'quote_available' => $product->quote_available ?? null,
			'isRequired' => (bool) $product->isRequired,
		];
	}
}