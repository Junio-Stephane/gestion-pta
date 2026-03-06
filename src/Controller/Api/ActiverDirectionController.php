<?php

namespace App\Controller\Api;

use App\Entity\Direction;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiverDirectionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PersonnelRepository $personnelRepository
    ) {}

    public function __invoke(Direction $direction, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$direction->estDesactivee()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Cette direction n\'est pas désactivée'
                ], Response::HTTP_BAD_REQUEST);
            }

            $garderDirecteur = $data['garderDirecteur'] ?? false;
            $nouveauDirecteurIm = $data['nouveauDirecteur'] ?? null;

            // Activer la direction
            $direction->activer();

            // Gérer le directeur
            $ancienDirecteur = $direction->getPersonnel();
            $ancienDirecteurDisponible = $ancienDirecteur && $ancienDirecteur->estDesactive();

            if ($garderDirecteur && $ancienDirecteurDisponible) {
                $ancienDirecteur->activer();
            } elseif ($nouveauDirecteurIm && $nouveauDirecteurIm !== '') {
                $nouveauDirecteur = $this->personnelRepository->find($nouveauDirecteurIm);
                
                if (!$nouveauDirecteur) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Personnel non trouvé'
                    ], Response::HTTP_NOT_FOUND);
                }

                if ($nouveauDirecteur->getDirectionD() && $nouveauDirecteur->getDirectionD()->estActive()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Ce personnel est déjà directeur d\'une autre direction active'
                    ], Response::HTTP_BAD_REQUEST);
                }

                if ($ancienDirecteur && $ancienDirecteur !== $nouveauDirecteur) {
                    $ancienDirecteur->setDirectionD(null);
                }

                $direction->setPersonnel($nouveauDirecteur);
            } else {
                if ($ancienDirecteur) {
                    $ancienDirecteur->setDirectionD(null);
                }
                $direction->setPersonnel(null);
                $direction->setStatutDirection('EN_ATTENTE');
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Direction activée avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'activation: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}