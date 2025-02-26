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
 *     name="JWT",
 *     description="To generate Json Web Token"
 * )
 */
class BaseController extends Controller
{
	// use ResponseTrait;
}
