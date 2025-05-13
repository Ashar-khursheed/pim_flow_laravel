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
            'product_id' => 'required|integer',
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

        return ProductSupplier::create($data);
    }

    /**
     * @OA\Get(
     *     path="/api/product-suppliers/{id}",
     *     operationId="getProductSupplierById",
     *     tags={"Product Suppliers"},
     *     summary="Get a product supplier by ID",
     *     description="Returns a product supplier",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product supplier",
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
    public function show($id)
    {
        return ProductSupplier::findOrFail($id);
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


