<?php
// src/EventListener/ResponseListener.php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Bundle\SecurityBundle\Security;

class ResponseListener
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Appliquer no-cache pour TOUTES les réponses
        $this->setNoCacheHeaders($response);

        // Headers supplémentaires de sécurité
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function setNoCacheHeaders($response): void
    {
        $response->headers->add([
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT',
            'X-Accel-Expires' => '0',
        ]);
    }
}