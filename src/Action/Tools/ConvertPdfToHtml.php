<?php

namespace App\Action\Tools;

use App\Service\Pdf\PdfConverter;
use App\Service\Pdf\PdfMerger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tools/pdf-to-html', name: 'tools.pdf-to-html.convert', methods: ['POST'])]
class ConvertPdfToHtml extends AbstractController
{
    public function __invoke(
        Request $request,
        PdfMerger $merger,
        PdfConverter $converter,
    ): BinaryFileResponse|JsonResponse {
        /** @var array<UploadedFile> $files */
        $files = $request->files->get('files', []);

        if (!is_array($files)) {
            return $this->json(['error' => 'Invalid file upload'], 400);
        }

        if (0 === count($files)) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $merged = $merger->merge($files);

        $converted = $converter->html($merged);

        $response = new BinaryFileResponse($converted);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $converted->getFilename());
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
