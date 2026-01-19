<?php

namespace Compucie\Database\Member\Model;

use DateTime;

readonly class Rfid
{
    public function __construct(
        private string $cardId,
        private int $congressusMemberId,
        private string $hashedActivationToken,
        private string $activationTokenValidUntil,
        private bool $isEmailConfirmed,
        private DateTime $lastUsedAt,
    ) {}

    public function getCardId(): string
    {
        return $this->cardId;
    }

    public function getCongressusMemberId(): int
    {
        return $this->congressusMemberId;
    }

    public function getHashedActivationToken(): string
    {
        return $this->hashedActivationToken;
    }

    public function getActivationTokenValidUntil(): string
    {
        return $this->activationTokenValidUntil;
    }

    public function isEmailConfirmed(): bool
    {
        return $this->isEmailConfirmed;
    }

    public function getLastUsedAt(): DateTime
    {
        return $this->lastUsedAt;
    }
}