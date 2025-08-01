<?php
namespace App\Http\Controllers;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function generate()
    {
        $sitemap = Sitemap::create();

        // Add static URLs
        $sitemap->add(Url::create('/')
            ->setLastModificationDate(Carbon::now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0)
        );

        $sitemap->add(Url::create('/about'));
        $sitemap->add(Url::create('/contact'));

        // Add dynamic URLs
        foreach (\App\Models\Product::where('status', 'published')->get() as $product) {
            $sitemap->add(Url::create("/product/{$product->slug}"));
        }

        // Save sitemap
        $sitemap->writeToFile(public_path('sitemap.xml'));

        return response()->json(['message' => 'Sitemap generated successfully.']);
    }
}
