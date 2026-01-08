<?php

namespace Compucie\Database\Event\Model;

use DateTime;

readonly class PinnedEvent
{
    /**
     * @param int $id
     * @param int $eventId
     * @param DateTime $startAt
     * @param DateTime $endAt
     */
    public function __construct(
        private int      $id,
        private int      $eventId,
        private DateTime $startAt,
        private DateTime $endAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function getStartAt(): DateTime
    {
        return $this->startAt;
    }

    public function getEndAt(): DateTime
    {
        return $this->endAt;
    }
}