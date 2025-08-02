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
        $sitemaps = [
            [
                'loc' => url('/sitemap-pages.xml'),
                'lastmod' => now()->toISOString(),
            ],
            [
                'loc' => url('/sitemap-categories.xml'),
                'lastmod' => Category::latest('updated_at')->first()?->updated_at->toISOString() ?? now()->toISOString(),
            ],
            [
                'loc' => url('/sitemap-products.xml'),
                'lastmod' => Product::latest('updated_at')->first()?->updated_at->toISOString() ?? now()->toISOString(),
            ],
            [
                'loc' => url('/sitemap-blog.xml'),
                'lastmod' => Post::latest('updated_at')->first()?->updated_at->toISOString() ?? now()->toISOString(),
            ],
        ];

        return response()->view('sitemap-index', compact('sitemaps'))
                         ->header('Content-Type', 'text/xml');
    }

    public function pages()
    {
        $urls = collect([
            [
                'loc' => url('/'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'daily',
                'priority' => '1.0'
            ],
            [
                'loc' => url('/about'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'monthly',
                'priority' => '0.8'
            ],
            [
                'loc' => url('/contact'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'monthly',
                'priority' => '0.8'
            ],
        ]);

        return response()->view('sitemap', compact('urls'))
                         ->header('Content-Type', 'text/xml');
    }

    public function categories()
    {
        $urls = collect();

        Category::active()->chunk(1000, function ($categories) use ($urls) {
            foreach ($categories as $category) {
                $urls->push([
                    'loc' => url("/category/{$category->slug}"),
                    'lastmod' => $category->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ]);
            }
        });

        return response()->view('sitemap', compact('urls'))
                         ->header('Content-Type', 'text/xml');
    }

    public function products()
    {
        $urls = collect();

        Product::active()->chunk(1000, function ($products) use ($urls) {
            foreach ($products as $product) {
                $urls->push([
                    'loc' => url("/products/{$product->slug}"),
                    'lastmod' => $product->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ]);
            }
        });

        return response()->view('sitemap', compact('urls'))
                         ->header('Content-Type', 'text/xml');
    }

    public function blog()
    {
        $urls = collect();

        Post::published()->chunk(1000, function ($posts) use ($urls) {
            foreach ($posts as $post) {
                $urls->push([
                    'loc' => url("/blog/{$post->slug}"),
                    'lastmod' => $post->updated_at->toISOString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6'
                ]);
            }
        });

        return response()->view('sitemap', compact('urls'))
                         ->header('Content-Type', 'text/xml');
    }
}