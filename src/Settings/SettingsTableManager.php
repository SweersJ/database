<?php

namespace Compucie\Database\Settings;

use Compucie\Database\DatabaseManager;
use Compucie\Database\Settings\Exceptions\NoSettingsException;
use Compucie\Database\Settings\Model\Settings;

class SettingsTableManager extends DatabaseManager
{

    public function createTables(): void
    {
        $statement = $this->getClient()->prepare(
            "CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `temp_student_number_login` TINYINT DEFAULT FALSE,
                PRIMARY KEY (`id`)
            );"
        );
        if ($statement){
            $statement->execute();
            $statement->close();
        }
    }

    public function getSettings(int $id): Settings
    {
        $row = $this->executeReadOne(
            "SELECT *
         FROM `settings`
         WHERE `id` = ?",
            [$id],
            "i"
        );

        if ($row === null) {
            throw new NoSettingsException();
        }

        return new Settings(
            (string) $row['id'],
            (bool) $row['is_email_confirmed']
        );
    }

    public function updateTempStudentNumberLogin(int $id, bool $newStatus): bool
    {
        return $this->executeUpdate(
          'settings',
          'id',
          $id,
          ['`temp_student_number_login` = ?'],
          [$newStatus],
          "i"
        );
    }

    public function addSettings(bool $tempStudentNumberLogin): int
    {
        return $this->executeCreate(
            'settings',
            ['temp_student_number_login'],
            [$tempStudentNumberLogin],
            "i"
        );
    }
}