<?php

namespace App\Helpers;

class PdfHelper
{
    /**
     * Get the Ghostscript binary path based on the environment
     *
     * @return string
     */
    private static function getGhostscriptPath()
    {
        // Check if we're on Windows (for local development)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return '"C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe"';
        }
        
        // For Linux/EC2, try common paths
        $possiblePaths = [
            '/usr/bin/gs',           // Most common on Ubuntu/Debian
            '/usr/local/bin/gs',     // Alternative installation path
            '/opt/gs/bin/gs',        // Custom installation path
            'gs'                     // If it's in the PATH
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path === 'gs') {
                // Check if gs is available in PATH
                exec('which gs 2>/dev/null', $output, $return);
                if ($return === 0 && !empty($output)) {
                    return 'gs';
                }
            } else {
                // Check if the specific path exists
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        // Fallback to 'gs' and let it fail if not found
        return 'gs';
    }

    /**
     * Compress PDF file using Ghostscript
     *
     * @param string $inputPath
     * @param string $outputPath
     * @param string $quality
     * @return array|string
     */
    public static function compressPdf($inputPath, $outputPath, $quality = 'ebook')
    {
        if (!file_exists($inputPath)) {
            return "❌ Input file does not exist: $inputPath";
        }

        // Get the appropriate Ghostscript path
        $gs = self::getGhostscriptPath();

        // Build the command
        $cmd = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/$quality "
             . "-dNOPAUSE -dBATCH -dQUIET -sOutputFile=\"$outputPath\" \"$inputPath\"";

        // Execute the command
        exec($cmd . " 2>&1", $output, $return);

        return [
            'command' => $cmd,
            'exit_code' => $return,
            'output' => $output,
            'output_file_exists' => file_exists($outputPath),
            'gs_path' => $gs
        ];
    }

    /**
     * Check if Ghostscript is available on the system
     *
     * @return array
     */
    public static function checkGhostscriptAvailability()
    {
        $gs = self::getGhostscriptPath();
        
        // Test if Ghostscript is working
        exec("$gs --version 2>&1", $output, $return);
        
        return [
            'available' => $return === 0,
            'path' => $gs,
            'version' => $return === 0 ? (implode(' ', $output)) : null,
            'exit_code' => $return,
            'output' => $output
        ];
    }
}