<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TrainingData;
use Illuminate\Http\Request;


class TrainingDataController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/training-data",
     *     summary="Get paginated training data with search and sorting",
     *     tags={"AI Training Data"},
     *     @OA\Parameter(name="search", in="query", description="Search by name, business_name, phone, transcript", required=false),
     *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort", required=false),
     *     @OA\Parameter(name="sort_order", in="query", description="Sort order asc/desc", required=false),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request)
    {
        $query = TrainingData::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('business_name', 'like', "%{$request->search}%")
                    ->orWhere('phone_number', 'like', "%{$request->search}%")
                    ->orWhere('transcript', 'like', "%{$request->search}%");
            });
        }

        if ($request->sort_by) {
            $query->orderBy($request->sort_by, $request->sort_order ?? 'asc');
        }

        return response()->json($query->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/api/training-data",
     *     summary="Create new training data",
     *     tags={"AI Training Data"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string"),
     *              @OA\Property(property="business_name", type="string"),
     *              @OA\Property(property="phone_number", type="string"),
     *              @OA\Property(property="quotation", type="boolean"),
     *              @OA\Property(property="call_summary", type="string"),
     *              @OA\Property(property="transcript", type="string"),
     *              @OA\Property(property="type", type="string"),
     *              @OA\Property(property="successful", type="boolean"),
     *              @OA\Property(property="zipcode", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'business_name' => 'nullable',
            'phone_number' => 'nullable',
            'quotation' => 'boolean',
            'call_summary' => 'nullable',
            'transcript' => 'nullable',
            'type' => 'nullable',
            'successful' => 'boolean',
            'zipcode' => 'nullable'
        ]);

        $training = TrainingData::create($data);
        return response()->json($training, 201);
    }


    /**
     * @OA\Get(
     *     path="/api/training-data/{id}",
     *     summary="Get training data by ID",
     *     tags={"AI Training Data"},
     *     @OA\Parameter(name="id", in="path", required=true),
     *     @OA\Response(response=200, description="Success"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        return TrainingData::findOrFail($id);
    }


    /**
     * @OA\Put(
     *     path="/api/training-data/{id}",
     *     summary="Update training data",
     *     tags={"AI Training Data"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              @OA\Property(property="name", type="string"),
     *              @OA\Property(property="business_name", type="string"),
     *              @OA\Property(property="phone_number", type="string"),
     *              @OA\Property(property="quotation", type="boolean"),
     *              @OA\Property(property="call_summary", type="string"),
     *              @OA\Property(property="transcript", type="string"),
     *              @OA\Property(property="type", type="string"),
     *              @OA\Property(property="successful", type="boolean"),
     *              @OA\Property(property="zipcode", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $training = TrainingData::findOrFail($id);
        $training->update($request->all());

        return response()->json($training);
    }

    /**
     * @OA\Delete(
     *     path="/api/training-data/{id}",
     *     summary="Delete training data",
     *     tags={"AI Training Data"},
     *     @OA\Parameter(name="id", in="path", required=true),
     *     @OA\Response(response=204, description="Deleted"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        TrainingData::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
