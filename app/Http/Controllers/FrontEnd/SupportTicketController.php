<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="SupportTickets")
 */
class SupportTicketController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/support-tickets",
     *     tags={"SupportTickets"},
     *     summary="Create a support ticket",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"full_name", "email", "category_id", "priority_id", "subject", "description"},
     *                 @OA\Property(property="full_name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="company_name", type="string"),
     *                 @OA\Property(property="phone_number", type="string"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="priority_id", type="integer"),
     *                 @OA\Property(property="subject", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="reference_id", type="string"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ticket Created")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name'     => 'required|string|max:255',
                'email'         => 'required|email',
                'company_name'  => 'nullable|string',
                'phone_number'  => 'nullable|string|max:20',
                'category_id'   => 'required|integer',
                'priority_id'   => 'required|integer',
                'subject'       => 'required|string',
                'description'   => 'required|string',
                'reference_id'  => 'nullable|string',
                'file'          => 'nullable|file|max:2048',
                'customer_id'   => 'nullable|integer',
            ]);

            if ($request->hasFile('file')) {
              $path = $request->file('file')->store('support-tickets', 's3');
                $validated['file_path'] = $path;
                $validated['file_url'] = Storage::disk('s3')->url($path);

            }

            $ticket = SupportTicket::create($validated);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Support ticket created successfully.'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Support Ticket Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

/**
 * @OA\Get(
 *     path="/api/frontend/support-tickets",
 *     tags={"SupportTickets"},
 *     summary="List all support tickets with optional filters, search, and sorting",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search keyword for subject or description",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filter tickets by status (e.g., open, closed, pending)",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Field to sort by (created_at, updated_at, subject, status)",
 *         required=false,
 *         @OA\Schema(type="string", enum={"created_at", "updated_at", "subject", "status"})
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order (asc or desc)",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc", "desc"})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="subject", type="string", example="Login Issue"),
 *                     @OA\Property(property="status", type="string", example="open"),
 *                     @OA\Property(property="description", type="string", example="I can't log in to my account."),
 *                     @OA\Property(property="category", type="string", example="Technical"),
 *                     @OA\Property(property="priority", type="string", example="High"),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-07-21T15:03:00Z"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-07-22T09:15:00Z")
 *                 )
 *             ),
 *             @OA\Property(property="message", type="string", example="Support tickets fetched successfully.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal Server Error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Failed to fetch support tickets."),
 *             @OA\Property(property="error", type="string", example="Exception message here")
 *         )
 *     )
 * )
 */


public function index(Request $request)
{
    try {
        // 🔐 Get authenticated user
        $user = Auth::id();

        // 📄 Fetch tickets belonging only to the logged-in user
        $query = SupportTicket::with(['category:id,name', 'priority:id,name'])
            ->where('customer_id', $user); // or 'customer_id' if that's your field

        // 🔍 Search by subject or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // ✅ Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 🔃 Sorting logic
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['created_at', 'updated_at', 'subject', 'status'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $perPage = $request->get('per_page', 10);
        $tickets = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        $transformed = $tickets->getCollection()->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'description' => $ticket->description,
                'category' => $ticket->category ? $ticket->category->name : null,
                'priority' => $ticket->priority ? $ticket->priority->name : null,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
            'message' => 'Support tickets fetched successfully.'
        ], 200);

    } catch (\Exception $e) {
        Log::error('SupportTicketController@index error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch support tickets.',
            'error' => $e->getMessage()
        ], 500);
    }
}



    /**
     * @OA\Get(
     *     path="/api/frontend/support-tickets/{id}",
     *     tags={"SupportTickets"},
     *     summary="Get a ticket by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Ticket Found"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
   public function show($id)
{
    try {
        $ticket = SupportTicket::with(['category', 'priority'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket,
            'message' => 'Support ticket found.'
        ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Support ticket not found.'
        ], 404);
    } catch (\Exception $e) {
        Log::error('SupportTicketController@show error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch ticket.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * @OA\Get(
     *     path="/api/frontend/customers/{customer_id}/support-tickets",
     *     tags={"SupportTickets"},
     *     summary="Get tickets for a specific customer",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="customer_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tickets found"),
     *     @OA\Response(response=404, description="No tickets found")
     * )
     */
   public function getTicketsByCustomer($customer_id)
{
    try {
        $tickets = SupportTicket::with(['category', 'priority'])
            ->where('customer_id', $customer_id)
            ->get();

        if ($tickets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No tickets found for this customer.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tickets,
            'message' => 'Support tickets found.'
        ], 200);
    } catch (\Exception $e) {
        Log::error('SupportTicketController@getTicketsByCustomer error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch customer tickets.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
