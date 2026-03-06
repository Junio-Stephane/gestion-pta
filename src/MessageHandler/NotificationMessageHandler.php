<?php

namespace App\MessageHandler;

use App\Message\NotificationMessage;
use App\Entity\Notification;
use App\Entity\Personnel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler]
class NotificationMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private HubInterface $hub
    ) {}

    public function __invoke(NotificationMessage $message)
    {
        // 1️⃣ Créer la notification en base de données
        $notification = new Notification();
        $notification->setMessage($message->getMessage());
        $notification->setType($message->getType());
        $notification->setIsRead(false);

        // Associer l'utilisateur concerné si disponible
        if ($message->getRelatedUserId()) {
            $user = $this->em->getRepository(Personnel::class)
                ->find($message->getRelatedUserId());
            if ($user) {
                $notification->setRelatedUser($user);
            }
        }

        $this->em->persist($notification);
        $this->em->flush();

        // 2️⃣ Publier via Mercure pour les notifications en temps réel
        $this->publishToMercure($notification);
        
        error_log("✅ Notification créée: " . $message->getMessage());
    }

    private function publishToMercure(Notification $notification): void
    {
        $update = new Update(
            '/notifications/admin',
            json_encode([
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'isRead' => $notification->isIsRead(),
                'createdAt' => $notification->getCreatedAt()->format('H:i'),
                'userId' => $notification->getRelatedUser()?->getImPer(),
                'userName' => $notification->getRelatedUser() 
                    ? $notification->getRelatedUser()->getPrenomPer() . ' ' . $notification->getRelatedUser()->getNomPer()
                    : null
            ])
        );

        $this->hub->publish($update);
    }
}