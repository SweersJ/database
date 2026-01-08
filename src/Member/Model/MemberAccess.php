<?php

namespace Compucie\Database\Member\Model;

readonly class MemberAccess
{
    /**
     * @param int $congressusMemberId
     * @param string $reason
     * @param bool $hasAccess
     */
    public function __construct(
        private int $congressusMemberId,
        private string $reason,
        private bool $hasAccess
    ) {
    }

    public function getCongressusMemberId(): int
    {
        return $this->congressusMemberId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getHasAccess(): bool
    {
        return $this->hasAccess;
    }
}