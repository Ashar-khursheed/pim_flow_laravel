<?php

namespace App\Http\Controllers\FrontEnd;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\FrontEnd\ProductError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ProductErrorMail;

class ProductErrorController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/product-errors",
     *     summary="Create a product error report",
     *     description="Submit an issue related to a specific product",
     *     tags={"Product Errors"},
     *     operationId="storeProductError",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "product_id", "problem"},
     *             @OA\Property(property="title", type="string", example="Image not loading"),
     *             @OA\Property(property="product_id", type="string", example="12345"),
     *             @OA\Property(property="problem", type="string", example="Product description is missing"),
     *             @OA\Property(property="problem_timestamp", type="string", format="date-time", example="2025-06-26T12:00:00Z"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="created_by", type="integer", example=1),
     *             @OA\Property(property="updated_by", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product error created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product error reported successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */


        public function store(Request $request)
        {
            $request->validate([
                'title' => 'required|string|max:255',
                'product_id' => 'required|string|max:255',
                'problem' => 'required|string',
                'problem_timestamp' => 'nullable|date',
                'email' => 'nullable|email:strict',
                'created_by' => 'nullable|integer',
                'updated_by' => 'nullable|integer',
            ]);

            $productError = ProductError::create($request->all());

            $data = $productError->toArray();

            // Send email to user if provided
            // if ($productError->email) {
            //     Notification::route('mail', $productError->email)
            //         ->notify(new ProductErrorMail($data));
            // }

            // Determine BCC based on title
            $title = strtolower(trim($productError->title));
            $bccRecipients = [];

            switch ($title) {
                case 'product content':
                    $bccRecipients = [
                        'content@horecastore.ae',
                        'webdeveloper01@horecastore.ae'
                    ];
                    break;
                case 'product image':
                    $bccRecipients[] = 'creative@horecastore.ae';
                    break;
                case 'product pricing':
                case 'product specification':
                    $bccRecipients[] = 'ecommerce@horecastore.ae';
                    break;
                case 'all the above':
                    $bccRecipients = [
                        'content@horecastore.ae',
                        'creative@horecastore.ae',
                        'ecommerce@horecastore.ae',
                    ];
                    break;
            }

            // Send BCC to internal team based on title
            if (!empty($bccRecipients)) {
                Mail::send('emails.product_error_reported', ['data' => $data], function ($message) use ($bccRecipients) {
                    $message->to('nomanpeera@horecastore.ae') // dummy TO
                            ->bcc($bccRecipients)
                            ->subject('New Product Error Reported');
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Product error reported successfully.',
                'data' => $productError,
            ], 201);
        }



    /**
     * @OA\Get(
     *     path="/api/frontend/product-errors",
     *     summary="List all product error reports",
     *     description="Returns a list of all submitted product errors",
     *     tags={"Product Errors"},
     *     operationId="getAllProductErrors",
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index()
    {
        return ProductError::all();
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/product-errors/{product_id}",
     *     summary="Get product errors by product ID",
     *     description="Retrieve all error reports associated with a specific product ID",
     *     tags={"Product Errors"},
     *     operationId="getProductErrorByProductId",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         required=true,
     *         description="The product ID to retrieve errors for",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product errors retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No errors found for the given product ID"
     *     )
     * )
     */
    public function show($product_id)
    {
        $errors = ProductError::where('product_id', $product_id)->get();

        if ($errors->isEmpty()) {
            return response()->json([
                'message' => 'No errors found for this product.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $errors,
        ], 200);
    }
}
