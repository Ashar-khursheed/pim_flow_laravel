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

        // Remove Laravel's default cache headers
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');

        // Only cache GET / HEAD
        if (!in_array($method, ['GET', 'HEAD'])) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-NonGET');
        }

        // Admin / backend routes - never cache
        if (str_starts_with($path, '/api/') &&
            !str_starts_with($path, '/api/frontend/') &&
            !str_starts_with($path, '/api/category-random-products')) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-Admin');
        }

        // Auth routes - never cache
        if (str_contains($path, '/login') ||
            str_contains($path, '/logout') ||
            str_contains($path, '/register') ||
            str_contains($path, '/auth/')) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-Auth');
        }

        // User-specific routes - never cache
        if (preg_match('#/(cart|wishlist|orders|customer|profile|save-for-later|recent-products|invoices|support-tickets|quotes)#', $path)) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-UserSpecific');
        }

        // Dynamic / real-time - never cache
        if (preg_match('#/(search|analytics|dashboard|payment|frontend/location|tracking)#', $path)) {
            return $this->noCache($response, 'EcommerceCacheMiddleware-Dynamic');
        }

        // Static assets - 1 year
        if (preg_match('#\.(xml|txt|css|js|png|jpg|jpeg|gif|ico|pdf)$#i', $path) || str_contains($path, '/media/') || str_contains($path, '/sitemap')) {
            return $this->cache($response, 31536000, 'EcommerceCacheMiddleware-Static');
        }

        // Semi-static (SEO, sliders, brands, etc.) - 4 hours
        if (preg_match('#/frontend/(all-categories|categories|category-with-slug|categories-with-children|home-categories|brands|sliders|faqs|blog-categories|menu-banners|countries|brands/alphabetical|seo/paragraphs|seo-management)#', $path)) {
            return $this->cache($response, 14400, 'EcommerceCacheMiddleware-SemiStatic');
        }

        // Product listings - 30 minutes
        if (preg_match('#/frontend/(products|brandproducts|brandguestproducts|categoryproducts|categoryguestproducts|products-guest)|/category-random-products|/product-info/#', $path)) {
            return $this->cache($response, 1800, 'EcommerceCacheMiddleware-Products');
        }

        // Blogs - 2 hours
        if (str_contains($path, '/frontend/blogs')) {
            return $this->cache($response, 7200, 'EcommerceCacheMiddleware-Blogs');
        }

        // Health check - 5 minutes
        if (str_contains($path, '/health')) {
            return $this->cache($response, 300, 'EcommerceCacheMiddleware-Health');
        }

        // Default frontend APIs - 15 minutes
        if (str_starts_with($path, '/api/frontend/')) {
            return $this->cache($response, 900, 'EcommerceCacheMiddleware-Frontend');
        }

        // Fallback - 5 minutes
        return $this->cache($response, 300, 'EcommerceCacheMiddleware-Default');
    }

    /**
     * Apply a public cache header with s-maxage (for CloudFront)
     */
    private function cache($response, $seconds, $tag)
    {
        $response->headers->set('Cache-Control', "public, s-maxage={$seconds}, max-age=60");
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('X-Cache-Middleware', $tag);
        $this->addCorsHeaders($response);
        return $response;
    }

    /**
     * Apply no-cache headers for sensitive routes
     */
    private function noCache($response, $tag)
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Cache-Middleware', $tag);
        $this->addCorsHeaders($response);
        return $response;
    }

    /**
     * Add CORS headers for CloudFront
     */
    private function addCorsHeaders($response)
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Session-ID');
    }
}
