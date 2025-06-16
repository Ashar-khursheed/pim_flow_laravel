<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Frontend\Country;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class CountryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/countries",
     *     operationId="getAllCountries",
     *     tags={"Frontend-Countries"},
     *     summary="Get all countries",
     *     description="Returns a list of all countries",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Country"))
     *     )
     * )
     */
    public function index()
    {
        $countries = Country::all();
        return response()->json($countries);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/countries/{id}",
     *     operationId="getCountryById",
     *     tags={"Frontend-Countries"},
     *     summary="Get a country by ID",
     *     description="Returns a single country",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of country to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Country")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found"
     *     )
     * )
     */
    public function show($id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        return response()->json($country);
    }
}
