<?php
// src/Entity/Notification.php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
// #[ApiResource(
//     operations: [
//         new GetCollection(
//             normalizationContext: ['groups' => ['notification:read']],
//             security: "is_granted('ROLE_ADMIN')"
//         ),
//         new Get(
//             normalizationContext: ['groups' => ['notification:read']],
//             security: "is_granted('ROLE_ADMIN')"
//         ),
//         new Post(security: "is_granted('ROLE_ADMIN')")
//     ]
// )]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    #[Groups(['notification:read'])]
    private ?string $type = 'info'; // info, warning, success, danger

    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(name: 'im_per', referencedColumnName: 'im_per', nullable: true)]
    #[Groups(['notification:read'])]
    private ?Personnel $relatedUser = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isIsRead(): ?bool
    {
        return $this->isRead;
    }
    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRelatedUser(): ?Personnel
    {
        return $this->relatedUser;
    }
    public function setRelatedUser(?Personnel $relatedUser): static
    {
        $this->relatedUser = $relatedUser;
        return $this;
    }
}
