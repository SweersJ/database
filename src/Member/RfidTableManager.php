<?php

namespace Compucie\Database\Member;

use Compucie\Database\Member\Exceptions\ActivationTokenNotFoundException;
use Compucie\Database\Member\Exceptions\CardNotRegisteredException;
use Compucie\Database\Member\Model\MemberAccess;
use DateTime;
use Exception;
use mysqli;
use mysqli_sql_exception;

trait RfidTableManager
{
    protected abstract function getClient(): mysqli;

    protected function createRfidTable(): void
    {
        $statement = $this->getClient()->prepare(
            "CREATE TABLE IF NOT EXISTS `rfid` (
                `card_id` VARCHAR(14) NOT NULL,
                `congressus_member_id` INT NOT NULL,
                `hashed_activation_token` VARCHAR(255) DEFAULT NULL,
                `activation_token_valid_until` DATETIME DEFAULT NULL,
                `is_email_confirmed` BOOLEAN NOT NULL DEFAULT FALSE,
                `last_used_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`card_id`)
            );"
        );
        if ($statement){
            $statement->execute();
            $statement->close();
        }

        $statement = $this->getClient()->prepare(
            "CREATE TABLE IF NOT EXISTS `access` (
                `congressus_member_id` INT NOT NULL,
                `reason` VARCHAR(255) DEFAULT NULL,
                `has_access` BOOLEAN NOT NULL DEFAULT FALSE,
                PRIMARY KEY (`congressus_member_id`)
            );"
        );
        if ($statement){
            $statement->execute();
            $statement->close();
        }
    }

    /**
     * Return the Congressus member ID of the member who registered the given card.
     * @param   string      $cardId     ID of the registered card
     * @return  int                     Congressus member ID
     * @throws  mysqli_sql_exception
     * @throws  CardNotRegisteredException
     */
    public function getCongressusMemberIdFromCardId(string $cardId): int
    {
        $row = $this->executeReadOne(
            "SELECT `congressus_member_id`
         FROM `rfid`
         WHERE `card_id` = ?",
            [$cardId],
            "s"
        );

        if ($row === null || (int)$row['congressus_member_id'] === 0) {
            throw new CardNotRegisteredException();
        }

        return (int)$row['congressus_member_id'];
    }

    /**
     * Return whether the given card is activated.
     * @param   string      $cardId     ID of the card
     * @return  bool                    Whether the card is activated
     * @throws  mysqli_sql_exception
     */
    public function isRfidCardActivated(string $cardId): bool
    {
        return $this->executeReadOne(
            "SELECT 1
            FROM `rfid`
            WHERE `card_id` = ?
                AND `is_email_confirmed` = TRUE
            LIMIT 1",
            [$cardId],
            "s"
        ) !== null;
    }


    /**
     * Get hashed activation token info for a given card ID.
     * @param string $cardId ID of the card
     * @return array{hashed_activation_token: string, activation_token_valid_until: DateTime}
     *      Array with 'hashed_activation_token' and 'activation_token_valid_until' or null if not found
     * @throws  mysqli_sql_exception
     * @throws  ActivationTokenNotFoundException
     * @throws Exception
     */
    public function getActivationTokenInfo(string $cardId): array
    {
        $row = $this->executeReadOne(
            "SELECT `hashed_activation_token`, `activation_token_valid_until`
            FROM `rfid`
            WHERE `card_id` = ?",
            [$cardId],
            "s"
        );

        if (
            $row === null ||
            $row['hashed_activation_token'] === null ||
            $row['activation_token_valid_until'] === null
        ) {
            throw new ActivationTokenNotFoundException();
        }

        return [
            'hashed_activation_token' => (string)$row['hashed_activation_token'],
            'activation_token_valid_until' => new DateTime(
                (string)$row['activation_token_valid_until']
            ),
        ];
    }

    /**
     * Register a card by inserting the card ID and associated member ID.
     * @param   string      $cardId                 ID of the card
     * @param   int         $congressusMemberId     Congressus member ID
     * @param   string      $hashedActivationToken  Hashed activation token for email confirmation
     * @param   DateTime    $activationTokenValidUntil   Expiration date of the activation token
     * @param   bool        $isEmailConfirmed       Whether the member confirmed their registration
     * @throws  mysqli_sql_exception
     */
    public function insertRfid(string $cardId, int $congressusMemberId, string $hashedActivationToken, DateTime $activationTokenValidUntil, bool $isEmailConfirmed = false): bool
    {
        return $this->executeCreate(
                'rfid',
                ['`card_id`','`congressus_member_id`','`hashed_activation_token`','`activation_token_valid_until`','`is_email_confirmed`'],
                [$cardId, $congressusMemberId, $hashedActivationToken, $activationTokenValidUntil->format(self::SQL_DATETIME_FORMAT), (int)$isEmailConfirmed],
                'sissi'
            ) !== -1;
    }

    /**
     * Activate a card by setting is_email_confirmed to TRUE and clearing activation token fields.
     * @param   string      $cardId     ID of the card to activate
     * @throws  mysqli_sql_exception
     */
    public function activateCard(string $cardId): bool
    {
        return $this->executeUpdate(
            'rfid',
            'card_id',
            $cardId,
            ['`is_email_confirmed` = TRUE','`hashed_activation_token` = NULL','`activation_token_valid_until` = NULL'],
            idType: "s"
        );
    }

    /**
     * Update the last used timestamp of a card to the current time.
     * @param   string      $cardId     ID of the card to update
     * @throws  mysqli_sql_exception
     */
    public function updateLastUsedAt(string $cardId): bool
    {
        return $this->executeUpdate(
            'rfid',
            'card_id',
            $cardId,
            ['`last_used_at` = CURRENT_TIMESTAMP'],
            idType: "s"
        );
    }

    /**
     * Delete a member's activated card registrations.
     * @param   int     $congressusMemberId     Member whose registrations to delete
     * @throws  mysqli_sql_exception
     */
    public function deleteMembersActivatedRegistrations(int $congressusMemberId): bool
    {
        return $this->executeDelete(
            'rfid',
            'congressus_member_id',
            $congressusMemberId,
            ['`is_email_confirmed` = TRUE']
        );
    }

    /**
     * @param int $congressusMemberId
     * @param string $reason
     * @param bool $hasAccess
     * @return int
     * @throws mysqli_sql_exception
     */
    public function addMemberAccess(int $congressusMemberId, string $reason, bool $hasAccess = false): int
    {
        return $this->executeCreate(
            "access",
            ["`congressus_member_id`", "`reason`", "`has_access`"],
            [
                $congressusMemberId,
                $reason,
                $hasAccess,
            ],
            "isi"
        );
    }

    /**
     * @return array<MemberAccess>
     * @throws mysqli_sql_exception
     */
    public function getAllMemberAccesses(): array
    {
        $rows = $this->executeReadAll(
            "SELECT `congressus_member_id`, `reason`, `has_access`
            FROM `access`"
        );

        $memberAccesses = [];

        foreach ($rows as $row) {
            $memberAccesses[] = new MemberAccess(
                (int) $row['congressus_member_id'],
                $row['reason'],
                (bool)$row['has_access']
            );
        }
        return $memberAccesses;
    }

    /**
     * @param int $congressusMemberId
     * @return MemberAccess|null
     * @throws Exception
     * @throws mysqli_sql_exception
     */
    public function getMemberAccesses(int $congressusMemberId): ?MemberAccess
    {
        if ($congressusMemberId <= 0){
            return null;
        }

        $row = $this->executeReadOne(
            "SELECT `congressus_member_id`, `reason`, `has_access` 
            FROM `access` 
            WHERE `congressus_member_id` = ?",
            [$congressusMemberId],
            "i"
        );

        if ($row === null){
            throw new Exception("Member access for $congressusMemberId not found");
        }

        return new MemberAccess(
            $congressusMemberId,
            $row['reason'],
            (bool)$row['has_access']
        );
    }

    /**
     * @param int $congressusMemberId
     * @param string|null $reason
     * @param bool|null $hasAccess
     * @param bool $clearReason
     * @return bool
     * @throws mysqli_sql_exception
     */
    public function updateMemberAccess(
        int $congressusMemberId,
        ?string $reason = null,
        ?bool $hasAccess = null,
        bool $clearReason = false
    ): bool {
        $fields = [];
        $params = [];
        $types  = '';

        if ($clearReason) {
            $fields[] = 'reason = NULL';
        } elseif ($reason !== null) {
            $fields[] = 'reason = ?';
            $params[] = $reason;
            $types   .= 's';
        }

        if ($hasAccess !== null) {
            $fields[] = 'has_access = ?';
            $params[] = $hasAccess;
            $types   .= 'i';
        }

        return $this->executeUpdate('access', 'congressus_member_id', $congressusMemberId, $fields, $params, $types);
    }

    /**
     * @param int $congressusMemberId
     * @param string $reason
     * @return bool
     * @throws mysqli_sql_exception
     * @throws Exception
     */
    public function revokeMemberAccess(int $congressusMemberId, string $reason): bool
    {
        if ($reason === "") {
            throw new Exception("Revoke member access reason cannot be empty");
        }
        return $this->updateMemberAccess($congressusMemberId, $reason, false);
    }

    /**
     * @param int $congressusMemberId
     * @param string $reason
     * @return bool
     * @throws mysqli_sql_exception
     * @throws Exception
     */
    public function approveMemberAccess(int $congressusMemberId, string $reason): bool
    {
        if ($reason === "") {
            throw new Exception("Approve member access reason cannot be empty");
        }
        return $this->updateMemberAccess($congressusMemberId, $reason, true);
    }

    /**
     * @param int $congressusMemberId
     * @return bool
     * @throws mysqli_sql_exception
     */
    public function deleteMemberAccess(int $congressusMemberId): bool
    {
        return $this->executeDelete("access", "congressus_member_id", $congressusMemberId);
    }
}
