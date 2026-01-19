<?php

namespace Compucie\DatabaseTest;

use Compucie\Database\Settings\SettingsDatabaseManager;
use mysqli;

final class TestableSettingsDatabaseManager extends SettingsDatabaseManager
{
    public function client(): mysqli
    {
        return $this->getClient();
    }
}