<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\SupportTicket;

use App\Jobs\SupportTicket\SupportTicketMailJob;

class SupportTicketController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/support-tickets",
	 *     summary="Get all tickets with pagination and filters",
	 *     tags={"FrontEnd-SupportTickets"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "ticket_number", "category_name", "priority_name", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Records retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ["id", "ticket_number", "category_name", "priority_name"];
		$sortableColumns = array_merge($searchableColumns, ["created_at", "updated_at"]);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = SupportTicket::where('customer_id', auth()->id());

		/* join for customer name or email */
		if ($sortBy === 'category_name' || ($request->filled('global') && in_array('category_name', $searchableColumns))) {
			$recordsQuery->leftJoin('support_categories', 'support_tickets.category_id', '=', 'support_categories.id');
			$recordsQuery->addSelect('support_tickets.*');
		}

		if ($sortBy === 'priority_name' || ($request->filled('global') && in_array('priority_name', $searchableColumns))) {
			$recordsQuery->leftJoin('support_priorities', 'support_tickets.priority_id', '=', 'support_priorities.id');
			$recordsQuery->addSelect('support_tickets.*');
		}

		/* Eager load relationships */
		$recordsQuery->with([
			'category:id,name',
			'priority:id,name',
		]);

		/* Global search */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					if ($col === 'category_name') {
						$q->orWhereHas('category', function ($sub) use ($search) {
							$sub->where('name', 'like', '%' . $search . '%');
						});
					} elseif ($col === 'priority_name') {
						$q->orWhereHas('priority', function ($sub) use ($search) {
							$sub->where('name', 'like', '%' . $search . '%');
						});
					} else {
						$q->orWhere("support_tickets.$col", 'like', '%' . $search . '%');
					}
				}
			});
		}

		/* Sorting */
		if ($sortBy === 'category_name') {
			$recordsQuery->orderBy('support_categories.name', $sortDir);
		} elseif ($sortBy === 'priority_name') {
			$recordsQuery->orderBy('support_priorities.name', $sortDir);
		} else {
			$recordsQuery->orderBy("support_tickets.$sortBy", $sortDir);
		}

		if ($request->filled('page') && $request->filled('length')) {
			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery
			->offset(($page - 1) * $length)
			->limit($length)
			->get();
		} else {
			/* No pagination: just fetch id */
			$records = SupportTicket::orderBy('id', 'desc')->get();
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		/* Transform results */
		$records->transform(function ($record) {
			if ($record->category) {
				$record->category_name = $record->category->name ?? null;
				unset($record->category);
			}
			if ($record->priority) {
				$record->priority_name = $record->priority->name ?? null;
				unset($record->priority);
			}
			return $record;
		});

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/support-tickets",
	 *     summary="Create a new support ticket",
	 *     tags={"FrontEnd-SupportTickets"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"category_id", "priority_id", "subject", "description"},
	 *                 @OA\Property(property="category_id", type="integer"),
	 *                 @OA\Property(property="priority_id", type="integer"),
	 *                 @OA\Property(property="subject", type="string"),
	 *                 @OA\Property(property="description", type="string"),
	 *                 @OA\Property(property="reference", type="string"),
	 *                 @OA\Property(property="file", type="string", format="binary")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'category_id' => 'required|integer|exists:support_categories,id',
			'priority_id' => 'required|integer|exists:support_priorities,id',
			'subject' => 'required|string',
			'description' => 'required|string',
			'reference' => 'nullable|string',
			'file' => 'nullable|file|max:2048',
		]);

		DB::beginTransaction();

		try {
			/* Upload file if provided */
			$filePath = null;
			if ($request->hasFile('file')) {
				$filePath = uploadFileToS3(
					$request->file('file'),
					env('STORAGE_ENV') . '/support-tickets'
				);
			}

			/* Get the latest ticket by ID (most recent) */
			$latestTicket = SupportTicket::orderBy('ticket_number', 'desc')->first();

			if ($latestTicket && is_numeric($latestTicket->ticket_number)) {
				$ticketNumber = (int) $latestTicket->ticket_number + 1;
			} else {
				$website = config('app.website');
				$ticketNumber = $website === 'US' ? 10001 : ($website === 'UAE' ? 1001 : 101);
			}

			$ticket = SupportTicket::create([
				'ticket_number' => $ticketNumber,
				'customer_id' => auth()->id(),
				'category_id' => $request->category_id,
				'priority_id' => $request->priority_id,
				'subject' => $request->subject,
				'description' => $request->description,
				'reference' => $request->reference,
				'file_path' => $filePath,
				'status' => 'open',
				'response_days' => 7,
				'created_by' => 0,
			]);
			DB::commit();

			$batch = Bus::batch([])->name('support ticket from admin')->dispatch();
			$batch->options['queue'] = config('app.website') . '_SPRT_TKT';
			$batch->add(new SupportTicketMailJob([
				'recordId' => $ticket->id
			]));

			return response()->json([
				'success' => true,
				'data' => $ticket,
				'message' => 'Support ticket created successfully.'
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create ticket: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/support-tickets/{id}",
	 *     summary="Get support ticket details",
	 *     tags={"FrontEnd-SupportTickets"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Support Ticket ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$record = SupportTicket::where('id', $id)->where('customer_id', auth()->id())->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Ticket not found."
			]);
		}

		/* Load relationships */
		$record->load([
			'category:id,name',
			'priority:id,name',
		]);

		/* Mutate the data for each support ticket */
		if ($record->category) {
			$record->category_name = $record->category->name ?? null;
			unset($record->category);
		}
		if ($record->priority) {
			$record->priority_name = $record->priority->name ?? null;
			unset($record->priority);
		}

		return response()->json([
			'success' => true,
			'data' => $record
		]);
	}
}
