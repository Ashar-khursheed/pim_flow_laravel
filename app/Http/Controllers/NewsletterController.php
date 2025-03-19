<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Newsletters",
 *     description="API Endpoints for managing newsletters"
 * )
 */
class NewsletterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/newsletters",
     *     summary="Get a paginated list of newsletters",
     *     tags={"Newsletters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Newsletter")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(Newsletter::latest()->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/api/newsletters",
     *     summary="Subscribe a new email",
     *     tags={"Newsletters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="status", type="string", enum={"subscribed", "unsubscribed"}, example="subscribed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Newsletter subscription created",
     *         @OA\JsonContent(ref="#/components/schemas/Newsletter")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:newsletters,email',
            'name' => 'nullable|string|max:120',
            'status' => 'nullable|string|in:subscribed,unsubscribed',
        ]);

        return response()->json(Newsletter::create($request->all()), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/newsletters/{id}",
     *     summary="Get a specific newsletter by ID",
     *     tags={"Newsletters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the newsletter",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Newsletter")
     *     )
     * )
     */
    public function show($id)
    {
        return response()->json(Newsletter::findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/newsletters/{id}",
     *     summary="Update a newsletter subscription",
     *     tags={"Newsletters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the newsletter",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="status", type="string", enum={"subscribed", "unsubscribed"}, example="subscribed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated",
     *         @OA\JsonContent(ref="#/components/schemas/Newsletter")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $newsletter = Newsletter::findOrFail($id);
        $request->validate([
            'email' => 'required|email|unique:newsletters,email,' . $id,
            'name' => 'nullable|string|max:120',
            'status' => 'nullable|string|in:subscribed,unsubscribed',
        ]);

        $newsletter->update($request->all());

        return response()->json($newsletter);
    }

    /**
     * @OA\Delete(
     *     path="/api/newsletters/{id}",
     *     summary="Delete a newsletter subscription",
     *     tags={"Newsletters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the newsletter",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Successfully deleted"
     *     )
     * )
     */
    public function destroy($id)
    {
        $newsletter = Newsletter::findOrFail($id);
        $newsletter->delete();

        return response()->json(null, 204);
    }
}
