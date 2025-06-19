<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FrontEnd\ProductAttributes;
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
        $productAttributes = ProductAttributes::with(['attribute' => function ($query) {
            $query->whereHas('attributeGroup', function ($q) {
                $q->where('name', 'Nutrition Facts Per Serving Group');
            });
        }])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id']);

        // Filter to only include those with valid attribute relation
        $nutritionFacts = $productAttributes->filter(function ($item) {
            return $item->attribute !== null;
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

    public function getNutritionFactsByProduct($productId)
    {
        // Keyword-based sort order (lowercase)
        $sortKeywords = [
            'serving',
            'calories',
            'total fat',
            'saturated fat',
            'trans fat',
            'cholesterol',
            'sodium',
            'total carbohydrate',
            'dietary fiber',
            'total sugars',
            'added sugars',
            'protein',
            'vitamin d',
            'calcium',
            'iron',
            'potassium'
        ];

        // Fetch product attributes in the group
        $productAttributes = ProductAttributes::with(['attribute' => function ($query) {
            $query->whereHas('attributeGroup', function ($q) {
                $q->where('name', 'Nutrition Facts Per Serving Group');
            })->with('attributeGroup');
        }])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id']);

        // Filter out null attributes
        $nutritionFacts = $productAttributes->filter(function ($item) {
            return $item->attribute !== null;
        });

        if ($nutritionFacts->isEmpty()) {
            return response()->json([
                'message' => 'Nutrition Facts Per Serving Group not found for this product.'
            ], 200);
        }

        // Sort dynamically based on keyword order
        $sortedFacts = $nutritionFacts->sortBy(function ($item) use ($sortKeywords) {
            $name = strtolower($item->attribute->name);
            foreach ($sortKeywords as $index => $keyword) {
                if (strpos($name, $keyword) !== false) {
                    return $index;
                }
            }
            return count($sortKeywords) + 1; // Unknown attributes go to end
        })->values();

        // Build the final response
        $response = [
            'group_name' => $sortedFacts[0]->attribute->attributeGroup->name ?? 'Nutrition Facts Per Serving Group',
            'attributes' => $sortedFacts->map(function ($item) {
                return [
                    'name'  => $item->attribute->name,
                    'value' => $item->attribute_value,
                    'unit'  => $item->measurementUnit->symbol ?? null  // 👈 unit name

                ];
            })
        ];

        return response()->json($response);
    }
 

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
    //     $productAttributes = ProductAttributes::with(['attribute' => function ($query) {
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
    public function getAttributesByProduct($productId)
    {
        // $productAttributes = ProductAttributes::with(['attribute' => function ($query) {
        //     $query->whereHas('attributeGroup', function ($q) {
        //         $q->where('name', '!=', 'Nutrition Facts Per Serving Group');
        //     });
        // }])
        // ->where('product_id', $productId)
        // ->get(['attribute_value', 'attribute_id']);
    
        // $productAttributes = ProductAttributes::with([
        //     'attribute' => function ($query) {
        //         $query->whereHas('attributeGroup', function ($q) {
        //             $q->where('name', '!=', 'Nutrition Facts Per Serving Group');
        //         });
        //     },
        //     'measurementUnit'
        // ])
        // $productAttributes = ProductAttributes::with([
        //     'attribute' => function ($query) {
        //         $query->whereHas('attributeGroup', function ($q) {
        //             $q->where('name', 'Nutrition Facts Per Serving Group');
        //         })->with('attributeGroup');
        //     },
        //     'measurementUnit' // 👈 eager load unit
        // ])
        // ->where('product_id', $productId)
        // ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']);
        $productAttributes = ProductAttributes::with([
            'attribute' => function ($query) {
                $query->whereHas('attributeGroup', function ($q) {
                    $q->where('name', 'Nutrition Facts Per Serving Group');
                })->with('attributeGroup');
            },
            'measurementUnit' // 👈 ADD THIS LINE
        ])
        ->where('product_id', $productId)
        ->get(['attribute_value', 'attribute_id', 'measurement_unit_id']); // 👈 Include unit ID
        
        
    
        // Filter out null attributes
        $filteredAttributes = $productAttributes->filter(function ($item) {
            return $item->attribute !== null;
        })->values();
    
        // Define fixed order
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
            'Type',
            'Pack Type',
            'Selling Unit',
            'Warranty',
            'Certification',
            'Features'
        ];
    
        $left = [];
        $right = [];
        $usedNames = [];
    
        // Helper: format item
        // $formatAttr = function ($item) {
        //     return [
        //         'attribute_name' => $item->attribute->name,
        //         'attribute_value' => $item->attribute_value,
        //     ];
        // };
    
        $formatAttr = function ($item) {
            $value = $item->attribute_value;
            if ($item->measurementUnit && $item->measurement_unit_id) {
                $value .= ' ' . $item->measurementUnit->symbol;
            }
        
            return [
                'attribute_name' => $item->attribute->name,
                'attribute_value' => $value,
            ];
        };
        
    
        // Add left ordered attributes
        foreach ($leftOrder as $name) {
            $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
            if ($match) {
                $left[] = $formatAttr($match);
                $usedNames[] = $name;
            }
        }
    
        // Add right ordered attributes
        foreach ($rightOrder as $name) {
            $match = $filteredAttributes->firstWhere(fn($item) => $item->attribute->name === $name);
            if ($match) {
                $right[] = $formatAttr($match);
                $usedNames[] = $name;
            }
        }
    
        // Get remaining attributes
        $remaining = $filteredAttributes->filter(function ($item) use ($usedNames) {
            return !in_array($item->attribute->name, $usedNames);
        })->map($formatAttr)->values();
    
        // Balance total count between left and right
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
    
        return response()->json([
            'left' => $left,
            'right' => $right
        ]);
    }

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
        $productAttributes = ProductAttributes::with(['attribute.attributeGroup'])
            ->where('product_id', $productId)
            ->get();

        // Grouping logic
        $groupedAttributes = [];

        foreach ($productAttributes as $productAttribute) {
            $attribute = $productAttribute->attribute;
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

   



    // public function getAttributesByProductWithGroup($productId)
    // {
    //         $productAttributes = ProductAttributes::with(['attribute.attributeGroups'])
    //         ->where('product_id', $productId)
    //         ->get();

    //     $groupedAttributes = [];

    //     foreach ($productAttributes as $productAttribute) {
    //         $attribute = $productAttribute->attribute;

    //         if (!$attribute || $attribute->attributeGroups->isEmpty()) {
    //             $groupName = 'Other';
    //         } else {
    //             // If attribute belongs to multiple groups, pick the first
    //             $groupName = $attribute->attributeGroups->first()->name;
    //         }

    //         $groupedAttributes[$groupName][] = [
    //             'name' => $attribute->name,
    //             'value' => $productAttribute->attribute_value,
    //         ];
    //     }

    //     // Final formatting
    //     $formatted = [];
    //     foreach ($groupedAttributes as $section => $specs) {
    //         $formatted[] = [
    //             'section' => $section,
    //             'specs' => $specs,
    //         ];
    //     }

    //     return response()->json($formatted);
    // }
}
