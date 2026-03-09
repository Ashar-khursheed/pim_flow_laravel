<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
					/* Build product relationships array */
					$productRelationships = [
						'seoUrl:id,relational_id,relational_type,url',
						'productSuppliers' => function($q) {
							$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information'])
							->cheapest();
						},
						'reviews:id,product_id,star',
						'currency:id,title,symbol',
						'sellingUnitAttribute',
					];

					/* Add translations for UAE websites only */
					if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
						$productRelationships[] = 'translations';
					}

					$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.sku',
						'ec_products.images',
						'ec_products.currency_id',
						'ec_products.alt_tags',
						'ec_products.quote_available',
						'ec_products.brand_id'
					])
					->where('ec_products.status', 'published')
					->with($productRelationships)
					->withCount('reviews')
					->withAvg('reviews', 'star');
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
						$productType->products->each(function ($product) {
							$this->transformFeaturedProduct($product, withTranslation:(in_array(config('app.website'), ['UAE', 'UAE_T']) ? true : false));
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

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/horeca-pages-by-slug/{slug}",
	//  *     summary="Get Horeca page details by slug",
	//  *     tags={"FrontEnd-Horeca Pages"},
	//  *     @OA\Parameter(
	//  *         name="slug",
	//  *         in="path",
	//  *         description="Horeca Page SEO Slug",
	//  *         required=true,
	//  *         @OA\Schema(type="string")
	//  *     ),
	//  *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	//  * )
	//  */
	// public function showBySlug($slug)
	// {
	// 	try {
	// 		// Find seo record matching slug and relational_type 'Page' (as requested)
	// 		$seoRecord = SeoManagement::where('url', $slug)
	// 			->where('relational_type', 'Page')
	// 			->first();

	// 		if (!$seoRecord) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Horeca page slug not found'
	// 			], 404);
	// 		}

	// 		/* Find the horeca page with relationships */
	// 		$page = HorecaPage::with([
	// 			'categories:id,name,image',
	// 			'categories.seoUrl:id,relational_id,relational_type,url',
	// 			'productTypes:id,horeca_page_id,type,description,order',
	// 			'productTypes.products' => function($query) {
	// 				/* Build product relationships array */
	// 				$productRelationships = [
	// 					'seoUrl:id,relational_id,relational_type,url',
	// 					'productSuppliers' => function($q) {
	// 						$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information'])
	// 						->cheapest();
	// 					},
	// 					'reviews:id,product_id,star',
	// 					'currency:id,title,symbol',
	// 					'sellingUnitAttribute',
	// 				];

	// 				/* Add translations for UAE websites only */
	// 				if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
	// 					$productRelationships[] = 'translations';
	// 				}

	// 				$query->select([
	// 					'ec_products.id',
	// 					'ec_products.name',
	// 					'ec_products.sku',
	// 					'ec_products.images',
	// 					'ec_products.currency_id',
	// 					'ec_products.alt_tags',
	// 					'ec_products.quote_available',
	// 					'ec_products.brand_id'
	// 				])
	// 				->where('ec_products.status', 'published')
	// 				->with($productRelationships)
	// 				->withCount('reviews')
	// 				->withAvg('reviews', 'star');
	// 			}
	// 		])->find($seoRecord->relational_id);

	// 		/* Check if page exists */
	// 		if (!$page) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Horeca page not found'
	// 			], 404);
	// 		}

	// 		/* Transform categories data */
	// 		if ($page->categories) {
	// 			$page->categories->transform(function ($category) {
	// 				$category->order = optional($category->pivot)->order ?? null;
	// 				$category->slug = optional($category->seoUrl)->url ?? null;
	// 				unset($category->pivot);
	// 				unset($category->seoUrl);
	// 				return $category;
	// 			});
	// 		}

	// 		/* Transform product types and their products */
	// 		if ($page->productTypes) {
	// 			$page->productTypes->transform(function ($productType) {
	// 				if ($productType->products) {
	// 					$productType->products->each(function ($product) {
	// 						$this->transformFeaturedProduct($product, withTranslation:(in_array(config('app.website'), ['UAE', 'UAE_T']) ? true : false));
	// 					});
	// 				}
	// 				return $productType;
	// 			});
	// 		}

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'Horeca page retrieved successfully',
	// 			'data' => $page
	// 		], 200);

	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to retrieve horeca page',
	// 			'error' => $e->getMessage()
	// 		], 500);
	// 	}
	// }

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
	\DB::enableQueryLog();

    try {
        $isUae = in_array(config('app.website'), ['UAE', 'UAE_T']);
        $cacheKey = "horeca_page_slug:{$slug}:" . ($isUae ? 'uae' : 'default');

        $result = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($slug, $isUae) {

            // Single query: join seo + page together to avoid 2 round trips
            $page = HorecaPage::select([
        'horeca_pages.id',
        'horeca_pages.name',
        'horeca_pages.description',
        'horeca_pages.link_name',
        'horeca_pages.link_url',
        'horeca_pages.banner_url',
        'horeca_pages.left_para_description',
        'horeca_pages.right_para_description',
        'horeca_pages.faqs',
        'horeca_pages.is_active',
        'horeca_pages.created_at',
    ])
    ->join('seo_management', function ($join) use ($slug) {
        $join->on('seo_management.relational_id', '=', 'horeca_pages.id')
            ->where('seo_management.relational_type', 'Page')
            ->where('seo_management.url', $slug);
    })
    ->first();

            if (!$page) {
                return null;
            }

            // Build product eager-load relationships once
           $productRelationships = [
					'seoUrl:id,relational_id,relational_type,url',
					'productSuppliers' => function ($q) {
						$q->select([
							'id', 'product_id', 'vendor_id', 'vendor_sku',
							'cost_per_item', 'sale_price', 'price', 'inventory',
							'in_stock', 'min_quantity', 'is_fixed', 'delivery_days',
							'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information'
						])
						->cheapest()
						->with([
							'vendor:id,address,zipcode,city_id,country_id',
							'vendor.country:id,name',
							'vendor.city:id,name',
						]);
					},
					'reviews:id,product_id,star',
					'currency:id,title,symbol',
					'sellingUnitAttribute',
				];

            if ($isUae) {
                $productRelationships[] = 'translations';
            }

            // Load everything in one go with constrained eager loading
            $page->load([
                // Categories: select only needed columns + seo in one eager load
                'categories' => function ($q) {
                    $q->select('categories.id', 'categories.name', 'categories.image')
                      ->orderByPivot('order'); // avoids in-memory sorting later
                },
                'categories.seoUrl:id,relational_id,relational_type,url',

                // Product types ordered upfront
                'productTypes' => function ($q) {
                    $q->select('id', 'horeca_page_id', 'type', 'description', 'order')
                      ->orderBy('order');
                },
				'productTypes.products' => function ($query) use ($productRelationships) {
					$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.sku',
						'ec_products.images',
						'ec_products.currency_id',
						'ec_products.alt_tags',
						'ec_products.quote_available',
						'ec_products.brand_id',
					])
					->where('ec_products.status', 'published')
					->with($productRelationships)
					->withCount('reviews')
					->withAvg('reviews', 'star');
				},
            ]);

            // --- Transform in-memory (no extra queries) ---

            // Categories: flatten pivot + seo into clean fields
            $page->setRelation('categories', $page->categories->map(function ($cat) {
                return [
                    'id'    => $cat->id,
                    'name'  => $cat->name,
                    'image' => $cat->image,
                    'order' => optional($cat->pivot)->order,
                    'slug'  => optional($cat->seoUrl)->url,
                ];
            })->values());

            // Product types + products
				$page->productTypes->each(function ($productType) use ($isUae) {
					$productType->products->each(function ($product) use ($isUae) {
						$this->transformFeaturedProduct(
							$product,
							categoryMostParentURL: '',  // skip parent_category_url() DB call
							categoryURL: '',            // skip category_url() DB call
							withTranslation: $isUae
				);
			});
		});

            return $page;
        });

        if (!$result) {
			$queries = \DB::getQueryLog();
			\Log::info('Total queries: ' . count($queries));
			\Log::info('Slow queries: ', array_filter($queries, fn($q) => $q['time'] > 100));
			\Log::info('ALL QUERIES:', $queries);
            return response()->json([
                'success' => false,
                'message' => 'Horeca page not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Horeca page retrieved successfully',
            'data'    => $result
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve horeca page',
            'error'   => $e->getMessage()
        ], 500);
    }
}
}