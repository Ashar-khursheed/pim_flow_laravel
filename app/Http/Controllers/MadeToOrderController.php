<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MadeToOrder;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
class MadeToOrderController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/made-to-orders",
     *     summary="Get all made-to-orders with pagination and filters",
     *     description="Retrieve a paginated list of made-to-orders with optional search and sorting parameters.",
     *     tags={"Made to Orders"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         example=1,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="length",
     *         in="query",
     *         description="Number of records per page",
     *         required=false,
     *         example=20,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="global",
     *         in="query",
     *         description="Global search term (name, email, city, state, country, phone_number)",
     *         required=false,
     *         @OA\Schema(type="string", example="John")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Column name to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"id", "name", "email"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_dir",
     *         in="query",
     *         description="Sort direction (asc or desc)",
     *         required=false,
     *         example="asc",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="from", type="integer", example=1),
     *             @OA\Property(property="to", type="integer", example=20),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=20),
     *             @OA\Property(property="total", type="integer", example=100)     *
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $length = $request->query('length', 20);
        $global = $request->query('global', null);
        $sortBy = $request->query('sort_by', 'id');
        $sortDir = $request->query('sort_dir', 'asc');

        // Validate sort direction
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        // Allowed columns for sorting
        $allowedSortColumns = ['id', 'name', 'email', 'city', 'country', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'id';
        }

        // Build query
        $query = MadeToOrder::with('product');

        // Apply global search
        if ($global) {
            $query->where(function ($q) use ($global) {
                $q->where('name', 'like', '%' . $global . '%')
                    ->orWhere('email', 'like', '%' . $global . '%')
                    ->orWhere('city', 'like', '%' . $global . '%')
                    ->orWhere('state', 'like', '%' . $global . '%')
                    ->orWhere('country', 'like', '%' . $global . '%')
                    ->orWhere('phone_number', 'like', '%' . $global . '%');
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDir);

        // Apply pagination
        $orders = $query->paginate($length, ['*'], 'page', $page);

        // ✅ Fix: Convert paginator items to collection before mapping
        $records = $orders->getCollection()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'product_id' => $payment->product_id,
                'quantity' => $payment->quantity,
                'name' => $payment->name,
                'email' => $payment->email ?? null,
                'address' => $payment->address ?? null,
                'city' => $payment->city ?? null,
                'state' => $payment->state ?? null,
                'country' => $payment->country ?? null,
                'zipcode' => $payment->zipcode ?? null,
                'phone_number' => $payment->phone_number ?? null,
                'notes' => $payment->notes,
                'created_at' => date('d-m-Y', strtotime($payment->created_at)),
                'updated_at' => date('d-m-Y', strtotime($payment->updated_at)),
            ];
        });

        // ✅ Fix: Update paginator collection after transformation
        $orders->setCollection($records);

        // ✅ Define total pages and total records
        $totalRecords = $orders->total();
        $totalPages = $orders->lastPage();

        // Return response
        return response()->json([
            'success' => true,
            'message' => __('msg_rec_list'),
            'data' => $orders->items(),
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
        ]);

    }

    /**
     * @OA\Post(
     *     path="/api/made-to-orders",
     *     summary="Create a new made-to-order request",
     *     description="This API allows users to create a new made-to-order request with customer and product details.",
     *     tags={"Made to Orders"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Made to Order form data",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"product_id", "quantity", "name", "email","phone_number","notes"},
     *
     *                 @OA\Property(property="product_id", type="integer", example=1795, description="ID of the product to order"),
     *                 @OA\Property(property="quantity", type="integer", example=2, description="Quantity of the product"),
     *                 @OA\Property(property="name", type="string", example="John Doe", description="Customer full name"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com", description="Customer email address"),
     *                 @OA\Property(property="address", type="string", example="123 Main Street, Connaught Place", description="Shipping address"),
     *                 @OA\Property(property="city", type="string", example="New Delhi", description="City name"),
     *                 @OA\Property(property="state", type="string", example="Delhi", description="State name"),
     *                 @OA\Property(property="country", type="string", example="India", description="Country name"),
     *                 @OA\Property(property="zipcode", type="string", example="110001", description="Postal or ZIP code"),
     *                 @OA\Property(property="phone_number", type="string", example="9876543210", description="Customer contact number"),
     *                 @OA\Property(property="notes", type="string", example="Need delivery before 25th December", description="Optional order notes"),
     *
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Made to Order request created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     )
     * )
     */
    public function store(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:ec_products,id',
                'quantity' => 'required|integer|min:1',
                'name' => 'required|string|max:255',
                'email' => 'required|email:strict',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'zipcode' => 'nullable|string|max:20',
                'phone_number' => 'required|string|regex:/^[0-9\-\+\(\)\s]+$/',
                'notes' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $order = MadeToOrder::create($data);

            if ($order) {
                $originalValues = [];
                $newValues = $order->getAttributes();
                $changes = [];
                foreach ($newValues as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $originalValues[$field] ?? null,
                        'new' => $newValue,
                    ];
                }
                $versionData = [
                    'version_id' => $order->id,
                    'created_by' => Auth::id() ?? 1,
                    'module' => 'MadeToOrder',
                    'action' => 'Create',
                    'description' => json_encode($changes),
                ];

                app(\App\Services\VersionService::class)
                    ->createVersion($versionData);
            }
           return response()->json([
                'success' => true,
                'message' => 'Made to Order successfully.',
                'data' => $order
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong while creating the payment.',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * @OA\Get(
     *     path="/api/made-to-orders/{id}",
     *     summary="Get a specific made-to-order",
     *     description="Retrieve details of a single made-to-order by its ID.",
     *     tags={"Made to Orders"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Made-to-order ID",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Made-to-order retrieved successfully",
     *
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Made-to-order not found"
     *     )
     * )
     */
    public function show($id)
    {
        $order = MadeToOrder::with('product')->find($id);

        $madeToOrder = [
            'id' => $order->id,
            'product_id' => $order->product_id,
            'quantity' => $order->quantity,
            'name' => $order->name,
            'email' => $order->email ?? null,
            'address' => $order->address ?? null,
            'city' => $order->city ?? null,
            'state' => $order->state ?? null,
            'country' => $order->country ?? null,
            'zipcode' => $order->zipcode ?? null,
            'phone_number' => $order->phone_number ?? null,
            'notes' => $order->notes,
            'created_at' => date('d-m-Y', strtotime($order->created_at)),
            'updated_at' => date('d-m-Y', strtotime($order->updated_at)),
        ];

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Made to Order successfully',
            'data' => $madeToOrder
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/made-to-orders/{id}",
     *     summary="Update an existing made-to-order",
     *     description="Update details of an existing made-to-order by its ID.",
     *     tags={"Made to Orders"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the made-to-order record to update",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated made-to-order data",
     *         @OA\JsonContent(
     *             required={"product_id", "quantity", "name", "email", "phone_number","notes"},
     *             @OA\Property(property="product_id", type="integer", example=1795),
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="address", type="string", example="123 Street Name"),
     *             @OA\Property(property="city", type="string", example="New Delhi"),
     *             @OA\Property(property="state", type="string", example="Delhi"),
     *             @OA\Property(property="country", type="string", example="India"),
     *             @OA\Property(property="zipcode", type="string", example="110001"),
     *             @OA\Property(property="phone_number", type="string", example="9876543210"),
     *             @OA\Property(property="notes", type="string", example="Urgent delivery requested")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Made-to-order updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Made-to-order not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $order = MadeToOrder::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:ec_products,id',
            'quantity' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'email' => 'required|email:strict',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zipcode' => 'nullable|string|max:20',
            'phone_number' => 'required|string|regex:/^[0-9\-\+\(\)\s]+$/',
            'notes' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $order->fill($data);

        if ($order->isDirty()) {
            $originalValues = $order->getOriginal();
            $changes = [];
            foreach ($order->getDirty() as $field => $newValue) {
                $changes[$field] = [
                    'old' => $originalValues[$field] ?? null,
                    'new' => $newValue,
                ];
            }
            $order->save();
            $versionData = [
                'version_id' => $order->id,
                'updated_by' => Auth::id() ?? 1,
                'module' => 'MadeToOrder',
                'action' => 'Update',
                'description' => json_encode($changes),
            ];

            app(\App\Services\VersionService::class)
                ->createVersion($versionData);
        }
        return response()->json([
            'success' => true,
            'message' => 'Made to Order updated successfully',
            'data' => $order
        ], 200);

    }

    /**
     * @OA\Delete(
     *     path="/api/made-to-orders/{id}",
     *     tags={"Made to Orders"},
     *     summary="Delete a made-to-order",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found"),
     * security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        $order = MadeToOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->delete();

         if ($order) {
                $originalValues = [];
                $newValues = $order->getAttributes();
                $changes = [];
                foreach ($newValues as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $originalValues[$field] ?? null,
                        'new' => $newValue,
                    ];
                }
                $versionData = [
                    'version_id' => $order->id,
                    'created_by' => Auth::id() ?? 1,
                    'module' => 'MadeToOrder',
                    'action' => 'Delete',
                    'description' => json_encode($changes),
                ];

                app(\App\Services\VersionService::class)
                    ->createVersion($versionData);
            }

        return response()->json(['message' => 'Made Order deleted successfully'], 200);
    }
}
