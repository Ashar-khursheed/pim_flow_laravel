<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Country;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Http\JsonResponse;


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

    /**
     * @OA\Get(
     *     path="/api/frontend/country-phonecodes",
     *     operationId="getCountryPhoneCodes",
     *     tags={"Country"},
     *     summary="Get all country phone codes",
     *     description="Returns a list of all country phone codes from the countries table.",
     *     @OA\Response(
     *         response=200,
     *         description="List of country phone codes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="iso3", type="string", example="Pak"),
     *                 @OA\Property(property="phonecode", type="integer", example=92)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getPhoneCodes(): JsonResponse
    {
        $phoneCodes = Country::select('id', 'iso3', 'phonecode')->get();
        return response()->json($phoneCodes);
    }
}
