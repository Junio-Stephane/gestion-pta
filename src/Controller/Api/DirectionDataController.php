<?php

namespace App\Controller\Api;

use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/directions')]
class DirectionDataController extends AbstractController
{
    #[Route('/personnels-actifs', name: 'api_directions_personnels_actifs', methods: ['GET'])]
    public function personnelsActifs(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnels = $personnelRepository->createQueryBuilder('p')
                ->leftJoin('p.directionD', 'd')
                ->where('(p.directionD IS NULL OR d.statutDirection = :desactivee OR p.StatutPer = :desactive)')
                ->andWhere('p.StatutPer IN (:statuts)')
                ->setParameter('statuts', ['ACTIF', 'DESACTIVE'])
                ->setParameter('desactivee', 'DESACTIVEE')
                ->setParameter('desactive', 'DESACTIVE')
                ->orderBy('p.StatutPer', 'DESC')
                ->addOrderBy('p.NomPer', 'ASC')
                ->addOrderBy('p.PrenomPer', 'ASC')
                ->getQuery()
                ->getResult();
            
            return $this->json([
                'success' => true,
                'personnels' => $personnels
            ], Response::HTTP_OK, [], ['groups' => ['personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/directions-en-attente', name: 'api_directions_en_attente', methods: ['GET'])]
    public function directionsEnAttente(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->findDirectionsEnAttente();
            
            return $this->json([
                'success' => true,
                'directions' => $directions
            ], Response::HTTP_OK, [], ['groups' => ['direction:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des directions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/personnels-pour-activation', name: 'api_directions_personnels_pour_activation', methods: ['GET'])]
    public function personnelsPourActivation(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnels = $personnelRepository->createQueryBuilder('p')
                ->where('p.directionD IS NULL')
                ->andWhere('p.StatutPer = :actif')
                ->setParameter('actif', 'ACTIF')
                ->orderBy('p.NomPer', 'ASC')
                ->addOrderBy('p.PrenomPer', 'ASC')
                ->getQuery()
                ->getResult();
            
            return $this->json([
                'success' => true,
                'personnels' => $personnels
            ], Response::HTTP_OK, [], ['groups' => ['personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}