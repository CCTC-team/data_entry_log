<?php

namespace CCTC\DataEntryLogModule;

use REDCap;
use ExternalModules\AbstractExternalModule;

class DataEntryLogModule extends AbstractExternalModule {

    /**
     * Check whether the user has permission to view logs and deny access if not.
     * Uses getUser() to properly handle impersonated users.
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        $user = $this->getUser();
        $rights = $user->getRights();
        if($rights['data_logging']) {
            return $link;
        } else {
            return 0;
        }
    }

    public function validateSettings($settings): ?string
    {
        if (array_key_exists("max-days-all-records", $settings)) {
            if(!is_numeric($settings['max-days-all-records']) || $settings['max-days-all-records'] < 1 || $settings['max-days-all-records'] > 365) {
                return "The maximum number of days permitted should be a number between 1 and 365";
            }
        }

        return null;
    }

    private function exec($query): void
    {
        db_query($query);
    }

    private function execFromFile($file): void
    {
        $sql = file_get_contents(dirname(__FILE__) . "/sql-setup/$file");
        db_query($sql);
    }

    public function redcap_module_system_enable($version): void
    {
        $this->dropAllDELogObjects();
        $this->createAllDELogObjects();
    }

    public function redcap_module_system_disable($version): void
    {
        $this->dropAllDELogObjects();
    }

    /**
     * Drops all database objects associated with the data entry log module.
     */
    private function dropAllDELogObjects(): void
    {
        $this->exec("drop function if exists rh_split_string;");
        $this->exec("drop function if exists GetInsertParts;");
        $this->exec("drop function if exists GetUpdateParts;");
        $this->exec("drop function if exists GetDeleteParts;");
        $this->exec("drop procedure if exists GetDataEntryLogs;");
    }

    /**
     * Creates all database objects required by the data entry log module.
     */
    private function createAllDELogObjects(): void
    {
        $this->execFromFile("0000__create_helper_functions.sql");
        $this->execFromFile("0010__create_GetInsertParts_function.sql");
        $this->execFromFile("0020__create_GetUpdateParts_function.sql");
        $this->execFromFile("0030__create_GetDeleteParts_function.sql");
        $this->execFromFile("0100__create_DataEntryLog_proc.sql");
    }
}
