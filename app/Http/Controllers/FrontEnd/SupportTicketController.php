<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            $validated['file_path'] = $request->file('file')->store('support-tickets', env('STORAGE_ENV', 'production'));
        }

        $ticket = SupportTicket::create($validated);

        return response()->json($ticket, 201);
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
        return response()->json(SupportTicket::all());
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/support-tickets/{id}",
     *     tags={"SupportTickets"},
     *     summary="Get a ticket by ID",
     *     @OA\Parameter(
     *         name="id", in="path", required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Ticket Found"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        return response()->json(SupportTicket::findOrFail($id));
    }
    
    /**
     * @OA\Get(
     *     path="/api/frontend/customers/{customer_id}/support-tickets",
     *     tags={"SupportTickets"},
     *     summary="Get tickets for a specific customer",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Tickets found"),
     *     @OA\Response(response=404, description="No tickets found")
     * )
     */
    public function getTicketsByCustomer($customer_id)
    {
        $tickets = SupportTicket::where('customer_id', $customer_id)->get();

        if ($tickets->isEmpty()) {
            return response()->json(['message' => 'No tickets found'], 404);
        }

        return response()->json($tickets, 200);
    }

}
