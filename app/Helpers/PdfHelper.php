<?php

namespace App\Helpers;

class PdfHelper
{
    public static function compressPdf($inputPath, $outputPath, $quality = 'ebook')
    {
        if (!file_exists($inputPath)) {
            return "❌ Input file does not exist: $inputPath";
        }

        $gs = '"C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe"';

        $cmd = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/$quality "
             . "-dNOPAUSE -dBATCH -sOutputFile=\"$outputPath\" \"$inputPath\"";

        exec($cmd . " 2>&1", $output, $return);

        return [
            'command' => $cmd,
            'exit_code' => $return,
            'output' => $output,
            'output_file_exists' => file_exists($outputPath),
        ];
    }
}
