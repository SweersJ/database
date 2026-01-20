<?php

namespace Compucie\Database\Settings\Model;

use DateTime;

readonly class Settings
{
    public function __construct(
        private int $id,
        private int $userId,
        private DateTime $lastUpdated,
        private bool $tempStudentNumberLogin,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLastUpdated(): DateTime
    {
        return $this->lastUpdated;
    }

    public function isTempStudentNumberLogin(): bool
    {
        return $this->tempStudentNumberLogin;
    }
}