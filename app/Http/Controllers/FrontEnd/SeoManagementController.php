<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SeoManagement;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Http;
class SeoManagementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/seo-management",
     *     summary="Get all SEO records",
     *     tags={"Frontend-SEO Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="url", type="string", example="https://example.com"),
     *                     @OA\Property(property="primary_keyword", type="string", example="SEO keyword"),
     *                     @OA\Property(property="title_tag", type="string", example="Page Title"),
     *                     @OA\Property(property="meta_title", type="string", example="Meta Title"),
     *                     @OA\Property(property="meta_description", type="string", example="Meta description"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="indexing", type="boolean", example=true),
     *                     @OA\Property(property="og_title", type="string", example="OG Title"),
     *                     @OA\Property(property="og_description", type="string", example="OG Description"),
     *                     @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
     *                     @OA\Property(property="og_image_name", type="string", example="image.jpg"),
     *                     @OA\Property(property="tags", type="string", example="tag1,tag2"),
     *                     @OA\Property(property="schema", type="string", example="schema markup")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $seoData = SeoManagement::with('seo_secondary_keywords')->get()->map(function ($item) {
            return $this->filterFields($item);
        });

        return response()->json([
            'status' => true,
            'data' => $seoData
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/seo-management/relational/{relational_id}",
     *     tags={"Frontend-SEO Management"},
     *     summary="Get SEO data by relational ID",
     *     description="Returns SEO management records for the given relational ID.",
     *     @OA\Parameter(
     *         name="relational_id",
     *         in="path",
     *         required=true,
     *         description="Relational ID to filter SEO data",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO data for the specified relational ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="url", type="string", example="https://example.com"),
     *                     @OA\Property(property="primary_keyword", type="string", example="SEO keyword"),
     *                     @OA\Property(property="title_tag", type="string", example="Page Title"),
     *                     @OA\Property(property="meta_title", type="string", example="Meta Title"),
     *                     @OA\Property(property="meta_description", type="string", example="Meta description"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="indexing", type="boolean", example=true),
     *                     @OA\Property(property="og_title", type="string", example="OG Title"),
     *                     @OA\Property(property="og_description", type="string", example="OG Description"),
     *                     @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
     *                     @OA\Property(property="og_image_name", type="string", example="image.jpg"),
     *                     @OA\Property(property="tags", type="string", example="tag1,tag2"),
     *                     @OA\Property(property="schema", type="string", example="schema markup")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

//     public function getByRelationalId($identifier)
// {
//     $seoQuery = SeoManagement::with('seo_secondary_keywords');

//     if (filter_var($identifier, FILTER_VALIDATE_URL)) {
//         // Handle full URL
//         $path = parse_url($identifier, PHP_URL_PATH);
//         $path = ltrim($path, '/');

//         $seoQuery->where(function ($q) use ($identifier, $path) {
//             $q->where('url', $identifier)         // full URL
//               ->orWhere('url', '/' . $path)       // with leading slash
//               ->orWhere('url', $path);            // without leading slash
//         });
//     } elseif (is_numeric($identifier)) {
//         // Handle numeric relational_id
//         $seoQuery->where('relational_id', $identifier);
//     } else {
//         // Handle string identifier like "countertop-gas-ranges"
//         $seoQuery->where(function ($q) use ($identifier) {
//             $path = ltrim($identifier, '/');
//             $q->where('url', $identifier)
//               ->orWhere('url', '/' . $path)
//               ->orWhere('url', $path);
//         });
//     }

//     $seoData = $seoQuery->get()->map(function ($item) {
//         $filtered = $this->filterFields($item);

//         // Decode schema JSON
//         if (!empty($filtered['schema']) && is_string($filtered['schema'])) {
//             $decoded = json_decode($filtered['schema'], true);
//             if (json_last_error() === JSON_ERROR_NONE) {
//                 if (!empty($decoded['@type']) && !empty($decoded['url'])) {
//                     $baseUrl = url("/");
//                     if (strtolower($decoded['@type']) === 'product') {
//                         $decoded['url'] = $baseUrl . 'products/' . ltrim($decoded['url'], '/');
//                     } elseif (strtolower($decoded['@type']) === 'category') {
//                         $decoded['url'] = $baseUrl . 'collections/' . ltrim($decoded['url'], '/');
//                     }
//                 }
//                 $filtered['schema'] = $decoded;
//             }
//         }

//         return $filtered;
//     });

//     return response()->json([
//         'status' => true,
//         'data' => $seoData
//     ]);
// }

public function getByRelationalId($identifier)
{   
    $seoQuery = SeoManagement::with('seo_secondary_keywords')->orderBy('id', 'desc');

    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
        // Handle full URL
        $path = parse_url($identifier, PHP_URL_PATH);
        $path = ltrim($path, '/');

        $seoQuery->where(function ($q) use ($identifier, $path) {
            $q->where('url', $identifier)         // full URL
              ->orWhere('url', '/' . $path)       // with leading slash
              ->orWhere('url', $path);            // without leading slash
        });
    } elseif (is_numeric($identifier)) {
        // Handle numeric relational_id
        $seoQuery->where('relational_id', $identifier);
    } else {
        // Handle string identifier
        $seoQuery->where(function ($q) use ($identifier) {
            $path = ltrim($identifier, '/');
            $q->where('url', $identifier)
              ->orWhere('url', '/' . $path)
              ->orWhere('url', $path);
        });
    }

    $seoData = $seoQuery->get()->map(function ($item) {
        $filtered = $this->filterFields($item);

        $canonicalUrl = null;

        // Decode schema JSON
        // if (!empty($filtered['schema']) && is_string($filtered['schema'])) {
        //     $decoded = json_decode($filtered['schema'], true);
          
        //     if (json_last_error() === JSON_ERROR_NONE) {
  
        //         // If schema is an array
        //         if (is_array($decoded) && isset($decoded[0])) {
                      
        //             foreach ($decoded as $schemaItem) {
        //                 if (isset($schemaItem['url'])) {
        //                     $canonicalUrl = $schemaItem['url'];
        //                     break; // take first one that has URL
        //                 }
        //             }
        //         } else {
                     
        //             // Single schema object
        //             if (!empty($decoded['url'])) {
        //                 $canonicalUrl = config('app.url').'/'.$decoded['url'];
        //             }
                   
        //               if (!empty($decoded['@type']) && !empty($decoded['url'])) {                                           
        //                 $decoded['url'] = config('app.url').'/'.$decoded['url'];                        
        //             }
        //             // Adjust product/category URL if needed
        //             // if (!empty($decoded['@type']) && !empty($decoded['url'])) {
        //             //     $baseUrl = url(path: "/");
        //             //     if (strtolower($decoded['@type']) === 'product') {
        //             //         $decoded['url'] = $baseUrl . 'products/' . ltrim($decoded['url'], '/');
        //             //     } elseif (strtolower($decoded['@type']) === 'category') {
        //             //         $decoded['url'] = $baseUrl . 'collections/' . ltrim($decoded['url'], '/');
        //             //     }
        //             // }
        //         }
 
        //         $filtered['schema'] = $decoded;
        //     }
        // }
        if (!empty($filtered['schema'])) {

    $raw = $filtered['schema'];
    $decoded = null;

    // 1️⃣ If already array (rare case)
    if (is_array($raw)) {
        $decoded = $raw;
    } else {

        // 2️⃣ Clean string
        $raw = trim($raw);

        // 3️⃣ Try direct decode
        $decoded = json_decode($raw, true);

        // 4️⃣ If failed → fix escaped slashes
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode(stripslashes($raw), true);
        }

        // 5️⃣ If still failed → remove outer quotes
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode(trim($raw, '"'), true);
        }

        // 6️⃣ If STILL failed → force clean (last attempt)
        if (json_last_error() !== JSON_ERROR_NONE) {
            $clean = html_entity_decode($raw);
            $decoded = json_decode($clean, true);
        }
    }

    // ❌ If still invalid
    if (!is_array($decoded)) {
        $filtered['schema'] = null;
        $filtered['canonical_url'] = null;
    } else {

        // ✅ optional: normalize multiple schema objects
        $filtered['schema'] = $decoded;

        // ✅ extract canonical URL safely
        $canonicalUrl = null;

        foreach ($decoded as $schemaItem) {

            if (!empty($schemaItem['@graph'])) {
                foreach ($schemaItem['@graph'] as $graph) {
                    if (!empty($graph['url'])) {
                        $canonicalUrl = $graph['url'];
                        break 2;
                    }
                }
            }

            if (!empty($schemaItem['url'])) {
                $canonicalUrl = $schemaItem['url'];
                break;
            }
        }

        $filtered['canonical_url'] = $canonicalUrl;
    }
}

        // Attach canonical URL separately

        return $filtered;
    });

    return response()->json([
        'status' => true,
        'data' => $seoData
    ]);
}



    /**
     * @OA\Get(
     *     path="/api/frontend/seo/paragraphs/{relational_id}",
     *     tags={"Frontend-SEO Management"},
     *     summary="Get SEO paragraph data by relational ID",
     *     description="Returns only paragraph-related SEO data for a given relational ID.",
     *     @OA\Parameter(
     *         name="relational_id",
     *         in="path",
     *         required=true,
     *         description="Relational ID for paragraph data",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO paragraph data",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="paragraph_1", type="string", example="Paragraph 1 text"),
     *                     @OA\Property(property="paragraph_2", type="string", example="Paragraph 2 text"),
     *                     @OA\Property(property="paragraph_3", type="string", example="Paragraph 3 text"),
     *                     @OA\Property(property="paragraph_4", type="string", example="Paragraph 4 text"),
     *                     @OA\Property(property="popular_tags", type="array", @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getParagraphData(Request $request, $identifier)
    {
        $relationalType = $request->query('relational_type'); // optional filter
 
        $seoQuery = SEOManagement::query();

        // If a full URL is passed, extract only the path
        if (filter_var($identifier, FILTER_VALIDATE_URL)) {
            $identifier = parse_url($identifier, PHP_URL_PATH);
        }

        // Now treat identifier as a slug/path and match using 'slug' column
        $seoQuery->where('url', ltrim($identifier, '/'));

        // Apply optional relational_type filter
        if ($relationalType) {
            $seoQuery->where('relational_type', $relationalType);
        }

        $seoData = $seoQuery->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'relational_id' => $item->relational_id,
                'relational_type' => $item->relational_type,
                'internal_links' => $item->internal_links,
                'cat_desc' => $item->cat_desc,
                'paragraph_1' => $item->paragraph_1,
                'paragraph_2' => $item->paragraph_2,
                'paragraph_3' => $item->paragraph_3,
                'paragraph_4' => $item->paragraph_4,
                'banner_slug' => $item->banner_slug,
                'banner_image_file' => $item->banner_image_file,
                'banner_image_alt_text' => $item->banner_image_alt_text,
                'popular_tags' => is_string($item->popular_tags)
                    ? json_decode($item->popular_tags, true)
                    : ($item->popular_tags ?? []),
                'popularTag_details' => is_string($item->popularTag_details)
                ? json_decode($item->popularTag_details, true)
                : ($item->popularTag_details ?? []),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $seoData
        ]);
    }



    private function filterFields($item)
    {
        return [
            'id' => $item->id,
            'relational_id' => $item->relational_id,
            'relational_type' => $item->relational_type,
            'url' => $item->url,
            'primary_keyword' => $item->primary_keyword,
            'title_tag' => $item->title_tag,
            'meta_title' => $item->meta_title,
            'meta_description' => $item->meta_description,
            'internal_links' => $item->internal_links,
            'indexing' => $item->indexing,
            'og_title' => $item->og_title,
            'og_description' => $item->og_description,
            'og_image_url' => $item->og_image_url,
            'og_image_alt_text' => $item->og_image_alt_text,
            'og_image_name' => $item->og_image_name,
            'tags' => $item->tags,
            'schema' => $item->schema
        ];
    }


}