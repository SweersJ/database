<?php

namespace Compucie\Database\Settings\Model;

readonly class Settings
{
    public function __construct(
        private int $id,
        private bool $tempStudentNumberLogin,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isTempStudentNumberLogin(): bool
    {
        return $this->tempStudentNumberLogin;
    }
}