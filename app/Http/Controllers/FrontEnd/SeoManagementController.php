<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SeoManagement;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
class SeoManagementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/seo-management",
     *     summary="Get all SEO records",
     *     tags={"Frontend-SEO Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="url", type="string", example="https://example.com"),
     *                     @OA\Property(property="primary_keyword", type="string", example="SEO keyword"),
     *                     @OA\Property(property="title_tag", type="string", example="Page Title"),
     *                     @OA\Property(property="meta_title", type="string", example="Meta Title"),
     *                     @OA\Property(property="meta_description", type="string", example="Meta description"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="indexing", type="boolean", example=true),
     *                     @OA\Property(property="og_title", type="string", example="OG Title"),
     *                     @OA\Property(property="og_description", type="string", example="OG Description"),
     *                     @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
     *                     @OA\Property(property="og_image_name", type="string", example="image.jpg"),
     *                     @OA\Property(property="tags", type="string", example="tag1,tag2"),
     *                     @OA\Property(property="schema", type="string", example="schema markup")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $seoData = SeoManagement::with('seo_secondary_keywords')->get()->map(function ($item) {
            return $this->filterFields($item);
        });

        return response()->json([
            'status' => true,
            'data' => $seoData
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/seo-management/relational/{relational_id}",
     *     tags={"Frontend-SEO Management"},
     *     summary="Get SEO data by relational ID",
     *     description="Returns SEO management records for the given relational ID.",
     *     @OA\Parameter(
     *         name="relational_id",
     *         in="path",
     *         required=true,
     *         description="Relational ID to filter SEO data",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO data for the specified relational ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="url", type="string", example="https://example.com"),
     *                     @OA\Property(property="primary_keyword", type="string", example="SEO keyword"),
     *                     @OA\Property(property="title_tag", type="string", example="Page Title"),
     *                     @OA\Property(property="meta_title", type="string", example="Meta Title"),
     *                     @OA\Property(property="meta_description", type="string", example="Meta description"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="indexing", type="boolean", example=true),
     *                     @OA\Property(property="og_title", type="string", example="OG Title"),
     *                     @OA\Property(property="og_description", type="string", example="OG Description"),
     *                     @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
     *                     @OA\Property(property="og_image_name", type="string", example="image.jpg"),
     *                     @OA\Property(property="tags", type="string", example="tag1,tag2"),
     *                     @OA\Property(property="schema", type="string", example="schema markup")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

//     public function getByRelationalId($identifier)
// {
//     $seoQuery = SeoManagement::with('seo_secondary_keywords');

//     if (filter_var($identifier, FILTER_VALIDATE_URL)) {
//         // Handle full URL
//         $path = parse_url($identifier, PHP_URL_PATH);
//         $path = ltrim($path, '/');

//         $seoQuery->where(function ($q) use ($identifier, $path) {
//             $q->where('url', $identifier)         // full URL
//               ->orWhere('url', '/' . $path)       // with leading slash
//               ->orWhere('url', $path);            // without leading slash
//         });
//     } elseif (is_numeric($identifier)) {
//         // Handle numeric relational_id
//         $seoQuery->where('relational_id', $identifier);
//     } else {
//         // Handle string identifier like "countertop-gas-ranges"
//         $seoQuery->where(function ($q) use ($identifier) {
//             $path = ltrim($identifier, '/');
//             $q->where('url', $identifier)
//               ->orWhere('url', '/' . $path)
//               ->orWhere('url', $path);
//         });
//     }

//     $seoData = $seoQuery->get()->map(function ($item) {
//         $filtered = $this->filterFields($item);

//         // Decode schema JSON
//         if (!empty($filtered['schema']) && is_string($filtered['schema'])) {
//             $decoded = json_decode($filtered['schema'], true);
//             if (json_last_error() === JSON_ERROR_NONE) {
//                 if (!empty($decoded['@type']) && !empty($decoded['url'])) {
//                     $baseUrl = url("/");
//                     if (strtolower($decoded['@type']) === 'product') {
//                         $decoded['url'] = $baseUrl . 'products/' . ltrim($decoded['url'], '/');
//                     } elseif (strtolower($decoded['@type']) === 'category') {
//                         $decoded['url'] = $baseUrl . 'collections/' . ltrim($decoded['url'], '/');
//                     }
//                 }
//                 $filtered['schema'] = $decoded;
//             }
//         }

//         return $filtered;
//     });

//     return response()->json([
//         'status' => true,
//         'data' => $seoData
//     ]);
// }

public function getByRelationalId($identifier)
{
    $seoQuery = SeoManagement::with('seo_secondary_keywords')
        ->orderBy('id', 'desc');

    // =========================
    // FILTER LOGIC
    // =========================
    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
        $path = parse_url($identifier, PHP_URL_PATH);
        $path = ltrim($path, '/');
        $seoQuery->where(function ($q) use ($identifier, $path) {
            $q->where('url', $identifier)
              ->orWhere('url', '/' . $path)
              ->orWhere('url', $path);
        });
    } elseif (is_numeric($identifier)) {
        $seoQuery->where('relational_id', $identifier);
    } else {
        $path = ltrim($identifier, '/');
        $seoQuery->where(function ($q) use ($identifier, $path) {
            $q->where('url', $identifier)
              ->orWhere('url', '/' . $path)
              ->orWhere('url', $path);
        });
    }

    // =========================
    // FETCH DATA
    // =========================
    $seoData = $seoQuery->get()->map(function ($item) {

        // Saare fields lo and clean "undefined" strings
        $filtered = $this->filterFields($item) ?? [];
        
        // Deep clean "undefined" strings from all top-level fields
        foreach ($filtered as $key => $value) {
            if (is_string($value) && strtolower(trim($value)) === 'undefined') {
                $filtered[$key] = null;
            }
        }

        // Raw schema directly DB se lo
        $raw = DB::table('seo_management')
            ->where('id', $item->id)
            ->value('schema');

        if (empty($raw)) {
            $filtered['schema']        = null;
            $filtered['canonical_url'] = null;
            return $filtered;
        }

        // =========================
        // SCHEMA DECODE
        // =========================
        $decoded = $this->decodeSchema($raw);

        if (!is_array($decoded)) {
            $filtered['schema']        = $raw;
            $filtered['canonical_url'] = null;
            return $filtered;
        }

        // Normalize to array
        if (!isset($decoded[0])) {
            $decoded = [$decoded];
        }

        // =========================
        // CLEAN & FIX SCHEMA DATA
        // =========================
        $baseUrl = rtrim(url('/'), '/');
        
        foreach ($decoded as &$schemaItem) {
            if (!is_array($schemaItem)) continue;

            // 1. Fix top-level URLs in schema item
            if (isset($schemaItem['url']) && is_string($schemaItem['url'])) {
                if (str_starts_with($schemaItem['url'], '//')) {
                    $schemaItem['url'] = 'https:' . $schemaItem['url'];
                }
            }

            // 2. Handle @graph if exists
            if (!empty($schemaItem['@graph']) && is_array($schemaItem['@graph'])) {
                foreach ($schemaItem['@graph'] as &$graph) {
                    if (!is_array($graph)) continue;

                    // Fix "undefined" and URLs in graph nodes
                    foreach ($graph as $gKey => $gValue) {
                        if (is_string($gValue)) {
                            if (strtolower(trim($gValue)) === 'undefined') {
                                $graph[$gKey] = null;
                            } elseif ($gKey === 'url' && str_starts_with($gValue, '//')) {
                                $graph[$gKey] = 'https:' . $gValue;
                            }
                        }
                    }

                    // Clean descriptions
                    if (isset($graph['description'])) {
                        if (is_array($graph['description'])) {
                            $graph['description'] = trim(
                                strip_tags(implode(' ', array_filter($graph['description'])))
                            );
                        } elseif (is_string($graph['description'])) {
                            $graph['description'] = trim(strip_tags($graph['description']));
                        }
                    }
                }
                unset($graph);
            } else {
                // 3. Handle non-graph items (flattened structure)
                foreach ($schemaItem as $sKey => $sValue) {
                    if (is_string($sValue)) {
                        if (strtolower(trim($sValue)) === 'undefined') {
                            $schemaItem[$sKey] = null;
                        }
                    }
                }
                
                if (isset($schemaItem['description'])) {
                    $schemaItem['description'] = trim(strip_tags($schemaItem['description']));
                }
            }
        }
        unset($schemaItem);

        // ✅ Parsed schema set karo
        $filtered['schema'] = $decoded;

        // =========================
        // CANONICAL URL EXTRACT
        // =========================
        $canonicalUrl = null;
        foreach ($decoded as $schemaItem) {
            if (!is_array($schemaItem)) continue;
            
            // Try @graph first
            if (!empty($schemaItem['@graph']) && is_array($schemaItem['@graph'])) {
                foreach ($schemaItem['@graph'] as $graph) {
                    if (!empty($graph['url'])) {
                        $canonicalUrl = $graph['url'];
                        break 2;
                    }
                }
            }
            
            // Try top level url
            if (!empty($schemaItem['url'])) {
                $canonicalUrl = $schemaItem['url'];
                break;
            }
        }

        // Final URL cleanup for canonical
        if ($canonicalUrl) {
            if (str_starts_with($canonicalUrl, '//')) {
                $canonicalUrl = 'https:' . $canonicalUrl;
            } elseif (!str_starts_with($canonicalUrl, 'http')) {
                $canonicalUrl = $baseUrl . '/' . ltrim($canonicalUrl, '/');
            }
        }
        
        $filtered['canonical_url'] = $canonicalUrl;

        return $filtered;
    });

    return response()->json(
        ['status' => true, 'data' => $seoData],
        200,
        [],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

// =============================================
// SCHEMA DECODE HELPER — Corrupted JSON fix
// =============================================
// private function decodeSchema($raw)
// {
//     if (is_array($raw)) return $raw;

//     // Step 1: Direct
//     $decoded = json_decode($raw, true);
//     if (is_array($decoded)) return $decoded;

//     // Step 2: Stripslashes pehle
//     $str = stripslashes($raw);
//     $decoded = json_decode($str, true);
//     if (is_array($decoded)) return $decoded;

//     // Step 3: Stripslashes ke baad inch fix
//     // 52" Pass -> 52in Pass  (lekin "15" ya "4.6" safe rahega)
//     $fixed = preg_replace('/(\d)"(?=[^,\}\]\d])/', '$1in', $str);
//     $decoded = json_decode($fixed, true);
//     if (is_array($decoded)) return $decoded;

//     // Step 4: Raw pe inch fix (without stripslashes)
//     $fixed2 = preg_replace('/(\d)\"(?=[^,\}\]\d])/', '$1in', $raw);
//     $decoded = json_decode($fixed2, true);
//     if (is_array($decoded)) return $decoded;

//     // Step 5: Dono ek saath + control chars
//     $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
//     $clean = preg_replace('/(\d)"(?=[^,\}\]\d])/', '$1in', $clean);
//     $decoded = json_decode($clean, true);
//     if (is_array($decoded)) return $decoded;

//     \Log::error('Schema decode failed', [
//         'error'   => json_last_error_msg(),
//         'snippet' => substr($fixed, 0, 300),
//     ]);

//     return null;
// }
private function decodeSchema($raw)
{
    if (is_array($raw)) return $raw;
    if (!is_string($raw) || trim($raw) === '') return null;

    $raw = trim($raw);

    // Step 1: Direct parse
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Step 2: Stripslashes (DB double-escaped)
    $str = stripslashes($raw);
    $decoded = json_decode($str, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Step 3: Deep sanitize on stripslashed version
    $sanitized = $this->sanitizeSchema($str);
    $decoded = json_decode($sanitized, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Step 4: Deep sanitize on original raw
    $sanitized2 = $this->sanitizeSchema($raw);
    $decoded = json_decode($sanitized2, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Step 5: Aggressive fix — fix inch marks in "name" fields specifically
    $aggressive = $this->fixInchMarksAggressive($str);
    $decoded = json_decode($aggressive, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Step 6: Nuclear option — try to extract JSON objects via bracket matching
    $extracted = $this->extractJsonFromRaw($raw);
    if (is_array($extracted) && !empty($extracted)) return $extracted;

    \Log::error('Schema decode failed', [
        'error'   => json_last_error_msg(),
        'snippet' => substr($str, 0, 500),
    ]);

    return null;
}

/**
 * Sanitize corrupted JSON schema string.
 * Handles: control chars, stringified arrays, inch marks, etc.
 */
private function sanitizeSchema(string $str): string
{
    // 1. Remove bad control characters (preserve \n \r \t)
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);

    // 2. Fix %22 / \%22 in URLs
    $str = str_replace(['\%22', '%22'], '"', $str);

    // 3. Fix stringified array fields: "description": "[\"...\"]" → "description": ["..."]
    $str = $this->fixStringifiedArrayFields($str, ['description', 'text', 'content']);

    // 4. Fix unescaped inch marks (e.g., 2-1/2", 24 Ga or 12.75\" (L))
    //    Strategy: find "name": "...X"..." patterns where X" is an inch mark
    $str = $this->fixInchMarksInValues($str);

    return $str;
}

/**
 * Fix unescaped inch/quote marks inside JSON string values.
 * Walks through the string character by character to find unescaped quotes
 * that appear inside string values (after digits or fractions).
 */
private function fixInchMarksInValues(string $str): string
{
    // Pattern: digit or fraction followed by " that is NOT at end of a JSON value
    // We look for: digit" followed by a non-JSON-structural character
    // JSON structural after a closing quote: , } ] : whitespace followed by key
    // Inch mark typically followed by: space+letter, comma+space+digit, parenthesis

    // This regex targets: a digit followed by " followed by something that indicates
    // the quote is NOT the closing quote of a JSON string value
    // Specifically: digit " (comma-space-or letter-or open-paren) but NOT (comma-" or comma-newline or })
    $str = preg_replace_callback(
        '/(\d)"((?:,\s*\d)|(?:\s+[A-Za-z(])|(?:\s*\()|(?:\\\\"))/',
        function ($m) {
            return $m[1] . '\\"' . $m[2];
        },
        $str
    );

    return $str;
}

/**
 * Aggressive inch mark fixer — targets specific known patterns in product names
 * like: 2-1/2", 24 Ga  or  Half-size, 2-1/2", 24
 */
private function fixInchMarksAggressive(string $str): string
{
    // First apply standard sanitize
    $str = $this->sanitizeSchema($str);

    // Fix pattern: fraction/number followed by " then comma (e.g., 2-1/2", 24)
    $str = preg_replace('/(\d)"(\s*,\s*\d)/', '$1\\"$2', $str);

    // Fix pattern: number followed by " then space and letter (e.g., 2" Anti or 10.38" (L))
    $str = preg_replace('/(\d)"(\s+[A-Za-z(])/', '$1\\"$2', $str);

    // Fix pattern: number followed by \" then space (already escaped but double-check)
    // This handles cases where \\" appears instead of \"
    $str = preg_replace('/(\d)\\\\\\\\+"/', '$1\\"', $str);

    return $str;
}

/**
 * Nuclear option: Extract valid JSON objects from a raw string by finding
 * balanced { } or [ ] blocks and attempting to parse each one.
 */
private function extractJsonFromRaw(string $raw): ?array
{
    $raw = trim($raw);

    // Try stripslashes version too
    $attempts = [$raw, stripslashes($raw)];

    foreach ($attempts as $str) {
        $str = trim($str);

        // If it starts with [ try to find balanced brackets
        if (str_starts_with($str, '[') || str_starts_with($str, '{')) {
            // Try progressively trimming from the end
            $result = $this->tryParseWithTruncation($str);
            if ($result !== null) return $result;
        }

        // Try to find JSON objects in the string
        $objects = [];
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) continue;

            if ($char === '{') {
                if ($depth === 0) $start = $i;
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $candidate = substr($str, $start, $i - $start + 1);
                    $parsed = json_decode($candidate, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                        $objects[] = $parsed;
                    }
                    $start = null;
                }
            }
        }

        if (!empty($objects)) {
            return count($objects) === 1 ? $objects : $objects;
        }
    }

    return null;
}

/**
 * Try parsing JSON, and if it fails, try fixing common issues and re-parsing.
 */
private function tryParseWithTruncation(string $str): ?array
{
    // Direct attempt
    $decoded = json_decode($str, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Apply sanitize and try
    $sanitized = $this->sanitizeSchema($str);
    $decoded = json_decode($sanitized, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    // Apply aggressive inch fix
    $fixed = $this->fixInchMarksAggressive($str);
    $decoded = json_decode($fixed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    return null;
}

/**
 * Fixes fields like "description": "[\"<p>...\"]" → "description": ["<p>..."]
 * Uses proper bracket depth tracking to find the real end of the stringified array.
 */
private function fixStringifiedArrayFields(string $str, array $fields): string
{
    foreach ($fields as $field) {
        // Match both "field": "[ and "field":"[
        $patterns = [
            '"' . $field . '": "',
            '"' . $field . '":"',
        ];

        foreach ($patterns as $needle) {
            $offset = 0;

            while (($pos = strpos($str, $needle, $offset)) !== false) {
                $valueStart = $pos + strlen($needle);

                // Check if value starts with [ (stringified array indicator)
                if (!isset($str[$valueStart]) || $str[$valueStart] !== '[') {
                    $offset = $pos + 1;
                    continue;
                }

                // Find the real closing ]" by tracking bracket depth
                // We need to handle escaped quotes inside the stringified array
                $depth = 0;
                $i = $valueStart;
                $len = strlen($str);
                $foundEnd = false;
                $endPos = -1;

                while ($i < $len) {
                    $ch = $str[$i];

                    if ($ch === '\\' && $i + 1 < $len) {
                        $i += 2; // skip escaped character
                        continue;
                    }

                    if ($ch === '[') {
                        $depth++;
                    } elseif ($ch === ']') {
                        $depth--;
                        if ($depth === 0) {
                            // This ] should be followed by " (closing the outer string)
                            if ($i + 1 < $len && $str[$i + 1] === '"') {
                                $endPos = $i;
                                $foundEnd = true;
                                break;
                            }
                        }
                    }
                    $i++;
                }

                if (!$foundEnd) {
                    $offset = $pos + 1;
                    continue;
                }

                // Extract the array content (including [ and ])
                $arrayContent = substr($str, $valueStart, ($endPos - $valueStart) + 1);

                // Unescape the internal escaped quotes: \" → "
                $arrayContent = str_replace('\\"', '"', $arrayContent);
                $arrayContent = str_replace('\\\\/', '/', $arrayContent);

                // Try to parse it as JSON array
                $testParse = json_decode($arrayContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If it fails, just strip HTML tags and make a clean string
                    $cleanText = strip_tags(implode(' ', json_decode($arrayContent, true) ?? [$arrayContent]));
                    $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));
                    $cleanText = str_replace(['\\', '"'], ['\\\\', '\\"'], $cleanText);
                    $replacement = '"' . $field . '": "' . $cleanText . '"';
                } else {
                    // It parsed fine as array - strip HTML from each element and join as clean string
                    if (is_array($testParse)) {
                        $cleanText = trim(strip_tags(implode(' ', array_filter($testParse))));
                        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
                        $cleanText = str_replace(['\\', '"'], ['\\\\', '\\"'], $cleanText);
                        $replacement = '"' . $field . '": "' . $cleanText . '"';
                    } else {
                        $offset = $pos + 1;
                        continue;
                    }
                }

                // Replace in original string
                $before = substr($str, 0, $pos);
                $after  = substr($str, $endPos + 2); // skip ]"

                $str = $before . $replacement . $after;
                $offset = strlen($before) + strlen($replacement);
            }
        }
    }

    return $str;
}
    // public function getByRelationalId($identifier)
    // {   
    //     $seoQuery = SeoManagement::with('seo_secondary_keywords')->orderBy('id', 'desc');

    //     if (filter_var($identifier, FILTER_VALIDATE_URL)) {
    //         $path = parse_url($identifier, PHP_URL_PATH);
    //         $path = ltrim($path, '/');

    //         $seoQuery->where(function ($q) use ($identifier, $path) {
    //             $q->where('url', $identifier)
    //             ->orWhere('url', '/' . $path)
    //             ->orWhere('url', $path);
    //         });
    //     } elseif (is_numeric($identifier)) {
    //         $seoQuery->where('relational_id', $identifier);
    //     } else {
    //         $seoQuery->where(function ($q) use ($identifier) {
    //             $path = ltrim($identifier, '/');
    //             $q->where('url', $identifier)
    //             ->orWhere('url', '/' . $path)
    //             ->orWhere('url', $path);
    //         });
    //     }

    //     $seoData = $seoQuery->get()->map(function ($item) {
    //         $filtered = $this->filterFields($item);

    //         if (!empty($filtered['schema'])) {
    //             $raw = $filtered['schema'];
    //             $decoded = null;

    //             if (is_array($raw)) {
    //                 $decoded = $raw;
    //             } else {
    //                 $raw = trim($raw);

    //                 $attempts = [
    //                     // 1. Direct decode
    //                     function($s) { 
    //                         return json_decode($s, true); 
    //                     },
    //                     // 2. Fix unescaped measurement quotes (e.g. 52" -> 52\")
    //                     function($s) { 
    //                         $s = preg_replace('/(\d)"/', '$1\\"', $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 3. Fix literal control characters
    //                     function($s) { 
    //                         $s = str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 4. Fix both measurement quotes + control characters
    //                     function($s) {
    //                         $s = preg_replace('/(\d)"/', '$1\\"', $s);
    //                         $s = str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $s);
    //                         return json_decode($s, true);
    //                     },
    //                     // 5. stripslashes
    //                     function($s) { 
    //                         return json_decode(stripslashes($s), true); 
    //                     },
    //                     // 6. Trim outer quotes
    //                     function($s) { 
    //                         return json_decode(trim($s, '"'), true); 
    //                     },
    //                     // 7. HTML entities
    //                     function($s) { 
    //                         return json_decode(html_entity_decode($s), true); 
    //                     }
    //                 ];

    //                 foreach ($attempts as $attempt) {
    //                     $res = $attempt($raw);

    //                     $limit = 5;
    //                     while (is_string($res) && $limit-- > 0) {
    //                         $res = json_decode($res, true);
    //                     }

    //                     if (json_last_error() === JSON_ERROR_NONE && is_array($res)) {
    //                         $decoded = $res;
    //                         break;
    //                     }
    //                 }
    //             }

    //             if (!is_array($decoded)) {
    //                 $filtered['schema']        = null;
    //                 $filtered['canonical_url'] = null;
    //             } else {
    //                 // Normalize to array of objects
    //                 if (!isset($decoded[0])) {
    //                     $decoded = [$decoded];
    //                 }

    //                 // Fix description array -> flat string across all @graph nodes
    //                 foreach ($decoded as &$schemaItem) {
    //                     if (!is_array($schemaItem) || empty($schemaItem['@graph'])) continue;

    //                     foreach ($schemaItem['@graph'] as &$graph) {
    //                         if (!isset($graph['description'])) continue;

    //                         if (is_array($graph['description'])) {
    //                             // Flatten array: remove nulls, strip HTML, join
    //                             $graph['description'] = trim(strip_tags(
    //                                 implode(' ', array_filter(
    //                                     $graph['description'],
    //                                     fn($v) => !is_null($v) && $v !== ''
    //                                 ))
    //                             ));
    //                         } elseif (is_string($graph['description'])) {
    //                             // Strip any leftover HTML tags from string descriptions
    //                             $graph['description'] = trim(strip_tags($graph['description']));
    //                         }
    //                     }
    //                     unset($graph);
    //                 }
    //                 unset($schemaItem);

    //                 $filtered['schema'] = $decoded;

    //                 // Extract canonical URL
    //                 $canonicalUrl = null;
    //                 foreach ($decoded as $schemaItem) {
    //                     if (!is_array($schemaItem)) continue;

    //                     if (!empty($schemaItem['@graph']) && is_array($schemaItem['@graph'])) {
    //                         foreach ($schemaItem['@graph'] as $graph) {
    //                             if (!empty($graph['url'])) {
    //                                 $canonicalUrl = $graph['url'];
    //                                 break 2;
    //                             }
    //                         }
    //                     }

    //                     if (!empty($schemaItem['url'])) {
    //                         $canonicalUrl = $schemaItem['url'];
    //                         break;
    //                     }
    //                 }

    //                 $filtered['canonical_url'] = $canonicalUrl;
    //             }
    //         }

    //         return $filtered;
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'data'   => $seoData
    //     ]);
    // }

    /**
     * @OA\Get(
     *     path="/api/frontend/seo/paragraphs/{relational_id}",
     *     tags={"Frontend-SEO Management"},
     *     summary="Get SEO paragraph data by relational ID",
     *     description="Returns only paragraph-related SEO data for a given relational ID.",
     *     @OA\Parameter(
     *         name="relational_id",
     *         in="path",
     *         required=true,
     *         description="Relational ID for paragraph data",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO paragraph data",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="relational_id", type="integer", example=12),
     *                     @OA\Property(property="relational_type", type="string", example="product"),
     *                     @OA\Property(property="internal_links", type="string", example="https://example.com/link"),
     *                     @OA\Property(property="paragraph_1", type="string", example="Paragraph 1 text"),
     *                     @OA\Property(property="paragraph_2", type="string", example="Paragraph 2 text"),
     *                     @OA\Property(property="paragraph_3", type="string", example="Paragraph 3 text"),
     *                     @OA\Property(property="paragraph_4", type="string", example="Paragraph 4 text"),
     *                     @OA\Property(property="popular_tags", type="array", @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getParagraphData(Request $request, $identifier)
    {
        $relationalType = $request->query('relational_type'); // optional filter
 
        $seoQuery = SEOManagement::query();

        // If a full URL is passed, extract only the path
        if (filter_var($identifier, FILTER_VALIDATE_URL)) {
            $identifier = parse_url($identifier, PHP_URL_PATH);
        }

        // Now treat identifier as a slug/path and match using 'slug' column
        $seoQuery->where('url', ltrim($identifier, '/'));

        // Apply optional relational_type filter
        if ($relationalType) {
            $seoQuery->where('relational_type', $relationalType);
        }

        $seoData = $seoQuery->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'relational_id' => $item->relational_id,
                'relational_type' => $item->relational_type,
                'internal_links' => $item->internal_links,
                'cat_desc' => $item->cat_desc,
                'paragraph_1' => $item->paragraph_1,
                'paragraph_2' => $item->paragraph_2,
                'paragraph_3' => $item->paragraph_3,
                'paragraph_4' => $item->paragraph_4,
                'banner_slug' => $item->banner_slug,
                'banner_image_file' => $item->banner_image_file,
                'banner_image_alt_text' => $item->banner_image_alt_text,
                'popular_tags' => is_string($item->popular_tags)
                    ? json_decode($item->popular_tags, true)
                    : ($item->popular_tags ?? []),
                'popularTag_details' => is_string($item->popularTag_details)
                ? json_decode($item->popularTag_details, true)
                : ($item->popularTag_details ?? []),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $seoData
        ]);
    }



    private function filterFields($item)
    {
        $fields = [
            'id' => $item->id,
            'relational_id' => $item->relational_id,
            'relational_type' => $item->relational_type,
            'url' => $item->url,
            'primary_keyword' => $item->primary_keyword,
            'title_tag' => $item->title_tag,
            'meta_title' => $item->meta_title,
            'meta_description' => $item->meta_description,
            'internal_links' => $item->internal_links,
            'indexing' => $item->indexing,
            'og_title' => $item->og_title,
            'og_description' => $item->og_description,
            'og_image_url' => $item->og_image_url,
            'og_image_alt_text' => $item->og_image_alt_text,
            'og_image_name' => $item->og_image_name,
            'tags' => $item->tags,
            'schema' => $item->schema
        ];

        // Ensure "undefined" strings are null
        foreach ($fields as $key => $value) {
            if (is_string($value) && strtolower(trim($value)) === 'undefined') {
                $fields[$key] = null;
            }
        }

        return $fields;
    }


}