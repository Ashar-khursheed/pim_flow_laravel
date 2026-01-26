<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Language;
use App\Repository\ExcelRepository;

use App\Jobs\ImportReviewJob;
use App\Services\ExcelImporterService;
use Carbon\Carbon;
use Faker\Factory as Faker;
class ReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/reviews",
     *     summary="Get all reviews with search and pagination",
     *     description="Returns a paginated list of reviews. Supports global search by customer name, email, product, or comment.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Global search keyword (searches name, email, comment, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", example="John")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *      @OA\Parameter(
     * 				name="status",
     *				in="query",
     *				description="Filter products by status (e.g., draft, published)",
     *				required=false,
     *				@OA\Schema(type="string", enum={"draft","published"},example="published")
     *				),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Column name to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"id"}, example="id")
     *     ),
     *     @OA\Parameter(
     *         name="sort_dir",
     *         in="query",
     *         description="Sort direction (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of reviews",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reviews fetched successfully."),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total_records", type="integer", example=45),
     *             @OA\Property(property="total_pages", type="integer", example=5),
     *
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function index(Request $request)
    {
        // Build query with product relationship
        $query = Review::with('product:id,name,sku');

        // Apply search filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_email', 'LIKE', "%{$search}%")
                    ->orWhere('comment', 'LIKE', "%{$search}%")
                    ->orWhere('product_id', 'LIKE', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('sku', 'LIKE', "%{$search}%");
                    });
            });
        }
        $status = $request->input('status');
        if ($status !== null) {
            $query->where('status', $status);
        }
        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Validate sort parameters
        $allowedSortColumns = ['id', 'customer_name', 'customer_email', 'star', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        if (!in_array(strtolower($sortDir), ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        // Paginate results
        $perPage = $request->input('per_page', 10);

        // Validate per_page value
        $perPage = max(1, min((int) $perPage, 100)); // Limit between 1 and 100

        $reviews = $query->paginate($perPage);

        // Map the data
        $mappedData = $reviews->getCollection()->map(function ($review) {
            return [
                'id' => $review->id,
                'customer_name' => $review->customer_name,
                'customer_email' => $review->customer_email,
                'star' => $review->star,
                'comment' => $review->comment,
                'status' => $review->status,
                'product_id' => $review->product?->id,
                'product_name' => $review->product?->name,
                'sku' => $review->product?->sku,
                'images' => is_string($review->images)
                    ? json_decode($review->images, true) ?? []
                    : ($review->images ?? []),
                'created_at' => $review->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $review->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        // Replace paginator collection with mapped data
        $reviews->setCollection($mappedData);

        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'current_page' => $reviews->currentPage(),
            'per_page' => $reviews->perPage(),
            'total_records' => $reviews->total(),
            'total_pages' => $reviews->lastPage(),
            'data' => $reviews->items(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reviews",
     *     summary="Create a new review",
     *     description="Allows an authenticated user to create a product review with optional images.",
     *     security={{"bearerAuth":{}}},
     *     tags={"Reviews"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"customer_name", "customer_email", "product_id", "star", "comment"},
     *
     *                 @OA\Property(
     *                     property="customer_name",
     *                     type="string",
     *                     example="John Doe"
     *                 ),
     *                 @OA\Property(
     *                     property="customer_email",
     *                     type="string",
     *                     format="email",
     *                     example="john@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="star",
     *                     type="integer",
     *                     example=5
     *                 ),
     *                 @OA\Property(
     *                     property="comment",
     *                     type="string",
     *                     example="Great product!"
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     example="published"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="images[]",
     *                     description="Multiple review images (optional)",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Review created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review created successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:191',
            'customer_email' => 'required|email:strict|max:191',
            'product_id' => 'required|exists:ec_products,id',
            'star' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
            'status' => 'nullable|string|max:60',
            'images' => 'nullable|array',
            //'images.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $imagePaths = [];
        $uploadedImages = [];
        $path = env('STORAGE_ENV') . '/production/review';

        // ✅ Upload & compress images
        if ($request->hasFile('images') && is_array($request->file('images'))) {

            foreach ($request->file('images') as $key=>$imageFile) {

                if (!$imageFile->isValid()) {
                    continue;
                }
                $tempRequest = new \Illuminate\Http\Request();
                $tempRequest->files->set('review_image_single', $imageFile);

                $url = uploadImageToWebpS3FromFile(
                    $tempRequest,
                    'review_image_single',
                    $path
                );

                    if ($url) {
                    $uploadedImages[] = $url;
                }
            }
        }

        $images = json_encode($uploadedImages);


        // Ensure default empty array for images
        $review = Review::create([
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'product_id' => $request->product_id,
            'star' => $request->star,
            'comment' => $request->comment,
            'status' => $request->status ?? 'published',
            'images' => !empty($images) ? $images : [],
            'created_at' => Carbon::now()->subDays(rand(60, 730)),
            'updated_at' => Carbon::now()->subDays(rand(60, 730)),

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review added successfully',
            'review' => $review
        ], 201);

    }


    /**
     * @OA\Get(
     *     path="/api/reviews/{id}",
     *     summary="Get a single review",
     *      security={{"bearerAuth":{}}},
     *     tags={"Reviews"},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review details"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function show($id)
    {
        $review = Review::with('product:id,name')->find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review successfully',
            'data' => [
                'id' => $review->id,
                'customer_id' => $review->customer_id,
                'customer_name' => $review->customer_name,
                'customer_email' => $review->customer_email,
                'product_id' => $review->product_id,
                'product_name' => $review->product->name,
                'star' => $review->star,
                'comment' => $review->comment,
                'status' => $review->status,
                'images' => $review->images,
            ]
        ]);

    }


    /**
 * @OA\Post(
 *     path="/api/reviews/{id}",
 *     summary="Update a review using POST with _method=PUT",
 *     tags={"Reviews"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID of the review to update",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *      @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="multipart/form-data",
    *             @OA\Schema(
    *                 required={"comment", "_method"},
    *                 @OA\Property(property="_method", type="string", example="PUT", description="Spoofing PUT request"),
    *                 @OA\Property(property="star", type="integer", minimum=1, maximum=5, example=4, description="Rating from 1 to 5"),
    *                 @OA\Property(property="comment", type="string", example="Great product!", description="Review comment"),
    *                 @OA\Property(property="status", type="string", example="published", description="Review status"),
    *                 @OA\Property(
    *                     property="images[]",
    *                     type="array",
    *                     @OA\Items(type="string", format="binary"),
    *                     description="Upload new images"
    *                 ),
    *                 @OA\Property(
    *                     property="delete_images[]",
    *                     type="array",
    *                     @OA\Items(type="string"),
    *                     description="List of image URLs to delete"
    *                 ),
    *
    *             )
    *         )
    *     ),

 *     @OA\Response(
 *         response=200,
 *         description="Review updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="Review updated successfully"),
 *             @OA\Property(property="review", type="object")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Unauthorized"),
 *     @OA\Response(response=422, description="Validation Error"),
 *     @OA\Response(response=404, description="Review Not Found")
 * )
 */

    public function update(Request $request, $id)
    {
        // if (!auth()->user()->can('update review')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => "You don't have permission to access this module.",
        //     ]);
        // }
        $review = Review::findOrFail($id);

        // Validate request
        $request->validate([
            'star' => 'nullable|integer|min:1|max:5',
            'comment' => 'required|string',
            'status' => 'nullable|string|in:published,pending,rejected',
            'images' => 'nullable|array',
            // 'images.*' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',

            'customer_name' => 'nullable|string|max:191',
            'customer_email' => 'nullable|email:strict|max:191'
        ]);

        // Update fields
        $review->star = $request->input('star', $review->star);
        $review->comment = $request->input('comment');
        $review->status = $request->input('status', $review->status);
        $review->customer_name = $request->input('customer_name', $review->customer_name);
        $review->customer_email = $request->input('customer_email', $review->customer_email);
        $existingImages = [];
        // Ensure existing images are an array
        $existingImages = is_string($review->images) ? json_decode($review->images, true) ?? [] : [];

        // Remove selected images safely
        if ($request->filled('delete_images')) {
            $deleteImages = $request->input('delete_images', []);

            // Remove only if they exist in the array
            $existingImages = array_values(array_filter($existingImages, function ($image) use ($deleteImages) {
                return !in_array($image, $deleteImages);
            }));
        }

          $path = env('STORAGE_ENV') . '/production/review';

            // Upload & compress images
           if ($request->hasFile('images') && is_array($request->file('images'))) {

                foreach ($request->file('images') as $key=>$imageFile) {

                    if (!$imageFile->isValid()) {
                        continue;
                    }
     	            $tempRequest = new \Illuminate\Http\Request();
				    $tempRequest->files->set('review_image_single', $imageFile);

                    $url = uploadImageToWebpS3FromFile(
                        $tempRequest,
                        'review_image_single',
                        $path
                    );

                     if ($url) {
                        $existingImages[] = $url;
                    }
                }
            }

            $images = json_encode($existingImages);


        // Store updated images list as JSON (Fix double escaping issue)
        $review->images = $images;

        // Allow modification of created_at only

        $review->created_at = Carbon::now()->subDays(rand(60, 730));
        $review->updated_at = Carbon::now()->subDays(rand(60, 730));
        $review->save();

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'review' => $review
        ]);
    }



    /**
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy($id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->delete();
        return response()->json(['message' => 'Review deleted'], 200);
    }



    /**
     * @OA\Post(
     *     path="/api/reviews/import",
     *     summary="Import reviews from an excel file",
     *     tags={"Reviews"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"upload_file"},
     *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Imported successfully", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function import(Request $request, ExcelImporterService $excelImporter)
    {
        /* Validate request data */
        $request->validate([
            'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
        ]);

        try {
            $langCodeArray = Language::pluck('lang_locale')->toArray();

            $keywordFileFormatArray = [
                'product_id' => 'product_id',
                'Review1' => 'Review1',
                'Review2' => 'Review2',
                'Review3' => 'Review3',
                'Review4' => 'Review4',
                'Review5' => 'Review5',
            ];


            $excelImporter->processExcelImport(
                $request->file('upload_file'),
                $keywordFileFormatArray,
                'Review', /* Module name */
                config('app.website') . '_REVIEW', /* Job name */
                'Import Reviews', /* Batch name */
                ImportReviewJob::class
            );

            return response()->json([
                'success' => true,
                'message' => 'The import process has been scheduled successfully.'
            ]);
        } catch (\Exception $exception) {
            $error[] = 'Error: ' . $exception->getMessage();
            $error[] = 'File: ' . $exception->getFile();
            $error[] = 'Line: ' . $exception->getLine();
            return response()->json([
                'success' => false,
                'message' => $error
            ]);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/reviews/export",
     *     summary="Export Excel Format",
     *     tags={"Reviews"},
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function export(Request $request, ExcelRepository $excelRepo)
    {

        /* Excel headers */
        $excelHeaders = ['product_id', 'Review1', 'Review2', 'Review3', 'Review4', 'Review5'];

        /* Fetch reviews and group by product_id */
        $groupedReviews = Review::whereBetween('id', [0, 0])
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('product_id'); // Group all reviews by product_id

        /* Format records - each product gets one row with up to 5 reviews */
        $records = $groupedReviews->map(function ($reviews, $productId) {
            // Take up to 5 reviews for this product
            $reviewComments = $reviews->pluck('comment')->take(5)->toArray();

            // Create row with product_id and up to 5 review comments
            $row = [
                $productId, // product_id
                $reviewComments[0] ?? '', // Review1
                $reviewComments[1] ?? '', // Review2
                $reviewComments[2] ?? '', // Review3
                $reviewComments[3] ?? '', // Review4
                $reviewComments[4] ?? '', // Review5
            ];

            return $row;
        })->values(); // Reset array keys

        /* Prepare spreadsheet */
        $spreadsheet = $excelRepo->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reviews');

        /* Set headers */
        $excelRepo->setHeader($sheet, $excelHeaders);

        /* Fill data rows */
        $rowIndex = 2;
        foreach ($records as $recordRow) {
            $excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
        }

        $fileName = 'reviews_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return $excelRepo->downloadFile($fileName, $spreadsheet);
    }

    /**
     * @OA\Post(
     *     path="/api/reviews/exportReview",
     *     summary="Export app keyword data to Excel",
     *     tags={"Reviews"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >=1)"),
     *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 5000 more)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function exportReview(Request $request, ExcelRepository $excelRepo)
    {

        /* Validate the request data */
        $request->validate([
            'range_from' => 'required|integer|min:1',
            'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 5000),
        ]);

        /* Define headers */
        $excelHeaders = ['customer_id', 'customer_name', 'customer_email', 'product_id', 'star', 'comment'];

        /* Fetch filtered records */
        $records = Review::query();
        $records = $records->offset($request->range_from - 1)
            ->limit($request->range_to - $request->range_from + 1)
            ->orderBy('id', 'desc')
            ->get()

            ->map(function ($review) {
                return [
                    $review->customer_id,
                    $review->customer_name,
                    $review->customer_email,
                    $review->product_id,
                    $review->star,
                    $review->comment,
                ];
            });

        /* Prepare spreadsheet */
        $spreadsheet = $excelRepo->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Review');

        /* Set headers */
        $excelRepo->setHeader($sheet, $excelHeaders);

        /* Fill data rows */
        $rowIndex = 2;
        foreach ($records as $recordRow) {
            $excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
        }

        /* Generate file name */
        $fileName = 'review_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        /* Return downloadable Excel */
        return $excelRepo->downloadFile($fileName, $spreadsheet);

    }



    /**
     * @OA\Post(
     *     path="/api/reviews/fekerEmailUpdate",
     *     summary="Get update reviews with name and email",
     *     description="Returns a paginated list of reviews. Supports global search by customer name, email, product, or comment.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Review Update",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reviews fetched successfully."),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total_records", type="integer", example=45),
     *             @OA\Property(property="total_pages", type="integer", example=5),
     *
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function fekerEmailUpdate(Request $request)
    {

        $notin = [
            'webdeveloper01@horecastore.ae',
            'webdeveloper02@horecastore.ae',
            'webdeveloper03@horecastore.ae',
            'webdeveloper04@horecastore.ae',
            'webdeveloper05@horecastore.ae',
            'webdeveloper06@horecastore.ae',
            'webdeveloper07@horecastore.ae',
            'webdeveloper08@horecastore.ae',
            'marketing@rapidhotelsupplies.com',
            'demo@gmail.com',
            'qa01@mailinator',
            'abcd@horecastore.ae',
            've@horecastore.ae',
            'es05@horecastore.ae',
            'qa05@horecastore.ae',
            'erpsupport@horecastore.ae',
            'qa04@horecastore.ae',
            'qa0445656@horecastore.ae',
            'abcd@horecastore.ae',
            'qa07@horecastore.ae',
            'test786543@gmail.com',
            'testbususer.usa.test@sharklasers.com',
            'test52@mailinator.com',
            'ndhake899@mailsac.com',
            'test@midsummer.agency',
            'testpvtuser.usa.test@sharklasers.com',
            'test6788@gmail.com',
            'testdev@mailinator.com',
            'shezadrazzaq@gmail.com',
            'dmm@thehorecastore.com',
            'sussexmobil1@gmail.com',
            'stevemcd1977@gmail.com',
            'fserrapumba@gmail.com',
            'testdev01@mailinator.com',
            'testing54@gmail.com',
            'thesweetestlilthings1@gmail.com',
            'testgaurav022@mailinator.com',
            'test23@gmail.com',
            'test@midsummer.agency',
            '56test@mailinator.com',
            'ndhake899@mailsac.com',
            'demo@yopmail.com',
            'nikhildemo83@mailsac.com',
            'careysauls66@yahoo.com',
            'little.rosalee@example.com',
            'demond.ernser@example.org',
            'shezadrazzaq@gmail.com',
            'marouscha.dorenbos@midsummer.agency',
            'marouscha.dorenbos@midsummer.agency',
            'emmy.abdulghaffarllc@gmail.com',
            'test786543@gmail.com',
            'hassan.quantum647@hotmail.com',
            'test1230@gmail.com',
            'webdeveloper01@rapid-supplies.com',
            'test.jasper@shopify.com',
            'testmail@gmail.com',
            'test43567@gmail.com',
            'qa01@mailinator.com',
            'dmm@thehorecastore.com',
            'test786543@gmail.com',
            'nikhiltest@gmail.com',
            'qa01@mailinator.com',
            'test@testkkk.com',
            'jack@yopmail.com',
            'jixaci8513@bawsny.com',
            'testdev@mailinator.com',
            'testsitelink@gmail.com',
            'test6788@gmail.com',
            'a1@mailinator.com',
            '56test@mailinator.com',
            'es05@horecastore.ae',
            'jixaci8513@bawsny.com',
            'test@midsummer.agency',
            'moghlhashan@gmail.com',
            'test45789@gmail.com',
            'yesy@test.com',
            'marymelito@aol.com',
            'testtest@gmail.com',
            'devtest@gmail.com',
            'horecastore@mailinator.com',
            'nailamemon1122@gmail.com',
            'testshaki@gmail.com',
            'test@example.com',
            'testdev03@mailinator.com',
            'whitestephen@example.com',
            'hhartman@example.net',
            'gfrancis@example.org',
        ];


        //
        $faker = Faker::create();
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        $reviews = Review::whereIn('customer_email', $notin)->get();
        $status = false;
        foreach ($reviews as $reviewv) {
            // Generate real-looking email
            $email = $faker->userName . '@' . $faker->randomElement($domains);
            $reviewv->customer_name = $faker->name;
            $reviewv->customer_email = $email;
            $reviewv->save();
            $status = 1;
        }
        $date = '2025-11-14';
        $reviews = Review::whereDate('created_at', $date)->get();

        foreach ($reviews as $reviewv) {
            $reviewv->created_at = Carbon::now()->subDays(rand(60, 730));

            $reviewv->save();
        }

        if ($status) {
            return response()->json([
                'success' => true,
                'message' => 'Review update successfully',

            ], 200);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Review not found',

            ], 201);

        }
    }
}
