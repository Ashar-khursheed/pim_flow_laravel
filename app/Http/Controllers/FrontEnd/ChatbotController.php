<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\Chat;
use App\Models\FrontEnd\ChatbotContact;
use Illuminate\Http\Request;

class ChatbotController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/chatbot_contacts/find-or-create",
	 *     summary="Find existing contact by email or create new one",
	 *     tags={"Chatbot - Frontend"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "email"},
	 *             @OA\Property(property="name", type="string", example="Noman Peera"),
	 *             @OA\Property(property="email", type="string", format="email", example="noman@example.com"),
	 *             @OA\Property(property="phone_number", type="string", example="+971123456789")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Contact found or created successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function findOrCreateContact(Request $request)
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|email|max:255',
			'phone_number' => 'nullable|string|max:20'
		]);

		try {
			/* Find or create contact */
			$contact = ChatbotContact::where('email', $validated['email'])->first();

			if ($contact) {
				/* Update existing contact info if needed */
				$contact->update([
					'name' => $validated['name'],
					'phone_number' => $validated['phone_number'] ?? $contact->phone_number,
					'control' => 0 /* Always reset to AI mode when customer returns */
				]);

				return response()->json([
					'success' => true,
					'message' => 'Contact already exists',
					'data' => $contact->load('chats'),
					'created' => false
				]);
			}

			/* Create new contact (AI mode by default) */
			$contact = ChatbotContact::create([
				'name' => $validated['name'],
				'email' => $validated['email'],
				'phone_number' => $validated['phone_number'],
				'control' => 0 /* AI by default */
			]);

			return response()->json([
				'success' => true,
				'message' => 'Contact created successfully',
				'data' => $contact->load('chats'),
				'created' => true
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to process contact',
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/chats",
	 *     summary="Create a new chat message from customer or AI",
	 *     tags={"Chatbot - Frontend"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"chatbot_contact_id", "message", "created_by_type"},
	 *             @OA\Property(property="chatbot_contact_id", type="integer", example=1),
	 *             @OA\Property(property="message", type="string", example="Hello, I need help"),
	 *             @OA\Property(property="created_by_type", type="string", enum={"customer", "AI"}, example="customer")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'chatbot_contact_id' => 'required|exists:chatbot_contacts,id',
			'message' => 'required|string|max:5000',
			'created_by_type' => 'required|in:customer,AI'
		]);

		try {
			/* Verify contact is in AI mode (control = 0) when AI tries to send message */
			if ($validated['created_by_type'] === 'AI') {
				$contact = ChatbotContact::find($validated['chatbot_contact_id']);

				if ($contact && $contact->control == 1) {
					return response()->json([
						'success' => false,
						'message' => 'This contact is currently handled by human support. AI cannot send messages.'
					]);
				}
			}

			/* Create message */
			$chat = Chat::create([
				'chatbot_contact_id' => $validated['chatbot_contact_id'],
				'message' => $validated['message'],
				'created_by' => 0,
				'created_by_type' => $validated['created_by_type'],
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
	 *     path="/api/frontend/chatbot_contacts/{chatbot_contact_id}/chats",
	 *     summary="Get all chats for a contact (customer view)",
	 *     tags={"Chatbot - Frontend"},
	 *     @OA\Parameter(
	 *         name="chatbot_contact_id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Chats retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($chatbotContactId)
	{
		try {
			$contact = ChatbotContact::with(['chats' => function($q) {
				$q->orderBy('created_at', 'asc');
			}])->find($chatbotContactId);

			if (!$contact) {
				return response()->json([
					'success' => false,
					'message' => 'Contact not found'
				]);
			}

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
	 * @OA\Post(
	 *     path="/api/frontend/chats/mark-read",
	 *     summary="Mark messages as read by customer",
	 *     tags={"Chatbot - Frontend"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"chatbot_contact_id"},
	 *             @OA\Property(property="chatbot_contact_id", type="integer", example=1)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Messages marked as read", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function markAsRead(Request $request)
	{
		try {
			$validated = $request->validate([
				'chatbot_contact_id' => 'required|exists:chatbot_contacts,id'
			]);

			/* Mark all unread messages from user/AI as read */
			Chat::where('chatbot_contact_id', $validated['chatbot_contact_id'])
			->whereIn('created_by_type', ['user', 'AI'])
			->where('is_read', false)
			->update([
				'is_read' => true,
				'read_at' => now()
			]);

			return response()->json([
				'success' => true,
				'message' => 'Messages marked as read'
			]);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $e->errors()
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to mark messages as read',
				'error' => $e->getMessage()
			]);
		}
	}
}