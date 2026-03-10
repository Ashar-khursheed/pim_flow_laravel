<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\City;
use App\Models\State;
use App\Models\Zipcode;

class LocationController extends BaseController
{
    // /**
    //  * @OA\Get(
    //  *     path="/api/countries",
    //  *     summary="Get country List",
    //  *     description="Fetches a list of all countries.",
    //  *     tags={"Locations"},
    //  *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
    //  *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
    //  *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
    //  * )
    //  */
    // public function getCountryList(Request $request)
    // {
    //     $records = Country::query();

    //     /* Pagination */
    //     if ($request->filled('page') && $request->filled('length')) {
    //         $page = (int) $request->input('page');
    //         $length = (int) $request->input('length');
    //         $totalRecords = $records->count();
    //         $totalPages = ceil($totalRecords / $length);

    //         $records = $records->offset(($page - 1) * $length)->limit($length)->get();
    //     } else {
    //         $records = $records->get(['id', 'name']);
    //         $totalRecords = $records->count();
    //     }

    //     return response()->json([
    //         'message' => __("msg_rec_list"),
    //         'data' => $records,
    //         'total_pages' => $totalPages ?? 1,
    //         'total_records' => $totalRecords,
    //     ]);
    // }

    /**
     * @OA\Get(
     *     path="/api/states/{country_id}",
     *     summary="Get state List",
     *     description="Fetches a list of all states.",
     *     tags={"Locations"},
     *     @OA\Parameter(name="country_id", in="path", required=true, description="ID of the country", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function getStateList(Request $request, $countryId)
    {
        $records = State::where('country_id', $countryId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'name']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{country_id}",
     *     summary="Get city List",
     *     description="Fetches a list of all cities.",
     *     tags={"Locations"},
     *     @OA\Parameter(name="country_id", in="path", required=true, description="ID of the country", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function getCityList(Request $request, $countryId)
    {
        $records = City::where('country_id', $countryId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'name']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/zipcodes/{city_id}",
     *     summary="Get zipcode List",
     *     description="Fetches a list of all zipcodes.",
     *     tags={"Locations"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="ID of the city",
     *         @OA\Schema(type="integer", example=1)
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
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getZipcodeList(Request $request, $cityId)
    {
        $records = Zipcode::where('city_id', $cityId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'zip_code']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
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
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
