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
    private $baseUrl = 'https://www.horecastore.ae';


    private function buildXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $now = Carbon::now()->toAtomString();
        $sitemaps = [
            [
                'loc' => '/',
                'lastmod' => $now,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => 'pages/about-us',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/contact-us',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'sell-on-horeca',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/return-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/shipping-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/cancellation-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/payment-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/vendor-supplier-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/privacy-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/terms-conditions',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/extended-warranty',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blog',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'categories.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [

                'loc' => 'products.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blog.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'brand.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'productImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blogImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'brandImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'categoryImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
        ];
        foreach ($sitemaps as $sitemap) {
            $xml .= '<url>';

            $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
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
    public function getSitemap()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $now = Carbon::now()->toAtomString();
        $sitemaps = [
            [
                'loc' => '',
                'lastmod' => $now,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => 'pages/about-us',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/contact-us',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'sell-on-horeca',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/return-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/shipping-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/cancellation-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/payment-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/vendor-supplier-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/privacy-policy',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/terms-conditions',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'pages/extended-warranty',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blog',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'categories.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'products.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blog.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'brand.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'productImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'blogImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'brandImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
            [
                'loc' => 'categoryImage.xml',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ],
        ];
        foreach ($sitemaps as $sitemap) {
            $xml .= '<url>';

            $loc = ltrim($sitemap['loc'], '/');
            $xml .= '<loc>' . rtrim($this->baseUrl, '/') . '/' . htmlspecialchars($loc) . '</loc>';
            $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');



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
    public function getCategoriesSitemap()
    {
        $mainCategories = Category::select(['id', 'name', 'updated_at'])
            ->with('seoUrl')
            ->where('parent_id', 0)
            ->where('status', 'published')
            ->whereHas('seoUrl', function ($q) {
                $q->whereNotNull('url');
            })
            ->get()
            ->map(function ($category) {
                return [
                    'loc' => $category->seoUrl->url,
                    'lastmod' => $category->updated_at
                        ? $category->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $subCategories = Category::with(['seoUrl', 'parent.parent.seoUrl']) // preload seoUrls up the chain
            ->whereNotNull('parent_id')
            ->where('parent_id', '!=', 0)
            ->whereHas('seoUrl', fn($q) => $q->whereNotNull('url'))
            ->select(['id', 'name', 'updated_at', 'slug', 'parent_id'])
            ->get()
            ->map(function ($subcategory) {
                $superParent = $subcategory->getSuperParent();
                $aprentUrls = SeoManagement::select('url', 'relational_id')->where('relational_type', 'Category')->where('relational_id', $superParent)->first();

                return [
                    'loc' => $aprentUrls->url . '/' . ($subcategory->seoUrl->url ?? ''),
                    'lastmod' => $subcategory->updated_at
                        ? $subcategory->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });
        $sitemaps = $mainCategories->merge($subCategories);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($sitemaps as $sitemap) {
            $xml .= '<url>';

            $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
            $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');


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
    public function getProductsSitemap()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $getProductsSitemap1 = $this->getProductsSitemap1();
        if ($getProductsSitemap1) {
            foreach ($getProductsSitemap1 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap2 = $this->getProductsSitemap2();
        if ($getProductsSitemap2) {
            foreach ($getProductsSitemap2 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap3 = $this->getProductsSitemap3();
        if ($getProductsSitemap3) {
            foreach ($getProductsSitemap3 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap4 = $this->getProductsSitemap4();
        if ($getProductsSitemap4) {
            foreach ($getProductsSitemap4 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap5 = $this->getProductsSitemap5();
        if ($getProductsSitemap5) {
            foreach ($getProductsSitemap5 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap6 = $this->getProductsSitemap6();
        if ($getProductsSitemap6) {
            foreach ($getProductsSitemap6 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap7 = $this->getProductsSitemap7();
        if ($getProductsSitemap7) {
            foreach ($getProductsSitemap7 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap8 = $this->getProductsSitemap8();
        if ($getProductsSitemap8) {
            foreach ($getProductsSitemap8 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap9 = $this->getProductsSitemap9();
        if ($getProductsSitemap9) {
            foreach ($getProductsSitemap9 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }

        $getProductsSitemap10 = $this->getProductsSitemap10();
        if ($getProductsSitemap10) {
            foreach ($getProductsSitemap10 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap11 = $this->getProductsSitemap11();
        if ($getProductsSitemap11) {
            foreach ($getProductsSitemap11 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap12 = $this->getProductsSitemap12();
        if ($getProductsSitemap12) {
            foreach ($getProductsSitemap12 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap13 = $this->getProductsSitemap13();
        if ($getProductsSitemap13) {
            foreach ($getProductsSitemap13 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap14 = $this->getProductsSitemap14();
        if ($getProductsSitemap14) {
            foreach ($getProductsSitemap14 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap15 = $this->getProductsSitemap15();
        if ($getProductsSitemap15) {
            foreach ($getProductsSitemap15 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap16 = $this->getProductsSitemap16();
        if ($getProductsSitemap16) {
            foreach ($getProductsSitemap16 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap17 = $this->getProductsSitemap17();
        if ($getProductsSitemap17) {
            foreach ($getProductsSitemap17 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap18 = $this->getProductsSitemap18();
        if ($getProductsSitemap18) {
            foreach ($getProductsSitemap18 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap19 = $this->getProductsSitemap19();
        if ($getProductsSitemap19) {
            foreach ($getProductsSitemap10 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $getProductsSitemap20 = $this->getProductsSitemap20();
        if ($getProductsSitemap20) {
            foreach ($getProductsSitemap20 as $sitemap) {
                $xml .= '<url>';
                $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }


        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function getProductsSitemap1()
    {
        return $this->generateProductsSitemap(0, 1000);
    }
    public function getProductsSitemap2()
    {
        return $this->generateProductsSitemap(2000, 1000);
    }
    public function getProductsSitemap3()
    {
        return $this->generateProductsSitemap(3000, 1000);
    }
    public function getProductsSitemap4()
    {
        return $this->generateProductsSitemap(4000, 1000);
    }
    public function getProductsSitemap5()
    {
        return $this->generateProductsSitemap(5000, 1000);
    }
    public function getProductsSitemap6()
    {
        return $this->generateProductsSitemap(6000, 1000);
    }
    public function getProductsSitemap7()
    {
        return $this->generateProductsSitemap(7000, 1000);
    }
    public function getProductsSitemap8()
    {
        return $this->generateProductsSitemap(8000, 1000);
    }
    public function getProductsSitemap9()
    {
        return $this->generateProductsSitemap(9000, 1000);
    }
    public function getProductsSitemap10()
    {
        return $this->generateProductsSitemap(10000, 1000);
    }
    public function getProductsSitemap11()
    {
        return $this->generateProductsSitemap(11000, 1000);
    }
    public function getProductsSitemap12()
    {
        return $this->generateProductsSitemap(12000, 1000);
    }
    public function getProductsSitemap13()
    {
        return $this->generateProductsSitemap(13000, 1000);
    }
    public function getProductsSitemap14()
    {
        return $this->generateProductsSitemap(14000, 1000);
    }
    public function getProductsSitemap15()
    {
        return $this->generateProductsSitemap(15000, 1000);
    }
    public function getProductsSitemap16()
    {
        return $this->generateProductsSitemap(16000, 1000);
    }
    public function getProductsSitemap17()
    {
        return $this->generateProductsSitemap(17000, 1000);
    }
    public function getProductsSitemap18()
    {
        return $this->generateProductsSitemap(18000, 1000);
    }
    public function getProductsSitemap19()
    {
        return $this->generateProductsSitemap(19000, 1000);
    }
    public function getProductsSitemap20()
    {
        return $this->generateProductsSitemap(20000, 1000);
    }
    public function getProductsSitemap21()
    {
        return $this->generateProductsSitemap(21000, 1000);
    }
    public function getProductsSitemap22()
    {
        return $this->generateProductsSitemap(22000, 1000);
    }
    public function getProductsSitemap23()
    {
        return $this->generateProductsSitemap(23000, 1000);
    }
    public function getProductsSitemap24()
    {
        return $this->generateProductsSitemap(24000, 1000);
    }
    public function getProductsSitemap25()
    {
        return $this->generateProductsSitemap(25000, 1000);
    }
    public function getProductsSitemap26()
    {
        return $this->generateProductsSitemap(26000, 1000);
    }
    public function getProductsSitemap27()
    {
        return $this->generateProductsSitemap(27000, 1000);
    }
    public function getProductsSitemap28()
    {
        return $this->generateProductsSitemap(28000, 1000);
    }
    public function getProductsSitemap29()
    {
        return $this->generateProductsSitemap(29000, 1000);
    }
    public function getProductsSitemap30()
    {
        return $this->generateProductsSitemap(30000, 1000);
    }
    public function getProductsSitemap31()
    {
        return $this->generateProductsSitemap(31000, 1000);
    }

    private function generateProductsSitemap($offset, $limit)
    {
        $totalProducts = Product::where('status', 'published')->count();
        $sitemaps = "";
        if ($offset < $totalProducts) {
            $sitemaps = Product::with('seoProductUrl:id,relational_id,relational_type,url')
                ->whereHas('seoProductUrl', fn($q) => $q->whereNotNull('url'))
                ->where('status', 'published')
                ->offset($offset)
                ->limit($limit)
                ->get(['id', 'name', 'updated_at'])
                ->map(fn($product) => [
                    'loc' => $product->parent_category_url() . '/' .
                        $product->category_url() . '/' .
                        ($product->seoProductUrl->url ?? ""),
                    'lastmod' => $product->updated_at ? $product->updated_at->toAtomString() : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ]);
        }
        return $sitemaps;
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
    public function getBlogSitemap()
    {
        $sitemaps = Blog::select(['name', 'slug', 'updated_at'])
            ->where('status', 'published')
            ->get()
            ->map(function ($blog) {
                return [
                    'loc' => 'blog/' . $blog->slug,
                    'lastmod' => $blog->updated_at
                        ? $blog->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= '<url>';
            $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
            $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';
        return response($xml, 200)->header('Content-Type', 'application/xml');
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
    public function getBrandSitemap()
    {
        $sitemaps = Brand::select(['id', 'name', 'updated_at'])
            ->whereHas('seoUrl', function ($q) {
                $q->whereNotNull('url');
            })
            ->with('seoUrl')
            ->where('status', 'published')
            ->get()
            ->map(function ($brand) {
                return [
                    'loc' => 'brands/' . ($brand->seoUrl)->url,
                    'lastmod' => $brand->updated_at
                        ? $brand->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($sitemaps as $sitemap) {
            $xml .= '<url>';
            $xml .= '<loc>' . $this->baseUrl . '/' . htmlspecialchars($sitemap['loc']) . '</loc>';
            $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Get XML Image Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/productImage.xml",
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
    public function getProductImageSitemap()
    {
        $sitemaps = Product::select(['id', 'name', 'images', 'updated_at'])
            ->whereNotNull('images')
            ->where('status', 'published')
            ->get()
            ->map(function ($product) {
                $images = collect(json_decode($product->images, true))
                    ->filter(function ($image) {
                        return !empty($image);
                    })
                    ->values();
                return [
                    'loc' => $images,
                    'lastmod' => $product->updated_at
                        ? $product->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $loc = $sitemap['loc'];
            $loc = json_decode($loc, true);
            if (is_array($loc)) {
                foreach ($loc as $imgSlug) {
                    $xml .= '<url>';
                    $xml .= '<loc>' . htmlspecialchars($imgSlug) . '</loc>';
                    $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                    $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                    $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                    $xml .= '</url>';
                }
            } else {

                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars($sitemap['loc']) . '</loc>';
                $xml .= '<lastmod>' . $sitemap['lastmod'] . '</lastmod>';
                $xml .= '<changefreq>' . $sitemap['changefreq'] . '</changefreq>';
                $xml .= '<priority>' . $sitemap['priority'] . '</priority>';
                $xml .= '</url>';
            }
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Get XML blog Image Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/blogImage.xml",
     *     summary="Get blogImage.xml",
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
    public function getBlogImageSitemap()
    {
        $sitemaps = Blog::select(['id', 'name', 'desktop_banner', 'updated_at'])
            ->whereNotNull('desktop_banner')
            ->where('status', 'published')

            ->get()
            ->map(function ($blog) {

                return [
                    'loc' => $blog->desktop_banner,
                    'lastmod' => $blog->updated_at
                        ? $blog->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

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
     * Get XML Brand image Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/brandImage.xml",
     *     summary="Get brandImage.xml",
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
    public function getBrandImageSitemap()
    {
        $sitemaps = Brand::select(['id', 'name', 'thumbnail', 'updated_at'])
            ->whereNotNull('thumbnail')
            ->where('status', 'published')
            ->get()
            ->map(function ($brand) {

                return [
                    'loc' => $brand->thumbnail,
                    'lastmod' => $brand->updated_at
                        ? $brand->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

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
     * Get XML Category image Sitemap.
     *
     * @OA\Get(
     *     path="/api/frontend/categoryImage.xml",
     *     summary="Get categoryImage.xml",
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
    public function getCategoryImageSitemap()
    {
        $sitemaps = Category::select(['id', 'name', 'image', 'updated_at'])
            ->whereNotNull('image')
            ->where('status', 'published')
            ->get()
            ->map(function ($category) {

                return [
                    'loc' => $category->image,
                    'lastmod' => $category->updated_at
                        ? $category->updated_at->toAtomString()
                        : now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

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

}