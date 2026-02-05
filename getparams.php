<?php

global $module;
$modName = $module->getModuleDirectoryName();

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/InputValidator.php";

use CCTC\DataEntryLogModule\Utility;
use CCTC\DataEntryLogModule\InputValidator;

//set the helper dates for use in the quick links
$oneDayAgo = Utility::NowAdjusted('-1 days');
$oneWeekAgo = Utility::NowAdjusted('-7 days');
$oneMonthAgo = Utility::NowAdjusted('-1 months');
$oneYearAgo = Utility::NowAdjusted('-1 years');

// Allowed values for enum validation
$allowedTimeFilters = ['customrange', 'onedayago', 'oneweekago', 'onemonthago', 'oneyearago'];
$allowedDirections = ['asc', 'desc'];
$allowedPageSizes = [10, 25, 50, 100, 250, 500];

// Get the user's date format for validation
$userDateFormat = Utility::UserDateTimeFormatNoSeconds();

//get form values - sanitized string parameters
$recordId = InputValidator::getStringParam('record_id', "");
$editor = InputValidator::getStringParam('editor', "");
$datafrm = InputValidator::getStringParamOrNull('datafrm');
$fieldnamelabel = InputValidator::getStringParamOrNull('fldnamelbl');
$newdatavalue = InputValidator::getStringParamOrNull('newdatavalue');
$logdescription = InputValidator::getStringParamOrNull('logdescription');
$changereason = InputValidator::getStringParamOrNull('changereason');

// Date parameters - validate format or use defaults
$minDate = $oneWeekAgo;
if (isset($_GET['startdt']) && $_GET['startdt'] !== "") {
    $validatedMinDate = InputValidator::validateDateString($_GET['startdt'], $userDateFormat);
    if ($validatedMinDate !== null) {
        $minDate = $validatedMinDate;
    }
}

$maxDate = null;
if (isset($_GET['enddt']) && $_GET['enddt'] !== "") {
    $validatedMaxDate = InputValidator::validateDateString($_GET['enddt'], $userDateFormat);
    if ($validatedMaxDate !== null) {
        $maxDate = $validatedMaxDate;
    }
}

// Time filter - validate against allowed values
$defaultTimeFilter = InputValidator::getEnumParam('defaulttimefilter', $allowedTimeFilters, 'oneweekago');
$customActive = $defaultTimeFilter === "customrange" ? "active" : "";
$dayActive = $defaultTimeFilter === "onedayago" ? "active" : "";
$weekActive = $defaultTimeFilter === "oneweekago" ? "active" : "";
$monthActive = $defaultTimeFilter === "onemonthago" ? "active" : "";
$yearActive = $defaultTimeFilter === "oneyearago" ? "active" : "";

// Direction - validate against allowed values
$dataDirection = InputValidator::getEnumParam('retdirection', $allowedDirections, 'desc');

// Page size - validate as integer within allowed values, default to 25
$pageSize = InputValidator::getIntParam('pagesize', 25, 1, 1000);
// Snap to nearest allowed value if not in the allowed list
if (!in_array($pageSize, $allowedPageSizes)) {
    $pageSize = 25;
}

// Page number - validate as non-negative integer
$pageNum = InputValidator::getIntParam('pagenum', 0, 0, 100000);

// Group - can be integer or empty string
$datagroup = "";
if (isset($_GET['datagrp']) && $_GET['datagrp'] !== "") {
    $validatedGroup = InputValidator::validateIntOrNull($_GET['datagrp'], 0);
    $datagroup = $validatedGroup !== null ? (string)$validatedGroup : "";
}

// Event - validate as integer or null
$dataevnt = InputValidator::getIntParamOrNull('dataevnt', 0);

// Arm - validate as integer or null
$dataarm = InputValidator::getIntParamOrNull('dataarm', 0);

// Instance - validate as integer or null
$datainstance = InputValidator::getIntParamOrNull('datainst', 0);

// Include no timestamp checkbox - validate against expected value
$incNoTimestamp = "";
if (isset($_GET['inc-no-timestamp'])) {
    $incNoTimestamp = $_GET['inc-no-timestamp'] === "yes" ? "checked" : "";
}

// Calculate derived values
$skipCount = (int)$pageSize * (int)$pageNum;
$minDateDb = Utility::DateStringToDbFormat($minDate);
$maxDateDb = Utility::DateStringToDbFormat($maxDate);
