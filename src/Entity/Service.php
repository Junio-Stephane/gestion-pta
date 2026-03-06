<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['service:read']],
    denormalizationContext: ['groups' => ['service:write']]
)]

#[UniqueEntity(
    fields: ['nomService'],
    message: 'Ce nom de service existe déjà.',
    entityClass: 'App\Entity\Service'
)]

class Service
{
    #[ORM\Id]
    #[ORM\Column(name: "code_service", length: 20)]
    #[Groups(['service:read', 'service:write', 'projet:read'])]
    private ?string $CodeService = null;

    #[ORM\Column(length: 50)]
    #[Groups(['service:read', 'service:write', 'projet:read'])]
    private ?string $nomService = null;

    #[ORM\Column(length: 20, options: ['default' => 'EN_ATTENTE'])] // Changé de 'ACTIF' à 'EN_ATTENTE'
    #[Groups(['service:read', 'service:write', 'projet:read'])]
    private ?string $statutService = 'EN_ATTENTE';

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'code_direction', referencedColumnName: 'code_direction', nullable: true)]
    #[Groups(['service:read', 'service:write', 'projet:read'])] 
    private ?Direction $direction = null;

    #[ORM\OneToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(name: 'chef_service_id', referencedColumnName: 'im_per', nullable: true)]
    #[Groups(['service:read', 'service:write'])]
    private ?Personnel $chefService = null;

    /**
     * @var Collection<int, Personnel>
     */
    #[ORM\OneToMany(targetEntity: Personnel::class, mappedBy: 'service')]
    private Collection $personnels;

    /**
     * @var Collection<int, Projet>
     */
    #[ORM\OneToMany(targetEntity: Projet::class, mappedBy: 'service')]
    #[Groups(['service:read'])]
    private Collection $projets;

    public function __construct()
    {
        $this->personnels = new ArrayCollection();
        $this->projets = new ArrayCollection();
    }

    public function getCodeService(): ?string
    {
        return $this->CodeService;
    }

    public function setCodeService(string $CodeService): static
    {
        $this->CodeService = $CodeService;
        return $this;
    }

    public function getNomService(): ?string
    {
        return $this->nomService;
    }

    public function setNomService(string $nomService): static
    {
        $this->nomService = $nomService;
        return $this;
    }

    public function getStatutService(): ?string
    {
        return $this->statutService;
    }

    public function setStatutService(string $statutService): static
    {
        $this->statutService = $statutService;
        return $this;
    }

    public function getDirection(): ?Direction
    {
        return $this->direction;
    }

    public function setDirection(?Direction $direction): static
    {
        $this->direction = $direction;
        return $this;
    }

    public function getChefService(): ?Personnel
    {
        return $this->chefService;
    }

    public function setChefService(?Personnel $chefService): static
{
    // Sauvegarder l'ancien chef
    $ancienChef = $this->chefService;
    
    // CAS 1: On enlève le chef actuel
    if ($ancienChef !== null && $chefService === null) {
        // Réinitialiser la fonction de l'ancien chef
        $ancienChef->setFonctionPer($ancienChef->determinerFonction());
        // L'ancien chef reste dans le service mais n'est plus chef
    }
    
    // CAS 2: On assigne un nouveau chef
    if ($chefService !== null) {
        // CAS 2A: On remplace l'ancien chef par un nouveau
        if ($ancienChef !== null && $ancienChef !== $chefService) {
            // Réinitialiser la fonction de l'ancien chef
            $ancienChef->setFonctionPer($ancienChef->determinerFonction());
        }
        
        // CAS 2B: Le nouveau chef rejoint le service
        // Ajouter le chef au service (relation bidirectionnelle)
        if (!$this->personnels->contains($chefService)) {
            $this->addPersonnel($chefService);
        }
        
        // Mettre à jour la fonction du nouveau chef
        $chefService->setFonctionPer('Chef_service');
    }

    $this->chefService = $chefService;
    return $this;
}
     
    public function mettreAJourDirectionChef(): static
{
    if ($this->chefService !== null && $this->direction !== null) {
        // Le chef hérite de la direction de son service
        $this->chefService->setDirectionD($this->direction);
        
        // Mettre à jour la fonction automatiquement
        $this->chefService->setFonctionPer($this->chefService->determinerFonction());
    }
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
            $personnel->setService($this);
        }

        return $this;
    }

    public function removePersonnel(Personnel $personnel): static
    {
        if ($this->personnels->removeElement($personnel)) {
            // set the owning side to null (unless already changed)
            if ($personnel->getService() === $this) {
                $personnel->setService(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, Projet>
     */
    public function getProjets(): Collection
    {
        return $this->projets;
    }

    public function addProjet(Projet $projet): static
    {
        if (!$this->projets->contains($projet)) {
            $this->projets->add($projet);
            $projet->setService($this);
        }

        return $this;
    }

    public function removeProjet(Projet $projet): static
    {
        if ($this->projets->removeElement($projet)) {
            // set the owning side to null (unless already changed)
            if ($projet->getService() === $this) {
                $projet->setService(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function estActif(): bool
    {
        return $this->statutService === 'ACTIF';
    }

    public function estDesactive(): bool
    {
        return $this->statutService === 'DESACTIVE';
    }


    // Dans App\Entity\Service

public function desactiverAvecCascade(): static
{
    $this->statutService = 'DESACTIVE';
    
    // Désactiver tous les personnels du service
    foreach ($this->personnels as $personnel) {
        $personnel->desactiver();
        // Mettre à jour la fonction du personnel
        $personnel->setFonctionPer($personnel->determinerFonction());
    }
    
    // Désactiver le chef de service s'il existe
    if ($this->chefService) {
        $this->chefService->desactiver();
        $this->chefService->setFonctionPer($this->chefService->determinerFonction());
    }
    
    // Désactiver tous les projets du service et leurs tâches
    foreach ($this->projets as $projet) {
        // Ne pas désactiver les projets déjà terminés
        if ($projet->getStatutPro() !== 'Terminé') {
            $projet->desactiverAvecCascade();
        }
    }
    
    return $this;
}

// /**
//  * Active le service et tous ses personnels associés
//  */
// public function activerAvecCascade(): static
// {
//     $this->statutService = 'ACTIF';
    
//     // Réactiver tous les personnels du service
//     foreach ($this->personnels as $personnel) {
//         $personnel->activer();
//         // Mettre à jour la fonction du personnel
//         $personnel->setFonctionPer($personnel->determinerFonction());
//     }
    
//     // Réactiver le chef de service s'il existe
//     if ($this->chefService) {
//         $this->chefService->activer();
//         $this->chefService->setFonctionPer($this->chefService->determinerFonction());
//     }
    
//     return $this;
// }

    public function estEnAttente(): bool
    {
        return $this->statutService === 'EN_ATTENTE';
    }

    public function activer(): static
    {
        $this->statutService = 'ACTIF';
        return $this;
    }

    public function desactiver(): static
    {
        $this->statutService = 'DESACTIVE';
        return $this;
    }

    public function mettreEnAttente(): static
    {
        $this->statutService = 'EN_ATTENTE';
        return $this;
    }
}