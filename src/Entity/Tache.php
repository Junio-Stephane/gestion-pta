<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\TacheRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['tache:read']],
    denormalizationContext: ['groups' => ['tache:write']]
)]
class Tache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "num_tache", type: 'integer')]
    #[Groups(['tache:read'])] // Retirer 'tache:write' car l'ID est généré automatiquement
    private ?int $numTache = null;

    #[ORM\Column(length: 40)]
    #[Groups(['tache:read', 'tache:write'])]
    #[Assert\NotBlank(message: "Le titre de la tâche est obligatoire")]
    private ?string $titreTache = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['tache:read', 'tache:write'])]
    private ?string $descriptionTache = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['tache:read', 'tache:write'])]
    private ?string $commentaireTache = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['tache:read', 'tache:write'])]
    #[Assert\NotNull(message: "La date de début est obligatoire")]
    private ?\DateTimeInterface $dateDebutTache = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['tache:read', 'tache:write'])]
    private ?\DateTimeInterface $dateFinTache = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['tache:read', 'tache:write'])]
    private ?int $avancementTache = 0;

    #[ORM\Column(length: 20)]
    #[Groups(['tache:read', 'tache:write'])]
    private ?string $statutTache = 'Débuté';

    #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'taches')]
    #[ORM\JoinColumn(name: 'num_projet', referencedColumnName: 'num_projet')]
    #[Groups(['tache:read', 'tache:write'])]
    #[Assert\NotNull(message: "Le projet est obligatoire")]
    private ?Projet $projet = null;

    public function __construct()
    {
        $this->avancementTache = 0;
    }

    public function getNumTache(): ?int
    {
        return $this->numTache;
    }

    // Supprimer setNumTache ou la rendre privée
    private function setNumTache(int $numTache): static
    {
        $this->numTache = $numTache;
        return $this;
    }

    // Le reste des getters et setters reste inchangé...
    public function getTitreTache(): ?string
    {
        return $this->titreTache;
    }

    public function setTitreTache(string $titreTache): static
    {
        $this->titreTache = $titreTache;
        return $this;
    }

    public function getDescriptionTache(): ?string
    {
        return $this->descriptionTache;
    }

    public function setDescriptionTache(?string $descriptionTache): static
    {
        $this->descriptionTache = $descriptionTache;
        return $this;
    }

    public function getCommentaireTache(): ?string
    {
        return $this->commentaireTache;
    }

    public function setCommentaireTache(?string $commentaireTache): static
    {
        $this->commentaireTache = $commentaireTache;
        return $this;
    }

    public function getDateDebutTache(): ?\DateTimeInterface
    {
        return $this->dateDebutTache;
    }

    public function setDateDebutTache(\DateTimeInterface $dateDebutTache): static
    {
        $this->dateDebutTache = $dateDebutTache;
        return $this;
    }

    public function getDateFinTache(): ?\DateTimeInterface
    {
        return $this->dateFinTache;
    }

    public function setDateFinTache(?\DateTimeInterface $dateFinTache): static
    {
        $this->dateFinTache = $dateFinTache;
        return $this;
    }

    public function getavancementTache(): ?int
    {
        return $this->avancementTache;
    }

    public function setavancementTache(?int $avancementTache): static
    {
        $this->avancementTache = $avancementTache === null ? null : max(0, min(100, $avancementTache));
        
        if ($this->statutTache !== 'Suspendu') {
            if ($this->avancementTache === null || $this->avancementTache === 0) {
                $this->statutTache = 'Débuté';
            } elseif ($this->avancementTache > 0 && $this->avancementTache < 100) {
                $this->statutTache = 'En cours';
            } elseif ($this->avancementTache === 100) {
                $this->statutTache = 'Terminé';
            }
        }
        
        return $this;
    }

    public function getStatutTache(): ?string
    {
        return $this->statutTache;
    }

    public function setStatutTache(string $statutTache): static
    {
        $this->statutTache = $statutTache;
        return $this;
    }

    public function getProjet(): ?Projet
    {
        return $this->projet;
    }

    public function setProjet(?Projet $projet): static
    {
        $this->projet = $projet;
        return $this;
    }
}