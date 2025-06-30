<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Validator;

class LookupController extends Controller
{
    public function lookup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
    
        if (!$sid || !$token) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio credentials are missing.'
            ], 500);
        }
    
        try {
            $twilio = new Client($sid, $token);
    
            $number = $twilio->lookups
                ->v1
                ->phoneNumbers($request->phone)
                ->fetch(["type" => ["carrier", "caller-name"]]);
    
            return response()->json([
                'success' => true,
                'message' => 'Phone number lookup successful.',
                'data' => [
                    'valid' => true,
                    'phone_number' => $number->phoneNumber,
                    'national_format' => $number->nationalFormat,
                    'carrier' => $number->carrier ?? null,
                    'caller_name' => $number->callerName['caller_name'] ?? null,
                ]
            ]);
    
        } catch (\Twilio\Exceptions\RestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio Lookup API error.',
                'error' => $e->getMessage()
            ], $e->getStatusCode() ?? 400);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
