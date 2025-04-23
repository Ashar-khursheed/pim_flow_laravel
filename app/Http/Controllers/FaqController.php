<?php
namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/faqs",
     *     summary="Get a list of FAQs with search and filters",
     *     description="Retrieve FAQs with optional search, category, status, and pagination.",
     *     operationId="getFaqs",
     *     tags={"FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search for a keyword in question or answer",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter FAQs by category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter FAQs by status (published/draft)",
     *         @OA\Schema(type="string", enum={"published", "draft"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limit the number of FAQs returned",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="A list of FAQs with pagination",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Faq")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('list faq')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $query = Faq::with('category');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('question', 'LIKE', "%$search%")
                  ->orWhere('answer', 'LIKE', "%$search%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $limit = $request->input('limit', 10);
        $faqs = $query->paginate($limit);

        // Custom pagination response format
        $pagination = [
            'total' => $faqs->total(),
            'per_page' => $faqs->perPage(),
            'current_page' => $faqs->currentPage(),
            'last_page' => $faqs->lastPage(),
            'next_page_url' => $faqs->nextPageUrl(),
            'prev_page_url' => $faqs->previousPageUrl(),
        ];

        return response()->json([
            'data' => $faqs->items(),
            'pagination' => $pagination,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/faqs",
     *     summary="Create a new FAQ",
     *     description="Add a new FAQ with question, answer, category, and status.",
     *     operationId="createFaq",
     *     tags={"FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"question", "answer", "category_id", "status"},
     *             @OA\Property(property="question", type="string"),
     *             @OA\Property(property="answer", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="FAQ created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Faq")
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('add faq')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'category_id' => 'required|exists:faq_categories,id',
            'status' => 'required|string|in:published,draft',
        ]);

        $faq = Faq::create($request->all());

        return response()->json($faq, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/faqs/{id}",
     *     summary="Get a specific FAQ by ID",
     *     description="Retrieve a single FAQ by its ID.",
     *     operationId="getFaqById",
     *     tags={"FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the FAQ to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ details",
     *         @OA\JsonContent(ref="#/components/schemas/Faq")
     *     )
     * )
     */
    public function show(Faq $faq)
    {
        if (!auth()->user()->can('show faq')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        return response()->json($faq->load('category'));
    }

    /**
     * @OA\Put(
     *     path="/api/faqs/{id}",
     *     summary="Update an existing FAQ",
     *     description="Modify a FAQ entry.",
     *     operationId="updateFaq",
     *     tags={"FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ ID to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="question", type="string"),
     *             @OA\Property(property="answer", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Faq")
     *     )
     * )
     */
    public function update(Request $request, Faq $faq)
    {
        if (!auth()->user()->can('update faq')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
            'category_id' => 'sometimes|exists:faq_categories,id',
            'status' => 'sometimes|string|in:published,draft',
        ]);

        $faq->update($request->all());

        return response()->json($faq);
    }

    /**
     * @OA\Delete(
     *     path="/api/faqs/{id}",
     *     summary="Delete an FAQ",
     *     description="Remove a FAQ by ID.",
     *     operationId="deleteFaq",
     *     tags={"FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ ID to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ deleted successfully",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"))
     *     )
     * )
     */
    public function destroy(Faq $faq)
    {
        if (!auth()->user()->can('delete faq')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $faq->delete();
        return response()->json(['message' => 'FAQ deleted successfully']);
    }
}
