<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
 
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Language;
use App\Repository\ExcelRepository;

use App\Jobs\ImportReviewJob;
use App\Services\ExcelImporterService;
class customerCartExportController extends Controller
{
      /**
     * @OA\Post(
     *     path="/api/customerCartExport/export",
     *     summary="Export Excel Format",
     *     tags={"Customer Cart Export"},    
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function export(Request $request, ExcelRepository $excelRepo)
    {
 

    }
}
