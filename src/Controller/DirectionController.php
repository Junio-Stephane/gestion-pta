<?php

namespace App\Controller;

use App\Entity\Direction;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/directions')]
class DirectionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_direction_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('direction/index.html.twig');
    }

    // API ENDPOINTS

    #[Route('/api/list', name: 'api_directions_list', methods: ['GET'])]
    public function apiList(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->findAllWithDirector();
            
            return $this->json([
                'success' => true,
                'directions' => $directions
            ], 200, [], ['groups' => ['direction:read', 'personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des directions: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/create', name: 'api_direction_create', methods: ['POST'])]
    public function apiCreate(Request $request, PersonnelRepository $personnelRepository, DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $errors = [];
            
            // Validation des données
            if (empty($data['codeDirection'])) {
                $errors['codeDirection'] = 'Le code de la direction est obligatoire';
            }

            if (empty($data['nomDirection'])) {
                $errors['nomDirection'] = 'Le nom de la direction est obligatoire';
            }

            // Vérifier si le code existe déjà
            if (!empty($data['codeDirection'])) {
                $existingDirection = $this->entityManager->getRepository(Direction::class)->find($data['codeDirection']);
                if ($existingDirection) {
                    $errors['codeDirection'] = 'Une direction avec ce code existe déjà';
                }
            }

            // VÉRIFICATION DE L'UNICITÉ DU NOM
            if (!empty($data['nomDirection']) && !$directionRepository->isNomDirectionUnique($data['nomDirection'])) {
                $errors['nomDirection'] = 'Une direction avec ce nom existe déjà';
            }

            // Vérification du directeur
            if (!empty($data['personnel'])) {
                $personnel = $personnelRepository->find($data['personnel']);
                if ($personnel && $personnel->getDirectionD() && $personnel->getDirectionD()->estActive()) {
                    $errors['personnel'] = 'Ce personnel est déjà directeur d\'une direction active';
                }
            }

            // Si il y a des erreurs, les retourner toutes
            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            $direction = new Direction();
            $direction->setCodeDirection($data['codeDirection']);
            $direction->setNomDirection($data['nomDirection']);
            
            // Déterminer le statut en fonction de la présence d'un directeur
            // Gestion du directeur
if (!empty($data['personnel'])) {
    // Si "AUCUN" est sélectionné, ne pas assigner de directeur
    if ($data['personnel'] === 'AUCUN') {
        $direction->setPersonnel(null);
        $direction->setStatutDirection('EN_ATTENTE');
    } else {
        $personnel = $personnelRepository->find($data['personnel']);
        if ($personnel) {
            if ($personnel->getDirectionD() && $personnel->getDirectionD()->estActive()) {
                $errors['personnel'] = 'Ce personnel est déjà directeur d\'une direction active';
            }
            
            if (empty($errors)) {
                if ($personnel->estChefService() && $personnel->getService()) {
                    $service = $personnel->getService();
                    $service->setChefService(null);
                    $personnel->setService(null);
                    $personnel->setStatutPer('ACTIF');
                    $personnel->setFonctionPer($personnel->determinerFonction());
                } 
                  
                $direction->setPersonnel($personnel);
                $direction->setStatutDirection('ACTIVE');
                $personnel->setFonctionPer($personnel->determinerFonction());
                
                // Si le personnel était désactivé, le réactiver
                if ($personnel->estDesactive()) {
                    $personnel->activer();
                }
            }
        } else {
            $direction->setStatutDirection('EN_ATTENTE');
        }
    }
} else {
    $direction->setStatutDirection('EN_ATTENTE');
}

            $this->entityManager->persist($direction);
            $this->entityManager->flush();

            $message = $direction->getStatutDirection() === 'ACTIVE' 
                ? 'Direction créée avec succès avec directeur' 
                : 'Direction créée avec succès - En attente de directeur';

            return $this->json([
                'success' => true,
                'message' => $message,
                'direction' => $direction
            ], 201, [], ['groups' => ['direction:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{codeDirection}/modifier', name: 'api_direction_modifier', methods: ['PUT'])]
    public function apiModifierDirection(Request $request, string $codeDirection, PersonnelRepository $personnelRepository, DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $direction = $this->entityManager->getRepository(Direction::class)->find($codeDirection);
            
            if (!$direction) {
                return $this->json([
                    'success' => false,
                    'message' => 'Direction non trouvée'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);
            
            $errors = [];

            if (empty($data['nomDirection'])) {
                $errors['nomDirection'] = 'Le nom de la direction est obligatoire';
            }

            // VÉRIFICATION DE L'UNICITÉ DU NOM (en excluant la direction actuelle)
            if (!empty($data['nomDirection'])) {
                $currentNomDirection = $direction->getNomDirection();
                $newNomDirection = $data['nomDirection'];
                
                // Seulement vérifier l'unicité si le nom a changé
                if ($newNomDirection !== $currentNomDirection) {
                    if (!$directionRepository->isNomDirectionUnique($newNomDirection, $codeDirection)) {
                        $errors['nomDirection'] = 'Une direction avec ce nom existe déjà';
                    }
                }
            }

            // Vérification du directeur
            if (!empty($data['personnel'])) {
                $nouveauDirecteur = $personnelRepository->find($data['personnel']);
                if ($nouveauDirecteur && $nouveauDirecteur->getDirectionD() && $nouveauDirecteur->getDirectionD() !== $direction && $nouveauDirecteur->getDirectionD()->estActive()) {
                    $errors['personnel'] = 'Ce personnel est déjà directeur d\'une autre direction active';
                }
            }

            // Si il y a des erreurs, les retourner toutes
            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            // Modifier le nom de la direction
            $direction->setNomDirection($data['nomDirection']);

            // Gérer le changement de directeur
            $nouveauDirecteurIm = $data['personnel'] ?? null;
            $ancienDirecteur = $direction->getPersonnel();

            if ($nouveauDirecteurIm) {
    // CAS "AUCUN DIRECTEUR"
    if ($nouveauDirecteurIm === 'AUCUN') {
        if ($ancienDirecteur) {
            $ancienDirecteur->setDirectionD(null);
            $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
        }
        $direction->setPersonnel(null);
        $direction->setStatutDirection('EN_ATTENTE');
    } else {
        // Assigner un nouveau directeur
        $nouveauDirecteur = $personnelRepository->find($nouveauDirecteurIm);
        
        if (!$nouveauDirecteur) {
            return $this->json([
                'success' => false,
                'message' => 'Personnel non trouvé'
            ], 404);
        }

                // CORRECTION : Libérer d'abord l'ancien directeur AVANT d'assigner le nouveau
                if ($ancienDirecteur && $ancienDirecteur !== $nouveauDirecteur) {
                    // Réinitialiser complètement la relation
                    $ancienDirecteur->setDirectionD(null);
                    $this->entityManager->persist($ancienDirecteur);
                    $this->entityManager->flush(); // Flush intermédiaire pour libérer la contrainte
                    
                    // Mettre à jour la fonction de l'ancien directeur
                    $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
                }

                // CORRECTION : Vérifier si le nouveau directeur a déjà une direction
                if ($nouveauDirecteur->getDirectionD() && $nouveauDirecteur->getDirectionD() !== $direction) {
                    // Libérer l'ancienne direction du nouveau directeur
                    $ancienneDirectionDuNouveau = $nouveauDirecteur->getDirectionD();
                    $ancienneDirectionDuNouveau->setPersonnel(null);
                    $this->entityManager->persist($ancienneDirectionDuNouveau);
                }

                if ($nouveauDirecteur->estChefService() && $nouveauDirecteur->getService()) {
                    $service = $nouveauDirecteur->getService();
                    $service->setChefService(null);
                    // $service->setStatutService('DESACTIVE');
                }

                if ($nouveauDirecteur->getService()) {
                    $ancienService = $nouveauDirecteur->getService();
                    
                    // Si le nouveau directeur était chef de service, libérer ce poste
                    if ($ancienService->getChefService() === $nouveauDirecteur) {
                        $ancienService->setChefService(null);
                        
                        // Optionnel : désactiver le service s'il n'a plus de chef
                        // $ancienService->setStatutService('EN_ATTENTE');
                    }
                    
                    // Retirer le personnel du service
                    $nouveauDirecteur->setService(null);
                    $this->entityManager->persist($ancienService);
                }

                // Assigner le nouveau directeur
                $direction->setPersonnel($nouveauDirecteur);
                $direction->setStatutDirection('ACTIVE');

                // Mettre à jour la fonction du nouveau directeur
                $nouveauDirecteur->setFonctionPer($nouveauDirecteur->determinerFonction());
                
                // Si le nouveau directeur était désactivé, le réactiver
                if ($nouveauDirecteur->estDesactive()) {
                    $nouveauDirecteur->activer();
                }

            }
} else {
    // Cas où personnel est vide (déjà géré)
    if ($ancienDirecteur) {
        $ancienDirecteur->setDirectionD(null);
        $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
    }
    $direction->setPersonnel(null);
    $direction->setStatutDirection('EN_ATTENTE');
}

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Direction modifiée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{codeDirection}/desactiver', name: 'api_direction_desactiver', methods: ['POST'])]
    public function apiDesactiverDirection(string $codeDirection): JsonResponse
    {
        try {
            $direction = $this->entityManager->getRepository(Direction::class)->find($codeDirection);
            
            if (!$direction) {
                return $this->json([
                    'success' => false,
                    'message' => 'Direction non trouvée'
                ], 404);
            }

            if ($direction->estDesactivee()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cette direction est déjà désactivée'
                ], 400);
            }

            // Utiliser la méthode de désactivation en cascade
            $direction->desactiverAvecCascade();

            $this->entityManager->flush();

            // Compter les éléments désactivés
            $servicesDesactives = $direction->getServices()->count();
            $personnelsDesactives = 0;
            $projetsDesactives = 0;
            $tachesDesactivees = 0;
            foreach ($direction->getServices() as $service) {
                $personnelsDesactives += $service->getPersonnels()->count();
                $projetsDesactives += $service->getProjets()->count();

                foreach ($service->getProjets() as $projet) {
                    $tachesDesactivees += $projet->getTaches()->count();
                }

                if ($service->getChefService()) {
                    $personnelsDesactives++;
                }
            }

            // Ajouter le directeur
            if ($direction->getPersonnel()) {
                $personnelsDesactives++;
            }

            return $this->json([
                'success' => true,
                'message' => 'Direction désactivée avec succès',
                'statistiques' => [
                    'servicesDesactives' => $servicesDesactives,
                    'projetsDesactives' => $projetsDesactives,
                    'tachesDesactivees' => $tachesDesactivees,
                    'personnelsDesactives' => $personnelsDesactives
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la désactivation: ' . $e->getMessage()
            ], 500);
        }
    }

#[Route('/api/{codeDirection}/activer', name: 'api_direction_activer', methods: ['POST'])]
public function apiActiverDirection(Request $request, string $codeDirection, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        $direction = $this->entityManager->getRepository(Direction::class)->find($codeDirection);
        
        if (!$direction) {
            return $this->json([
                'success' => false,
                'message' => 'Direction non trouvée'
            ], 404);
        }

        if (!$direction->estDesactivee()) {
            return $this->json([
                'success' => false,
                'message' => 'Cette direction n\'est pas désactivée'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);
        $garderDirecteur = $data['garderDirecteur'] ?? false;
        $nouveauDirecteurIm = $data['nouveauDirecteur'] ?? null;

        // Activer la direction
        $direction->activer();

        // Gérer le directeur
        $ancienDirecteur = $direction->getPersonnel();
        $ancienDirecteurDisponible = $ancienDirecteur && $ancienDirecteur->estDesactive();

        if ($garderDirecteur && $ancienDirecteurDisponible) {
            // Réactiver l'ancien directeur
            $ancienDirecteur->activer();
            $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
        } elseif ($nouveauDirecteurIm !== null && $nouveauDirecteurIm !== '') {
            // CAS "AUCUN DIRECTEUR"
            if ($nouveauDirecteurIm === 'AUCUN') {
                if ($ancienDirecteur) {
                    $ancienDirecteur->setDirectionD(null);
                    $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
                }
                $direction->setPersonnel(null);
                $direction->setStatutDirection('EN_ATTENTE');
            } else {
                // Assigner un nouveau directeur
                $nouveauDirecteur = $personnelRepository->find($nouveauDirecteurIm);
                
                if (!$nouveauDirecteur) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Personnel non trouvé'
                    ], 404);
                }

                // Vérifier si le nouveau directeur n'est pas déjà directeur d'une direction active
                if ($nouveauDirecteur->getDirectionD() && $nouveauDirecteur->getDirectionD()->estActive()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Ce personnel est déjà directeur d\'une autre direction active'
                    ], 400);
                }

                if ($nouveauDirecteur->estDesactive()) {
                    $nouveauDirecteur->activer();
                }

                // Libérer l'ancien directeur
                if ($ancienDirecteur && $ancienDirecteur !== $nouveauDirecteur) {
                    $ancienDirecteur->setDirectionD(null);
                    $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
                }

                // Gérer le cas où le nouveau directeur était chef de service
                if ($nouveauDirecteur->estChefService() && $nouveauDirecteur->getService()) {
                    $service = $nouveauDirecteur->getService();
                    $service->setChefService(null);
                    $nouveauDirecteur->setService(null);
                    $nouveauDirecteur->setStatutPer('ACTIF');
                    $this->entityManager->persist($service);
                }

                $direction->setPersonnel($nouveauDirecteur);
                $nouveauDirecteur->setFonctionPer($nouveauDirecteur->determinerFonction());
            }
        } else {
            // Pas de directeur sélectionné (bouton radio "Aucun directeur")
            if ($ancienDirecteur) {
                $ancienDirecteur->setDirectionD(null);
                $ancienDirecteur->setFonctionPer($ancienDirecteur->determinerFonction());
            }
            $direction->setPersonnel(null);
            $direction->setStatutDirection('EN_ATTENTE');
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Direction activée avec succès'
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de l\'activation: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/mutation', name: 'api_direction_mutation', methods: ['POST'])]
    public function apiMutation(Request $request, DirectionRepository $directionRepository, PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (empty($data['directeur']) || empty($data['nouvelleDirection'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le directeur et la nouvelle direction sont requis'
                ], 400);
            }

            $directeur = $personnelRepository->find($data['directeur']);
            $nouvelleDirection = $directionRepository->find($data['nouvelleDirection']);

            if (!$directeur || !$nouvelleDirection) {
                return $this->json([
                    'success' => false,
                    'message' => 'Directeur ou direction non trouvé'
                ], 404);
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

            $directeur->setFonctionPer($directeur->determinerFonction());

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Mutation effectuée avec succès'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mutation: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/personnels-selections', name: 'api_personnels_selections', methods: ['GET'])]
    public function apiPersonnelsActifs(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnels = $personnelRepository->findDirecteursListSelect();

            return $this->json([
                'success' => true,
                'personnels' => $personnels
            ], 200, [], ['groups' => ['personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels'
            ], 500);
        }
    }

    #[Route('/api/directions-en-attente', name: 'api_directions_en_attente', methods: ['GET'])]
    public function apiDirectionsEnAttente(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->findDirectionsEnAttente();
            
            return $this->json([
                'success' => true,
                'directions' => $directions
            ], 200, [], ['groups' => ['direction:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des directions'
            ], 500);
        }
    }

    #[Route('/api/personnels-pour-activation', name: 'api_personnels_pour_activation', methods: ['GET'])]
public function apiPersonnelsPourActivation(Request $request, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        // Récupérer le code de direction depuis les paramètres de requête si disponible
        $codeDirection = $request->query->get('directionCode');
        $ancienDirecteurIm = null;
        
        // Si on a un code direction, récupérer l'ancien directeur
        if ($codeDirection) {
            $direction = $this->entityManager->getRepository(Direction::class)->find($codeDirection);
            if ($direction && $direction->getPersonnel()) {
                $ancienDirecteurIm = $direction->getPersonnel()->getImPer();
            }
        }

        // Récupérer les personnels sans direction OU l'ancien directeur s'il existe
        $qb = $personnelRepository->createQueryBuilder('p')
            ->leftJoin('p.directionD', 'd')
            ->where('((p.FonctionPer != :directeur AND p.FonctionPer != :chef_service) OR p.StatutPer = :desactive)')
            ->andWhere('p.StatutPer != :en_attente')
            ->setParameter('directeur', 'directeur')
            ->setParameter('chef_service', 'chef_service')
            ->setParameter('desactive', 'DESACTIVE')
            ->setParameter('en_attente', 'EN_ATTENTE')
            ->orderBy('p.StatutPer', 'DESC') // ACTIF d'abord
            ->addOrderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC');

        // Si on a un ancien directeur, l'inclure dans les résultats
        if ($ancienDirecteurIm) {
            $qb->orWhere('p.ImPer = :ancienDirecteur')
               ->setParameter('ancienDirecteur', $ancienDirecteurIm);
        }

        $personnels = $qb->getQuery()->getResult();
        
        return $this->json([
            'success' => true,
            'personnels' => $personnels,
            'ancienDirecteurIm' => $ancienDirecteurIm
        ], 200, [], ['groups' => ['personnel:read']]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des personnels'
        ], 500);
    }
}
}

// #[Route('/api/personnels-pour-modification', name: 'api_personnels_pour_modification', methods: ['GET'])]
// public function apiPersonnelsPourModification(PersonnelRepository $personnelRepository): JsonResponse
// {
//     try {
//         // Récupère tous les personnels (actifs et désactivés) qui ne sont PAS directeurs actifs
//         $personnels = $personnelRepository->createQueryBuilder('p')
//             ->leftJoin('p.directionD', 'd')
//             ->where('(p.directionD IS NULL OR d.statutDirection = :desactivee OR p.StatutPer = :desactive)')
//             ->andWhere('p.StatutPer IN (:statuts)')
//             ->setParameter('statuts', ['ACTIF', 'DESACTIVE'])
//             ->setParameter('desactivee', 'DESACTIVEE')
//             ->setParameter('desactive', 'DESACTIVE')
//             ->orderBy('p.StatutPer', 'DESC') // ACTIF d'abord
//             ->addOrderBy('p.NomPer', 'ASC')
//             ->addOrderBy('p.PrenomPer', 'ASC')
//             ->getQuery()
//             ->getResult();
        
//         return $this->json([
//             'success' => true,
//             'personnels' => $personnels
//         ], 200, [], ['groups' => ['personnel:read']]);
//     } catch (\Exception $e) {
//         return $this->json([
//             'success' => false,
//             'message' => 'Erreur lors du chargement des personnels'
//         ], 500);
//     }
// }

    // ENDPOINTS POUR LES DONNEES

// #[Route('/api/personnels-sans-direction', name: 'api_personnels_sans_direction', methods: ['GET'])]
// public function apiPersonnelsSansDirection(PersonnelRepository $personnelRepository): JsonResponse
// {
//     try {
//         // Inclure les personnels désactivés mais sans direction active
//         $personnels = $personnelRepository->createQueryBuilder('p')
//             ->leftJoin('p.directionD', 'd')
//             ->where('(p.directionD IS NULL OR d.statutDirection = :desactivee)')
//             ->andWhere('p.StatutPer IN (:statuts)')
//             ->setParameter('statuts', ['ACTIF', 'DESACTIVE'])
//             ->setParameter('desactivee', 'DESACTIVEE')
//             ->orderBy('p.StatutPer', 'DESC') // ACTIF d'abord
//             ->addOrderBy('p.NomPer', 'ASC')
//             ->addOrderBy('p.PrenomPer', 'ASC')
//             ->getQuery()
//             ->getResult();
        
//         return $this->json([
//             'success' => true,
//             'personnels' => $personnels
//         ], 200, [], ['groups' => ['personnel:read']]);
//     } catch (\Exception $e) {
//         return $this->json([
//             'success' => false,
//             'message' => 'Erreur lors du chargement des personnels'
//         ], 500);
//     }
// }