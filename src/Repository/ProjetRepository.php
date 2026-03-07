<?php

namespace App\Repository;

use App\Entity\Projet;
use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Projet>
 */
class ProjetRepository extends ServiceEntityRepository
{
    private $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        parent::__construct($registry, Projet::class);
        $this->security = $security;
    }

    /**
     * Trouve tous les projets avec leurs relations, filtrés selon le rôle de l'utilisateur
     */
    public function findAllWithDetails(): array
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->orderBy('p.dateDebutPro', 'DESC');

        if ($user instanceof Personnel) {
            if ($user->hasRole('ROLE_UTILISATEUR')) {

                $subQb = $this->createQueryBuilder('p2')
                    ->select('p2.numProjet')
                    ->leftJoin('p2.personnels', 'pers2')
                    ->where('pers2.ImPer = :userId');

                $qb->andWhere($qb->expr()->in('p.numProjet', $subQb->getDQL()))
                    ->setParameter('userId', $user->getImPer());
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve un projet spécifique avec vérification des permissions
     */
    public function findWithPermissionCheck(string $numProjet): ?Projet
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->where('p.numProjet = :numProjet')
            ->setParameter('numProjet', $numProjet);


        if ($user instanceof Personnel && $user->hasRole('ROLE_UTILISATEUR')) {

            $subQb = $this->createQueryBuilder('p2')
                ->select('p2.numProjet')
                ->leftJoin('p2.personnels', 'pers2')
                ->where('pers2.ImPer = :userId')
                ->andWhere('p2.numProjet = :numProjet');

            $qb->andWhere($qb->expr()->exists($subQb->getDQL()))
                ->setParameter('userId', $user->getImPer());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Méthode optimisée pour les utilisateurs simples
     */
    public function findForSimpleUser(Personnel $user): array
    {
        $projetNumbers = $this->createQueryBuilder('p')
            ->select('p.numProjet')
            ->leftJoin('p.personnels', 'pers')
            ->where('pers.ImPer = :userId')
            ->setParameter('userId', $user->getImPer())
            ->getQuery()
            ->getResult();

        $projetNumbers = array_column($projetNumbers, 'numProjet');

        if (empty($projetNumbers)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->where('p.numProjet IN (:projetNumbers)')
            ->setParameter('projetNumbers', $projetNumbers)
            ->orderBy('p.dateDebutPro', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si l'utilisateur a accès à un projet spécifique
     */
    public function userHasAccessToProjet(string $numProjet, Personnel $user): bool
    {
        if (!$user->hasRole('ROLE_UTILISATEUR')) {
            return true;
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.personnels', 'pers')
            ->select('COUNT(p.numProjet)')
            ->where('p.numProjet = :numProjet')
            ->andWhere('pers.ImPer = :userId')
            ->setParameter('numProjet', $numProjet)
            ->setParameter('userId', $user->getImPer());

        $count = (int) $qb->getQuery()->getSingleScalarResult();
        return $count > 0;
    }

    public function findByService(string $serviceCode): array
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->where('s.CodeService = :serviceCode')
            ->setParameter('serviceCode', $serviceCode)
            ->orderBy('p.dateDebutPro', 'DESC');

        if ($user instanceof Personnel && $user->hasRole('ROLE_UTILISATEUR')) {
            $subQb = $this->createQueryBuilder('p2')
                ->select('p2.numProjet')
                ->leftJoin('p2.personnels', 'pers2')
                ->where('pers2.ImPer = :userId');

            $qb->andWhere($qb->expr()->in('p.numProjet', $subQb->getDQL()))
                ->setParameter('userId', $user->getImPer());
        }

        return $qb->getQuery()->getResult();
    }

    public function findProjetsEnCours(): array
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->where('p.StatutPro = :statut')
            ->setParameter('statut', 'En cours')
            ->orderBy('p.dateDebutPro', 'DESC');

        if ($user instanceof Personnel && $user->hasRole('ROLE_UTILISATEUR')) {
            $subQb = $this->createQueryBuilder('p2')
                ->select('p2.numProjet')
                ->leftJoin('p2.personnels', 'pers2')
                ->where('pers2.ImPer = :userId');

            $qb->andWhere($qb->expr()->in('p.numProjet', $subQb->getDQL()))
                ->setParameter('userId', $user->getImPer());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les projets où un utilisateur est assigné (avec tous les responsables)
     */
    public function findByPersonnel(Personnel $personnel): array
    {
        $projetNumbers = $this->createQueryBuilder('p')
            ->select('p.numProjet')
            ->leftJoin('p.personnels', 'pers')
            ->where('pers.ImPer = :imPer')
            ->setParameter('imPer', $personnel->getImPer())
            ->getQuery()
            ->getResult();

        $projetNumbers = array_column($projetNumbers, 'numProjet');

        if (empty($projetNumbers)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')
            ->leftJoin('s.direction', 'd')
            ->leftJoin('p.personnels', 'pers')
            ->leftJoin('p.taches', 't')
            ->leftJoin('p.createur', 'c')
            ->addSelect('s', 'd', 'pers', 't', 'c')
            ->where('p.numProjet IN (:projetNumbers)')
            ->setParameter('projetNumbers', $projetNumbers)
            ->orderBy('p.dateDebutPro', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
