<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PdfToHtmlController extends AbstractController
{
    #[Route('/tools/pdf-to-html', name: 'app_tools_pdf_to_html', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/pdf_to_html.html.twig');
    }

    #[Route('/tools/pdf-to-html', name: 'app_tools_pdf_to_html_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $files = $request->files->get('pdfs', []);

        // TODO: process PDFs and convert to HTML

        return $this->json([
            'status' => 'received',
            'count' => count($files),
        ]);
    }
}
