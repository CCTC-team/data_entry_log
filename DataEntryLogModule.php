<?php

namespace CCTC\DataEntryLogModule;

use REDCap;
use ExternalModules\AbstractExternalModule;

class DataEntryLogModule extends AbstractExternalModule {

    //check whether the user has permission to view logs and deny access if not
    public function redcap_module_link_check_display($project_id, $link)
    {
        //replaces the below for #105
        $user = $this->getUser();
        $rights = $user->getRights();
        if($rights['data_logging']) {
            return $link;
        } else {
            return 0;
        }

//        replace this which doesn't take account of the impersonated user
//        $this_user = USERID;
//        $rights = REDCap::getUserRights($this_user);
//        if ($rights[$this_user]['data_logging']){
//            return $link;
//        } else if (SUPER_USER){
//            return $link;
//        } else {
//            return 0;
//        }
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

    function exec($query) : void
    {
        db_query($query);
    }

    function execFromFile($file) : void
    {
        $sql = file_get_contents(dirname(__FILE__) . "/sql-setup/$file");
        db_query($sql);
    }

    function redcap_module_system_enable($version): void
    {
        self::dropAllDELogObjects();
        self::createAllDELogObjects();
    }

    function redcap_module_system_disable($version): void
    {
        self::dropAllDELogObjects();
    }


    //just drops all the objects associated with the data entry log module
    function dropAllDELogObjects(): void
    {
        self::exec("drop function if exists rh_split_string;");
        self::exec("drop function if exists GetInsertParts;");
        self::exec("drop function if exists GetUpdateParts;");
        self::exec("drop function if exists GetDeleteParts;");
        self::exec("drop procedure if exists GetDataEntryLogs;");
    }


    function createAllDELogObjects(): void
    {
        self::execFromFile("0000__create_helper_functions.sql");
        self::execFromFile("0010__create_GetInsertParts_function.sql");
        self::execFromFile("0020__create_GetUpdateParts_function.sql");
        self::execFromFile("0030__create_GetDeleteParts_function.sql");
        self::execFromFile("0100__create_DataEntryLog_proc.sql");
    }
}
