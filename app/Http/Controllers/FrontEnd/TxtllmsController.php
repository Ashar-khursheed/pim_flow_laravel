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
     * Get LLMS TXT.
     *
     * @OA\Get(
     *     path="/api/frontend/llms.txt",
     *     summary="Get llms.txt",
     *     description="Returns the llms.txt containing public URLs of the website.",
     *     tags={"Frontend TXT"},
     *     @OA\Response(
     *         response=200,
     *         description="llms.txt generated successfully",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(type="string", example="- [HorecaStore Supplies](https://thehorecastore.com/)")
     *         )
     *     ),
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
    public function getTxt()
    {
        try {
            $offset = 0;
            $limit = 1000;
            $txt = "- [about-us](https://thehorecastore.com/pages/about-us): HORECA STORE - Operating Supplies for Hotel & Cafe.";
            $txt .= "- [contact-us](https://thehorecastore.com/pages/contact-us): Explore HorecaStore for top-quality restaurant supplies and equipment.";

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
                    $txt .= " - [" . $txtText['primary_keyword'] . "](" . $txtText['fullurl'] . "): " . $txtText['title_tag']. "\n";
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
