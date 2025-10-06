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
        
        // Force remove Laravel's default cache headerss
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');
        
        // Only apply caching to GET requests
        if (!in_array($method, ['GET', 'HEAD'])) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-NonGET');
            return $response;
        }
        
        // Admin API Routes - Never cache (sensitive data)
        // Exception: Allow caching for public product endpoints
        if (str_starts_with($path, '/api/') &&
            !str_starts_with($path, '/api/frontend/') &&
            !str_starts_with($path, '/api/category-random-products')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Admin');
            return $response;
        }
        
        // Authentication routes - Never cache
        if (str_contains($path, '/login') ||
            str_contains($path, '/logout') ||
            str_contains($path, '/register') ||
            str_contains($path, '/auth/')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Auth');
            return $response;
        }
        
        // User-specific routes - Never cache
        if (str_contains($path, '/cart') ||
            str_contains($path, '/wishlist') ||
            str_contains($path, '/orders') ||
            str_contains($path, '/customer') ||
            str_contains($path, '/profile') ||
            str_contains($path, '/save-for-later') ||
            str_contains($path, '/recent-products') ||
            str_contains($path, '/invoices') ||
            str_contains($path, '/support-tickets') ||
            str_contains($path, '/quotes')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-UserSpecific');
            return $response;
        }
        
        // Dynamic/Real-time data - Never cache
        if (str_contains($path, '/search') ||
            str_contains($path, '/analytics') ||
            str_contains($path, '/dashboard') ||
            str_contains($path, '/payment') ||
            str_contains($path, '/frontend/location') ||
            str_contains($path, '/tracking')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Dynamic');
            return $response;
        }
        
        // Static content - Long term caching (1 year)
        if (str_contains($path, '/media/') ||
            str_ends_with($path, '.xml') ||
            str_ends_with($path, '.txt') ||
            str_contains($path, '/sitemap') ||
            str_ends_with($path, '.css') ||
            str_ends_with($path, '.js') ||
            str_ends_with($path, '.png') ||
            str_ends_with($path, '.jpg') ||
            str_ends_with($path, '.gif') ||
            str_ends_with($path, '.ico') ||
            str_ends_with($path, '.pdf')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000'); // 1 year
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Static');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Semi-static content - Medium term caching (4 hours)
        if (str_contains($path, '/frontend/all-categories') ||
            str_contains($path, '/frontend/categories') ||
            str_contains($path, '/frontend/category-with-slug') ||
            str_contains($path, '/frontend/categories-with-children') ||
            str_contains($path, '/frontend/home-categories') ||
            str_contains($path, '/frontend/brands') ||
            str_contains($path, '/frontend/sliders') ||
            str_contains($path, '/frontend/faqs') ||
            str_contains($path, '/frontend/blog-categories') ||
            str_contains($path, '/frontend/menu-banners') ||
            str_contains($path, '/frontend/countries') ||
            str_contains($path, '/frontend/brands/alphabetical') ||
            str_contains($path, '/frontend/seo/paragraphs') ||
            str_contains($path, '/frontend/seo-management')) {
            $response->headers->set('Cache-Control', 'public, max-age=14400'); // 4 hours
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 14400) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-SemiStatic');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Product listings - Short term caching (30 minutes)
        if (str_contains($path, '/frontend/products') ||
            str_contains($path, '/frontend/brandproducts') ||
	    str_contains($path, '/frontend/brandguestproducts') ||
            str_contains($path, '/frontend/categoryproducts') ||
            str_contains($path, '/frontend/categoryguestproducts') ||
            str_contains($path, '/frontend/products-guest') ||
            str_contains($path, '/category-random-products') ||
            str_contains($path, '/product-info/')) {
            $response->headers->set('Cache-Control', 'public, max-age=1800'); // 30 minutes
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 1800) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Products');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Blog content - Medium term caching (2 hours)
        if (str_contains($path, '/frontend/blogs')) {
            $response->headers->set('Cache-Control', 'public, max-age=7200'); // 2 hours
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 7200) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Blogs');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Health check - Very short caching (5 minutes)
        if (str_contains($path, '/health')) {
            $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Health');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Default for other frontend routes - Short caching (15 minutes)
        if (str_starts_with($path, '/api/frontend/')) {
            $response->headers->set('Cache-Control', 'public, max-age=900'); // 15 minutes
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 900) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Frontend');
            $this->addCorsHeaders($response);
            return $response;
        }
        
        // Default - Very short caching (5 minutes)
        $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        $response->headers->set('X-Cache-Middleware', 'EcommerceCacheMiddleware-Default');
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
