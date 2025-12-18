<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FrontEnd\CustomerDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CustomerDocumentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/customer-documents",
     *     tags={"Frontend CustomerDocuments"},
     *     summary="Upload a new customer document",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "document"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="document", type="file"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Document uploaded successfully"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
   public function store(Request $request)
    {  
        $request->validate([
            'name' => 'required|string|max:255',
            'document' => 'required|file|max:10240', // 10MB
            'status' => 'nullable|in:active,inactive'
        ]);

        $userId = Auth::id();
        $isUserLoggedIn = $userId !== null;

        // Store the file
        $path = $request->file('document')->store(
            'customers/directory/documents',
            Storage::getDefaultDriver()
        );

        // Get full URL (e.g., http://yourdomain.com/storage/...)
        $fullUrl = Storage::url($path);

        // Optional: if you need absolute URL with domain
        $absoluteUrl = asset($fullUrl);

        $document = CustomerDocument::create([
            'customer_id' => $userId,
            'name' => $request->name,
            'document_path' => $absoluteUrl, // Save full URL
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document create successfully',
            'data' => $document,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/customer-documents/{id}",
     *     tags={"Frontend CustomerDocuments"},
     *     summary="Update customer document",
     *     security={{"bearerAuth":{}}}, *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer document ID",
     *         @OA\Schema(type="integer")
     *     ), *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", example="Trade License"),
     *                 @OA\Property(property="document", type="file"),
     *                 @OA\Property(property="status", type="string", enum={"active","inactive"})
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Document updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     )
     * )
     */

    public function update(Request $request, $id)
    { 
        $request->validate([
            'name' => 'required|string|max:255',
            'document' => 'nullable|file|max:10240', // 10MB
            'status' => 'nullable|in:active,inactive'
        ]);

        $userId = Auth::id();

        // ✅ Check document exists
        $document = CustomerDocument::find($id);
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // ✅ If new file uploaded
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store(
                'customers/directory/documents',
                Storage::getDefaultDriver()
            );

            $document->document_path = asset(Storage::url($path));
        }

        // ✅ Update fields
        $document->customer_id = $userId;
        $document->name = $request->name;
        $document->status = $request->status ?? 'active';
        $document->save();

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'data' => $document
        ], 200);
    }



    /**
     * @OA\Get(
     *     path="/api/frontend/customer-documents",
     *     tags={"Frontend CustomerDocuments"},
     *     summary="Get customer documents",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of documents")
     * )
     */
    public function index()
    {
         
         $userId = Auth::id();
            $isUserLoggedIn = $userId !== null;

        $documents = CustomerDocument::where('customer_id', $userId)->get();
    
        return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
                'data'=>$documents,                
            ], 200);
       
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/customer-documents/{id}",
     *     tags={"Frontend CustomerDocuments"},
     *     summary="Delete a customer document",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Deleted successfully"),
     *     @OA\Response(response=404, description="Document not found")
     * )
     */
    public function destroy($id)
    {
        $userId = Auth::id();
            $isUserLoggedIn = $userId !== null;
        $document = CustomerDocument::where('customer_id', $userId)->where('id', $id)->firstOrFail();

        Storage::delete($document->document_path);
        $document->delete();

         return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
                
            ], 200);
         
    }
     /**
     * @OA\Get(
     *     path="/api/frontend/customer-documents/{customer_id}",
     *     summary="Get all documents for a customer",
     *     tags={"Frontend CustomerDocuments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="List of documents")
     * )
     */
    public function customerDocuments($customer_id)
    {
        $documents = CustomerDocument::where('customer_id', $customer_id)->get();
        return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
                'data' => $documents
            ], 200);
    }
}
