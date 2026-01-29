<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class CustomerController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/customers",
	 *     summary="Get all customers with search, sort, and pagination",
	 *     tags={"Customers"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "email", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';

			$records = Customer::whereBetween('created_at', [$from, $to])->pluck('id');
			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $records,
			]);
		}
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
				'id', 'name', 'email', 'type', 'dob', 'country_code', 'mobile_number', 'profile_img', 'created_by', 'created_at', 'updated_at'
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
	 *                 @OA\Property(property="business_name", type="string", example="Acme Corporation"),
	 *                 @OA\Property(property="business_licence", type="file", description="Business licence PDF (max 2MB)"),
	 *                 @OA\Property(property="trn_number", type="string", example="1234567890"),
	 *                 @OA\Property(property="vat_certificate", type="file", description="VAT certificate PDF (max 2MB)"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="type", type="string", example="Business"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="country_code", type="string", example="+91"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 MB)"),
	 *                 @OA\Property(property="is_tax_free", type="boolean", example=false),
	 *                 @OA\Property(property="approval_action_notes", type="string", example="Customer provided valid exemption certificate.")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Customer created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$request->merge([
			'is_tax_free' => filter_var($request->input('is_tax_free'), FILTER_VALIDATE_BOOLEAN)
		]);
		$validatedData = $request->validate([
			'name' => 'required|string|max:255',

			'business_name'    => 'nullable|string',
			'business_licence' => 'nullable|file|mimes:pdf|max:2048',
			'trn_number'       => 'nullable|string',
			'vat_certificate'  => 'nullable|file|mimes:pdf|max:2048',

			'email' => 'required|string|email:strict|max:255|unique:customers,email',
			'password' => 'required|string|min:8',
			'type' => 'nullable|string',
			'dob' => 'nullable|date',
			'country_code' => 'nullable|string|max:10',
			'mobile_number' => 'nullable|string|max:20',
			'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
			'is_tax_free' => 'nullable|boolean',
			'approval_action_notes' => 'required_if:is_tax_free,1|string|nullable',

		]);

		$validatedData['profile_img'] = uploadImageToWebpS3FromFile(
			$request,
			'profile_img',
			env('STORAGE_ENV') . '/customer/profile_img'
		);

		/* Business licence PDF */
		if ($request->hasFile('business_licence')) {
			$validatedData['business_licence'] = uploadPdfToS3FromFile(
				$request,
				'business_licence',
				env('STORAGE_ENV') . '/customer/business_licence'
			);
		}

		/* VAT certificate PDF */
		if ($request->hasFile('vat_certificate')) {
			$validatedData['vat_certificate'] = uploadPdfToS3FromFile(
				$request,
				'vat_certificate',
				env('STORAGE_ENV') . '/customer/vat_certificate'
			);
		}

		$isTaxFree = $request->boolean('is_tax_free');

		$customer = new Customer([
			'name' => $validatedData['name'],
			'business_name' => $validatedData['business_name'] ?? null,
			'business_licence' => $validatedData['business_licence'] ?? null,
			'trn_number' => $validatedData['trn_number'] ?? null,
			'vat_certificate' => $validatedData['vat_certificate'] ?? null,
			'email' => $validatedData['email'],
			'password' => Hash::make($validatedData['password']),
			'type' => $validatedData['type'] ?? null,
			'dob' => $validatedData['dob'] ?? null,
			'country_code' => $validatedData['country_code'] ?? null,
			'mobile_number' => $validatedData['mobile_number'] ?? null,
			'profile_img' => $validatedData['profile_img'] ?? null,
			'is_tax_free' => $isTaxFree,
			'approval_action_notes' => $isTaxFree ? ($validatedData['approval_action_notes'] ?? null) : null,
			'approval_action_by' => $isTaxFree ? auth()->id() : null,
			'approval_action_at' => $isTaxFree ? now() : null,
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
			'customerAddress'
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
	 * @OA\Post(
	 *     path="/api/customers/{id}",
	 *     summary="Update a customer",
	 *     tags={"Customers"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"_method", "name", "email"},
	 *                 @OA\Property(property="_method", type="string", example="PUT"),
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="business_name", type="string", example="Acme Corporation"),
	 *                 @OA\Property(property="business_licence", type="file", description="Business licence PDF (max 2MB)"),
	 *                 @OA\Property(property="trn_number", type="string", example="1234567890"),
	 *                 @OA\Property(property="vat_certificate", type="file", description="VAT certificate PDF (max 2MB)"),
	 *
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="type", type="string", example="Business"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="country_code", type="string", example="+91"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img_url", type="string", example="https://example.com/image.png"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 MB)"),
	 *                 @OA\Property(property="is_tax_free", type="boolean", example=false),
	 *                 @OA\Property(property="approval_action_notes", type="string", example="Customer provided valid exemption certificate.")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")
	 *     ),
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
			'name'             => 'required|string|max:255',

			'business_name'    => 'nullable|string',
			'business_licence' => 'nullable|file|mimes:pdf|max:2048',
			'trn_number'       => 'nullable|string',
			'vat_certificate'  => 'nullable|file|mimes:pdf|max:2048',

			'email'            => 'string|email:strict|max:255|unique:customers,email,' . $id,
			'password'         => 'nullable|string|min:8',
			'type'             => 'nullable|string',
			'dob'              => 'nullable|date',
			'country_code'     => 'nullable|string|max:10',
			'mobile_number'    => 'nullable|string|max:20',
			'profile_img_url'  => 'nullable',
			'profile_img'      => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',

			'is_tax_free' => 'nullable|boolean',
			'approval_action_notes' => 'required_with:is_tax_free|string|nullable',
		]);

		/* Profile Image */
		if ($request->hasFile('profile_img')) {
			$validatedData['profile_img'] = uploadImageToWebpS3FromFile(
				$request,
				'profile_img',
				env('STORAGE_ENV') . '/customer/profile_img'
			);
		} elseif (!empty($validatedData['profile_img_url'])) {
			$validatedData['profile_img'] = $validatedData['profile_img_url'];
		} else {
			unset($validatedData['profile_img']);
		}

		/* Business licence PDF */
		if ($request->hasFile('business_licence')) {
			$validatedData['business_licence'] = uploadPdfToS3FromFile(
				$request,
				'business_licence',
				env('STORAGE_ENV') . '/customer/business_licence'
			);
		}

		/* VAT certificate PDF */
		if ($request->hasFile('vat_certificate')) {
			$validatedData['vat_certificate'] = uploadPdfToS3FromFile(
				$request,
				'vat_certificate',
				env('STORAGE_ENV') . '/customer/vat_certificate'
			);
		}

		/* Password */
		if (isset($validatedData['password'])) {
			$validatedData['password'] = Hash::make($validatedData['password']);
		} else {
			unset($validatedData['password']);
		}

		if (!is_null($request->is_tax_free)) {
			$isTaxFree = $request->boolean('is_tax_free');

			$validatedData['is_tax_free'] = $isTaxFree;
			$validatedData['approval_action_notes'] = $validatedData['approval_action_notes'];
			$validatedData['approval_action_by'] = auth()->id();
			$validatedData['approval_action_at'] = now();
		} else {
			unset(
				$validatedData['is_tax_free'],
				$validatedData['approval_action_notes']
			);
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

	/**
	 * @OA\Get(
	 *     path="/api/customers/filter-by-date",
	 *     summary="Filter customers by created_at or updated_at date range",
	 *     tags={"Customers"},
	 *     security={{"bearerAuth":{}}},
	 *     operationId="filterCustomersByDate",
	 *     @OA\Parameter(
	 *         name="date_type",
	 *         in="query",
	 *         required=true,
	 *         description="Field to filter on: created_at or updated_at",
	 *         @OA\Schema(type="string", enum={"created_at", "updated_at"})
	 *     ),
	 *     @OA\Parameter(
	 *         name="start_date",
	 *         in="query",
	 *         required=true,
	 *         description="Start date in YYYY-MM-DD format",
	 *         @OA\Schema(type="string", format="date")
	 *     ),
	 *     @OA\Parameter(
	 *         name="end_date",
	 *         in="query",
	 *         required=true,
	 *         description="End date in YYYY-MM-DD format",
	 *         @OA\Schema(type="string", format="date")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Filtered customer IDs",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Customer IDs filtered by date range."),
	 *             @OA\Property(property="data", type="array", @OA\Items(type="integer"))
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error"
	 *     )
	 * )
	 */
	public function filterByDate(Request $request)
	{
		// Add this at the very beginning to confirm the method is being called

		try {
			$request->validate([
				'date_type' => 'required|in:created_at,updated_at',
				'start_date' => 'required|date',
				'end_date' => 'required|date|after_or_equal:start_date',
			]);

			$dateType = $request->input('date_type');
			$startDate = $request->input('start_date');
			$endDate = $request->input('end_date');


			// First, let's check if there are ANY customers in the database
			$totalCustomers = Customer::count();

			// Check a few sample customers and their dates
			$sampleCustomers = Customer::take(5)->get(['id', 'created_at', 'updated_at']);

			// Build the query step by step for debugging
			$query = Customer::query();

			// Debug the raw SQL query
			$sqlQuery = Customer::whereBetween($dateType, [
				Carbon::parse($startDate)->startOfDay(),
				Carbon::parse($endDate)->endOfDay()
			])->toSql();

			$bindings = Customer::whereBetween($dateType, [
				Carbon::parse($startDate)->startOfDay(),
				Carbon::parse($endDate)->endOfDay()
			])->getBindings();



			// Execute the query
			$customers = Customer::whereBetween($dateType, [
				Carbon::parse($startDate)->startOfDay(),
				Carbon::parse($endDate)->endOfDay()
			])->get();


			// Return proper response
			return response()->json([
				'success' => true,
				'message' => 'Customer IDs filtered by date range.',
				'data' => $customers->pluck('id')->toArray(),
				'total' => $customers->count(),
				'debug' => [
					'total_customers_in_db' => $totalCustomers,
					'sql_query' => $sqlQuery,
					'bindings' => $bindings
				]
			]);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'An error occurred: ' . $e->getMessage(),
				'error' => $e->getMessage()
			], 500);
		}
	}
}
