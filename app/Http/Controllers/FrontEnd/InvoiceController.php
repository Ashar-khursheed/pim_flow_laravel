<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/invoices",
     *     operationId="getCustomerInvoices",
     *     tags={"Frontend Invoices"},
     *     summary="Get all invoices for the authenticated customer",
     *     description="Returns invoice summary and list for the authenticated customer.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_outstanding", type="number", example=1200.50),
     *             @OA\Property(property="paid_this_month", type="number", example=850.00),
     *             @OA\Property(property="overdue", type="number", example=350.50),
     *             @OA\Property(property="total_invoices", type="integer", example=5),
     *             @OA\Property(
     *                 property="invoices",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="invoice_number", type="string", example="INV-1001"),
     *                     @OA\Property(property="invoice_date", type="string", example="Jul 24 2025"),
     *                     @OA\Property(property="order_id", type="integer", example=101),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-07"),
     *                     @OA\Property(property="due_date", type="string", example="Jul 31 2025"),
     *                     @OA\Property(property="amount", type="string", example="1200.50"),
     *                     @OA\Property(property="payment_method", type="string", example="Bank Transfer"),
     *                     @OA\Property(property="status", type="string", example="unpaid")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function index()
    {
        $customer = Auth::id();

        $invoices = Invoice::where('customer_id', $customer)->latest()->get();

        return response()->json([
            'total_outstanding' => $invoices->where('status', '!=', 'paid')->sum('amount'),
            'paid_this_month'   => $invoices->where('status', 'paid')->filter(function ($inv) {
                return Carbon::parse($inv->updated_at)->format('m') == now()->format('m');
            })->sum('amount'),
            'overdue'           => $invoices->where('status', 'overdue')->sum('amount'),
            'total_invoices'    => $invoices->count(),
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'invoice_number'  => $invoice->invoice_number,
                    'invoice_date'    => Carbon::parse($invoice->invoice_date)->format('M d Y'),
                    'order_id'        => $invoice->order_id,
                    'po_number'       => $invoice->po_number,
                    'due_date'        => Carbon::parse($invoice->due_date)->format('M d Y'),
                    'amount'          => number_format($invoice->amount, 2),
                    'payment_method'  => $invoice->payment_method,
                    'status'          => $invoice->status,
                ];
            }),
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/invoices/{id}",
     *     operationId="getCustomerInvoiceById",
     *     tags={"Frontend Invoices"},
     *     summary="Get a single invoice by ID or invoice number (customer-specific)",
     *     description="Returns details for one invoice, only if it belongs to the authenticated customer.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID or invoice number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice found",
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_number", type="string", example="INV-1001"),
     *             @OA\Property(property="invoice_date", type="string", example="Jul 24 2025"),
     *             @OA\Property(property="order_id", type="integer", example=101),
     *             @OA\Property(property="po_number", type="string", example="PO-2025-07"),
     *             @OA\Property(property="due_date", type="string", example="Jul 31 2025"),
     *             @OA\Property(property="amount", type="string", example="1200.50"),
     *             @OA\Property(property="payment_method", type="string", example="Bank Transfer"),
     *             @OA\Property(property="status", type="string", example="unpaid"),
     *             @OA\Property(property="created_at", type="string", example="Jul 24 2025 14:32:00"),
     *             @OA\Property(property="updated_at", type="string", example="Jul 24 2025 15:10:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $customer = Auth::id();

        $invoice = Invoice::where('customer_id', $customer)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('invoice_number', $id);
            })
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        return response()->json([
            'invoice_number'  => $invoice->invoice_number,
            'invoice_date'    => $invoice->invoice_date->format('M d Y'),
            'order_id'        => $invoice->order_id,
            'po_number'       => $invoice->po_number,
            'due_date'        => $invoice->due_date->format('M d Y'),
            'amount'          => number_format($invoice->amount, 2),
            'payment_method'  => $invoice->payment_method,
            'status'          => $invoice->status,
            'created_at'      => $invoice->created_at->format('M d Y H:i:s'),
            'updated_at'      => $invoice->updated_at->format('M d Y H:i:s'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/invoices",
     *     summary="Create a new invoice",
     *     tags={"Frontend Invoices"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invoice_number","invoice_date","amount"},
     *             @OA\Property(property="invoice_number", type="string"),
     *             @OA\Property(property="invoice_date", type="string", format="date"),
     *             @OA\Property(property="order_id", type="integer"),
     *             @OA\Property(property="po_number", type="string"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="payment_method", type="string"),
     *             @OA\Property(property="status", type="string", enum={"paid","unpaid","overdue"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Invoice created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number'  => 'required|string|unique:invoices,invoice_number',
            'invoice_date'    => 'required|date',
            'order_id'        => 'nullable|integer',
            'po_number'       => 'nullable|string',
            'due_date'        => 'nullable|date',
            'amount'          => 'required|numeric|min:0',
            'payment_method'  => 'nullable|string',
            'status'          => 'required|in:paid,unpaid,overdue',
        ]);

        $customer = Auth::id();

        $invoice = Invoice::create(array_merge($validated, [
            'customer_id' => $customer,
        ]));

        return response()->json([
            'message' => 'Invoice created',
            'invoice' => $invoice
        ], 201);
    }

}
