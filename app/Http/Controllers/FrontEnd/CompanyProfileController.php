<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyProfileController extends Controller
{
    

    /**
     * @OA\Get(
     *     path="/api/frontend/company-profiles",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get the logged-in user's company profile",
     *     @OA\Response(response=200, description="Company profile"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function index()
    {
        $userId = Auth::id();
        $profile = CompanyProfile::where('customer_id', $userId)->get();

        return response()->json($profile);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/company-profiles",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Create a new company profile for the logged-in user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_name", "country"},
     *             @OA\Property(property="business_name", type="string", example="Golden Palace Restaurant LLC"),
     *             @OA\Property(property="trade_name", type="string", example="Golden Palace"),
     *             @OA\Property(property="company_reg_no", type="string", example="LLC-2019-001234"),
     *             @OA\Property(property="vat_number", type="string", example="100123456700003"),
     *             @OA\Property(property="country", type="string", example="United Arab Emirates"),
     *             @OA\Property(property="legal_status", type="string", example="Limited Liability Company (LLC)")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string',
            'trade_name' => 'nullable|string',
            'company_reg_no' => 'nullable|string',
            'vat_number' => 'nullable|string',
            'country' => 'required|string',
            'legal_status' => 'nullable|string',
        ]);

        $data['customer_id'] = Auth::id();

        $profile = CompanyProfile::create($data);

        return response()->json($profile, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get a specific company profile by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Profile found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $profile = CompanyProfile::findOrFail($id);

        if ($profile->customer_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($profile);
    }

    /**
     * @OA\Put(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Update the logged-in user's company profile",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="business_name", type="string", example="Golden Palace Restaurant LLC"),
     *             @OA\Property(property="trade_name", type="string", example="Golden Palace"),
     *             @OA\Property(property="company_reg_no", type="string", example="LLC-2019-001234"),
     *             @OA\Property(property="vat_number", type="string", example="100123456700003"),
     *             @OA\Property(property="country", type="string", example="United Arab Emirates"),
     *             @OA\Property(property="legal_status", type="string", example="LLC")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $profile = CompanyProfile::findOrFail($id);

        if ($profile->customer_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'business_name' => 'sometimes|string',
            'trade_name' => 'nullable|string',
            'company_reg_no' => 'nullable|string',
            'vat_number' => 'nullable|string',
            'country' => 'sometimes|string',
            'legal_status' => 'nullable|string',
        ]);

        $profile->update($data);

        return response()->json($profile);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/company-profiles/{id}",
     *     tags={"Company Profiles"},
     *     security={{"bearerAuth":{}}},
     *     summary="Delete a company profile",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $profile = CompanyProfile::findOrFail($id);

        if ($profile->customer_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $profile->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
