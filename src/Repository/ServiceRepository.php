<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Trouve tous les services avec leurs relations
     */
    public function findAllWithRelations()
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('s.chefService', 'cs')
            ->addSelect('d', 'cs')
            ->orderBy('s.statutService', 'ASC')
            ->addOrderBy('s.nomService', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un nom de service est unique
     */
    public function isNomServiceUnique(string $nomService, ?string $codeService = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.nomService = :nomService')
            ->setParameter('nomService', $nomService);

        if ($codeService !== null) {
            $qb->andWhere('s.CodeService != :codeService')
                ->setParameter('codeService', $codeService);
        }

        $result = $qb->getQuery()->getResult();

        return count($result) === 0;
    }

    /**
     * Trouve les services en attente (sans chef de service)
     */
    public function findServicesEnAttente()
    {
        return $this->createQueryBuilder('s')
            ->where('s.chefService IS NULL')
            ->andWhere('s.statutService = :actif')
            ->setParameter('actif', 'ACTIF')
            ->orderBy('s.nomService', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les personnels disponibles pour être chef de service
     */
    public function findPersonnelsPourChefService()
    {
        return $this->createQueryBuilder('s')
            ->select('p')
            ->from(Personnel::class, 'p')
            ->leftJoin('p.directionD', 'd', Join::WITH, 'd.statutDirection = :active')
            ->leftJoin('p.service', 'serv', Join::WITH, 'serv.chefService = p AND serv.statutService = :active')
            ->where('p.StatutPer = :actif')
            ->andWhere('d.id IS NULL')
            ->andWhere('serv.id IS NULL')
            ->setParameter('actif', 'ACTIF')
            ->setParameter('active', 'ACTIVE')
            ->orderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les services d'une direction spécifique
     */
    public function findByDirection(string $codeDirection)
    {
        return $this->createQueryBuilder('s')
            ->join('s.direction', 'd')
            ->where('d.CodeDirection = :codeDirection')
            ->setParameter('codeDirection', $codeDirection)
            ->orderBy('s.nomService', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findChefsServiceDisponibles(?string $excludeServiceCode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.chefService', 'c')
            ->where('s.statutService = :actif OR s.statutService = :desactive')
            ->andWhere('c IS NOT NULL')
            ->setParameter('actif', 'ACTIF')
            ->setParameter('desactive', 'DESACTIVE');

        if ($excludeServiceCode) {
            $qb->andWhere('s.CodeService != :excludeService')
                ->setParameter('excludeService', $excludeServiceCode);
        }

        return $qb->getQuery()->getResult();
    }

    public function findServicesEnAttenteTable(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.chefService', 'c')
            ->where('s.chefService IS NULL')
            ->andWhere('s.statutService = :statut')
            ->setParameter('statut', 'EN_ATTENTE')
            ->orderBy('s.nomService', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
