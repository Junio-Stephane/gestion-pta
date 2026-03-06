<?php

namespace App\Message;

class NewRegistrationNotification
{
    public function __construct(
        private string $personnelId
    ) {}

    public function getPersonnelId(): string
    {
        return $this->personnelId;
    }
}