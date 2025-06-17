<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Blogs",
 *     description="API endpoints for managing blogs"
 * )
 */
class BlogController extends Controller
{
   /**
 * @OA\Get(
 *     path="/api/blogs",
 *     tags={"Blogs"},
 *     security={{"bearerAuth":{}}},
 *     summary="Get paginated list of blogs with search and sorting",
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search blogs by title",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Sort by column (e.g., created_at, title)",
 *         required=false,
 *         @OA\Schema(type="string", default="created_at")
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order (asc or desc)",
 *         required=false,
 *         @OA\Schema(type="string", default="desc")
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number for pagination",
 *         required=false,
 *         @OA\Schema(type="integer", default=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of items per page",
 *         required=false,
 *         @OA\Schema(type="integer", default=10)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Paginated list of blogs",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Blogs fetched successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/Blog")
 *             ),
 *             @OA\Property(
 *                 property="pagination",
 *                 type="object",
 *                 @OA\Property(property="total", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="current_page", type="integer"),
 *                 @OA\Property(property="last_page", type="integer"),
 *                 @OA\Property(property="from", type="integer"),
 *                 @OA\Property(property="to", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
public function index(Request $request)
{
    try {
        $query = Blog::query();

        // Search filter by title
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = (int) $request->query('per_page', 10);
        $blogs = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Blogs fetched successfully',
            'data' => $blogs->items(),
            'pagination' => [
                'total' => $blogs->total(),
                'per_page' => $blogs->perPage(),
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'from' => $blogs->firstItem(),
                'to' => $blogs->lastItem(),
            ]
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch blogs',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    
    /**
     * @OA\Post(
     *     path="/api/blogs",
     *     operationId="storeBlog",
     *     tags={"Blogs"},
     *     summary="Create a new blog post",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "status"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="desktop_banner", type="file"),
     *                 @OA\Property(property="desktop_banner_alt", type="string"),
     *                 @OA\Property(property="mobile_banner", type="file"),
     *                 @OA\Property(property="mobile_banner_alt", type="string"),
     *                 @OA\Property(property="thumbnail", type="file"),
     *                 @OA\Property(property="thumbnail_alt", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="faqs", type="string"),
     *                 @OA\Property(property="tags", type="string"),
     *                 @OA\Property(property="image", type="file"),
     *                 @OA\Property(property="written_by", type="string"),
     *                 @OA\Property(property="created_date", type="string"),
     *                 @OA\Property(property="blog_category_id", type="integer"),
     *                 @OA\Property(property="status", type="draft"),
     *                 @OA\Property(property="is_featured", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blog created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Blog")
     *         )
     *     )
     * )
     */
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'slug' => 'nullable|string|unique:blogs,slug',
    //         'desktop_banner' => 'nullable|image',
    //         'desktop_banner_alt' => 'nullable|string',
    //         'mobile_banner' => 'nullable|image',
    //         'mobile_banner_alt' => 'nullable|string',
    //         'thumbnail' => 'nullable|image',
    //         'thumbnail_alt' => 'nullable|string',
    //         'description' => 'nullable|string',
    //         'faqs' => 'nullable|string',
    //         'tags' => 'nullable|string',
    //         'blog_category_id' => 'nullable|exists:blog_categories,id',
    //         'status' => 'required|in:draft,published',
    //         'is_featured' => 'nullable|boolean',
    //         'written_by' => 'nullable|string',
    //         'created_date' => 'nullable|date',
    //         'image' => 'nullable|string', // Assuming this is a string path
    //     ]);

    //     $pathPrefix = env('STORAGE_ENV') . '/blogs/' . Str::slug($request->name);

    //     $uploadToS3 = fn($file) => $file
    //     ? Storage::disk('s3')->url(
    //         Storage::disk('s3')->put($pathPrefix, $file)
    //     )
    //     : null;


    //     $data = [
    //     'name' => $request->name,
    //     'slug' => $request->slug ?? Str::slug($request->name),
    //     'desktop_banner' => $uploadToS3($request->file('desktop_banner')),
    //     'desktop_banner_alt' => $request->desktop_banner_alt,
    //     'mobile_banner' => $uploadToS3($request->file('mobile_banner')),
    //     'mobile_banner_alt' => $request->mobile_banner_alt,
    //     'thumbnail' => $uploadToS3($request->file('thumbnail')),
    //     'thumbnail_alt' => $request->thumbnail_alt,
    //     'description' => json_decode($request->description, true),
    //     'faqs' => json_decode($request->faqs, true),
    //     'tags' => json_decode($request->tags, true),
    //     'blog_category_id' => $request->blog_category_id,
    //     'status' => $request->status,
    //     'is_featured' => $request->is_featured ?? false,
    //     'created_by' => auth()->id(),
    //     ];

    //     $blog = Blog::create($data);

    //     return response()->json([
    //         'message' => 'Blog created successfully.',
    //         'data' => $blog
    //     ], 201);
    // }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:blogs,slug',
            'desktop_banner' => 'nullable|image',
            'desktop_banner_alt' => 'nullable|string',
            'mobile_banner' => 'nullable|image',
            'mobile_banner_alt' => 'nullable|string',
            'thumbnail' => 'nullable|image',
            'thumbnail_alt' => 'nullable|string',
            'description' => 'nullable|string',
            'faqs' => 'nullable|string',
            'tags' => 'nullable|string',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'status' => 'required|in:draft,published',
            'is_featured' => 'nullable|boolean',

            // Corrected validation for the new fields
            'written_by' => 'nullable|string',
            'created_date' => 'nullable|date',
            'image' => 'nullable|image', // Assuming this is a string path
        ]);

        $pathPrefix = env('STORAGE_ENV') . '/blogs/' . Str::slug($request->name);

        $uploadToS3 = fn($file) => $file
            ? Storage::disk('s3')->url(
                Storage::disk('s3')->put($pathPrefix, $file)
            )
            : null;

        $data = [
            'name' => $request->name,
            'slug' => $request->slug ?? Str::slug($request->name),
            'desktop_banner' => $uploadToS3($request->file('desktop_banner')),
            'desktop_banner_alt' => $request->desktop_banner_alt,
            'mobile_banner' => $uploadToS3($request->file('mobile_banner')),
            'mobile_banner_alt' => $request->mobile_banner_alt,
            'thumbnail' => $uploadToS3($request->file('thumbnail')),
            'thumbnail_alt' => $request->thumbnail_alt,
            'description' => json_decode($request->description, true),
            'faqs' => json_decode($request->faqs, true),
            'tags' => json_decode($request->tags, true),
            'blog_category_id' => $request->blog_category_id,
            'status' => $request->status,
            'is_featured' => $request->is_featured ?? false,
            'created_by' => auth()->id(),

            // Added new fields
            'written_by' => $request->written_by,
            'created_date' => $request->created_date,
            'image' => $uploadToS3($request->file('image')),
        ];

        $blog = Blog::create($data);

        return response()->json([
            'message' => 'Blog created successfully.',
            'data' => $blog
        ], 201);
    }


    /**
     * @OA\Get(
     *     path="/api/blogs/{id}",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get blog details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $blog = Blog::findOrFail($id);
        return response()->json($blog);
    }

   /**
     * @OA\Post(
     *     path="/api/blogs/{id}",
     *     operationId="updateBlog",
     *     tags={"Blogs"},
     *     summary="Update an existing blog post",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="desktop_banner", type="file"),
     *                 @OA\Property(property="desktop_banner_alt", type="string"),
     *                 @OA\Property(property="mobile_banner", type="file"),
     *                 @OA\Property(property="mobile_banner_alt", type="string"),
     *                 @OA\Property(property="thumbnail", type="file"),
     *                 @OA\Property(property="thumbnail_alt", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="faqs", type="string"),
     *                 @OA\Property(property="tags", type="string"),
     *                 @OA\Property(property="image", type="file"),
     *                 @OA\Property(property="written_by", type="string"),
     *                 @OA\Property(property="created_date", type="string"),
     *                 @OA\Property(property="blog_category_id", type="integer"),
     *                 @OA\Property(property="status", type="string", enum={"draft", "published"}),
     *                 @OA\Property(property="is_featured", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Blog")
     *         )
     *     )
     * )
    */
    // public function update(Request $request, $id)
    // {
    //     // Check method spoofing manually since this is a POST route
    //     if ($request->_method !== 'PUT') {
    //         return response()->json(['message' => 'Invalid method. Use _method=PUT in form data.'], 405);
    //     }

    //     $blog = Blog::findOrFail($id);

    //     $request->validate([
    //         'name' => 'sometimes|required|string|max:255',
    //         'slug' => 'nullable|string|unique:blogs,slug,' . $blog->id,
    //         'desktop_banner' => 'nullable|image',
    //         'desktop_banner_alt' => 'nullable|string',
    //         'mobile_banner' => 'nullable|image',
    //         'mobile_banner_alt' => 'nullable|string',
    //         'thumbnail' => 'nullable|image',
    //         'thumbnail_alt' => 'nullable|string',
    //         'description' => 'nullable|string',
    //         'faqs' => 'nullable|string',
    //         'tags' => 'nullable|string',
    //         'blog_category_id' => 'nullable|exists:blog_categories,id',
    //         'status' => 'required|in:draft,published',
    //         'is_featured' => 'nullable|boolean',
    //     ]);

    //     $pathPrefix = env('STORAGE_ENV') . '/blogs/' . Str::slug($request->name ?? $blog->name);

    //     $uploadToS3 = fn($file) => $file
    //         ? Storage::disk('s3')->url(
    //             Storage::disk('s3')->put($pathPrefix, $file)
    //         )
    //         : null;

    //     $data = [
    //         'name' => $request->name ?? $blog->name,
    //         'slug' => $request->slug ?? $blog->slug,
    //         'desktop_banner_alt' => $request->desktop_banner_alt ?? $blog->desktop_banner_alt,
    //         'mobile_banner_alt' => $request->mobile_banner_alt ?? $blog->mobile_banner_alt,
    //         'thumbnail_alt' => $request->thumbnail_alt ?? $blog->thumbnail_alt,
    //         'description' => $request->filled('description') ? json_decode($request->description, true) : $blog->description,
    //         'faqs' => $request->filled('faqs') ? json_decode($request->faqs, true) : $blog->faqs,
    //         'tags' => $request->filled('tags') ? json_decode($request->tags, true) : $blog->tags,
    //         'blog_category_id' => $request->blog_category_id ?? $blog->blog_category_id,
    //         'status' => $request->status,
    //         'is_featured' => $request->is_featured ?? $blog->is_featured,
    //     ];

    //     if ($request->hasFile('desktop_banner')) {
    //         $data['desktop_banner'] = $uploadToS3($request->file('desktop_banner'));
    //     }

    //     if ($request->hasFile('mobile_banner')) {
    //         $data['mobile_banner'] = $uploadToS3($request->file('mobile_banner'));
    //     }

    //     if ($request->hasFile('thumbnail')) {
    //         $data['thumbnail'] = $uploadToS3($request->file('thumbnail'));
    //     }

    //     $blog->update($data);

    //     return response()->json([
    //         'message' => 'Blog updated successfully.',
    //         'data' => $blog
    //     ]);
    // }
    public function update(Request $request, $id)
    {
        // Check method spoofing manually since this is a POST route
        if ($request->_method !== 'PUT') {
            return response()->json(['message' => 'Invalid method. Use _method=PUT in form data.'], 405);
        }

        $blog = Blog::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|unique:blogs,slug,' . $blog->id,
            'desktop_banner' => 'nullable|image',
            'desktop_banner_alt' => 'nullable|string',
            'mobile_banner' => 'nullable|image',
            'mobile_banner_alt' => 'nullable|string',
            'thumbnail' => 'nullable|image',
            'thumbnail_alt' => 'nullable|string',
            'description' => 'nullable|string',
            'faqs' => 'nullable|string',
            'tags' => 'nullable|string',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'status' => 'required|in:draft,published',
            'is_featured' => 'nullable|boolean',

            // New fields
            'written_by' => 'nullable|string',
            'created_date' => 'nullable|date',
            'nullable|image', // Assuming frontend passes path or URL
        ]);

        $pathPrefix = env('STORAGE_ENV') . '/blogs/' . Str::slug($request->name ?? $blog->name);

        $uploadToS3 = fn($file) => $file
            ? Storage::disk('s3')->url(
                Storage::disk('s3')->put($pathPrefix, $file)
            )
            : null;

        $data = [
            'name' => $request->name ?? $blog->name,
            'slug' => $request->slug ?? $blog->slug,
            'desktop_banner_alt' => $request->desktop_banner_alt ?? $blog->desktop_banner_alt,
            'mobile_banner_alt' => $request->mobile_banner_alt ?? $blog->mobile_banner_alt,
            'thumbnail_alt' => $request->thumbnail_alt ?? $blog->thumbnail_alt,
            'description' => $request->filled('description') ? json_decode($request->description, true) : $blog->description,
            'faqs' => $request->filled('faqs') ? json_decode($request->faqs, true) : $blog->faqs,
            'tags' => $request->filled('tags') ? json_decode($request->tags, true) : $blog->tags,
            'blog_category_id' => $request->blog_category_id ?? $blog->blog_category_id,
            'status' => $request->status,
            'is_featured' => $request->is_featured ?? $blog->is_featured,

            // New fields
            'written_by' => $request->written_by ?? $blog->written_by,
            'created_date' => $request->created_date ?? $blog->created_date,
            'image' => $request->image ?? $blog->image,
        ];

        if ($request->hasFile('desktop_banner')) {
            $data['desktop_banner'] = $uploadToS3($request->file('desktop_banner'));
        }

        if ($request->hasFile('mobile_banner')) {
            $data['mobile_banner'] = $uploadToS3($request->file('mobile_banner'));
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $uploadToS3($request->file('thumbnail'));
        }
        if ($request->hasFile('image')) {
            $data['image'] = $uploadToS3($request->file('image'));
        }

        $blog->update($data);

        return response()->json([
            'message' => 'Blog updated successfully.',
            'data' => $blog
        ]);
    }




   /**
     * @OA\Delete(
     *     path="/api/blogs/{id}",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     summary="Delete blog by ID",
     *     description="Deletes a specific blog post by its ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the blog to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blog not found.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $blog = Blog::find($id);

        if (!$blog) {
            return response()->json([
                'success' => false,
                'message' => 'Blog not found.'
            ], 404);
        }

        $blog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blog deleted successfully.'
        ], 200);
    }

}
