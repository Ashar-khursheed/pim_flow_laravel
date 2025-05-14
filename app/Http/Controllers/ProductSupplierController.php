<?php
// app/Http/Controllers/Api/ProductSupplierController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductSupplier;
use Illuminate\Http\Request;

class ProductSupplierController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/product-suppliers",
     *     operationId="getProductSuppliers",
     *     tags={"Product Suppliers"},
     *     summary="Get all product suppliers",
     *     description="Returns a list of all product suppliers",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProductSupplier"))
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index()
    {
        return ProductSupplier::all();
    }

    /**
     * @OA\Post(
     *     path="/api/product-suppliers",
     *     operationId="storeProductSupplier",
     *     tags={"Product Suppliers"},
     *     summary="Create a new product supplier",
     *     description="Creates a new product supplier entry",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"sku", "vendor_id", "product_id"},
     *             @OA\Property(property="sku", type="string"),
     *             @OA\Property(property="vendor_id", type="integer"),
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="warranty_information", type="string"),
     *             @OA\Property(property="refund", type="string"),
     *             @OA\Property(property="delivery_days", type="string"),
     *             @OA\Property(property="cost_per_item", type="number", format="float"),
     *             @OA\Property(property="sale_price", type="number", format="float"),
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="margin", type="number", format="float"),
     *             @OA\Property(property="inventory", type="integer"),
     *             @OA\Property(property="additional_cost", type="number", format="float"),
     *             @OA\Property(property="final_cost_price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string',
            'vendor_id' => 'required|integer',
            'product_id' => 'nullable|integer', // changed to nullable
            'warranty_information' => 'nullable|string',
            'refund' => 'nullable|string',
            'delivery_days' => 'nullable|string',
            'cost_per_item' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'margin' => 'nullable|numeric',
            'inventory' => 'nullable|integer',
            'additional_cost' => 'nullable|numeric',
            'final_cost_price' => 'nullable|numeric',
        ]);

        // Check if a record with the same sku and vendor_id already exists
        $existingEntry = ProductSupplier::where('sku', $data['sku'])
                                        ->where('vendor_id', $data['vendor_id'])
                                        ->first();

        if ($existingEntry) {
            return response()->json([
                'message' => 'A product supplier with the same SKU and Vendor ID already exists.',
            ], 422);
        }

        // Automatically fetch product_id if not provided
        if (empty($data['product_id']) && !empty($data['sku'])) {
            $product = \DB::table('ec_products')->where('sku', $data['sku'])->first();

            if (!$product) {
                return response()->json([
                    'message' => 'No product found with the given SKU.',
                ], 422);
            }

            $data['product_id'] = $product->id;
        }

        // Validate price logic
        if (
            isset($data['price'], $data['sale_price']) &&
            $data['price'] < $data['sale_price']
        ) {
            return response()->json([
                'message' => 'Price cannot be less than sale price.',
            ], 422);
        }

        return ProductSupplier::create($data);
    }

    
    

   /**
     * @OA\Get(
     *     path="/api/product-suppliers/{product_id}",
     *     operationId="getProductSupplierByProductId",
     *     tags={"Product Suppliers"},
     *     summary="Get a product supplier by Product ID",
     *     description="Returns a product supplier by its associated product ID",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product supplier not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($product_id)
    {
        $supplier = ProductSupplier::with('vendor')->where('product_id', $product_id)->first();

        if (!$supplier) {
            return response()->json(['message' => 'Product supplier not found'], 404);
        }

        return response()->json([
            'id' => $supplier->id,
            'product_id' => $supplier->product_id,
            'sku' => $supplier->sku,
            'vendor_id' => $supplier->vendor_id,
            'vendor_name' => $supplier->vendor ? $supplier->vendor->name : null,
            'warranty_information' => $supplier->warranty_information,
            'refund' => $supplier->refund,
            'delivery_days' => $supplier->delivery_days,
            'cost_per_item' => $supplier->cost_per_item,
            'sale_price' => $supplier->sale_price,
            'price' => $supplier->price,
            'margin' => $supplier->margin,
            'inventory' => $supplier->inventory,
            'additional_cost' => $supplier->additional_cost,
            'final_cost_price' => $supplier->final_cost_price,
            'created_at' => $supplier->created_at,
            'updated_at' => $supplier->updated_at,
        ]);
    }

    

    /**
     * @OA\Put(
     *     path="/api/product-suppliers/{id}",
     *     operationId="updateProductSupplier",
     *     tags={"Product Suppliers"},
     *     summary="Update a product supplier",
     *     description="Updates the details of a product supplier",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product supplier to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sku", type="string"),
     *             @OA\Property(property="vendor_id", type="integer"),
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="warranty_information", type="string"),
     *             @OA\Property(property="refund", type="string"),
     *             @OA\Property(property="delivery_days", type="string"),
     *             @OA\Property(property="cost_per_item", type="number", format="float"),
     *             @OA\Property(property="sale_price", type="number", format="float"),
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="margin", type="number", format="float"),
     *             @OA\Property(property="inventory", type="integer"),
     *             @OA\Property(property="additional_cost", type="number", format="float"),
     *             @OA\Property(property="final_cost_price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product supplier not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        $supplier = ProductSupplier::findOrFail($id);
        $supplier->update($request->all());
        return $supplier;
    }

    /**
     * @OA\Delete(
     *     path="/api/product-suppliers/{id}",
     *     operationId="deleteProductSupplier",
     *     tags={"Product Suppliers"},
     *     summary="Delete a product supplier",
     *     description="Deletes a product supplier by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product supplier to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product supplier not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        ProductSupplier::destroy($id);
        return response()->json(['message' => 'Deleted successfully']);
    }


    

}


