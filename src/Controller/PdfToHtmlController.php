<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PdfToHtmlController extends AbstractController
{
    #[Route('/tools/pdf-to-html', name: 'tools.pdf-to-html', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/pdf_to_html.html.twig');
    }
}
