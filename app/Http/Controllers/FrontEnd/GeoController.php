<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
       /**
     * @OA\Get(
     *     path="/api/frontend/location-info",
     *     summary="Get location information by ZIP/postal code",
     *     tags={"Geolocation"},
     *     @OA\Parameter(
     *         name="zip",
     *         in="query",
     *         required=true,
     *         description="The ZIP or postal code to look up",
     *         @OA\Schema(type="string", example="122851")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with location details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="zip", type="string", example="122851"),
     *             @OA\Property(property="city", type="string", example="Dubai"),
     *             @OA\Property(property="state", type="string", nullable=true, example=null),
     *             @OA\Property(property="country", type="string", example="United Arab Emirates")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The zip field is required.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to fetch location info",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unable to fetch location")
     *         )
     *     )
     * )
     */

    public function getLocationInfo(Request $request)
    {
        $request->validate([
            'zip' => 'required|string',
        ]);

        $zip = $request->query('zip');
        $apiKey = config('services.google_maps.key'); // optional if using services.php
        $url = "https://maps.googleapis.com/maps/api/geocode/json";

        $response = Http::get($url, [
            'address' => $zip,
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Unable to fetch location'], 500);
        }

        $data = $response->json();
        $components = $data['results'][0]['address_components'] ?? [];

        $result = [
            'zip' => self::getComponent($components, 'postal_code'),
            'city' => self::getComponent($components, 'locality'),
            'state' => self::getComponent($components, 'administrative_area_level_1'),
            'country' => self::getComponent($components, 'country'),
        ];

        return response()->json($result);
    }

    private static function getComponent(array $components, string $type): ?string
    {
        foreach ($components as $component) {
            if (in_array($type, $component['types'])) {
                return $component['long_name'];
            }
        }
        return null;
    }

  public function addressAutocomplete(Request $request)
{
    $request->validate([
        'input' => 'required|string',
    ]);

    $input = $request->query('input');
    $apiKey = config('services.google_maps.key');

    // Step 1: Autocomplete API
    $autocompleteResponse = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
        'input' => $input,
        'key' => $apiKey,
        'types' => 'geocode',
        'components' => 'country:us',
    ]);

    if ($autocompleteResponse->failed()) {
        return response()->json(['error' => 'Failed to fetch autocomplete suggestions'], 500);
    }

    $autocompleteData = $autocompleteResponse->json();

    if (empty($autocompleteData['predictions'])) {
        return response()->json(['predictions' => []]);
    }

    $firstPrediction = $autocompleteData['predictions'][0];
    $placeId = $firstPrediction['place_id'];

    // Step 2: Place Details API for first suggestion
    $placeResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
        'place_id' => $placeId,
        'key' => $apiKey,
        'fields' => 'address_component,formatted_address',
    ]);

    if ($placeResponse->failed()) {
        return response()->json([
            'predictions' => $autocompleteData['predictions'],
            'details' => null,
            'error' => 'Failed to fetch place details'
        ], 500);
    }

    $placeData = $placeResponse->json()['result'] ?? [];
    $components = $placeData['address_components'] ?? [];

    $details = [
        'address' => $placeData['formatted_address'] ?? null,
        'zip' => $this->getComponent($components, 'postal_code'),
        'city' => $this->getComponent($components, 'locality'),
        'state' => $this->getComponent($components, 'administrative_area_level_1'),
        'country' => $this->getComponent($components, 'country'),
    ];

    return response()->json([
        'predictions' => $autocompleteData['predictions'],
        'details' => $details,
    ]);
}

}
