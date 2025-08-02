<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SeoManagement;
use OpenApi\Annotations as OA;

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
public function getByRelationalId($identifier)
{
    $seoQuery = SeoManagement::with('seo_secondary_keywords');

    // Check if it's a full URL
    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
        $path = parse_url($identifier, PHP_URL_PATH); // Just in case you get full URLs
        $seoQuery->where('url', $path);
    } elseif (is_numeric($identifier)) {
        $seoQuery->where('relational_id', $identifier);
    } else {
        // Assume it's a slug or string relational_id
        $seoQuery->where('relational_id', $identifier);
    }

    $seoData = $seoQuery->get()->map(function ($item) {
        return $this->filterFields($item);
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

    // Check if identifier is a URL or slug-like path
    if (filter_var($identifier, FILTER_VALIDATE_URL) || str_contains($identifier, '/')) {
        $identifier = parse_url($identifier, PHP_URL_PATH); // optional: clean domain
        $seoQuery->where('url', $identifier);
    } else {
        $seoQuery->where('relational_id', $identifier);
    }

    // Apply optional filter for relational_type
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
            'banner_image_file' => $item->banner_image_file,
            'banner_image_alt_text' => $item->banner_image_alt_text,
            'popular_tags' => is_string($item->popular_tags)
                ? json_decode($item->popular_tags, true)
                : ($item->popular_tags ?? []),
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