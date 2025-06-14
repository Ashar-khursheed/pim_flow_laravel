<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    
     /**
     * @OA\Get(
     *     path="/api/frontend/addresses",
     *     summary="Get all addresses of the authenticated user",
     *     tags={"Frontend-Address"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of user addresses",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Address"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        Log::info('Entered index method in AddressController.');
        $userId = Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

    
        $addresses = Address::where('customer_id', $userId)->get();
    
        Log::info('Fetched addresses: ', ['addresses' => $addresses]);
    
        return response()->json([
            'message' => 'Fetched addresses',
             'success'=>true,
            'data' => $addresses
        ]);
    }

     /**
     * @OA\Post(
     *     path="/api/frontend/addresses",
     *     summary="Add a new address",
     *     tags={"Frontend-Address"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","phone","country","state","city","address","zip_code"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="zip_code", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Address added successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
   public function store(Request $request)
    {
        Log::info('Entered store method for AddressController.');

        $userId = Auth::id(); // Get the authenticated user's ID
        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'country' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'zip_code' => 'required|string|max:10',
            ]);

            Log::info('Validated data: ', $validatedData);

            $addressCount = Address::where('customer_id', $userId)->count();

            // If it's the first address, make it the default
            $isDefault = $addressCount === 0 ? 1 : 0;

            $addressData = array_merge($validatedData, [
                'customer_id' => $userId,
                'is_default' => $isDefault,
            ]);

            Log::info('Merging data for creation: ', $addressData);

            $address = Address::create($addressData);

            Log::info('Address successfully created: ', ['address' => $address]);

            return response()->json([
                'message' => 'Address added successfully.',
                'success' => true,
                'data' => $address,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating address: ', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
     /**
     * @OA\Post(
     *     path="/api/frontend/addresses/default",
     *     summary="Set default address",
     *     tags={"Frontend-Address"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"address_id"},
     *             @OA\Property(property="address_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Default address updated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
     public function updateDefaultAddress(Request $request)
    {
        Log::info('Entered updateDefaultAddress method.');

        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        $validatedData = $request->validate([
            'address_id' => 'required|integer|exists:ec_customer_addresses,id'
        ]);

        try {
            // Remove current default
            Address::where('customer_id', $userId)->where('is_default', 1)->update(['is_default' => 0]);

            // Set the requested address as default
            $updated = Address::where('id', $validatedData['address_id'])->update(['is_default' => 1]);

            if ($updated) {
                Log::info('Default address updated successfully.');
                return response()->json([
                    'message' => 'Default address updated successfully.',
                    'success' => true,
                ]);
            }

            Log::error('Failed to update the default address.');
            return response()->json([
                'error' => 'Failed to set default address.',
                'success' => false,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in updateDefaultAddress: ', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    
    

    /**
     * @OA\Put(
     *     path="/api/frontend/addresses/{id}",
     *     summary="Update an address by ID",
     *     tags={"Frontend-Address"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Address ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="zip_code", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Address updated successfully"),
     *     @OA\Response(response=404, description="Address not found")
     * )
     */
    public function update(Request $request, $id)
    {
        Log::info('Entered update method with ID: ', ['id' => $id]);
    
        $address = Address::where('id', $id)->first();
    
        if (!$address) {
            Log::warning('Address not found.', ['id' => $id]);
            return response()->json(['error' => 'Address not found'], 404);
        }
    
        $address->update($request->all());
        Log::info('Address updated: ', ['address' => $address]);
    
        return response()->json(
            ['message' => 'Address updated successfully.', 
              'success'=>true,
            'data' => $address]);
    }
    
     /**
     * @OA\Delete(
     *     path="/api/frontend/addresses/{id}",
     *     summary="Delete an address by ID",
     *     tags={"Frontend-Address"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Address ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Address deleted successfully"),
     *     @OA\Response(response=404, description="Address not found")
     * )
     */
    public function destroy($id)
    {
        Log::info('Entered destroy method with ID: ', ['id' => $id]);
    
        // Attempt to delete the address directly
        $deleted = Address::where('id', $id)->delete();
        
        if ($deleted) {
            Log::info('Address deleted successfully.', ['id' => $id]);
            return response()->json([
                'message' => 'Address deleted successfully',
                'success' => true,
            ], 200);
        }
    
        Log::info('No address found to delete.', ['id' => $id]);
        return response()->json([
            'message' => 'Address deleted successfully',
            'success' => true,
        ], 200);
    }




  
}
