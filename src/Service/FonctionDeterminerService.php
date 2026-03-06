<?php

namespace App\Service;

use App\Entity\Personnel;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class FonctionDeterminerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository
    ) {}

    public function determinerFonction(Personnel $personnel): string
    {
        // D'abord vérifier si c'est un directeur
        if ($personnel->getDirectionD() !== null) {
            return 'Directeur';
        }
        
        // Ensuite vérifier si c'est un chef de service dans n'importe quel service
        // en utilisant l'immatricule (ImPer)
        $serviceOuIlEstChef = $this->serviceRepository->createQueryBuilder('s')
            ->join('s.chefService', 'cs')
            ->where('cs.ImPer = :imPer')
            ->setParameter('imPer', $personnel->getImPer())
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($serviceOuIlEstChef !== null) {
            return 'Chef_service';
        }
        
        // Enfin, si il a un service mais n'est pas chef, c'est un agent
        if ($personnel->getService() !== null) {
            return 'Agent';
        }
        
        // Par défaut
        return 'Agent';
    }
}