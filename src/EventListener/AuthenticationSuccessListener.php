<?php
// src/EventListener/AuthenticationSuccessListener.php

namespace App\EventListener;

use App\Entity\Personnel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class AuthenticationSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Personnel) {
            return;
        }

        // Redirections selon le statut
        if ($user->getStatutPer() === 'DESACTIVE') {
            $response = new RedirectResponse($this->urlGenerator->generate('app_compte_desactive'));
            $event->setResponse($response);
            return;
        }

        if ($user->getRolePer() === 'ROLE_EN_ATTENTE' || $user->getStatutPer() === 'EN ATTENTE') {
            $response = new RedirectResponse($this->urlGenerator->generate('app_compte_en_attente'));
            $event->setResponse($response);
            return;
        }

        if ($user->getRolePer() === 'ROLE_REJETE' || $user->getStatutPer() === 'REJETE') {
            // Déconnecter l'utilisateur rejeté
            $response = new RedirectResponse($this->urlGenerator->generate('app_compte_refuse'));
            $event->setResponse($response);
            return;
        }
    }
}