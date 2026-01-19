<?php

namespace Compucie\DatabaseTest;

use Compucie\Database\Member\Exceptions\ActivationTokenNotFoundException;
use Compucie\Database\Member\Exceptions\CardNotRegisteredException;
use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertGreaterThan;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

class MemberDatabaseManagerTest extends TestCase
{
    private TestableMemberDatabaseManager $dbm;
    protected DbTestHelper $dbh;

    protected function setUp(): void
    {
        $env = parse_ini_file(".env", true);
        if ($env) {
            $this->dbm = new TestableMemberDatabaseManager($env['member']);
            $this->dbm->createTables();
            $this->dbh = new DbTestHelper($this->dbm->client());

            $this->dbh->truncateTables(['screen_birthdays', 'rfid', 'access']);
        }
    }

    protected function tearDown(): void
    {
        $this->dbh->truncateTables(['screen_birthdays','rfid', 'access']);
    }

    public function testGetMemberIdsWithBirthdayToday(): void
    {
        //implement insert
        assertSame(0, $this->dbh->rowCount('screen_birthdays')); //change to 1
    }

    public function testGetCongressusMemberIdFromCardIdNotFound(): void
    {
        $this->expectException(CardNotRegisteredException::class);
        $this->expectExceptionMessage("Card is not registered.");
        $this->dbm->getCongressusMemberIdFromCardId("deadbeaf");
        assertSame(0, $this->dbh->rowCount('rfid'));
    }

    /**
     * @return array{
     *     hashedActivationToken: string,
     *     activationTokenValidUntil: DateTime
     * }
     * @throws RandomException
     */
    private function generateActivationTokenData(): array
    {
        $activationToken = rtrim(
            strtr(base64_encode(random_bytes(32)), '+/', '-_'),
            '='
        );

        return [
            'hashedActivationToken' => hash('sha256', $activationToken),
            'activationTokenValidUntil' => (new DateTime())->modify('+7 days'),
        ];
    }

    /**
     * @throws CardNotRegisteredException
     * @throws RandomException
     */
    public function testGetCongressusMemberIdFromCardId(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil']);
        $congressusMemberId = $this->dbm->getCongressusMemberIdFromCardId("deadbeaf");
        assertSame(1, $this->dbh->rowCount('rfid'));
        assertSame(123, $congressusMemberId);
    }

    /**
     * @throws RandomException
     */
    public function testIsRfidCardActivated(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil'], true);
        assertSame(1, $this->dbh->rowCount('rfid'));
        $registered = $this->dbm->isRfidCardActivated("deadbeaf");

        assertTrue($registered);
    }

    public function testIsRfidCardActivatedNot(): void
    {
        assertSame(0, $this->dbh->rowCount('rfid'));
        $registered = $this->dbm->isRfidCardActivated("deadbeaf");

        assertFalse($registered);
    }

    /**
     * @throws RandomException
     */
    public function testInsertRfid(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil']);

        assertSame(1, $this->dbh->rowCount('rfid'));
        assertSame(1, $this->dbh->rowCount(
            'rfid',
            "card_id = 'deadbeaf' AND congressus_member_id = 123 AND is_email_confirmed = 0"
        ));
    }

    /**
     * @throws RandomException
     */
    public function testInsertRfidEmailConfirmed(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil'], true);

        assertSame(1, $this->dbh->rowCount('rfid'));
        assertSame(1, $this->dbh->rowCount(
            'rfid',
            "card_id = 'deadbeaf' AND congressus_member_id = 123 AND is_email_confirmed = 1"
        ));
    }

    /**
     * @throws RandomException
     */
    public function testDeleteMembersActivatedRegistrations(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil'], true);

        assertSame(1, $this->dbh->rowCount('rfid'));

        $this->dbm->deleteMembersActivatedRegistrations(123);

        assertSame(0, $this->dbh->rowCount('rfid'));
    }

    /**
     * @throws RandomException
     * @throws ActivationTokenNotFoundException
     */
    public function testGetActivationTokenInfo(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil']);

        $activationTokenInfo = $this->dbm->getActivationTokenInfo("deadbeaf");

        assertSame($tokenData['hashedActivationToken'], $activationTokenInfo['hashed_activation_token']);
        assertSame($tokenData['activationTokenValidUntil']->format("Y-m-d H:i:s"), $activationTokenInfo['activation_token_valid_until']->format("Y-m-d H:i:s"));
    }

    /**
     * @throws ActivationTokenNotFoundException
     */
    public function testGetActivationTokenInfoActivationTokenNotFoundException(): void
    {
        $this->expectException(ActivationTokenNotFoundException::class);
        $this->dbm->getActivationTokenInfo("deadbeaf");
    }

    /**
     * @throws RandomException
     */
    public function testActivateCard(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil']);
        assertSame(1,$this->dbh->rowCount(
            'rfid',
            "card_id = 'deadbeaf' AND congressus_member_id = 123 AND is_email_confirmed = 0 AND hashed_activation_token IS NOT NULL AND activation_token_valid_until IS NOT NULL"
        ));

        $this->dbm->activateCard("deadbeaf");
        assertSame(1,$this->dbh->rowCount(
            'rfid',
            "card_id = 'deadbeaf' 
            AND congressus_member_id = 123 
            AND is_email_confirmed = 1 
            AND hashed_activation_token IS NULL 
            AND activation_token_valid_until IS NULL"
        ));
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function testUpdateLastUsedAt(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid(
            "deadbeaf",
            123,
            $tokenData['hashedActivationToken'],
            $tokenData['activationTokenValidUntil']
        );

        assertSame(
            1,
            $this->dbh->rowCount(
                'rfid',
                "card_id = 'deadbeaf'
             AND congressus_member_id = 123
             AND is_email_confirmed = 0
             AND hashed_activation_token IS NOT NULL
             AND activation_token_valid_until IS NOT NULL"
            )
        );

        $before = $this->dbh->fetchOne(
            "SELECT `last_used_at` FROM `rfid` WHERE `card_id` = 'deadbeaf'"
        );
        assertNotNull($before);
        $beforeDt = new DateTime((string)$before);

        sleep(1);

        $updated = $this->dbm->updateLastUsedAt("deadbeaf");
        assertTrue($updated);

        $after = $this->dbh->fetchOne(
            "SELECT `last_used_at` FROM `rfid` WHERE `card_id` = 'deadbeaf'"
        );
        assertNotNull($after);
        $afterDt = new DateTime((string)$after);

        assertGreaterThan($beforeDt->getTimestamp(), $afterDt->getTimestamp());

        assertSame(
            1,
            $this->dbh->rowCount(
                'rfid',
                "card_id = 'deadbeaf'
             AND congressus_member_id = 123
             AND is_email_confirmed = 0
             AND hashed_activation_token IS NOT NULL
             AND activation_token_valid_until IS NOT NULL"
            )
        );
    }

    /**
     * @throws RandomException
     * @throws CardNotRegisteredException
     */
    public function testGetRfid(): void
    {
        $tokenData = $this->generateActivationTokenData();

        $this->dbm->insertRfid("deadbeaf", 123, $tokenData['hashedActivationToken'], $tokenData['activationTokenValidUntil']);
        assertSame(1,$this->dbh->rowCount(
            'rfid',
            "card_id = 'deadbeaf' AND congressus_member_id = 123 AND is_email_confirmed = 0 AND hashed_activation_token IS NOT NULL AND activation_token_valid_until IS NOT NULL"
        ));

        $rfid = $this->dbm->getRfid(123);
        assertNotNull($rfid);
    }
}
