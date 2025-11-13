<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Language;
use App\Models\FrontEnd\CustomerCart;
use App\Repository\ExcelRepository;

use App\Jobs\ImportReviewJob;
use App\Services\ExcelImporterService;
class CustomerCartExportController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/customerCartExport/export",
     *     summary="Export app keyword data to Excel",
     *     tags={"Customer Cart Export"},
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

    public function export(Request $request, ExcelRepository $excelRepo)
    {
        $request->validate([
            'range_from' => 'required|integer|min:1',
            'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 5000),
        ]);

        $customerCarts = CustomerCart::with([
            'customer:id,name,email,country_code,mobile_number',
            'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity,amount,total_amount',
            'customerCartProducts.product:id,name,images,sku,currency_id,barcode',
            'customerCartProducts.product.currency:id,symbol',
            'creator:id,username'
        ])
            ->offset($request->range_from - 1)
            ->limit($request->range_to - $request->range_from + 1)
            ->orderBy('id', 'asc')
            ->get();

        /* Find maximum number of products in any cart */
        $maxProducts = $customerCarts->max(function ($cart) {
            return $cart->customerCartProducts->count();
        });

        /* Build dynamic headers */
        $excelHeaders = [
            'reference_number',
            'customer_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'total_products',
        ];

        // Add dynamic product columns
        for ($i = 1; $i <= $maxProducts; $i++) {
            $excelHeaders[] = "product_{$i}_id";
            $excelHeaders[] = "product_{$i}_name";
            $excelHeaders[] = "product_{$i}_sku";
            $excelHeaders[] = "product_{$i}_quantity";
            $excelHeaders[] = "product_{$i}_price";
            $excelHeaders[] = "product_{$i}_total_amout";
        }

        /* Transform data */
        $exportData = $customerCarts->map(function ($cart) use ($maxProducts) {
            // dd($cart);
            $row = [
                '#C-' . $cart->reference_number ?? '',
                $cart->customer_id ?? '',
                $cart->customer->name ?? '',
                $cart->customer->email ?? '',
                ($cart->customer->country_code ?? '') . ($cart->customer->mobile_number ?? ''),
                $cart->customerCartProducts->count(),

            ];
            $products = $cart->customerCartProducts;

            // Add each product's data
            for ($i = 0; $i < $maxProducts; $i++) {
                if (isset($products[$i])) {
                    $row[] = $products[$i]->product->id ?? '';
                    $row[] = $products[$i]->product->name ?? '';
                    $row[] = $products[$i]->product->sku ?? '';
                    $row[] = $products[$i]->quantity ?? 0;
                    $row[] = $products[$i]->amount ?? 0;
                    $row[] = $products[$i]->total_amount ?? 0;
                } else {
                    // Empty cells if this cart has fewer products
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                }
            }
            return $row;
        })->toArray();

        /* Prepare spreadsheet */
        $spreadsheet = $excelRepo->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CustomerCart');

        /* Set headers */
        $excelRepo->setHeader($sheet, $excelHeaders);

        /* Fill data rows */
        $rowIndex = 2;
        foreach ($exportData as $recordRow) {
            $excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
        }

        /* Generate file name */
        $fileName = 'CustomerCart_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        /* Return downloadable Excel */
        return $excelRepo->downloadFile($fileName, $spreadsheet);

    }
}
