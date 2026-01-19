<?php

namespace Compucie\DatabaseTest;

use Compucie\Database\Settings\SettingsTableManager;
use mysqli;

final class TestableSettingsDatabaseManager extends SettingsTableManager
{
    public function client(): mysqli
    {
        return $this->getClient();
    }
}