<?php

namespace App\Action\Tools;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tools/pdf-to-html', name: 'tools.pdf-to-html.convert', methods: ['POST'])]
class ConvertPdfToHtml extends AbstractController
{
    public function __invoke(Request $request)
    {
        $files = $request->files->get('files', []);

        if (!is_array($files)) {
            return $this->json(['error' => 'Invalid file upload'], 400);
        }

        if (count($files) === 0) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $paths = array_map(fn($file) => $file->getPathname(), $files);

        $id = uniqid(true);

        $merger = new Process(
            array_merge(
                ['qpdf', '--empty', '--pages'],
                $paths,
                ['--', "/tmp/$id.pdf"]
            )
        );
        $merger->run();

        $converter = new Process(['pdf2htmlEX', '--dest-dir', "/tmp/output", "/tmp/$id.pdf"]);
        $converter->run();

        if (!$converter->isSuccessful()) {
            return $this->json(['error' => 'Conversion failed: ' . $converter->getErrorOutput()], 500);
        }

        $response = new BinaryFileResponse("/tmp/output/$id.html");
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "$id.html");
        $response->deleteFileAfterSend(true);

        return $response;
    }
}