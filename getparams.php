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

//get form values - sanitized string parameters
$recordId = InputValidator::getStringParam('record_id', "");
$editor = InputValidator::getStringParam('editor', "");
$datafrm = InputValidator::getStringParamOrNull('datafrm');
$fieldNameOrLabel = InputValidator::getStringParamOrNull('fldnamelbl');
$newDataValue = InputValidator::getStringParamOrNull('newdatavalue');
$logDescription = InputValidator::getStringParamOrNull('logdescription');
$changeReason = InputValidator::getStringParamOrNull('changereason');

//dates keep what the user typed so that a cleared date means no limit, as on the log page
$minDate = $oneWeekAgo;
if (isset($_GET['startdt'])) {
    $minDate = InputValidator::sanitizeString($_GET['startdt']);
}

$maxDate = null;
if (isset($_GET['enddt'])) {
    $maxDate = InputValidator::sanitizeString($_GET['enddt']);
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

//-1 selects entries with no group, arm or instance
$datagrp = isset($_GET['datagrp']) ? InputValidator::validateIntOrNull($_GET['datagrp'], -1) : null;
$dataevnt = InputValidator::getIntParamOrNull('dataevnt', -1);
$dataarm = InputValidator::getIntParamOrNull('dataarm', -1);
$datainstance = InputValidator::getIntParamOrNull('datainst', -1);

// Include no timestamp checkbox - validate against expected value
$incNoTimestamp = "";
if (isset($_GET['inc-no-timestamp'])) {
    $incNoTimestamp = $_GET['inc-no-timestamp'] === "yes" ? "checked" : "";
}

// Calculate derived values
$skipCount = (int)$pageSize * (int)$pageNum;
$minDateDb = Utility::DateStringToDbFormat($minDate);
$maxDateDb = Utility::DateStringToDbFormat($maxDate);
