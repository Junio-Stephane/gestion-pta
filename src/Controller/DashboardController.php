<?php

namespace App\Controller;

use App\Entity\Personnel;
use App\Entity\Direction;
use App\Entity\Service;
use App\Entity\Tache;
use App\Entity\Notification;
use App\Entity\Projet;
use App\Service\NotificationService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    // ROUTE PRINCIPALE DE REDIRECTION PAR RÔLE
    #[Route('/dashboard', name: 'app_dashboard_par_role')]
    public function dashboardParRole(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Personnel) {
            return $this->redirectToRoute('app_login');
        }

        // VÉRIFICATION DE SÉCURITÉ : S'assurer que l'utilisateur est actif et a un rôle valide
        if ($user->getStatutPer() !== 'ACTIF' || $user->getRolePer() === 'ROLE_EN_ATTENTE') {
            $this->addFlash('error', 'Votre compte n\'est pas activé ou a été désactivé.');
            return $this->redirectToRoute('app_logout');
        }

        // Redirection selon le rôle
        if ($user->isAdmin()) {
            return $this->redirectToRoute('app_dashboard_admin');
        } elseif ($user->hasRole('ROLE_CREATEUR_DE_PROJET')) {
            return $this->redirectToRoute('app_dashboard_createur');
        } elseif ($user->hasRole('ROLE_UTILISATEUR')) {
            return $this->redirectToRoute('app_dashboard_utilisateur');
        } else {
            // Si aucun rôle valide, déconnecter
            $this->addFlash('error', 'Vous n\'avez pas les permissions nécessaires pour accéder au dashboard.');
            return $this->redirectToRoute('app_logout');
        }
    }

    // DASHBOARD ADMIN
    #[Route('/dashboard/admin', name: 'app_dashboard_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboardAdmin(Request $request, EntityManagerInterface $em): Response
    {
        date_default_timezone_set('Indian/Antananarivo'); // 🆕 AJOUTEZ CETTE LIGNE

        $user = $this->getUser();

        // DOUBLE VÉRIFICATION DE SÉCURITÉ
        if (!$user instanceof Personnel || $user->getStatutPer() !== 'ACTIF') {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_logout');
        }

        // Récupérer les statistiques pour le personnel
        $totalUsers = $em->getRepository(Personnel::class)->count([]);
        $activeUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'ACTIF']);
        $pendingUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'EN ATTENTE']);
        $disabledUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'DESACTIVE']);
        $rejectedUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'REJETE']);

        // Récupérer les statistiques pour les directions
        $totalDirections = $em->getRepository(Direction::class)->count([]);
        $activeDirections = $em->getRepository(Direction::class)->count(['statutDirection' => 'ACTIVE']);
        $pendingDirections = $em->getRepository(Direction::class)->count(['statutDirection' => 'EN_ATTENTE']);
        $disabledDirections = $em->getRepository(Direction::class)->count(['statutDirection' => 'DESACTIVEE']);

        // Récupérer les statistiques pour les services
        $totalServices = $em->getRepository(Service::class)->count([]);
        $activeServices = $em->getRepository(Service::class)->count(['statutService' => 'ACTIF']);
        $pendingServices = $em->getRepository(Service::class)->count(['statutService' => 'EN_ATTENTE']);
        $disabledServices = $em->getRepository(Service::class)->count(['statutService' => 'DESACTIVE']);

        // Récupérer les statistiques pour les projets
        $totalProjets = $em->getRepository(Projet::class)->count([]);
        $startedProjets = $em->getRepository(Projet::class)->count(['StatutPro' => 'Débuté']);
        $inProgressProjets = $em->getRepository(Projet::class)->count(['StatutPro' => 'En cours']);
        $completedProjets = $em->getRepository(Projet::class)->count(['StatutPro' => 'Terminé']);
        $suspendedProjets = $em->getRepository(Projet::class)->count(['StatutPro' => 'Suspendu']);

        // Récupérer les statistiques pour les tâches
        $totalTaches = $em->getRepository(Tache::class)->count([]);
        $startedTaches = $em->getRepository(Tache::class)->count(['statutTache' => 'Débuté']);
        $inProgressTaches = $em->getRepository(Tache::class)->count(['statutTache' => 'En cours']);
        $completedTaches = $em->getRepository(Tache::class)->count(['statutTache' => 'Terminé']);
        $suspendedTaches = $em->getRepository(Tache::class)->count(['statutTache' => 'Suspendu']);

        // Récupérer les utilisateurs en attente
        $pendingUsersList = $em->getRepository(Personnel::class)->findBy(
            ['StatutPer' => 'EN ATTENTE'],
            ['Date_creationPer' => 'DESC']
        );

        // 🆕 CORRECTION DU TEMPS ÉCOULÉ - FUSEAU HORAIRE COHÉRENT
        $now = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
        foreach ($pendingUsersList as $pendingUser) {
            $userCreationDate = $pendingUser->getDateCreationPer();

            // CONVERTIR LA DATE DE CRÉATION AU FUSEAU INDIAN/ANTANANARIVO
            if ($userCreationDate->getTimezone()->getName() !== 'Indian/Antananarivo') {
                $userCreationDate = clone $userCreationDate; // Éviter de modifier l'original
                $userCreationDate->setTimezone(new \DateTimeZone('Indian/Antananarivo'));
            }

            $interval = $now->diff($userCreationDate);
            $pendingUser->setTimeAgo($this->formatTimeAgo($interval));
        }

        // COMPTER LES NOTIFICATIONS NON LUES
        $unreadNotificationsCount = $em->getRepository(Notification::class)
            ->count(['isRead' => false]);

        // RÉCUPÉRER L'UTILISATEUR À METTRE EN SURBRILLANCE
        $highlightUserIm = $request->query->get('highlight') ?? $request->getSession()->get('highlightUserIm');

        // Gestion des requêtes AJAX
        if ($request->isXmlHttpRequest() && $request->query->get('partial') === 'pending_list') {
            $latestOnly = $request->query->get('latest');

            if ($latestOnly && count($pendingUsersList) > 0) {
                return $this->render('dashboard/_pending_list.html.twig', [
                    'pending_users_list' => [$pendingUsersList[0]],
                    'pending_users' => $pendingUsers,
                    'highlight_user_im' => $highlightUserIm
                ]);
            }

            return $this->render('dashboard/_pending_list.html.twig', [
                'pending_users_list' => $pendingUsersList,
                'pending_users' => $pendingUsers,
                'highlight_user_im' => $highlightUserIm
            ]);
        }


        return $this->render('dashboard/admin.html.twig', [
            // Personnel
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'disabled_users' => $disabledUsers,
            'rejected_users' => $rejectedUsers,

            // Directions
            'total_directions' => $totalDirections,
            'active_directions' => $activeDirections,
            'pending_directions' => $pendingDirections,
            'disabled_directions' => $disabledDirections,

            // Services
            'total_services' => $totalServices,
            'active_services' => $activeServices,
            'pending_services' => $pendingServices,
            'disabled_services' => $disabledServices,

            // Projets
            'total_projets' => $totalProjets,
            'started_projets' => $startedProjets,
            'in_progress_projets' => $inProgressProjets,
            'completed_projets' => $completedProjets,
            'suspended_projets' => $suspendedProjets,

            // Tâches
            'total_taches' => $totalTaches,
            'started_taches' => $startedTaches,
            'in_progress_taches' => $inProgressTaches,
            'completed_taches' => $completedTaches,
            'suspended_taches' => $suspendedTaches,

            // Autres données
            'pending_users_list' => $pendingUsersList,
            'unread_notifications_count' => $unreadNotificationsCount,
            'highlight_user_im' => $highlightUserIm,
            'user_role' => 'admin',
        ]);
    }

    #[Route('/dashboard/admin/stats', name: 'app_dashboard_admin_stats')]
    public function getAgentStats(EntityManagerInterface $em): JsonResponse
    {
        $totalUsers = $em->getRepository(Personnel::class)->count([]);
        $activeUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'ACTIF']);
        $pendingUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'EN ATTENTE']);
        $disabledUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'DESACTIVE']);
        $rejectedUsers = $em->getRepository(Personnel::class)->count(['StatutPer' => 'REJETE']);

        return $this->json([
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'disabled_users' => $disabledUsers,
            'rejected_users' => $rejectedUsers
        ]);
    }

    // DASHBOARD CREATEUR DE PROJET
    #[Route('/dashboard/createur', name: 'app_dashboard_createur')]
    #[IsGranted('ROLE_CREATEUR_DE_PROJET')]
    public function dashboardCreateur(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // VÉRIFICATION DE SÉCURITÉ
        if (!$user instanceof Personnel || $user->getStatutPer() !== 'ACTIF') {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_logout');
        }

        // Récupérer TOUS les projets créés par cet utilisateur
        $mesProjets = $em->getRepository(Projet::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.taches', 't')
            ->addSelect('t')
            ->leftJoin('p.service', 's')
            ->addSelect('s')
            ->leftJoin('p.personnels', 'pers')
            ->addSelect('pers')
            ->where('p.createur = :createur')
            ->setParameter('createur', $user)
            ->orderBy('p.dateDebutPro', 'DESC')
            ->getQuery()
            ->getResult();

        // Récupérer TOUS les projets (pour l'option "tous les projets")
        $tousLesProjets = $em->getRepository(Projet::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.taches', 't')
            ->addSelect('t')
            ->leftJoin('p.service', 's')
            ->addSelect('s')
            ->leftJoin('p.personnels', 'pers')
            ->addSelect('pers')
            ->leftJoin('p.createur', 'c')
            ->addSelect('c')
            ->orderBy('p.dateDebutPro', 'DESC')
            ->getQuery()
            ->getResult();

        // STATISTIQUES GÉNÉRALES
        $totalProjets = count($mesProjets);
        $totalTousProjets = count($tousLesProjets);

        // Projets par statut (mes projets)
        $projetsDebut = array_filter($mesProjets, fn($p) => $p->getStatutPro() === 'Débuté');
        $projetsEnCours = array_filter($mesProjets, fn($p) => $p->getStatutPro() === 'En cours');
        $projetsTermines = array_filter($mesProjets, fn($p) => $p->getStatutPro() === 'Terminé');
        $projetsSuspendus = array_filter($mesProjets, fn($p) => $p->getStatutPro() === 'Suspendu');

        // Projets par statut (tous les projets)
        $startedProjets = count(array_filter($tousLesProjets, fn($p) => $p->getStatutPro() === 'Débuté'));
        $inProgressProjets = count(array_filter($tousLesProjets, fn($p) => $p->getStatutPro() === 'En cours'));
        $completedProjets = count(array_filter($tousLesProjets, fn($p) => $p->getStatutPro() === 'Terminé'));
        $suspendedProjets = count(array_filter($tousLesProjets, fn($p) => $p->getStatutPro() === 'Suspendu'));

        $projetsActifs = array_merge($projetsDebut, $projetsEnCours);

        // DONNÉES POUR LES GRAPHIQUES - CORRIGÉ

        // 1. Données pour MES PROJETS (utilisées par défaut)
        $donneesAvancementMesProjets = [];
        foreach ($mesProjets as $projet) {
            // Vérifier si la deadline approche (dans les 7 jours)
            $deadlineApproche = false;
            if ($projet->getDateFinPro()) {
                $now = new \DateTime();
                $interval = $now->diff($projet->getDateFinPro());
                $deadlineApproche = $interval->days <= 7 && $interval->invert === 0;
            }

            $donneesAvancementMesProjets[] = [
                'titre' => $projet->getTitrePro(),
                'avancement' => $projet->getAvancementPro() ?? 0,
                'statut' => $projet->getStatutPro(),
                'numero' => $projet->getNumProjet(),
                'service' => $projet->getService() ? $projet->getService()->getNomService() : 'Aucun service',
                'dateDebut' => $projet->getDateDebutPro() ? $projet->getDateDebutPro()->format('Y-m-d') : null,
                'dateFin' => $projet->getDateFinPro() ? $projet->getDateFinPro()->format('Y-m-d') : null,
                'createur' => $projet->getCreateur()->getPrenomPer() . ' ' . $projet->getCreateur()->getNomPer(),
                'estMonProjet' => true,
                'deadlineApproche' => $deadlineApproche
            ];
        }

        // 2. Données pour TOUS les projets
        $donneesAvancementTousProjets = [];
        foreach ($tousLesProjets as $projet) {
            // Vérifier si la deadline approche (dans les 7 jours)
            $deadlineApproche = false;
            if ($projet->getDateFinPro()) {
                $now = new \DateTime();
                $interval = $now->diff($projet->getDateFinPro());
                $deadlineApproche = $interval->days <= 7 && $interval->invert === 0;
            }

            $donneesAvancementTousProjets[] = [
                'titre' => $projet->getTitrePro(),
                'avancement' => $projet->getAvancementPro() ?? 0,
                'statut' => $projet->getStatutPro(),
                'numero' => $projet->getNumProjet(),
                'service' => $projet->getService() ? $projet->getService()->getNomService() : 'Aucun service',
                'dateDebut' => $projet->getDateDebutPro() ? $projet->getDateDebutPro()->format('Y-m-d') : null,
                'dateFin' => $projet->getDateFinPro() ? $projet->getDateFinPro()->format('Y-m-d') : null,
                'createur' => $projet->getCreateur()->getPrenomPer() . ' ' . $projet->getCreateur()->getNomPer(),
                'estMonProjet' => $projet->getCreateur()->getImPer() === $user->getImPer(),
                'deadlineApproche' => $deadlineApproche
            ];
        }

        // 3. Données pour le camembert (répartition par statut) - MES PROJETS
        $donneesStatuts = [
            'Débuté' => count($projetsDebut),
            'En cours' => count($projetsEnCours),
            'Terminé' => count($projetsTermines),
            'Suspendu' => count($projetsSuspendus)
        ];

        // 4. Statistiques des tâches
        $totalTaches = 0;
        $tachesCompletees = 0;

        foreach ($mesProjets as $projet) {
            $taches = $projet->getTaches();
            $totalTaches += count($taches);

            foreach ($taches as $tache) {
                if ($tache->getAvancementTache() === 100) {
                    $tachesCompletees++;
                }
            }
        }

        // 5. Services uniques pour les filtres
        $servicesUniques = [];
        foreach ($tousLesProjets as $projet) {
            $service = $projet->getService();
            if ($service) {
                $nomService = $service->getNomService();
                $servicesUniques[$nomService] = $service->getCodeService();
            }
        }

        return $this->render('dashboard/createur.html.twig', [
            // Données principales
            'mes_projets' => $mesProjets,
            'tous_les_projets' => $tousLesProjets,

            // Statistiques générales
            'total_projets' => $totalProjets,
            'total_tous_projets' => $totalTousProjets,
            'projets_actifs' => count($projetsActifs),
            'projets_termines' => count($projetsTermines),

            // Statistiques tous projets
            'started_projets' => $startedProjets,
            'in_progress_projets' => $inProgressProjets,
            'completed_projets' => $completedProjets,
            'suspended_projets' => $suspendedProjets,

            // Données détaillées pour les graphiques
            'projets_debut' => count($projetsDebut),
            'projets_en_cours' => count($projetsEnCours),
            'projets_suspendus' => count($projetsSuspendus),

            // Données structurées pour JavaScript - CORRIGÉ
            'donnees_avancement_mes_projets' => $donneesAvancementMesProjets,
            'donnees_avancement_tous_projets' => $donneesAvancementTousProjets,
            'donnees_statuts' => $donneesStatuts,
            'services_uniques' => $servicesUniques,

            // Statistiques avancées
            'total_taches' => $totalTaches,
            'taches_completees' => $tachesCompletees,

            'user_role' => 'createur',
        ]);
    }

    // DASHBOARD UTILISATEUR SIMPLE
    #[Route('/dashboard/utilisateur', name: 'app_dashboard_utilisateur')]
    #[IsGranted('ROLE_UTILISATEUR')]
    public function dashboardUtilisateur(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // VÉRIFICATION DE SÉCURITÉ
        if (!$user instanceof Personnel || $user->getStatutPer() !== 'ACTIF') {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_logout');
        }

        // Récupérer les projets où l'utilisateur est assigné
        $projetsAssignes = [];
        $tachesAssignees = [];

        if (method_exists($user, 'getProjetsG')) {
            $projetsAssignes = $user->getProjetsG()->toArray();

            // Récupérer les tâches assignées à cet utilisateur
            foreach ($projetsAssignes as $projet) {
                foreach ($projet->getTaches() as $tache) {
                    $tachesAssignees[] = $tache;
                }
            }
        }

        // Préparer les données pour les graphiques
        $donneesProjetsUtilisateur = [];
        $donneesTachesUtilisateur = [];

        foreach ($projetsAssignes as $projet) {
            // Vérifier si la deadline approche (dans les 7 jours)
            $deadlineApproche = false;
            if ($projet->getDateFinPro()) {
                $now = new \DateTime();
                $interval = $now->diff($projet->getDateFinPro());
                $deadlineApproche = $interval->days <= 7 && $interval->invert === 0;
            }

            // Formater les dates correctement
            $dateDebut = $projet->getDateDebutPro() ? $projet->getDateDebutPro()->format('Y-m-d') : null;
            $dateFin = $projet->getDateFinPro() ? $projet->getDateFinPro()->format('Y-m-d') : null;

            $donneesProjetsUtilisateur[] = [
                'titre' => $projet->getTitrePro(),
                'avancement' => $projet->getAvancementPro() ?? 0,
                'statut' => $projet->getStatutPro(),
                'numero' => $projet->getNumProjet(),
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'deadlineApproche' => $deadlineApproche
            ];

            // Données pour les tâches
            foreach ($projet->getTaches() as $tache) {
                $donneesTachesUtilisateur[] = [
                    'titre' => $tache->getTitreTache(),
                    'avancement' => $tache->getAvancementTache() ?? 0,
                    'statut' => $tache->getStatutTache(),
                    'projetId' => $projet->getNumProjet(),
                    'projetTitre' => $projet->getTitrePro()
                ];
            }
        }

        // Calculer les statistiques des projets par statut
        $projetsDebutes = array_filter($projetsAssignes, fn($p) => $p->getStatutPro() === 'Débuté');
        $projetsEnCours = array_filter($projetsAssignes, fn($p) => $p->getStatutPro() === 'En cours');
        $projetsTermines = array_filter($projetsAssignes, fn($p) => $p->getStatutPro() === 'Terminé');
        $projetsSuspendus = array_filter($projetsAssignes, fn($p) => $p->getStatutPro() === 'Suspendu');

        // Calculer les statistiques des tâches
        $tachesDebutees = array_filter($tachesAssignees, fn($t) => $t->getStatutTache() === 'Débuté');
        $tachesEnCours = array_filter($tachesAssignees, fn($t) => $t->getStatutTache() === 'En cours');
        $tachesTerminees = array_filter($tachesAssignees, fn($t) => $t->getStatutTache() === 'Terminé');
        $tachesSuspendues = array_filter($tachesAssignees, fn($t) => $t->getStatutTache() === 'Suspendu');

        return $this->render('dashboard/utilisateur.html.twig', [
            'projets_assignes' => $projetsAssignes,
            'taches_assignees' => $tachesAssignees,

            // Statistiques des projets
            'total_projets' => count($projetsAssignes),
            'projets_debutes' => count($projetsDebutes),
            'projets_en_cours' => count($projetsEnCours),
            'projets_termines' => count($projetsTermines),
            'projets_suspendus' => count($projetsSuspendus),

            // Statistiques des tâches
            'total_taches' => count($tachesAssignees),
            'taches_debutees' => count($tachesDebutees),
            'taches_en_cours' => count($tachesEnCours),
            'taches_terminees' => count($tachesTerminees),
            'taches_suspendues' => count($tachesSuspendues),

            // Données pour les graphiques
            'donnees_projets_utilisateur' => $donneesProjetsUtilisateur,
            'donnees_taches_utilisateur' => $donneesTachesUtilisateur,

            'user_role' => 'utilisateur',
        ]);
    }

    /**
     * Formate l'intervalle de temps en texte lisible
     */
    /**
     * Formate l'intervalle de temps en texte lisible avec gestion du fuseau horaire
     */
    private function formatTimeAgo(\DateInterval $interval): string
    {
        if ($interval->y > 0) {
            return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
        }
        if ($interval->m > 0) {
            return $interval->m . ' mois';
        }
        if ($interval->d > 0) {
            $days = $interval->d;
            if ($interval->h >= 12) $days++; // Arrondir si plus de 12 heures
            return $days . ' jour' . ($days > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }
        return 'Quelques secondes';
    }

    // MÉTHODES D'APPROBATION/REJET (Admin uniquement)
    #[Route('/admin/user/{id}/approve', name: 'app_admin_user_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approveUser(Request $request, string $id, EntityManagerInterface $em, EmailService $emailService, NotificationService $notificationService): Response
    {
        $user = $em->getRepository(Personnel::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        // Récupérer le rôle depuis le formulaire
        $role = $request->request->get('role', '');

        // VALIDATION DU RÔLE - OBLIGATOIRE
        $validRoles = ['ROLE_ADMIN', 'ROLE_CREATEUR_DE_PROJET', 'ROLE_UTILISATEUR'];
        if (empty($role) || !in_array($role, $validRoles)) {
            $this->addFlash('error', 'Veuillez sélectionner un rôle valide pour cet utilisateur.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        // MARQUER LA NOTIFICATION COMME LUE AVANT L'APPROBATION
        $notificationService->markNotificationAsReadByUser($user);

        // Approuver l'utilisateur
        $user->setStatutPer('ACTIF');
        $user->setRolePer($role);
        $user->setIsValidated(true);
        $user->setFonctionPer($user->determinerFonction());

        $em->flush();

        // ENVOYER L'EMAIL DE CONFIRMATION
        $emailSent = $emailService->sendAccountApprovalEmail(
            $user->getEmailPer(),
            $user->getPrenomPer() . ' ' . $user->getNomPer()
        );

        // Convertir le rôle en nom lisible pour le message flash
        $roleLisible = $this->getRoleLisible($role);

        $message = sprintf(
            'Utilisateur <strong>%s %s</strong> a été approuvé avec succès. Rôle attribué : <strong>%s</strong>.',
            $user->getPrenomPer(),
            $user->getNomPer(),
            $roleLisible
        );

        if ($emailSent) {
            $message .= ' Un email de confirmation a été envoyé.';
        } else {
            $message .= ' <strong>Attention:</strong> L\'email de confirmation n\'a pas pu être envoyé.';
        }

        $this->addFlash('success', $message);

        // Redirection avec paramètre pour surbrillance
        return $this->redirectToRoute('app_dashboard_admin', [
            'highlight' => $user->getImPer()
        ]);
    }

    /**
     * Convertir un rôle technique en nom lisible
     */
    private function getRoleLisible(string $role): string
    {
        $roles = [
            'ROLE_ADMIN' => 'Administrateur',
            'ROLE_CREATEUR_DE_PROJET' => 'Créateur de projet',
            'ROLE_UTILISATEUR' => 'Utilisateur'
        ];

        return $roles[$role] ?? $role;
    }

    #[Route('/admin/user/{id}/reject', name: 'app_admin_user_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectUser(string $id, EntityManagerInterface $em, EmailService $emailService, NotificationService $notificationService): Response
    {
        $user = $em->getRepository(Personnel::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        $userName = $user->getPrenomPer() . ' ' . $user->getNomPer();
        $userEmail = $user->getEmailPer();

        // MARQUER LA NOTIFICATION COMME LUE AVANT LE REJET
        $notificationService->markNotificationAsReadByUser($user);

        // Rejeter l'utilisateur
        $user->setStatutPer('REJETE');
        $user->setRolePer('ROLE_REJETE');
        $user->setIsValidated(false);
        $em->flush();

        // ENVOYER L'EMAIL DE REJET
        $emailSent = $emailService->sendAccountRejectionEmail($userEmail, $userName);

        $message = sprintf('Utilisateur %s a été rejeté.', $userName);

        if ($emailSent) {
            $message .= ' Un email de notification a été envoyé.';
        } else {
            $message .= ' <strong>Attention:</strong> L\'email de notification n\'a pas pu être envoyé.';
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_dashboard_admin');
    }

    // NOUVELLE MÉTHODE : Désactivation d'utilisateur
    #[Route('/admin/user/{id}/disable', name: 'app_admin_user_disable', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function disableUser(string $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(Personnel::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        $userName = $user->getPrenomPer() . ' ' . $user->getNomPer();

        // Désactiver l'utilisateur
        $user->setStatutPer('DESACTIVE');
        $em->flush();

        $this->addFlash('success', sprintf('Utilisateur %s a été désactivé.', $userName));

        return $this->redirectToRoute('app_dashboard_admin');
    }

    // NOUVELLE MÉTHODE : Activation d'utilisateur
    #[Route('/admin/user/{id}/enable', name: 'app_admin_user_enable', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function enableUser(string $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(Personnel::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        $userName = $user->getPrenomPer() . ' ' . $user->getNomPer();

        // Activer l'utilisateur
        $user->setStatutPer('ACTIF');
        $em->flush();

        $this->addFlash('success', sprintf('Utilisateur %s a été activé.', $userName));

        return $this->redirectToRoute('app_dashboard_admin');
    }
}
