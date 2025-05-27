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
 *     name="Transaction Logs",
 *     description="API Endpoints for Transaction Logs Management"
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
 *     name="Categories",
 *     description="API Endpoints for Category Management"
 * )


*
* @OA\SecurityScheme(
*    securityScheme="bearerAuth",
*    in="header",
*    name="bearerAuth",
*    type="http",
*    scheme="bearer",
*    bearerFormat="JWT",
* )
*/

class BaseController extends Controller
{
	// use ResponseTrait;
}
