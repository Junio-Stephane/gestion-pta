<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GuideController extends AbstractController
{
    #[Route('/guide-utilisation', name: 'app_guide_utilisation')]
    public function index(): Response
    {
        // Récupérer le rôle de l'utilisateur connecté
        $userRole = 'utilisateur';
        
        if ($this->isGranted('ROLE_ADMIN')) {
            $userRole = 'admin';
        } elseif ($this->isGranted('ROLE_CREATEUR_DE_PROJET')) {
            $userRole = 'createur';
        }
        
        return $this->render('guide/utilisation.html.twig', [
            'user_role' => $userRole,
        ]);
    }
}