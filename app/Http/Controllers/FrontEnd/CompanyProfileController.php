<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\CompanyProfile;
use Illuminate\Http\Request;


class CompanyProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/company-profiles",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get all company profiles",
     *     @OA\Response(
     *         response=200,
     *         description="List of company profiles"
     *     )
     * )
     */
    public function index()
    {
        return CompanyProfile::all();
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/company-profiles",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Create a new company profile",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "business_name", "country"},
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="business_name", type="string", example="Golden Palace Restaurant LLC"),
     *             @OA\Property(property="trade_name", type="string", example="Golden Palace"),
     *             @OA\Property(property="company_reg_no", type="string", example="LLC-2019-001234"),
     *             @OA\Property(property="vat_number", type="string", example="100123456700003"),
     *             @OA\Property(property="country", type="string", example="United Arab Emirates"),
     *             @OA\Property(property="legal_status", type="string", example="Limited Liability Company (LLC)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Company profile created successfully"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'business_name' => 'required|string',
            'trade_name' => 'nullable|string',
            'company_reg_no' => 'nullable|string',
            'vat_number' => 'nullable|string',
            'country' => 'required|string',
            'legal_status' => 'nullable|string',
        ]);

        $companyProfile = CompanyProfile::create($data);

        return response()->json($companyProfile, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get a specific company profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company profile found"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Company profile not found"
     *     )
     * )
     */
    public function show($id)
    {
        return CompanyProfile::findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Update a company profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="business_name", type="string", example="Golden Palace Restaurant LLC"),
     *             @OA\Property(property="trade_name", type="string", example="Golden Palace"),
     *             @OA\Property(property="company_reg_no", type="string", example="LLC-2019-001234"),
     *             @OA\Property(property="vat_number", type="string", example="100123456700003"),
     *             @OA\Property(property="country", type="string", example="United Arab Emirates"),
     *             @OA\Property(property="legal_status", type="string", example="Limited Liability Company (LLC)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company profile updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Company profile not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $companyProfile = CompanyProfile::findOrFail($id);

        $data = $request->validate([
            'customer_id' => 'sometimes|integer',
            'business_name' => 'sometimes|string',
            'trade_name' => 'nullable|string',
            'company_reg_no' => 'nullable|string',
            'vat_number' => 'nullable|string',
            'country' => 'sometimes|string',
            'legal_status' => 'nullable|string',
        ]);

        $companyProfile->update($data);

        return response()->json($companyProfile);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Delete a company profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Company profile not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        CompanyProfile::destroy($id);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
