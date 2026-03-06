<?php

namespace App\Message;

class NotificationMessage
{
    public function __construct(
        private string $message,
        private string $type = 'info',
        private ?string $relatedUserId = null
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRelatedUserId(): ?string
    {
        return $this->relatedUserId;
    }
}