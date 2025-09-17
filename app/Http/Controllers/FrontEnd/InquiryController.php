<?php
namespace App\Http\Controllers\FrontEnd;


use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class InquiryController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/frontend/inquiries",
 *     tags={"Inquiries"},
 *     summary="List inquiries with search, sorting, and pagination",
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search by full name, phone, email, or company name",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Column to sort by (id, full_name, email, created_at, etc.)",
 *         required=false,
 *         @OA\Schema(type="string", example="created_at")
 *     ),
 *     @OA\Parameter(
 *         name="sort_dir",
 *         in="query",
 *         description="Sort direction (asc or desc)",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc","desc"}, example="desc")
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of records per page",
 *         required=false,
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Paginated inquiry list"
 *     )
 * )
 */
public function index(Request $request): JsonResponse
{
    $query = Inquiry::query();

    // 🔍 Search
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('full_name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company_name', 'like', "%{$search}%");
        });
    }

    // ↕️ Sorting
    $sortBy = $request->get('sort_by', 'created_at');
    $sortDir = $request->get('sort_dir', 'desc');

    if (!in_array(strtolower($sortDir), ['asc', 'desc'])) {
        $sortDir = 'desc';
    }

    // prevent SQL injection by only allowing known columns
    $allowedSorts = ['id', 'full_name', 'email', 'phone', 'company_name', 'created_at'];
    if (!in_array($sortBy, $allowedSorts)) {
        $sortBy = 'created_at';
    }

    $query->orderBy($sortBy, $sortDir);

    // 📄 Pagination
    $perPage = (int) $request->get('per_page', 10);
    $perPage = $perPage > 0 ? $perPage : 10;

    $inquiries = $query->paginate($perPage);

    return response()->json($inquiries);
}

    /**
     * @OA\Post(
     *     path="/api/frontend/inquiries",
     *     tags={"Inquiries"},
     *     summary="Submit a new inquiry",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"full_name","phone","email","company_name","restaurant_type"},
     *                 @OA\Property(property="full_name", type="string", example="Jhon Smith"),
     *                 @OA\Property(property="phone", type="string", example="+1 (234) 567-8900"),
     *                 @OA\Property(property="email", type="string", example="you@example.com"),
     *                 @OA\Property(property="company_name", type="string", example="Bella’s Italian Bistro"),
     *                 @OA\Property(property="restaurant_type", type="string", example="Italian"),
     *                 @OA\Property(
     *                     property="files[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(property="notes", type="string", example="Attach menu if available")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Inquiry created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'company_name' => 'required|string|max:255',
            'restaurant_type' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $storedFiles = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->storePublicly(
                    "inquiries",
                    'public'
                );
                $storedFiles[] = Storage::disk('public')->url($path);
            }
        }

        $inquiry = Inquiry::create([
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'],
            'restaurant_type' => $validated['restaurant_type'],
            'notes' => $validated['notes'] ?? null,
            'files' => $storedFiles,
        ]);

        return response()->json($inquiry, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/inquiries/{id}",
     *     tags={"Inquiries"},
     *     summary="Get single inquiry",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Inquiry found"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id): JsonResponse
    {
        $inquiry = Inquiry::find($id);

        if (! $inquiry) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($inquiry);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/inquiries/{id}",
     *     tags={"Inquiries"},
     *     summary="Delete an inquiry",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id): JsonResponse
    {
        $inquiry = Inquiry::find($id);

        if (! $inquiry) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Delete stored files
        if (is_array($inquiry->files)) {
            foreach ($inquiry->files as $file) {
                $relativePath = str_replace(Storage::disk('public')->url(''), '', $file);
                Storage::disk('public')->delete($relativePath);
            }
        }

        $inquiry->delete();

        return response()->json(null, 204);
    }
}
