<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Personnel;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Crée une nouvelle notification
     */
    public function createNotification(string $message, string $type = 'info', ?Personnel $relatedUser = null): Notification
    {
        $notification = new Notification();
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setRelatedUser($relatedUser);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * Notifie une nouvelle inscription
     */
    public function notifyNewRegistration(Personnel $user): void
    {
        $message = sprintf(
            "Nouvelle demande d'inscription de %s %s (%s)",
            $user->getPrenomPer(),
            $user->getNomPer(),
            $user->getImPer()
        );

        $this->createNotification($message, 'warning', $user);
    }

    /**
     * Récupère toutes les notifications non lues
     */
    public function getUnreadNotifications(): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['isRead' => false], ['createdAt' => 'DESC']);
    }

    /**
     * Récupère les dernières notifications non lues (pour les notifications globales)
     */
    public function getLatestUnreadNotifications(int $limit = 5): array
    {
        return $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->where('n.isRead = false')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues
     */
    public function getUnreadNotificationsCount(): int
    {
        return $this->em->getRepository(Notification::class)
            ->count(['isRead' => false]);
    }

    /**
     * Marque une notification spécifique comme lue
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = $this->em->getRepository(Notification::class)->find($notificationId);
        if ($notification) {
            $notification->setIsRead(true);
            $this->em->flush();
        }
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead(): void
    {
        $notifications = $this->getUnreadNotifications();
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markUserNotificationsAsRead(Personnel $user): void
    {
        try {
            // Trouver toutes les notifications non lues liées à cet utilisateur
            $notifications = $this->em->getRepository(Notification::class)
                ->findBy([
                    'relatedUser' => $user,
                    'isRead' => false
                ]);

            foreach ($notifications as $notification) {
                $notification->setIsRead(true);
            }

            if (count($notifications) > 0) {
                $this->em->flush();
            }
        } catch (\Exception $e) {
            // Log silencieux - on ne veut pas bloquer l'approbation/rejet
            error_log("NotificationService: Erreur marquage notifications utilisateur " . $user->getImPer() . " - " . $e->getMessage());
        }
    }

    /**
     * Marque la notification la plus récente d'un utilisateur comme lue
     */
    public function markNotificationAsReadByUser(Personnel $user): bool
    {
        try {
            // Trouver la notification la plus récente pour cet utilisateur
            $notification = $this->em->getRepository(Notification::class)
                ->findOneBy([
                    'relatedUser' => $user,
                    'isRead' => false
                ], ['createdAt' => 'DESC']);

            if ($notification) {
                $notification->setIsRead(true);
                $this->em->flush();
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("NotificationService: Erreur marquage notification utilisateur " . $user->getImPer() . " - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les notifications d'un utilisateur spécifique
     */
    public function getUserNotifications(Personnel $user): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['relatedUser' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Récupère les notifications non lues d'un utilisateur spécifique
     */
    public function getUnreadUserNotifications(Personnel $user): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy([
                'relatedUser' => $user,
                'isRead' => false
            ], ['createdAt' => 'DESC']);
    }

    /**
     * Supprime les notifications anciennes (optionnel - pour le ménage)
     */
    public function cleanupOldNotifications(int $days = 30): int
    {
        $dateLimit = new \DateTime("-$days days");
        
        $deletedCount = $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :dateLimit')
            ->andWhere('n.isRead = true')
            ->setParameter('dateLimit', $dateLimit)
            ->getQuery()
            ->execute();

        return $deletedCount;
    }

    /**
     * Récupère les notifications avec pagination (pour future évolution)
     */
    public function getNotificationsPaginated(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        return $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}