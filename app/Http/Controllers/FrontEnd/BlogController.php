<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\Product;
use App\Models\BlogCategory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\BlogComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;


class BlogController extends Controller
{

   /**
     * @OA\Get(
     *     path="/api/frontend/blogs",
     *     summary="Get list of blogs",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of blogs per page",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_featured",
     *         in="query",
     *         description="Filter by featured blogs",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of blogs"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $isFeatured = $request->get('is_featured');
        $page = $request->get('page', 1);

        $cacheKey = "blogs_index_{$perPage}_{$page}_{$isFeatured}";

        $blogs = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($perPage, $isFeatured) {
            $query = Blog::with(['category:id,name,slug,description'])
                ->select(['id', 'name', 'slug', 'description', 'desktop_banner', 'desktop_banner_alt', 'mobile_banner', 'mobile_banner_alt', 'thumbnail', 'thumbnail_alt', 'tags', 'total_views', 'total_likes', 'total_shares', 'is_featured', 'created_at', 'blog_category_id'])
                ->where('status', 'published');

            if ($isFeatured !== null) {
                $query->where('is_featured', (int) $isFeatured);
            }

            $blogs = $query->orderByDesc('created_at')->paginate($perPage);

            $blogs->getCollection()->transform(function ($blog) {
                return $this->formatBlog1($blog);
            });

            return $blogs;
        });

        return response()->json($blogs);
    }


    protected function formatBlog1($blog)
    {
        // description/faqs/tags are already cast to arrays by the Blog model
        $description = $blog->description ?? [];

        return [
            'id' => $blog->id,
            'name' => $blog->name,
            'slug' => $blog->slug,
            'description' => isset($description[0]) ? [$description[0]] : [], // Only first paragraph for listing
            'desktop_banner' => $blog->desktop_banner,
            'desktop_banner_alt' => $blog->desktop_banner_alt,
            'mobile_banner' => $blog->mobile_banner,
            'mobile_banner_alt' => $blog->mobile_banner_alt,
            'thumbnail' => $blog->thumbnail,
            'thumbnail_alt' => $blog->thumbnail_alt,
            'tags' => $blog->tags ?? [],
            'total_views' => $blog->total_views ?? 0,
            'total_likes' => $blog->total_likes ?? 0,
            'total_shares' => $blog->total_shares ?? 0,
            'is_featured' => $blog->is_featured ?? 0,
            'created_at' => $blog->created_at,
            'category' => [
                'id' => $blog->category->id ?? null,
                'name' => $blog->category->name ?? null,
                'slug' => $blog->category->slug ?? null,
                'description' => $blog->category->description ?? null,
            ]
        ];
    }

    // Alternative simpler approach if you're sure about the data structure
    protected function formatBlog1Alternative($blog)
    {
        $description = [];
        if ($blog->description) {
            $firstDecode = json_decode($blog->description, true);
            // If first decode gives us a string, decode again
            if (is_string($firstDecode)) {
                $description = json_decode($firstDecode, true) ?? [];
            } else {
                $description = $firstDecode ?? [];
            }
        }

        return [
            'id' => $blog->id,
            'name' => $blog->name,
            'slug' => $blog->slug,
            'description' => $description,
            'desktop_banner' => $blog->desktop_banner,
            'desktop_banner_alt' => $blog->desktop_banner_alt,
            'mobile_banner' => $blog->mobile_banner,
            'mobile_banner_alt' => $blog->mobile_banner_alt,
            'thumbnail' => $blog->thumbnail,
            'thumbnail_alt' => $blog->thumbnail_alt,
            'created_date' => $blog->created_date,
            'written_by' => $blog->written_by,
               'author_designation' => $blog->author_designation,
             'author_desc' => $blog->author_desc,
            'image' => $blog->image,
            'tags' => $blog->tags ?? [],
            'total_views' => $blog->total_views ?? 0,
            'total_likes' => $blog->total_likes ?? 0,
            'total_shares' => $blog->total_shares ?? 0,
            'is_featured' => $blog->is_featured ?? 0,
            'faqs' => $blog->faqs,
            'created_at' => $blog->created_at,
            'category' => [
                'id' => $blog->category->id ?? null,
                'name' => $blog->category->name ?? null,
                'slug' => $blog->category->slug ?? null,
                'description' => $blog->category->description ?? null,
            ]
        ];
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/blogs/{slug}",
     *     summary="Get blog details by slug",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug of the blog",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog details"
     *     )
     * )
     */
    public function show($slug)
    {
        $data = Cache::remember("blog_show_{$slug}", now()->addMinutes(60), function () use ($slug) {
            $blog = Blog::with(['category:id,name,slug,description'])
                ->where('slug', $slug)
                ->where('status', 'published')
                ->firstOrFail();

            return $this->formatBlog($blog);
        });

        return response()->json($data);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/like",
     *     summary="Like a blog post",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog liked"
     *     )
     * )
     */
    public function like($id)
    {
        $blog = Blog::findOrFail($id);
        $ip = request()->ip();
        $liked = Cache::has("blog_like_{$id}_{$ip}");
        if ($liked) {
        return response()->json(['total_likes' => $blog->total_likes]);
        }
        Cache::put("blog_like_{$id}_{$ip}", true, now()->addDays(30));
        $blog->increment('total_likes');
        return response()->json(['total_likes' => $blog->total_likes]);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/share",
     *     summary="Share a blog post",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog shared"
     *     )
     * )
     */
    public function share($id)
    {
        $blog = Blog::findOrFail($id);
        $ip = request()->ip();
        $liked = Cache::has("blog_share_{$id}_{$ip}");
        if ($liked) {
        return response()->json(['total_shares' => $blog->total_shares]);
        }
        Cache::put("blog_share_{$id}_{$ip}", true, now()->addDays(30));
        $blog->increment('total_shares');
        return response()->json(['total_shares' => $blog->total_shares]);
    }

     /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/view",
     *     summary="View a blog post",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog viewed"
     *     )
     * )
     */
    public function view($id)
    {
        $blog = Blog::findOrFail($id);
        $ip = request()->ip();
        $liked = Cache::has("blog_view_{$id}_{$ip}");
        if ($liked) {
        return response()->json(['total_views' => $blog->total_views]);
        }
        Cache::put("blog_view_{$id}_{$ip}", true, now()->addDays(30));
        $blog->increment('total_views');
        return response()->json(['total_views' => $blog->total_views]);
    }

     /**
     * @OA\Get(
     *     path="/api/frontend/blog-categories",
     *     summary="Get list of blog categories",
     *     tags={"Frontend-Blogs"},
     *     @OA\Response(
     *         response=200,
     *         description="List of blog categories"
     *     )
     * )
     */
    public function categories()
    {
        $categories = Cache::remember('blog_categories', now()->addMinutes(60), function () {
            return BlogCategory::where('status', 'published')
                ->orderBy('order', 'asc')
                ->get();
        });

        return response()->json($categories);
    }

    private function formatBlog($blog)
    {
        // description/faqs/tags are already cast to arrays by the Blog model
        $description = $blog->description ?? [];

        return [
             'id' => $blog->id,
             'name' => $blog->name,
             'slug' => $blog->slug,
             'description' => $description, // Now this will be an array of objects
             'desktop_banner' => $blog->desktop_banner,
             'desktop_banner_alt' => $blog->desktop_banner_alt,
             'mobile_banner' => $blog->mobile_banner,
             'created_date' => $blog->created_date,
             'written_by' => $blog->written_by,
             'author_designation' => $blog->author_designation,
             'author_desc' => $blog->author_desc,
             'image' => $blog->image,
             'mobile_banner_alt' => $blog->mobile_banner_alt,
             'thumbnail' => $blog->thumbnail,
             'thumbnail_alt' => $blog->thumbnail_alt,
              'faqs' => $blog->faqs,
              'tags' => $blog->tags,
              'total_views' => $blog->total_views,
              'total_likes' => $blog->total_likes,
              'total_shares' => $blog->total_shares,
             'is_featured' => $blog->is_featured,
              'created_at' => $blog->created_at,
              'category' => $blog->category ? [
                'id' => $blog->category->id,
                'name' => $blog->category->name,
                'slug' => $blog->category->slug,
                'description' => $blog->category->description,
            ] : null,
        ];
    }

     /**
     * @OA\Put(
     *     path="/api/frontend/blogs/{id}/comment",
     *     summary="Post a comment or reply on a blog",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"comment", "created_by"},
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="created_by", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comment added"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    // public function postComment(Request $request, $id) {
	// 	/* Validate incoming request data*/
	// 	$validator = Validator::make($request->all(), [
	// 		'comment' => 'required|string', /* Comment must be a required text*/
	// 		'parent_id' => 'nullable|integer', /* Parent ID can be null or an integer*/
	// 		'created_by' => 'required|integer', /* Created by must be a required integer*/
	// 	]);

	// 	if ($validator->fails()) {
	// 		/* Return validation errors as a response*/
	// 		return response()->json([
	// 			'status' => 'error',
	// 			'message' => $validator->errors()->first(),
	// 			'errors' => $validator->errors(),
	// 		], Response::HTTP_UNPROCESSABLE_ENTITY);
	// 	}

	// 	$post = Blog::findOrFail($id); /* Ensure the post exists*/

	// 	/* Add the comment*/
	// 	$comment = $post->comments()->create([
	// 		'comment' => $request->comment,
	// 		'parent_id' => $request->parent_id ?? null,
	// 		'created_by' => $request->created_by,
	// 	]);

	// 	return response()->json([
	// 		'status' => 'success',
	// 		'message' => $request->parent_id ? 'Replied successfully.' : 'Comment added successfully.',
	// 		'data' => [
	// 			'post' => $post,
	// 			'comments' => $post->comments,
	// 		],
	// 	], Response::HTTP_OK);
	// }
    public function postComment(Request $request, $id)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Please log in to comment.',
            ], \Illuminate\Http\Response::HTTP_UNAUTHORIZED);
        }

        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $post = Blog::findOrFail($id);

        $comment = $post->comments()->create([
            'comment' => $request->comment,
            'parent_id' => $request->parent_id ?? null,
            'created_by' => Auth::id()??1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $request->parent_id ? 'Replied successfully.' : 'Comment added successfully.',
            'data' => [
                'post' => $post,
                'comments' => $post->comments,
            ],
        ], Response::HTTP_OK);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/blogs/{postId}/comments",
     *     summary="Get all top-level comments with nested replies for a blog post",
     *     tags={"Frontend-Blogs"},
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         description="ID of the blog post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of comments retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Comments retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="post",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="My Blog Title")
     *                 ),
     *                 @OA\Property(
     *                     property="comments",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="comment", type="string", example="Great post!"),
     *                         @OA\Property(property="created_by", type="integer", example=5),
     *                         @OA\Property(
     *                             property="replies",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=11),
     *                                 @OA\Property(property="comment", type="string", example="Thanks!"),
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="creator",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=5),
     *                             @OA\Property(property="name", type="string", example="John Doe")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Post not found.")
     *         )
     *     )
     * )
     */
    public function viewComments($postId)
    {
        $post = Blog::with(['comments' => function ($query) {
            $query->whereNull('parent_id') // Only top-level comments
                ->with(['replies', 'creator']); // Nested replies and user info if needed
        }])->findOrFail($postId);

        return response()->json([
            'status' => 'success',
            'message' => 'Comments retrieved successfully.',
            'data' => [
                'post' => $post->only(['id', 'title']), // Optional: limit fields
                'comments' => $post->comments,
            ],
        ], \Illuminate\Http\Response::HTTP_OK);
    }



    public function categoryWiseBlogs()
    {
        $categories = BlogCategory::where('status', 'published')
            ->orderBy('order', 'asc')
            ->get(['id', 'name', 'slug', 'description']);

        $categoryIds = $categories->pluck('id');

        // Single query for all blogs, then group in PHP — eliminates N+1
        $allBlogs = Blog::where('status', 'published')
            ->whereIn('blog_category_id', $categoryIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('blog_category_id');

        $data = $categories->map(function ($category) use ($allBlogs) {
            $blogs = $allBlogs->get($category->id, collect())->map(function ($blog) {
                $description = $blog->description ?? [];
                return array_merge($blog->toArray(), [
                    'description' => isset($description[0]) ? [$description[0]] : [],
                ]);
            })->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'blogs' => $blogs,
            ];
        })->values();

        return response()->json($data);
    }


    public function blogsByCategorySlug(Request $request, $slug)
    {
        $page = $request->get('page', 1);

        $category = Cache::remember("blog_category_{$slug}", now()->addMinutes(60), function () use ($slug) {
            return BlogCategory::where('slug', $slug)
                ->where('status', 'published')
                ->first();
        });

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $result = Cache::remember("blogs_by_category_{$slug}_{$page}", now()->addMinutes(30), function () use ($category) {
            $blogs = Blog::where('blog_category_id', $category->id)
                ->where('status', 'published')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ],
                'blogs' => $blogs,
            ];
        });

        return response()->json($result);
    }

}
