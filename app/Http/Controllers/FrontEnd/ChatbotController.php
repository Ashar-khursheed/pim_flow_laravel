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
	 *     path="/api/frontend/chatbot/contacts/find-or-create",
	 *     summary="Find existing contact by email or create new one",
	 *     tags={"Chatbot"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "email"},
	 *             @OA\Property(property="name", type="string", example="Noman Peera"),
	 *             @OA\Property(property="email", type="string", format="email", example="noman@example.com"),
	 *             @OA\Property(property="phone_number", type="string", example="+971123456789"),
	 *             @OA\Property(property="control", type="boolean", example=false),
	 *             @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Contact found or created successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function findOrCreateContact(Request $request)
	{
		try {
			$request->validate([
				'name' => 'required|string|max:255',
				'email' => 'required|email',
				'phone_number' => 'nullable|string|max:20',
				'control' => 'nullable|boolean'
			]);

			/* Check if contact exists by email */
			$contact = ChatbotContact::where('email', $request->email)->first();

			if ($contact) {
				return response()->json([
					'success' => true,
					'message' => 'Contact already exists',
					'data' => $contact,
					'created' => false
				]);
			}

			/* Contact doesn't exist, create new one */
			$contact = ChatbotContact::create([
				'name' => $request->name,
				'email' => $request->email,
				'phone_number' => $request->phone_number,
				'control' => $request->control ?? 0
			]);

			return response()->json([
				'success' => true,
				'message' => 'Contact created successfully',
				'data' => $contact->load('chats'),
				'data' => $contact,
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
	 *     path="/api/frontend/chatbot/chats",
	 *     summary="Create a new chat message",
	 *     tags={"Chatbot"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"chatbot_contact_id", "message", "created_by_type"},
	 *             @OA\Property(property="chatbot_contact_id", type="integer", example=1),
	 *             @OA\Property(property="message", type="string", example="Hello, I need help with my order"),
	 *             @OA\Property(property="created_by_type", type="string", enum={"user", "customer", "AI"}, example="customer")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function createChat(Request $request)
	{
		try {
			$request->validate([
				'chatbot_contact_id' => 'required|exists:chatbot_contacts,id',
				'message' => 'required|string',
				'created_by_type' => 'required|in:user,customer,AI'
			]);

			/* Create the chat message */
			$chat = Chat::create([
				'chatbot_contact_id' => $request->chatbot_contact_id,
				'message' => $request->message,
				'created_by' => 0,
				'created_by_type' => $request->created_by_type ?? 'customer'
			]);

			/* Load contact relationship */
			$chat->load('chatbotContact');

			/* Send to Pusher using helper function */
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
			], 500);
		}
	}

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/chatbot/contacts",
	//  *     summary="Get all chatbot contacts",
	//  *     tags={"Chatbot"},
	//  *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
	//  * )
	//  */
	// public function getContacts()
	// {
	// 	try {
	// 		$contacts = ChatbotContact::with('chats')->orderBy('created_at', 'desc')->get();

	// 		return response()->json([
	// 			'success' => true,
	// 			'data' => $contacts
	// 		]);
	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to fetch contacts',
	// 			'error' => $e->getMessage()
	// 		]);
	// 	}
	// }


	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/chatbot/chats",
	//  *     summary="Get all chats with optional filters",
	//  *     tags={"Chatbot"},
	//  *     @OA\Parameter(
	//  *         name="chatbot_contact_id",
	//  *         in="query",
	//  *         description="Filter by contact ID",
	//  *         required=true,
	//  *         @OA\Schema(type="integer")
	//  *     ),
	//  *     @OA\Parameter(
	//  *         name="created_by_type",
	//  *         in="query",
	//  *         description="Filter by creator type",
	//  *         required=false,
	//  *         @OA\Schema(type="string", enum={"user", "customer", "AI"})
	//  *     ),
	//  *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
	//  * )
	//  */
	// public function getChats(Request $request)
	// {
	// 	try {
	// 		$query = Chat::with('chatbotContact');

	// 		/* Filter by contact if provided */
	// 		if ($request->has('chatbot_contact_id')) {
	// 			$query->where('chatbot_contact_id', $request->chatbot_contact_id);
	// 		}

	// 		/* Filter by type if provided */
	// 		if ($request->has('created_by_type')) {
	// 			$query->where('created_by_type', $request->created_by_type);
	// 		}

	// 		$chats = $query->orderBy('created_at', 'desc')->get();

	// 		return response()->json([
	// 			'success' => true,
	// 			'data' => $chats
	// 		], 200);
	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to fetch chats',
	// 			'error' => $e->getMessage()
	// 		], 500);
	// 	}
	// }

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/chatbot/chats/{id}",
	//  *     summary="Get a specific chat by ID",
	//  *     tags={"Chatbot"},
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         description="Chat ID",
	//  *         required=true,
	//  *         @OA\Schema(type="integer")
	//  *     ),
	//  *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	//  * )
	//  */
	// public function showChat($id)
	// {
	// 	try {
	// 		$chat = Chat::with('chatbotContact')->find($id);

	// 		if (!$chat) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Chat not found'
	// 			], 404);
	// 		}

	// 		return response()->json([
	// 			'success' => true,
	// 			'data' => $chat
	// 		], 200);
	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to fetch chat',
	// 			'error' => $e->getMessage()
	// 		], 500);
	// 	}
	// }

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/chatbot/contacts/{id}/chats",
	//  *     summary="Get all chats for a specific contact",
	//  *     tags={"Chatbot"},
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         description="Contact ID",
	//  *         required=true,
	//  *         @OA\Schema(type="integer")
	//  *     ),
	//  *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	//  * )
	//  */
	// public function getContactChats($contactId)
	// {
	// 	try {
	// 		$contact = ChatbotContact::with('chats')->find($contactId);

	// 		if (!$contact) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Contact not found'
	// 			], 404);
	// 		}

	// 		return response()->json([
	// 			'success' => true,
	// 			'data' => [
	// 				'contact' => $contact,
	// 				'chats' => $contact->chats
	// 			]
	// 		], 200);
	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to fetch contact chats',
	// 			'error' => $e->getMessage()
	// 		], 500);
	// 	}
	// }
}