<?php

namespace App\Service\Pdf;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;

class PdfConverter
{
    public function html(File $source): File
    {
        $filename = Path::getFilenameWithoutExtension($source->getFilename());
        $basedir = Path::getDirectory($source->getPathname());

        $outputFilename = "$filename.html";
        $output = "$basedir/$outputFilename";

        $converter = new Process([
            'pdf2htmlEX',
            '--dest-dir', $basedir,
            $source->getPathname(),
            $outputFilename,
        ]);
        $converter->run();

        return new File($output);
    }
}
