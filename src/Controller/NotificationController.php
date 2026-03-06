<?php

namespace App\Controller;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/admin/notifications', name: 'admin_notifications')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $notifications = $em->getRepository(Notification::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $unreadCount = $em->getRepository(Notification::class)
            ->count(['isRead' => false]);

        // Si c'est une requête AJAX pour le panel
        if ($request->query->get('partial')) {
            return $this->render('notification/_notification_list.html.twig', [
                'notifications' => $notifications
            ]);
        }

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    #[Route('/admin/notifications/mark-read/{id}', name: 'admin_notification_mark_read')]
    public function markAsRead(int $id, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $notification = $em->getRepository(Notification::class)->find($id);
        
        if ($notification) {
            $notification->setIsRead(true);
            $em->flush();
            $this->addFlash('success', 'Notification marquée comme lue');
        }

        return $this->redirectToRoute('admin_notifications');
    }

    #[Route('/admin/notifications/mark-all-read', name: 'admin_notifications_mark_all_read')]
    public function markAllAsRead(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $notifications = $em->getRepository(Notification::class)
            ->findBy(['isRead' => false]);
            
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        
        $em->flush();
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues');

        return $this->redirectToRoute('admin_notifications');
    }

    #[Route('/admin/notifications/delete/{id}', name: 'admin_notification_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $notification = $em->getRepository(Notification::class)->find($id);
        
        if ($notification) {
            $em->remove($notification);
            $em->flush();
            $this->addFlash('success', 'Notification supprimée');
        }

        return $this->redirectToRoute('admin_notifications');
    }
}