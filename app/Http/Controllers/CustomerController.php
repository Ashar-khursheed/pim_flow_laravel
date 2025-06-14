<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/customers",
	 *     summary="Get all customers with search, sort, and pagination",
	 *     tags={"Customers"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "email", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'email'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Customer::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['creator:id,first_name,last_name']);

			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
			}

			/* Apply sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id', 'name', 'email', 'dob', 'mobile_number', 'created_by', 'created_at', 'updated_at'
			]);

			$records->transform(function ($record) {
				$record->created_by = $record->creator->name ?? null;
				unset($record->creator);
				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/customers",
	 *     summary="Create a new customer",
	 *     tags={"Customers"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "email", "password"},
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 mb)"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Customer created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$validatedData = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:customers',
			'password' => 'required|string|min:8',
			'dob' => 'nullable|date',
			'mobile_number' => 'nullable|string|max:20|unique:customers',
			'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
		]);

		$validatedData['profile_img'] = uploadImageToWebpS3FromFile(
			$request,
			'profile_img',
			env('STORAGE_ENV') . '/customer/profile_img'
		);

		$customer = new Customer([
			'name' => $validatedData['name'],
			'email' => $validatedData['email'],
			'password' => Hash::make($validatedData['password']),
			'dob' => $validatedData['dob'] ?? null,
			'mobile_number' => $validatedData['mobile_number'] ?? null,
			'profile_img' => $validatedData['profile_img'] ?? null,
			'created_by' => auth()->id(),
		]);

		$customer->save();

		return response()->json([
			'success' => true,
			'message' => 'Customer created successfully!',
			'user' => $customer
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/customers/{id}",
	 *     summary="Get customer details",
	 *     description="Fetches customer details based on the given customer ID.",
	 *     tags={"Customers"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="ID of the customer", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$customer = Customer::with([
			'customerAddress',
			'customerAddress.country:id,name',
			'customerAddress.state:id,name',
			'customerAddress.city:id,name'
		])->find($id);

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $customer
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/customers/{id}",
	 *     summary="Update a customer",
	 *     tags={"Customers"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "email", "password"},
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 mb)"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$customer = Customer::find($id);

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		$validatedData = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:customers',
			'password' => 'required|string|min:8',
			'dob' => 'nullable|date',
			'mobile_number' => 'nullable|string|max:20|unique:customers',
			// 'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
		]);

		if (isset($validatedData['password'])) {
			$validatedData['password'] = Hash::make($validatedData['password']);
		} else {
			unset($validatedData['password']);
		}

		$customer->update($validatedData);

		return response()->json([
			'success' => true,
			'message' => 'Customer updated successfully',
			'data' => $customer
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/customers/{id}",
	 *     summary="Delete a customer",
	 *     tags={"Customers"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$customer = Customer::find($id);

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		$customer->delete();

		return response()->json([
			'success' => true,
			'message' => 'Customer deleted successfully',
		]);
	}
}
