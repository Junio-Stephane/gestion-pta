<?php

namespace App\Repository;

use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Personnel>
 */
class PersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personnel::class);
    }

    /**
     * Trouve les personnels qui ne sont pas directeurs
     */
    // public function findPersonnelsSansDirection()
    // {
    //     return $this->createQueryBuilder('p')
    //         ->Where('(p.FonctionPer != :directeur AND p.FonctionPer != :chef_service) OR p.StatutPer = :desactive')
    //         ->setParameter('directeur', 'directeur')
    //         ->setParameter('chef_service', 'chef_service')
    //         ->setParameter('desactive', 'DESACTIVE')
    //         ->orderBy('p.NomPer', 'ASC')
    //         ->addOrderBy('p.PrenomPer', 'ASC')
    //         ->getQuery()
    //         ->getResult();
    // }

    /**
     * Trouve les directeurs actuels
     */
    public function findDirecteursListSelect()
    {
        return $this->createQueryBuilder('p')
            ->where('((p.FonctionPer != :directeur AND p.FonctionPer != :chef_service) OR p.StatutPer = :desactive)')
            ->andWhere('p.StatutPer != :en_attente')
            ->setParameter('directeur', 'directeur')
            ->setParameter('chef_service', 'chef_service')
            ->setParameter('desactive', 'DESACTIVE')
            ->setParameter('en_attente', 'EN_ATTENTE')
            ->orderBy('p.StatutPer', 'DESC')
            ->addOrderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'sd')
            ->leftJoin('p.directionD', 'd')
            ->addSelect('s', 'sd', 'd')
            ->where('p.StatutPer NOT IN (:excludedStatuses)')
            ->andWhere('p.RolePer != :roleAttente')
            ->setParameter('excludedStatuses', ['EN_ATTENTE', 'REJETE'])
            ->setParameter('roleAttente', 'ROLE_EN_ATTENTE')
            ->orderBy('p.StatutPer', 'ASC')
            ->addOrderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les personnels actifs avec leurs relations
     */
    public function findPersonnelsActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 's', 'd')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->where('p.StatutPer = :statut')
            ->setParameter('statut', 'ACTIF')
            ->orderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findPersonnelsActifsEtDesactives(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 's', 'd') 
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->where('p.StatutPer = :actif OR p.StatutPer = :desactive')
            ->setParameter('actif', 'ACTIF')
            ->setParameter('desactive', 'DESACTIVE')
            ->orderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

/**
 * Trouve les personnels actifs
 */
    // public function findPersonnelsActifs()
    // {
    //     return $this->createQueryBuilder('p')
    //         ->where('p.StatutPer = :statut')
    //         ->setParameter('statut', 'ACTIF')
    //         ->orderBy('p.NomPer', 'ASC')
    //         ->addOrderBy('p.PrenomPer', 'ASC')
    //         ->getQuery()
    //         ->getResult();
    // }
