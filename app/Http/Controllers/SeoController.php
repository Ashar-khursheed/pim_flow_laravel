<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeoManagement;

class SeoController extends Controller
{
    public function renderWithSeo(Request $request, $any = null)
    {
        // Reuse your SEO fetching logic
        $seoResponse = app()->call('App\Http\Controllers\FrontEnd\SeoManagementController@getByRelationalId', [
            'identifier' => $request->path(), // current path
        ]);

        $seoData = $seoResponse->getData()->data[0] ?? null;

        // Render Blade view that bootstraps React
        return view('react-app', [
            'seoData' => $seoData,
        ]);
    }
}
