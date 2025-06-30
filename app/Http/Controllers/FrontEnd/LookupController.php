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
            return response()->json(['error' => 'Phone number is required.'], 400);
        }

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $twilio = new Client($sid, $token);

        try {
            $number = $twilio->lookups
                ->v1
                ->phoneNumbers($request->phone)
                ->fetch(["type" => ["carrier", "caller-name"]]); // Add/remove types as needed

            return response()->json([
                'valid' => true,
                'phone_number' => $number->phoneNumber,
                'national_format' => $number->nationalFormat,
                'carrier' => $number->carrier ?? null,
                'caller_name' => $number->callerName['caller_name'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
