<?php

namespace App\Controller;

use App\Entity\Tache;
use App\Entity\Projet;
use App\Entity\Personnel;
use App\Repository\TacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/taches')]
class TacheController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/projet/{numProjet}', name: 'app_tache_index', methods: ['GET'])]
    public function index(string $numProjet): Response
    {
        // Vérifier que le projet existe
        $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
        if (!$projet) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        return $this->render('tache/index.html.twig', [
            'numProjet' => $numProjet
        ]);
    }

    

    /**
     * Vérifie si l'utilisateur a accès au projet (lecture)
     * TOUS les utilisateurs connectés ont accès en lecture
     */
    private function checkProjetAccess(Projet $projet): bool
    {
        $user = $this->getUser();
        
        // Tout utilisateur connecté a accès en lecture
        return $user instanceof Personnel;
    }

    /**
     * Vérifie si l'utilisateur peut MODIFIER le projet
     */

private function canManageProjet(Projet $projet): bool
{
    $user = $this->getUser();
    
    if (!$user instanceof Personnel) {
        return false;
    }

    // Admin peut tout gérer
    if ($user->hasRole('ROLE_ADMIN')) {
        return true;
    }

    // Créateur peut gérer SES PROPRES projets
    if ($user->hasRole('ROLE_CREATEUR_DE_PROJET')) {
        // Vérifier si l'utilisateur est le créateur du projet
        if ($projet->getCreateur() === $user) {
            return true; // ✅ Peut gérer SON projet même s'il n'est pas responsable
        }
        // Pour les projets des autres : doit être désigné comme responsable
        return $projet->getPersonnels()->contains($user);
    }

    // Les utilisateurs normaux (ROLE_UTILISATEUR) peuvent gérer les projets où ils sont assignés
    if ($user->hasRole('ROLE_UTILISATEUR') && $projet->getPersonnels()->contains($user)) {
        return true;
    }

    return false;
}

    // API ENDPOINTS

    #[Route('/api/projet/{numProjet}/list', name: 'api_taches_list', methods: ['GET'])]
    public function apiList(string $numProjet): JsonResponse
    {
        try {
            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
            
            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            // Vérifier l'accès en lecture au projet
            if (!$this->checkProjetAccess($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce projet'
                ], 403);
            }

            $taches = $projet->getTaches();

            return $this->json([
                'success' => true,
                'projet' => $projet,
                'taches' => $taches,
                'canManage' => $this->canManageProjet($projet) // Info de permission pour le frontend
            ], 200, [], ['groups' => ['projet:read', 'tache:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des tâches: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numTache}', name: 'api_tache_get', methods: ['GET'])]
    public function apiGet(string $numTache): JsonResponse
    {
        try {
            $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
            
            if (!$tache) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            $projet = $tache->getProjet();
            
            // Vérifier l'accès en lecture
            if (!$this->checkProjetAccess($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à cette tâche'
                ], 403);
            }

            return $this->json([
                'success' => true,
                'tache' => $tache,
                'projetDates' => [
                    'dateDebut' => $projet->getDateDebutPro()?->format('Y-m-d'),
                    'dateFin' => $projet->getDateFinPro()?->format('Y-m-d')
                ],
                'canManage' => $this->canManageProjet($projet)
            ], 200, [], ['groups' => ['tache:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la tâche: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/projet/{numProjet}/dates', name: 'api_projet_dates', methods: ['GET'])]
    public function apiGetProjetDates(string $numProjet): JsonResponse
    {
        try {
            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
            
            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            // Vérifier l'accès en lecture
            if (!$this->checkProjetAccess($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce projet'
                ], 403);
            }

            return $this->json([
                'success' => true,
                'dateDebut' => $projet->getDateDebutPro()?->format('Y-m-d'),
                'dateFin' => $projet->getDateFinPro()?->format('Y-m-d')
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des dates du projet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validation des dates des tâches par rapport au projet
     */
    private function validateTacheDates(Tache $tache, array &$errors): bool
    {
        $projet = $tache->getProjet();
        
        if (!$projet) {
            return true; // Pas de projet, pas de validation
        }

        $dateDebutTache = $tache->getDateDebutTache();
        $dateFinTache = $tache->getDateFinTache();
        $dateDebutProjet = $projet->getDateDebutPro();
        $dateFinProjet = $projet->getDateFinPro();

        // Vérifier que la date de début de la tâche est >= date début projet
        if ($dateDebutProjet && $dateDebutTache < $dateDebutProjet) {
            $errors['dateDebutTache'] = 'La date de début de la tâche ne peut pas être antérieure au début du projet (' . $dateDebutProjet->format('d/m/Y') . ')';
        }

        if ($dateFinProjet && $dateFinTache && $dateFinTache > $dateFinProjet) {
            $errors['dateFinTache'] = 'La date de fin de la tâche ne peut pas être postérieure à la fin du projet (' . $dateFinProjet->format('d/m/Y') . ')';
        }

        return empty($errors);
    }

    #[Route('/api/create', name: 'api_tache_create', methods: ['POST'])]
    public function apiCreate(Request $request, ValidatorInterface $validator): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $errors = [];

            // Vérifier si le projet existe
            $projet = $this->entityManager->getRepository(Projet::class)->find($data['numProjet']);
            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à créer des tâches pour ce projet'
                ], 403);
            }

            if ($projet->getStatutPro() === 'Suspendu') {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de créer une tâche pour un projet suspendu'
                ], 400);
            }

            // Validation des données de base (plus besoin de vérifier numTache)
            if (empty($data['titreTache'])) {
                $errors['titreTache'] = 'Le titre de la tâche est obligatoire';
            }

            if (empty($data['dateDebutTache'])) {
                $errors['dateDebutTache'] = 'La date de début est obligatoire';
            }

            if (empty($data['numProjet'])) {
                $errors['numProjet'] = 'Le projet est obligatoire';
            }

            // Vérification de la cohérence des dates entre début et fin
            if (!empty($data['dateDebutTache']) && !empty($data['dateFinTache'])) {
                $dateDebut = new \DateTime($data['dateDebutTache']);
                $dateFin = new \DateTime($data['dateFinTache']);
                
                if ($dateFin < $dateDebut) {
                    $errors['dateFinTache'] = 'La date de fin doit être postérieure à la date de début';
                }
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            $tache = new Tache();
            // Plus besoin de setNumTache - il sera généré automatiquement
            $tache->setTitreTache($data['titreTache']);
            $tache->setDescriptionTache($data['descriptionTache'] ?? null);
            $tache->setCommentaireTache($data['commentaireTache'] ?? null);
            $tache->setDateDebutTache(new \DateTime($data['dateDebutTache']));
            
            if (!empty($data['dateFinTache'])) {
                $tache->setDateFinTache(new \DateTime($data['dateFinTache']));
            }

            // Assigner le projet
            $projet = $this->entityManager->getRepository(Projet::class)->find($data['numProjet']);
            if ($projet) {
                $tache->setProjet($projet);
            }

            // Statut initial
            $tache->setStatutTache('Débuté');
            $tache->setavancementTache(0);

            // VALIDATION DES DATES PAR RAPPORT AU PROJET
            if (!$this->validateTacheDates($tache, $errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs de dates ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            // Validation Symfony
            $validationErrors = $validator->validate($tache);
            if (count($validationErrors) > 0) {
                foreach ($validationErrors as $error) {
                    $errors[$error->getPropertyPath()] = $error->getMessage();
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs de validation ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            $this->entityManager->persist($tache);
            $this->entityManager->flush();

            if ($projet) {
                $projet->updateAvancementAndStatut();
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Tâche créée avec succès',
                'tache' => $tache
            ], 201, [], ['groups' => ['tache:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numTache}/modifier', name: 'api_tache_modifier', methods: ['PUT'])]
    public function apiModifier(Request $request, int $numTache, ValidatorInterface $validator): JsonResponse
    {
        try {
            $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
            
            if (!$tache) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            $projet = $tache->getProjet();

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier cette tâche'
                ], 403);
            }

            if ($projet && $projet->getStatutPro() === 'Suspendu') {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de modifier une tâche d\'un projet suspendu'
                ], 400);
            }

            $data = json_decode($request->getContent(), true);
            
            $errors = [];

            // Validation des données
            if (empty($data['titreTache'])) {
                $errors['titreTache'] = 'Le titre de la tâche est obligatoire';
            }

            if (empty($data['dateDebutTache'])) {
                $errors['dateDebutTache'] = 'La date de début est obligatoire';
            }

            // Vérification de la cohérence des dates entre début et fin
            if (!empty($data['dateDebutTache']) && !empty($data['dateFinTache'])) {
                $dateDebut = new \DateTime($data['dateDebutTache']);
                $dateFin = new \DateTime($data['dateFinTache']);
                
                if ($dateFin < $dateDebut) {
                    $errors['dateFinTache'] = 'La date de fin doit être postérieure à la date de début';
                }
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            // Mettre à jour les propriétés (ne pas toucher à numTache)
            $tache->setTitreTache($data['titreTache']);
            $tache->setDescriptionTache($data['descriptionTache'] ?? null);
            $tache->setCommentaireTache($data['commentaireTache'] ?? null);
            $tache->setDateDebutTache(new \DateTime($data['dateDebutTache']));
            
            if (!empty($data['dateFinTache'])) {
                $tache->setDateFinTache(new \DateTime($data['dateFinTache']));
            } else {
                $tache->setDateFinTache(null);
            }

            // Gestion de l'avancement (mode manuel uniquement)
            if (isset($data['avancementTache'])) {
                $tache->setavancementTache($data['avancementTache']);
                
                // Mettre à jour le statut en fonction de l'avancement
                if ($data['avancementTache'] == 0) {
                    $tache->setStatutTache('Débuté');
                } elseif ($data['avancementTache'] > 0 && $data['avancementTache'] < 100) {
                    $tache->setStatutTache('En cours');
                } elseif ($data['avancementTache'] == 100) {
                    $tache->setStatutTache('Terminé');
                }
            }

            // VALIDATION DES DATES PAR RAPPORT AU PROJET
            if (!$this->validateTacheDates($tache, $errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs de dates ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            // Validation Symfony
            $validationErrors = $validator->validate($tache);
            if (count($validationErrors) > 0) {
                foreach ($validationErrors as $error) {
                    $errors[$error->getPropertyPath()] = $error->getMessage();
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs de validation ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            $this->entityManager->flush();

            $projet = $tache->getProjet();
            if ($projet) {
                $projet->updateAvancementAndStatut();
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Tâche modifiée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numTache}/supprimer', name: 'api_tache_supprimer', methods: ['DELETE'])]
    public function apiSupprimer(string $numTache): JsonResponse
    {
        try {
            $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
            
            if (!$tache) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            $projet = $tache->getProjet();

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette tâche'
                ], 403);
            }

            $this->entityManager->remove($tache);
            $this->entityManager->flush();

            if ($projet) {
                $projet->updateAvancementAndStatut();
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Tâche supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    // Dans TacheController.php, ajoutez/modifiez ces méthodes :

#[Route('/api/{numTache}/terminer', name: 'api_tache_terminer', methods: ['POST'])]
public function apiTerminer(string $numTache): JsonResponse
{
    try {
        $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
        
        if (!$tache) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        $projet = $tache->getProjet();

        // Vérifier les permissions de gestion
        if (!$this->canManageProjet($projet)) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à terminer cette tâche'
            ], 403);
        }

        // Vérifier si terminer cette tâche va mener le projet à 100%
        $taches = $projet->getTaches();
        $allTachesCount = $taches->count();
        $completedTachesCount = $taches->filter(function($t) use ($numTache) {
            // Compter cette tâche comme terminée
            return $t->getavancementTache() === 100 || $t->getNumTache() == $numTache;
        })->count();
        
        $willCompleteProject = ($completedTachesCount === $allTachesCount && $allTachesCount > 0);

        // Terminer la tâche
        $tache->setavancementTache(100);
        $tache->setStatutTache('Terminé');

        $this->entityManager->flush();

        // Mettre à jour le projet
        $projet->updateAvancementAndStatut();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $willCompleteProject 
                ? 'Tâche terminée - Le projet est maintenant terminé à 100%' 
                : 'Tâche terminée avec succès',
            'projetTermine' => $willCompleteProject
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
        ], 500);
    }
}

#[Route('/api/projet/{numProjet}/terminer-projet', name: 'api_projet_terminer', methods: ['POST'])]
public function apiTerminerProjet(string $numProjet): JsonResponse
{
    try {
        $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
        
        if (!$projet) {
            return $this->json([
                'success' => false,
                'message' => 'Projet non trouvé'
            ], 404);
        }

        // Vérifier les permissions
        if (!$this->canManageProjet($projet)) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à terminer ce projet'
            ], 403);
        }

        // Marquer le projet comme terminé
        $projet->setStatutPro('Terminé');
        $projet->setavancementPro(100);

        // Marquer toutes les tâches comme terminées
        foreach ($projet->getTaches() as $tache) {
            $tache->setavancementTache(100);
            $tache->setStatutTache('Terminé');
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Projet marqué comme terminé'
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la terminaison: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/{numTache}/reprendre', name: 'api_tache_reprendre', methods: ['POST'])]
    public function apiReprendre(string $numTache): JsonResponse
    {
        try {
            $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
            
            if (!$tache) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            $projet = $tache->getProjet();

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à reprendre cette tâche'
                ], 403);
            }

            $tache->setavancementTache(0);
            $tache->setStatutTache('Débuté');

            $this->entityManager->flush();

            $projet = $tache->getProjet();
            if ($projet) {
                $projet->updateAvancementAndStatut();
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Tâche reprise avec succès'
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numTache}/suspendre', name: 'api_tache_suspendre', methods: ['POST'])]
    public function apiSuspendreTache(string $numTache): JsonResponse
    {
        try {
            $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
            
            if (!$tache) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            $projet = $tache->getProjet();

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à suspendre cette tâche'
                ], 403);
            }

            // Vérifier si le projet n'est pas déjà suspendu
            if ($projet && $projet->getStatutPro() === 'Suspendu') {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de suspendre une tâche : le projet est déjà suspendu'
                ], 400);
            }

            $tache->setStatutTache('Suspendu');
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tâche suspendue avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suspension de la tâche: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/projet/{numProjet}/reprendre-toutes', name: 'api_tache_reprendre_toutes', methods: ['POST'])]
    public function apiReprendreToutesTaches(string $numProjet): JsonResponse
    {
        try {
            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
            
            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à reprendre les tâches de ce projet'
                ], 403);
            }

            $taches = $this->entityManager->getRepository(Tache::class)
                ->findBy(['numProjet' => $numProjet, 'statutTache' => 'Suspendu']);
            
            $tachesReprises = 0;
            
            foreach ($taches as $tache) {
                // Remettre le statut précédent basé sur l'avancement
                $avancement = $tache->getavancementTache();
                
                if ($avancement === 100) {
                    $tache->setStatutTache('Terminé');
                } elseif ($avancement > 0 && $avancement < 100) {
                    $tache->setStatutTache('En cours');
                } else {
                    $tache->setStatutTache('Débuté');
                }
                
                $tachesReprises++;
            }
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $tachesReprises . ' tâche(s) reprise(s) avec succès',
                'tachesReprises' => $tachesReprises
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la reprise des tâches: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/projet/{numProjet}/suspendre-toutes', name: 'api_tache_suspendre_toutes', methods: ['POST'])]
    public function apiSuspendreToutesTaches(string $numProjet): JsonResponse
    {
        try {
            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);
            
            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            // Vérifier les permissions de gestion
            if (!$this->canManageProjet($projet)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à suspendre les tâches de ce projet'
                ], 403);
            }

            $taches = $this->entityManager->getRepository(Tache::class)
                ->findBy(['numProjet' => $numProjet]);
            
            $tachesSuspendues = 0;
            
            foreach ($taches as $tache) {
                if ($tache->getStatutTache() !== 'Suspendu') {
                    $tache->setStatutTache('Suspendu');
                    $tachesSuspendues++;
                }
            }
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $tachesSuspendues . ' tâche(s) suspendue(s) avec succès',
                'tachesSuspendues' => $tachesSuspendues
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suspension des tâches: ' . $e->getMessage()
            ], 500);
        }
    }

#[Route('/api/{numTache}/verifier-cloture', name: 'api_tache_verifier_cloture', methods: ['GET'])]
public function apiVerifierCloture(string $numTache): JsonResponse
{
    try {
        $tache = $this->entityManager->getRepository(Tache::class)->find($numTache);
        
        if (!$tache) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        $projet = $tache->getProjet();
        
        if (!$projet) {
            return $this->json([
                'success' => false,
                'message' => 'Projet non trouvé'
            ], 404);
        }

        // Récupérer toutes les tâches du projet
        $taches = $projet->getTaches();
        $totalTaches = $taches->count();
        
        if ($totalTaches === 0) {
            return $this->json([
                'success' => true,
                'vaCloturer' => false,
                'message' => 'Aucune tâche dans le projet'
            ], 200);
        }

        // Compter les tâches déjà terminées (avancement = 100%)
        $tachesTerminees = 0;
        foreach ($taches as $t) {
            if ($t->getavancementTache() === 100) {
                $tachesTerminees++;
            }
        }

        // Vérifier si cette tâche est déjà terminée
        $tacheEstDejaTerminee = $tache->getavancementTache() === 100;
        
        // Si la tâche est déjà terminée, alors elle n'ajoutera pas de nouvelle tâche terminée
        $nouvellesTachesTerminees = $tacheEstDejaTerminee ? 0 : 1;
        
        // Calculer le total après l'action
        $totalApresAction = $tachesTerminees + $nouvellesTachesTerminees;
        
        // Vérifier si toutes les tâches seront terminées après cette action
        $vaCloturer = ($totalApresAction === $totalTaches);
        
        return $this->json([
            'success' => true,
            'vaCloturer' => $vaCloturer,
            'totalTaches' => $totalTaches,
            'tachesTerminees' => $tachesTerminees,
            'tacheEstDejaTerminee' => $tacheEstDejaTerminee,
            'tachesInfo' => $taches->map(function($t) {
                return [
                    'numTache' => $t->getNumTache(),
                    'titre' => $t->getTitreTache(),
                    'avancement' => $t->getavancementTache(),
                    'statut' => $t->getStatutTache()
                ];
            })->toArray()
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
        ], 500);
    }
}


    
}