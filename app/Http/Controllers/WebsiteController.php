<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/websites",
	 *     summary="Get Website List",
	 *     security={{"bearerAuth":{}}},
	 *     description="Fetches a list of all websites.",
	 *     tags={"Websites"},
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Login required.")
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", example="Website List"),
	 *             @OA\Property(
	 *                 property="websites",
	 *                 type="objecty"
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function index(Request $request)
	{
		if (!auth()->user()->can('list website')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$websites = Website::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$websites = $websites->offset(($page - 1)*$length)->limit($length);
		}

		$websites = $websites->get();

		return response()->json([
			'message' => 'Website List',
			'websites' => $websites
		]);
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		//
	}

	/**
	 * Display the specified resource.
	 */
	public function show(Website $website)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Website $website)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Website $website)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Website $website)
	{
		//
	}
}
