<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
	}

	/**
	 * Store a newly created resource in storage.
	 */
	/**
	 * @OA\Post(
	 *     path="/api/products/store",
	 *     summary="Create a new product",
	 *     tags={"Products"},
	 *	   security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "sku", "status", "is_featured", "brand_id", "stock_status", "price", "store_id"},
	 *             @OA\Property(property="name", type="string", example="Sample Product"),
	 *             @OA\Property(property="description", type="string", nullable=true, example="Product description"),
	 *             @OA\Property(property="content", type="string", nullable=true, example="Product content details"),
	 *             @OA\Property(property="warranty_information", type="string", nullable=true, example="2 years"),
	 *             @OA\Property(property="sku", type="string", example="SKU12345"),
	 *             @OA\Property(property="status", type="string", example="active"),
	 *             @OA\Property(property="delivery_days", type="string", nullable=true, example="3-5"),
	 *             @OA\Property(property="is_featured", type="string", example="1"),
	 *             @OA\Property(property="brand_id", type="string", example="2"),
	 *             @OA\Property(property="images", type="array", @OA\Items(type="string"), example={"image1.jpg", "image2.jpg"}),
	 *             @OA\Property(property="video_path", type="string", nullable=true, example="video.mp4"),
	 *             @OA\Property(property="stock_status", type="string", example="in_stock"),
	 *             @OA\Property(property="with_storehouse_management", type="string", example="1"),
	 *             @OA\Property(property="unit_of_measurement_id", type="string", example="1"),
	 *             @OA\Property(property="quantity", type="string", nullable=true, example="100"),
	 *             @OA\Property(property="cost_per_item", type="string", nullable=true, example="50.00"),
	 *             @OA\Property(property="price", type="string", example="100.00"),
	 *             @OA\Property(property="sale_price", type="string", nullable=true, example="90.00"),
	 *             @OA\Property(property="start_date", type="string", format="date", nullable=true, example="2025-02-26"),
	 *             @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2025-03-10"),
	 *             @OA\Property(property="sale_type", type="string", example="percentage"),
	 *             @OA\Property(property="weight", type="string", nullable=true, example="1.5"),
	 *             @OA\Property(property="weight_unit_id", type="string", example="kg"),
	 *             @OA\Property(property="length", type="string", nullable=true, example="10"),
	 *             @OA\Property(property="width", type="string", nullable=true, example="5"),
	 *             @OA\Property(property="height", type="string", nullable=true, example="2"),
	 *             @OA\Property(property="depth", type="string", nullable=true, example="3"),
	 *             @OA\Property(property="shipping_weight_option", type="string", example="kg"),
	 *             @OA\Property(property="shipping_weight", type="string", nullable=true, example="1.7"),
	 *             @OA\Property(property="shipping_dimension_option", type="string", example="cm"),
	 *             @OA\Property(property="shipping_width", type="string", nullable=true, example="6"),
	 *             @OA\Property(property="shipping_depth", type="string", nullable=true, example="3"),
	 *             @OA\Property(property="shipping_height", type="string", nullable=true, example="2"),
	 *             @OA\Property(property="shipping_length", type="string", nullable=true, example="12"),
	 *             @OA\Property(property="frequently_bought_together", type="string", example="product1,product2"),
	 *             @OA\Property(property="compare_products", type="string", example="product3,product4"),
	 *             @OA\Property(property="refund", type="string", example="No refund policy"),
	 *             @OA\Property(property="currency_id", type="string", example="1"),
	 *             @OA\Property(property="variant_1_title", type="string", nullable=true, example="Size"),
	 *             @OA\Property(property="variant_1_value", type="string", nullable=true, example="M"),
	 *             @OA\Property(property="variant_1_products", type="string", nullable=true, example="variant1"),
	 *             @OA\Property(property="variant_2_title", type="string", nullable=true, example="Color"),
	 *             @OA\Property(property="variant_2_value", type="string", nullable=true, example="Red"),
	 *             @OA\Property(property="variant_2_products", type="string", nullable=true, example="variant2"),
	 *             @OA\Property(property="variant_3_title", type="string", nullable=true, example="Material"),
	 *             @OA\Property(property="variant_3_value", type="string", nullable=true, example="Cotton"),
	 *             @OA\Property(property="variant_3_products", type="string", nullable=true, example="variant3"),
	 *             @OA\Property(property="variant_color_title", type="string", nullable=true, example="Color"),
	 *             @OA\Property(property="variant_color_value", type="string", nullable=true, example="Blue"),
	 *             @OA\Property(property="variant_color_products", type="string", nullable=true, example="variant4"),
	 *             @OA\Property(property="barcode", type="string", nullable=true, example="123456789012"),
	 *             @OA\Property(property="minimum_order_quantity", type="string", example="1"),
	 *             @OA\Property(property="variant_requires_shipping", type="string", example="1"),
	 *             @OA\Property(property="google_shopping_category", type="string", nullable=true, example="Electronics"),
	 *             @OA\Property(property="google_shopping_mpn", type="string", nullable=true, example="MPN12345"),
	 *             @OA\Property(property="box_quantity", type="string", nullable=true, example="10"),
	 *             @OA\Property(property="store_id", type="string", example="5")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product added successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product created successfully"),
	 *             @OA\Property(property="product", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(response=401, description="Unauthorized")
	 * )
	 */
	public function store(Request $request)
	{
		$product = new Product();
		$product->name = $name;
		$product->description = !empty($description) ? $description : null;
		$product->content = !empty($content) ? $content : null;
		$product->warranty_information = !empty($warrantyInformation) ? $warrantyInformation : null;
		$product->sku = $sku;
		$product->status = $status;
		$product->delivery_days = !empty($deliveryDays) ? $deliveryDays : null;
		$product->is_featured = $isFeatured;
		$product->brand_id = $brandId;
		$product->images = json_encode($fetchedImages);
		$product->image = $fetchedImages[0] ?? null;
		$product->video_path = $uploadVideo;
		$product->stock_status = $stockStatus;
		$product->with_storehouse_management = $withStorehouseManagement;
		$product->unit_of_measurement_id = $unitOfMeasurementID;
		$product->quantity = !empty($quantity) ? $quantity : null;
		$product->cost_per_item = !empty($costPerItem) ? $costPerItem : null;
		$product->price = !empty($price) ? $price : null;
		$product->sale_price = !empty($salePrice) ? $salePrice : null;
		$product->start_date = !empty($startDateSalePrice) ? Carbon::parse($startDateSalePrice) : null;
		$product->end_date = !empty($endDateSalePrice) ? Carbon::parse($endDateSalePrice) : null;
		$product->sale_type = $saleType;
		$product->weight = !empty($weight) ? $weight : null;
		$product->weight_unit_id = $weightOption;
		$product->length = !empty($length) ? $length : null;
		$product->length_unit_id = $dimensionOption;
		$product->width = !empty($width) ? $width : null;
		$product->height = !empty($height) ? $height : null;
		$product->depth = !empty($depth) ? $depth : null;
		$product->shipping_weight_option = $shippingWeightOption;
		$product->shipping_weight = !empty($shippingWeight) ? $shippingWeight : null;
		$product->shipping_dimension_option = $shippingDimensionOption;
		$product->shipping_width = !empty($shippingWidth) ? $shippingWidth : null;
		$product->shipping_depth = !empty($shippingDepth) ? $shippingDepth : null;
		$product->shipping_height = !empty($shippingHeight) ? $shippingHeight : null;
		$product->shipping_length = !empty($shippingLength) ? $shippingLength : null;
		$product->frequently_bought_together = $frequentlyBoughtTogether;
		// $product->compare_type = !empty($compareType) ? $compareType : null;
		$product->compare_products = $compareProducts;
		$product->refund = $refundPolicy;
		$product->currency_id = 1;
		$product->variant_1_title = !empty($variant1Title) ? $variant1Title : null;
		$product->variant_1_value = !empty($variant1Value) ? $variant1Value : null;
		$product->variant_1_products = !empty($variant1Products) ? $variant1Products : null;
		$product->variant_2_title = !empty($variant2Title) ? $variant2Title : null;
		$product->variant_2_value = !empty($variant2Value) ? $variant2Value : null;
		$product->variant_2_products = !empty($variant2Products) ? $variant2Products : null;
		$product->variant_3_title = !empty($variant3Title) ? $variant3Title : null;
		$product->variant_3_value = !empty($variant3Value) ? $variant3Value : null;
		$product->variant_3_products = !empty($variant3Products) ? $variant3Products : null;
		$product->variant_color_title = !empty($variantColorTitle) ? $variantColorTitle : null;
		$product->variant_color_value = !empty($variantColorValue) ? $variantColorValue : null;
		$product->variant_color_products = !empty($variantColorProducts) ? $variantColorProducts : null;
		$product->barcode = !empty($barcode) ? $barcode : null;
		$product->minimum_order_quantity = !empty($minimumOrderQuantity) ? $minimumOrderQuantity : 0;
		$product->variant_requires_shipping = $variantRequiresShipping;
		$product->google_shopping_category = !empty($googleShoppingCategory) ? $googleShoppingCategory : null;
		$product->google_shopping_mpn = !empty($googleShoppingMpn) ? $googleShoppingMpn : null;
		$product->box_quantity = !empty($boxQuantity) ? $boxQuantity : null;

		$product->store_id = $storeId;
		$product->created_at = now();
		$product->updated_at = now();
		$product->created_by_id = $this->userId;
		$product->created_by_type = User::class;
		$product->save();
	}

	/**
	 * Display the specified resource.
	 */
	public function show(Product $product)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Product $product)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Product $product)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Product $product)
	{
		//
	}
}
