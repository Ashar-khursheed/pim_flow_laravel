<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EcommerceCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Remove default Laravel headers
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');

        // Only cache GET / HEAD requests
        if (!in_array($method, ['GET', 'HEAD'])) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-NonGET');
        }

        // Admin APIs (never cache)
        if (str_starts_with($path, '/api/') &&
            !str_starts_with($path, '/api/frontend/') &&
            !str_starts_with($path, '/api/category-random-products')) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-Admin');
        }

        // Auth / User routes (never cache)
        if ($this->pathContains($path, [
            '/login', '/logout', '/register', '/auth/',
            '/cart', '/wishlist', '/orders', '/customer',
            '/profile', '/save-for-later', '/recent-products',
            '/invoices', '/support-tickets', '/quotes'
        ])) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-User');
        }

        // Dynamic / real-time data (never cache)
        if ($this->pathContains($path, [
            '/search', '/analytics', '/dashboard', '/payment',
            '/frontend/location', '/tracking'
        ])) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-Dynamic');
        }

        // Static files (1 year)
        if ($this->pathContains($path, [
            '/media/', '.xml', '.txt', '/sitemap', '.css', '.js', '.png',
            '.jpg', '.gif', '.ico', '.pdf'
        ])) {
            return $this->cache($response, 31536000, 'EcommerceCacheMiddleware-Static');
        }

        // Semi-static APIs (4 hours)
        if ($this->pathContains($path, [
            '/frontend/all-categories', '/frontend/categories',
            '/frontend/category-with-slug', '/frontend/categories-with-children',
            '/frontend/home-categories', '/frontend/brands',
            '/frontend/sliders', '/frontend/faqs',
            '/frontend/blog-categories', '/frontend/menu-banners',
            '/frontend/countries', '/frontend/brands/alphabetical',
            '/frontend/seo/paragraphs', '/frontend/seo-management'
        ])) {
            return $this->cache($response, 14400, 'EcommerceCacheMiddleware-SemiStatic');
        }

        // Product listings (30 mins)
        if ($this->pathContains($path, [
            '/frontend/products', '/frontend/brandproducts',
            '/frontend/brandguestproducts', '/frontend/categoryproducts',
            '/frontend/categoryguestproducts', '/frontend/products-guest',
            '/category-random-products', '/product-info/'
        ])) {
            return $this->cache($response, 1800, 'EcommerceCacheMiddleware-Products');
        }

        // Blog pages (2 hours)
        if (str_contains($path, '/frontend/blogs')) {
            return $this->cache($response, 7200, 'EcommerceCacheMiddleware-Blogs');
        }

        // Health check (5 mins)
        if (str_contains($path, '/health')) {
            return $this->cache($response, 300, 'EcommerceCacheMiddleware-Health');
        }

        // Default frontend (15 mins)
        if (str_starts_with($path, '/api/frontend/')) {
            return $this->cache($response, 900, 'EcommerceCacheMiddleware-Frontend');
        }

        // Fallback (5 mins)
        return $this->cache($response, 300, 'EcommerceCacheMiddleware-Default');
    }

    private function cache($response, int $seconds, string $label)
    {
        $response->headers->set(
            'Cache-Control',
            "public, max-age=60, s-maxage={$seconds}, stale-while-revalidate=600"
        );
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('X-Cache-Middleware', $label);
        $this->addCors($response);
        return $response;
    }

    private function noCache($response, string $label)
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Cache-Middleware', $label);
        $this->addCors($response);
        return $response;
    }

    private function addCors($response)
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Session-ID');
    }

    private function pathContains(string $path, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($path, $needle)) return true;
        }
        return false;
    }
}
