<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Tache;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\ProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['projet:read']],
    denormalizationContext: ['groups' => ['projet:write']]
)]
class Projet
{
    #[ORM\Id]
    #[ORM\Column(name: "num_projet", length: 20)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $numProjet = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: "Le titre du projet est obligatoire")]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $titrePro = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $descriptionPro = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $commentairePro = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank(message: "Le budget est obligatoire")]
    #[Assert\Positive(message: "Le budget doit être positif")]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $budgetPro = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: "La date de début est obligatoire")]
    #[Groups(['projet:read', 'projet:write'])]
    private ?\DateTimeInterface $dateDebutPro = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?\DateTimeInterface $dateFinPro = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?int $avancementPro = 0;

    #[ORM\Column(length: 20)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?string $StatutPro = 'Débuté';

    #[ORM\ManyToOne(targetEntity: Personnel::class, inversedBy: 'projetsCrees')]
    #[ORM\JoinColumn(name: 'im_per_createur', referencedColumnName: 'im_per', nullable: false)]
    #[Groups(['projet:read'])]
    private ?Personnel $createur = null;
    

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'projets')]
    #[ORM\JoinColumn(name: 'code_service', referencedColumnName: 'code_service', nullable: true)]
    #[Groups(['projet:read', 'projet:write'])]
    private ?Service $service = null;

    /**
     * @var Collection<int, Personnel>
     */
    #[ORM\ManyToMany(targetEntity: Personnel::class, mappedBy: 'projetsG')]
    #[Groups(['projet:read', 'projet:write'])]
    private Collection $personnels;

    #[ORM\OneToMany(targetEntity: Tache::class, mappedBy: 'projet')]
    #[Groups(['projet:read'])]
    private Collection $taches;

    public function __construct()
    {
        $this->avancementPro = 0;
        $this->personnels = new ArrayCollection();
        $this->taches = new ArrayCollection();
    }

    public function getNumProjet(): ?string
    {
        return $this->numProjet;
    }

    public function setNumProjet(string $numProjet): static
    {
        $this->numProjet = $numProjet;
        return $this;
    }

    public function getTitrePro(): ?string
    {
        return $this->titrePro;
    }

    public function setTitrePro(string $titrePro): static
    {
        $this->titrePro = $titrePro;
        return $this;
    }

    public function getDescriptionPro(): ?string
    {
        return $this->descriptionPro;
    }

    public function setDescriptionPro(?string $descriptionPro): static // Accepte null
    {
        $this->descriptionPro = $descriptionPro;
        return $this;
    }

    public function getCommentairePro(): ?string
    {
        return $this->commentairePro;
    }

    public function setCommentairePro(?string $commentairePro): static // Accepte null
    {
        $this->commentairePro = $commentairePro;
        return $this;
    }

    public function getBudgetPro(): ?string
    {
        return $this->budgetPro;
    }

    public function setBudgetPro(string $budgetPro): static
    {
        $this->budgetPro = $budgetPro;
        return $this;
    }

    public function getDateDebutPro(): ?\DateTimeInterface
    {
        return $this->dateDebutPro;
    }

    public function setDateDebutPro(\DateTimeInterface $dateDebutPro): static
    {
        $this->dateDebutPro = $dateDebutPro;
        return $this;
    }

    public function getDateFinPro(): ?\DateTimeInterface
    {
        return $this->dateFinPro;
    }

    public function setDateFinPro(?\DateTimeInterface $dateFinPro): static // Accepte null
    {
        $this->dateFinPro = $dateFinPro;
        return $this;
    }

    public function getavancementPro(): ?int
    {
        return $this->avancementPro;
    }

    public function setavancementPro(?int $avancementPro): static // Accepte null
    {
        $this->avancementPro = $avancementPro === null ? null : max(0, min(100, $avancementPro));
        
        if ($this->avancementPro === null) {
            $this->StatutPro = 'Débuté';
        } elseif ($this->avancementPro === 0) {
            $this->StatutPro = 'Débuté';
        } elseif ($this->avancementPro > 0 && $this->avancementPro < 100) {
            $this->StatutPro = 'En cours';
        } elseif ($this->avancementPro === 100) {
            $this->StatutPro = 'Terminé';
        }
        
        return $this;
    }

    public function getStatutPro(): ?string
    {
        return $this->StatutPro;
    }

    public function setStatutPro(string $StatutPro): static
    {
        $this->StatutPro = $StatutPro;
        return $this;
    }

    public function getCreateur(): ?Personnel
    {
        return $this->createur;
    }

    public function setCreateur(?Personnel $createur): static
    {
        $this->createur = $createur;
        return $this;
    }


    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    /**
     * @return Collection<int, Personnel>
     */
    public function getPersonnels(): Collection
    {
        return $this->personnels;
    }

    public function addPersonnel(Personnel $personnel): static
    {
        if (!$this->personnels->contains($personnel)) {
            $this->personnels->add($personnel);
            $personnel->addProjetsG($this);
        }

        return $this;
    }

    public function removePersonnel(Personnel $personnel): static
    {
        if ($this->personnels->removeElement($personnel)) {
            $personnel->removeProjetsG($this);
        }

        return $this;
    }

    /**
 * Désactive le projet et toutes ses tâches
 */
public function desactiverAvecCascade(): static
{
    $this->StatutPro = 'Suspendu'; // Ou créer un statut 'DESACTIVE' si nécessaire
    
    // Suspendre toutes les tâches du projet
    foreach ($this->taches as $tache) {
        $tache->setStatutTache('Suspendu');
    }
    
    return $this;
}

    /**
     * @return Collection<int, Tache>
     */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function addTache(Tache $tache): static
    {
        if (!$this->taches->contains($tache)) {
            $this->taches->add($tache);
            $tache->setProjet($this);
        }

        return $this;
    }

    public function removeTache(Tache $tache): static
    {
        if ($this->taches->removeElement($tache)) {
            // set the owning side to null (unless already changed)
            if ($tache->getProjet() === $this) {
                $tache->setProjet(null);
            }
        }

        return $this;
    }

    // Méthode pour calculer la durée estimée
    #[Groups(['projet:read'])]
    public function getDureeEstimee(): ?string
    {
        if (!$this->dateDebutPro || !$this->dateFinPro) {
            return null;
        }

        $interval = $this->dateDebutPro->diff($this->dateFinPro);
        $days = $interval->days;

        if ($days >= 14) {
            $weeks = ceil($days / 7);
            return $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
        } else {
            return $days . ' jour' . ($days > 1 ? 's' : '');
        }
    }

    // Méthode pour vérifier la cohérence des dates
    public function isDateRangeValid(): bool
    {
        if (!$this->dateDebutPro || !$this->dateFinPro) {
            return true;
        }

        return $this->dateFinPro > $this->dateDebutPro;
    }


/**
 * Met à jour le statut basé sur les tâches selon les règles spécifiées
 */
public function updateStatutFromTaches(): void
    {
        $taches = $this->taches;
        
        if ($taches->isEmpty()) {
            $this->StatutPro = 'Débuté';
            return;
        }

        $hasActiveTask = false;
        $allTerminated = true;

        /** @var Tache $tache */
        foreach ($taches as $tache) {
            $avancement = $tache->getavancementTache() ?? 0;
            $statutTache = $tache->getStatutTache();
            
            if (($avancement > 0 && $avancement < 100) || $statutTache === 'En cours') {
                $hasActiveTask = true;
                $allTerminated = false;
            }
            
            if ($avancement < 100) {
                $allTerminated = false;
            }
        }

        if ($allTerminated && !$taches->isEmpty()) {
            $this->StatutPro = 'Terminé';
        } elseif ($hasActiveTask) {
            $this->StatutPro = 'En cours';
        } else {
            $this->StatutPro = 'Débuté';
        }
    }

/**
 * Calcule l'avancement automatique basé sur la moyenne des avancements des tâches
 */
public function calculateAvancementAuto(): void
    {
        $taches = $this->taches;
        
        if ($taches->isEmpty()) {
            $this->avancementPro = 0;
            return;
        }

        $totalAvancement = 0;
        $tachesAvecAvancement = 0;

        /** @var Tache $tache */
        foreach ($taches as $tache) {
            $avancement = $tache->getavancementTache() ?? 0;
            $totalAvancement += $avancement;
            $tachesAvecAvancement++;
        }

        if ($tachesAvecAvancement > 0) {
            $this->avancementPro = (int) round($totalAvancement / $tachesAvecAvancement);
        } else {
            $this->avancementPro = 0;
        }
    }

/**
 * Met à jour à la fois l'avancement et le statut
 */
public function updateAvancementAndStatut(): void
{
    $this->calculateAvancementAuto();
    $this->updateStatutFromTaches();
}

    // Alias pour les responsables (personnels)
    public function getResponsables(): Collection
    {
        return $this->personnels;
    }

    public function addResponsable(Personnel $responsable): static
    {
        return $this->addPersonnel($responsable);
    }

    public function removeResponsable(Personnel $responsable): static
    {
        return $this->removePersonnel($responsable);
    }
}