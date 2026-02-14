<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\Chat;
use App\Models\FrontEnd\ChatbotContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatbotManagementController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/chatbot_contacts",
	 *     summary="Get all contacts with unread count",
	 *     tags={"Chatbot - Backend"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="control", in="query", description="Filter by control type (0=AI, 1=Human)", @OA\Schema(type="integer", enum={0, 1})),
	 *     @OA\Parameter(name="has_unread", in="query", description="Filter contacts with unread messages", @OA\Schema(type="boolean")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "email", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Contacts retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'email'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'updated_at';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = ChatbotContact::withCount(['chats as unread_count' => function($q) {
			$q->where('created_by_type', 'customer')
			->where('is_read', false);
		}])->with(['chats' => function($q) {
			$q->latest()->limit(1);
		}]);

		/* Filter by control type */
		if ($request->has('control')) {
			$recordsQuery->where('control', $request->control);
		}

		/* Filter by unread messages */
		if ($request->has('has_unread') && $request->has_unread) {
			$recordsQuery->has('chats', '>', 0)
			->whereHas('chats', function($q) {
				$q->where('created_by_type', 'customer')
				->where('is_read', false);
			});
		}

		/* Apply global or column-specific filters */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere($col, 'LIKE', '%' . $search . '%');
				}
			});
		} else {
			foreach ($searchableColumns as $col) {
				if ($request->filled($col)) {
					$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
				}
			}
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
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

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id',
				'name'
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
	 * @OA\Get(
	 *     path="/api/chatbot_contacts/{chatbot_contact_id}",
	 *     summary="Get all chats for a contact (admin view)",
	 *     tags={"Chatbot - Backend"},
	 *     @OA\Parameter(
	 *         name="chatbot_contact_id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Chats retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function show($chatbotContactID)
	{
		try {
			$contact = ChatbotContact::with(['chats' => function($q) {
				$q->orderBy('created_at', 'asc');
			}])->find($chatbotContactID);

			if (!$contact) {
				return response()->json([
					'success' => false,
					'message' => __("err_exist")
				]);
			}

			/* Mark customer messages as read */
			Chat::where('chatbot_contact_id', $chatbotContactID)
			->where('created_by_type', 'customer')
			->where('is_read', false)
			->update([
				'is_read' => true,
				'read_at' => now()
			]);

			return response()->json([
				'success' => true,
				'data' => $contact
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve chats',
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Put(
	 *     path="/api/chatbot_contacts/{chatbot_contact_id}",
	 *     summary="Switch between AI and Human control",
	 *     tags={"Chatbot - Backend"},
	 *     @OA\Parameter(
	 *         name="chatbot_contact_id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"control"},
	 *             @OA\Property(property="control", type="integer", enum={0, 1}, example=1, description="0 for AI, 1 for Human")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Control updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function update(Request $request, $contactId)
	{
		$validated = $request->validate([
			'control' => 'required|in:0,1'
		]);

		try {
			$contact = ChatbotContact::find($contactId);

			if (!$contact) {
				return response()->json([
					'success' => false,
					'message' => __("err_exist")
				]);
			}

			$contact->update(['control' => $validated['control']]);

			/* Create system message */
			$controlType = $validated['control'] == 1 ? 'human support' : 'AI assistant';
			Chat::create([
				'chatbot_contact_id' => $contactId,
				'message' => "Chat has been transferred to {$controlType}",
				'created_by' => Auth::id() ?? 0,
				'created_by_type' => 'user',
				'is_read' => false
			]);

			return response()->json([
				'success' => true,
				'message' => 'Control updated successfully',
				'data' => $contact
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update control',
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/chats",
	 *     summary="Create a chat message from support user",
	 *     tags={"Chatbot - Backend"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"chatbot_contact_id", "message"},
	 *             @OA\Property(property="chatbot_contact_id", type="integer", example=1),
	 *             @OA\Property(property="message", type="string", example="How can I help you?")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Chat created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function store(Request $request)
	{
		try {
			$validated = $request->validate([
				'chatbot_contact_id' => 'required|exists:chatbot_contacts,id',
				'message' => 'required|string|max:5000'
			]);

			$userId = Auth::id();

			/* Verify contact is in human mode (control = 1) */
			$contact = ChatbotContact::find($validated['chatbot_contact_id']);

			if ($contact && $contact->control == 0) {
				return response()->json([
					'success' => false,
					'message' => 'This contact is in AI mode. Please switch to human control first.'
				]);
			}

			$chat = Chat::create([
				'chatbot_contact_id' => $validated['chatbot_contact_id'],
				'message' => $validated['message'],
				'created_by' => $userId,
				'created_by_type' => 'user',
				'is_read' => false
			]);

			$chat->load('chatbotContact');

			/* Send to Pusher */
			callPusher($chat);

			return response()->json([
				'success' => true,
				'message' => 'Chat created successfully',
				'data' => $chat
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to create chat',
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/chatbot_stats",
	 *     summary="Get chatbot statistics",
	 *     tags={"Chatbot - Backend"},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Statistics retrieved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="data", type="object",
	 *                 @OA\Property(property="total_contacts", type="integer", example=150),
	 *                 @OA\Property(property="ai_controlled", type="integer", example=120),
	 *                 @OA\Property(property="human_controlled", type="integer", example=30),
	 *                 @OA\Property(property="unread_messages", type="integer", example=45)
	 *             )
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function stats()
	{
		try {
			$stats = [
				'total_contacts' => ChatbotContact::count(),
				'ai_controlled' => ChatbotContact::where('control', 0)->count(),
				'human_controlled' => ChatbotContact::where('control', 1)->count(),
				'unread_messages' => Chat::where('created_by_type', 'customer')
				->where('is_read', false)
				->count(),
				'total_chats_today' => Chat::whereDate('created_at', today())->count()
			];

			return response()->json([
				'success' => true,
				'data' => $stats
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve stats',
				'error' => $e->getMessage()
			]);
		}
	}
}