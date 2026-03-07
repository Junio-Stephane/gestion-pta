<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Direction;
use App\Entity\Personnel;
use App\Repository\ProjetRepository;
use App\Repository\ServiceRepository;
use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/projets')]
class ProjetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_projet_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('projet/index.html.twig');
    }


    #[Route('/api/list', name: 'api_projets_list', methods: ['GET'])]
    public function apiList(ProjetRepository $projetRepository): JsonResponse
    {
        try {
            $projets = $projetRepository->findAllWithDetails();

            return $this->json([
                'success' => true,
                'projets' => $projets
            ], 200, [], ['groups' => ['projet:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des projets: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/create', name: 'api_projet_create', methods: ['POST'])]
    public function apiCreate(Request $request, ServiceRepository $serviceRepository, PersonnelRepository $personnelRepository, ValidatorInterface $validator): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof Personnel) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Empêcher les utilisateurs ROLE_UTILISATEUR de créer des projets
            if ($user->hasRole('ROLE_UTILISATEUR')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les permissions nécessaires pour créer un projet'
                ], 403);
            }

            $data = json_decode($request->getContent(), true);

            $errors = [];

            // Validation des données
            if (empty($data['numProjet'])) {
                $errors['numProjet'] = 'Le numéro du projet est obligatoire';
            }

            if (empty($data['titrePro'])) {
                $errors['titrePro'] = 'Le titre du projet est obligatoire';
            }

            if (empty($data['budgetPro'])) {
                $errors['budgetPro'] = 'Le budget est obligatoire';
            }

            if (empty($data['dateDebutPro'])) {
                $errors['dateDebutPro'] = 'La date de début est obligatoire';
            }

            if (empty($data['service'])) {
                $errors['service'] = 'Le service est obligatoire';
            }

            if (empty($data['personnels']) || !is_array($data['personnels']) || count($data['personnels']) === 0) {
                $errors['personnels'] = 'Au moins un responsable doit être sélectionné';
            }

            if (!empty($data['numProjet'])) {
                $existingProjet = $this->entityManager->getRepository(Projet::class)->find($data['numProjet']);
                if ($existingProjet) {
                    $errors['numProjet'] = 'Un projet avec ce numéro existe déjà';
                }
            }

            // Vérification de la cohérence des dates
            if (!empty($data['dateDebutPro']) && !empty($data['dateFinPro'])) {
                $dateDebut = new \DateTime($data['dateDebutPro']);
                $dateFin = new \DateTime($data['dateFinPro']);

                if ($dateFin <= $dateDebut) {
                    $errors['dateFinPro'] = 'La date de fin doit être postérieure à la date de début';
                }
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            $projet = new Projet();
            $projet->setNumProjet($data['numProjet']);
            $projet->setTitrePro($data['titrePro']);
            $projet->setDescriptionPro($data['descriptionPro'] ?? null);
            $projet->setCommentairePro($data['commentairePro'] ?? null);
            $projet->setBudgetPro($data['budgetPro']);
            $projet->setDateDebutPro(new \DateTime($data['dateDebutPro']));

            if (!empty($data['dateFinPro'])) {
                $projet->setDateFinPro(new \DateTime($data['dateFinPro']));
            }

            // RÉCUPÉRATION ET ASSIGNATION DU CRÉATEUR (utilisateur connecté)
            $user = $this->getUser();
            if ($user instanceof Personnel) {
                $projet->setCreateur($user);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié ou non autorisé'
                ], 401);
            }

            // Assigner le service
            $service = $serviceRepository->find($data['service']);
            if ($service) {
                $projet->setService($service);
            }

            // Statut initial
            $projet->setStatutPro('Débuté');
            $projet->setavancementPro(0);

            // Assigner les responsables (personnels)
            if (!empty($data['personnels']) && is_array($data['personnels'])) {
                foreach ($data['personnels'] as $personnelIm) {
                    $personnel = $personnelRepository->find($personnelIm);
                    if ($personnel) {
                        $projet->addPersonnel($personnel);
                    }
                }
            }

            // Validation Symfony
            $validationErrors = $validator->validate($projet);
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

            $this->entityManager->persist($projet);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet créé avec succès',
                'projet' => $projet
            ], 201, [], ['groups' => ['projet:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numProjet}/modifier', name: 'api_projet_modifier', methods: ['PUT'])]
    public function apiModifierProjet(Request $request, string $numProjet, ServiceRepository $serviceRepository, PersonnelRepository $personnelRepository, ValidatorInterface $validator): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof Personnel) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);

            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            $errors = [];

            // Validation des données
            if (empty($data['titrePro'])) {
                $errors['titrePro'] = 'Le titre du projet est obligatoire';
            }

            if (empty($data['budgetPro'])) {
                $errors['budgetPro'] = 'Le budget est obligatoire';
            }

            if (empty($data['dateDebutPro'])) {
                $errors['dateDebutPro'] = 'La date de début est obligatoire';
            }

            if (empty($data['service'])) {
                $errors['service'] = 'Le service est obligatoire';
            }


            if (empty($data['personnels']) || !is_array($data['personnels']) || count($data['personnels']) === 0) {
                $errors['personnels'] = 'Au moins un responsable doit être sélectionné';
            }

            // Vérification de la cohérence des dates
            if (!empty($data['dateDebutPro']) && !empty($data['dateFinPro'])) {
                $dateDebut = new \DateTime($data['dateDebutPro']);
                $dateFin = new \DateTime($data['dateFinPro']);

                if ($dateFin <= $dateDebut) {
                    $errors['dateFinPro'] = 'La date de fin doit être postérieure à la date de début';
                }
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Des erreurs ont été trouvées',
                    'errors' => $errors
                ], 400);
            }

            // Vérification des permissions
            if ($user->hasRole('ROLE_UTILISATEUR')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les permissions nécessaires pour modifier un projet'
                ], 403);
            }

            if ($user->hasRole('ROLE_CREATEUR_DE_PROJET') && $projet->getCreateur() !== $user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que les projets que vous avez créés'
                ], 403);
            }

            // Mettre à jour les propriétés
            $projet->setTitrePro($data['titrePro']);
            $projet->setDescriptionPro($data['descriptionPro'] ?? null);
            $projet->setCommentairePro($data['commentairePro'] ?? null);
            $projet->setBudgetPro($data['budgetPro']);
            $projet->setDateDebutPro(new \DateTime($data['dateDebutPro']));

            if (!empty($data['dateFinPro'])) {
                $projet->setDateFinPro(new \DateTime($data['dateFinPro']));
            } else {
                $projet->setDateFinPro(null);
            }

            // Mettre à jour le service
            $service = $serviceRepository->find($data['service']);
            if ($service) {
                $projet->setService($service);
            }

            foreach ($projet->getPersonnels() as $personnel) {
                $projet->removePersonnel($personnel);
            }

            $this->entityManager->flush();

            // 3. Ajouter les nouveaux personnels
            if (!empty($data['personnels']) && is_array($data['personnels'])) {
                foreach ($data['personnels'] as $personnelIm) {
                    $personnel = $personnelRepository->find($personnelIm);
                    if ($personnel) {
                        $projet->addPersonnel($personnel);
                    }
                }
            }

            $validationErrors = $validator->validate($projet);
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

            return $this->json([
                'success' => true,
                'message' => 'Projet modifié avec succès'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numProjet}/suspendre', name: 'api_projet_suspendre', methods: ['POST'])]
    public function apiSuspendreProjet(string $numProjet): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof Personnel) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);

            // Empêcher les utilisateurs ROLE_UTILISATEUR de suspendre des projets
            if ($user->hasRole('ROLE_UTILISATEUR')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les permissions nécessaires pour suspendre un projet'
                ], 403);
            }

            if ($user->hasRole('ROLE_CREATEUR_DE_PROJET') && $projet->getCreateur() !== $user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que les projets que vous avez créés'
                ], 403);
            }

            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);

            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            if ($projet->getStatutPro() === 'Suspendu') {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce projet est déjà suspendu'
                ], 400);
            }

            // Suspendre le projet
            $projet->setStatutPro('Suspendu');

            // Suspendre toutes les tâches associées
            foreach ($projet->getTaches() as $tache) {
                $tache->setStatutTache('Suspendu');
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet et toutes ses tâches ont été suspendus avec succès'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suspension: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numProjet}/reprendre', name: 'api_projet_reprendre', methods: ['POST'])]
    public function apiReprendreProjet(Request $request, string $numProjet, ServiceRepository $serviceRepository): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof Personnel) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);

            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            if ($user->hasRole('ROLE_UTILISATEUR')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les permissions nécessaires pour reprendre ce projet'
                ], 403);
            }

            if ($projet->getStatutPro() !== 'Suspendu') {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce projet n\'est pas suspendu'
                ], 400);
            }

            // VÉRIFICATION DU SERVICE - AVEC GESTION DES OPTIONS
            $service = $projet->getService();
            $data = json_decode($request->getContent(), true);

            $garderService = $data['garderService'] ?? false;
            $nouveauServiceCode = $data['nouveauService'] ?? null;

            // Si le service actuel est inactif ET on ne veut pas le garder
            if (!$garderService && $service && !$service->estActif()) {
                if ($nouveauServiceCode) {
                    // Changer de service
                    $nouveauService = $serviceRepository->find($nouveauServiceCode);
                    if (!$nouveauService) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Service non trouvé'
                        ], 404);
                    }

                    if (!$nouveauService->estActif()) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Le service sélectionné n\'est pas actif'
                        ], 400);
                    }

                    $projet->setService($nouveauService);
                } else {
                    return $this->json([
                        'success' => false,
                        'message' => 'service_inactive',
                        'projet' => [
                            'numProjet' => $projet->getNumProjet(),
                            'titrePro' => $projet->getTitrePro(),
                            'service' => $service ? [
                                'codeService' => $service->getCodeService(),
                                'nomService' => $service->getNomService(),
                                'statutService' => $service->getStatutService()
                            ] : null
                        ]
                    ], 400);
                }
            }

            // Reprendre le projet
            $projet->setStatutPro('Débuté');

            // Reprendre toutes les tâches associées qui étaient suspendues
            foreach ($projet->getTaches() as $tache) {
                if ($tache->getStatutTache() === 'Suspendu') {
                    // Recalculer le statut basé sur l'avancement actuel
                    $avancement = $tache->getavancementTache();

                    if ($avancement === 100) {
                        $tache->setStatutTache('Terminé');
                    } elseif ($avancement > 0 && $avancement < 100) {
                        $tache->setStatutTache('En cours');
                    } else {
                        $tache->setStatutTache('Débuté');
                    }
                }
            }

            // Mettre à jour l'avancement et le statut du projet
            $projet->updateAvancementAndStatut();

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet et toutes ses tâches ont été repris avec succès'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la reprise: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/projet-toutes-directions', name: 'api_projet_toutes_directions', methods: ['GET'])]
    public function apiToutesDirections(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->findAll();

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

    #[Route('/api/projet-directions-actives', name: 'api_projet_directions_actives', methods: ['GET'])]
    public function apiDirectionsActives(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->findBy(['statutDirection' => 'ACTIVE']);

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

    #[Route('/api/tous-services', name: 'api_tous_services', methods: ['GET'])]
    public function apiTousServices(ServiceRepository $serviceRepository): JsonResponse
    {
        try {
            $services = $serviceRepository->findAll();

            return $this->json([
                'success' => true,
                'services' => $services
            ], 200, [], ['groups' => ['service:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des services'
            ], 500);
        }
    }

    #[Route('/api/services-actifs', name: 'api_services_actifs', methods: ['GET'])]
    public function apiServicesActifs(ServiceRepository $serviceRepository): JsonResponse
    {
        try {
            $services = $serviceRepository->findBy(['statutService' => 'ACTIF']);

            return $this->json([
                'success' => true,
                'services' => $services
            ], 200, [], ['groups' => ['service:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des services'
            ], 500);
        }
    }

    #[Route('/api/tous-personnels', name: 'api_tous_personnels', methods: ['GET'])]
    public function apiTousPersonnels(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            // Utilisez la nouvelle méthode qui charge les relations
            $personnels = $personnelRepository->findPersonnelsActifsEtDesactives();

            return $this->json([
                'success' => true,
                'personnels' => $personnels
            ], 200, [], ['groups' => ['personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/personnels-actifs', name: 'api_personnels_actifs', methods: ['GET'])]
    public function apiPersonnelsActifs(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            // Utilisez la nouvelle méthode qui charge les relations
            $personnels = $personnelRepository->findPersonnelsActifs();

            return $this->json([
                'success' => true,
                'personnels' => $personnels
            ], 200, [], ['groups' => ['personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{numProjet}', name: 'api_projet_get', methods: ['GET'])]
    public function apiGetProjet(string $numProjet): JsonResponse
    {
        try {
            $projet = $this->entityManager->getRepository(Projet::class)->find($numProjet);

            if (!$projet) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'projet' => $projet
            ], 200, [], ['groups' => ['projet:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du projet'
            ], 500);
        }
    }

    // Dans votre RapportController
    private function serializeDirectionForRapport(Direction $direction): array
    {
        $data = [
            'CodeDirection' => $direction->getCodeDirection(),
            'nomDirection' => $direction->getNomDirection(),
            'services' => []
        ];

        foreach ($direction->getServices() as $service) {
            if ($service->getStatutService() !== 'ACTIF') {
                continue;
            }

            $serviceData = [
                'CodeService' => $service->getCodeService(),
                'nomService' => $service->getNomService(),
                'projets' => []
            ];

            foreach ($service->getProjets() as $projet) {
                $projetData = [
                    'numProjet' => $projet->getNumProjet(),
                    'titrePro' => $projet->getTitrePro(),
                    'descriptionPro' => $projet->getDescriptionPro(),
                    'commentairePro' => $projet->getCommentairePro(),
                    'budgetPro' => $projet->getBudgetPro(),
                    'dateDebutPro' => $projet->getDateDebutPro() ? $projet->getDateDebutPro()->format('Y-m-d') : null,
                    'dateFinPro' => $projet->getDateFinPro() ? $projet->getDateFinPro()->format('Y-m-d') : null,
                    'avancementPro' => $projet->getavancementPro(),
                    'StatutPro' => $projet->getStatutPro(),
                    'taches' => []
                ];

                // Tâches du projet
                foreach ($projet->getTaches() as $tache) {
                    $projetData['taches'][] = [
                        'numTache' => $tache->getNumTache(),
                        'titreTache' => $tache->getTitreTache(),
                        'descriptionTache' => $tache->getDescriptionTache(),
                        'commentaireTache' => $tache->getCommentaireTache(),
                        'dateDebutTache' => $tache->getDateDebutTache() ? $tache->getDateDebutTache()->format('Y-m-d') : null,
                        'dateFinTache' => $tache->getDateFinTache() ? $tache->getDateFinTache()->format('Y-m-d') : null,
                        'avancementTache' => $tache->getavancementTache(),
                        'statutTache' => $tache->getStatutTache()
                    ];
                }

                $serviceData['projets'][] = $projetData;
            }

            $data['services'][] = $serviceData;
        }

        return $data;
    }
}
