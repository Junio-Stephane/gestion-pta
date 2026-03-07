<?php
// src/Service/DirectionApiService.php

namespace App\Service;

use App\Repository\DirectionRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class DirectionApiService
{
    public function __construct(
        private DirectionRepository $directionRepository,
        private PersonnelRepository $personnelRepository,
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager
    ) {}

    public function getAllDirections(): array
    {
        try {
            $directions = $this->directionRepository->findAllWithDirecteur();
            
            $data = $this->serializer->serialize(
                $directions, 
                'json', 
                [
                    'groups' => ['direction:read', 'personnel:read'],
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                        return $object->getCodeDirection();
                    }
                ]
            );
            
            $decodedData = json_decode($data, true);
            
            // Transformer les données pour Twig
            return array_map(function($item) {
                return $this->formatDirectionForTwig($item);
            }, $decodedData);
            
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du chargement: ' . $e->getMessage());
        }
    }

    public function getDirection(string $code): ?array
    {
        try {
            $direction = $this->directionRepository->find($code);
            
            if (!$direction) {
                return null;
            }

            $data = $this->serializer->serialize(
                $direction, 
                'json', 
                [
                    'groups' => ['direction:read', 'personnel:read'],
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                        return $object->getCodeDirection();
                    }
                ]
            );
            
            $decodedData = json_decode($data, true);
            return $this->formatDirectionForTwig($decodedData);
            
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du chargement: ' . $e->getMessage());
        }
    }

    public function createDirection(array $data): bool
    {
        try {
            $direction = $this->serializer->deserialize(
                json_encode($data),
                'App\Entity\Direction',
                'json',
                ['groups' => 'direction:write']
            );

            $this->entityManager->persist($direction);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Erreur création: ' . $e->getMessage());
        }
    }

    public function updateDirection(string $code, array $data): bool
    {
        try {
            $direction = $this->directionRepository->find($code);
            
            if (!$direction) {
                throw new \Exception('Direction non trouvée');
            }


            $this->serializer->deserialize(
                json_encode($data),
                'App\Entity\Direction',
                'json',
                [
                    'groups' => 'direction:write',
                    AbstractNormalizer::OBJECT_TO_POPULATE => $direction
                ]
            );

            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Erreur modification: ' . $e->getMessage());
        }
    }

    public function deleteDirection(string $code): bool
    {
        try {
            $direction = $this->directionRepository->find($code);
            
            if (!$direction) {
                throw new \Exception('Direction non trouvée');
            }

            $this->entityManager->remove($direction);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Erreur suppression: ' . $e->getMessage());
        }
    }

    public function getPersonnelsNonDirecteurs(): array
    {
        try {
            $personnels = $this->personnelRepository->findPersonnelsNonDirecteurs();
            
            $data = $this->serializer->serialize(
                $personnels, 
                'json', 
                ['groups' => 'personnel:read']
            );
            
            $decodedData = json_decode($data, true);
            

            return array_map(function($item) {
                return $this->transformPersonnelKeys($item);
            }, $decodedData);
            
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPersonnelsDirecteurs(): array
    {
        try {
            $personnels = $this->personnelRepository->findPersonnelsDirecteurs();
            
            $data = $this->serializer->serialize(
                $personnels, 
                'json', 
                ['groups' => 'personnel:read']
            );
            
            $decodedData = json_decode($data, true);

            return array_map(function($item) {
                return $this->transformPersonnelKeys($item);
            }, $decodedData);
            
        } catch (\Exception $e) {
            return [];
        }
    }

    public function mettreEnAttenteDirection(string $code): bool
    {
        try {
            $direction = $this->directionRepository->find($code);
            
            if (!$direction) {
                throw new \Exception('Direction non trouvée');
            }

            $ancienDirecteur = $direction->getPersonnel();
            
            if ($ancienDirecteur) {
                $ancienDirecteur->setStatutPer('EN_ATTENTE_REAFFECTATION');
                $ancienDirecteur->setDirectionD(null);
                $this->entityManager->persist($ancienDirecteur);
            }

            $direction->setPersonnel(null);
            $direction->mettreEnAttente();
            $this->entityManager->persist($direction);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Erreur mise en attente: ' . $e->getMessage());
        }
    }

    /**
     * Formate les données de direction pour Twig
     */
    private function formatDirectionForTwig(array $directionData): array
    {
        // Transformer les clés de la direction
        $formatted = [
            'CodeDirection' => $directionData['CodeDirection'] ?? $directionData['codeDirection'] ?? null,
            'nomDirection' => $directionData['nomDirection'] ?? null,
            'statutDirection' => $directionData['statutDirection'] ?? null,
        ];

        // Gérer le personnel
        if (isset($directionData['personnel'])) {
            if (is_string($directionData['personnel'])) {
                // C'est une IRI, extraire l'ID
                $personnelId = basename($directionData['personnel']);
                $formatted['personnel'] = [
                    'im_per' => $personnelId,
                    'prenom_per' => 'Chargement...',
                    'nom_per' => 'Chargement...'
                ];
            } elseif (is_array($directionData['personnel'])) {
                // C'est un tableau de données, transformer les clés
                $formatted['personnel'] = $this->transformPersonnelKeys($directionData['personnel']);
            }
        }

        return $formatted;
    }

    /**
     * Transforme les clés du personnel de camelCase en snake_case
     */
    private function transformPersonnelKeys(array $personnelData): array
    {
        $transformations = [
            'ImPer' => 'im_per',
            'NomPer' => 'nom_per', 
            'PrenomPer' => 'prenom_per',
            'EmailPer' => 'email_per',
            'TelPer' => 'tel_per',
            'RolePer' => 'role_per',
            'directionD' => 'direction_d'
        ];

        $result = [];
        foreach ($personnelData as $key => $value) {
            $newKey = $transformations[$key] ?? $key;
            
            if (is_array($value) && $key === 'directionD') {
                $value = $this->transformDirectionKeys($value);
            }
            
            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * Transforme les clés de direction de camelCase en snake_case
     */
    private function transformDirectionKeys(array $directionData): array
    {
        $transformations = [
            'CodeDirection' => 'code_direction',
            'nomDirection' => 'nom_direction',
            'statutDirection' => 'statut_direction'
        ];

        $result = [];
        foreach ($directionData as $key => $value) {
            $newKey = $transformations[$key] ?? $key;
            $result[$newKey] = $value;
        }

        return $result;
    }
}