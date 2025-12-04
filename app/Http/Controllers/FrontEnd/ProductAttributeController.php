<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductAttribute;
use App\Models\Product;
use OpenApi\Annotations as OA;

class ProductAttributeController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/frontend/product/{id}/nutrition-facts1",
     *     operationId="getNutritionFactsByProduct1",
     *     tags={"Frontend-Attribute Products"},
     *     summary="Get basic nutrition facts by product ID",
     *     description="Returns a list of nutrition facts under the 'Nutrition Facts Per Serving Group' attribute group for the given product ID.",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         description="ID of the product",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nutrition facts retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="attribute_value", type="string"),
     *                 @OA\Property(property="attribute_id", type="integer"),
     *                 @OA\Property(property="attribute", type="object")
     *             )
     *         )
     *     )
     * )
     */

    public function getNutritionFactsByProduct1($productId)
    {
        // Fetch attributes only under the "Nutrition Facts Per Serving Group"
        $productAttributes = ProductAttribute::with(['attributeDetails' => function ($query) {
            $query->whereHas('attributeGroup', function ($q) {
                $q->where('name', 'Nutrition Facts Per Serving Group');
            });
        }])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id']);

        // Filter to only include those with valid attribute relation
        $nutritionFacts = $productAttributes->filter(function ($item) {
            return $item->attributeDetails !== null;
        })->values();

        if ($nutritionFacts->isEmpty()) {
            return response()->json([
                'message' => 'Nutrition Facts Per Serving Group not found for this product.'
            ], 200);
        }

        return response()->json($nutritionFacts);
    }

    /**
     * @OA\Get(
     *     path="/api/product/{id}/nutrition-facts",
     *     operationId="getNutritionFactsByProduct",
     *     tags={"Frontend-Attribute Products"},
     *     summary="Get sorted nutrition facts by product ID",
     *     description="Returns a sorted list of nutrition facts for a product based on predefined keywords.",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         description="ID of the product",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sorted nutrition facts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="group_name", type="string"),
     *             @OA\Property(
     *                 property="attributes",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="value", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    //  public function getNutritionFactsByProduct($productId)
    //  {
    //      // Keyword-based sort order (lowercase)
    //      $sortKeywords = [
    //          'serving',
    //          'energy',
    //          'protein',
    //          'total fat',
    //          'trans fat',
    //          'fat',
    //          'saturated fat',
    //          'total carbohydrate',
    //          'carbohydrate',
    //          'sugars',
    //          'total sugars',
    //          'added sugars',
    //          'fiber',
    //          'dietary fiber',
    //          'sodium',
    //          'calories',
    //          'cholesterol',
    //          'vitamin a',
    //          'vitamin c',
    //          'vitamin d',
    //          'calcium',
    //          'iron',
    //          'potassium',
    //          'magnesium',
    //          'chloride',
    //          'fluoride',
    //          'nitrate',
    //          'bicarbonate',
    //          'carbonate',
    //          'sulfate',
    //          'ph',
    //          'tds',
    //          'salt',
    //          'caffeination'
    //      ];

    //      // Fetch product attributes in the group
    //      $productAttributes = ProductAttribute::with([
    //          'attribute' => function ($query) {
    //              $query->whereHas('attributeGroup', function ($q) {
    //                  $q->where('name', 'Nutrition Facts Per Serving Group');
    //              })->with('attributeGroup');
    //          },
    //          'measurementUnit'
    //      ])
    //      ->where('product_id', $productId)
    //      ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);

    //      // Filter out null attributes
    //      $nutritionFacts = $productAttributes->filter(function ($item) {
    //          return $item->attribute !== null;
    //      });

    //      if ($nutritionFacts->isEmpty()) {
    //          return response()->json([
    //              'message' => 'Nutrition Facts Per Serving Group not found for this product.'
    //          ], 200);
    //      }

    //      // Sort dynamically based on partial keyword match
    //      $sortedFacts = $nutritionFacts->sortBy(function ($item) use ($sortKeywords) {
    //          $name = strtolower($item->attribute->name);

    //          foreach ($sortKeywords as $index => $keyword) {
    //              $keywordParts = explode(' ', strtolower($keyword));

    //              foreach ($keywordParts as $part) {
    //                  if (strpos($name, $part) !== false) {
    //                      return $index;
    //                  }
    //              }
    //          }

    //          return count($sortKeywords) + 1; // Unknowns go to the end
    //      })->values();

    //      // Build flat response array
    //      $response = [];

    //      foreach ($sortedFacts as $item) {
    //          $name = $item->attribute->name;
    //          $value = trim($item->attribute_value . ' ' . ($item->measurementUnit->symbol ?? ''));

    //          // Add dash for sub-values (e.g., "Saturated Fat" under "Fat")
    //          $formattedName = preg_match('/^(saturated|trans|polyunsaturated|monounsaturated|sugar|fibre|fiber|added)/i', $name)
    //              ? "- $name"
    //              : $name;

    //          $response[] = "{$formattedName} {$value}";
    //      }

    //      // Optionally bring "Serving Size" to the top if it exists
    //      foreach ($response as $index => $line) {
    //          if (stripos($line, 'Serving Size') === 0) {
    //              $servingLine = $line;
    //              unset($response[$index]);
    //              array_unshift($response, $servingLine);
    //              break;
    //          }
    //      }

    //      return response()->json(array_values($response));
    //  }
    public function getNutritionFactsByProduct($productId)
    {
        $sortKeywords = [
            'serving size', 'energy', 'fat', 'saturated fat', 'trans fat', 'polyunsaturated fat', 'monounsaturated fat',
            'cholesterol', 'carbohydrates', 'sugar', 'fibre', 'fiber', 'added sugar', 'protein', 'sodium', 'salt',
            'caffeination', 'vitamin a', 'vitamin c', 'vitamin d', 'calcium', 'iron', 'potassium', 'magnesium', 'chloride',
            'fluoride', 'nitrate', 'bicarbonate', 'carbonate', 'sulfate', 'ph', 'tds'
        ];

        $groups = [
            'Fat' => ['Saturated Fat', 'Trans Fat', 'Polyunsaturated Fat', 'Monounsaturated Fat'],
            'Carbohydrates' => ['Sugar', 'Fibre', 'Fiber', 'Added Sugar'],
        ];

        $productAttributes = ProductAttribute::with([
            'attributeDetails' => function ($query) {
                $query->whereHas('attributeGroup', function ($q) {
                    $q->where('name', 'Nutrition Facts Per Serving Group');
                })->with('attributeGroup');
            },
            'measurementUnit'
        ])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);

        $nutritionFacts = $productAttributes->filter(function ($item) {
            return $item->attributeDetails !== null;
        });

        if ($nutritionFacts->isEmpty()) {
            return response()->json([
                'message' => 'Nutrition Facts Per Serving Group not found for this product.'
            ], 200);
        }

        $items = [];
        $grouped = [];

        foreach ($nutritionFacts as $item) {
            $name = trim($item->attributeDetails->name);
            $value = trim($item->attribute_value . ' ' . ($item->measurementUnit->symbol ?? ''));

            $isChild = false;
            foreach ($groups as $parent => $children) {
                foreach ($children as $child) {
                    if (strcasecmp($name, $child) === 0) {
                        $grouped[$parent]['children'][] = [
                            'name' => $child,
                            'value' => $value
                        ];
                        $isChild = true;
                        break 2;
                    }
                }
            }

            if (!$isChild) {
                $grouped[$name]['value'] = $value;
            }
        }

        // Transform grouped data into array format
        foreach ($grouped as $key => $entry) {
            $data = [
                'name' => $key,
                'value' => $entry['value'] ?? ''
            ];

            if (isset($entry['children'])) {
                $data['children'] = $entry['children'];
            }

            $items[] = $data;
        }

        // Sort based on sortKeywords order
        usort($items, function ($a, $b) use ($sortKeywords) {
            $aIndex = array_search(strtolower($a['name']), $sortKeywords);
            $bIndex = array_search(strtolower($b['name']), $sortKeywords);

            $aIndex = $aIndex === false ? 999 : $aIndex;
            $bIndex = $bIndex === false ? 999 : $bIndex;

            return $aIndex <=> $bIndex;
        });

        return response()->json($items);
    }



    // public function getNutritionFactsByProduct($productId)
    // {
    //     // Keyword-based sort order (lowercase)
    //     $sortKeywords = [
    //         'serving',
    //         'energy',
    //         'protein',
    //         'total fat',
    //         'trans fat',
    //         'fat',
    //         'saturated fat',
    //         'total carbohydrate',
    //         'carbohydrate',
    //         'sugars',
    //         'total sugars',
    //         'added sugars',
    //         'fiber',
    //         'dietary fiber',
    //         'sodium',
    //         'calories',
    //         'cholesterol',
    //         'vitamin d',
    //         'calcium',
    //         'iron',
    //         'potassium'
    //     ];

    //     // Fetch product attributes in the group
    //     $productAttributes = ProductAttribute::with([
    //         'attribute' => function ($query) {
    //             $query->whereHas('attributeGroup', function ($q) {
    //                 $q->where('name', 'Nutrition Facts Per Serving Group');
    //             })->with('attributeGroup');
    //         },
    //         'measurementUnit'
    //     ])
    //     ->where('product_id', $productId)
    //     ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);

    //     // Filter out null attributes
    //     $nutritionFacts = $productAttributes->filter(function ($item) {
    //         return $item->attribute !== null;
    //     });

    //     if ($nutritionFacts->isEmpty()) {
    //         return response()->json([
    //             'message' => 'Nutrition Facts Per Serving Group not found for this product.'
    //         ], 200);
    //     }

    //     // Sort dynamically based on partial keyword match
    //     $sortedFacts = $nutritionFacts->sortBy(function ($item) use ($sortKeywords) {
    //         $name = strtolower($item->attribute->name);

    //         foreach ($sortKeywords as $index => $keyword) {
    //             $keywordParts = explode(' ', strtolower($keyword));

    //             foreach ($keywordParts as $part) {
    //                 if (strpos($name, $part) !== false) {
    //                     return $index;
    //                 }
    //             }
    //         }

    //         return count($sortKeywords) + 1; // Unknowns go to the end
    //     })->values();

    //     // Build the final response
    //     $response = [
    //         'group_name' => $sortedFacts[0]->attribute->attributeGroup->name ?? 'Nutrition Facts Per Serving Group',
    //         'attributes' => $sortedFacts->map(function ($item) {
    //             $symbol = $item->measurementUnit->symbol ?? '';
    //             return [
    //                 'name'  => $item->attribute->name,
    //                 'value' => trim($item->attribute_value . ' ' . $symbol),
    //             ];
    //         })
    //     ];

    //     return response()->json($response);
    // }



    /**
     * @OA\Get(
     *     path="/api/frontend/product/{productId}/attributes",
     *     operationId="getAttributesByProduct",
     *     tags={"Frontend-Attribute Products"},
     *     summary="Get non-nutrition attributes sorted into left/right layout",
     *     description="Returns product attributes not in 'Nutrition Facts Per Serving Group', organized into left and right sections.",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         description="ID of the product",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attributes retrieved and sorted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="left",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="attribute_name", type="string"),
     *                     @OA\Property(property="attribute_value", type="string")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="right",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="attribute_name", type="string"),
     *                     @OA\Property(property="attribute_value", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    // public function getAttributesByProduct($productId)
    // {
    //     $productAttributes = ProductAttribute::with(['attribute' => function ($query) {
    //         $query->whereHas('attributeGroup', function ($q) {
    //             $q->where('name', '!=', 'Nutrition Facts Per Serving Group');
    //         });
    //     }])
    //     ->where('product_id', $productId)
    //     ->get(['attribute_value', 'attribute_id']);

    //     // Filter out null attributes
    //     $filteredAttributes = $productAttributes->filter(function ($item) {
    //         return $item->attribute !== null;
    //     })->values();

    //     // Define fixed order
    //     $leftOrder = [
    //         'Sku / Item Code',
    //         'Manufacturer',
    //         'Country of Origin',
    //         'Material',
    //         'Color',
    //         'Capacity',
    //         'Width',
    //         'Depth',
    //         'Height'
    //     ];

    //     $rightOrder = [
    //         'Type',
    //         'Pack Type',
    //         'Selling Unit',
    //         'Warranty',
    //         'Certification',
    //         'Features'
    //     ];

    //     $left = [];
    //     $right = [];
    //     $usedNames = [];

    //     // Helper: format item
    //     $formatAttr = function ($item) {
    //         return [
    //             'attribute_name' => $item->attribute->name,
    //             'attribute_value' => $item->attribute_value,
    //         ];
    //     };

    //     // Add left ordered attributes
    //     foreach ($leftOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $left[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Add right ordered attributes
    //     foreach ($rightOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $right[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Get remaining attributes
    //     $remaining = $filteredAttributes->filter(function ($item) use ($usedNames) {
    //         return !in_array($item->attribute->name, $usedNames);
    //     })->map($formatAttr)->values();

    //     // Balance total count between left and right
    //     $totalLeft = count($left);
    //     $totalRight = count($right);

    //     foreach ($remaining as $item) {
    //         if ($totalLeft <= $totalRight) {
    //             $left[] = $item;
    //             $totalLeft++;
    //         } else {
    //             $right[] = $item;
    //             $totalRight++;
    //         }
    //     }

    //     return response()->json([
    //         'left' => $left,
    //         'right' => $right
    //     ]);
    // }
    // public function getAttributesByProduct($productId)
    // {
    //     // Fetch product attributes with their attribute and measurement unit
    //     $productAttributes = ProductAttribute::with([
    //         'attribute' => function ($query) {
    //             $query->whereHas('attributeGroup', function ($q) {
    //                 $q->whereNotIn('name', ['Nutrition Facts Per Serving Group', 'Ingredients']);
    //             });
    //         },
    //         'measurementUnit'
    //     ])
    //     ->where('product_id', $productId)
    //     ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);

    //     // Filter out attributes where attribute relation is null
    //     $filteredAttributes = $productAttributes->filter(function ($item) {
    //         return $item->attribute !== null;
    //     })->values();

    //     // Clone before hiding for use in Inside Carton logic
    //     $allAttributes = clone $filteredAttributes;

    //     // Now hide attributes by name from the output
    //     // $filteredAttributes = $filteredAttributes->reject(function ($item) {
    //     //     return in_array($item->attribute->name, [
    //     //         'Unit of Measurement',
    //     //         'Unit Qty',
    //     //         'Units per Case',
    //     //         'Pack Type',
    //     //         'Ingredients'
    //     //     ]);
    //     // })->values();
    //       $filteredAttributes = $filteredAttributes->reject(function ($item) {
    //             $appWebsite = env('APP_WEBSITE');

    //             // Always reject these
    //             $attributesToReject = [
    //                 'Unit of Measurement',
    //                 'Unit Qty',
    //                 'Pack Type',
    //                 'Ingredients'
    //             ];

    //             // Conditionally reject 'Units per Case' if APP_WEBSITE is not 'US'
    //             if ($appWebsite !== 'US') {
    //                 $attributesToReject[] = 'Units per Case';
    //             }

    //             return in_array($item->attribute->name, $attributesToReject);
    //         })->values();


    //     // Define fixed order
    //     $leftOrder = [
    //         'Sku / Item Code',
    //         'Manufacturer',
    //         'Country of Origin',
    //         'Material',
    //         'Color',
    //         'Capacity',
    //         'Width',
    //         'Depth',
    //         'Height'
    //     ];

    //     $rightOrder = [
    //         'Inside Carton',
    //         'Selling Unit',
    //         'Units per Case',
    //         'Type',
    //         'Warranty',
    //         'Certification',
    //         'Features'
    //     ];

    //     $left = [];
    //     $right = [];
    //     $usedNames = [];

    //     // Helper to format attribute
    //     $formatAttr = function ($item) {
    //         $value = $item->attribute_value;
    //         if ($item->measurementUnit && $item->measurement_unit_id) {
    //             $value .= ' ' . $item->measurementUnit->symbol;
    //         }

    //         return [
    //             'attribute_name' => $item->attribute->name,
    //             'attribute_value' => $value,
    //         ];
    //     };

    //     // Add left ordered attributes
    //     foreach ($leftOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $left[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Add right ordered attributes
    //     foreach ($rightOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $right[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Get remaining attributes
    //     $remaining = $filteredAttributes->filter(function ($item) use ($usedNames) {
    //         return !in_array($item->attribute->name, $usedNames);
    //     })->map($formatAttr)->values();

    //     // Balance left and right
    //     $totalLeft = count($left);
    //     $totalRight = count($right);

    //     foreach ($remaining as $item) {
    //         if ($totalLeft <= $totalRight) {
    //             $left[] = $item;
    //             $totalLeft++;
    //         } else {
    //             $right[] = $item;
    //             $totalRight++;
    //         }
    //     }

    //     // // Inside Carton calculation using the original (not filtered) attributes
    //     // $unitsPerCase = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Units per Case');
    //     // $unitSelling = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Selling Unit');
    //     // $packType = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Pack Type');
    //     // $unitQty = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit Qty');
    //     // $unitMeasurement = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit of Measurement');

    //     // if ($unitsPerCase && $packType && $unitQty && $unitMeasurement) {
    //     //     $insideCartonValue = $unitsPerCase->attribute_value . ' ' . $packType->attribute_value . ' x ' . $unitQty->attribute_value . $unitMeasurement->attribute_value;

    //     //     // Insert at second position (index 1)
    //     //     array_splice($right, 1, 0, [[
    //     //         'attribute_name' => 'Inside Carton',
    //     //         'attribute_value' => $insideCartonValue,
    //     //     ]]);
    //     // }
    //     $sellingUnit     = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Selling Unit');
    //     $unitsPerCase    = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Units per Case');
    //     $packType        = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Pack Type');
    //     $unitQty         = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit Qty');
    //     $unitMeasurement = $allAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit of Measurement');

    //     $rawSelling = $sellingUnit?->attribute_value;

    //     $hasAllValues =
    //         $sellingUnit && !empty($rawSelling) &&
    //         $unitsPerCase && !empty($unitsPerCase->attribute_value) &&
    //         $packType && !empty($packType->attribute_value) &&
    //         $unitQty && !empty($unitQty->attribute_value) &&
    //         $unitMeasurement && !empty($unitMeasurement->attribute_value);

    //     // First, always remove original Selling Unit if it exists
    //     $right = collect($right)->filter(fn($item) =>
    //         strtolower($item['attribute_name']) !== 'selling unit'
    //     )->values()->toArray();

    //     // If all required values are present, show the custom format
    //     if ($hasAllValues) {
    //         $parsedSelling = preg_replace('#/#', ' ', $rawSelling);

    //         $insideCarton = $unitsPerCase->attribute_value . ' ' .
    //                         $packType->attribute_value . ' x ' .
    //                         $unitQty->attribute_value . ' ' .
    //                         $unitMeasurement->attribute_value . ' Each';

    //         $fullValue = $parsedSelling . ' (' . $insideCarton . ')';

    //         array_splice($right, 0, 0, [[
    //             'attribute_name'  => 'Selling Unit',
    //             'attribute_value' => $fullValue,
    //         ]]);
    //     }
    //     // If not all present, but Selling Unit exists, add original
    //     elseif (!empty($rawSelling)) {
    //         array_splice($right, 0, 0, [[
    //             'attribute_name'  => 'Selling Unit',
    //             'attribute_value' => $rawSelling,
    //         ]]);
    //     }




    //     return response()->json([
    //         'left' => $left,
    //         'right' => $right
    //     ]);
    // }
    public function getAttributesByProduct($productInput)
    {
        // Resolve product ID from slug or direct ID
        if (is_numeric($productInput)) {
            $productId = (int) $productInput;
        } else {
            $product = Product::whereHas('seoUrl', function ($q) use ($productInput) {
                $q->where('url', $productInput);
            })->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found by slug.',
                ], 404);
            }

            $productId = $product->id;
        }

        // Fetch product attributes with their attribute and measurement unit
        $productAttributes = ProductAttribute::with([
            'attributeDetails' => function ($query) {
                $query->whereHas('attributeGroup', function ($q) {
                    $q->whereNotIn('name', ['Nutrition Facts Per Serving Group', 'Ingredients']);
                });
            },
            'measurementUnit'
        ])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);

        // Filter out attributes where attribute relation is null
        $filteredAttributes = $productAttributes->filter(function ($item) {
            return $item->attributeDetails !== null;
        })->values();

        // Clone before hiding for use in Inside Carton logic
        $allAttributes = clone $filteredAttributes;

        // Filter out based on attribute name and APP_WEBSITE
        $filteredAttributes = $filteredAttributes->reject(function ($item) {
            $appWebsite = env('APP_WEBSITE');

            $attributesToReject = [
                'Unit of Measurement',
                'Unit Qty',
                'Pack Type',
                'Ingredients'
            ];

            if ($appWebsite !== 'US') {
                $attributesToReject[] = 'Units per Case';
            }

            return in_array($item->attributeDetails->name, $attributesToReject);
        })->values();

        $leftOrder = [
            'Sku / Item Code',
            'Manufacturer',
            'Country of Origin',
            'Material',
            'Color',
            'Capacity',
            'Width',
            'Depth',
            'Height'
        ];

        $rightOrder = [
            'Inside Carton',
            'Selling Unit',
            'Units per Case',
            'Type',
            'Warranty',
            'Certification',
            'Features'
        ];

        $left = [];
        $right = [];
        $usedNames = [];

        $formatAttr = function ($item) {
            $value = $item->attribute_value;
            if ($item->measurementUnit && $item->measurement_unit_id) {
                $value .= ' ' . $item->measurementUnit->symbol;
            }

            return [
                'attribute_name' => $item->attributeDetails->name,
                'attribute_value' => $value,
            ];
        };

        foreach ($leftOrder as $name) {
            $match = $filteredAttributes->firstWhere(fn($item) => $item->attributeDetails->name === $name);
            if ($match) {
                $left[] = $formatAttr($match);
                $usedNames[] = $name;
            }
        }

        foreach ($rightOrder as $name) {
            $match = $filteredAttributes->firstWhere(fn($item) => $item->attributeDetails->name === $name);
            if ($match) {
                $right[] = $formatAttr($match);
                $usedNames[] = $name;
            }
        }

        $remaining = $filteredAttributes->filter(function ($item) use ($usedNames) {
            return !in_array($item->attributeDetails->name, $usedNames);
        })->map($formatAttr)->values();

        $totalLeft = count($left);
        $totalRight = count($right);

        foreach ($remaining as $item) {
            if ($totalLeft <= $totalRight) {
                $left[] = $item;
                $totalLeft++;
            } else {
                $right[] = $item;
                $totalRight++;
            }
        }

        $sellingUnit     = $allAttributes->firstWhere(fn($item) => $item->attributeDetails->name === 'Selling Unit');
        $unitsPerCase    = $allAttributes->firstWhere(fn($item) => $item->attributeDetails->name === 'Units per Case');
        $packType        = $allAttributes->firstWhere(fn($item) => $item->attributeDetails->name === 'Pack Type');
        $unitQty         = $allAttributes->firstWhere(fn($item) => $item->attributeDetails->name === 'Unit Qty');
        $unitMeasurement = $allAttributes->firstWhere(fn($item) => $item->attributeDetails->name === 'Unit of Measurement');

        $rawSelling = $sellingUnit?->attribute_value;

        $hasAllValues =
            $sellingUnit && !empty($rawSelling) &&
            $unitsPerCase && !empty($unitsPerCase->attribute_value) &&
            $packType && !empty($packType->attribute_value) &&
            $unitQty && !empty($unitQty->attribute_value) &&
            $unitMeasurement && !empty($unitMeasurement->attribute_value);

        $right = collect($right)->filter(fn($item) =>
            strtolower($item['attribute_name']) !== 'selling unit'
        )->values()->toArray();

        if ($hasAllValues) {
            $parsedSelling = preg_replace('#/#', ' ', $rawSelling);

            $insideCarton = $unitsPerCase->attribute_value . ' ' .
                            $packType->attribute_value . ' x ' .
                            $unitQty->attribute_value . ' ' .
                            $unitMeasurement->attribute_value . ' Each';

            $fullValue = $parsedSelling . ' (' . $insideCarton . ')';

            array_splice($right, 0, 0, [[
                'attribute_name'  => 'Selling Unit',
                'attribute_value' => $fullValue,
            ]]);
        } elseif (!empty($rawSelling)) {
            array_splice($right, 0, 0, [[
                'attribute_name'  => 'Selling Unit',
                'attribute_value' => $rawSelling,
            ]]);
        }

        return response()->json([
            'left' => $left,
            'right' => $right
        ]);
    }


    // public function getAttributesByProduct($productId)
    // {
    //     // $productAttributes = ProductAttribute::with(['attribute' => function ($query) {
    //     //     $query->whereHas('attributeGroup', function ($q) {
    //     //         $q->where('name', '!=', 'Nutrition Facts Per Serving Group');
    //     //     });
    //     // }])
    //     // ->where('product_id', $productId)
    //     // ->get(['attribute_value', 'attribute_id']);

    //     $productAttributes = ProductAttribute::with([
    //         'attribute' => function ($query) {
    //             $query->whereHas('attributeGroup', function ($q) {
    //                 $q->whereNotIn('name', ['Nutrition Facts Per Serving Group', 'Ingredients']);
    //             });

    //         },
    //         'measurementUnit'
    //     ])
    //     ->where('product_id', $productId)
    //     ->get(['attribute_value', 'attribute_id', 'measurement_unit_id' ]);

    //     // Filter out null attributes
    //     $filteredAttributes = $productAttributes->filter(function ($item) {
    //         return $item->attribute !== null;
    //     })->values();

    //     // Define fixed order
    //     $leftOrder = [
    //         'Sku / Item Code',
    //         'Manufacturer',
    //         'Country of Origin',
    //         'Material',
    //         'Color',
    //         'Capacity',
    //         'Width',
    //         'Depth',
    //         'Height'
    //     ];

    //     $rightOrder = [
    //         'Type',
    //         'Pack Type',
    //         'Selling Unit',
    //         'Warranty',
    //         'Certification',
    //         'Features'
    //     ];

    //     $left = [];
    //     $right = [];
    //     $usedNames = [];

    //     // Helper: format item
    //     // $formatAttr = function ($item) {
    //     //     return [
    //     //         'attribute_name' => $item->attribute->name,
    //     //         'attribute_value' => $item->attribute_value,
    //     //     ];
    //     // };

    //     $formatAttr = function ($item) {
    //         $value = $item->attribute_value;
    //         if ($item->measurementUnit && $item->measurement_unit_id) {
    //             $value .= ' ' . $item->measurementUnit->symbol;
    //         }

    //         return [
    //             'attribute_name' => $item->attribute->name,
    //             'attribute_value' => $value,
    //         ];
    //     };


    //     // Add left ordered attributes
    //     foreach ($leftOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $left[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Add right ordered attributes
    //     foreach ($rightOrder as $name) {
    //         $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
    //         if ($match) {
    //             $right[] = $formatAttr($match);
    //             $usedNames[] = $name;
    //         }
    //     }

    //     // Get remaining attributes
    //     $remaining = $filteredAttributes->filter(function ($item) use ($usedNames) {
    //         return !in_array($item->attribute->name, $usedNames);
    //     })->map($formatAttr)->values();

    //     // Balance total count between left and right
    //     $totalLeft = count($left);
    //     $totalRight = count($right);

    //     foreach ($remaining as $item) {
    //         if ($totalLeft <= $totalRight) {
    //             $left[] = $item;
    //             $totalLeft++;
    //         } else {
    //             $right[] = $item;
    //             $totalRight++;
    //         }
    //     }
    //     // Inside Carton calculation
    //    // Inside Carton calculation
    //     $unitsPerCase = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === 'Units per Case');
    //     $packType = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === 'Pack Type');
    //     $unitQty = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit Qty');
    //     $unitMeasurement = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === 'Unit of Measurement');

    //     if ($unitsPerCase && $packType && $unitQty && $unitMeasurement) {
    //         $insideCartonValue = $unitsPerCase->attribute_value . ' ' . $packType->attribute_value . ' x ' . $unitQty->attribute_value . $unitMeasurement->attribute_value;

    //         $right[] = [
    //             'attribute_name' => 'Inside Carton',
    //             'attribute_value' => $insideCartonValue,
    //         ];
    //     }



    //     return response()->json([
    //         'left' => $left,
    //         'right' => $right
    //     ]);
    // }

   /**
     * @OA\Get(
     *     path="/api/frontend/product-group/{productId}/attributes",
     *     operationId="getAttributesByProductWithGroup",
     *     tags={"Frontend-Attribute Products"},
     *     summary="Get all attributes grouped by attribute group",
     *     description="Returns all product attributes grouped by their attribute group (section).",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         description="ID of the product",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grouped attributes retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="section", type="string"),
     *                 @OA\Property(
     *                     property="specs",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="value", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

     public function getAttributesByProductWithGroup($productId)
    {
        // Get product attributes with related attribute and attribute group
        $productAttributes = ProductAttribute::with(['attributeDetails.attributeGroup'])
            ->where('product_id', $productId)
            ->get();

        // Grouping logic
        $groupedAttributes = [];

        foreach ($productAttributes as $productAttribute) {
            $attribute = $productAttribute->attributeDetails;
            if (!$attribute) continue;

            $groupName = $attribute->attributeGroup->name ?? 'Other';

            $groupedAttributes[$groupName][] = [
                'name'  => $attribute->name,
                'value' => $productAttribute->attribute_value,
            ];
        }

        // Formatting final response
        $formatted = [];
        foreach ($groupedAttributes as $section => $specs) {
            $formatted[] = [
                'section' => $section,
                'specs' => $specs,
            ];
        }

        return response()->json($formatted);
    }





}
