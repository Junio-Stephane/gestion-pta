<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\Direction;
use App\Entity\Personnel;
use App\Repository\ServiceRepository;
use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Service\FonctionDeterminerService;

#[Route('/admin/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FonctionDeterminerService $fonctionDeterminerService
    ) {}

    #[Route('/', name: 'app_service_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('service/index.html.twig');
    }

    // API ENDPOINTS

    #[Route('/api/list', name: 'api_services_list', methods: ['GET'])]
    public function apiList(ServiceRepository $serviceRepository): JsonResponse
    {
        try {
            $services = $serviceRepository->findAllWithRelations();
            
            $servicesData = [];
            foreach ($services as $service) {
                $chefService = $service->getChefService();
                $servicesData[] = [
                    'CodeService' => $service->getCodeService(),
                    'nomService' => $service->getNomService(),
                    'statutService' => $service->getStatutService(),
                    'direction' => $service->getDirection(),
                    'chefService' => $chefService,
                    'personnelsCount' => $service->getPersonnels()->count(),
                ];
            }
            
            return $this->json([
                'success' => true,
                'services' => $servicesData
            ], 200, [], ['groups' => ['service:read', 'direction:read', 'personnel:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des services: ' . $e->getMessage()
            ], 500);
        }
    }

   #[Route('/api/create', name: 'api_service_create', methods: ['POST'])]
public function apiCreate(Request $request, ServiceRepository $serviceRepository, DirectionRepository $directionRepository, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);
        
        $errors = [];
        
        // Validation des données
        if (empty($data['codeService'])) {
            $errors['codeService'] = 'Le code du service est obligatoire';
        }

        if (empty($data['nomService'])) {
            $errors['nomService'] = 'Le nom du service est obligatoire';
        }

        if (empty($data['direction'])) {
            $errors['direction'] = 'La direction est obligatoire';
        }

        // Vérifier si le code existe déjà
        if (!empty($data['codeService'])) {
            $existingService = $this->entityManager->getRepository(Service::class)->find($data['codeService']);
            if ($existingService) {
                $errors['codeService'] = 'Un service avec ce code existe déjà';
            }
        }

        // Vérification de l'unicité du nom
        if (!empty($data['nomService']) && !$serviceRepository->isNomServiceUnique($data['nomService'])) {
            $errors['nomService'] = 'Un service avec ce nom existe déjà';
        }

        // Vérification de la direction
        if (!empty($data['direction'])) {
            $direction = $directionRepository->find($data['direction']);
            if (!$direction) {
                $errors['direction'] = 'Direction non trouvée';
            } elseif (!$direction->estActive()) {
                $errors['direction'] = 'La direction sélectionnée n\'est pas active';
            }
        }

        // Vérification simple du chef de service (juste s'il existe)
        // EXCLURE LE CAS "AUCUN" DE CETTE VÉRIFICATION
        if (!empty($data['chefService']) && $data['chefService'] !== 'AUCUN') {
            $chefService = $personnelRepository->find($data['chefService']);
            if (!$chefService) {
                $errors['chefService'] = 'Personnel non trouvé';
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

        $service = new Service();
        $service->setCodeService($data['codeService']);
        $service->setNomService($data['nomService']);
        
        // Assigner la direction
        $direction = $directionRepository->find($data['direction']);
        $service->setDirection($direction);

        // Gestion du chef de service
        if (!empty($data['chefService'])) {
            // Si "AUCUN" est sélectionné, ne pas assigner de chef de service
            if ($data['chefService'] === 'AUCUN') {
                $service->setChefService(null);
                $service->setStatutService('EN_ATTENTE');
            } else {
                $chefService = $personnelRepository->find($data['chefService']);
                if ($chefService) {
                    // Vérifier si le personnel n'est pas déjà chef de service d'un service actif
                    if ($chefService->getService() && $chefService->getService()->estActif()) {
                        $errors['chefService'] = 'Ce personnel est déjà chef de service d\'un service actif';
                    }
                    
                    if (empty($errors)) {
                        if ($chefService->estDirecteur() && $chefService->getDirectionD()) {
                            $direction = $chefService->getDirectionD();
                            $chefService->setDirectionD(null);
                            $chefService->setStatutPer('ACTIF');
                            $direction->setPersonnel(null);
                            $direction->setStatutDirection('EN_ATTENTE');
                            $this->entityManager->persist($direction);
                        }

                        $service->setChefService($chefService);
                        $service->setStatutService('ACTIF');

                        $chefService->setFonctionPer($chefService->determinerFonction());
                        
                        // Si le chef de service était désactivé, le réactiver
                        if ($chefService->estDesactive()) {
                            $chefService->activer();
                        }
                    }
                } else {
                    $service->setStatutService('EN_ATTENTE');
                }
            }
        } else {
            $service->setStatutService('EN_ATTENTE');
        }
        
        // Vérifier à nouveau les erreurs après la logique métier
        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'message' => 'Des erreurs ont été trouvées',
                'errors' => $errors
            ], 400);
        }

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        $message = $service->getStatutService() === 'ACTIF'
            ? 'Service créé avec succès avec chef de service' 
            : 'Service créé avec succès - En attente de chef de service';

        return $this->json([
            'success' => true,
            'message' => $message,
            'service' => $service
        ], 201, [], ['groups' => ['service:read']]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la création: ' . $e->getMessage()
        ], 500);
    }
}


   #[Route('/api/{codeService}/modifier', name: 'api_service_modifier', methods: ['PUT'])]
public function apiModifierService(Request $request, string $codeService, ServiceRepository $serviceRepository, DirectionRepository $directionRepository, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        $service = $this->entityManager->getRepository(Service::class)->find($codeService);
        
        if (!$service) {
            return $this->json([
                'success' => false,
                'message' => 'Service non trouvé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        $errors = [];

        if (empty($data['nomService'])) {
            $errors['nomService'] = 'Le nom du service est obligatoire';
        }

        if (empty($data['direction'])) {
            $errors['direction'] = 'La direction est obligatoire';
        }

        // Vérification de l'unicité du nom (en excluant le service actuel)
        if (!empty($data['nomService'])) {
            $currentNomService = $service->getNomService();
            $newNomService = $data['nomService'];
            
            // Seulement vérifier l'unicité si le nom a changé
            if ($newNomService !== $currentNomService) {
                if (!$serviceRepository->isNomServiceUnique($newNomService, $codeService)) {
                    $errors['nomService'] = 'Un service avec ce nom existe déjà';
                }
            }
        }

        // Vérification de la direction
        if (!empty($data['direction'])) {
            $direction = $directionRepository->find($data['direction']);
            if (!$direction) {
                $errors['direction'] = 'Direction non trouvée';
            } elseif (!$direction->estActive()) {
                $errors['direction'] = 'La direction sélectionnée n\'est pas active';
            }
        }

        // Vérification du chef de service
        if (!empty($data['chefService'])) {
            $nouveauChef = $personnelRepository->find($data['chefService']);
            if ($nouveauChef && $nouveauChef->getService() && $nouveauChef->getService()->getChefService() === $nouveauChef && $nouveauChef->getService() !== $service && $nouveauChef->getService()->estActif()) {
                $errors['chefService'] = 'Ce personnel est déjà chef de service d\'un autre service actif';
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

        // Modifier le nom du service
        $service->setNomService($data['nomService']);

        // Modifier la direction
        $direction = $directionRepository->find($data['direction']);
        $service->setDirection($direction);

        // Gérer le changement de chef de service
        $nouveauChefIm = $data['chefService'] ?? null;
        $ancienChef = $service->getChefService();

        if ($nouveauChefIm) {
    // CAS "AUCUN CHEF DE SERVICE"
    if ($nouveauChefIm === 'AUCUN') {
        if ($ancienChef) {
            // Si l'ancien chef existe, le libérer
            $ancienChef->setService(null);
            $ancienChef->setFonctionPer($ancienChef->determinerFonction());
            $this->entityManager->persist($ancienChef);
        }
        $service->setChefService(null);
        $service->setStatutService('EN_ATTENTE');
    } else {
        // Assigner un nouveau chef de service
        $nouveauChef = $personnelRepository->find($nouveauChefIm);
        
        if (!$nouveauChef) {
            return $this->json([
                'success' => false,
                'message' => 'Personnel non trouvé'
            ], 404);
        }

            // 1. Gérer l'ancien chef S'IL Y A UN CHANGEMENT
            if ($ancienChef && $ancienChef !== $nouveauChef) {
                // IMPORTANT: Enlever le statut de chef de service à l'ancien chef
                $service->setChefService(null); // Ligne CRITIQUE qui manquait
                
                // L'ancien chef reste dans le service mais devient agent
                $ancienChef->setService($service); // Il reste dans le même service
                $ancienChef->setFonctionPer('Agent'); // Il redevient agent
                
                // Si l'ancien chef était directeur d'une direction, le libérer de cette direction
                if ($ancienChef->estDirecteur() && $ancienChef->getDirectionD()) {
                    $directionAncienChef = $ancienChef->getDirectionD();
                    $directionAncienChef->setPersonnel(null);
                    $this->entityManager->persist($directionAncienChef);
                }
                
                $this->entityManager->persist($ancienChef);
            }

            // 2. Gérer le nouveau chef
            // Libérer le nouveau chef de son ancien poste si nécessaire
            $ancienServiceDuNouveauChef = $this->entityManager->getRepository(Service::class)
                ->findOneBy(['chefService' => $nouveauChef]);
                
            if ($ancienServiceDuNouveauChef && $ancienServiceDuNouveauChef !== $service) {
                // Libérer l'ancien service du nouveau chef
                $ancienServiceDuNouveauChef->setChefService(null);
                $this->entityManager->persist($ancienServiceDuNouveauChef);
                
                // Rendre l'ancien chef agent dans son ancien service
                if ($nouveauChef->getService() && $nouveauChef->getService() !== $service) {
                    $nouveauChef->setFonctionPer('Agent');
                    // Le nouveau chef reste dans son ancien service comme agent
                    $nouveauChef->setService($nouveauChef->getService());
                }
            }

            // Si le nouveau chef était directeur, le libérer de cette direction
            if ($nouveauChef->estDirecteur() && $nouveauChef->getDirectionD()) {
                $directionDuChef = $nouveauChef->getDirectionD();
                $directionDuChef->setPersonnel(null);
                $this->entityManager->persist($directionDuChef);
            }

            // 3. Assigner le nouveau chef au service
            $service->setChefService($nouveauChef);
            $nouveauChef->setService($service); // Ajouter le service au nouveau chef
            $nouveauChef->setFonctionPer('Chef_service');
            
            $service->setStatutService('ACTIF');
            
            // Si le nouveau chef était désactivé, le réactiver
            if ($nouveauChef->estDesactive()) {
                $nouveauChef->activer();
            }

        }
} else {
    // Cas où chefService est vide
    if ($ancienChef) {
        $ancienChef->setService(null);
        $ancienChef->setFonctionPer($ancienChef->determinerFonction());
        $this->entityManager->persist($ancienChef);
    }
    $service->setChefService(null);
    $service->setStatutService('EN_ATTENTE');
}

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Service modifié avec succès'
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la modification: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/mutation-chef-service', name: 'api_service_mutation_chef', methods: ['POST'])]
public function apiMutationChefService(Request $request, ServiceRepository $serviceRepository, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['chefService']) || empty($data['nouveauService'])) {
            return $this->json([
                'success' => false,
                'message' => 'Le chef de service et le nouveau service sont requis'
            ], 400);
        }

        $chefService = $personnelRepository->find($data['chefService']);
        $nouveauService = $serviceRepository->find($data['nouveauService']);

        if (!$chefService || !$nouveauService) {
            return $this->json([
                'success' => false,
                'message' => 'Chef de service ou service non trouvé'
            ], 404);
        }

        // Récupérer l'ancien service du chef
        $ancienService = $chefService->getService();

        // Mettre l'ancien service en attente
        if ($ancienService) {
            $ancienService->setChefService(null);
            $ancienService->setStatutService('EN_ATTENTE');
            
            // Mettre à jour la fonction de l'ancien chef
            $chefService->setFonctionPer($chefService->determinerFonction());
        }

        // Assigner le chef au nouveau service
        $nouveauService->setChefService($chefService);
        $nouveauService->setStatutService('ACTIF');
        
        // Mettre à jour la fonction du chef
        $chefService->setFonctionPer('Chef_service');

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mutation du chef de service effectuée avec succès'
        ], 200);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la mutation: ' . $e->getMessage()
        ], 500);
    }
}

#[Route('/api/services-en-attente', name: 'api_services_en_attente', methods: ['GET'])]
public function apiServicesEnAttente(ServiceRepository $serviceRepository): JsonResponse
{
    try {
        $services = $serviceRepository->findServicesEnAttenteTable();
        
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

    #[Route('/api/{codeService}/desactiver', name: 'api_service_desactiver', methods: ['POST'])]
    public function apiDesactiverService(string $codeService): JsonResponse
    {
        try {
            $service = $this->entityManager->getRepository(Service::class)->find($codeService);
            
            if (!$service) {
                return $this->json([
                    'success' => false,
                    'message' => 'Service non trouvé'
                ], 404);
            }

            if ($service->estDesactive()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce service est déjà désactivé'
                ], 400);
            }

            // Désactiver le service
            $service->desactiverAvecCascade();

            // Désactiver le chef de service s'il existe
            $chefService = $service->getChefService();
            if ($chefService) {
                $chefService->desactiver();
                $service->setChefService($chefService);
            }

            // Désactiver tous les personnels de ce service
            $personnelsDesactives = 0;
            $projetsDesactives = $service->getProjets()->count();
            $tachesDesactivees = 0;
            foreach ($service->getPersonnels() as $personnel) {
                $personnel->desactiver();
                $personnel->setFonctionPer($personnel->determinerFonction());
                $personnelsDesactives++;
            }

            foreach ($service->getProjets() as $projet) {
                $tachesDesactivees += $projet->getTaches()->count();
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Service désactivé avec succès',
                'statistiques' => [
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


   #[Route('/api/{codeService}/activer', name: 'api_service_activer', methods: ['POST'])]
   public function apiActiverService(Request $request, string $codeService, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        $service = $this->entityManager->getRepository(Service::class)->find($codeService);
        
        if (!$service) {
            return $this->json([
                'success' => false,
                'message' => 'Service non trouvé'
            ], 404);
        }

        if (!$service->estDesactive()) {
            return $this->json([
                'success' => false,
                'message' => 'Ce service n\'est pas désactivé'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);
        $garderChef = $data['garderChef'] ?? false;
        $nouveauChef = $data['nouveauChef'] ?? null; // Ceci est une STRING (ou null)

        // Activer le service
        $service->activer();

        // Gérer le chef de service
        $ancienChef = $service->getChefService();
        $ancienChefDisponible = $ancienChef && $ancienChef->estDesactive();

        if ($garderChef && $ancienChefDisponible) {
            // Réactiver l'ancien chef de service
            $ancienChef->activer();
            $ancienChef->setFonctionPer($ancienChef->determinerFonction());
            
        } elseif ($nouveauChef && $nouveauChef !== '') {
            // CAS "AUCUN CHEF DE SERVICE"
            if ($nouveauChef === 'AUCUN') {
                if ($ancienChef) {
                    $ancienChef->setService(null);
                    $ancienChef->setFonctionPer($ancienChef->determinerFonction());
                    $this->entityManager->persist($ancienChef);
                }
                $service->setChefService(null);
                $service->setStatutService('EN_ATTENTE');
            } else {
                // Assigner un nouveau chef de service
                // IMPORTANT: $nouveauChef est une STRING, on doit chercher l'objet Personnel
                $nouveauChefEntity = $personnelRepository->find($nouveauChef);
                
                if (!$nouveauChefEntity) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Personnel non trouvé'
                    ], 404);
                }

                // MAINTENANT on peut utiliser les méthodes d'objet
                if ($nouveauChefEntity->estDesactive()) {
                    $nouveauChefEntity->activer(); // Réactiver le personnel s'il est désactivé
                }

                // Vérifier si le nouveau chef n'est pas déjà chef d'un service actif
                $serviceActuelDuNouveauChef = $this->entityManager->getRepository(Service::class)
                    ->findOneBy(['chefService' => $nouveauChefEntity]);
                    
                if ($serviceActuelDuNouveauChef && $serviceActuelDuNouveauChef->estActif()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Ce personnel est déjà chef de service d\'un autre service actif'
                    ], 400);
                }

                // Libérer l'ancien chef si différent
                if ($ancienChef && $ancienChef !== $nouveauChefEntity) {
                    $ancienChef->setService(null);
                    $ancienChef->setFonctionPer($ancienChef->determinerFonction());
                }

                // Gérer le cas où le nouveau chef était directeur
                if ($nouveauChefEntity->estDirecteur() && $nouveauChefEntity->getDirectionD()) {
                    $directionDuChef = $nouveauChefEntity->getDirectionD();
                    $directionDuChef->setPersonnel(null);
                    $this->entityManager->persist($directionDuChef);
                }

                $service->setChefService($nouveauChefEntity);
                $nouveauChefEntity->setFonctionPer($nouveauChefEntity->determinerFonction());
            }
        } else {
            // Pas de chef de service - mode "en attente"
            if ($ancienChef) {
                $ancienChef->setService(null);
                $ancienChef->setFonctionPer($ancienChef->determinerFonction());
            }
            $service->setChefService(null);
            $service->setStatutService('EN_ATTENTE');
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Service activé avec succès'
        ], 200);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de l\'activation: ' . $e->getMessage()
        ], 500);
    }
}

    // API pour les sélections

    #[Route('/api/directions-actives', name: 'api_directions_actives', methods: ['GET'])]
    public function apiDirectionsActives(DirectionRepository $directionRepository): JsonResponse
    {
        try {
            $directions = $directionRepository->createQueryBuilder('d')
                ->where('d.statutDirection = :actif')
                ->setParameter('actif', 'ACTIVE')
                ->orderBy('d.nomDirection', 'ASC')
                ->getQuery()
                ->getResult();

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

    #[Route('/api/personnels-pour-chef-service', name: 'api_personnels_pour_chef_service', methods: ['GET'])]
    public function apiPersonnelsPourChefService(PersonnelRepository $personnelRepository): JsonResponse
    {
        try {
            $personnels = $personnelRepository->createQueryBuilder('p')
                ->where('(p.FonctionPer != :directeur AND p.FonctionPer != :chef_service) OR (p.FonctionPer = :directeur AND p.StatutPer = :desactive) OR (p.FonctionPer = :chef_service AND p.StatutPer = :desactive)')
                ->setParameter('directeur', 'directeur')  // Attention à la casse !
                ->setParameter('chef_service', 'chef_service')  // Attention à la casse !
                ->setParameter('desactive', 'DESACTIVE')
                ->orderBy('p.StatutPer', 'DESC') // ACTIF d'abord
                ->addOrderBy('p.NomPer', 'ASC')
                ->addOrderBy('p.PrenomPer', 'ASC')
                ->getQuery()
                ->getResult();

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

    #[Route('/api/personnels-pour-activation-service', name: 'api_personnels_pour_activation_service', methods: ['GET'])]
public function apiPersonnelsPourActivationService(Request $request, PersonnelRepository $personnelRepository): JsonResponse
{
    try {
        // Récupérer le code service depuis les paramètres de requête si disponible
        $codeService = $request->query->get('serviceCode');
        $ancienChefIm = null;
        
        // Si on a un code service, récupérer l'ancien chef
        if ($codeService) {
            $service = $this->entityManager->getRepository(Service::class)->find($codeService);
            if ($service && $service->getChefService()) {
                $ancienChefIm = $service->getChefService()->getImPer();
            }
        }

        // Créer le QueryBuilder de base
        $qb = $personnelRepository->createQueryBuilder('p')
            ->where('(p.FonctionPer != :directeur AND p.FonctionPer != :chef_service) OR (p.FonctionPer = :directeur AND p.StatutPer = :desactive) OR (p.FonctionPer = :chef_service AND p.StatutPer = :desactive)')
            ->setParameter('directeur', 'directeur')
            ->setParameter('chef_service', 'chef_service')
            ->setParameter('desactive', 'DESACTIVE');

        // Si on a un ancien chef, l'inclure dans les résultats avec OR
        if ($ancienChefIm) {
            $qb->orWhere('p.ImPer = :ancienChef')
               ->setParameter('ancienChef', $ancienChefIm);
        }

        $personnels = $qb->orderBy('p.StatutPer', 'DESC') // ACTIF d'abord
            ->addOrderBy('p.NomPer', 'ASC')
            ->addOrderBy('p.PrenomPer', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'personnels' => $personnels,
            'ancienChefIm' => $ancienChefIm
        ], 200, [], ['groups' => ['personnel:read']]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des personnels'
        ], 500);
    }
}


}