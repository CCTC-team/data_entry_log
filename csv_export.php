<?php

require_once APP_PATH_DOCROOT . "/Config/init_project.php";
$lang = Language::getLanguage('English');

global $Proj;
$project_id = $module->getProjectId();
global $module;

// This page is not a config.json link, so the framework does not run redcap_module_link_check_display()
// for it. Run that check here, before any data is read, so the export follows the same rights as the log page.
if (!$module->redcap_module_link_check_display($project_id, ['url' => 'index.php'])) {
    http_response_code(403);
    exit("You do not have permission to access this page.");
}

$modName = $module->getModuleDirectoryName();

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/DataEntryLogModule.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/GetDbData.php";

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/UserDag.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/DataChange.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Rendering.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/InputValidator.php";
require_once APP_PATH_DOCROOT . "/Classes/Records.php";
require_once APP_PATH_DOCROOT . "/Classes/RCView.php";
require_once APP_PATH_DOCROOT . "/Classes/DateTimeRC.php";

use CCTC\DataEntryLogModule\GetDbData;
use CCTC\DataEntryLogModule\DataEntryLogModule;
use CCTC\DataEntryLogModule\InputValidator;
use CCTC\DataEntryLogModule\Utility;

// Maximum number of records to export at once (prevents memory/timeout issues)
$maxExportRecords = 1000000;

// Increase memory limit in case needed for intensive processing
//System::increaseMemory(2048);

// File: getparams.php
/** @var $skipCount */
/** @var $pageSize */
/** @var $dataDirection */
/** @var $recordId */
/** @var $minDateDb */
/** @var $maxDateDb */
/** @var $dagUser */
/** @var $editor */
/** @var $dataevnt */
/** @var $datagrp */
/** @var $dataarm */
/** @var $datainstance */
/** @var $logDescription */
/** @var $changeReason */
/** @var $datafrm */
/** @var $fieldNameOrLabel */
/** @var $newDataValue */

include "getparams.php";

//run the query using the same params as on the index page when the query called
//runForExport means it only returns the actual data requested (and not data for filters)

//use the export_type param to determine what to export and adjust params accordingly
// Validate export_type against allowed values
$allowedExportTypes = ['current_page', 'all_pages', 'everything'];
$exportType = InputValidator::getEnumParam('export_type', $allowedExportTypes, 'current_page');

//if current_page then keep the params already captured from getparams.php

//change paging to include everything
if($exportType == 'all_pages' || $exportType == 'everything') {
    $skipCount = 0;
    $pageSize = $maxExportRecords;
}

//set all filters to null
if($exportType == 'everything') {
    $recordId = null;
    $minDateDb = null;
    $maxDateDb = null;
    $editor = null;
    $datagrp = null;
    $dataevnt = null;
    $dataarm = null;
    $datainstance = null;
    $datafrm = null;
    $fieldNameOrLabel = null;
    $newDataValue = null;
    $logDescription = null;
    $changeReason = null;

    //includes items with no timestamp
    $incNoTimestamp = "checked";
}

//get the user dag membership
$user = $module->getUser();
$username = $user->getUsername();

$userDags = GetDbData::GetUserDags(false, $username);
$dagUser = count($userDags) > 0 ? $username : null;

//if given, get the regex where fields should always be ignored
$excludeFieldNameRegex = $module->getProjectSetting('always-exclude-fields-with-regex');

//run the stored proc
$result = GetDbData::GetDataLogsFromSP(
    $skipCount, $pageSize, $dataDirection, $recordId, $minDateDb, $maxDateDb, $dagUser,
    $editor, $dataevnt, $datagrp, $dataarm, $datainstance, $logDescription, $changeReason, $datafrm,
    $fieldNameOrLabel, $newDataValue, $excludeFieldNameRegex);

// Set headers
$headers = array("timestamp","user name","record","group id", "group name", "event id", "event name",
    "arm number", "arm name", "instance", "form name", "field", "field label", "value",
    "reason for change", "action");

// Set file name and path
// The random part stops two exports in the same second from sharing a file
$filename = APP_PATH_TEMP . date("YmdHis") . '_' . PROJECT_ID . '_' . bin2hex(random_bytes(8)) . '_data_entry_logs.csv';

// Begin writing file from query result
$fp = fopen($filename, 'w');

if ($fp && $result)
{
    try {

        $delim = User::getCsvDelimiter();

        // Write headers to file
        fputcsv($fp, $headers, $delim, '"', '\\');

        // Set values for this row and write to file
        foreach ($result["dataChanges"] as $dc) {

            //timestamp
            $row["ts"] =
                $dc->timestamp == null || $dc->timestamp == ""
                    ? ""
                    : DateTime::createFromFormat('YmdHis', $dc->timestamp)->format('Y-m-d H:i:s');

            //add rest of columns
            $row["user name"] = $dc->editor;
            $row["record"] = $dc->recordId;
            $row["group id"] = $dc->groupId;
            $row["group name"] = $dc->groupName;
            $row["event id"] = $dc->eventId;
            $row["event name"] = $dc->eventName;
            $row["arm number"] = $dc->armNum;
            $row["arm name"] = $dc->armName;
            $row["instance"] = $dc->instance;
            $row["form name"] = $dc->formName;
            $row["field"] = $dc->fieldName;
            $row["field label"] = $dc->fieldLabel;
            // line breaks are replaced as REDCap does for its CSV exports, so each log entry stays on one row
            $row["value"] = str_replace(["\r\n", "\n", "\r"], ["  ", "  ", ""], stripcslashes((string)$dc->newValue));
            $row["reason for change"] = $dc->reason;
            $row["action"] = $dc->description;

            fputcsv($fp, array_map([Utility::class, 'CsvSafe'], $row), $delim, '"', '\\');
        }

        // Close file for writing
        fclose($fp);

        // Open file for downloading
        $app_title = strip_tags(label_decode($Proj->project['app_title']));
        $download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_DataEntryLog_" . date("Y-m-d_Hi") . ".csv";

        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$download_filename");

        // Open file for reading and output to user
        $fp = fopen($filename, 'rb');
        print addBOMtoUTF8(fread($fp, filesize($filename)));

        // Close file and delete it from temp directory
        fclose($fp);
        unlink($filename);

        // Logging
        Logging::logEvent("", Logging::getLogEventTable($project_id),"MANAGE",$project_id,"project_id = $project_id", "Export data entry logging (custom)");

    } catch (Exception $e) {
        if (file_exists($filename)) {
            unlink($filename);
        }
        $module->log("ex: ". $e->getMessage());
    }
}
else
{
    //error
	print $lang['global_01'];
}
