<?php

// app/Models/SeoDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 
        'type', 
        'primary_keyword', 
        'primary_keyword_search_volume', 
        'secondary_keywords', 
        'secondary_keywords_search_volume', 
        'url', 
        'title_tag', 
        'meta_title', 
        'meta_description', 
        'og_title', 
        'og_description', 
        'og_image', 
        'canonical_tag', 
        'schema', 
        'internal_links', 
        'indexing',
    ];

    // Automatically generate the schema when SEO details are saved
    public static function boot()
    {
        parent::boot();

        static::creating(function ($seoDetail) {
            $seoDetail->schema = self::generateSchema($seoDetail);
        });

        static::updating(function ($seoDetail) {
            $seoDetail->schema = self::generateSchema($seoDetail);
        });
    }

    // Generate schema dynamically
    public static function generateSchema($seoDetail)
    {
        // Generate a random rating between 4.5 and 5
        $rating = rand(45, 50) / 10;
        $reviewsCount = rand(10, 15); // Random reviews between 10 and 15

        // Generate the schema (JSON-LD for SEO)
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => $seoDetail->primary_keyword,
            "url" => $seoDetail->url,
            "description" => $seoDetail->meta_description,
            "mainEntityOfPage" => $seoDetail->url,
            "brand" => [
                "@type" => "Brand",
                "name" => "Your Brand Name", // Replace with dynamic brand name if applicable
            ],
            "review" => [
                "@type" => "Review",
                "reviewRating" => [
                    "@type" => "Rating",
                    "ratingValue" => $rating,
                    "bestRating" => "5",
                ],
                "author" => [
                    "@type" => "Person",
                    "name" => "Anonymous", // Replace with real reviewer name if available
                ],
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => $rating,
                "reviewCount" => $reviewsCount,
            ],
        ];

        // Add other fields if necessary (OG title, meta, etc.)
        // Example: $schema['ogImage'] = $seoDetail->og_image;

        return json_encode($schema);
    }
}
