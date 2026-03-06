<?php

namespace App\Controller\Api;

use App\Entity\Direction;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DesactiverDirectionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository
    ) {}

    public function __invoke(Direction $direction, Request $request): JsonResponse
    {
        try {
            if ($direction->estDesactivee()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Cette direction est déjà désactivée'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Désactiver la direction
            $direction->desactiver();

            // Désactiver le directeur s'il existe
            $directeur = $direction->getPersonnel();
            if ($directeur) {
                $directeur->desactiver();
            }

            // Désactiver tous les services et personnels
            $services = $this->serviceRepository->findBy(['direction' => $direction]);
            $personnelsDesactives = 0;

            foreach ($services as $service) {
                $service->desactiver();
                
                foreach ($service->getPersonnels() as $personnel) {
                    $personnel->desactiver();
                    $personnelsDesactives++;
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Direction désactivée avec succès',
                'servicesDesactives' => count($services),
                'personnelsDesactives' => $personnelsDesactives
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la désactivation: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}