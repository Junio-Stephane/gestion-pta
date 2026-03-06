<?php

namespace App\Controller\Api;

use App\Entity\Direction;
use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MutationDirecteurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectionRepository $directionRepository,
        private PersonnelRepository $personnelRepository
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (empty($data['directeur']) || empty($data['nouvelleDirection'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le directeur et la nouvelle direction sont requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $directeur = $this->personnelRepository->find($data['directeur']);
            $nouvelleDirection = $this->directionRepository->find($data['nouvelleDirection']);

            if (!$directeur || !$nouvelleDirection) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Directeur ou direction non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Récupérer l'ancienne direction du directeur
            $ancienneDirection = $directeur->getDirectionD();

            // Mettre l'ancienne direction en attente
            if ($ancienneDirection) {
                $ancienneDirection->setPersonnel(null);
                $ancienneDirection->setStatutDirection('EN_ATTENTE');
            }

            // Assigner le directeur à la nouvelle direction
            $nouvelleDirection->setPersonnel($directeur);
            $nouvelleDirection->setStatutDirection('ACTIVE');

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Mutation effectuée avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la mutation: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}