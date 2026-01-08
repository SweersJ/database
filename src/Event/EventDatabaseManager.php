<?php

namespace Compucie\Database\Event;

use Exception;
use Compucie\Database\DatabaseManager;
use Compucie\Database\Event\Model\PinnedEvent;
use DateTime;
use InvalidArgumentException;
use mysqli_sql_exception;
use function Compucie\Database\safeDateTime;

class EventDatabaseManager extends DatabaseManager
{
    public function createTables(): void
    {
        $statement = $this->getClient()->prepare(
            "CREATE TABLE IF NOT EXISTS `pins` (
                `pin_id` INT NOT NULL AUTO_INCREMENT UNIQUE,
                `event_id` INT NOT NULL,
                `start_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `end_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (pin_id)
            );"
        );
        if ($statement) {
            $statement->execute();
            $statement->close();
        }
    }

    /**
     * Return array of IDs of currently pinned events.
     * @return  int[]   $eventIds
     * @throws  mysqli_sql_exception
     */
    public function getCurrentlyPinnedEventIds(): array
    {
        $rows = $this->executeReadAll(
            "SELECT `event_id`
         FROM `pins`
         WHERE (NOW() BETWEEN `start_at` AND `end_at`)
            OR (`start_at` <= NOW() AND `end_at` IS NULL)"
        );

        return array_map(
            static fn(array $row): int => (int)$row['event_id'],
            $rows
        );
    }

    /**
     * @return array<int>
     * @throws mysqli_sql_exception
     */
    public function getPinnedEventIds(): array
    {
        $rows = $this->executeReadAll(
            "SELECT `event_id`
            FROM `pins`"
        );

        return array_map(
            static fn(array $row): int => (int)$row['event_id'],
            $rows
        );
    }

    /**
     * @return array<PinnedEvent>
     * @throws mysqli_sql_exception
     */
    public function getPinnedEvents(): array
    {
        $rows = $this->executeReadAll(
            "SELECT `pin_id`, `event_id`, `start_at`, `end_at`
            FROM `pins`"
        );

        $pinnedEvents = [];

        foreach ($rows as $row) {
            $pinnedEvents[] = new PinnedEvent(
                (int) $row['pin_id'],
                (int) $row['event_id'],
                safeDateTime((string)$row['start_at']),
                safeDateTime((string)$row['end_at'])
            );
        }
        return $pinnedEvents;
    }

    /**
     * @param int $pinId
     * @return PinnedEvent|null
     * @throws Exception
     * @throws mysqli_sql_exception
     */
    public function getPinnedEvent(int $pinId): ?PinnedEvent
    {
        if ($pinId <= 0){
            return null;
        }

        $row = $this->executeReadOne(
            "SELECT `pin_id`, `event_id`, `start_at`, `end_at` 
            FROM `pins` 
            WHERE `pin_id` = ?",
            [$pinId],
            "i"
        );

        if ($row === null){
            throw new Exception("Pinned event $pinId not found");
        }

        return new PinnedEvent(
            $pinId,
            (int) $row['event_id'],
            safeDateTime((string)$row['start_at']),
            safeDateTime((string)$row['end_at'])
        );
    }

    /**
     * Insert an event pin.
     * @param int $eventId
     * @param DateTime|null $startAt
     * @param DateTime|null $endAt
     * @return int
     * @throws  mysqli_sql_exception
     * @throws InvalidArgumentException
     */
    public function insertPin(int $eventId, ?DateTime $startAt = null, ?DateTime $endAt = null): int
    {
        if ($endAt !== null && $endAt < ($startAt ?? new DateTime())) {
            throw new InvalidArgumentException('endAt must be after startAt');
        }

        $start = ($startAt ?? new DateTime())
            ->format(self::SQL_DATETIME_FORMAT);

        $end = $endAt?->format(self::SQL_DATETIME_FORMAT);

        return $this->executeCreate(
            'pins',
            ['`event_id`', '`start_at`', '`end_at`'],
            [$eventId, $start, $end],
            'iss'
        );
    }

    /**
     * Update an event pin.
     * @throws InvalidArgumentException
     * @throws  mysqli_sql_exception
     */
    public function updatePin(int $eventId, ?DateTime $startAt = null, ?DateTime $endAt = null): bool
    {
        if ($endAt !== null && $endAt < ($startAt ?? new DateTime())) {
            throw new InvalidArgumentException('endAt must be after startAt');
        }

        $start = ($startAt ?? new DateTime())
            ->format(self::SQL_DATETIME_FORMAT);

        $end = $endAt?->format(self::SQL_DATETIME_FORMAT);

        return $this->executeUpdate(
            'pins',
            'event_id',
            $eventId,
            [
                '`start_at` = ?',
                '`end_at` = ?',
            ],
            [$start, $end],
            'ss'
        );
    }

    /**
     * @param int $pinId
     * @return bool
     * @throws  mysqli_sql_exception
     */
    public function deletePin(int $pinId): bool
    {
        return $this->executeDelete('pins', 'pin_id', $pinId);
    }
}
