<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="PIM flow API Documentation",
 *     version="1.0.0",
 *     description="APIs documentation of PIM flow",
 *     @OA\Contact(
 *         email="sysadmin@horecastore.org"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="https://www.apache.org/licenses/LICENSE-2.0.html"
 *     ),
 *     x={
 *         "logo": {
 *             "url": "https://via.placeholder.com/190x90.png?text=L5-Swagger"
 *         }
 *     }
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="To generate Json Web Token"
 * )
 *
 * @OA\Tag(
 *     name="Attributes",
 *     description="API Endpoints for Attribute Management"
 * )
 *
 * @OA\Tag(
 *     name="Attribute Group",
 *     description="API Endpoints for Attribute Group Management"
 * )
 *
 * @OA\Tag(
 *     name="Category Attribute Group",
 *     description="API Endpoints for Managing Category Attributes and Groups"
 * )
 *
 * @OA\Tag(
 *     name="Products",
 *     description="API Endpoints for Product Management"
 * )
 *
 * @OA\Tag(
 *     name="Product Attributes",
 *     description="API Endpoints for Product Attributes Management"
 * )
 *
 *  @OA\Tag(
 *     name="Products Report",
 *     description="API Endpoints for Product Report Management"
 * )
 *	@OA\Tag(
 *     name="Products AI alternates",
 *     description="API Endpoints for Products AI alternates"
 * )
 *	@OA\Tag(
 *     name="Product Accessories",
 *     description="API Endpoints for Product Accessories"
 * )
 *	@OA\Tag(
 *     name="Made To Orders",
 *     description="API Endpoints for Made To Orders"
 * )
 *	@OA\Tag(
 *     name="Delivery Payment History",
 *     description="Get list of Delivery Payment History"
 * )
 * @OA\Tag(
 *     name="Product Variants",
 *     description="API Endpoints for managing product variants"
 * )
 * @OA\Tag(
 *     name="Product Feed XML",
 *     description="API Endpoints for managing product XML Data FeedWatch"
 * )
 * @OA\Tag(
 *     name="Finance",
 *     description="API Endpoints for Finance Management"
 * )
 * @OA\Tag(
 *     name="Categories",
 *     description="API Endpoints for Category Management"
 * )
 * @OA\Tag(
 *     name="Customers",
 *     description="API Endpoints for Customers"
 * )
 *
 * @OA\Tag(
 *     name="Transaction Logs",
 *     description="API Endpoints for Transaction Logs Management"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     in="header",
 *     name="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 * )
 */

class BaseController extends Controller
{
	// use ResponseTrait;
}
