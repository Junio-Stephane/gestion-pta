<?php
// src/Controller/Api/NotificationApiController.php

namespace App\Controller\Api;

use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class NotificationApiController extends AbstractController
{
    #[Route('/notifications', name: 'api_notifications', methods: ['GET'])]
    public function getNotifications(NotificationService $notificationService): JsonResponse
    {
        // Vérifier si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        $notifications = $notificationService->getUnreadNotifications();
        
        $data = [];
        foreach ($notifications as $notification) {
            $relatedUser = $notification->getRelatedUser();
            $data[] = [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i'),
                'relatedUserId' => $relatedUser?->getImPer(),
                'relatedUserName' => $relatedUser ? $relatedUser->getPrenomPer() . ' ' . $relatedUser->getNomPer() : null
            ];
        }

        return $this->json($data);
    }

    #[Route('/notifications/count', name: 'api_notifications_count', methods: ['GET'])]
    public function getNotificationsCount(NotificationService $notificationService): JsonResponse
    {
        // Vérifier si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['count' => 0], 200); // Retourner 0 pour les non-admins
        }

        $count = $notificationService->getUnreadNotificationsCount();

        return $this->json(['count' => $count]);
    }

    #[Route('/notifications/{id}/read', name: 'api_notification_read', methods: ['POST'])]
    public function markAsRead(int $id, NotificationService $notificationService): JsonResponse
    {
        // Vérifier si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }
        
        $notificationService->markAsRead($id);
        return $this->json(['success' => true]);
    }

    #[Route('/notifications/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllAsRead(NotificationService $notificationService): JsonResponse
    {
        // Vérifier si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }
        
        $notificationService->markAllAsRead();
        return $this->json(['success' => true]);
    }

    #[Route('/notifications/latest', name: 'api_notifications_latest', methods: ['GET'])]
    public function getLatestNotifications(NotificationService $notificationService): JsonResponse
    {
        // Vérifier si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([], 200); // Retourner un tableau vide pour les non-admins
        }

        $notifications = $notificationService->getLatestUnreadNotifications(5);
        
        $data = [];
        foreach ($notifications as $notification) {
            $relatedUser = $notification->getRelatedUser();
            $data[] = [
                'id' => $notification->getId(),
                'title' => 'Nouvelle demande d\'inscription',
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'timeAgo' => $this->formatTimeAgo($notification->getCreatedAt()),
                'relatedUserId' => $relatedUser?->getImPer(),
                'relatedUserName' => $relatedUser ? $relatedUser->getPrenomPer() . ' ' . $relatedUser->getNomPer() : null
            ];
        }

        return $this->json($data);
    }

    private function formatTimeAgo(\DateTimeInterface $createdAt): string
{
    date_default_timezone_set('Indian/Antananarivo');
    
    // Créer un objet DateTime mutable à partir de l'interface
    $notificationDate = \DateTime::createFromFormat('Y-m-d H:i:s', $createdAt->format('Y-m-d H:i:s'));
    $notificationDate->setTimezone(new \DateTimeZone('Indian/Antananarivo'));
    
    $now = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    
    $interval = $now->diff($notificationDate);
    
    if ($interval->y > 0) {
        return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    }
    if ($interval->m > 0) {
        return $interval->m . ' mois';
    }
    if ($interval->d > 0) {
        $days = $interval->d;
        if ($interval->h >= 12) $days++;
        return $days . ' jour' . ($days > 1 ? 's' : '');
    }
    if ($interval->h > 0) {
        return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
    }
    if ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }
    return 'Quelques secondes';
}
}