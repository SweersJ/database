<?php

use Compucie\Database\Settings\Exceptions\NoSettingsException;
use Compucie\DatabaseTest\DbTestHelper;
use Compucie\DatabaseTest\TestableSettingsDatabaseManager;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

class SettingsDatabaseManagerTest extends TestCase
{
    private TestableSettingsDatabaseManager $dbm;
    protected DbTestHelper $dbh;

    protected function setUp(): void
    {
        $env = parse_ini_file(".env", true);
        if ($env) {
            $this->dbm = new TestableSettingsDatabaseManager($env['settings']);
            $this->dbm->createTables();
            $this->dbh = new DbTestHelper($this->dbm->client());

            $this->dbh->truncateTables(['settings']);
        }
    }

    protected function tearDown(): void
    {
        $this->dbh->truncateTables(['settings']);
    }

    /**
     * @throws NoSettingsException
     */
    public function testAddAndGetSettings(): void
    {
        $ok = $this->dbm->addSettings(1, true);
        $this->assertSame(1, $ok);

        $settings = $this->dbm->getSettings(1);

        $this->assertSame(1, $settings->getId());
        $this->assertFalse($settings->isTempStudentNumberLogin());
    }

    /**
     * @throws NoSettingsException
     */
    public function testUpdateTempStudentNumberLogin(): void
    {
        $this->assertSame(1, $this->dbm->addSettings(1, false));

        $this->assertTrue($this->dbm->updateTempStudentNumberLogin(1, true));

        $settings = $this->dbm->getSettings(1);
        $this->assertFalse($settings->isTempStudentNumberLogin());
    }

    public function testGetSettingsThrowsWhenMissing(): void
    {
        $this->expectException(NoSettingsException::class);
        $this->dbm->getSettings(999);
    }

    /**
     * @throws NoSettingsException
     */
    public function testGetLastSettingsByUserId(): void
    {
        $ok = $this->dbm->addSettings(1,true);
        $this->assertSame(1, $ok);

        $ok = $this->dbm->addSettings(1,true);
        $this->assertSame(2, $ok);

        $settings = $this->dbm->getLastSettingsByUserId(1);

        assertSame(2, $settings->getId());
    }
}