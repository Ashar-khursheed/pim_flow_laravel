<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Post;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = collect();

        // Static pages
        $urls->push([
            'loc' => url('/'),
            'lastmod' => now()->toISOString(),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ]);

        $urls->push([
            'loc' => url('/about'),
            'lastmod' => now()->toISOString(),
            'changefreq' => 'monthly',
            'priority' => '0.8'
        ]);

        $urls->push([
            'loc' => url('/contact'),
            'lastmod' => now()->toISOString(),
            'changefreq' => 'monthly',
            'priority' => '0.8'
        ]);

        // Categories
        Category::active()->get()->each(function ($category) use ($urls) {
            $urls->push([
                'loc' => url("/category/{$category->slug}"),
                'lastmod' => $category->updated_at->toISOString(),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ]);
        });

        // Products
        Product::active()->get()->each(function ($product) use ($urls) {
            $urls->push([
                'loc' => url("/products/{$product->slug}"),
                'lastmod' => $product->updated_at->toISOString(),
                'changefreq' => 'weekly',
                'priority' => '0.7'
            ]);
        });

        // Blog posts
        Post::published()->get()->each(function ($post) use ($urls) {
            $urls->push([
                'loc' => url("/blog/{$post->slug}"),
                'lastmod' => $post->updated_at->toISOString(),
                'changefreq' => 'monthly',
                'priority' => '0.6'
            ]);
        });

        return response()->view('sitemap', compact('urls'))
                         ->header('Content-Type', 'text/xml');
    }
}