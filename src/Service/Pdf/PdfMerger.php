<?php

namespace App\Service\Pdf;

use App\Storage\TemporaryStorage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;

class PdfMerger
{
    public function __construct(
        private TemporaryStorage $storage,
    ) {
    }

    /**
     * Merge multiple PDF files into a single PDF using qpdf.
     *
     * @return File the file resulting from the merge
     */
    public function merge(array $files): File
    {
        $id = uniqid("", true);

        $output = $this->storage->path("$id.pdf");

        $merger = new Process(
            array_merge(
                ['qpdf', '--empty', '--pages'],
                array_map(fn ($file) => $file->getPathname(), $files),
                ['--', $output]
            )
        );
        $merger->run();

        return new File($output);
    }
}
