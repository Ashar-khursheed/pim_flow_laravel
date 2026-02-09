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
	 *     path="/api/frontend/contacts/find-or-create",
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
		try {
			$validated = $request->validate([
				'name' => 'required|string|max:255',
				'email' => 'required|email|max:255',
				'phone_number' => 'nullable|string|max:20'
			]);

			/* Find or create contact */
			$contact = ChatbotContact::where('email', $validated['email'])->first();

			if ($contact) {
				/* Update existing contact info if needed */
				$contact->update([
					'name' => $validated['name'],
					'phone_number' => $validated['phone_number'] ?? $contact->phone_number
					'control' => 0
				]);

				return response()->json([
					'success' => true,
					'message' => 'Contact already exists',
					'data' => $contact->load('chats'),
					'created' => false
				], 200);
			}

			/* Create new contact (AI mode by default) */
			$contact = ChatbotContact::create([
				'name' => $validated['name'],
				'email' => $validated['email'],
				'phone_number' => $validated['phone_number'],
				'control' => 0
			]);

			return response()->json([
				'success' => true,
				'message' => 'Contact created successfully',
				'data' => $contact->load('chats'),
				'created' => true
			], 201);

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
	 *     summary="Create a new chat message from customer",
	 *     tags={"Chatbot - Frontend"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"chatbot_contact_id", "message"},
	 *             @OA\Property(property="chatbot_contact_id", type="integer", example=1),
	 *             @OA\Property(property="message", type="string", example="Hello, I need help")
	 *             @OA\Property(property="created_by_type", type="string", enum={"customer", "AI"}, example="AI")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function store(Request $request)
	{
		try {
			$validated = $request->validate([
				'chatbot_contact_id' => 'required|exists:chatbot_contacts,id',
				'message' => 'required|string|max:5000',
				'created_by_type' => 'required|in:customer,AI'
			]);

			/* Create customer message */
			$chat = Chat::create([
				'chatbot_contact_id' => $validated['chatbot_contact_id'],
				'message' => $validated['message'],
				'created_by' => 0,
				'created_by_type' => $request->created_by_type,
				'is_read' => false
			]);

			$chat->load('chatbotContact');

			/* Send to Pusher */
			callPusher($chat);

			return response()->json([
				'success' => true,
				'message' => 'Chat created successfully',
				'data' => $chat
			], 201);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to create chat',
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
	 *     @OA\Response(response=200, description="Messages marked as read")
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

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to mark messages as read',
				'error' => $e->getMessage()
			], 500);
		}
	}
}