<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\PersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['personnel:read']],
    denormalizationContext: ['groups' => ['personnel:write']]
)]
class Personnel implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(name: "im_per", length: 6)]
    #[Assert\NotBlank(message: "L'immatricule est obligatoire")]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: "L'immatricule doit contenir exactement 6 chiffres")]
    #[Groups(['personnel:read', 'personnel:write', 'direction:read', 'projet:read'])]
    private ?string $ImPer = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Groups(['personnel:read', 'personnel:write', 'direction:read', 'projet:read'])]
    private ?string $NomPer = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['personnel:read', 'personnel:write', 'direction:read', 'projet:read'])]
    private ?string $PrenomPer = null;

    #[ORM\Column(length: 55, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'adresse email n'est pas valide")]
    #[Groups(['personnel:read', 'personnel:write', 'direction:read', 'projet:read'])]
    private ?string $EmailPer = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['personnel:read', 'personnel:write'])]
    private ?string $TelPer = null;

    #[ORM\Column(length: 255)]
    #[Groups(['personnel:write'])]
    private ?string $MdpPer = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(length: 30)]
    #[Groups(['personnel:read', 'personnel:write'])]
    private ?string $RolePer = 'ROLE_EN_ATTENTE';

    #[ORM\Column(length: 30)]
    #[Groups(['personnel:read', 'personnel:write'])]
    private ?string $StatutPer = 'EN_ATTENTE'; // EN_ATTENTE | ACTIF | REJETE | DESACTIVE

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['personnel:read', 'personnel:write', 'direction:read'])]
    private ?string $FonctionPer = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isValidated = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['personnel:read'])]
    private ?\DateTimeImmutable $Date_creationPer = null;

    #[ORM\OneToOne(inversedBy: 'personnel', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'code_direction', referencedColumnName: 'code_direction', nullable: true)]
    #[Groups(['personnel:read'])]
    private ?Direction $directionD = null;

    private ?string $timeAgo = null;

    #[ORM\ManyToOne(inversedBy: 'personnels')]
    #[ORM\JoinColumn(name: 'code_service', referencedColumnName: 'code_service', nullable: true)]
    private ?Service $service = null;

    #[ORM\ManyToMany(targetEntity: Projet::class, inversedBy: 'personnels')]
    #[ORM\JoinTable(name: 'personnel_projet',
        joinColumns: [new ORM\JoinColumn(name: 'im_per', referencedColumnName: 'im_per')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'num_projet', referencedColumnName: 'num_projet')]
    )]
    private Collection $projetsG;

    
    /**
     * @var Collection<int, Projet>
     */
    #[ORM\OneToMany(targetEntity: Projet::class, mappedBy: 'createur')]
    private Collection $projetsCrees;

    public function __construct()
    {
        $this->Date_creationPer = new \DateTimeImmutable();
        $this->projetsG = new ArrayCollection();
        $this->projetsCrees = new ArrayCollection(); // AJOUT
    }



    // GETTERS/SETTERS
    public function getImPer(): ?string { return $this->ImPer; }
    public function setImPer(string $ImPer): static { $this->ImPer = $ImPer; return $this; }

    public function getNomPer(): ?string { return $this->NomPer; }
    public function setNomPer(string $NomPer): static { $this->NomPer = $NomPer; return $this; }

    public function getPrenomPer(): ?string { return $this->PrenomPer; }
    public function setPrenomPer(?string $PrenomPer): static { $this->PrenomPer = $PrenomPer; return $this; }

    public function getEmailPer(): ?string { return $this->EmailPer; }
    public function setEmailPer(string $EmailPer): static { $this->EmailPer = $EmailPer; return $this; }

    public function getTelPer(): ?string { return $this->TelPer; }
    public function setTelPer(?string $TelPer): static { $this->TelPer = $TelPer; return $this; }

    public function getMdpPer(): ?string { return $this->MdpPer; }
    public function setMdpPer(string $MdpPer): static { $this->MdpPer = $MdpPer; return $this; }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function isResetTokenExpired(): bool
    {
        if (!$this->resetTokenExpiresAt) {
            return true;
        }
        return $this->resetTokenExpiresAt < new \DateTimeImmutable();
    }

    public function getRolePer(): ?string { return $this->RolePer; }
    public function setRolePer(string $RolePer): static { $this->RolePer = $RolePer; return $this; }

    public function getStatutPer(): ?string { return $this->StatutPer; }
    public function setStatutPer(string $StatutPer): static { $this->StatutPer = $StatutPer; return $this; }

     public function getTimeAgo(): ?string
    {
        return $this->timeAgo;
    }

    public function setTimeAgo(string $timeAgo): static
    {
        $this->timeAgo = $timeAgo;

        return $this;
    }

    public function getFonctionPer(): ?string
    {
        // Si la fonction n'est pas définie en base, on la détermine automatiquement
        if ($this->FonctionPer === null) {
            return $this->determinerFonction();
        }
        
        return $this->FonctionPer;
    }

    public function setFonctionPer(?string $FonctionPer): static
    {
        $this->FonctionPer = $FonctionPer;
        return $this;
    }

    public function isValidated(): bool { return $this->isValidated; }
    public function setIsValidated(bool $isValidated): static { $this->isValidated = $isValidated; return $this; }

    public function getDateCreationPer(): ?\DateTimeImmutable { return $this->Date_creationPer; }

    // MÉTHODES UTILITAIRES
    public function estDesactive(): bool
    {
        return $this->StatutPer === 'DESACTIVE';
    }

    public function estActif(): bool
    {
        return $this->StatutPer === 'ACTIF';
    }

    public function desactiver(): static
    {
        $this->StatutPer = 'DESACTIVE';
        return $this;
    }

    public function activer(): static
    {
        $this->StatutPer = 'ACTIF';
        return $this;
    }

    public function approve(): static
    {
        $this->StatutPer = 'ACTIF';
        $this->isValidated = true;        
        return $this;
    }

    public function reject(): static
    {
        $this->StatutPer = 'REJETE';
        $this->isValidated = false;
        return $this;
    }

    /**
 * Détermine automatiquement la fonction du personnel basée sur ses relations
 */
public function determinerFonction(): string
{
    // Priorité 1: Si c'est directeur d'une direction
    if ($this->directionD !== null) {
        // Vérifier si c'est bien le directeur de cette direction
        if ($this->directionD->getPersonnel() === $this) {
            return 'Directeur';
        }
    }
    
    // Priorité 2: Si c'est le chef de son service actuel
    if ($this->service && $this->service->getChefService() === $this) {
        return 'Chef_service';
    }
    
    // Priorité 3: Si c'est dans un service mais pas chef
    if ($this->service !== null) {
        return 'Agent';
    }
    
    return 'Agent'; // Par défaut
}

    /**
     * Vérifie si le personnel est directeur
     */
    public function estDirecteur(): bool
    {
        return $this->getFonctionPer() === 'Directeur';
    }

    /**
     * Vérifie si le personnel est chef de service
     */
    public function estChefService(): bool
    {
        return $this->getFonctionPer() === 'Chef_service';
    }

    /**
     * Vérifie si le personnel est agent
     */
    public function estAgent(): bool
    {
        return $this->getFonctionPer() === 'Agent';
    }

    // RELATIONS
    public function getDirectionD(): ?Direction { return $this->directionD; }
    public function setDirectionD(?Direction $directionD): static { $this->directionD = $directionD; return $this; }

    public function getService(): ?Service { return $this->service; }
    public function setService(?Service $service): static { $this->service = $service; return $this; }

    public function getProjetsG(): Collection { return $this->projetsG; }
    public function addProjetsG(Projet $projetsG): static {
        if (!$this->projetsG->contains($projetsG)) {
            $this->projetsG->add($projetsG);
        }
        return $this;
    }
    public function removeProjetsG(Projet $projetsG): static {
        $this->projetsG->removeElement($projetsG);
        return $this;
    }

    // UserInterface
    public function getRoles(): array {
    $roles = [$this->RolePer];
    
    // Garantir que ROLE_USER est toujours présent pour tous les utilisateurs authentifiés
    if (!in_array('ROLE_USER', $roles)) {
        $roles[] = 'ROLE_USER';
    }
    
    return array_unique($roles);
}

    public function getPassword(): string { return $this->MdpPer; }
    public function getSalt(): ?string { return null; }
    public function eraseCredentials(): void { }
    public function getUserIdentifier(): string { return $this->EmailPer; }
    public function getUsername(): string { return $this->getUserIdentifier(); }

    // VÉRIFICATIONS
    public function hasRole(string $role): bool { return in_array($role, $this->getRoles(), true); }
    public function isAdmin(): bool { return $this->hasRole('ROLE_ADMIN'); }
    public function isApproved(): bool { return $this->StatutPer === 'ACTIF' && $this->isValidated; }
    public function isPending(): bool { return $this->StatutPer === 'EN_ATTENTE'; }

    public function __toString(): string {
        $fonction = $this->getFonctionPer();
        return sprintf('%s %s (%s) - %s', 
            $this->PrenomPer, 
            $this->NomPer, 
            $this->EmailPer,
            ucfirst($fonction)
        );
    }

    // AJOUT: Getters et setters pour projetsCrees
public function getProjetsCrees(): Collection
{
    return $this->projetsCrees;
}

public function addProjetsCree(Projet $projetsCree): static
{
    if (!$this->projetsCrees->contains($projetsCree)) {
        $this->projetsCrees->add($projetsCree);
        $projetsCree->setCreateur($this);
    }
    return $this;
}

public function removeProjetsCree(Projet $projetsCree): static
{
    if ($this->projetsCrees->removeElement($projetsCree)) {
        if ($projetsCree->getCreateur() === $this) {
            $projetsCree->setCreateur(null);
        }
    }
    return $this;
}
}