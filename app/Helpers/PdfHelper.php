<?php

namespace App\Helpers;

class PdfHelper
{
    /**
     * Get the Ghostscript binary path based on the environment
     */
    private static function getGhostscriptPath()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return '"C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe"';
        }
        
        $possiblePaths = [
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            '/opt/gs/bin/gs',
            'gs'
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path === 'gs') {
                exec('which gs 2>/dev/null', $output, $return);
                if ($return === 0 && !empty($output)) {
                    return 'gs';
                }
            } else {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        return 'gs';
    }

    /**
     * Compress PDF file using Ghostscript with better quality preservation
     */
    public static function compressPdf($inputPath, $outputPath, $quality = 'ebook')
    {
        if (!file_exists($inputPath)) {
            return "❌ Input file does not exist: $inputPath";
        }

        $gs = self::getGhostscriptPath();

        // Enhanced compression settings with better quality preservation
        $qualitySettings = [
            'printer' => [
                'dPDFSETTINGS' => '/printer',
                'dColorImageResolution' => '300',
                'dGrayImageResolution' => '300',
                'dMonoImageResolution' => '1200',
                'dColorImageDownsampleType' => '/Bicubic',
                'dGrayImageDownsampleType' => '/Bicubic',
                'dColorConversionStrategy' => '/RGB',
                'dProcessColorModel' => '/DeviceRGB'
            ],
            'ebook' => [
                'dPDFSETTINGS' => '/ebook',
                'dColorImageResolution' => '150',
                'dGrayImageResolution' => '150',
                'dMonoImageResolution' => '600',
                'dColorImageDownsampleType' => '/Bicubic',
                'dGrayImageDownsampleType' => '/Bicubic',
                'dColorConversionStrategy' => '/RGB',
                'dProcessColorModel' => '/DeviceRGB'
            ],
            'screen' => [
                'dPDFSETTINGS' => '/screen',
                'dColorImageResolution' => '96',
                'dGrayImageResolution' => '96',
                'dMonoImageResolution' => '300',
                'dColorImageDownsampleType' => '/Bicubic',
                'dGrayImageDownsampleType' => '/Bicubic',
                'dColorConversionStrategy' => '/RGB',
                'dProcessColorModel' => '/DeviceRGB'
            ]
        ];

        // Get settings for the specified quality
        $settings = $qualitySettings[$quality] ?? $qualitySettings['ebook'];
        
        // Build the command with enhanced quality settings
        $cmd = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4";
        
        // Add quality-specific parameters
        foreach ($settings as $param => $value) {
            $cmd .= " -$param=$value";
        }
        
        // Add additional quality preservation parameters
        $cmd .= " -dNOPAUSE -dBATCH -dQUIET";
        $cmd .= " -dAutoRotatePages=/None";  // Prevent auto-rotation
        $cmd .= " -dPreserveAnnots=true";   // Keep annotations
        $cmd .= " -dPreserveOPIComments=true"; // Keep OPI comments
        $cmd .= " -dOptimize=true";         // Optimize for web viewing
        $cmd .= " -dEmbedAllFonts=true";    // Embed fonts to prevent text issues
        $cmd .= " -dSubsetFonts=true";      // Subset fonts to reduce size
        $cmd .= " -dCompressFonts=true";    // Compress fonts
        
        // Add input and output files
        $cmd .= " -sOutputFile=\"$outputPath\" \"$inputPath\"";

        // Execute the command
        exec($cmd . " 2>&1", $output, $return);

        return [
            'command' => $cmd,
            'exit_code' => $return,
            'output' => $output,
            'output_file_exists' => file_exists($outputPath),
            'gs_path' => $gs,
            'quality_level' => $quality
        ];
    }

    /**
     * Check if Ghostscript is available on the system
     */
    public static function checkGhostscriptAvailability()
    {
        $gs = self::getGhostscriptPath();
        
        exec("$gs --version 2>&1", $output, $return);
        
        return [
            'available' => $return === 0,
            'path' => $gs,
            'version' => $return === 0 ? (implode(' ', $output)) : null,
            'exit_code' => $return,
            'output' => $output
        ];
    }

    /**
     * Get PDF info without processing it
     */
    public static function getPdfInfo($inputPath)
    {
        if (!file_exists($inputPath)) {
            return null;
        }

        $gs = self::getGhostscriptPath();
        
        // Get basic PDF info
        $cmd = "$gs -dNODISPLAY -dNOSAFER -dBATCH -dQUIET -c \"($inputPath) (r) file runpdfbegin pdfpagecount = quit\"";
        exec($cmd . " 2>&1", $output, $return);
        
        $pageCount = $return === 0 && !empty($output) ? (int)trim($output[0]) : 0;
        
        return [
            'page_count' => $pageCount,
            'file_size' => filesize($inputPath),
            'file_size_mb' => round(filesize($inputPath) / 1048576, 2)
        ];
    }
}