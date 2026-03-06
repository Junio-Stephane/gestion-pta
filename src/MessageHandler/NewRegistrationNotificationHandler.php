<?php

namespace App\MessageHandler;

use App\Message\NewRegistrationNotification;
use App\Entity\Notification;
use App\Entity\Personnel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler]
class NewRegistrationNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private HubInterface $hub
    ) {}

    public function __invoke(NewRegistrationNotification $message)
    {
        // 1️⃣ Charger l'utilisateur concerné
        $user = $this->em->getRepository(Personnel::class)->find($message->getPersonnelId());
        if (!$user) {
            error_log("❌ Utilisateur non trouvé: " . $message->getPersonnelId());
            return;
        }

        // 2️⃣ Créer une Notification en base
        $notification = new Notification();
        $notification->setMessage(sprintf(
            "Nouvelle inscription : %s %s (IM %s)",
            $user->getPrenomPer(),
            $user->getNomPer(),
            $user->getImPer()
        ));
        $notification->setType('warning');
        $notification->setIsRead(false);
        $notification->setRelatedUser($user);

        $this->em->persist($notification);
        $this->em->flush();

        // 3️⃣ Publier l'événement temps réel via Mercure
        $update = new Update(
            '/notifications/admin',
            json_encode([
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'isRead' => $notification->isIsRead(),
                'createdAt' => $notification->getCreatedAt()->format('H:i'),
                'userId' => $user->getImPer(),
                'userName' => $user->getPrenomPer() . ' ' . $user->getNomPer()
            ])
        );

        try {
            $this->hub->publish($update);
            error_log("✅ Notification créée et publiée via Mercure pour l'utilisateur: " . $user->getImPer());
        } catch (\Exception $e) {
            error_log("❌ Erreur Mercure: " . $e->getMessage());
        }
    }
}