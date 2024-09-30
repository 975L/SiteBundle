<?php

namespace c975L\SiteBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AssetController extends AbstractController
{
    #[Route(
        '/asset/{file}',
        requirements: ['file' => '^.*$'],
        name: 'asset_file',
        methods: ['GET']
    )]
    public function assetFile(string $file): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $file;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($file)
        );
        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}