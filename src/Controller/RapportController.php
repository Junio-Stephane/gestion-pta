<?php

namespace App\Controller;

use App\Entity\Direction;
use App\Entity\Service;
use App\Entity\Projet;
use App\Entity\Tache;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PdfGeneratorService;


class RapportController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    #[Route('/rapports/projets', name: 'app_rapport_projets')]
    public function index(): Response
    {
        return $this->render('rapport/index.html.twig');
    }


    #[Route('/api/rapport-directions-actives', name: 'api_rapport_directions_actives', methods: ['GET'])]
    public function getActiveDirections(): JsonResponse
    {
        try {
            $directions = $this->entityManager->getRepository(Direction::class)
                ->findBy(['statutDirection' => 'ACTIVE']);

            $data = [];
            foreach ($directions as $direction) {
                $data[] = [
                    'CodeDirection' => $direction->getCodeDirection(),
                    'nomDirection' => $direction->getNomDirection()
                ];
            }

            return $this->json([
                'success' => true,
                'directions' => $data
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des directions'
            ], 500);
        }
    }


    #[Route('/api/rapport/direction/{codeDirection}', name: 'api_rapport_direction', methods: ['GET'])]
    public function getRapportDirection(string $codeDirection): JsonResponse
    {
        try {
            $direction = $this->entityManager->getRepository(Direction::class)
                ->findOneBy(['CodeDirection' => $codeDirection]);

            if (!$direction) {
                return $this->json([
                    'success' => false,
                    'message' => 'Direction non trouvée'
                ], 404);
            }

            $data = $this->serializeDirectionForRapport($direction);

            return $this->json([
                'success' => true,
                'direction' => $data
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des données'
            ], 500);
        }
    }


    private function serializeDirectionForRapport(Direction $direction): array
    {
        $data = [
            'CodeDirection' => $direction->getCodeDirection(),
            'nomDirection' => $direction->getNomDirection(),
            'statutDirection' => $direction->getStatutDirection(),
            'services' => []
        ];

        foreach ($direction->getServices() as $service) {
            // INCLURE TOUS LES SERVICES, MÊME DÉSACTIVÉS
            $serviceData = [
                'CodeService' => $service->getCodeService(),
                'nomService' => $service->getNomService(),
                'statutService' => $service->getStatutService(),
                'projets' => []
            ];

            foreach ($service->getProjets() as $projet) {
                // INCLURE TOUS LES PROJETS, MÊME SUSPENDUS/DÉSACTIVÉS
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
                    // AJOUT DU CRÉATEUR
                    'createur' => $projet->getCreateur() ? [
                        'ImPer' => $projet->getCreateur()->getImPer(),
                        'NomPer' => $projet->getCreateur()->getNomPer(),
                        'PrenomPer' => $projet->getCreateur()->getPrenomPer()
                    ] : null,
                    // AJOUT DES RESPONSABLES
                    'personnels' => [],
                    'taches' => []
                ];

                // Responsables du projet
                foreach ($projet->getPersonnels() as $personnel) {
                    $projetData['personnels'][] = [
                        'ImPer' => $personnel->getImPer(),
                        'NomPer' => $personnel->getNomPer(),
                        'PrenomPer' => $personnel->getPrenomPer(),
                        'EmailPer' => $personnel->getEmailPer(),
                        'FonctionPer' => $personnel->getFonctionPer()
                    ];
                }

                // Tâches du projet  - INCLURE TOUTES LES TÂCHES
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


    #[Route('/api/rapport/export-pdf/{codeDirection}', name: 'api_rapport_export_pdf', methods: ['GET'])]
    public function exportPdf(string $codeDirection, PdfGeneratorService $pdfGenerator): Response
    {
        try {
            $direction = $this->entityManager->getRepository(Direction::class)
                ->findOneBy(['CodeDirection' => $codeDirection]);

            if (!$direction) {
                return $this->json([
                    'success' => false,
                    'message' => 'Direction non trouvée'
                ], 404);
            }

            $data = $this->serializeDirectionForRapport($direction);
            $stats = $this->calculateStatistiques($direction);

            $pdfContent = $pdfGenerator->generateRapportPdf([
                'direction' => $data,
                'stats' => $stats,
                'dateRapport' => (new \DateTime())->format('d/m/Y')
            ]);

            // Retourner le PDF
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set(
                'Content-Disposition',
                sprintf(
                    'attachment; filename="Rapport_%s_%s.pdf"',
                    $direction->getNomDirection(),
                    (new \DateTime())->format('Y-m-d')
                )
            );

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/rapport/export-excel/{codeDirection}', name: 'api_rapport_export_excel', methods: ['GET'])]
    public function exportExcel(string $codeDirection): JsonResponse
    {
        try {
            // Cette méthode serait utilisée pour générer un Excel côté serveur
            // Pour l'instant, on retourne un succès car l'Excel est généré côté client
            return $this->json([
                'success' => true,
                'message' => 'Fichier Excel généré avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du fichier Excel'
            ], 500);
        }
    }


    #[Route('/api/rapport/statistiques/{codeDirection}', name: 'api_rapport_statistiques', methods: ['GET'])]
    public function getStatistiques(string $codeDirection): JsonResponse
    {
        try {
            $direction = $this->entityManager->getRepository(Direction::class)
                ->findOneBy(['CodeDirection' => $codeDirection, 'statutDirection' => 'ACTIVE']);

            if (!$direction) {
                return $this->json([
                    'success' => false,
                    'message' => 'Direction non trouvée'
                ], 404);
            }

            $statistiques = $this->calculateStatistiques($direction);

            return $this->json([
                'success' => true,
                'statistiques' => $statistiques
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }


    private function calculateStatistiques(Direction $direction): array
    {
        $totalServices = 0;
        $totalProjets = 0;
        $totalTaches = 0;
        $budgetTotal = 0;
        $totalAvancementProjets = 0;
        $totalAvancementTaches = 0;
        $projetsParStatut = [
            'Débuté' => 0,
            'En cours' => 0,
            'Terminé' => 0,
            'Suspendu' => 0
        ];
        $tachesParStatut = [
            'Débuté' => 0,
            'En cours' => 0,
            'Terminé' => 0,
            'Suspendu' => 0
        ];

        foreach ($direction->getServices() as $service) {
            if ($service->getStatutService() !== 'ACTIF') {
                continue;
            }

            $totalServices++;

            foreach ($service->getProjets() as $projet) {
                $totalProjets++;
                $budgetTotal += floatval($projet->getBudgetPro());
                $projetsParStatut[$projet->getStatutPro()]++;

                if ($projet->getavancementPro() !== null) {
                    $totalAvancementProjets += $projet->getavancementPro();
                }

                foreach ($projet->getTaches() as $tache) {
                    $totalTaches++;
                    $tachesParStatut[$tache->getStatutTache()]++;

                    if ($tache->getavancementTache() !== null) {
                        $totalAvancementTaches += $tache->getavancementTache();
                    }
                }
            }
        }

        $avancementMoyenProjets = $totalProjets > 0 ? round($totalAvancementProjets / $totalProjets) : 0;
        $avancementMoyenTaches = $totalTaches > 0 ? round($totalAvancementTaches / $totalTaches) : 0;

        return [
            'totalServices' => $totalServices,
            'totalProjets' => $totalProjets,
            'totalTaches' => $totalTaches,
            'budgetTotal' => $budgetTotal,
            'avancementMoyenProjets' => $avancementMoyenProjets,
            'avancementMoyenTaches' => $avancementMoyenTaches,
            'projetsParStatut' => $projetsParStatut,
            'tachesParStatut' => $tachesParStatut
        ];
    }
}
