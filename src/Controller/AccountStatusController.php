<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountStatusController extends AbstractController
{
    #[Route('/compte-desactive', name: 'app_compte_desactive')]
    public function compteDesactive(): Response
    {
        return $this->render('account_status/desactive.html.twig');
    }

    #[Route('/compte-en-attente', name: 'app_compte_en_attente')]
    public function compteEnAttente(): Response
    {
        return $this->render('account_status/en_attente.html.twig');
    }

    #[Route('/compte-refuse', name: 'app_compte_refuse')]
    public function compteRefuse(): Response
    {
        return $this->render('account_status/refuse.html.twig');
    }
}