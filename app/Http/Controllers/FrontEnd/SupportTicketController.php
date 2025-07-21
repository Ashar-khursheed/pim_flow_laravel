<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
                $validated['file_path'] = $request->file('file')->store('support-tickets', env('STORAGE_ENV', 'public'));
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
     *     summary="List all tickets",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
public function index()
{
    try {
        $tickets = SupportTicket::with(['category:id,name', 'priority:id,name'])->get();

        $transformed = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
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
