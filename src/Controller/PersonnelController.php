<?php

namespace App\Controller;

use App\Entity\Personnel;
use App\Entity\Service;
use App\Entity\Direction;
use App\Repository\PersonnelRepository;
use App\Repository\ServiceRepository;
use App\Repository\DirectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/personnels')]
class PersonnelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_personnel_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('personnel/index.html.twig');
    }

    // API ENDPOINTS

    #[Route('/api/list', name: 'api_personnels_list', methods: ['GET'])]
    public function apiList(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnels = $personnelRepository->findAllWithRelations();
            
            $personnelsData = [];
            foreach ($personnels as $personnel) {
                $service = $personnel->getService();
                $direction = $personnel->getDirectionD();
                
                // Déterminer la direction selon la fonction
                $codeDirection = null;
                $nomDirection = null;
                
                if ($personnel->estDirecteur()) {
                    // Directeur - direction depuis sa relation directe
                    if ($direction) {
                        $codeDirection = $direction->getCodeDirection();
                        $nomDirection = $direction->getNomDirection();
                    }
                } elseif ($personnel->estChefService() || $personnel->estAgent()) {
                    // Chef de service ou Agent - direction depuis le service
                    if ($service && $service->getDirection()) {
                        $codeDirection = $service->getDirection()->getCodeDirection();
                        $nomDirection = $service->getDirection()->getNomDirection();
                    }
                }
                
                $personnelsData[] = [
                    'ImPer' => $personnel->getImPer(),
                    'NomPer' => $personnel->getNomPer(),
                    'PrenomPer' => $personnel->getPrenomPer(),
                    'EmailPer' => $personnel->getEmailPer(),
                    'TelPer' => $personnel->getTelPer(),
                    'RolePer' => $personnel->getRolePer(),
                    'FonctionPer' => $personnel->getFonctionPer(),
                    'StatutPer' => $personnel->getStatutPer(),
                    'service' => $service ? [
                        'codeService' => $service->getCodeService(),
                        'nomService' => $service->getNomService(),
                        'statutService' => $service->getStatutService()
                    ] : null,
                    'direction' => $codeDirection ? [
                        'codeDirection' => $codeDirection,
                        'nomDirection' => $nomDirection
                    ] : null
                ];
            }
            
            return $this->json([
                'success' => true,
                'personnels' => $personnelsData
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des personnels: ' . $e->getMessage()
            ], 500);
        }
    }

#[Route('/api/{imPer}/modifier', name: 'api_personnel_modifier', methods: ['PUT'])]
public function apiModifierPersonnel(
    Request $request, 
    string $imPer, 
    PersonnelRepository $personnelRepository,
    ServiceRepository $serviceRepository
): JsonResponse
{
    try {
        $personnel = $personnelRepository->find($imPer);
        
        if (!$personnel) {
            return $this->json([
                'success' => false,
                'message' => 'Personnel non trouvé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        $errors = [];

        // Validation des données
        if (empty($data['NomPer'])) {
            $errors['NomPer'] = 'Le nom est obligatoire';
        }

        if (empty($data['EmailPer'])) {
            $errors['EmailPer'] = 'L\'email est obligatoire';
        } elseif (!filter_var($data['EmailPer'], FILTER_VALIDATE_EMAIL)) {
            $errors['EmailPer'] = 'L\'email n\'est pas valide';
        }

        // Validation du rôle
        if (empty($data['RolePer'])) {
            $errors['RolePer'] = 'Le rôle est obligatoire';
        } elseif (!in_array($data['RolePer'], ['ROLE_ADMIN', 'ROLE_UTILISATEUR', 'ROLE_CREATEUR_DE_PROJET'])) {
            $errors['RolePer'] = 'Le rôle sélectionné n\'est pas valide';
        }

        // Vérification de l'unicité de l'email
        if (!empty($data['EmailPer'])) {
            $existingPersonnel = $personnelRepository->findOneBy(['EmailPer' => $data['EmailPer']]);
            if ($existingPersonnel && $existingPersonnel->getImPer() !== $imPer) {
                $errors['EmailPer'] = 'Cet email est déjà utilisé par un autre personnel';
            }
        }

        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'message' => 'Des erreurs ont été trouvées',
                'errors' => $errors
            ], 400);
        }

        $this->entityManager->beginTransaction();

        try {
            // Mise à jour des informations de base
            $personnel->setNomPer($data['NomPer']);
            $personnel->setPrenomPer($data['PrenomPer'] ?? null);
            $personnel->setEmailPer($data['EmailPer']);
            $personnel->setTelPer($data['TelPer'] ?? null);
            
            // Mise à jour du rôle
            $personnel->setRolePer($data['RolePer']);

            // Gestion du service (uniquement pour les non-directeurs)
            if (!$personnel->estDirecteur() && !$personnel->estChefService()) {
                $serviceCode = $data['service'] ?? null;
                
                // Si la valeur est 'aucun', mettre à null
                if ($serviceCode === 'aucun') {
                    $personnel->setService(null);
                    $personnel->setDirectionD(null);
                } 
                // Si un service est spécifié
                elseif ($serviceCode && $serviceCode !== '') {
                    $service = $serviceRepository->find($serviceCode);
                    if ($service && $service->estActif()) {
                        
                        // Réinitialiser puis assigner le nouveau service
                        $personnel->setService(null);
                        $personnel->setDirectionD(null);
                        
                        // Puis assigner le nouveau service
                        $personnel->setService($service);
                        
                    } else {
                        throw new \Exception('Service non valide ou désactivé');
                    }
                }
                // Si serviceCode est null ou vide string
                else {
                    $personnel->setService(null);
                    $personnel->setDirectionD(null);
                }
            }

            // Mise à jour automatique de la fonction
            $personnel->setFonctionPer($personnel->determinerFonction());

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'success' => true,
                'message' => 'Personnel modifié avec succès'
            ], 200);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

    } catch (\Exception $e) {
        error_log("ERREUR modification personnel: " . $e->getMessage());
        
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la modification: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/{imPer}/desactiver', name: 'api_personnel_desactiver', methods: ['POST'])]
    public function apiDesactiverPersonnel(string $imPer, PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnel = $personnelRepository->find($imPer);
            
            if (!$personnel) {
                return $this->json([
                    'success' => false,
                    'message' => 'Personnel non trouvé'
                ], 404);
            }

            if ($personnel->estDesactive()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce personnel est déjà désactivé'
                ], 400);
            }

            // Désactiver le personnel
            $personnel->desactiver();

            // Si c'est un directeur, mettre sa direction en attente
            if ($personnel->estDirecteur() && $personnel->getDirectionD()) {
                $direction = $personnel->getDirectionD();
                $direction->setStatutDirection('DESACTIVEE');
            }

            // Si c'est un chef de service, libérer le service
            if ($personnel->estChefService() && $personnel->getService()) {
                $service = $personnel->getService();
                $service->setStatutService('DESACTIVE');
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Personnel désactivé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la désactivation: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{imPer}/activer', name: 'api_personnel_activer', methods: ['POST'])]
public function apiActiverPersonnel(Request $request, string $imPer, PersonnelRepository $personnelRepository, ServiceRepository $serviceRepository, DirectionRepository $directionRepository): JsonResponse
{
    try {
        $personnel = $personnelRepository->find($imPer);
        
        if (!$personnel) {
            return $this->json([
                'success' => false,
                'message' => 'Personnel non trouvé'
            ], 404);
        }

        // if (!$personnel->estDesactive()) {
        //     return $this->json([
        //         'success' => false,
        //         'message' => 'Ce personnel n\'est pas désactivé'
        //     ], 400);
        // }

        $data = json_decode($request->getContent(), true);
        $nouveauServiceCode = $data['nouveauService'] ?? null;
        $nouvelleDirectionCode = $data['nouvelleDirection'] ?? null;

        // CAS 1: DIRECTEUR - Activation simple
        if ($personnel->estDirecteur()) {
            $personnel->activer();
            
            // Réactiver la direction si le directeur en a une
            if ($personnel->getDirectionD()) {
                $direction = $personnel->getDirectionD();
                $direction->setStatutDirection('ACTIVE');
            }
        }
        // CAS 2: CHEF DE SERVICE - Activation avec service
        elseif ($personnel->estChefService()) {
            // Vérifier si changement de direction est demandé
            if ($nouvelleDirectionCode) {
                // Changement de direction demandé
                $nouvelleDirection = $directionRepository->find($nouvelleDirectionCode);
                if (!$nouvelleDirection || !$nouvelleDirection->estActive()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'La direction sélectionnée n\'est pas active'
                    ], 400);
                }
                
                // Pour un chef de service, il doit avoir un service
                $service = $personnel->getService();
                if (!$service) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Ce chef de service n\'a pas de service associé'
                    ], 400);
                }
                
                // Changer la direction du service
                $service->setDirection($nouvelleDirection);
                
                $personnel->activer();
                $service->setStatutService('ACTIF');
                
            } else {
                // Pas de changement de direction demandé
                // Vérifier si le service existe
                $service = $personnel->getService();
                
                if ($service) {
                    // Vérifier si la direction du service est active
                    $directionDuService = $service->getDirection();
                    
                    if ($directionDuService && !$directionDuService->estActive()) {
                        // ICI: Direction inactive - renvoyer l'erreur avec les données du personnel
                        return $this->json([
                            'success' => false,
                            'message' => 'direction_inactive',
                            'personnel' => [
                                'ImPer' => $personnel->getImPer(),
                                'NomPer' => $personnel->getNomPer(),
                                'PrenomPer' => $personnel->getPrenomPer(),
                                'service' => $service ? [
                                    'codeService' => $service->getCodeService(),
                                    'nomService' => $service->getNomService(),
                                    'statutService' => $service->getStatutService()
                                ] : null,
                                'direction' => $directionDuService ? [
                                    'codeDirection' => $directionDuService->getCodeDirection(),
                                    'nomDirection' => $directionDuService->getNomDirection(),
                                    'statutDirection' => $directionDuService->getStatutDirection()
                                ] : null
                            ]
                        ], 400);
                    }
                    
                    // Si la direction est active ou n'existe pas, activer le service
                    $service->setStatutService('ACTIF');
                }
                
                $personnel->activer();
            }
        }
        // CAS 3: AUTRES PERSONNELS (Agent, etc.) - Vérification du service
        else {
            $service = $personnel->getService();
            
            // Si changement de service demandé
            if ($nouveauServiceCode) {
                $nouveauService = $serviceRepository->find($nouveauServiceCode);
                if ($nouveauService && $nouveauService->estActif()) {
                    $personnel->setService($nouveauService);
                    $personnel->activer();
                } else {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le service sélectionné n\'est pas valide ou n\'est pas actif'
                    ], 400);
                }
            }
            // Si garder le service actuel mais il est inactif
            elseif (!$personnel->estDirecteur() && $service && !$service->estActif()) {
                return $this->json([
                    'success' => false,
                    'message' => 'service_inactive',
                    'personnel' => [
                        'ImPer' => $personnel->getImPer(),
                        'NomPer' => $personnel->getNomPer(),
                        'PrenomPer' => $personnel->getPrenomPer(),
                        'service' => $service ? [
                            'codeService' => $service->getCodeService(),
                            'nomService' => $service->getNomService(),
                            'statutService' => $service->getStatutService()
                        ] : null
                    ]
                ], 400);
            }
            // Service actif ou aucun service - Activation simple
            else {
                $personnel->activer();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Personnel activé avec succès'
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de l\'activation: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/directions/{codeDirection}/details', name: 'api_direction_details', methods: ['GET'])]
public function apiDirectionDetails(string $codeDirection, DirectionRepository $directionRepository): JsonResponse
{
    try {
        $direction = $directionRepository->find($codeDirection);
        
        if (!$direction) {
            return $this->json([
                'success' => false,
                'message' => 'Direction non trouvée'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'direction' => [
                'CodeDirection' => $direction->getCodeDirection(),
                'nomDirection' => $direction->getNomDirection(),
                'statutDirection' => $direction->getStatutDirection()
            ]
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des détails de la direction'
        ], 500);
    }
}

    // API pour les sélections

    #[Route('/api/directions-actives', name: 'api_directions_actives_personnel', methods: ['GET'])]
public function apiDirectionsActives(DirectionRepository $directionRepository): JsonResponse
{
    try {
        $directions = $directionRepository->createQueryBuilder('d')
            ->where('d.statutDirection = :actif')
            ->setParameter('actif', 'ACTIVE')
            ->orderBy('d.nomDirection', 'ASC')
            ->getQuery()
            ->getResult();

        $directionsData = [];
        foreach ($directions as $direction) {
            $directionsData[] = [
                'CodeDirection' => $direction->getCodeDirection(),
                'nomDirection' => $direction->getNomDirection(),
                'statutDirection' => $direction->getStatutDirection()
            ];
        }

        return $this->json([
            'success' => true,
            'directions' => $directionsData
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des directions'
        ], 500);
    }
}

    #[Route('/api/services-actifs', name: 'api_services_actifs_personnel', methods: ['GET'])]
    public function apiServicesActifs(ServiceRepository $serviceRepository): JsonResponse
    {
        try {
            $services = $serviceRepository->createQueryBuilder('s')
                ->where('s.statutService = :actif')
                ->setParameter('actif', 'ACTIF')
                ->orderBy('s.nomService', 'ASC')
                ->getQuery()
                ->getResult();

            $servicesData = [];
            foreach ($services as $service) {
                $servicesData[] = [
                    'codeService' => $service->getCodeService(),
                    'nomService' => $service->getNomService(),
                    'direction' => $service->getDirection() ? [
                        'codeDirection' => $service->getDirection()->getCodeDirection(),
                        'nomDirection' => $service->getDirection()->getNomDirection()
                    ] : null
                ];
            }

            return $this->json([
                'success' => true,
                'services' => $servicesData
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des services'
            ], 500);
        }
    }

    #[Route('/api/services-actifs-pour-modal', name: 'api_services_actifs_pour_modal', methods: ['GET'])]
public function apiServicesActifsPourModal(ServiceRepository $serviceRepository): JsonResponse
{
    try {
        // Récupérer tous les services actifs (même ceux avec chef)
        $services = $serviceRepository->createQueryBuilder('s')
            ->where('s.statutService = :actif')
            ->setParameter('actif', 'ACTIF')
            ->orderBy('s.nomService', 'ASC')
            ->getQuery()
            ->getResult();

        $servicesData = [];
        foreach ($services as $service) {
            $servicesData[] = [
                'codeService' => $service->getCodeService(),
                'nomService' => $service->getNomService(),
                'direction' => $service->getDirection() ? [
                    'codeDirection' => $service->getDirection()->getCodeDirection(),
                    'nomDirection' => $service->getDirection()->getNomDirection()
                ] : null
            ];
        }

        return $this->json([
            'success' => true,
            'services' => $servicesData
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des services'
        ], 500);
    }
}
}