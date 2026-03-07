<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\DirectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: DirectionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['direction:read']],
    denormalizationContext: ['groups' => ['direction:write']]
)]

#[UniqueEntity(
    fields: ['nomDirection'],
    message: 'Ce nom de direction existe déjà.',
    entityClass: 'App\Entity\Direction'
)]

class Direction
{
    #[ORM\Id]
    #[ORM\Column(name: "code_direction", length: 20)]
    #[Groups(['direction:read', 'direction:write', 'projet:read'])]
    private ?string $CodeDirection = null;

    #[ORM\Column(length: 50)]
    #[Groups(['direction:read', 'direction:write', 'projet:read'])]
    private ?string $nomDirection = null;

    #[ORM\OneToOne(mappedBy: 'directionD', cascade: ['persist', 'remove'])]
    #[Groups(['direction:read', 'personnel:read'])]
    private ?Personnel $personnel = null;

    #[ORM\Column(length: 20, options: ['default' => 'ACTIVE'])]
    #[Groups(['direction:read', 'direction:write', 'projet:read'])]
    private ?string $statutDirection = 'ACTIVE';

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'direction')]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
    }

    public function getCodeDirection(): ?string
    {
        return $this->CodeDirection;
    }

    public function setCodeDirection(string $CodeDirection): static
    {
        $this->CodeDirection = $CodeDirection;
        return $this;
    }

    public function getNomDirection(): ?string
    {
        return $this->nomDirection;
    }

    public function setNomDirection(string $nomDirection): static
    {
        $this->nomDirection = $nomDirection;
        return $this;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        if ($personnel === null && $this->personnel !== null) {
            $this->personnel->setDirectionD(null);
        }

        if ($personnel !== null && $personnel->getDirectionD() !== $this) {
            $personnel->setDirectionD($this);
        }

        $this->personnel = $personnel;

        return $this;
    }

    public function getStatutDirection(): ?string
    {
        return $this->statutDirection;
    }

    public function setStatutDirection(string $statutDirection): static
    {
        $this->statutDirection = $statutDirection;
        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setDirection($this);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            if ($service->getDirection() === $this) {
                $service->setDirection(null);
            }
        }

        return $this;
    }

    public function estEnAttente(): bool
    {
        return $this->statutDirection === 'EN_ATTENTE';
    }

    public function estActive(): bool
    {
        return $this->statutDirection === 'ACTIVE';
    }

    public function estDesactivee(): bool
    {
        return $this->statutDirection === 'DESACTIVEE';
    }

    public function activer(): static
    {
        $this->statutDirection = 'ACTIVE';
        return $this;
    }

    public function desactiver(): static
    {
        $this->statutDirection = 'DESACTIVEE';
        return $this;
    }

    public function desactiverAvecCascade(): static
    {
        $this->statutDirection = 'DESACTIVEE';

        // Désactiver tous les services de cette direction
        foreach ($this->services as $service) {
            $service->desactiverAvecCascade();
        }

        // Désactiver le directeur s'il existe
        if ($this->personnel) {
            $this->personnel->desactiver();
            $this->personnel->setFonctionPer($this->personnel->determinerFonction());
        }

        return $this;
    }


    public function __toString(): string
    {
        return $this->nomDirection ?? '';
    }
}
