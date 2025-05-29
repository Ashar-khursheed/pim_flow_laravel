<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;


class CustomerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/customers",
     *     summary="Get all customers with search, sort, and pagination",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Column to sort by (e.g., name, email, created_at)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(response=200, description="Paginated list of customers")
     * )
    */
        // public function index(Request $request)
        // {
        //     $query = Customer::query();

        //     // Search by name or email
        //     if ($request->filled('search')) {
        //         $search = $request->input('search');
        //         $query->where(function ($q) use ($search) {
        //             $q->where('name', 'like', "%$search%")
        //             ->orWhere('email', 'like', "%$search%");
        //         });
        //     }

        //     // Sorting
        //     $sortBy = $request->input('sort_by', 'created_at');
        //     $sortOrder = $request->input('sort_order', 'desc');
        //     $query->orderBy($sortBy, $sortOrder);

        //     // Pagination
        //     $perPage = $request->input('per_page', 10);
        //     $customers = $query->paginate($perPage);

        //     return response()->json($customers);
        // }

        public function index(Request $request)
        {
            $query = Customer::query();

            // Search by name or email
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
                });
            }

            // Valid sort columns
            $allowedSortColumns = ['id', 'name', 'email', 'created_at', 'updated_at'];
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Validate sortBy
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'created_at'; // fallback to default
            }

            // Validate sortOrder
            $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 10);
            $customers = $query->paginate($perPage);

            // Optional: Handle no results found
            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No customers found.',
                    'data' => [],
                ], 200);
            }

            return response()->json($customers);
        }


    /**
     * @OA\Post(
     *     path="/api/customers",
     *     summary="Create a new customer",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="avatar", type="string"),
     *             @OA\Property(property="dob", type="string", format="date"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="is_vendor", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Customer created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:ec_customers,email',
            'password' => 'required|string|min:6',
            'avatar'   => 'nullable|string',
            'dob'      => 'nullable|date',
            'phone'    => 'nullable|string|max:20',
            'status'   => 'nullable|string|max:50',
            'is_vendor' => 'nullable|boolean',
        ]);

        $data['password'] = Hash::make($data['password']);

        $customer = Customer::create($data);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     summary="Get a specific customer by ID",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Customer found"),
     *     @OA\Response(response=404, description="Customer not found")
     * )
     */
    public function show($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json($customer);
    }

    /**
     * @OA\Put(
     *     path="/api/customers/{id}",
     *     summary="Update a customer",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="avatar", type="string"),
     *             @OA\Property(property="dob", type="string", format="date"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="is_vendor", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Customer updated"),
     *     @OA\Response(response=404, description="Customer not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $data = $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('ec_customers')->ignore($customer->id)],
            'password' => 'sometimes|nullable|string|min:6',
            'avatar'   => 'nullable|string',
            'dob'      => 'nullable|date',
            'phone'    => 'nullable|string|max:20',
            'status'   => 'nullable|string|max:50',
            'is_vendor' => 'nullable|boolean',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $customer->update($data);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     summary="Delete a customer",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Customer deleted"),
     *     @OA\Response(response=404, description="Customer not found")
     * )
     */
    public function destroy($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/customers/list-names",
     *     summary="Get list of customer IDs and names",
     *     tags={"Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of customer IDs and names",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe")
     *             )
     *         )
     *     )
     * )
     */
    public function listNames()
    {
        $customers = Customer::select('id', 'name')->get();
        return response()->json($customers);
    }

}
