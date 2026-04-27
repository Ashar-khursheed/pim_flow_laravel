<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SeoManagement;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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
    $seoQuery = SeoManagement::with('seo_secondary_keywords')
        ->orderBy('id', 'desc');

    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
        $path = parse_url($identifier, PHP_URL_PATH);
        $path = ltrim($path, '/');
        $seoQuery->where(function ($q) use ($identifier, $path) {
            $q->where('url', $identifier)
              ->orWhere('url', '/' . $path)
              ->orWhere('url', $path);
        });
    } elseif (is_numeric($identifier)) {
        $seoQuery->where('relational_id', $identifier);
    } else {
        $path = ltrim($identifier, '/');
        $seoQuery->where(function ($q) use ($identifier, $path) {
            $q->where('url', $identifier)
              ->orWhere('url', '/' . $path)
              ->orWhere('url', $path);
        });
    }

    $seoData = $seoQuery->get()->map(function ($item) {

        $filtered = $this->filterFields($item) ?? [];

        $raw = DB::table('seo_management')
            ->where('id', $item->id)
            ->value('schema');

        if (empty($raw)) {
            $filtered['schema']        = null;
            $filtered['canonical_url'] = null;
            return $filtered;
        }

        $decoded = $this->decodeSchema($raw);

        if (!is_array($decoded)) {
            $filtered['schema']        = null;
            $filtered['canonical_url'] = null;
            return $filtered;
        }

        if (!isset($decoded[0])) {
            $decoded = [$decoded];
        }

        // Clean descriptions
        foreach ($decoded as &$schemaItem) {
            if (!is_array($schemaItem) || empty($schemaItem['@graph'])) continue;
            foreach ($schemaItem['@graph'] as &$graph) {
                if (!is_array($graph) || !isset($graph['description'])) continue;
                if (is_array($graph['description'])) {
                    $graph['description'] = trim(strip_tags(implode(' ', array_filter($graph['description']))));
                } elseif (is_string($graph['description'])) {
                    $graph['description'] = trim(strip_tags($graph['description']));
                }
            }
            unset($graph);
        }
        unset($schemaItem);

        $filtered['schema'] = $decoded;

        // Canonical URL
        $canonicalUrl = null;
        foreach ($decoded as $schemaItem) {
            if (!is_array($schemaItem)) continue;
            if (!empty($schemaItem['@graph']) && is_array($schemaItem['@graph'])) {
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

        return $filtered;
    });

    return response()->json(
        ['status' => true, 'data' => $seoData],
        200,
        [],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

// ============================================================
// DECODE — multiple attempts with targeted fixes
// ============================================================
private function decodeSchema($raw)
{
    if (is_array($raw)) return $raw;

    // Attempt 1: Direct
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;

    // Stripslashes version
    $str = stripslashes($raw);

    // Attempt 2: Stripslashes only
    $decoded = json_decode($str, true);
    if (is_array($decoded)) return $decoded;

    // Attempt 3: Stripslashes + all fixes
    $fixed = $this->fixJsonString($str);
    $decoded = json_decode($fixed, true);
    if (is_array($decoded)) return $decoded;

    // Attempt 4: Control chars bhi hata do
    $fixed = preg_replace('/[\x00-\x1F\x7F]/u', '', $fixed);
    $decoded = json_decode($fixed, true);
    if (is_array($decoded)) return $decoded;

    // Attempt 5: Raw pe bhi try karo fixes ke saath
    $fixed2 = $this->fixJsonString($raw);
    $decoded = json_decode($fixed2, true);
    if (is_array($decoded)) return $decoded;

    return null;
}

// ============================================================
// FIX — sab known corruptions handle karo
// ============================================================
private function fixJsonString($str)
{
    // Fix 1: Inch marks  — 34" -> 34in
    $str = preg_replace('/(\d+(?:\.\d+)?)"/', '$1in', $str);

    // Fix 2: Description stored as array-string
    // Pattern after stripslashes: "description": "["<p>...</p>","<p>...</p>"]"
    // Convert karo clean plain text mein
    $str = preg_replace_callback(
        '/"description"\s*:\s*"(\[.*?\])"/s',
        function ($m) {
            $inner = $m[1];

            // Pehle JSON decode try karo
            $arr = json_decode($inner, true);
            if (is_array($arr)) {
                $text = trim(strip_tags(implode(' ', array_filter($arr))));
            } else {
                // Manual clean — brackets aur quotes hata do, HTML strip karo
                $text = str_replace(['["', '"]', '","', '\\"', '"'], '', $inner);
                $text = trim(strip_tags($text));
                $text = preg_replace('/\s+/', ' ', $text);
            }

            return '"description": ' . json_encode($text);
        },
        $str
    );

    return $str;
}

    // public function getByRelationalId($identifier)
    // {   
    //     $seoQuery = SeoManagement::with('seo_secondary_keywords')->orderBy('id', 'desc');

    //     if (filter_var($identifier, FILTER_VALIDATE_URL)) {
    //         $path = parse_url($identifier, PHP_URL_PATH);
    //         $path = ltrim($path, '/');

    //         $seoQuery->where(function ($q) use ($identifier, $path) {
    //             $q->where('url', $identifier)
    //             ->orWhere('url', '/' . $path)
    //             ->orWhere('url', $path);
    //         });
    //     } elseif (is_numeric($identifier)) {
    //         $seoQuery->where('relational_id', $identifier);
    //     } else {
    //         $seoQuery->where(function ($q) use ($identifier) {
    //             $path = ltrim($identifier, '/');
    //             $q->where('url', $identifier)
    //             ->orWhere('url', '/' . $path)
    //             ->orWhere('url', $path);
    //         });
    //     }

    //     $seoData = $seoQuery->get()->map(function ($item) {
    //         $filtered = $this->filterFields($item);

    //         if (!empty($filtered['schema'])) {
    //             $raw = $filtered['schema'];
    //             $decoded = null;

    //             if (is_array($raw)) {
    //                 $decoded = $raw;
    //             } else {
    //                 $raw = trim($raw);

    //                 $attempts = [
    //                     // 1. Direct decode
    //                     function($s) { 
    //                         return json_decode($s, true); 
    //                     },
    //                     // 2. Fix unescaped measurement quotes (e.g. 52" -> 52\")
    //                     function($s) { 
    //                         $s = preg_replace('/(\d)"/', '$1\\"', $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 3. Fix literal control characters
    //                     function($s) { 
    //                         $s = str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 4. Fix both measurement quotes + control characters
    //                     function($s) {
    //                         $s = preg_replace('/(\d)"/', '$1\\"', $s);
    //                         $s = str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 5. stripslashes
    //                     function($s) { 
    //                         return json_decode(stripslashes($s), true); 
    //                     },
    //                     // 6. Trim outer quotes
    //                     function($s) { 
    //                         return json_decode(trim($s, '"'), true); 
    //                     },
    //                     // 7. HTML entities
    //                     function($s) { 
    //                         return json_decode(html_entity_decode($s), true); 
    //                     }
    //                 ];

    //                 foreach ($attempts as $attempt) {
    //                     $res = $attempt($raw);

    //                     $limit = 5;
    //                     while (is_string($res) && $limit-- > 0) {
    //                         $res = json_decode($res, true);
    //                     }

    //                     if (json_last_error() === JSON_ERROR_NONE && is_array($res)) {
    //                         $decoded = $res;
    //                         break;
    //                     }
    //                 }
    //             }

    //             if (!is_array($decoded)) {
    //                 $filtered['schema']        = null;
    //                 $filtered['canonical_url'] = null;
    //             } else {
    //                 // Normalize to array of objects
    //                 if (!isset($decoded[0])) {
    //                     $decoded = [$decoded];
    //                 }

    //                 // Fix description array -> flat string across all @graph nodes
    //                 foreach ($decoded as &$schemaItem) {
    //                     if (!is_array($schemaItem) || empty($schemaItem['@graph'])) continue;

    //                     foreach ($schemaItem['@graph'] as &$graph) {
    //                         if (!isset($graph['description'])) continue;

    //                         if (is_array($graph['description'])) {
    //                             // Flatten array: remove nulls, strip HTML, join
    //                             $graph['description'] = trim(strip_tags(
    //                                 implode(' ', array_filter(
    //                                     $graph['description'],
    //                                     fn($v) => !is_null($v) && $v !== ''
    //                                 ))
    //                             ));
    //                         } elseif (is_string($graph['description'])) {
    //                             // Strip any leftover HTML tags from string descriptions
    //                             $graph['description'] = trim(strip_tags($graph['description']));
    //                         }
    //                     }
    //                     unset($graph);
    //                 }
    //                 unset($schemaItem);

    //                 $filtered['schema'] = $decoded;

    //                 // Extract canonical URL
    //                 $canonicalUrl = null;
    //                 foreach ($decoded as $schemaItem) {
    //                     if (!is_array($schemaItem)) continue;

    //                     if (!empty($schemaItem['@graph']) && is_array($schemaItem['@graph'])) {
    //                         foreach ($schemaItem['@graph'] as $graph) {
    //                             if (!empty($graph['url'])) {
    //                                 $canonicalUrl = $graph['url'];
    //                                 break 2;
    //                             }
    //                         }
    //                     }

    //                     if (!empty($schemaItem['url'])) {
    //                         $canonicalUrl = $schemaItem['url'];
    //                         break;
    //                     }
    //                 }

    //                 $filtered['canonical_url'] = $canonicalUrl;
    //             }
    //         }

    //         return $filtered;
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'data'   => $seoData
    //     ]);
    // }

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