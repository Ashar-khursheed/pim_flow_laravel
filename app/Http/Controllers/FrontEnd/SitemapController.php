<?php

namespace App\Http\Controllers\FrontEnd;
use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\Category;
use App\Models\Blog;
use App\Models\SeoManagement;
use App\Models\Brand;
use Carbon\Carbon;
class SitemapController extends Controller
{
        private $baseUrl = 'https://www.horecastore.ae'; // 🔒 Static Base URL

    private function buildXml($sitemaps)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($sitemaps as $sitemap) {
        $xml .= '<url>';
        $xml .= '<loc>' . htmlspecialchars($sitemap['loc']) . '</loc>';
        $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
        $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
        $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
        $xml .= '</url>';
    }

    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml');
}

    /**
     * Get XML Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/sitemap.xml",
     *     summary="Get sitemap.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getSitemap()
    // {
    //     $now = Carbon::now()->toAtomString();
    //     $sitemaps = [
    //         [
    //             'loc' => $this->baseUrl .'https://www.horecastore.ae/'),
    //             'lastmod' => $now,
    //             'changefreq' => 'daily',
    //             'priority' => '1.0',
    //         ],
    //         [
    //             'loc' => 'pages/about-us',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/contact-us',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'sell-on-horeca',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/return-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/shipping-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/cancellation-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/payment-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/vendor-supplier-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/privacy-policy',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/terms-conditions',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'pages/extended-warranty',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'blog',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'categories.xml',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [

    //             'loc' => 'products.xml',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'blog.xml',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [
    //             'loc' => 'brand.xml',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //         [

    //             'loc' => 'images.xml',
    //             'lastmod' => $now,
    //             'changefreq' => 'weekly',
    //             'priority' => '0.5',
    //         ],
    //     ];
    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);

    // }
    public function getSitemap()
{
    $now = Carbon::now()->toAtomString();

$sitemaps = [
        ['loc' => $this->baseUrl . '/', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => $this->baseUrl . '/pages/about-us', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/contact-us', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/sell-on-horeca', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/return-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/shipping-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/cancellation-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/payment-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/vendor-supplier-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/privacy-policy', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/terms-conditions', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/pages/extended-warranty', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/blog', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/categories.xml', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/products.xml', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/blog.xml', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/brand.xml', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['loc' => $this->baseUrl . '/image.xml', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.5'],
    ];

    return $this->buildXml($sitemaps);
}

    /**
     * Get XML categories Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/categories.xml",
     *     summary="Get categories.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getCategoriesSitemap()
    // {
    //     $mainCategories = Category::select(['id', 'name', 'updated_at'])
    //         ->with('seoUrl')
    //         ->where('parent_id', 0)
    //         ->where('status', 'published')
    //         ->whereHas('seoUrl', function ($q) {
    //             $q->whereNotNull('url');
    //         })
    //         ->get()
    //         ->map(function ($category) {
    //             return [
    //                 'loc' => $category->seoUrl->url, // safe now
    //                 'lastmod' => $category->updated_at
    //                     ? $category->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });

    //     $subCategories = Category::with(['seoUrl', 'parent.parent.seoUrl']) // preload seoUrls up the chain
    //         ->whereNotNull('parent_id')
    //         ->where('parent_id', '!=', 0)
    //         ->whereHas('seoUrl', fn($q) => $q->whereNotNull('url'))
    //         ->select(['id', 'name', 'updated_at', 'slug', 'parent_id'])
    //         ->get()
    //         ->map(function ($subcategory) {
    //             $superParent = $subcategory->getSuperParent();
    //             $aprentUrls = SeoManagement::select('url', 'relational_id')->where('relational_type', 'Category')->where('relational_id', $superParent)->first();

    //             return [
    //                 'loc' => $aprentUrls->url . '/' . ($subcategory->seoUrl->url ?? ''),
    //                 'lastmod' => $subcategory->updated_at
    //                     ? $subcategory->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });
    //     $sitemaps = $mainCategories->merge($subCategories);
    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);

    // }
    public function getCategoriesSitemap()
{
    $mainCategories = Category::with('seoUrl')
        ->where('parent_id', 0)
        ->where('status', 'published')
        ->whereHas('seoUrl', fn($q) => $q->whereNotNull('url'))
        ->get()
        ->map(function ($category) {
            return [
                'loc' => $this->baseUrl . '/' . ltrim($category->seoUrl->url),
                'lastmod' => optional($category->updated_at)->toAtomString() ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

    $subCategories = Category::with(['seoUrl', 'parent.parent.seoUrl'])
        ->whereNotNull('parent_id')
        ->where('status', 'published')
        ->whereHas('seoUrl', fn($q) => $q->whereNotNull('url'))
        ->get()
        ->map(function ($subcategory) {
            $superParent = $subcategory->getSuperParent();
            $parentUrl = SeoManagement::where('relational_type', 'Category')
                ->where('relational_id', $superParent)
                ->value('url');

            return [
                'loc' => $this->baseUrl . '/' . ltrim($parentUrl . '/' . $subcategory->seoUrl->url),
                'lastmod' => optional($subcategory->updated_at)->toAtomString() ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

    return $this->buildXml($mainCategories->merge($subCategories));
}

    /**
     * Get XML product Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/products.xml",
     *     summary="Get products.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getProductsSitemap()
    // {
    //     $sitemaps = Product::with('seoProductUrl:id,relational_id,relational_type,url')
    //         ->whereHas('seoProductUrl', function ($q) {
    //             $q->whereNotNull('url');
    //         })

    //         ->where('status', 'published')
    //         ->get(['id', 'name', 'updated_at'])
    //         ->map(function ($product) {
    //             return [
    //                 'loc' => $product->parent_category_$this->baseUrl .) . '/' .
    //                     $product->category_$this->baseUrl .) . '/' .
    //                     ($product->seoProductUrl->url ?? ""),
    //                 'lastmod' => $product->updated_at
    //                     ? $product->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });

    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);

    // }
public function getProductsSitemap()
{
    $sitemaps = Product::with(['seoProductUrl', 'category.parentCategory'])
        ->whereHas('seoProductUrl', fn($q) => $q->whereNotNull('url'))
        ->where('status', 'published')
        ->get()
        ->map(function ($product) {
            // Safely get parent category slug
            $parentSlug = optional($product->category->parentCategory)->slug ?? '';
            $categorySlug = optional($product->category)->slug ?? '';
            $productUrl = optional($product->seoProductUrl)->url ?? '';

            // Build full loc exactly like old function
            $loc = $this->baseUrl . '/' . 
                   ($parentSlug ? $parentSlug . '/' : '') . 
                   ($categorySlug ? $categorySlug . '/' : '') . 
                   $productUrl;

            return [
                'loc' => $loc,
                'lastmod' => $product->updated_at
                    ? $product->updated_at->toAtomString()
                    : now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

    return $this->buildXml($sitemaps);
}



    /**
     * Get XML product Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/blog.xml",
     *     summary="Get blog.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getBlogSitemap()
    // {
    //     $sitemaps = Blog::select(['name', 'slug', 'updated_at'])
    //         ->where('status', 'published')
    //         ->get()
    //         ->map(function ($blog) {
    //             return [
    //                 'loc' => 'blog/' . $blog->slug,
    //                 'lastmod' => $blog->updated_at
    //                     ? $blog->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });

    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);

    // }

    public function getBlogSitemap()
{
    $sitemaps = Blog::where('status', 'published')
        ->get()
        ->map(function ($blog) {
            return [
                'loc' => $this->baseUrl . '/blog/' . $blog->slug,
                'lastmod' => optional($blog->updated_at)->toAtomString() ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

    return $this->buildXml($sitemaps);
}

    /**
     * Get XML product Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/brand.xml",
     *     summary="Get brand.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getBrandSitemap()
    // {
    //     $sitemaps = Brand::select(['id', 'name', 'updated_at'])
    //         ->whereHas('seoUrl', function ($q) {
    //             $q->whereNotNull('url');
    //         })
    //         ->with('seoUrl')
    //         ->where('status', 'published')
    //         ->get()
    //         ->map(function ($brand) {
    //             return [
    //                 'loc' => 'brands/' . ($brand->seoUrl)->url,
    //                 'lastmod' => $brand->updated_at
    //                     ? $brand->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });

    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);

    // }
    public function getBrandSitemap()
{
    $sitemaps = Brand::with('seoUrl')
        ->where('status', 'published')
        ->whereHas('seoUrl', fn($q) => $q->whereNotNull('url'))
        ->get()
        ->map(function ($brand) {
            return [
                'loc' => $this->baseUrl . '/brands/' . $brand->seoUrl->url,
                'lastmod' => optional($brand->updated_at)->toAtomString() ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

    return $this->buildXml($sitemaps);
}

    /**
     * Get XML Image Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/image.xml",
     *     summary="Get image.xml",
     *     description="Returns the XML sitemap containing public URLs of the website.",
     *     tags={"Sitemap"},
     *     @OA\Response(
     *         response=200,
     *         description="Sitemap XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    // public function getImageSitemap()
    // {
    //     $sitemaps = Product::select(['id', 'name', 'images', 'updated_at'])
    //         ->whereNotNull('images')
    //         ->where('status', 'published')
    //         ->get()
    //         ->map(function ($product) {
    //             $images = collect(json_decode($product->images, true))
    //                 ->filter(function ($image) {
    //                     return !empty($image);
    //                 })
    //                 ->values();

    //             return [
    //                 'loc' => $images,
    //                 'lastmod' => $product->updated_at
    //                     ? $product->updated_at->toAtomString()
    //                     : now()->toAtomString(),
    //                 'changefreq' => 'weekly',
    //                 'priority' => '0.8',
    //             ];
    //         });
    //     return response()->json([
    //         'status' => true,
    //         'data' => $sitemaps
    //     ]);
    // }
    public function getImageSitemap()
{
    $sitemaps = Product::whereNotNull('images')
        ->where('status', 'published')
        ->get()
        ->flatMap(function ($product) {
            $images = collect(json_decode($product->images, true))->filter()->values();
            return $images->map(function ($image) use ($product) {
                return [
                    'loc' => $image,
                    'lastmod' => optional($product->updated_at)->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });
        });

    return $this->buildXml($sitemaps);
}

}