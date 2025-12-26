<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Blog;
use App\Models\SeoManagement;
use App\Models\Brand;
class TxtllmsController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/frontend/llms.txt",
     *     summary="Get llms.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *    @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 500)",
     *         required=false,
     *         @OA\Schema(type="integer", example=500)
     *     ),    
     *     @OA\Response(
     *         response=200,
     *         description="llms.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="- [HorecaStore Supplies](https://thehorecastore.com/)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getAllPageTxt(Request $request)
    {           

          try {
            $txt = "- [about-us](https://thehorecastore.com/pages/about-us): HORECA STORE - Operating Supplies for Hotel & Cafe.";
            $txt .= "- [contact-us](https://thehorecastore.com/pages/contact-us): Explore HorecaStore for top-quality restaurant supplies and equipment.";

            $perPage = $request->input('per_page');

            $query = Product::with([
                    'seoProductUrl:id,relational_id,url',
                    'seoManagement:id,relational_id,title_tag,primary_keyword'
                ])
                ->whereHas('seoProductUrl', function ($q) {
                    $q->whereNotNull('url');
                })
                ->where('status', 'published');

            // 🚀 If per_page is empty/null, fetch ALL results
            if (!empty($perPage)) {
                $products = $query->paginate($perPage, ['*'], 'page', 1);
            } else {
                $products = $query->get();
            }

            $txtfiles = $products->map(function ($product) {
                return [
                    'fullurl' => config('app.url') . '/' . 
                        $product->parent_category_url() . '/' .
                        $product->category_url() . '/' .
                        ($product->seoProductUrl->url ?? ""),

                    'title_tag' => $product->seoManagement->title_tag ?? '',
                    'primary_keyword' => $product->seoManagement->primary_keyword ?? '',
                ];
            });

            if ($txtfiles) {
                foreach ($txtfiles as $txtText) {
                    $txt .= " - [" . $txtText['primary_keyword'] . "](" . $txtText['fullurl'] . "): " . $txtText['title_tag'] . "\n";
                }
            }

            return response($txt, 200)->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error generating txt file',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/llms-1.txt",
     *     summary="Get llms-1.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-1.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="- [HorecaStore Supplies](https://thehorecastore.com/)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt1()
    {
        return $this->getProductsTxt(0, 1000);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/llms-2.txt",
     *     summary="Get llms-2.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-2.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="- [HorecaStore Supplies](https://thehorecastore.com/)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt2()
    {
        return $this->getProductsTxt(1000, 1000);
    }

    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-3.txt",
     *     summary="Get llms-3.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-3.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="- [HorecaStore Supplies](https://thehorecastore.com/)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt3()
    {
        return $this->getProductsTxt(2000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-4.txt",
     *     summary="Get llms-4.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-4.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt4()
    {
        return $this->getProductsTxt(3000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-5.txt",
     *     summary="Get llms-5.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-5.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt5()
    {
        return $this->getProductsTxt(4000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-6.txt",
     *     summary="Get llms-6.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-6.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt6()
    {
        return $this->getProductsTxt(5000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-7.txt",
     *     summary="Get llms-7.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-7.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt7()
    {
        return $this->getProductsTxt(6000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-8.txt",
     *     summary="Get llms-8.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-8.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt8()
    {
        return $this->getProductsTxt(7000, 1000);
    }
    /**
     * Get LLMS TXT
     *
     * @OA\Get(
     *     path="/api/frontend/llms-9.txt",
     *     summary="Get llms-9.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="llms-9.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example=""
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating txt file")
     *         )
     *     )
     * )
     */

    public function getProductsTxt9()
    {
        return $this->getProductsTxt(7000, 1000);
    }


    public function getProductsTxt($offset, $limit)
    { 
        try {

            $txt = "";
            $txtfiles = Product::with([
                'seoProductUrl:id,relational_id,url',
                'seoManagement:id,relational_id,title_tag,primary_keyword'
            ])

                ->whereHas('seoProductUrl', function ($q) {
                    $q->whereNotNull('url');
                })

                ->where('status', 'published')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($product) {

                    return [
                        'fullurl' => config('app.url') . '/' . $product->parent_category_url() . '/' .
                            $product->category_url() . '/' .
                            ($product->seoProductUrl->url ?? ""),

                        'title_tag' => $product->seoManagement->title_tag ?? '',
                        'primary_keyword' => $product->seoManagement->primary_keyword ?? '',
                    ];
                });
            if ($txtfiles) {
                foreach ($txtfiles as $txtText) {
                    $txt .= " - [" . $txtText['primary_keyword'] . "](" . $txtText['fullurl'] . "): " . $txtText['title_tag'] . "\n";
                }
            }


            return response($txt, 200)->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error generating txt file'
            ], 500);
        }
    }

}
