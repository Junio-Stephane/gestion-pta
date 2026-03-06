<?php

namespace App\Repository;

use App\Entity\Direction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Direction>
 */
class DirectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Direction::class);
    }

    /**
     * Trouve toutes les directions avec leur directeur
     */
    public function findAllWithDirector()
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.personnel', 'p')
            ->addSelect('p')
            ->orderBy('d.statutDirection', 'ASC') // ACTIVE d'abord
            ->addOrderBy('d.nomDirection', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les directions en attente de directeur
     */
    public function findDirectionsEnAttente()
    {
        return $this->createQueryBuilder('d')
            ->where('d.statutDirection = :statut')
            ->setParameter('statut', 'EN_ATTENTE')
            ->andWhere('d.statutDirection != :desactive')
            ->setParameter('desactive', 'DESACTIVEE')
            ->orderBy('d.nomDirection', 'ASC')
            ->getQuery()
            ->getResult();
    }


     /**
     * Vérifie si un nom de direction existe déjà (en excluant une direction spécifique pour la modification)
     */
    public function isNomDirectionUnique(string $nomDirection, ?string $excludeCodeDirection = null): bool
{
    $qb = $this->createQueryBuilder('d')
        ->where('d.nomDirection = :nomDirection')
        ->setParameter('nomDirection', $nomDirection);

    if ($excludeCodeDirection) {
        $qb->andWhere('d.CodeDirection != :codeDirection')
           ->setParameter('codeDirection', $excludeCodeDirection);
    }

    $result = $qb->getQuery()
        ->getOneOrNullResult();

    return $result === null;
}



    /**
     * Trouve les directions actives
     */
    // public function findDirectionsActives()
    // {
    //     return $this->createQueryBuilder('d')
    //         ->where('d.statutDirection = :statut')
    //         ->setParameter('statut', 'ACTIVE')
    //         ->orderBy('d.nomDirection', 'ASC')
    //         ->getQuery()
    //         ->getResult();
    // }

       

    // /**
    //  * Trouve une direction par son nom
    //  */
    // public function findByNomDirection(string $nomDirection): ?Direction
    // {
    //     return $this->createQueryBuilder('d')
    //         ->where('d.nomDirection = :nomDirection')
    //         ->setParameter('nomDirection', $nomDirection)
    //         ->getQuery()
    //         ->getOneOrNullResult();
    // }

}