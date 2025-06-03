<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/blogs",
     *     tags={"Frontend-Blogs"},
     *     summary="Get all blogs with pagination and search",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by blog name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
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
     *         description="Paginated list of blogs"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Blog::where('status', 'published')
            ->with('category')
            ->orderByDesc('created_at');
    
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
    
        $perPage = $request->get('per_page', 10);
    
        $blogs = $query->paginate($perPage);
    
        return response()->json([
            'success' => true,
            'message' => 'Blogs fetched successfully',
            'data' => $blogs,
        ]);
    }
    

    /**
     * @OA\Get(
     *     path="/api/frontend/blogs/{slug}",
     *     tags={"Frontend-Blogs"},
     *     summary="Get single blog",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog data"
     *     )
     * )
     */
    public function show($slug)
    {
        $blog = Blog::where('slug', $slug)->with('category')->firstOrFail();
        return response()->json($blog);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/view",
     *     tags={"Frontend-Blogs"},
     *     summary="Increment blog view count",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="View incremented"
     *     )
     * )
     */
    public function incrementView($id)
    {
        Blog::where('id', $id)->increment('total_views');
        return response()->json(['message' => 'View count updated']);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/like",
     *     tags={"Frontend-Blogs"},
     *     summary="Increment blog like count",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Like incremented"
     *     )
     * )
     */
    public function incrementLike($id)
    {
        Blog::where('id', $id)->increment('total_likes');
        return response()->json(['message' => 'Like count updated']);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/blogs/{id}/share",
     *     tags={"Frontend-Blogs"},
     *     summary="Increment blog share count",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Share incremented"
     *     )
     * )
     */
    public function incrementShare($id)
    {
        Blog::where('id', $id)->increment('total_shares');
        return response()->json(['message' => 'Share count updated']);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/blogs/latest",
     *     tags={"Frontend-Blogs"},
     *     summary="Get latest 5 published blogs",
     *     @OA\Response(
     *         response=200,
     *         description="Latest blogs retrieved successfully"
     *     )
     * )
     */
    public function latest()
    {
        $latestBlogs = Blog::where('status', 'published')
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Latest blogs fetched successfully',
            'data' => $latestBlogs,
        ]);
    }

}
